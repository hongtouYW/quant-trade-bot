package main

import (
	"fmt"
	"io"
	"log"
	"net/http"
	"strconv"
	"strings"
	"time"
	"video_cut_go/mingshun"

	"gorm.io/driver/mysql"
	"gorm.io/gorm"
)

type VideoChoose struct {
	ID             int
	VideoID        int
	CutCallbackMsg string
	CutAt          time.Time
}

type Video struct {
	ID         int
	CoverPhoto string
}

var db *gorm.DB

func main() {
	var err error
	if err = InitDatabase(); err != nil {
		panic(err)
	}
	defer Close()

	if err = mingshun.Init(&mingshun.Config{
		URL: "https://api.minggogogo.com",
	}); err != nil {
		panic(err)
	}

	// Step 1: 查询最近30天的video_chooses
	var chooses []VideoChoose
	err = db.Raw(`
		SELECT id, video_id 
		FROM video_chooses 
		WHERE cut_callback_msg LIKE '%image: unknown format%' 
		  AND cut_at > now() - INTERVAL 30 DAY 
		ORDER BY cut_at DESC
	`).Scan(&chooses).Error
	if err != nil {
		panic(fmt.Errorf("query video_chooses error: %w", err))
	}

	log.Printf("Found %d problematic records", len(chooses))

	client := &http.Client{Timeout: 10 * time.Second}

	for _, choose := range chooses {
		// Step 2: 查询 video
		var video Video
		if err = db.Raw(`SELECT id, cover_photo FROM videos WHERE id = ?`, choose.VideoID).Scan(&video).Error; err != nil {
			log.Printf("[ID=%d | VideoID=%d] query video error: %v", choose.ID, choose.VideoID, err)
			continue
		}

		if video.CoverPhoto == "" {
			log.Printf("[ID=%d | VideoID=%d] empty cover_photo", choose.ID, choose.VideoID)
			continue
		}

		url := fmt.Sprintf("https://resources.minggogogo.com/public/%s", video.CoverPhoto)

		// Step 3: 请求封面资源
		resp, err := client.Get(url)
		if err != nil {
			log.Printf("[ID=%d | VideoID=%d] GET %s error: %v", choose.ID, choose.VideoID, url, err)
			continue
		}
		_, _ = io.Copy(io.Discard, resp.Body)
		_ = resp.Body.Close()

		if resp.StatusCode != 200 {
			log.Printf("[ID=%d | VideoID=%d] cover_photo not 200, got %d", choose.ID, choose.VideoID, resp.StatusCode)
			continue
		}

		// Step 4: 解析 X-Upstream header
		xUpstream := resp.Header.Get("X-Upstream")
		if xUpstream == "" {
			log.Printf("[ID=%d | VideoID=%d] no X-Upstream header", choose.ID, choose.VideoID)
			continue
		}

		// 例: X-Upstream: 192.168.100.90:8000 192.168.100.92:8000
		parts := strings.Fields(xUpstream)
		last := parts[len(parts)-1]
		lastIP := strings.Split(last, ":")[0]

		// Step 5: curl 最后一个 upstream
		checkURL := fmt.Sprintf("http://%s:9090/download/%s", lastIP, video.CoverPhoto)
		resp2, err := client.Get(checkURL)
		if err != nil {
			log.Printf("[ID=%d | VideoID=%d] GET %s error: %v", choose.ID, choose.VideoID, checkURL, err)
			continue
		}
		_, _ = io.Copy(io.Discard, resp2.Body)
		_ = resp2.Body.Close()

		if resp2.StatusCode == 200 {
			if err = mingshun.VideoRecut("", strconv.Itoa(choose.ID)); err != nil {
				log.Printf("[ID=%d | VideoID=%d] ⚠️ recut error: %s", choose.ID, choose.VideoID, err)
			} else {
				log.Printf("[ID=%d | VideoID=%d] ✅ recut OK", choose.ID, choose.VideoID)
			}
		} else {
			log.Printf("[ID=%d | VideoID=%d] ❌ %s returned %d", choose.ID, choose.VideoID, checkURL, resp2.StatusCode)
		}
	}
}

func InitDatabase() error {
	var err error
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		"mingshun", "", "127.0.0.1", 3306, "mingshun")
	if db, err = gorm.Open(mysql.Open(dsn), &gorm.Config{}); err != nil {
		return err
	}

	return nil
}

func Close() error {
	DB, err := db.DB()
	if err != nil {
		return err
	}
	return DB.Close()
}
