package downloader

import (
	"errors"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"slices"
	"video_cut_go/common"
	"video_cut_go/common/util/image"
	"video_cut_go/metrics"
	"video_cut_go/mingshun"
	"video_cut_go/service/editor"
	"video_cut_go/service/imager"
)

func (d *Downloader) download() (success bool, timeout bool) {
	d.status = "getting new task"
	config, err := d.getNewConfig()
	if err != nil {
		if errors.Is(err, tooManyErr) {
			d.status = "waiting others service complete"
			d.log.Errorf("", "fetch config error: "+err.Error())
			return false, true
		} else {
			d.status = "error"
			if config != nil {
				d.log.Errorf(config.Identifier, "fetch config error: "+err.Error())
			} else {
				d.log.Errorf("", "fetch config error: "+err.Error())

			}
		}
		return
	}

	if config == nil {
		return
	}
	metrics.VideoDownloader.Inc()

	d.log.BreakLine()
	d.status = "processing"
	d.log.Infof(config.Identifier, "processing config")
	if slices.Contains(d.ignoreProject, config.ProjectId) {
		d.log.Errorf(config.Identifier, "ignore project id: %d", config.ProjectId)
		return
	}

	d.Callback(&mingshun.Callback{Identifier: config.Identifier, Msg: "初始化数据中"})

	root := filepath.Join(editor.RootPath, config.Identifier)
	_ = os.MkdirAll(root, 0750)
	defer func() {
		if !success {
			_ = os.RemoveAll(root)
		}
	}()

	switch false {
	case d.video(root, config):
		return
	case d.cover(root, config):
		return
	case d.headVideo(root, config):
		return
	case d.adImage(root, config):
		return
	case d.logoImage(root, config):
		return
	case d.verImage(root, config):
		return
	case d.subtitle(root, config):
		return
	}

	d.log.Infof(config.Identifier, "pass config to %s", imager.RedisKeyName)
	if err = d.passToNext(config); err != nil {
		d.log.Errorf(config.Identifier, "pass to next error: %s", err)
		return
	}
	d.status = "done"
	d.log.Infof(config.Identifier, "download success")
	return true, false
}

func (d *Downloader) mp4(id, url, output string, header http.Header) error {
	httpDownload, err := NewThreadDownloader(id, url, 10, output, header, d.log)
	if err != nil {
		return err
	}

	if err = httpDownload.Download(); err != nil {
		return err
	}

	return nil
}

func (d *Downloader) video(root string, config *editor.Config) bool {
	d.status = "downloading video"
	videoFile := filepath.Join(root, config.Identifier+".mp4")
	if common.ValidateURL(config.Video) {
		d.log.Infof(config.Identifier, "downloading video: %s", config.Video)
		if err := d.mp4(config.Identifier, config.Video, videoFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download video error: %s", err)
			return false
		}
	} else {
		d.log.Infof(config.Identifier, "moving video: %s", config.Video)
		if err := os.Rename(config.Video, videoFile); err != nil {
			d.log.Errorf(config.Identifier, "move video error: %s", err)
			return false
		}
	}

	config.Video = videoFile
	config.Data.PlayURL = videoFile
	config.Data.DownloadURL = videoFile
	return true
}

func normal(url, output string, header http.Header) error {
	resp, err := common.HttpGet(url, header)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("response status: %s", resp.Status)
	}

	return common.HttpSaveToFile(resp, output)
}

func convertImageToJpg(inputFile, outputFile string) error {
	switch filepath.Ext(inputFile) {
	case ".png":
		return image.PngToJpg(inputFile, outputFile)
	case ".jpeg":
		return os.Rename(inputFile, outputFile)
	case ".jpg":
		return os.Rename(inputFile, outputFile)
	case ".webp":
		return image.WebpToJpg(inputFile, outputFile)
	default:
		return errors.New(filepath.Ext(inputFile) + " image extension not support")
	}
}

func convertImageToPng(inputFile, outputFile string) error {
	switch filepath.Ext(inputFile) {
	case ".png":
		return os.Rename(inputFile, outputFile)
	case ".jpeg":
		return image.JpgToPng(inputFile, outputFile)
	case ".jpg":
		return image.JpgToPng(inputFile, outputFile)
	default:
		return errors.New(filepath.Ext(inputFile) + " image extension not support")
	}
}

func (d *Downloader) cover(root string, config *editor.Config) bool {
	if config.Cover == "" {
		return true
	}
	d.status = "downloading cover"

	var oriFile string
	coverFile := filepath.Join(root, config.Identifier+".jpg")
	if common.ValidateURL(config.Cover) {
		oriFile = filepath.Join(root, config.Identifier+filepath.Ext(config.Cover))
		d.log.Infof(config.Identifier, "downloading cover: %s", config.Cover)
		if err := normal(config.Cover, oriFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download cover error: %s", err)
			return false
		}
	} else {
		oriFile = config.Cover
	}

	d.log.Infof(config.Identifier, "converting cover image: %s", oriFile)
	if err := convertImageToJpg(oriFile, coverFile); err != nil {
		d.log.Errorf(config.Identifier, "convert cover image error: %s", err)
		return false
	}

	config.Cover = coverFile
	config.Data.Cover = coverFile
	return true
}

