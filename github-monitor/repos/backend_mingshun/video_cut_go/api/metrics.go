package api

import (
	"github.com/gin-gonic/gin"
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"strconv"
	"sync"
	"time"
	"video_cut_go/metrics"
	"video_cut_go/service/downloader"
	"video_cut_go/service/editor"
	"video_cut_go/service/imager"
	"video_cut_go/service/sender"
)

var h = promhttp.Handler()

var wg sync.WaitGroup

func metricsHandler(c *gin.Context) {
	wg.Add(4)

	var (
		dm downloader.Metrics
		im imager.Metrics
		em editor.Metrics
		sm sender.Metrics
	)
	go func() {
		defer wg.Done()
		dm = downloader.GetMetrics()
	}()
	go func() {
		defer wg.Done()
		im = imager.GetMetrics()
	}()
	go func() {
		defer wg.Done()
		em = editor.GetMetrics()
	}()
	go func() {
		defer wg.Done()
		sm = sender.GetMetrics()
	}()
	wg.Wait()

	metrics.VideoQueue.Set(float64(im.Queue + em.Queue + sm.Queue))
	metrics.VideoDownloader.Set(float64(boolToInt(dm.ProcessingId != "")))
	metrics.VideoImager.Set(float64(boolToInt(im.ProcessingId != "")))
	metrics.VideoEditor.Set(float64(boolToInt(em.ProcessingId != "")))
	metrics.VideoSender.Set(float64(boolToInt(sm.ProcessingId != "")))

	now := float64(time.Now().Unix())

	metrics.DownloaderInfo.WithLabelValues(dm.ProcessingId, dm.Progress, dm.Status).Set(now)
	metrics.ImagerInfo.WithLabelValues(im.ProcessingId, im.Progress, strconv.FormatInt(im.Queue, 10), im.Status).Set(now)
	metrics.EditorInfo.WithLabelValues(em.ProcessingId, em.Progress, strconv.FormatInt(em.Queue, 10),
		em.VideoMetrics.Resolution, em.VideoMetrics.Duration, strconv.Itoa(em.Threads),
		em.VideoProgress.Speed, em.VideoProgress.Progress, em.VideoProgress.OutTime, em.Status).Set(now)
	metrics.SenderInfo.WithLabelValues(sm.ProcessingId, sm.Progress, strconv.FormatInt(sm.Queue, 10), sm.Status).Set(now)

	h.ServeHTTP(c.Writer, c.Request)
}

func boolToInt(b bool) int {
	if b {
		return 1
	}

	return 0
}
