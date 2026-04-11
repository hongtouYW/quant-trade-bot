package main

import (
	"log"
	"os"
	"path/filepath"
	"time"
)

const maxAge = 7 * 24 * time.Hour // 7 天

func cleanExpFile() {
	now := time.Now()

	err := filepath.Walk(backupPath, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if info.IsDir() {
			return nil
		}
		if now.Sub(info.ModTime()) > maxAge {
			log.Printf("delete expired file: %s", path)
			return os.Remove(path)
		}
		return nil
	})

	if err != nil {
		log.Printf("scan backup dir error: %v", err)
	}

	deleteEmptyDirs(backupPath)
}

func deleteEmptyDirs(dir string) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		log.Println(err)
		return
	}

	for _, entry := range entries {
		if entry.IsDir() {
			fullPath := filepath.Join(dir, entry.Name())
			deleteEmptyDirs(fullPath)
		}
	}

	if len(entries) == 0 && dir != backupPath {
		log.Printf("delete empty dir: %s", dir)
		_ = os.Remove(dir)
	}
}
