package check_video_cuted

import (
	"bufio"
	"errors"
	"os"
	"strings"
	"sync"
	"time"
)

type Result struct {
	NodeIP   string `json:"nodeIP"`
	DateTime string `json:"dateTime"`
	Status   string `json:"status"`
	raw      string
}

func Run(videoID string) (*Result, error) {
	var results []*Result
	file, err := os.Open("node.list")
	if err != nil {
		return nil, err
	}
	defer file.Close()

	var wg sync.WaitGroup
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		if scanner.Text() == "" {
			continue
		}
		wg.Add(1)

		ip := scanner.Text()
		go func(ip string) {
			defer wg.Done()
			logs, err := getNodeLog(ip, videoID)
			if err != nil {
				return
			}

			if logs != "" {
				results = append(results, &Result{NodeIP: ip, raw: logs})
			}
		}(ip)
	}

	if scanner.Err() != nil {
		return nil, err
	}

	wg.Wait()
	if len(results) == 0 {
		return nil, errors.New("result not found")
	}

	latestIndex := 0
	for i, result := range results {
		logScanner := bufio.NewScanner(strings.NewReader(result.raw))
		var lastLine string
		for logScanner.Scan() {
			line := logScanner.Text()
			if strings.Contains(line, "__"+videoID) {
				lastLine = line
			}
		}

		contents := strings.Split(lastLine, "\t")
		result.DateTime = contents[0]
		result.Status = contents[1]
		result.raw = ""

		latestTime, _ := time.Parse("2006-01-02T15:04:05.999-0700", results[latestIndex].DateTime)
		nowTime, _ := time.Parse("2006-01-02T15:04:05.999-0700", result.DateTime)
		if latestTime.Before(nowTime) {
			latestIndex = i
		}
	}

	return results[latestIndex], nil
}
