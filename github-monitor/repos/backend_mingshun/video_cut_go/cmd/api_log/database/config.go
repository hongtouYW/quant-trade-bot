package database

import "github.com/go-playground/validator/v10"

type Config struct {
	Username string `yaml:"username" validate:"required"`
	Password string `yaml:"password" validate:"required"`
	Host     string `yaml:"host" validate:"required"`
	Port     uint16 `yaml:"port" validate:"required"`
	Database string `yaml:"database" validate:"required"`
}

func (c *Config) validate() error {
	validate := validator.New()
	return validate.Struct(c)
}
