package main

import (
	"errors"
	"gopkg.in/yaml.v3"
	"os"
)

type RedisConf struct {
	Addr     string `yaml:"addr"`
	Password string `yaml:"password"`
}

type Config struct {
	Redis   *RedisConf     `yaml:"redis"`
	Port    uint16         `yaml:"port"`
	Project map[string]int `yaml:"project"`
}

func (c *Config) validate() error {
	if c.Redis == nil {
		return errors.New("local redis not found")
	}

	if c.Port == 0 {
		c.Port = 10000
	}

	if len(c.Project) == 0 {
		return errors.New("project not found")
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