func (d *Downloader) headVideo(root string, config *editor.Config) bool {
	head := config.Rule.HeadVideo
	if !head.Enable {
		return true
	}
	d.status = "downloading headVideo"

	videoFile := filepath.Join(root, config.Identifier+"_head_video.mp4")
	if common.ValidateURL(head.Video) {
		d.log.Infof(config.Identifier, "downloading head video: %s", head.Video)
		if err := d.mp4(config.Identifier, head.Video, videoFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download head video error: %s", err)
			return false
		}
	} else {
		d.log.Infof(config.Identifier, "moving head video: %s", head.Video)
		if err := os.Rename(head.Video, videoFile); err != nil {
			d.log.Errorf(config.Identifier, "move head video error: %s", err)
			return false
		}
	}

	config.Rule.HeadVideo.Video = videoFile
	return true
}

func (d *Downloader) adImage(root string, config *editor.Config) bool {
	ad := config.Rule.Ad
	if !ad.Enable {
		return true
	}
	d.status = "downloading adImage"

	var oriFile string
	imageFile := filepath.Join(root, config.Identifier+"_ad_image.png")
	if common.ValidateURL(ad.Image) {
		oriFile = filepath.Join(root, config.Identifier+"_ad_image"+filepath.Ext(ad.Image))
		d.log.Infof(config.Identifier, "downloading ad image: %s", ad.Image)
		if err := normal(ad.Image, oriFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download ad image error: %s", err)
			return false
		}
	} else {
		oriFile = ad.Image
	}

	d.log.Infof(config.Identifier, "converting ad image: %s", oriFile)
	if err := convertImageToPng(oriFile, imageFile); err != nil {
		d.log.Errorf(config.Identifier, "convert ad image error: %s", err)
		return false
	}

	config.Rule.Ad.Image = imageFile
	return true
}

func (d *Downloader) logoImage(root string, config *editor.Config) bool {
	logo := config.Rule.Logo
	if !logo.Enable {
		return true
	}
	d.status = "downloading logoImage"

	var oriFile string
	imageFile := filepath.Join(root, config.Identifier+"_logo_image.png")
	if common.ValidateURL(logo.Image) {
		oriFile = filepath.Join(root, config.Identifier+"_logo_image"+filepath.Ext(logo.Image))
		d.log.Infof(config.Identifier, "downloading logo image: %s", logo.Image)
		if err := normal(logo.Image, oriFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download logo image error: %s", err)
			return false
		}
	} else {
		oriFile = logo.Image
	}

	d.log.Infof(config.Identifier, "converting logo image: %s", oriFile)
	if err := convertImageToPng(oriFile, imageFile); err != nil {
		d.log.Errorf(config.Identifier, "convert logo image error: %s", err)
		return false
	}

	config.Rule.Logo.Image = imageFile
	return true
}

func (d *Downloader) verImage(root string, config *editor.Config) bool {
	if config.CoverVer == "" {
		return true
	}
	d.status = "downloading verImage"

	var oriFile string
	imageFile := filepath.Join(root, config.Identifier+"_ver.jpg")
	if common.ValidateURL(config.CoverVer) {
		oriFile = filepath.Join(root, config.Identifier+"_ver"+filepath.Ext(config.CoverVer))
		d.log.Infof(config.Identifier, "downloading cover ver image: %s", config.CoverVer)
		if err := normal(config.CoverVer, oriFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download cover ver image error: %s", err)
			return false
		}
	} else {
		oriFile = config.CoverVer
	}

	d.log.Infof(config.Identifier, "converting cover ver image: %s", oriFile)
	if err := convertImageToJpg(oriFile, imageFile); err != nil {
		d.log.Errorf(config.Identifier, "convert cover ver image error: %s", err)
		return false
	}

	config.Data.ThumbVer = imageFile
	return true
}

func (d *Downloader) subtitle(root string, config *editor.Config) bool {
	subtitle := config.Rule.Subtitle

	if !subtitle.Enable {
		return true
	}
	d.status = "downloading subtitle"

	ext := filepath.Ext(subtitle.File)
	subtitleFile := filepath.Join(root, config.Identifier+"_sub"+ext)
	if ext != ".srt" && ext != ".ass" {
		d.log.Errorf(config.Identifier, "subtitle file format not support: %s", ext)
		return false
	}

	if common.ValidateURL(subtitle.File) {
		d.log.Infof(config.Identifier, "downloading subtitle: %s", subtitle.File)
		if err := d.mp4(config.Identifier, subtitle.File, subtitleFile, nil); err != nil {
			d.log.Errorf(config.Identifier, "download subtitle error: %s", err)
			return false
		}
	} else {
		d.log.Infof(config.Identifier, "moving subtitle: %s", subtitle.File)
		if err := os.Rename(subtitle.File, subtitleFile); err != nil {
			d.log.Errorf(config.Identifier, "move subtitle error: %s", err)
			return false
		}
	}

	config.Rule.Subtitle.File = subtitleFile
	return true
}
