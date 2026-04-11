package model

import (
	"errors"
)

type BillRequest struct {
	ManagerID      uint  `json:"managerID"`
	StartTimestamp int64 `json:"startTimestamp"`
	EndTimestamp   int64 `json:"endTimestamp"`
}

type BillData struct {
	ProjectID uint
	Size      float64
}

func (b *BillRequest) validate() error {
	if b.StartTimestamp == 0 {
		return errors.New("startTimestamp not set")
	}

	if b.EndTimestamp == 0 {
		return errors.New("endTimestamp not set")
	}

	if b.StartTimestamp > b.EndTimestamp {
		return errors.New("startTimestamp cannot largest then endTimestamp")
	}

	return nil
}

func (b *BillRequest) GetAllBillData() ([]BillData, error) {
	if err := b.validate(); err != nil {
		return nil, err
	}

	sql := `SELECT size FROM cut_json WHERE	STATUS = 1 AND updated_at BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)`
	rows, err := db.Raw(sql, b.StartTimestamp, b.EndTimestamp).Rows()
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	result := make([]BillData, 0)
	for rows.Next() {
		var data BillData
		err = rows.Scan(&data.Size)
		if err != nil {
			return nil, err
		}

		result = append(result, data)
	}

	return result, nil
}
