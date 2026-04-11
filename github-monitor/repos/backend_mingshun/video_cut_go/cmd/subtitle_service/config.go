package main

import (
	"errors"
	"gopkg.in/yaml.v3"
	"os"
)

type ClickhouseConf struct {
	Addr     string `yaml:"host"`
	Username string `yaml:"username"`
	Password string `yaml:"password"`
	Database string `yaml:"database"`
}

type RedisConf struct {
	Addr     string `yaml:"addr"`
	Password string `yaml:"password"`
	DB       int    `yaml:"DB"`
}

type Config struct {
	CallbackUrl string          `yaml:"callbackUrl"`
	Port        uint16          `yaml:"port"`
	Redis       *RedisConf      `yaml:"redis"`
	Clickhouse  *ClickhouseConf `yaml:"clickhouse"`

	BackupPath   string `yaml:"backupPath"`
	SubtitlePath string `yaml:"subtitlePath"`
	WavPath      string `yaml:"wavPath"`
}

func (c *Config) validate() error {
	if c.CallbackUrl == "" {
		return errors.New("callbackUrl not found")
	}

	if c.Clickhouse == nil {
		return errors.New("clickhouse not found")
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
