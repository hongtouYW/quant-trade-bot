package database

import (
	"fmt"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
	"video_cut_go/cmd/api_log/database/model"
)

var db *gorm.DB

func Init(config *Config) error {
	var err error
	if err = config.validate(); err != nil {
		return err
	}
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		config.Username, config.Password, config.Host, config.Port, config.Database)
	if db, err = gorm.Open(mysql.Open(dsn), &gorm.Config{}); err != nil {
		return err
	}
	return model.Init(db)
}

func Close() error {
	DB, err := db.DB()
	if err != nil {
		return err
	}
	return DB.Close()
}
