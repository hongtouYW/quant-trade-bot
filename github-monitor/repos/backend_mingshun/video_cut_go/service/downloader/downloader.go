package downloader

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"gopkg.in/yaml.v3"
	corev1 "k8s.io/api/core/v1"
	"k8s.io/apimachinery/pkg/watch"
	"log"
	"os"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/k8s"
	"video_cut_go/logger"
	"video_cut_go/metrics"
	"video_cut_go/mingshun"
	"video_cut_go/service/editor"
	"video_cut_go/service/editor/module"
	"video_cut_go/service/imager"
)

const RedisKeyName = "downloader2"

type Downloader struct {
	wg            *sync.WaitGroup
	ctx           context.Context
	lRdb          *redis.Client
	rRdb          *redis.Client
	log           *logger.Logger
	current       string
	status        string
	ignoreProject []int
}

var (
	tooManyErr = errors.New("editor have more than 3 video need to process, waiting to complete those video before continue")
	dr         *Downloader
	configName = os.Getenv("CONFIG_NAME")
)

func Init(ctx context.Context, wg *sync.WaitGroup, lRdb, rRdb *redis.Client, ck clickhouse.Conn, cw *k8s.ConfigWatcher) error {
	if dr != nil {
		return errors.New("downloader already initialize")
	}

	if configName == "" {
		return fmt.Errorf("configName is empty")
	}

	ckHook := logger.NewClickHouseVideoCutGo(ck, "downloader")

	lg := logger.Config{
		File:       "logs/downloader.log",
		Level:      "info",
		MaxSize:    516,
		MaxBackups: 7,
		MaxAge:     7,
		Compress:   true,
		Callback:   mingshun.CALLBACK_API_VIDEO,
	}
	logg, err := logger.New(&lg, zap.Hooks(ckHook.Run))
	if err != nil {
		return err
	}

	ctxT, cancelT := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancelT()

	status := lRdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	status = rRdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	dr = new(Downloader)
	dr.ctx = ctx
	dr.wg = wg
	dr.lRdb = lRdb
	dr.rRdb = rRdb
	dr.log = logg
	dr.status = "initialized"

	err = cw.Register(configName, func(event watch.EventType, cm *corev1.ConfigMap) {
		switch event {
		case watch.Added, watch.Modified:
			log.Println("ConfigMap updated:", cm.Name)
			if err = dr.updateIgnoreProject(cm); err != nil {
				log.Println(err)
			}
		case watch.Deleted:
			log.Println("ConfigMap deleted:", cm.Name)
		}
	})
	if err != nil {
		return err
	}

	go dr.Run()
	return nil
}

func (d *Downloader) getNewConfig() (*editor.Config, error) {
	d.current = ""
	total, err := imager.GetQueueCount()
	if err != nil {
		return nil, err
	}
	if total >= 1 {
		return nil, tooManyErr
	}

	//set maxQueue to 1 is to prevent the source stuck in the machine and
	//long time no being process but there is others machine is free
	total, err = editor.GetQueueCount()
	if err != nil {
		return nil, err
	}
	if total >= 1 {
		return nil, tooManyErr
	}

	str, err := d.rRdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	d.log.Infof("", "receive new config: %s", str[1])

	metrics.VideoCut.Inc()

	config := new(editor.Config)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		var identifier struct {
			Identifier string `json:"identifier"`
		}

		_ = json.Unmarshal([]byte(str[1]), &identifier)
		config.Identifier = identifier.Identifier
		return config, err
	}

	config.Data = new(module.Data)
	if err = config.Validate(); err != nil {
		if config.Identifier != "" {
			d.log.Errorf(config.Identifier, "config invalid: %s", err)
		}
		return config, err
	}

	d.current = config.Identifier
	return config, nil
}

func (d *Downloader) passToNext(config *editor.Config) error {
	marshal, err := json.Marshal(config)
	if err != nil {
		return err
	}

	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()
	return d.lRdb.RPush(ctx, imager.RedisKeyName, string(marshal)).Err()
}

func (d *Downloader) Run() {
	defer d.wg.Done()
	d.status = "running"
	d.log.Info("downloader running")
	var timeout time.Duration
	for {
		timer := time.NewTimer(timeout)
		select {
		case <-timer.C:
			timer.Stop()
			_, shouldDelay := d.download()
			if d.ctx.Err() != nil {
				d.status = "stopped"
				d.log.Info("downloader stopped")
				_ = d.log.Sync()
				return
			}

			if shouldDelay {
				timeout = 1 * time.Minute
			} else {
				timeout = 0
			}
		}
	}
}

func (d *Downloader) Callback(callback *mingshun.Callback) {
	err := mingshun.UpdateMsgCallback(callback)
	if err != nil {
		d.log.Errorf(callback.Identifier, "send mingshun callback error: %s", err)
	}
}

func (d *Downloader) updateIgnoreProject(cm *corev1.ConfigMap) error {
	// 解析 ignoreProject 字段
	ignoreProjectData, ok := cm.Data["ignoreProject"]
	if !ok {
		return fmt.Errorf("ConfigMap does not contain 'ignoreProject' key")
	}

	ignoreProject := make([]int, 0)
	if err := yaml.Unmarshal([]byte(ignoreProjectData), &ignoreProject); err != nil {
		return fmt.Errorf("failed to unmarshal ignoreProject data: %v", err)
	}

	d.ignoreProject = ignoreProject
	log.Printf("ignoreProject updated: %v", d.ignoreProject)
	return nil
}

func GetCurrent() string {
	return dr.current
}

type Metrics struct {
	ProcessingId string
	Progress     string
	Status       string
}

func GetMetrics() Metrics {
	if dr.status == "stopped" {
		return Metrics{
			ProcessingId: "",
			Progress:     "stopped",
			Status:       "0",
		}
	} else {
		return Metrics{
			ProcessingId: dr.current,
			Progress:     dr.status,
			Status:       "1",
		}
	}
}
