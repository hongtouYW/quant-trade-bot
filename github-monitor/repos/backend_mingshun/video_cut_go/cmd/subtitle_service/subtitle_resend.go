package main

import (
	"errors"
	"github.com/gin-gonic/gin"
	"net/http"
	"os"
	"path/filepath"
	"subtitle_service/response"
	"subtitle_service/sender"
)

type SubtitleSendBody struct {
	VideoId  string           `json:"videoId"`
	Receiver *sender.Receiver `json:"receiver" validate:"required"`
}

func SubtitleResendHandler(c *gin.Context) {
	var (
		data SubtitleSendBody
		err  error
	)
	if err = c.ShouldBindBodyWithJSON(&data); err != nil {
		response.Error(c, http.StatusBadRequest, err)
		return
	}

	backupResp := filepath.Join(backupPath, data.VideoId, "response.json")
	resp, err := os.ReadFile(backupResp)
	if err != nil {
		response.Error(c, 600, errors.New("use retry instead of resend"))
		return
	}

	if err = passToSender(&sender.Config{
		Identifier: data.VideoId,
		Local:      filepath.Join(subtitlePath, data.VideoId),
		Receiver:   data.Receiver,
		Data:       string(resp),
	}); err != nil {
		ilog.Errorf(data.VideoId, "send receiver error: %s", err)
		response.Error(c, http.StatusInternalServerError, errors.New("send receiver error"))
	}

	response.Success(c, nil)
}
