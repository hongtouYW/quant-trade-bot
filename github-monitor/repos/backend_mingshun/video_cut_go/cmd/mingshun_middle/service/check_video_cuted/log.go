package check_video_cuted

import (
	"encoding/json"
	"errors"
	"io"
	"log"
	"net/http"
	"video_cut_go/api/util/response"
)

func getNodeLog(nodeIP string, id string) (string, error) {
	resp, err := http.Get("http://" + nodeIP + ":5566/api/log/__" + id)
	if err != nil {
		log.Println(err)
		return "", errors.New("fetch log error")
	}
	defer resp.Body.Close()

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", err
	}

	var result response.Msg
	if err = json.Unmarshal(body, &result); err != nil {
		return "", err
	}

	if resp.StatusCode != http.StatusOK {
		return "", errors.New("fetch log error: " + result.Msg)
	}

	return result.Data.(string), nil
}
