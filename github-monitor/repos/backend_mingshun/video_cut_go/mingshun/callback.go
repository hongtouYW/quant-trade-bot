package mingshun

import (
	"bytes"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/url"
)

const (
	CALLBACK_API_VIDEO      = "/api/videoCallback"
	CALLBACK_API_VIDEO_SYNC = "/api/videoSyncCallback"
	CALLBACK_API_UPDATE_MSG = "/api/updateMsgCallback"
)

const (
	// CALLBACK_VIDEO_ERROR_STATUS 切片失败
	CALLBACK_VIDEO_ERROR_STATUS = iota
	// CALLBACK_VIDEO_SYNC_SUCCESS_STATUS [deprecated] 同步成功 - 发送后后端会发送同步请求到中转节点
	CALLBACK_VIDEO_SYNC_SUCCESS_STATUS
	// CALLBACK_VIDEO_SUCCESS_STATUS 切片成功
	CALLBACK_VIDEO_SUCCESS_STATUS
	// CALLBACK_VIDEO_SUBTITLE_PROCESSING_STATUS 字幕处理中
	CALLBACK_VIDEO_SUBTITLE_PROCESSING_STATUS
	// CALLBACK_VIDEO_SUBTITLE_PREPARE_ERROR_STATUS 字幕准备失败
	CALLBACK_VIDEO_SUBTITLE_PREPARE_ERROR_STATUS
	// CALLBACK_VIDEO_SUBTITLE_ERROR_STATUS 字幕处理失败
	CALLBACK_VIDEO_SUBTITLE_ERROR_STATUS
	// CALLBACK_VIDEO_SUBTITLE_RESPONSE_ERROR_STATUS 字幕服务处理失败
	CALLBACK_VIDEO_SUBTITLE_RESPONSE_ERROR_STATUS
	// CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS 字幕发送失败
	CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS
	// CALLBACK_VIDEO_SUBTITLE_SEND_SUCCESS_STATUS 字幕发送成功
	CALLBACK_VIDEO_SUBTITLE_SEND_SUCCESS_STATUS
)

type Callback struct {
	Identifier string `json:"identifier"`
	Status     int8   `json:"status"`
	Msg        string `json:"msg"`
	Server     string `json:"server"`
}

type sendCallbackParam struct {
	signature
	*Callback
}

func SendCallback(uri string, data *Callback) error {
	if m.CallbackServer != "" {
		data.Server = m.CallbackServer
	}

	param := sendCallbackParam{
		signature: newSignature(),
		Callback:  data,
	}

	marshal, err := json.Marshal(param)
	if err != nil {
		return err
	}

	path, err := url.JoinPath(m.URL, uri)
	if err != nil {
		return err
	}

	req, err := http.NewRequest("POST", path, bytes.NewBuffer(marshal))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != http.StatusOK {
		//body, _ := io.ReadAll(resp.Body)
		return errors.New(string(body))
	}

	return nil
}

func VideoSyncCallback(data *Callback) error {
	return SendCallback(CALLBACK_API_VIDEO_SYNC, data)
}

func UpdateMsgCallback(data *Callback) error {
	return SendCallback(CALLBACK_API_UPDATE_MSG, data)
}
