package main

import (
	"context"
	"encoding/json"
	"flag"
	"github.com/redis/go-redis/v9"
	"log"
	"strings"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/common/signal"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
)

const RedisKeyName = "sender"
const mingshunCallback = "/api/videoSyncCallback"

type Sender struct {
	wg  *sync.WaitGroup
	ctx context.Context
	rdb *redis.Client
}

var Logger *logger.Logger
var config *Config
var confFile string

func init() {
	flag.StringVar(&confFile, "c", "config.yml", "Configuration file")
	flag.Parse()

	var err error
	lg := logger.Config{
		File:       "logs/sender.log",
		Level:      "info",
		MaxSize:    516,
		MaxBackups: 7,
		MaxAge:     7,
		Compress:   true,
		Callback:   mingshunCallback,
	}

	if Logger, err = logger.New(&lg); err != nil {
		log.Fatal(err)
	}

	config = new(Config)
	if err = config.ReadFromFile(confFile); err != nil {
		log.Fatal(err)
	}
}

func main() {
	sr := new(Sender)

	ctxT, cancelT := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancelT()

	rdb := redis.NewClient(&redis.Options{
		Addr:     config.Redis.Addr,
		Password: config.Redis.Password,
		DB:       config.Redis.DB,
	})

	err := rdb.Ping(ctxT).Err()
	if err != nil {
		log.Fatal(err)
	}

	sr.rdb = rdb
	var cancel context.CancelFunc
	sr.wg = new(sync.WaitGroup)
	sr.ctx, cancel = context.WithCancel(context.Background())

	go sr.Run()
	signal.WaitForProgramClose(func() {
		cancel()
		sr.wg.Wait()
	})
}

func (s *Sender) getNewData() (*Data, error) {
	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()
	str, err := s.rdb.LPop(ctx, RedisKeyName).Result()
	if err != nil {
		return nil, err
	}

	data := new(Data)
	if err = json.Unmarshal([]byte(str), data); err != nil {
		return nil, err
	}

	ctx, cancel = common.TimeoutContext(3 * time.Second)
	defer cancel()
	if err = data.validate(); err != nil {
		Logger.Callback(mingshun.CALLBACK_VIDEO_ERROR_STATUS, data.Identifier, "configuration error: %s", err)
		return nil, err
	}

	if data.Port == 0 {
		data.Port = 22
	}

	if strings.Index(data.Path, "${YYYYMMDD}") != -1 {
		data.Path = strings.ReplaceAll(data.Path, "${YYYYMMDD}", time.Now().Format("20060102"))
	}

	return data, nil
}

func (s *Sender) Callback(callback *mingshun.Callback) {
	err := mingshun.VideoSyncCallback(callback)

	if err != nil {
		Logger.Errorf(callback.Identifier, "send mingshun callback error: %s", err)
	}
}

func (s *Sender) Run() {
	defer s.wg.Done()
	Logger.Info("sender running")
	for {
		select {
		case <-s.ctx.Done():
			_ = Logger.Sync()
			Logger.Info("sender stopped")
			return
		default:
			s.send()
		}
	}
}
