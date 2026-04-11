package main

import (
	"encoding/json"
	"errors"
	"io"
	"log"
	"net/http"
	"sync"
	"time"
)

type InfoResult struct {
	Code int    `json:"code"`
	Msg  string `json:"msg"`
	Data struct {
		QueueCount int       `json:"queueCount"`
		Queue      []string  `json:"queue"`
		Downloader string    `json:"downloader"`
		Imager     string    `json:"imager"`
		Editor     string    `json:"editor"`
		Sender     string    `json:"sender"`
		Processing bool      `json:"processing"`
		Time       time.Time `json:"time"`
	} `json:"data"`
}

type InfoStore struct {
	mutex sync.Mutex
	wg    sync.WaitGroup

	data map[string]*InfoResult
	errs map[string]error
}

func (s *InfoStore) fetch(client *http.Client, ip string) {
	defer s.wg.Done()

	var (
		err    error
		resp   *http.Response
		body   []byte
		result *InfoResult
	)

	resp, err = client.Get("http://" + ip + ":5566/api/info")
	if err != nil {
		goto ERROR
	}
	defer resp.Body.Close()

	body, err = io.ReadAll(resp.Body)
	if err != nil {
		goto ERROR
	}

	if resp.StatusCode != http.StatusOK {
		err = errors.New(string(body))
		goto ERROR
	}

	result = new(InfoResult)
	if err = json.Unmarshal(body, result); err != nil {
		goto ERROR
	}

	s.mutex.Lock()
	defer s.mutex.Unlock()
	s.data[ip] = result
	return
ERROR:
	s.mutex.Lock()
	defer s.mutex.Unlock()
	s.errs[ip] = err
}

func GetCutInfos(ips []string) (map[string]*InfoResult, map[string]error) {
	client := &http.Client{
		Timeout: 10 * time.Second,
	}

	store := &InfoStore{
		mutex: sync.Mutex{},
		wg:    sync.WaitGroup{},
		data:  make(map[string]*InfoResult, len(ips)),
		errs:  make(map[string]error, len(ips)),
	}

	store.wg.Add(len(ips))
	for _, ip := range ips {
		go store.fetch(client, ip)
	}

	store.wg.Wait()

	for ip, data := range store.data {
		log.Printf("%s %+v", ip, data.Data)
	}
	return store.data, store.errs
}
