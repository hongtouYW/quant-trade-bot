package main

import (
	"context"
	"flag"
	"log"
	_ "net/http/pprof"
	"sync"
	"time"
	"video_cut_go/api"
	"video_cut_go/common/signal"
	"video_cut_go/k8s"
	"video_cut_go/mingshun"
	"video_cut_go/service/downloader"
	"video_cut_go/service/editor"
	"video_cut_go/service/imager"
	"video_cut_go/service/sender"

	"github.com/ClickHouse/clickhouse-go/v2"
	"github.com/redis/go-redis/v9"
)

var (
	confFile            string
	mainCtx, mainCancel = context.WithCancel(context.Background())
	wg                  sync.WaitGroup
	configWatcher       *k8s.ConfigWatcher
)

//var debug bool

func init() {
	flag.StringVar(&confFile, "c", "config.yml", "Configuration file")
	//flag.BoolVar(&debug, "d", false, "Debug mode")
	flag.Parse()
	//start()

	//if AiSubtitleServerConfig == "" {
	//	panic("AI_SUBTITLE_SERVER_CONFIG not set")
	//}
}

func main() {
	var err error

	log.Println("k8s.NewConfigWatcher")
	configWatcher, err = k8s.NewConfigWatcher(mainCtx)
	if err != nil {
		log.Fatal(err)
	}

	//aiSubtitleServer = new(AiSubtitleServer)
	//log.Println("aiSubtitleServer.load")
	//if err = aiSubtitleServer.load(); err != nil {
	//	log.Fatal(err)
	//}

	config := new(Config)
	log.Println("config.ReadFromFile")
	if err = config.ReadFromFile(confFile); err != nil {
		log.Fatal(err)
	}

	log.Println("mingshun.Init")
	if err = mingshun.Init(config.Mingshun); err != nil {
		log.Fatal(err)
	}

	localRdb := redis.NewClient(&redis.Options{
		Addr:     config.Local.Addr,
		Password: config.Local.Password,
		DB:       config.Local.DB,
	})

	remoteRdb := redis.NewClient(&redis.Options{
		Addr:     config.Remote.Addr,
		Password: config.Remote.Password,
		DB:       config.Remote.DB,
	})

	log.Println("clickhouse.Open")
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

	wg.Add(4)

	log.Println("downloader.Init")
	if err = downloader.Init(mainCtx, &wg, localRdb, remoteRdb, conn, configWatcher); err != nil {
		log.Fatal(err)
	}

	log.Println("imager.Init")
	if err = imager.Init(mainCtx, &wg, localRdb, conn); err != nil {
		log.Fatal(err)
	}

	log.Println("editor.Init")
	if err = editor.Init(mainCtx, &wg, localRdb, conn); err != nil {
		log.Fatal(err)
	}

	log.Println("sender.Init")
	//if err = sender.Init(mainCtx, &wg, localRdb, aiSubtitleServer.GetServer(), conn); err != nil {
	//	log.Fatal(err)
	//}
	if err = sender.Init(mainCtx, &wg, localRdb, conn); err != nil {
		log.Fatal(err)
	}

	//log.Println("backuper.Init")
	//if err = backuper.Init(mainCtx, &wg, config.Backuper, localRdb, conn); err != nil {
	//	log.Fatal(err)
	//}

	log.Println("api.Start")
	go api.Start()

	signal.WaitForProgramClose(func() {
		mainCancel()
		wg.Wait()
	})
}
