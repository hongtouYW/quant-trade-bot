package api

import (
	"github.com/gin-gonic/gin"
	"time"
	"video_cut_go/api/util/response"
	"video_cut_go/service/downloader"
	"video_cut_go/service/editor"
	"video_cut_go/service/imager"
	"video_cut_go/service/sender"
)

type InfoResult struct {
	QueueCount int       `json:"queueCount"`
	Queue      []string  `json:"queue"`
	Downloader string    `json:"downloader"`
	Imager     string    `json:"imager"`
	Editor     string    `json:"editor"`
	Sender     string    `json:"sender"`
	Processing bool      `json:"processing"`
	Time       time.Time `json:"time"`
}

func (i *InfoResult) IsProcessing() bool {
	return i.QueueCount != 0 || i.Downloader != "" || i.Imager != "" || i.Editor != "" || i.Sender != ""
}

func info(c *gin.Context) {
	val1, _ := imager.GetQueue()
	val2, _ := editor.GetQueue()
	val3, _ := sender.GetQueue()
	var queue []string
	queue = append(queue, val1...)
	queue = append(queue, val2...)
	queue = append(queue, val3...)

	result := InfoResult{
		QueueCount: len(queue),
		Queue:      queue,
		Downloader: downloader.GetCurrent(),
		Imager:     imager.GetCurrent(),
		Editor:     editor.GetCurrent(),
		Sender:     sender.GetCurrent(),
		Time:       time.Now(),
	}

	result.Processing = result.IsProcessing()
	response.Success(c, &result)
}
