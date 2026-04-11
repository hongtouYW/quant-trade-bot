package logger

import (
	"errors"
	"go.uber.org/zap"
	"go.uber.org/zap/zapcore"
	"strings"
)

type Level string

func (l *Level) String() string {
	ls := strings.ToLower(string(*l))
	switch ls {
	case "debug", "info", "warn", "error":
		return ls
	default:
		return ""
	}
}

func (l *Level) ZapLevel() zapcore.Level {
	ls := l.String()
	switch ls {
	case "debug":
		return zap.DebugLevel
	case "info":
		return zap.InfoLevel
	case "warn":
		return zap.WarnLevel
	case "error":
		return zap.ErrorLevel
	default:
		return zap.DebugLevel
	}
}

func (l *Level) UnmarshalYAML(unmarshal func(interface{}) error) error {
	var levelStr string
	if err := unmarshal(&levelStr); err != nil {
		return err
	}

	level := Level(levelStr)
	if level.String() == "" {
		return errors.New("logger.level(" + levelStr + ") not support")
	}
	*l = level
	return nil
}
