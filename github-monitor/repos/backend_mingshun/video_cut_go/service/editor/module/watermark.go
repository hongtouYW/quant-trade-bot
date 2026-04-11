package module

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
	"video_cut_go/common"
	"video_cut_go/common/util/image"
	"video_cut_go/ffmpeg"
)

var resolutions = []int{480, 720, 1080, 2160}

type WaterMark struct {
	ResolutionOption int       `json:"resolutionOption"`
	Font             Font      `json:"font"`
	HeadVideo        HeadVideo `json:"headVideo"`
	Ad               Ad        `json:"ad"`
	Logo             Logo      `json:"logo"`
	SkipVideo        SkipVideo `json:"skipVideo"`
	Subtitle         Subtitle  `json:"subtitle"`
}

// Font 添加字体跑马灯
type Font struct {
	Enable bool   `json:"enable"`
	Text   string `json:"text"`
	// Color 文字颜色  16进制
	Color string `json:"color"`
	// Size 文字大小
	Size int `json:"size"`
	// Position 0,1,2  上中下
	Position int `json:"position"`
	// Interval 滚动间隔
	Interval int `json:"interval"`
	// Scroll 文字滚动时间
	Scroll int `json:"scroll"`
	// Border 文字边框
	Border int `json:"border"`
	// Shadow 文字阴影
	Shadow bool `json:"shadow"`
	// Space 文字间间隔
	Space int `json:"space"`
}

func (font *Font) SetDefault() {
	if font.Interval == 0 {
		font.Interval = 60
	}
	if font.Scroll == 0 {
		font.Scroll = len(font.Text) / 2
	}
}

// HeadVideo 片头视频
type HeadVideo struct {
	Enable bool   `json:"enable"`
	Video  string `json:"video"`
}

// Ad 片头文字广告
type Ad struct {
	Enable bool   `json:"enable"`
	Image  string `json:"image"`
	Start  int    `json:"start"`
}

// Logo 添加Logo图在视频里
type Logo struct {
	Enable bool   `json:"enable"`
	Image  string `json:"image"`
	// Position 位置 0,1,2,3  左上  右上   左下  右下
	Position int `json:"position"`
	// Padding 距离的边距
	Padding int `json:"padding"`
	// Scale 缩小比例
	Scale int `json:"scale"`
}

type Subtitle struct {
	Enable bool   `json:"enable"`
	File   string `json:"file"`
	Size   int    `json:"size"`
}

func (s *Subtitle) SetDefault() {
	if s.Size == 0 {
		s.Size = 15
	}
}

func (Logo *Logo) SetDefault() {
	if Logo.Scale == 0 {
		Logo.Scale = 15
	}
}

type SkipVideo struct {
	Enable bool `json:"enable"`
	// Head 移除片头(秒)
	Head int `json:"head"`
	// Back 移除片尾(秒)
	Back int `json:"back"`
}

