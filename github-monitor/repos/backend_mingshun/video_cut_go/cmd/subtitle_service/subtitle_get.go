package main

import (
	"encoding/json"
	"errors"
	"github.com/gin-gonic/gin"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"subtitle_service/response"
	"subtitle_service/sender"
	"video_cut_go/mingshun"
)

type AiSubtitleGetResponse struct {
	Id        string      `json:"id"`
	Status    string      `json:"status"`
	Msg       string      `json:"msg"`
	LangFiles interface{} `json:"language_files"`
}

func SubtitleGetHandler(c *gin.Context) {
	videoID := c.PostForm("id")
	status := c.PostForm("status")
	msg := c.PostForm("msg")
	langJSON := c.PostForm("language_files")
	ilog.Infof(videoID, "get subtitle")

	marshal, err := json.Marshal(AiSubtitleGetResponse{
		Id:        videoID,
		Status:    status,
		Msg:       msg,
		LangFiles: langJSON,
	})
	if err != nil {
		ilog.Errorf(videoID, "json marshal error: %s", err)
		response.Error(c, http.StatusBadRequest, errors.New("data is corrupted"))
		return
	}

	if status == "0" || status == "2" {
		ilog.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_RESPONSE_ERROR_STATUS, videoID, string(marshal))
		response.Success(c, nil)
		return
	}

	file, err := c.FormFile("file")
	if err != nil {
		ilog.Errorf(videoID, "get file error: %s", err)
		response.Error(c, http.StatusBadRequest, errors.New("invalid file"))
		return
	}

	if err = c.SaveUploadedFile(file, file.Filename); err != nil {
		ilog.Errorf(videoID, "save file error: %s", err)
		response.Error(c, http.StatusInternalServerError, errors.New("failed to save file"))
		return
	}
	defer os.RemoveAll(file.Filename)

	outputPath := filepath.Join(subtitlePath, videoID, "subtitles")
	_ = os.MkdirAll(outputPath, os.ModePerm)

	ilog.Infof(videoID, "uncompress file: %s", file.Filename)
	output, err := exec.Command("tar", "-xzf", file.Filename, "-C", outputPath).CombinedOutput()
	if err != nil {
		ilog.Errorf(videoID, "uncompress file error: %s | %s", err, output)
		response.Error(c, http.StatusInternalServerError, errors.New("file is not a valid compress file"))
		return
	}

	wavFile := filepath.Join(wavPath, videoID+".wav")
	_ = os.RemoveAll(wavFile)

	go func() {
		backupResp := filepath.Join(backupPath, videoID, "response.json")
		_ = os.WriteFile(backupResp, marshal, 0666)

		receiver, err := mingshun.GetReceiver(videoID)
		if err != nil {
			ilog.Errorf(videoID, "failed to get receiver: %s", err)
			ilog.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS, videoID, "failed to get receiver: %s", err)
			return
		}
		if err = passToSender(&sender.Config{
			Identifier: videoID,
			Local:      filepath.Join(subtitlePath, videoID),
			Receiver: &sender.Receiver{
				Username: receiver.Username,
				Host:     receiver.Host,
				Port:     receiver.Port,
				Path:     receiver.Path,
			},
			Data: string(marshal),
		}); err != nil {
			ilog.Errorf(videoID, "send receiver error: %s", err)
			ilog.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS, videoID, "send to queue error")
		}
	}()

	response.Success(c, nil)
}
