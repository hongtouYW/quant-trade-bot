package main

import (
	"context"
	"fmt"
	"gopkg.in/yaml.v3"
	corev1 "k8s.io/api/core/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/fields"
	"k8s.io/apimachinery/pkg/watch"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/rest"
	"log"
	"os"
	"sync"
	"time"
	"video_cut_go/k8s"
)

type ServiceServer struct {
	IP         string `yaml:"IP"`
	Port       uint16 `yaml:"port"`
	Username   string `yaml:"username"`
	UploadPath string `yaml:"uploadPath"`
	ApiBaseUrl string `yaml:"apiBaseUrl"`
}

type AiSubtitleServer struct {
	// ServerGroup map[GroupName]*sender.AiSubtitleServer.
	// Must have default group
	ServerGroup map[string]*ServiceServer `yaml:"serverGroup"`
	// Pods map[PodName]ServerGroupName
	Pods map[string]string `yaml:"pods"`
	mu   sync.RWMutex
}

var (
	AiSubtitleServerConfig = os.Getenv("AI_SUBTITLE_SERVER_CONFIG")
)

const DEFAULT_AI_SUBTITLE_SERVER_GROUP = "default"

func (s *AiSubtitleServer) validate() error {
	if s.ServerGroup == nil {
		return fmt.Errorf("AiSubtitleServer.serverGroup is nil")
	}

	if _, ok := s.ServerGroup[DEFAULT_AI_SUBTITLE_SERVER_GROUP]; !ok {
		return fmt.Errorf("AiSubtitleServer.serverGroup must have default group")
	}

	if s.Pods == nil {
		s.Pods = make(map[string]string)
	}

	return nil
}

func (s *AiSubtitleServer) ReadFromConfigMap(configMap *corev1.ConfigMap) (err error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	// 解析 serverGroup 字段
	serverGroupData, ok := configMap.Data["serverGroup"]
	if !ok {
		return fmt.Errorf("ConfigMap does not contain 'serverGroup' key")
	}

	serverGroup := make(map[string]*ServiceServer)
	if err = yaml.Unmarshal([]byte(serverGroupData), &serverGroup); err != nil {
		return fmt.Errorf("failed to unmarshal serverGroup data: %v", err)
	}

	// 解析 pods 字段
	podsData, ok := configMap.Data["pods"]
	if !ok {
		return fmt.Errorf("ConfigMap does not contain 'pods' key")
	}

	pods := make(map[string]string)
	if err = yaml.Unmarshal([]byte(podsData), &pods); err != nil {
		return fmt.Errorf("failed to unmarshal serverGroup data: %v", err)
	}

	tmp := new(AiSubtitleServer)
	tmp.ServerGroup = serverGroup
	tmp.Pods = pods

	if err = tmp.validate(); err != nil {
		return fmt.Errorf("validation failed: %v", err)
	}

	fmt.Printf("before UpdateAiSubtitleServer: %+v\n", s.ServerGroup)
	s.ServerGroup = tmp.ServerGroup
	fmt.Printf("after UpdateAiSubtitleServer: %+v\n", s.ServerGroup)
	s.Pods = tmp.Pods
	log.Printf("AiSubtitleServer updated: \n%+v\n%+v\n", s.ServerGroup, s.Pods)
	return nil
}

func (s *AiSubtitleServer) watch(clientSet *kubernetes.Clientset, latestRV *string) error {
	log.Println("start new AiSubtitleServer watcher")
	watcher, err := clientSet.CoreV1().ConfigMaps(k8s.Namespace).Watch(context.TODO(), metav1.ListOptions{
		FieldSelector:   fields.OneTermEqualSelector("metadata.name", AiSubtitleServerConfig).String(),
		Watch:           true,
		ResourceVersion: *latestRV,
	})
	if err != nil {
		return fmt.Errorf("failed to watch ConfigMap: %v", err)
	}
	defer func() {
		watcher.Stop()
		log.Println("AiSubtitleServer watcher stopped")
	}()

	for {
		select {
		case event, ok := <-watcher.ResultChan():
			if !ok {
				log.Println("Watcher channel closed")
				return nil
			}

			configMap, ok := event.Object.(*corev1.ConfigMap)
			if !ok {
				continue
			}

			switch event.Type {
			case watch.Added, watch.Modified:
				log.Println("ConfigMap updated:", configMap.Name)
				if err = s.ReadFromConfigMap(configMap); err != nil {
					log.Printf("Failed to read ConfigMap: %v", err)
					continue
				}
				*latestRV = configMap.ResourceVersion
			case watch.Deleted:
				log.Println("ConfigMap deleted:", configMap.Name)
			case watch.Error:
				log.Printf("Error watching ConfigMap: %v", event.Object)
			}
		}
	}
}

func (s *AiSubtitleServer) retryWatch(clientSet *kubernetes.Clientset, latestRV string) {
	defer log.Println("AiSubtitleServer retryWatch stopped")

	for {
		if err := s.watch(clientSet, &latestRV); err != nil {
			log.Printf("Failed to watch ConfigMap: %v", err)
		}

		select {
		case <-time.After(30 * time.Second):
		}
	}
}

func (s *AiSubtitleServer) load() error {
	config, err := rest.InClusterConfig()
	if err != nil {
		return fmt.Errorf("failed to create in-cluster config: %v", err)
	}

	clientSet, err := kubernetes.NewForConfig(config)
	if err != nil {
		return fmt.Errorf("failed to create clientset: %v", err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	configMap, err := clientSet.CoreV1().ConfigMaps(k8s.Namespace).Get(ctx, AiSubtitleServerConfig, metav1.GetOptions{})
	if err != nil {
		return fmt.Errorf("failed to get ConfigMap: %v", err)
	}

	if err = s.ReadFromConfigMap(configMap); err != nil {
		return err
	}

	go s.retryWatch(clientSet, configMap.ResourceVersion)

	return nil
}

func (s *AiSubtitleServer) GetServer(name string) *ServiceServer {
	s.mu.RLock()
	defer s.mu.RUnlock()

	serverGroupName := DEFAULT_AI_SUBTITLE_SERVER_GROUP

	if groupName, ok := s.Pods[name]; ok {
		serverGroupName = groupName
	}

	if server, ok := s.ServerGroup[serverGroupName]; ok {
		return server
	}
	return s.ServerGroup[DEFAULT_AI_SUBTITLE_SERVER_GROUP]
}
