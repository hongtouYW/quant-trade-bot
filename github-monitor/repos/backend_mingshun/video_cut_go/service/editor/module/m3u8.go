package module

import (
	"fmt"
	"math/rand"
	"os"
	"path/filepath"
	"video_cut_go/ffmpeg"
)

// M3U8 转换视频到M3U8
type M3U8 struct {
	Enable   bool `json:"enable"`
	Encrypt  bool `json:"encrypt"`
	Interval int  `json:"interval"`
	// Bitrate 比特率
	Bitrate int `json:"bitrate"`
	// Fps 帧数
	Fps int `json:"fps"`
}

func (m *M3U8) SetDefault() {
	if m.Interval == 0 {
		m.Interval = 10
	}
}

const (
	keyFile  = "enc.key"
	infoFile = "enc.info"
)

func (m *M3U8) GenerateKey(kf, infoFile string) error {
	key := make([]byte, 16)

	_, err := rand.Read(key)
	if err != nil {
		return err
	}

	err = os.WriteFile(kf, key, 0644)
	if err != nil {
		return err
	}

	return os.WriteFile(infoFile, []byte(fmt.Sprintf("%s\n%s", keyFile, kf)), 0666)
}

func (m *M3U8) Run(d *VideoData) bool {
	if !m.Enable {
		return true
	}

	d.Log.Infof(d.Identifier, "processing m3u8 rule")
	playIndex, err := m.m3u8(d, "1")
	if err != nil {
		d.Log.Errorf(d.Identifier, "process m3u8 rule error: %s", err)
		return false
	}
	d.Data.PlayURL = playIndex

	if d.Preview != "" {
		d.Log.Infof(d.Identifier, "processing preview m3u8 rule")
		previewIndex, err := m.m3u8(d, "preview")
		if err != nil {
			d.Log.Errorf(d.Identifier, "process preview m3u8 rule error: %s", err)
			return false
		}
		_ = os.Remove(d.Preview)
		d.Preview = previewIndex
		d.Data.Preview = previewIndex
	}

	return true
}

func (m *M3U8) m3u8(d *VideoData, version string) (string, error) {
	root := filepath.Join(filepath.Dir(d.Video), "hls", version)
	err := os.MkdirAll(root, 0750)
	if err != nil {
		return "", fmt.Errorf("创建文件夹失败: %s", err)
	}

	m3u8File := filepath.Join(root, "index.m3u8")

	var extend string
	if m.Encrypt {
		keyF := filepath.Join(root, keyFile)
		infoF := filepath.Join(root, infoFile)
		if err = m.GenerateKey(keyF, infoF); err != nil {
			return "", fmt.Errorf("创建key文件失败: %s", err)
		}
		defer os.Remove(infoF)

		extend += " -hls_key_info_file " + infoF
	}

	if m.Bitrate > 0 {
		extend += fmt.Sprintf(" -b %dK ", m.Bitrate)
	}

	if m.Fps > 0 {
		extend += fmt.Sprintf(" -r %d ", m.Fps)
	}

	video := d.Video
	if version == "preview" {
		video = d.Preview
	}

	args := fmt.Sprintf(`-loglevel error -i %s -hls_time %d -force_key_frames "expr:gte(t,n_forced*1)"%s -hls_playlist_type vod -hls_list_size 0 -strict -2 -progress pipe:1 -nostats -max_muxing_queue_size 9999 -f hls %s`,
		video, m.Interval, extend, m3u8File)

	if err = ffmpeg.ExecWithProgress(args, d.Detail.GetDuration(), d.Progress); err != nil {
		return "", fmt.Errorf("生成m3u8失败: %s", err)
	}

	return m3u8File, nil
}
