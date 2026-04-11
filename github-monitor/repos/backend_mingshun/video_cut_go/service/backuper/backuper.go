package backuper

import (
	"context"
	"encoding/json"
	"errors"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
	"go.uber.org/zap"
	"sync"
	"time"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
)

const RedisKeyName = "backuper"

type Backuper struct {
	wg     *sync.WaitGroup
	ctx    context.Context
	lRdb   *redis.Client
	log    *logger.Logger
	config *Config
}

var br *Backuper

func Init(ctx context.Context, wg *sync.WaitGroup, config *Config, lRdb *redis.Client, ck clickhouse.Conn) error {
	if br != nil {
		return errors.New("backuper already initialize")
	}

	ckHook := logger.NewClickHouseVideoCutGo(ck, "backuper")

	lg := logger.Config{
		File:       "logs/backuper.log",
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

	status := lRdb.Ping(ctxT)
	if status.Err() != nil {
		return status.Err()
	}

	br = new(Backuper)
	br.ctx = ctx
	br.wg = wg
	br.config = config
	br.lRdb = lRdb
	br.log = log

	go br.Run()
	return nil
}

func (b *Backuper) getNewConfig() (*BackupConfig, error) {
	str, err := b.lRdb.BLPop(context.Background(), 5*time.Second, RedisKeyName).Result()
	if errors.Is(err, redis.Nil) {
		return nil, nil
	} else if err != nil {
		return nil, err
	}

	config := new(BackupConfig)
	if err = json.Unmarshal([]byte(str[1]), config); err != nil {
		return nil, err
	}

	return config, nil
}

func (b *Backuper) Run() {
	defer b.wg.Done()
	b.log.Info("backuper running")
	for {
		select {
		case <-b.ctx.Done():
			b.log.Info("backuper stopped")
			_ = b.log.Sync()
			return
		default:
			b.backup()
		}
	}
}
