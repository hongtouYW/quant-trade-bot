package mingshun

import (
	"bytes"
	"encoding/json"
	"errors"
	"io"
	"net/http"
	"net/url"
)

type GetReceiverParam struct {
	signature
	Identifier string `json:"identifier"`
	ID         string `json:"id"`
}

type Receiver struct {
	Username string `json:"username" validate:"required"`
	Host     string `json:"host" validate:"required,ipv4"`
	Port     uint16 `json:"port" validate:"required"`
	Path     string `json:"path" validate:"required"`
}

func GetReceiver(identifier string) (*Receiver, error) {
	param := VideoRecutParam{
		signature: newSignature(),
		ID:        identifier,
	}

	marshal, err := json.Marshal(param)
	if err != nil {
		return nil, err
	}

	path, err := url.JoinPath(m.URL, "api/videoServerInfo")
	if err != nil {
		return nil, err
	}

	req, err := http.NewRequest("POST", path, bytes.NewBuffer(marshal))
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != http.StatusOK {
		return nil, errors.New(string(body))
	}

	data := new(Response[Receiver])
	if err = json.Unmarshal(body, data); err != nil {
		return nil, err
	}

	return &data.Data, nil
}
