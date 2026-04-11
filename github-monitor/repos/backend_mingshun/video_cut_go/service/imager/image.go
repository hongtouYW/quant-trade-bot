package imager

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"video_cut_go/common/util/image"
	"video_cut_go/ffmpeg"
	"video_cut_go/metrics"
	"video_cut_go/service/editor"
)

func (i *Imager) image() (success bool) {
	i.status = "getting new task"
	config, err := i.getNewConfig()
	if err != nil {
		i.status = "error"
		i.log.Error("fetch config error: " + err.Error())
		return
	}

	if config == nil {
		return
	}
	metrics.VideoImager.Inc()

	i.log.BreakLine()
	i.status = "processing"
	i.log.Infof(config.Identifier, "processing config")
	root := filepath.Join(editor.RootPath, config.Identifier)
	defer func() {
		if !success {
			_ = os.RemoveAll(root)
		}
	}()
	i.status = "processing cover"
	if !i.cover(root, config) {
		return
	}

	i.status = "processing thumbnail"
	if !i.generateVideoThumb(root, config) {
		return
	}
	//switch false {
	//case i.cover(root, config):
	//	return
	//case i.generateVideoThumb(root, config):
	//	return
	//}

	i.log.Infof(config.Identifier, "pass config to %s", editor.RedisKeyName)
	if err = i.passToNext(config); err != nil {
		i.log.Errorf(config.Identifier, "pass to next error: %s", err)
		return
	}
	i.status = "done"
	i.log.Infof(config.Identifier, "imaging success")
	return true
}

func (i *Imager) cover(root string, config *editor.Config) bool {
	if config.Cover == "" {
		i.log.Infof(config.Identifier, "generating cover image")
		config.Cover = filepath.Join(root, config.Identifier+".jpg")
		cmd, err := ffmpeg.ScreenshotFromMP4(config.Video, config.Cover, 0, 1, "")
		i.log.Infof(config.Identifier, "process ffmpeg: %s", cmd)
		if err != nil {
			i.log.Errorf(config.Identifier, "generate cover error: %s", err)
			return false
		}
		config.Data.Cover = config.Cover
	}

	i.log.Infof(config.Identifier, "generating cover webp")
	coverWebp := config.Cover[:len(config.Cover)-3] + "webp"
	if err := image.JPGtoWebP(config.Cover, coverWebp); err != nil {
		i.log.Errorf(config.Identifier, "generate cover webp error: %s", err)
		return false
	}

	// ---------------------------- cover_hor ----------------------------
	i.log.Infof(config.Identifier, "generating cover_hor")
	hor := filepath.Join(root, config.Identifier+"_hor.jpg")
	err := image.Resize(config.Cover, hor, 310, 174)
	if err != nil {
		i.log.Errorf(config.Identifier, "generate cover_hor error: %s", err)
		return false
	}
	config.Data.ThumbHor = hor

	i.log.Infof(config.Identifier, "generating cover_hor webp")
	if err = image.JPGtoWebP(hor, hor[:len(hor)-3]+"webp"); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_hor webp error: %s", err)
		return false
	}

	// ---------------------------- cover_ver ----------------------------
	ver := filepath.Join(root, config.Identifier+"_ver.jpg")
	if config.Data.ThumbVer == "" {
		i.log.Infof(config.Identifier, "generating cover_ver")
		if err = image.Resize(config.Cover, ver, 180, 246); err != nil {
			i.log.Errorf(config.Identifier, "generate cover_ver error: %s", err)
			return false
		}
		config.Data.ThumbVer = ver
	} else {
		i.log.Infof(config.Identifier, "skip generate cover_ver because exist")
	}

	i.log.Infof(config.Identifier, "generating cover_ver webp")
	if err = image.JPGtoWebP(ver, ver[:len(ver)-3]+"webp"); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_ver webp error: %s", err)
		return false
	}

	// ---------------------------- cover_hor_large ----------------------------
	i.log.Infof(config.Identifier, "generating cover_hor_large")
	horLarge := filepath.Join(root, config.Identifier+"_hor_large.jpg")
	if err = image.Resize(config.Cover, horLarge, 310*5, 174*5); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_hor_large error: %s", err)
		return false
	}

	i.log.Infof(config.Identifier, "generating cover_hor_large webp")
	if err = image.JPGtoWebP(horLarge, horLarge[:len(horLarge)-3]+"webp"); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_hor_large webp error: %s", err)
		return false
	}

	// ---------------------------- cover_ver_large ----------------------------
	i.log.Infof(config.Identifier, "generating cover_ver_large")
	verLarge := filepath.Join(root, config.Identifier+"_ver_large.jpg")
	if err = image.Resize(config.Cover, verLarge, 180*5, 246*5); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_ver_large error: %s", err)
		return false
	}

	if err = image.JPGtoWebP(verLarge, verLarge[:len(verLarge)-3]+"webp"); err != nil {
		i.log.Errorf(config.Identifier, "generate cover_ver_large webp error: %s", err)
		return false
	}

	return true
}

