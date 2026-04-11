package backuper

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"video_cut_go/common"
	"video_cut_go/metrics"
)

func Backup(config *BackupConfig) error {
	_, ok := br.config.Method[config.Name]
	if !ok {
		return fmt.Errorf("backup method %s havent configure", config.Name)
	}

	filename := filepath.Base(config.File)
	destination := filepath.Join(br.config.Local, filename)
	err := common.CopyFile(config.File, destination)
	if err != nil {
		return err
	}

	config.File = destination
	raw, err := json.Marshal(config)
	if err != nil {
		return err
	}

	return br.lRdb.RPush(context.Background(), RedisKeyName, string(raw)).Err()
}

func (b *Backuper) backup() {
	config, err := b.getNewConfig()
	if err != nil {
		b.log.Error("fetch config error: " + err.Error())
		return
	}

	if config == nil {
		return
	}
	metrics.VideoBackup.Inc()

	b.log.Infof(config.Identifier, "backup %s", config.File)

	syncInfo, ok := b.config.Method[config.Name]
	if !ok {
		b.log.RawErrorf(config.Identifier, "sync info not found: %s", config.Name)
		return
	}

	if _, err = os.Stat(config.File); err != nil {
		b.log.RawErrorf(config.Identifier, "check %s error: %s", config.File, err)
		return
	}

	switch syncInfo.Type {
	case "http":
		err = b.backupToHttp(config, syncInfo.Http)
	}

	if err != nil {
		b.log.RawErrorf(config.Identifier, err.Error())
		raw, _ := json.Marshal(config)
		b.lRdb.RPush(context.Background(), RedisKeyName, raw)
	} else {
		b.log.Infof(config.Identifier, "backup success")
	}
}