func (m *WaterMark) Run(d *VideoData) bool {
	var cleanList []string
	defer func() {
		for _, f := range cleanList {
			_ = os.Remove(f)
		}
	}()

	d.Log.Infof(d.Identifier, "processing watermark")
	_VideoHeight := resolutions[m.ResolutionOption]
	videoWidth, videoHeight := d.Detail.GetShape()
	if _VideoHeight >= videoHeight {
		_VideoHeight = videoHeight
	}

	_VideoWidth := _VideoHeight * videoWidth / videoHeight
	if _VideoWidth%2 == 1 {
		_VideoWidth += 1
	}

	codec := d.Detail.GetCodecName()
	sar := d.Detail.GetSAR()

	if sar != "0:1" {
		sar = strings.ReplaceAll(sar, ":", "/")
		if codec == "h264" || codec == "hevc" {
			d.SAR = sar
		}
	}

	if d.SAR == "" {
		d.SAR = "1"
	}

	var fontFile string
	if m.Font.Enable {
		if m.Font.Size == 0 {
			m.Font.Size = _VideoHeight / 20
		}
		fontFile = fmt.Sprintf("%d.txt", time.Now().Unix())
		if err := os.WriteFile(fontFile, []byte(m.Font.Text), 0666); err != nil {
			d.Log.Errorf(d.Identifier, "create font text file error: %s", err)
			return false
		}
		cleanList = append(cleanList, fontFile)
	}

	var _blackY int
	if m.HeadVideo.Enable {
		cleanList = append(cleanList, m.HeadVideo.Video)
		headDetail, err := ffmpeg.GetVideoDetail(m.HeadVideo.Video)
		if err != nil {
			d.Log.Errorf(d.Identifier, "fetch head video detail error: %s", err)
			return false
		}

		headX, headY := headDetail.GetShape()
		if headX == 0 {
			d.Log.Errorf(d.Identifier, "fetch head video shape detail failure")
			return false
		}

		headHeight := _VideoWidth * headY / headX
		_blackY = (_VideoHeight - headHeight) / 2
		if _blackY < 0 {
			_blackY = 0
		}
	}

	if m.Ad.Enable {
		cleanList = append(cleanList, m.Ad.Image)
	}

	if m.Logo.Enable {
		cleanList = append(cleanList, m.Logo.Image)
		if err := image.ResizeByScale(m.Logo.Image, m.Logo.Image, _VideoHeight, m.Logo.Scale); err != nil {
			d.Log.Errorf(d.Identifier, "scale logo error: %s", err)
			return false
		}
	}

	duration := d.Detail.GetDuration()
	if m.SkipVideo.Enable {
		if m.SkipVideo.Head >= duration-m.SkipVideo.Back {
			d.Log.Errorf(d.Identifier, "video duration not enough to skip")
			return false
		}
	} else {
		m.SkipVideo.Head = 0
		m.SkipVideo.Back = 0
	}

	if m.Subtitle.Enable {
		cleanList = append(cleanList, m.Subtitle.File)
	}

	output := fmt.Sprintf("%d.mp4", time.Now().Unix())
	cleanList = append(cleanList, output)

	args := m.buildCmd(d, output, _VideoWidth, _VideoHeight, _blackY, fontFile)
	d.Log.Infof(d.Identifier, "process ffmpeg: %s", args)
	err := ffmpeg.ExecWithProgress(args, d.Detail.GetDuration(), d.Progress)
	if err != nil {
		d.Log.Errorf(d.Identifier, "process ffmpeg error: %s", err)
		return false
	}

	_ = os.Remove(d.Video)

	if err = common.MoveFile(output, d.Video); err != nil {
		d.Log.Errorf(d.Identifier, "move process_video error: %s", err)
		return false
	}

	d.Data.PlayURL = d.Video
	d.Data.DownloadURL = d.Video
	return true
}

