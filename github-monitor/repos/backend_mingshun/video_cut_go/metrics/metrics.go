package metrics

import (
	"github.com/prometheus/client_golang/prometheus"
	"time"
)

var (
	VideoQueue = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_queue",
		Help: "Total queuing videos",
	})

	VideoDownloader = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_downloader",
		Help: "Downloader process state",
	})

	VideoImager = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_imager",
		Help: "Imager process state",
	})

	VideoEditor = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_editor",
		Help: "Editor process state",
	})

	VideoSender = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_sender",
		Help: "Sender process state",
	})

	VideoBackup = prometheus.NewGauge(prometheus.GaugeOpts{
		Name: "video_backup",
		Help: "Backuper process state",
	})

	VideoComplete = prometheus.NewCounter(
		prometheus.CounterOpts{
			Name: "video_cut_complete",
			Help: "Total count of complete cut, resets every day",
		},
	)

	VideoCut = prometheus.NewCounter(
		prometheus.CounterOpts{
			Name: "video_cut",
			Help: "Total count of cut, resets every day",
		},
	)

	DownloaderInfo = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "downloader_info",
			Help: "Downloader Info",
		},
		[]string{"processing_id", "progress", "status"},
	)
	ImagerInfo = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "imager_info",
			Help: "imager Info",
		},
		[]string{"processing_id", "progress", "queue", "status"},
	)
	EditorInfo = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "editor_info",
			Help: "editor Info",
		},
		[]string{"processing_id", "progress", "queue", "video_resolution", "video_length",
			"threads", "speed", "out_progress", "out_time", "status"},
	)
	SenderInfo = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "sender_info",
			Help: "sender Info",
		},
		[]string{"processing_id", "progress", "queue", "status"},
	)
)

func init() {
	prometheus.MustRegister(VideoQueue, VideoDownloader, VideoImager, VideoEditor, VideoSender, VideoCut, VideoComplete,
		DownloaderInfo, ImagerInfo, EditorInfo, SenderInfo)
	go func() {
		for {
			// Calculate the time until midnight.
			now := time.Now()
			next := time.Date(now.Year(), now.Month(), now.Day()+1, 0, 0, 0, 0, now.Location())
			timeUntilMidnight := time.Until(next)

			// Wait until midnight.
			time.Sleep(timeUntilMidnight)

			// Reset the counter.
			resetCounter()
		}
	}()
}

func resetCounter() {
	prometheus.Unregister(VideoComplete)
	prometheus.Unregister(VideoCut)
	prometheus.Unregister(K8sConfigReconnect)
	VideoComplete = prometheus.NewCounter(
		prometheus.CounterOpts{
			Name: "video_cut_complete",
			Help: "Total count of complete cut, resets every day",
		},
	)

	VideoCut = prometheus.NewCounter(
		prometheus.CounterOpts{
			Name: "video_cut",
			Help: "Total count of cut, resets every day",
		},
	)

	K8sConfigReconnect = prometheus.NewCounterVec(
		prometheus.CounterOpts{
			Name: "k8s_config_reconnect",
			Help: "ConfigMap Reconnect Times",
		},
		[]string{"name"},
	)
	prometheus.MustRegister(VideoComplete, VideoCut, K8sConfigReconnect)
}
