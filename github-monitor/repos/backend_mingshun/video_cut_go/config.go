package main

import (
	"errors"
	"os"
	"video_cut_go/mingshun"
	"video_cut_go/service/backuper"

	"gopkg.in/yaml.v3"
)

type RedisConf struct {
	Addr     string `yaml:"addr"`
	Password string `yaml:"password"`
	DB       int    `yaml:"DB"`
}

type ClickhouseConf struct {
	Addr     string `yaml:"host"`
	Username string `yaml:"username"`
	Password string `yaml:"password"`
	Database string `yaml:"database"`
}

type Config struct {
	Remote     *RedisConf       `yaml:"remote"`
	Local      *RedisConf       `yaml:"local"`
	Mingshun   *mingshun.Config `yaml:"mingshun"`
	Clickhouse *ClickhouseConf  `yaml:"clickhouse"`
	Backuper   *backuper.Config `yaml:"backuper"`
}

func (c *Config) validate() error {
	if c.Local == nil {
		return errors.New("local redis not found")
	}

	if c.Remote == nil {
		return errors.New("remote redis not found")
	}

	if c.Mingshun == nil {
		return errors.New("mingshun not found")
	}

	if c.Clickhouse == nil {
		return errors.New("clickhouse not found")
	}

	if c.Backuper == nil {
		return errors.New("backuper not found")
	}
	return nil
}

func (c *Config) ReadFromFile(file string) error {
	content, err := os.ReadFile(file)
	if err != nil {
		return err
	}

	replacedData := os.ExpandEnv(string(content))

	if err = yaml.Unmarshal([]byte(replacedData), c); err != nil {
		return err
	}

	return c.validate()
}
