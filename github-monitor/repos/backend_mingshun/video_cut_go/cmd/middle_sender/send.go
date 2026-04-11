package main

import (
	"errors"
	"fmt"
	"github.com/go-playground/validator/v10"
	"github.com/redis/go-redis/v9"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"
	"video_cut_go/mingshun"
)

type Data struct {
	Username   string `json:"username" validate:"required"`
	Host       string `json:"host" validate:"required,ipv4"`
	Port       uint16 `json:"port" validate:"required"`
	Identifier string `json:"identifier" validate:"required"`
	Path       string `json:"path" validate:"required"`
	local      string
}

func (d *Data) validate() error {
	validate := validator.New()
	if err := validate.Struct(d); err != nil {
		return err
	}

	newPath := filepath.Join(config.Resource, d.Identifier)
	stat, err := os.Stat(newPath)
	if err != nil {
		return errors.New(d.Identifier + " file not found")
	}

	if !stat.IsDir() {
		return errors.New(d.Identifier + " directory not found")
	}

	d.local = newPath
	return nil
}

func (s *Sender) send() {
	Logger.Info("fetching new config")
	data, err := s.getNewData()
	if err != nil {
		if err == redis.Nil {
			Logger.Error("fetch config error: currently no config, wait for 1 minute to try again")
			time.Sleep(1 * time.Minute)
		} else {
			Logger.Error("fetch config error: " + err.Error())
		}
		return
	}
	Logger.Infof(data.Identifier, "processing config")

	cmd := fmt.Sprintf(`rsync -aP -e "ssh -p %d -o PubkeyAuthentication=yes -o stricthostkeychecking=no" --rsync-path="mkdir -p %s && rsync" %s %s@%s:%s`,
		data.Port, data.Path, data.local, data.Username, data.Host, data.Path)
	Logger.Infof(data.Identifier, "processing command: %s", cmd)
	output, err := exec.CommandContext(s.ctx, "/bin/bash", "-c", cmd).CombinedOutput()
	if err != nil {
		Logger.Errorf(data.Identifier, "send error: %s", strings.TrimSpace(string(output)))
		return
	}

	Logger.Infof(data.Identifier, "send callback to remote")
	Logger.Callback(mingshun.CALLBACK_VIDEO_SYNC_SUCCESS_STATUS, data.Identifier, "success")

	Logger.Infof(data.Identifier, "cleaning resource")
	_ = os.RemoveAll(data.local)

	Logger.Infof(data.Identifier, "send success")
	return
}
