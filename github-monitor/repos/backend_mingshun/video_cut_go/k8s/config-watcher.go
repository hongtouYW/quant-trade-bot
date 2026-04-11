package k8s

import (
	"context"
	"fmt"
	"log"
	"strconv"
	"sync"
	"time"
	"video_cut_go/metrics"

	v1 "k8s.io/api/core/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/fields"
	"k8s.io/apimachinery/pkg/watch"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/rest"
)

type HandlerFunc func(event watch.EventType, cm *v1.ConfigMap)

type ConfigWatcher struct {
	ctx       context.Context
	clientSet *kubernetes.Clientset

	revisions  map[string]string
	handlers   map[string]HandlerFunc
	handlerMux sync.RWMutex
}

func NewConfigWatcher(ctx context.Context) (*ConfigWatcher, error) {
	config, err := rest.InClusterConfig()
	if err != nil {
		return nil, fmt.Errorf("failed to create in-cluster config: %v", err)
	}

	clientSet, err := kubernetes.NewForConfig(config)
	if err != nil {
		return nil, fmt.Errorf("failed to create clientset: %v", err)
	}

	return &ConfigWatcher{
		ctx:       ctx,
		clientSet: clientSet,
		revisions: make(map[string]string),
		handlers:  make(map[string]HandlerFunc),
	}, nil
}

func (w *ConfigWatcher) Register(configName string, handler HandlerFunc) error {
	w.handlerMux.Lock()
	defer w.handlerMux.Unlock()

	cm, err := w.getConfigMap(configName)
	if err != nil {
		return fmt.Errorf("failed to get latest resourceVersion for %s: %v", configName, err)
	}

	w.handlers[configName] = handler
	w.revisions[configName] = cm.ResourceVersion

	handler(watch.Added, cm)
	metrics.K8sConfigVersion.WithLabelValues(configName).Set(getConfigMapNumericVersion(cm))

	go w.retryWatch(configName)
	return nil
}

func (w *ConfigWatcher) retryWatch(configName string) {
	defer log.Println("watcher stopped:", configName)

	for {
		if err := w.watch(configName); err != nil {
			log.Printf("fail to watch ConfigMap [%s]: %v", configName, err)
		}

		select {
		case <-w.ctx.Done():
			log.Println("exit watcher watch due to context done:", configName)
			return
		case <-time.After(30 * time.Second):
			log.Println("retrying watch:", configName)
		}

		metrics.K8sConfigReconnect.WithLabelValues(configName).Inc()
	}
}

func (w *ConfigWatcher) watch(configName string) error {
	watcher, err := w.clientSet.CoreV1().ConfigMaps(Namespace).Watch(w.ctx, metav1.ListOptions{
		FieldSelector:   fields.OneTermEqualSelector("metadata.name", configName).String(),
		Watch:           true,
		ResourceVersion: w.revisions[configName],
	})
	if err != nil {
		return err
	}
	defer func() {
		watcher.Stop()
		log.Printf("[%s] watch stopped", configName)
	}()

	log.Printf("[%s] start new config watch", configName)
	for {
		select {
		case <-w.ctx.Done():
			log.Printf("[%s] watch stopped due to ctx done", configName)
			return nil
		case event, ok := <-watcher.ResultChan():
			if !ok {
				log.Printf("[%s] watch channel closed", configName)

				log.Printf("[%s] trying manually fetch latest ConfigMap", configName)
				cm, err := w.getConfigMap(configName)
				if err != nil {
					log.Printf("[%s] manually fetch ConfigMap error: %s", configName, err)
					return nil
				}

				w.runHandler(configName, event.Type, cm)
				return nil
			}

			cm, ok := event.Object.(*v1.ConfigMap)
			if !ok {
				continue
			}

			switch event.Type {
			case watch.Added, watch.Modified:
				w.handlerMux.Lock()
				w.revisions[configName] = cm.ResourceVersion
				w.handlerMux.Unlock()
			case watch.Error:
				status, ok := event.Object.(*metav1.Status)
				if !ok {
					log.Printf("[%s] error watching ConfigMap, but event.Object is not *metav1.Status: %v", configName, event.Object)
					continue
				}
				log.Printf("[%s] Watch error. Status: %s, Message: %s, Reason: %s, Code: %d",
					configName, status.Status, status.Message, status.Reason, status.Code)

				// 针对特定的错误类型进行处理，例如 ResourceVersion 过期
				if status.Code == 410 && status.Reason == metav1.StatusReasonGone {
					log.Printf("[%s] ResourceVersion is too old. Will re-fetch latest ConfigMap on next retry.", configName)
					// 在这里，你可能需要一个机制来告知 retryWatch 函数，下次重试时需要重新获取 ResourceVersion
					// 或者就像之前建议的，在 watch 循环外部的 retryWatch 逻辑中处理 ResourceVersion 刷新
					return fmt.Errorf("resource version too old: %s", status.Message) // 返回错误让外层处理
				}

				continue
			}

			w.runHandler(configName, event.Type, cm)
		}
	}
}

func (w *ConfigWatcher) getConfigMap(configName string) (*v1.ConfigMap, error) {
	ctx, cancel := context.WithTimeout(w.ctx, 5*time.Second)
	defer cancel()

	cm, err := w.clientSet.CoreV1().ConfigMaps(Namespace).Get(ctx, configName, metav1.GetOptions{})
	if err != nil {
		return nil, fmt.Errorf("failed to get ConfigMap %s: %v", configName, err)
	}
	return cm, nil
}

func (w *ConfigWatcher) runHandler(configName string, eventType watch.EventType, cm *v1.ConfigMap) {
	w.handlerMux.RLock()
	handler, exists := w.handlers[configName]
	w.handlerMux.RUnlock()

	if exists {
		handler(eventType, cm)
		metrics.K8sConfigVersion.WithLabelValues(configName).Set(getConfigMapNumericVersion(cm))
	}
}

func getConfigMapNumericVersion(cm *v1.ConfigMap) float64 {
	// 尝试解析 resourceVersion
	if cm.ResourceVersion != "" {
		if rvFloat, err := strconv.ParseFloat(cm.ResourceVersion, 64); err == nil {
			return rvFloat
		}
		// 如果 resourceVersion 存在但无法解析为 float64，则记录警告并回退
		log.Printf("Warning: ConfigMap %s/%s resourceVersion '%s' is not a valid float64, falling back to creationTimestamp.",
			cm.Namespace, cm.Name, cm.ResourceVersion)
	}

	// 如果 resourceVersion 不存在或无法解析，则使用 creationTimestamp
	if !cm.CreationTimestamp.IsZero() {
		return float64(cm.CreationTimestamp.Unix())
	}

	// 如果两者都无效，返回0或一个指示错误的默认值
	log.Printf("Error: ConfigMap %s/%s has neither a valid resourceVersion nor a creationTimestamp. Returning 0 for version.",
		cm.Namespace, cm.Name)
	return 0.0
}
