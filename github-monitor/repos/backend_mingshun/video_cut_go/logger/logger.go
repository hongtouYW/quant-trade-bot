package logger

import (
	"fmt"
	"go.uber.org/zap"
	"go.uber.org/zap/zapcore"
	"gopkg.in/natefinch/lumberjack.v2"
	"os"
	"strings"
	"video_cut_go/mingshun"
)

type Logger struct {
	writer *zap.Logger
	sugar  *zap.SugaredLogger
	config *Config
	level  zapcore.Level
}

var breakLine = strings.Repeat("-", 50)

func New(config *Config, opts ...zap.Option) (*Logger, error) {
	config.init()
	return initLogger(config, opts...)
}

func initLogger(config *Config, opts ...zap.Option) (*Logger, error) {
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
	zapLog = zapLog.WithOptions(opts...)
	sugar := zapLog.Sugar()
	return &Logger{
		writer: zapLog,
		sugar:  sugar,
		config: config,
		level:  zapLevel,
	}, nil
}

func (l *Logger) Debug(str string, args ...zap.Field) {
	l.writer.Debug(str, args...)
}

func (l *Logger) Info(str string, args ...zap.Field) {
	l.writer.Info(str, args...)
}

func (l *Logger) Warn(str string, args ...zap.Field) {
	l.writer.Warn(str, args...)
}

func (l *Logger) Error(str string, args ...zap.Field) {
	l.writer.Error(str, args...)
}

func (l *Logger) Debugf(id, str string, args ...interface{}) {
	if l.level > zapcore.DebugLevel {
		return
	}
	l.sugar.Debugf(fmt.Sprintf(id+": "+str, args...))
}
func (l *Logger) Infof(id, str string, args ...interface{}) {
	if l.level > zapcore.InfoLevel {
		return
	}
	l.sugar.Infof(fmt.Sprintf(id+": "+str, args...))
}
func (l *Logger) Warnf(id, str string, args ...interface{}) {
	if l.level > zapcore.WarnLevel {
		return
	}
	l.sugar.Warnf(fmt.Sprintf(id+": "+str, args...))
}
func (l *Logger) Errorf(id, str string, args ...interface{}) {
	if l.level > zapcore.ErrorLevel {
		return
	}
	l.sugar.Errorf(id+": "+str, args...)
	l.Callback(mingshun.CALLBACK_VIDEO_ERROR_STATUS, id, str, args...)
}
func (l *Logger) RawErrorf(id, str string, args ...interface{}) {
	if l.level > zapcore.ErrorLevel {
		return
	}
	l.sugar.Errorf(id+": "+str, args...)
}
func (l *Logger) Sync() error {
	return l.writer.Sync()
}

func (l *Logger) Callback(status int8, id, str string, args ...interface{}) {
	if l.config.Callback == "" {
		return
	}

	err := mingshun.SendCallback(l.config.Callback, &mingshun.Callback{
		Identifier: id,
		Status:     status,
		Msg:        fmt.Sprintf(str, args...),
	})
	if err != nil {
		l.Error(id + ": mingshun callback error: " + err.Error())
	}
}

func (l *Logger) BreakLine() {
	l.writer.Info(breakLine)
}
