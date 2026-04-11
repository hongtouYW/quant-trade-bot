package backuper

import (
	"bytes"
	"fmt"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
)

type HttpSyncInfo struct {
	URL         string            `yaml:"url"`
	Method      string            `yaml:"method"`
	Destination string            `yaml:"destination"`
	Header      map[string]string `yaml:"header"`
}

func (b *Backuper) backupToHttp(config *BackupConfig, sync HttpSyncInfo) error {
	file, err := os.Open(config.File)
	if err != nil {
		return err
	}
	defer file.Close()

	destination := filepath.Join(sync.Destination, config.Destination)
	if ext := filepath.Ext(destination); ext == "" {
		filename := filepath.Base(config.File)
		destination = filepath.Join(sync.Destination, config.Destination, filename)
	}

	body := &bytes.Buffer{}
	writer := multipart.NewWriter(body)
	part, _ := writer.CreateFormFile("file", destination)

	if _, err = io.Copy(part, file); err != nil {
		return err
	}

	for k, v := range config.Extra {
		_ = writer.WriteField(k, v)
	}

	if err = writer.Close(); err != nil {
		return err
	}

	req, _ := http.NewRequest(sync.Method, sync.URL, body)
	req.Header.Set("Content-Type", writer.FormDataContentType())
	req.Header.Set("Destination", destination)
	for k, v := range sync.Header {
		req.Header.Set(k, v)
	}

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		raw, err := io.ReadAll(resp.Body)
		if err != nil {
			return err
		}

		return fmt.Errorf("HTTP backup response status not 200: %s", string(raw))
	}

	_ = os.RemoveAll(config.File)
	return nil
}
