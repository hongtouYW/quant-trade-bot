package sender

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strings"
	"time"
	"video_cut_go/common"
	"video_cut_go/metrics"
	"video_cut_go/mingshun"
	"video_cut_go/service/editor/module"
)

//var aiSubtitleServer *AiSubtitleServer

type Receiver struct {
	Username   string `json:"username" validate:"required"`
	Host       string `json:"host" validate:"required,ipv4"`
	Port       uint16 `json:"port" validate:"required"`
	Identifier string `json:"identifier" validate:"required"`
	Path       string `json:"path" validate:"required"`
}

type Config struct {
	Identifier string
	Local      string
	Data       *module.Data
	Receiver   *Receiver
	//AiSubtitle *AiSubtitle
}

func (c *Config) validate() error {
	if c.Identifier == "" {
		return errors.New("identifier not found")
	}

	if c.Local == "" {
		return errors.New("local dir not found")
	}

	stat, err := os.Stat(c.Local)
	if err != nil {
		return errors.New("local dir search not found")
	}

	if !stat.IsDir() {
		return errors.New("local is not a directory")
	}

	return nil
}

const (
	maxRetry      = 10
	retryTimeWait = 60
)

func (s *Sender) send() {
	s.status = "getting new task"
	config, err := s.getNewConfig()
	if err != nil {
		s.status = "error"
		s.log.Error("fetch config error: " + err.Error())
		return
	}

	if config == nil {
		return
	}
	metrics.VideoSender.Inc()

	defer func() {
		s.log.Infof(config.Identifier, "cleaning resource")
		_ = os.RemoveAll(config.Local)
	}()

	s.log.BreakLine()
	s.status = "processing"
	s.log.Infof(config.Identifier, "processing config")
	cmd := fmt.Sprintf(`
rsync -azP \
--timeout=30 \
-e "ssh -p %d \
-o ConnectTimeout=10 \
-o ServerAliveInterval=5 \
-o ServerAliveCountMax=3 \
-o TCPKeepAlive=yes \
-o PubkeyAuthentication=yes \
-o StrictHostKeyChecking=no" \
--rsync-path="mkdir -p %s && rsync" \
%s %s@%s:%s
`,
		config.Receiver.Port,
		config.Receiver.Path,
		config.Local,
		config.Receiver.Username,
		config.Receiver.Host,
		config.Receiver.Path)

	s.log.Infof(config.Identifier, "processing command: %s", cmd)

	var retry int
	for retry = 0; retry <= maxRetry; retry++ {
	    s.status = fmt.Sprintf("sending video to remote: %d times", retry+1)
	    
	    _, err = common.BashExecuteContext(s.ctx, cmd)
	    if err == nil {
	        break
	    }
	
	    if retry < maxRetry && strings.Contains(strings.ToLower(err.Error()), "timeout") {
	        time.Sleep(time.Duration(retryTimeWait) * time.Second)
	        continue
	    }
	
	    break
	}
	
	if err != nil {
	    tag := "send error"
	    if retry > 0 {
	        tag = fmt.Sprintf("send error with %d retry", retry)
	    }
	    s.log.Errorf(config.Identifier, "%s: %v", tag, err)
	    return
	}

	marshal, err := json.Marshal(config.Data)
	if err != nil {
		s.log.Errorf(config.Identifier, "send callback error: %s", err)
		return
	}

	//if config.AiSubtitle.Enable {
	//	s.status = "processing ai_subtitle"
	//	s.Callback(&mingshun.Callback{Identifier: config.Identifier, Msg: "正在准备生成AI字幕"})
	//
	//	s.log.Infof(config.Identifier, "preparing process subtitle")
	//
	//	s.log.Infof(config.Identifier, "converting video to wav")
	//	filename := filepath.Join(config.Local, config.Identifier)
	//	wavFile := filename + ".wav"
	//	cmd, err = ffmpeg.ConvertMp4ToWav(filename+".mp4", wavFile)
	//	s.log.Infof(config.Identifier, "process ffmpeg: %s", cmd)
	//	if err != nil {
	//		s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_PREPARE_ERROR_STATUS, config.Identifier, "convert error: %s", err.Error())
	//		s.log.RawErrorf(config.Identifier, "convert error: %s", err.Error())
	//		return
	//	}
	//
	//	s.log.Infof(config.Identifier, "backup audio file")
	//	backup := aiSubtitleServer.Backup
	//	backupPath := filepath.Join(backup.Path, config.Identifier)
	//	cmd = fmt.Sprintf(`rsync -aP -e "ssh -p %d -o PubkeyAuthentication=yes -o stricthostkeychecking=no" --rsync-path="mkdir -p %s && rsync" %s %s@%s:%s`,
	//		aiSubtitleServer.Port, backupPath, wavFile, backup.Username, backup.Host, backupPath)
	//	s.log.Infof(config.Identifier, "processing command: %s", cmd)
	//	_, err = common.BashExecuteContext(s.ctx, cmd)
	//	if err != nil {
	//		s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_PREPARE_ERROR_STATUS, config.Identifier, "backup error: %s", err.Error())
	//		s.log.RawErrorf(config.Identifier, "backup error: %s", err)
	//		return
	//	}
	//	//err = backuper.Backup(&backuper.BackupConfig{
	//	//	Identifier: config.Identifier,
	//	//	Name:       "wavfile",
	//	//	File:       wavFile,
	//	//	Extra:      map[string]string{"cutInfo": string(marshal)},
	//	//})
	//	//if err != nil {
	//	//	s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_PREPARE_ERROR_STATUS, config.Identifier, "backup error: %s", err.Error())
	//	//	s.log.RawErrorf(config.Identifier, "backup error: %s", err.Error())
	//	//	return
	//	//}
	//
	//	s.log.Infof(config.Identifier, "send audio file to aiSubtitle server")
	//	cmd = fmt.Sprintf(`rsync -aP -e "ssh -p %d -o PubkeyAuthentication=yes -o stricthostkeychecking=no" --rsync-path="mkdir -p %s && rsync" %s %s@%s:%s`,
	//		aiSubtitleServer.Port, aiSubtitleServer.UploadPath, wavFile, aiSubtitleServer.Username, aiSubtitleServer.IP, aiSubtitleServer.UploadPath)
	//	s.log.Infof(config.Identifier, "processing command: %s", cmd)
	//	_, err = common.BashExecuteContext(s.ctx, cmd)
	//	if err != nil {
	//		s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_ERROR_STATUS, config.Identifier, "send error: %s", err.Error())
	//		s.log.RawErrorf(config.Identifier, "send error: %s", err)
	//		return
	//	}
	//
	//	s.log.Infof(config.Identifier, "call aiSubtitle api")
	//	remoteWav := filepath.Join(aiSubtitleServer.UploadPath, config.Identifier+".wav")
	//	if err = s.AiSubtitle(config.Identifier, wavFile, remoteWav, config.AiSubtitle.Language); err != nil {
	//		s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_ERROR_STATUS, config.Identifier, "call error: %s", err)
	//		s.log.RawErrorf(config.Identifier, "call error: %s", err)
	//		return
	//	}
	//
	//	_ = os.RemoveAll(wavFile)
	//}

	s.log.Infof(config.Identifier, "send callback to remote")
	//if config.AiSubtitle.Enable {
	//	s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_PROCESSING_STATUS, config.Identifier, string(marshal))
	//} else {
	//	s.log.Callback(mingshun.CALLBACK_VIDEO_SUCCESS_STATUS, config.Identifier, string(marshal))
	//}
	s.log.Callback(mingshun.CALLBACK_VIDEO_SUCCESS_STATUS, config.Identifier, string(marshal))

	s.status = "done"
	s.log.Infof(config.Identifier, "send success")
	metrics.VideoComplete.Inc()
	return
}