func (i *Imager) generateVideoThumb(root string, config *editor.Config) bool {
	var count int
	var interval int

	i.log.Infof(config.Identifier, "fetching video detail")
	detail, err := ffmpeg.GetVideoDetail(config.Video)
	if err != nil {
		i.log.Errorf(config.Identifier, "fetch video detail error: %s", err)
		return false
	}

	duration := detail.GetDuration()
	if duration < 6 {
		count = 1
		interval = 1
	} else if duration < 30 {
		count = 5
		interval = (duration - 1) / count
	} else if duration < 600 {
		count = 10
		interval = (duration - 3) / count
	} else {
		count = 25
		interval = (duration - 3) / count
	}

	i.log.Infof(config.Identifier, "generating video thumbnail")
	output := filepath.Join(root, config.Identifier+"_thumb_%2d.jpg")
	cmd, err := ffmpeg.ScreenshotFromMP4(config.Video, output, interval, count, "")
	i.log.Infof(config.Identifier, "process ffmpeg: %s", cmd)
	if err != nil {
		i.log.Errorf(config.Identifier, "generate video thumbnail error: %s", err)
		return false
	}

	thumbSeries := make([]int, 0, count)
	increase := duration / count
	i.log.Infof(config.Identifier, "renaming video thumbnail according video second")
	for c := 1; c <= count; c++ {
		s := (c-1)*increase + 3
		src := filepath.Join(root, fmt.Sprintf("%s_thumb_%02d.jpg", config.Identifier, c))
		dst := filepath.Join(root, fmt.Sprintf("%s_thumb_%d.jpg", config.Identifier, s))
		if err = os.Rename(src, dst); err != nil {
			// 由于视频文件部分损坏，导致无法正常读取或解码部分内容，所以生成不了指定帧数的thumbnail。
			if errors.Is(err, os.ErrNotExist) {
				i.log.Errorf(config.Identifier, "Due to partial corruption of the video file, some content cannot be properly read or decoded.")
			} else {
				i.log.Errorf(config.Identifier, "rename video thumbnail error: %s", err)
			}
			return false
		}
		thumbSeries = append(thumbSeries, s)
	}

	glob, err := filepath.Glob(filepath.Join(filepath.Dir(config.Cover), config.Identifier+"_thumb_*.jpg"))
	if err != nil {
		i.log.Errorf(config.Identifier, "glob video thumbnail error: %s", err)
		return false
	}

	i.log.Infof(config.Identifier, "generating video thumbnail webp")
	for _, thumb := range glob {
		if err = image.JPGtoWebP(thumb, thumb[:len(thumb)-3]+"webp"); err != nil {
			i.log.Errorf(config.Identifier, "generate video thumbnail webp error: %s", err)
			return false
		}
	}

	config.Data.Thumbnail = filepath.Join(root, fmt.Sprintf("%s_thumb_%d.jpg", config.Identifier, thumbSeries[0]))
	config.Data.ThumbSeries = thumbSeries

	i.log.Infof(config.Identifier, "generating video long thumbnail")
	vWidth, vHeight := detail.GetShape()
	longWidth := 640
	longHeight := longWidth * vHeight / vWidth
	longSize := fmt.Sprintf("%d*%d", longWidth, longHeight)
	config.Data.ThumbLongview = filepath.Join(root, fmt.Sprintf("%s_longPreview.jpg", config.Identifier))
	if duration < 100 {
		cmd, err = ffmpeg.ScreenshotFromMP4(config.Video, config.Data.ThumbLongview, 1, 1, longSize)
		i.log.Infof(config.Identifier, "process ffmpeg: %s", cmd)
		if err != nil {
			i.log.Errorf(config.Identifier, "generate video long thumbnail error: %s", err)
			return false
		}
	} else {
		tmpLong := filepath.Join(root, "long_tmp")
		if err = os.MkdirAll(tmpLong, 0750); err != nil {
			i.log.Errorf(config.Identifier, "generate video long thumbnail error: %s", err)
			return false
		}

		for c := 1; c < 101; c++ {
			cmd, err = ffmpeg.ScreenshotFromMP4(config.Video, filepath.Join(tmpLong, fmt.Sprintf("%d.jpg", c)), (duration*c-1)/100, 1, longSize)
			i.log.Infof(config.Identifier, "process ffmpeg: %s", cmd)
			if err != nil {
				i.log.Errorf(config.Identifier, "generate video long thumbnail error: %s", err)
				return false
			}
		}

		if err = image.Combine(tmpLong, config.Data.ThumbLongview, longWidth, longHeight); err != nil {
			i.log.Errorf(config.Identifier, "generate video long thumbnail combine error: %s", err)
			return false
		}

		_ = os.RemoveAll(tmpLong)
	}
	return true
}
