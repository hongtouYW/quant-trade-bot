package sender

import (
	"context"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"time"
	"video_cut_go/common"
	"video_cut_go/mingshun"
)

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
	Receiver   *Receiver
	Data       string
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

func (s *Sender) send() {
	config, err := s.getNewConfig()
	if err != nil {
		s.log.Error("[SENDER] fetch config error: " + err.Error())
		return
	}

	if config == nil {
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	s.log.BreakLine()
	s.log.Infof(config.Identifier, "[SENDER] processing config")
	receiverPath := config.Receiver.Path
	receiverBasePath := filepath.Join(receiverPath, config.Identifier)
	sourcePath := filepath.Join(basePath, config.Identifier)
	cmd := fmt.Sprintf(`rsync -aP -e "ssh -p %d -o PubkeyAuthentication=yes -o stricthostkeychecking=no" --rsync-path="mkdir -p %s && rsync" %s/* %s@%s:%s`,
		config.Receiver.Port, receiverPath, sourcePath, config.Receiver.Username, config.Receiver.Host, receiverBasePath)
	s.log.Infof(config.Identifier, "[SENDER] send to remote cmd: %s", cmd)
	_, err = common.ShExecuteContext(ctx, cmd)
	if err != nil {
		s.log.Errorf(config.Identifier, "[SENDER] send to remote error: %s", err)
		if errors.Is(err, context.DeadlineExceeded) {
			s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS, config.Identifier, "send timeout")
		} else {
			s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_SEND_ERROR_STATUS, config.Identifier, err.Error())
		}
		return
	}

	_ = os.RemoveAll(sourcePath)

	s.log.Infof(config.Identifier, "[SENDER] send callback to remote")
	s.log.Callback(mingshun.CALLBACK_VIDEO_SUBTITLE_SEND_SUCCESS_STATUS, config.Identifier, config.Data)
	s.log.Infof(config.Identifier, "[SENDER] subtitle send success")
	return
}
