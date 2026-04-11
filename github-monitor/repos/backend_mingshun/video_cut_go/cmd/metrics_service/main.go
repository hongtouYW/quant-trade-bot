package main

import (
	"context"
	"flag"
	"fmt"
	"github.com/gin-gonic/gin"
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/redis/go-redis/v9"
	"log"
	"sync"
)

var (
	confFile   string
	h          = promhttp.Handler()
	projectRdb map[string]*redis.Client

	wg  sync.WaitGroup
	ctx = context.Background()
)

func main() {
	flag.StringVar(&confFile, "c", "config.yml", "Configuration file")
	flag.Parse()

	config := new(Config)
	err := config.ReadFromFile(confFile)
	if err != nil {
		log.Fatal(err)
	}

	projectRdb = make(map[string]*redis.Client, len(config.Project))
	for name, db := range config.Project {
		rdb, err := redisConn(config.Redis, db)
		if err != nil {
			log.Fatalf("rdb %d error: %s", db, err)
		}

		projectRdb[name] = rdb
	}

	prometheus.MustRegister(PendingVideo)
	router := gin.Default()
	router.GET("/metrics", metricsHandler)

	if err = router.Run(fmt.Sprintf(":%d", config.Port)); err != nil {
		log.Fatal(err)
	}
}

func redisConn(conf *RedisConf, db int) (*redis.Client, error) {
	client := redis.NewClient(&redis.Options{
		Addr:     conf.Addr,
		Password: conf.Password,
		DB:       db,
	})
	if err := client.Ping(ctx).Err(); err != nil {
		return nil, err
	}

	return client, nil
}
