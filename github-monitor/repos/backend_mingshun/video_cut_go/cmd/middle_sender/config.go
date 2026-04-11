package main

import (
	"errors"
	"gopkg.in/yaml.v3"
	"os"
	"video_cut_go/mingshun"
)

type RedisConf struct {
	Addr     string `yaml:"addr"`
	Password string `yaml:"password"`
	DB       int    `yaml:"DB"`
}

type Config struct {
	Redis    *RedisConf       `yaml:"redis"`
	Resource string           `yaml:"resource"`
	Mingshun *mingshun.Config `yaml:"mingshun"`
}

func (c *Config) validate() error {
	if c.Redis == nil {
		return errors.New("redis not found")
	}

	if c.Resource == "" {
		return errors.New("resource not found")
	}

	if c.Mingshun == nil {
		return errors.New("mingshun not found")
	}

	return nil
}

func (c *Config) ReadFromFile(file string) error {
	content, err := os.ReadFile(file)
	if err != nil {
		return err
	}

	if err = yaml.Unmarshal(content, c); err != nil {
		return err
	}

	return c.validate()
}
