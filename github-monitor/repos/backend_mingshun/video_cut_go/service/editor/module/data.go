package module

import (
	"video_cut_go/ffmpeg"
	"video_cut_go/logger"
)

type VideoData struct {
	Detail     *ffmpeg.VideoDetail
	Video      string
	Preview    string
	Identifier string
	Log        *logger.Logger
	Data       *Data
	SAR        string
	Threads    int
	Progress   chan<- ffmpeg.Progress
}

type Data struct {
	Duration      int    `json:"duration"`
	WebpCount     int    `json:"webp_count"`
	PlayURL       string `json:"play_url"`
	DownloadURL   string `json:"download_url"`
	Thumbnail     string `json:"thumbnail"`
	ThumbVer      string `json:"thumb_ver"`
	ThumbHor      string `json:"thumb_hor"`
	ThumbLongview string `json:"thumb_longview"`
	Cover         string `json:"cover"`
	Webp          string `json:"webp"`
	Preview       string `json:"preview"`
	ThumbSeries   []int  `json:"thumb_series"`
	Resolution    string `json:"resolution"`
	Server        string `json:"server"`
}

func (d *Data) RemoveHead(head int) {
	if len(d.PlayURL) > head {
		d.PlayURL = d.PlayURL[head:]
	}
	if len(d.DownloadURL) > head {
		d.DownloadURL = d.DownloadURL[head:]
	}
	if len(d.Thumbnail) > head {
		d.Thumbnail = d.Thumbnail[head:]
	}
	if len(d.ThumbVer) > head {
		d.ThumbVer = d.ThumbVer[head:]
	}
	if len(d.ThumbHor) > head {
		d.ThumbHor = d.ThumbHor[head:]
	}
	if len(d.ThumbLongview) > head {
		d.ThumbLongview = d.ThumbLongview[head:]
	}
	if len(d.Cover) > head {
		d.Cover = d.Cover[head:]
	}
	if len(d.Webp) > head {
		d.Webp = d.Webp[head:]
	}
	if len(d.Preview) > head {
		d.Preview = d.Preview[head:]
	}
}
