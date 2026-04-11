package imager

import (
	"context"
	"encoding/json"
	"errors"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
	"video_cut_go/service/editor"
)

const RedisKeyName = "imager"

type Imager struct {
	wg      *sync.WaitGroup
	ctx     context.Context
	rdb     *redis.Client
	log     *logger.Logger
	current string
	status  string
}

var ir *Imager

func Init(ctx context.Context, wg *sync.WaitGroup, rdb *redis.Client, ck clickhouse.Conn) error {
	if ir != nil {
		return errors.New("imager already initialize")
	}

	ckHook := logger.NewClickHouseVideoCutGo(ck, "imager")

	lg := logger.Config{
		File:       "logs/imager.log",
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

	ir = new(Imager)
	ir.ctx = ctx
	ir.wg = wg
	ir.rdb = rdb
	ir.log = log
	ir.status = "initialized"

	go ir.Run()
	return nil
}

func (i *Imager) getNewConfig() (*editor.Config, error) {
	i.current = ""
	str, err := i.rdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	config := new(editor.Config)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		return nil, err
	}
	i.current = config.Identifier
	return config, config.Validate()
}

func (i *Imager) passToNext(config *editor.Config) error {
	marshal, err := json.Marshal(config)
	if err != nil {
		return err
	}

	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()
	if err = i.rdb.RPush(ctx, editor.RedisKeyName, string(marshal)).Err(); err != nil {
		return err
	}

	i.Callback(&mingshun.Callback{Identifier: config.Identifier, Msg: "正在排队切片"})
	return nil
}

func (i *Imager) Run() {
	defer i.wg.Done()
	i.status = "running"
	i.log.Info("imager running")
	for {
		i.image()
		if i.ctx.Err() != nil {
			i.status = "stopped"
			i.log.Info("imager stopped")
			_ = i.log.Sync()
			return
		}
	}
}

func (i *Imager) Callback(callback *mingshun.Callback) {
	err := mingshun.UpdateMsgCallback(callback)
	if err != nil {
		i.log.Errorf(callback.Identifier, "send mingshun callback error: %s", err)
	}
}

func GetCurrent() string {
	return ir.current
}

func GetQueueCount() (int64, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	return ir.rdb.LLen(ctx, RedisKeyName).Result()
}

func GetQueue() ([]string, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()

	results, err := ir.rdb.LRange(ctx, RedisKeyName, 0, -1).Result()
	if err != nil {
		return nil, err
	}

	identifierList := make([]string, 0, len(results))
	for _, result := range results {
		config := new(editor.Config)
		if err = json.Unmarshal([]byte(result), config); err != nil {
			return nil, err
		}

		identifierList = append(identifierList, config.Identifier)
	}

	return identifierList, nil
}

type Metrics struct {
	ProcessingId string
	Progress     string
	Queue        int64
	Status       string
}

func GetMetrics() Metrics {
	queue, _ := GetQueueCount()
	if ir.status == "stopped" {
		return Metrics{
			ProcessingId: "",
			Progress:     "stopped",
			Status:       "0",
		}
	} else {
		return Metrics{
			ProcessingId: ir.current,
			Progress:     ir.status,
			Queue:        queue,
			Status:       "1",
		}
	}
}