func (m *WaterMark) buildCmd(d *VideoData, output string, w, h, blackY int, fontFile string) string {
	var pos int
	sign := make(map[string]string)
	sign["input"] = fmt.Sprintf("[%d:v]", pos)

	pos++

	duration := d.Detail.GetDuration()
	totalDuration := duration - m.SkipVideo.Back

	inputCmd := fmt.Sprintf("-i %s", d.Video)
	if m.HeadVideo.Enable {
		sign["head"] = fmt.Sprintf("[%d:v]", pos)
		inputCmd += fmt.Sprintf(" -i %s", m.HeadVideo.Video)
		hd, _ := ffmpeg.GetVideoDetail(m.HeadVideo.Video)
		totalDuration += hd.GetDuration()
		pos++
	}

	if m.Ad.Enable {
		sign["font"] = fmt.Sprintf("[%d:v]", pos)
		inputCmd += fmt.Sprintf(" -i %s", m.Ad.Image)
		pos++
	}

	if m.Logo.Enable {
		sign["water"] = fmt.Sprintf("[%d:v]", pos)
		inputCmd += fmt.Sprintf(" -i %s", m.Logo.Image)
		pos++
	}

	startCmd := fmt.Sprintf(`-loglevel error %s -ss %d -to %d -vsync 2 -filter_complex "`,
		inputCmd, m.SkipVideo.Head, totalDuration)

	var outCmd string
	if m.HeadVideo.Enable {
		outCmd = fmt.Sprintf(`" -map "[v]" -map "[a]" -c:v libx264 -threads %d -c:a aac -b:a 128k -progress pipe:1 -nostats -max_muxing_queue_size 9999 -y `, d.Threads)
	} else {
		outCmd = fmt.Sprintf(`" -c:v libx264 -threads %d -c:a aac -b:a 128k -progress pipe:1 -nostats -max_muxing_queue_size 9999 -y `, d.Threads)
	}

	var filterCmd []string
	if m.Subtitle.Enable {
		fv := "ass"
		if filepath.Ext(m.Subtitle.File) == ".srt" {
			fv = "subtitles"
		}
		filterCmd = append(filterCmd, fmt.Sprintf("%sscale=%d:%d,setsar=%s,%s=%s:force_style='Fontsize=%d'%s",
			sign["input"], w, h, d.SAR, fv, m.Subtitle.File, m.Subtitle.Size, "[resizemp4]"))
	} else {
		filterCmd = append(filterCmd, fmt.Sprintf("%sscale=%d:%d,setsar=%s%s", sign["input"], w, h, d.SAR, "[resizemp4]"))
	}
	sign["input"] = "[resizemp4]"

	// ffmpeg -i "input 1.avi" -filter:v "
	//fps=15,scale=420:-1:flags=lanczos,ass='input 1.ass',split[s0][s1];
	//[s0]palettegen[p];
	//[s1][p]paletteuse
	//" -ss 10:00 -t 5 output.gif -y -hide_banner

	if m.HeadVideo.Enable {
		filterCmd = append(filterCmd, fmt.Sprintf("%sscale=%d:%d,setsar=%s,pad=%d:%d:0:%d:black%s",
			sign["head"], w, h-blackY*2, d.SAR, w, h, blackY, "[resizehead]"))
		sign["head"] = "[resizehead]"
	}

	if m.Ad.Enable {
		filterCmd = append(filterCmd, fmt.Sprintf("%s%sscale2ref=w=(iw*2/3):h=(ow/mdar)[fiamge][resize]",
			sign["font"], sign["input"]))
		sign["input"] = "[font_image]"
		filterCmd = append(filterCmd, fmt.Sprintf("[resize][fiamge]overlay=x=(W/6):(H/4):enable='between(t,0,%d)'%s",
			m.Ad.Start, sign["input"]))
	}

	if m.Font.Enable {
		var shadow string
		if m.Font.Shadow {
			shadow = "shadowx=2:shadowy=2:"
		}

		var position string
		switch m.Font.Position {
		case 2:
			position = fmt.Sprintf("h-line_h-%d", m.Font.Space)
		case 1:
			position = "(h-line_h)/2"
		default:
			position = strconv.Itoa(m.Font.Space)
		}

		filterCmd = append(filterCmd, fmt.Sprintf(`%sdrawtext=textfile=%s:borderw=%d:%sfontfile=font/bos.ttf:fontcolor=%s:fontsize=%d:y=%s:x=w-(text_w+w)/%d*mod(t\,%d):enable=lt(mod(t\,%d)\,%d)[fontwater]`,
			sign["input"], fontFile, m.Font.Border, shadow, m.Font.Color, m.Font.Size, position, m.Font.Interval,
			m.Font.Interval+m.Font.Scroll, m.Font.Interval, m.Font.Interval))
		sign["input"] = "[fontwater]"
	}

	if m.Logo.Enable {
		var overlay string
		switch m.Logo.Position {
		case 1:
			overlay = fmt.Sprintf("overlay=x=main_w-overlay_w-%d:y=%d", m.Logo.Padding, m.Logo.Padding)
		case 2:
			overlay = fmt.Sprintf("overlay=x=%d:y=main_h-overlay_h-%d", m.Logo.Padding, m.Logo.Padding)
		case 3:
			overlay = fmt.Sprintf("overlay=x=main_w-overlay_w-%d:y=main_h-overlay_h-%d", m.Logo.Padding, m.Logo.Padding)
		default:
			overlay = fmt.Sprintf("overlay=x=%d:y=%d", m.Logo.Padding, m.Logo.Padding)
		}
		filterCmd = append(filterCmd, fmt.Sprintf("%s%s%s[addwater]",
			sign["input"], sign["water"], overlay))
		sign["input"] = "[addwater]"
	}

	if m.HeadVideo.Enable {
		filterCmd = append(filterCmd, fmt.Sprintf("%s[1:a]%s[0:a]concat=n=2:v=1:a=1[v][a]",
			sign["head"], sign["input"]))
	}

	lastIndex := len(filterCmd) - 1
	lastFC := filterCmd[lastIndex]
	if lastFC[len(lastFC)-1:] == "]" && lastFC[len(lastFC)-3:] != "[a]" {
		lastLetterIndex := strings.LastIndex(lastFC, "[")
		filterCmd[lastIndex] = lastFC[:lastLetterIndex]
	}

	return startCmd + strings.Join(filterCmd, ";") + outCmd + output
}
