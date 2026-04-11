package main

import (
	"github.com/gin-gonic/gin"
	"github.com/prometheus/client_golang/prometheus"
	"github.com/redis/go-redis/v9"
	"video_cut_go/service/downloader"
)

var (
	PendingVideo = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "project_pending_videos",
			Help: "Total pending videos per project",
		},
		[]string{"project"},
	)
)

func metricsHandler(c *gin.Context) {
	for project, rdb := range projectRdb {
		wg.Add(1)
		go updatePending(rdb, project)
	}

	wg.Wait()
	h.ServeHTTP(c.Writer, c.Request)
}

func updatePending(client *redis.Client, project string) {
	defer wg.Done()
	value := client.LLen(ctx, downloader.RedisKeyName).Val()
	PendingVideo.With(prometheus.Labels{"project": project}).Set(float64(value))
}
