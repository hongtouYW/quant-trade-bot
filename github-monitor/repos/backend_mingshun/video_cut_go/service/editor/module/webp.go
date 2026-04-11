package module

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"
	"video_cut_go/ffmpeg"
)

// Webp 视频片段截图
type Webp struct {
	Enable bool `json:"enable"`
	// Start webp 的起点时间
	Start int `json:"start"`
	// Interval webp 的间隔时间 (默认0的话自动计算Interval)
	Interval int `json:"interval"`
	// Count 一共截取多少次
	Count int `json:"count"`
	// Length 截取一次多长时间
	Length int `json:"length"`
}

func (m *Webp) SetDefault() {
	if m.Count == 0 {
		m.Count = 10
	}
}

func (m *Webp) Run(d *VideoData) bool {
	if !m.Enable {
		return true
	}

	d.Log.Infof(d.Identifier, "processing webp rule")
	videoDuration := d.Detail.GetDuration()
	if videoDuration < (m.Count*m.Length + m.Start) {
		d.Log.Errorf(d.Identifier, "process webp rule error: video duration not enough")
		return false
	}

	if m.Interval == 0 || videoDuration < (m.Count*m.Length+m.Interval*(m.Count-1))+m.Start {
		m.Interval = (videoDuration - m.Start - (m.Count * m.Length)) / m.Count
		d.Log.Infof(d.Identifier, "assign a new interval value: %d", m.Interval)
	}

	if m.Interval == 0 {
		d.Log.Errorf(d.Identifier, "process webp rule error: video duration not enough")
		return false
	}

	videoWidth, videoHeight := d.Detail.GetShape()
	width := 400
	height := width * videoHeight / videoWidth
	if (height % 2) != 0 {
		height += 1
	}
	size := fmt.Sprintf("%d*%d", width, height)
	dir := filepath.Dir(d.Video)

	tmpVideos := make([]string, 0, m.Count)
	var err error
	for i := 0; i < m.Count; i++ {
		output := filepath.Join(dir, fmt.Sprintf("_tmp_%d.mp4", i))
		if _, err = ffmpeg.Cut(d.Video, output, i*m.Interval+m.Start+m.Length*i, m.Length, size); err != nil {
			d.Log.Errorf(d.Identifier, "process webp rule error: video's %d second have problem", i*m.Interval+m.Start)
			return false
		}
		tmpVideos = append(tmpVideos, output)
	}

	tmpInput := tmpVideos[0]
	if len(tmpVideos) > 1 {
		arr := strings.Join(tmpVideos, " -i ")
		tmpVideo := fmt.Sprintf("%d.mp4", time.Now().Unix())

		var vs string
		for i := 0; i < len(tmpVideos); i++ {
			vs += fmt.Sprintf("[%d:v]", i)
		}

		args := fmt.Sprintf("-loglevel error -i %s -y -max_muxing_queue_size 9999  -filter_complex \"%sconcat=n=%d:v=1 [v]\" -map \"[v]\" %s",
			arr, vs, len(tmpVideos), tmpVideo)
		d.Log.Infof(d.Identifier, "process ffmpeg: %s", args)
		if err = ffmpeg.Exec(args); err != nil {
			d.Log.Errorf(d.Identifier, "process webp rule error: %s", err)
			return false
		}

		tmpInput = tmpVideo
	}

	output := filepath.Join(dir, d.Identifier+"_sync.webp")
	args := fmt.Sprintf("-loglevel error -i %s -y -loop 0 -max_muxing_queue_size 9999 -filter:v fps=fps=20 -lossless 1 -preset picture -compression_level 6 %s",
		tmpInput, output)
	d.Log.Infof(d.Identifier, "process ffmpeg: %s", args)
	if err = ffmpeg.Exec(args); err != nil {
		d.Log.Errorf(d.Identifier, "process ffmpeg error: %s", err)
		return false
	}

	d.Data.Webp = output
	d.Data.WebpCount = m.Count

	for _, v := range tmpVideos {
		_ = os.Remove(v)
	}
	_ = os.Remove(tmpInput)
	return true
}
