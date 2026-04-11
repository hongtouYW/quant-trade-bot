package mingshun

import (
	"errors"
)

type Config struct {
	URL            string `yaml:"url"`
	CallbackServer string `yaml:"callbackServer"`
}

func (c *Config) validate() error {
	if c.URL == "" {
		return errors.New("mingshun.url not found")
	}

	return nil
}
