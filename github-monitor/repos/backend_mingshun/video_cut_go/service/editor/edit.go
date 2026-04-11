package editor

import (
	"os"
	"path/filepath"
	"time"
	"video_cut_go/ffmpeg"
	"video_cut_go/metrics"
	"video_cut_go/mingshun"
	"video_cut_go/service/editor/module"
	"video_cut_go/service/sender"
)

func (e *Editor) edit() (success bool) {
	e.status = "getting new task"
	config, err := e.getNewConfig()
	if err != nil {
		e.status = "error"
		e.log.Error("fetch config error: " + err.Error())
		return
	}

	if config == nil {
		return
	}
	metrics.VideoEditor.Inc()

	e.log.BreakLine()
	e.status = "processing"
	e.log.Infof(config.Identifier, "processing config")
	e.Callback(&mingshun.Callback{Identifier: config.Identifier, Msg: "正在切片中"})
	config.Rule.SetDefault()

	e.log.Infof(config.Identifier, "fetching video detail")
	defer func() {
		if !success {
			_ = os.RemoveAll(filepath.Dir(config.Video))
		}
	}()
	detail, err := ffmpeg.GetVideoDetail(config.Video)
	if err != nil {
		e.log.Errorf(config.Identifier, "fetch video detail error: %s", err)
		return
	}
	duration := time.Duration(detail.GetDuration()) * time.Second
	e.videoMetrics = VideoMetrics{
		Resolution: detail.GetResolution(),
		Duration:   duration.String(),
	}

	progress := make(chan ffmpeg.Progress, 10)
	go func() {
		for p := range progress {
			e.Progress = p
		}
	}()

	e.log.Infof(config.Identifier, "processing video rule")
	if !config.Rule.Run(&e.status, &module.VideoData{
		Video:      config.Video,
		Identifier: config.Identifier,
		Detail:     detail,
		Log:        e.log,
		Data:       config.Data,
		Threads:    e.threads,
		Progress:   progress,
	}) {
		return
	}

	e.log.Infof(config.Identifier, "pass config to %s", sender.RedisKeyName)
	if err = e.passToNext(config); err != nil {
		e.log.Errorf(config.Identifier, "pass to next error: %s", err)
		return
	}

	e.status = "done"
	e.log.Infof(config.Identifier, "edit success")
	return true
}
