package main

import (
	"time"
)

type CutRealServerStatusLog struct {
	ID        uint
	Namespace string
	Server    string
	Status    int
	Hours     int
	CreatedAt time.Time
	UpdatedAt time.Time
}

func getCurrentHourLog() ([]CutRealServerStatusLog, error) {
	now := time.Now()
	var cutRealServerStatusLogs []CutRealServerStatusLog
	return cutRealServerStatusLogs, db.
		Model(&CutRealServerStatusLog{}).
		Where("hours = ? AND DATE(created_at) = ?", now.Hour(), now.Format("2006-01-02")).
		Find(&cutRealServerStatusLogs).
		Error
}

func createLogs(data []CutRealServerStatusLog) error {
	now := time.Now()
	for i := range data {
		data[i].CreatedAt = now
		data[i].UpdatedAt = now
		data[i].Hours = now.Hour()
	}

	return db.Create(&data).Error
}

func updateLogsStatus(ids []uint) error {
	now := time.Now()
	return db.Model(&CutRealServerStatusLog{}).Where("id IN ?", ids).
		Updates(map[string]interface{}{
			"status":     1,
			"updated_at": now,
		}).Error
}
