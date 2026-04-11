package model

import (
	"fmt"
	"gorm.io/gorm"
)

var db *gorm.DB

type Model interface {
	TableName() string
	Default() error
}

func migrate(ms ...Model) (err error) {
	for _, m := range ms {
		if err = db.AutoMigrate(m); err != nil {
			return fmt.Errorf("migrate %s error: %w", m.TableName(), err)
		}

		if err = m.Default(); err != nil {
			return fmt.Errorf("migrate %s default data error: %w", m.TableName(), err)
		}
	}

	return nil
}

func Init(gormDB *gorm.DB) error {
	db = gormDB
	return migrate(&CutJson{})
}
