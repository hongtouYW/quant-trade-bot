package logger

type Config struct {
	File  string `yaml:"file"`
	Level Level  `yaml:"level"`
	// MaxSize log maximum size, unit is mb
	MaxSize int `yaml:"maxsize"`
	// MaxBackups maximum log backup
	MaxBackups int `yaml:"maxBackups"`
	// MaxAge backup log maximum age, unit is day
	MaxAge   int    `yaml:"maxAge"`
	Compress bool   `yaml:"compress"`
	Callback string `yaml:"callback"`
}

func (c *Config) init() {
	if c.File == "" {
		c.File = "/tmp/logger.log"
	}
	if c.Level == "" {
		c.Level = "debug"
	}
	if c.MaxSize <= 0 {
		c.MaxSize = 512
	}
	if c.MaxBackups <= 0 {
		c.MaxBackups = 10
	}
	if c.MaxAge <= 0 {
		c.MaxAge = 7
	}
}
