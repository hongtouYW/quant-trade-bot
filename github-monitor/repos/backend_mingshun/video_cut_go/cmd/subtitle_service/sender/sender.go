package sender

import (
	"context"
	"encoding/json"
	"errors"
	"github.com/redis/go-redis/v9"
	"sync"
	"time"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
)

const RedisKeyName = "sender"

type Sender struct {
	wg      *sync.WaitGroup
	ctx     context.Context
	rdb     *redis.Client
	log     *logger.Logger
	current string
}

var sr *Sender
var basePath string

func Init(ctx context.Context, wg *sync.WaitGroup, rdb *redis.Client, ilog *logger.Logger, subtitlePath string) error {
	if sr != nil {
		return errors.New("sender already initialize")
	}

	ctxT, cancelT := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancelT()

	status := rdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	sr = new(Sender)
	sr.ctx = ctx
	sr.wg = wg
	sr.rdb = rdb
	sr.log = ilog
	basePath = subtitlePath

	go sr.Run()
	return nil
}

func (s *Sender) getNewConfig() (*Config, error) {
	s.current = ""
	str, err := s.rdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	config := new(Config)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		return nil, err
	}
	s.current = config.Identifier
	return config, config.validate()
}

func (s *Sender) Run() {
	defer s.wg.Done()
	s.log.Info("[SENDER] running")
	for {
		s.send()
		if s.ctx.Err() != nil {
			s.log.Info("[SENDER] stopped")
			_ = s.log.Sync()
			return
		}
	}
}

func (s *Sender) Callback(callback *mingshun.Callback) {
	err := mingshun.UpdateMsgCallback(callback)
	if err != nil {
		s.log.Errorf(callback.Identifier, "[SENDER] send mingshun callback error: %s", err)
	}
}
