package ffmpeg

import (
	"encoding/json"
	"fmt"
	"strconv"
	"time"
	"video_cut_go/common"
)

type VideoDetail struct {
	Streams []struct {
		Index              int    `json:"index"`
		CodecName          string `json:"codec_name"`
		CodecLongName      string `json:"codec_long_name"`
		Profile            string `json:"profile"`
		CodecType          string `json:"codec_type"`
		CodecTagString     string `json:"codec_tag_string"`
		CodecTag           string `json:"codec_tag"`
		Width              int    `json:"width,omitempty"`
		Height             int    `json:"height,omitempty"`
		CodedWidth         int    `json:"coded_width,omitempty"`
		CodedHeight        int    `json:"coded_height,omitempty"`
		ClosedCaptions     int    `json:"closed_captions,omitempty"`
		FilmGrain          int    `json:"film_grain,omitempty"`
		HasBFrames         int    `json:"has_b_frames,omitempty"`
		SampleAspectRatio  string `json:"sample_aspect_ratio,omitempty"`
		DisplayAspectRatio string `json:"display_aspect_ratio,omitempty"`
		PixFmt             string `json:"pix_fmt,omitempty"`
		Level              int    `json:"level,omitempty"`
		ColorRange         string `json:"color_range,omitempty"`
		ColorSpace         string `json:"color_space,omitempty"`
		ColorTransfer      string `json:"color_transfer,omitempty"`
		ColorPrimaries     string `json:"color_primaries,omitempty"`
		ChromaLocation     string `json:"chroma_location,omitempty"`
		FieldOrder         string `json:"field_order,omitempty"`
		Refs               int    `json:"refs,omitempty"`
		IsAvc              string `json:"is_avc,omitempty"`
		NalLengthSize      string `json:"nal_length_size,omitempty"`
		ID                 string `json:"id"`
		RFrameRate         string `json:"r_frame_rate"`
		AvgFrameRate       string `json:"avg_frame_rate"`
		TimeBase           string `json:"time_base"`
		StartPts           int    `json:"start_pts"`
		StartTime          string `json:"start_time"`
		DurationTs         int    `json:"duration_ts"`
		Duration           string `json:"duration"`
		BitRate            string `json:"bit_rate"`
		BitsPerRawSample   string `json:"bits_per_raw_sample,omitempty"`
		NbFrames           string `json:"nb_frames"`
		ExtradataSize      int    `json:"extradata_size"`
		Disposition        struct {
			Default         int `json:"default"`
			Dub             int `json:"dub"`
			Original        int `json:"original"`
			Comment         int `json:"comment"`
			Lyrics          int `json:"lyrics"`
			Karaoke         int `json:"karaoke"`
			Forced          int `json:"forced"`
			HearingImpaired int `json:"hearing_impaired"`
			VisualImpaired  int `json:"visual_impaired"`
			CleanEffects    int `json:"clean_effects"`
			AttachedPic     int `json:"attached_pic"`
			TimedThumbnails int `json:"timed_thumbnails"`
			Captions        int `json:"captions"`
			Descriptions    int `json:"descriptions"`
			Metadata        int `json:"metadata"`
			Dependent       int `json:"dependent"`
			StillImage      int `json:"still_image"`
		} `json:"disposition"`
		Tags struct {
			CreationTime time.Time `json:"creation_time"`
			Language     string    `json:"language"`
			HandlerName  string    `json:"handler_name"`
			VendorID     string    `json:"vendor_id"`
		} `json:"tags"`
		SampleFmt      string `json:"sample_fmt,omitempty"`
		SampleRate     string `json:"sample_rate,omitempty"`
		Channels       int    `json:"channels,omitempty"`
		ChannelLayout  string `json:"channel_layout,omitempty"`
		BitsPerSample  int    `json:"bits_per_sample,omitempty"`
		InitialPadding int    `json:"initial_padding,omitempty"`
	} `json:"streams"`
	Format struct {
		Filename       string `json:"filename"`
		NbStreams      int    `json:"nb_streams"`
		NbPrograms     int    `json:"nb_programs"`
		FormatName     string `json:"format_name"`
		FormatLongName string `json:"format_long_name"`
		StartTime      string `json:"start_time"`
		Duration       string `json:"duration"`
		Size           string `json:"size"`
		BitRate        string `json:"bit_rate"`
		ProbeScore     int    `json:"probe_score"`
		Tags           struct {
			MajorBrand       string    `json:"major_brand"`
			MinorVersion     string    `json:"minor_version"`
			CompatibleBrands string    `json:"compatible_brands"`
			CreationTime     time.Time `json:"creation_time"`
		} `json:"tags"`
	} `json:"format"`
}

func (p *VideoDetail) GetShape() (int, int) {
	for _, stream := range p.Streams {
		if stream.CodecType == "video" {
			return stream.Width, stream.Height
		}
	}
	return 0, 0
}

func (p *VideoDetail) GetResolution() string {
	width, height := p.GetShape()
	return fmt.Sprintf("%dx%d", width, height)
}

func (p *VideoDetail) GetDuration() int {
	for _, stream := range p.Streams {
		if stream.CodecType == "video" {
			num, _ := strconv.ParseFloat(stream.Duration, 64)
			return int(num)
		}
	}
	return 0
}

func (p *VideoDetail) GetCodecName() string {
	for _, stream := range p.Streams {
		if stream.CodecType == "video" {
			return stream.CodecName
		}
	}
	return ""
}

func (p *VideoDetail) GetSAR() string {
	for _, stream := range p.Streams {
		if stream.CodecType == "video" {
			if stream.SampleAspectRatio == "" {
				return "0:1"
			}
			return stream.SampleAspectRatio
		}
	}
	return "0:1"
}

func GetVideoDetail(mp4 string) (*VideoDetail, error) {
	cmd := "/home/ffmpeg/ffprobe -v quiet -show_format -show_streams -print_format json " + mp4
	output, err := common.BashExecute(cmd)
	if err != nil {
		return nil, fmt.Errorf("GetVideoDetail %s error: %s", mp4, err)
	}

	detail := new(VideoDetail)
	if err = json.Unmarshal([]byte(output), detail); err != nil {
		return nil, err
	}

	return detail, nil
}
