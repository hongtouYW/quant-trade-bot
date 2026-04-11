package editor

import (
	"errors"
	"github.com/go-playground/validator/v10"
	"video_cut_go/service/editor/module"
	"video_cut_go/service/sender"
)

type Config struct {
	Identifier string           `json:"identifier" validate:"required"`
	ProjectId  int              `json:"projectId"`
	Rule       *Rule            `json:"rule"`
	Video      string           `json:"video" validate:"required"`
	Cover      string           `json:"cover"`
	CoverVer   string           `json:"coverVer"`
	Data       *module.Data     `json:"data"`
	Receiver   *sender.Receiver `json:"receiver" validate:"required"`
}

func (c *Config) Validate() error {
	validate := validator.New()
	if err := validate.Struct(c); err != nil {
		return err
	}

	if c.Rule == nil {
		return errors.New("require rule")
	}

	if c.Rule.Ad.Enable && c.Rule.Ad.Image == "" {
		return errors.New("ad enabled but image is missing")
	}

	if c.Rule.Logo.Enable && c.Rule.Logo.Image == "" {
		return errors.New("logo enabled but image is missing")
	}

	if c.Rule.HeadVideo.Enable && c.Rule.HeadVideo.Video == "" {
		return errors.New("head_video enabled but video is missing")
	}

	return nil
}

type Rule struct {
	Webp module.Webp `json:"webp"`
	module.WaterMark
	Preview    module.Preview     `json:"preview"`
	M3U8       module.M3U8        `json:"m3u8"`
	AiSubtitle *sender.AiSubtitle `json:"aiSubtitle"`
}

func (r *Rule) SetDefault() {
	r.Font.SetDefault()
	r.Logo.SetDefault()
	r.Preview.SetDefault()
	r.M3U8.SetDefault()
	r.Webp.SetDefault()
	r.Subtitle.SetDefault()
}

func (r *Rule) Run(status *string, vd *module.VideoData) (success bool) {
	defer close(vd.Progress)
	*status = "webp"
	if !r.Webp.Run(vd) {
		return
	}

	*status = "watermark"
	if !r.WaterMark.Run(vd) {
		return
	}

	*status = "preview"
	if !r.Preview.Run(vd) {
		return
	}

	*status = "m3u8"
	if !r.M3U8.Run(vd) {
		return
	}

	//switch false {
	//case r.Webp.Run(vd):
	//	return
	//case r.WaterMark.Run(vd):
	//	return
	//case r.Preview.Run(vd):
	//	return
	//case r.M3U8.Run(vd):
	//	return
	//}
	return true
}
