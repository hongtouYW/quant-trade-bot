package module

import (
	"path/filepath"
	"video_cut_go/ffmpeg"
)

// Preview 试看视频
type Preview struct {
	Enable   bool `json:"enable"`
	Interval int  `json:"interval"`
	Skip     int  `json:"skip"`
}

func (m *Preview) SetDefault() {
	// 设置默认值
	if m.Interval == 0 {
		m.Interval = 1
	}
}

func (m *Preview) Run(d *VideoData) bool {
	if !m.Enable {
		return true
	}

	d.Log.Infof(d.Identifier, "processing preview rule")
	root := filepath.Dir(d.Video)
	previewMp4 := filepath.Join(root, "preview_"+d.Identifier+".mp4")
	arg, err := ffmpeg.Cut(d.Video, previewMp4, m.Skip, m.Interval, "")
	if err != nil {
		d.Log.Infof(d.Identifier, "process ffmpeg: %s", arg)
		d.Log.Errorf(d.Identifier, "process preview rule error: %s", err)
		return false
	}
	d.Preview = previewMp4
	d.Data.Preview = previewMp4
	return true
}
