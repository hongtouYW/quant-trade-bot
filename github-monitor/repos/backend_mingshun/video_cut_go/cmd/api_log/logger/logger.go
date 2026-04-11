package logger

import (
	"errors"
	"fmt"
	"go.uber.org/zap"
	"go.uber.org/zap/zapcore"
	"gopkg.in/natefinch/lumberjack.v2"
	"os"
)

var logger *Logger

type Logger struct {
	writer *zap.Logger
	sugar  *zap.SugaredLogger
	config *Config
	level  zapcore.Level
}

func Init(config *Config) error {
	if logger != nil {
		return errors.New("logger already initialized")
	}

	config.init()
	return initLogger(config)
}

func initLogger(config *Config) error {
	hook := lumberjack.Logger{
		Filename:   config.File,
		MaxSize:    config.MaxSize,
		MaxBackups: config.MaxBackups,
		MaxAge:     config.MaxAge,
		Compress:   config.Compress,
	}
	w := zapcore.AddSync(&hook)

	encoderConfig := zap.NewProductionEncoderConfig()
	encoderConfig.EncodeTime = zapcore.ISO8601TimeEncoder
	zapLevel := config.Level.ZapLevel()
	var core zapcore.Core
	if zapLevel == zapcore.DebugLevel {
		core = zapcore.NewTee(
			zapcore.NewCore(
				zapcore.NewConsoleEncoder(encoderConfig),
				zapcore.Lock(os.Stdout),
				zapLevel,
			),
			zapcore.NewCore(
				zapcore.NewConsoleEncoder(encoderConfig),
				w,
				zapLevel,
			),
		)
	} else {
		core = zapcore.NewCore(
			zapcore.NewConsoleEncoder(encoderConfig),
			w,
			zapLevel,
		)
	}

	zapLog := zap.New(core)
	sugar := zapLog.Sugar()
	logger = &Logger{
		writer: zapLog,
		sugar:  sugar,
		config: config,
		level:  zapLevel,
	}

	return nil
}

func Debug(str string, args ...zap.Field) {
	logger.writer.Debug(str, args...)
}

func Info(str string, args ...zap.Field) {
	logger.writer.Info(str, args...)
}

func Warn(str string, args ...zap.Field) {
	logger.writer.Warn(str, args...)
}

func Error(str string, args ...zap.Field) {
	logger.writer.Error(str, args...)
}

func Debugf(str string, args ...interface{}) {
	if logger.level > zapcore.DebugLevel {
		return
	}
	logger.sugar.Debugf(fmt.Sprintf(str, args...))
}
func Infof(str string, args ...interface{}) {
	if logger.level > zapcore.InfoLevel {
		return
	}
	logger.sugar.Infof(fmt.Sprintf(str, args...))
}
func Warnf(str string, args ...interface{}) {
	if logger.level > zapcore.WarnLevel {
		return
	}
	logger.sugar.Warnf(fmt.Sprintf(str, args...))
}
func Errorf(str string, args ...interface{}) {
	if logger.level > zapcore.ErrorLevel {
		return
	}
	logger.sugar.Errorf(str, args...)
}
func Sync() error {
	return logger.writer.Sync()
}
