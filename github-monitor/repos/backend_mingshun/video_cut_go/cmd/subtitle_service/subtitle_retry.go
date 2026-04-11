package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"github.com/gin-gonic/gin"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"subtitle_service/response"
	"video_cut_go/common"
	"video_cut_go/mingshun"
)

type SubtitleRetryBody struct {
	VideoId  string   `json:"videoId"`
	Language []string `json:"language"`
	Redis    string   `json:"redis"`
}

func SubtitleRetryHandler(c *gin.Context) {
	var (
		data SubtitleRetryBody
		err  error
	)
	if err = c.ShouldBindBodyWithJSON(&data); err != nil {
		response.Error(c, http.StatusBadRequest, err)
		return
	}

	backupFile := filepath.Join(backupPath, data.VideoId, data.VideoId+".wav")
	wavFile := filepath.Join(wavPath, data.VideoId+".wav")

	if _, err = os.Stat(backupFile); err != nil {
		response.Error(c, http.StatusBadRequest, fmt.Errorf("backup is expired"))
		return
	}

	if err = common.CopyFile(backupFile, wavFile); err != nil {
		ilog.Infof(data.VideoId, "copied wav file from %s to %s error: %s", backupFile, wavFile, err.Error())
		response.Error(c, http.StatusBadRequest, fmt.Errorf("wav data error"))
		return
	}

	go func(data SubtitleRetryBody) {
		ilog.Infof(data.VideoId, "start subtitle retry")
		server := aiSubtitleServer.GetServer(data.Redis)
		ilog.Infof(data.VideoId, "ai server %+v", *server)

		ilog.Infof(data.VideoId, "call aiSubtitle api: %s %s", wavFile, data.Language)
		remoteWav := filepath.Join(server.UploadPath, data.VideoId+".wav")
		if err = aiSubtitle(data.VideoId, wavFile, remoteWav, data.Language, server); err != nil {
			ilog.Infof(data.VideoId, "call aiSubtitle api error: %s", err)

			if err = mingshun.SendCallback(mingshun.CALLBACK_API_VIDEO, &mingshun.Callback{
				Identifier: data.VideoId,
				Status:     mingshun.CALLBACK_VIDEO_SUBTITLE_ERROR_STATUS,
				Msg:        fmt.Sprintf("call error: %s", err),
			}); err != nil {
				ilog.Errorf(data.VideoId, " mingshun callback error: %s", err)
			}
			return
		}

		if err = mingshun.SendCallback(mingshun.CALLBACK_API_VIDEO, &mingshun.Callback{
			Identifier: data.VideoId,
			Status:     mingshun.CALLBACK_VIDEO_SUBTITLE_PROCESSING_STATUS,
			Msg:        "retry",
		}); err != nil {
			ilog.Errorf(data.VideoId, " mingshun callback error: %s", err)
		}
		ilog.Infof(data.VideoId, "subtitle retry success")
	}(data)

	response.Success(c, nil)
}

type AiSubtitleResponse struct {
	Error   string `json:"error"`
	Details string `json:"details"`
	Code    int    `json:"code"`
}

// aiSubtitle 更新这个function需要也更新video_cut_go.sender
func aiSubtitle(id, wavFile, remoteWav string, languages []string, server *ServiceServer) error {
	type Payload struct {
		VideoId      string   `json:"video_id"`
		WavFile      string   `json:"wav_file_path"`
		Checksum     string   `json:"md5_hash_file"`
		Size         int64    `json:"file_size"`
		ThirdPartyId int      `json:"third_party_id"`
		Languages    []string `json:"languages"`
	}

	stat, err := os.Stat(wavFile)
	if err != nil {
		return err
	}

	md5, err := common.MD5(wavFile)
	if err != nil {
		return err
	}

	marshal, err := json.Marshal(&Payload{
		VideoId:      id,
		WavFile:      remoteWav,
		Checksum:     md5,
		Size:         stat.Size(),
		ThirdPartyId: 1,
		Languages:    languages,
	})
	if err != nil {
		return err
	}

	apiUrl, _ := url.JoinPath(server.ApiBaseUrl, "transcribes")
	ilog.Infof(id, "ai subtitle api url: %s", apiUrl)
	ilog.Infof(id, "ai subtitle api body: %s", marshal)
	req, err := http.NewRequest("POST", apiUrl, bytes.NewBuffer(marshal))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return fmt.Errorf("read response error: %s", err)
	}

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("aiSubtitle response error: %s", body)
	}

	var data AiSubtitleResponse
	if err = json.Unmarshal(body, &data); err != nil {
		return fmt.Errorf("unmarshal response error: %s", err)
	}

	if data.Code != 200 {
		return fmt.Errorf("%s: %s", data.Error, data.Details)
	}

	return nil
}
