package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/gin-gonic/gin"
	"github.com/redis/go-redis/v9"
	"github.com/robfig/cron/v3"
	"go.uber.org/zap"
	"log"
	"os/exec"
	"subtitle_service/sender"
	"sync"
	"time"
	"video_cut_go/common"
	"video_cut_go/common/signal"
	"video_cut_go/k8s"
	"video_cut_go/logger"
	"video_cut_go/mingshun"
)

var (
	ilog             *logger.Logger
	confFile         string
	config           *Config
	aiSubtitleServer *AiSubtitleServer
	rdb              *redis.Client
)

var (
	backupPath   = "/home/backup/"
	subtitlePath = "/home/subtitle/"
	wavPath      = "/home/wav/"
)

func init() {
	_, err := exec.LookPath("tar")
	if err != nil {
		log.Fatal("tar command not found")
	}

	_, err = exec.LookPath("rsync")
	if err != nil {
		log.Fatal("rsync command not found")
	}

	if AiSubtitleServerConfig == "" {
		panic("AI_SUBTITLE_SERVER_CONFIG not set")
	}

	aiSubtitleServer = new(AiSubtitleServer)
	if err = aiSubtitleServer.load(); err != nil {
		panic(err)
	}

	flag.StringVar(&confFile, "c", "config.yml", "Configuration file")
	flag.Parse()
}

func main() {
	ctx, cancel := context.WithCancel(context.Background())
	wg := new(sync.WaitGroup)
	config = new(Config)
	err := config.ReadFromFile(confFile)
	if err != nil {
		log.Fatal(err)
	}

	if config.BackupPath != "" {
		backupPath = config.BackupPath
	}

	if config.SubtitlePath != "" {
		subtitlePath = config.SubtitlePath
	}

	if config.WavPath != "" {
		wavPath = config.WavPath
	}

	err = mingshun.Init(&mingshun.Config{
		URL:            config.CallbackUrl,
		CallbackServer: k8s.PodName,
	})
	if err != nil {
		log.Fatal(err)
	}

	conn, err := clickhouse.Open(&clickhouse.Options{
		Addr: []string{config.Clickhouse.Addr},
		Auth: clickhouse.Auth{
			Username: config.Clickhouse.Username,
			Password: config.Clickhouse.Password,
			Database: config.Clickhouse.Database,
		},
		DialTimeout: 5 * time.Second,
	})
	if err != nil {
		log.Fatal(err)
	}

	ckHook := logger.NewClickHouseSubtitle(conn)

	lg := logger.Config{
		File:       "logs/service.log",
		Level:      "info",
		MaxSize:    516,
		MaxBackups: 7,
		MaxAge:     7,
		Compress:   true,
		Callback:   mingshun.CALLBACK_API_VIDEO,
	}
	if ilog, err = logger.New(&lg, zap.Hooks(ckHook.Run)); err != nil {
		log.Fatal(err)
	}

	rdb = redis.NewClient(&redis.Options{
		Addr:     config.Redis.Addr,
		Password: config.Redis.Password,
		DB:       config.Redis.DB,
	})

	timeout, timeoutCancel := context.WithTimeout(ctx, time.Second)
	if err = rdb.Ping(timeout).Err(); err != nil {
		timeoutCancel()
		log.Fatal(err)
	}
	timeoutCancel()

	crontab := cron.New(cron.WithSeconds())
	defer crontab.Start()
	if _, err = crontab.AddFunc("0 0 */6 * * *", cleanExpFile); err != nil {
		log.Fatal(err)
	}
	crontab.Start()

	if err = sender.Init(ctx, wg, rdb, ilog, config.SubtitlePath); err != nil {
		log.Fatal(err)
	}

	router := gin.Default()
	router.POST("/api/video/getSubtitle", SubtitleGetHandler)
	router.POST("/subtitle/resend", SubtitleResendHandler)
	router.POST("/subtitle/retry", SubtitleRetryHandler)

	go func() {
		if err = router.Run(fmt.Sprintf(":%d", config.Port)); err != nil {
			log.Fatal(err)
		}
	}()

	signal.WaitForProgramClose(func() {
		cancel()
		wg.Wait()
	})
}

func passToSender(config *sender.Config) error {
	marshal, err := json.Marshal(config)
	if err != nil {
		return err
	}

	ctx, cancel := common.TimeoutContext(3 * time.Second)
	defer cancel()
	return rdb.RPush(ctx, sender.RedisKeyName, string(marshal)).Err()
}
