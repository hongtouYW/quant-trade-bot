package ffmpeg

import "fmt"

func Cut(input, output string, start, step int, size string) (string, error) {
	var args string
	if size != "" {
		args = fmt.Sprintf("-loglevel error -ss %d -accurate_seek -t %d -i %s -s %s -y -max_muxing_queue_size 9999  -vcodec h264 %s",
			start, step, input, size, output)
	} else {
		args = fmt.Sprintf("-loglevel error -ss %d -accurate_seek -t %d -i %s -y -max_muxing_queue_size 9999  -vcodec h264 %s",
			start, step, input, output)
	}

	return args, Exec(args)
}
