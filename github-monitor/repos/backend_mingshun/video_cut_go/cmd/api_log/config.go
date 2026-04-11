package main

import (
	"github.com/go-playground/validator/v10"
	"gopkg.in/yaml.v3"
	"os"
	"video_cut_go/cmd/api_log/database"
	"video_cut_go/cmd/api_log/logger"
)

type Config struct {
	Logger   *logger.Config   `yaml:"logger" validate:"required"`
	Database *database.Config `yaml:"database" validate:"required"`
	Port     uint16           `yaml:"port" validate:"required"`
}

func (c *Config) validate() error {
	validate := validator.New()
	return validate.Struct(c)
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
