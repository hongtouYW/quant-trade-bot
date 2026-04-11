package editor

import (
	"context"
	"encoding/json"
	"errors"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"path/filepath"
	"runtime"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/ffmpeg"
	"video_cut_go/logger"
	"video_cut_go/metrics"
	"video_cut_go/mingshun"
	"video_cut_go/service/sender"
)

const (
	RedisKeyName = "editor"
	RootPath     = "/home/resource/"
)

type VideoMetrics struct {
	Resolution string
	Duration   string
}
type Editor struct {
	wg           *sync.WaitGroup
	ctx          context.Context
	rdb          *redis.Client
	log          *logger.Logger
	current      string
	videoMetrics VideoMetrics
	status       string
	threads      int
	Progress     ffmpeg.Progress
}

var er *Editor
var rootPathLen = len(RootPath)

func Init(ctx context.Context, wg *sync.WaitGroup, rdb *redis.Client, ck clickhouse.Conn) error {
	if er != nil {
		return errors.New("editor already initialize")
	}

	ckHook := logger.NewClickHouseVideoCutGo(ck, "editor")

	lg := logger.Config{
		File:       "logs/editor.log",
		Level:      "info",
		MaxSize:    516,
		MaxBackups: 7,
		MaxAge:     7,
		Compress:   true,
		Callback:   mingshun.CALLBACK_API_VIDEO,
	}
	log, err := logger.New(&lg, zap.Hooks(ckHook.Run))
	if err != nil {
		return err
	}

	ctxT, cancelT := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancelT()

	status := rdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	er = new(Editor)
	er.ctx = ctx
	er.wg = wg
	er.rdb = rdb
	er.log = log
	er.status = "initialized"
	er.threads = runtime.NumCPU() - 2

	go er.Run()
	return nil
}

func (e *Editor) getNewConfig() (*Config, error) {
	e.current = ""
	str, err := e.rdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	config := new(Config)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		return nil, err
	}
	e.current = config.Identifier
	return config, config.Validate()
}

func (e *Editor) passToNext(config *Config) error {
	detail, err := ffmpeg.GetVideoDetail(config.Data.DownloadURL)
	if err != nil {
		return err
	}

	config.Data.Duration = detail.GetDuration()
	config.Data.Resolution = detail.GetResolution()
	config.Data.RemoveHead(rootPathLen)

	marshal, err := json.Marshal(sender.Config{
		Identifier: config.Identifier,
		Local:      filepath.Dir(config.Video),
		Data:       config.Data,
		Receiver:   config.Receiver,
	})
	if err != nil {
		return err
	}

	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()
	return e.rdb.RPush(ctx, sender.RedisKeyName, string(marshal)).Err()
}

func (e *Editor) Run() {
	defer e.wg.Done()
	e.status = "running"
	e.log.Info("editor running")
	for {
		e.edit()
		if e.ctx.Err() != nil {
			e.status = "stopped"
			e.log.Info("editor stopped")
			_ = e.log.Sync()
			return
		}
	}
}

func (e *Editor) Callback(callback *mingshun.Callback) {
	err := mingshun.UpdateMsgCallback(callback)
	if err != nil {
		e.log.Errorf(callback.Identifier, "send mingshun callback error: %s", err)
	}
}

func GetCurrent() string {
	return er.current
}

func GetQueueCount() (int64, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	return er.rdb.LLen(ctx, RedisKeyName).Result()
}

func GetQueue() ([]string, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	results, err := er.rdb.LRange(ctx, RedisKeyName, 0, -1).Result()
	if err != nil {
		return nil, err
	}

	identifierList := make([]string, 0, len(results))
	for _, result := range results {
		config := new(Config)
		if err = json.Unmarshal([]byte(result), config); err != nil {
			return nil, err
		}

		identifierList = append(identifierList, config.Identifier)
	}

	return identifierList, nil
}

func (e *Editor) UpdateMetrics() {
	if e.status == "stopped" {
		metrics.EditorInfo.WithLabelValues("", e.status, "", "").Set(0)
	} else {
		metrics.EditorInfo.WithLabelValues(e.current, e.status, e.videoMetrics.Resolution, e.videoMetrics.Duration).Set(1)
	}
}

type Metrics struct {
	ProcessingId  string
	Progress      string
	Queue         int64
	VideoMetrics  VideoMetrics
	VideoProgress ffmpeg.Progress
	Threads       int
	Status        string
}

func GetMetrics() Metrics {
	queue, _ := GetQueueCount()
	if er.status == "stopped" {
		return Metrics{
			ProcessingId: "",
			Progress:     "stopped",
			Status:       "0",
		}
	} else {
		if er.current == "" {
			return Metrics{
				ProcessingId: er.current,
				Progress:     er.status,
				Queue:        queue,
				Threads:      er.threads,
				Status:       "1",
			}
		}
		return Metrics{
			ProcessingId:  er.current,
			Progress:      er.status,
			Queue:         queue,
			VideoMetrics:  er.videoMetrics,
			VideoProgress: er.Progress,
			Threads:       er.threads,
			Status:        "1",
		}
	}
}
