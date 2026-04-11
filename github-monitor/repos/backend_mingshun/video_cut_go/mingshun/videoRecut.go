package mingshun

import (
	"bytes"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/url"
)

type VideoRecutParam struct {
	signature
	Identifier string `json:"identifier"`
	ID         string `json:"id"`
}

func VideoRecut(identifier string, id string) error {
	param := VideoRecutParam{
		signature:  newSignature(),
		Identifier: identifier,
		ID:         id,
	}

	marshal, err := json.Marshal(param)
	if err != nil {
		return err
	}

	path, err := url.JoinPath(m.URL, "api/videoChoose/recut")
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
		return errors.New(string(body))
	}

	return nil
}
