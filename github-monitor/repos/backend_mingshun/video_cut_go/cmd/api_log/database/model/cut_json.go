package model

import (
	"gorm.io/gorm"
)

type CutJson struct {
	gorm.Model
	Identifier string `json:"identifier"`
	JSON       string `gorm:"json" json:"json"`
	Status     int    `json:"status"`
	Size       int64  `json:"size"`
}

func (*CutJson) TableName() string {
	return "cut_json"
}

func (*CutJson) Default() error {
	return nil
}

func (m *CutJson) Create() (err error) {
	return db.Create(m).Error
}

func (m *CutJson) Update() (err error) {
	return db.Model(&m).Where("identifier = ?", m.Identifier).Updates(m).Error
}
