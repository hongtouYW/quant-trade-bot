package ffmpeg

import (
	"bufio"
	"errors"
	"fmt"
	"os/exec"
	"strconv"
	"strings"
	"sync"
	"time"
	"video_cut_go/common"
)

const Executable = "/home/ffmpeg/ffmpeg"

func Exec(args string) error {
	_, err := common.BashExecute(Executable + " " + args)
	if err != nil {
		return err
	}
	return nil
}

type Progress struct {
	OutTime  string
	Speed    string
	Progress string
	End      bool
}

func ExecWithProgress(args string, totalDuration int, result chan<- Progress) error {
	cmd := exec.Command("/bin/bash", "-c", Executable+" "+args)

	stdout, _ := cmd.StdoutPipe()
	stderr, _ := cmd.StderrPipe()

	if err := cmd.Start(); err != nil {
		return err
	}

	var wg sync.WaitGroup
	wg.Add(2)

	go func() {
		defer wg.Done()
		scanner := bufio.NewScanner(stdout)
		var progress Progress
		for scanner.Scan() {
			line := scanner.Text()
			if strings.HasPrefix(line, "out_time_ms=") {
				msStr := strings.TrimPrefix(line, "out_time_ms=")
				ms, _ := strconv.ParseInt(msStr, 10, 64)
				d := time.Duration(ms) * time.Microsecond
				percent := d.Seconds() / float64(totalDuration) * 100
				progress.Progress = fmt.Sprintf("%.3f%%", percent)
			} else if strings.HasPrefix(line, "out_time=") {
				progress.OutTime = strings.TrimPrefix(line, "out_time=")
			} else if strings.HasPrefix(line, "speed=") {
				progress.Speed = strings.TrimPrefix(line, "speed=")
			} else if strings.HasPrefix(line, "progress=") {
				if strings.Contains(line, "end") {
					progress.Progress = "100%"
					progress.End = true
				}
				result <- progress
			}
		}
	}()

	var errMsg string
	go func() {
		defer wg.Done()
		scanner := bufio.NewScanner(stderr)
		for scanner.Scan() {
			line := scanner.Text()
			errMsg += line + "\n"
		}
	}()

	wg.Wait()
	if err := cmd.Wait(); err != nil {
		if errMsg != "" {
			return errors.New(errMsg)
		}
		return err
	}

	return nil
}

func ScreenshotFromMP4(input, output string, second, count int, size string) (string, error) {
	var cmd string
	if count <= 1 {
		if size == "" {
			cmd = fmt.Sprintf("%s -y -ss %d -i %s -y -f image2 -vframes %d %s", Executable, second, input, count, output)
		} else {
			cmd = fmt.Sprintf("%s -y -ss %d -i %s -y -s %s -f image2 -vframes %d %s", Executable, second, input, size, count, output)
		}
	} else {
		if size == "" {
			cmd = fmt.Sprintf("%s -y -i %s -vf fps=1/%d -q:v 2 -f image2 -vframes %d %s", Executable, input, second, count, output)
		} else {
			cmd = fmt.Sprintf("%s -y -i %s -vf fps=1/%d -q:v 2 -s %s -f image2 -vframes %d %s", Executable, input, second, size, count, output)
		}
	}

	if _, err := common.BashExecute(cmd); err != nil {
		return cmd, err
	}
	return cmd, nil
}

func ConvertMp4ToWav(input, output string) (string, error) {
	detail, err := GetVideoDetail(input)
	if err != nil {
		return "", err
	}

	sampleRate := ""
	for _, stream := range detail.Streams {
		if stream.CodecType == "audio" {
			if stream.SampleRate != "" {
				sampleRate = stream.SampleRate
				break
			}
		}
	}

	if sampleRate == "" {
		return "", fmt.Errorf("can't find sample rate")
	}

	var cmd string
	cmd = fmt.Sprintf("%s -hide_banner -loglevel error -y -i %s -vn -ac 1 -ar %s -c:a pcm_s16le -f wav %s",
		Executable, input, sampleRate, output)

	if _, err = common.BashExecute(cmd); err != nil {
		return cmd, err
	}
	return cmd, nil
}
