package common

import (
	"crypto/md5"
	"encoding/hex"
	"fmt"
	"io"
	"os"
	"path/filepath"
)

func CopyFile(src, dst string) error {
	// 打开源文件
	sourceFile, err := os.Open(src)
	if err != nil {
		return fmt.Errorf("failed to open source file: %w", err)
	}
	defer sourceFile.Close()

	// 获取源文件的文件权限
	fileInfo, err := sourceFile.Stat()
	if err != nil {
		return fmt.Errorf("failed to get source file info: %w", err)
	}
	mode := fileInfo.Mode()

	err = os.MkdirAll(filepath.Dir(dst), 0750)
	if err != nil {
		return err
	}

	// 创建目标文件，应用源文件的权限
	destinationFile, err := os.OpenFile(dst, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, mode)
	if err != nil {
		return fmt.Errorf("failed to create destination file: %w", err)
	}
	defer destinationFile.Close()

	// 复制文件内容
	_, err = io.Copy(destinationFile, sourceFile)
	if err != nil {
		return fmt.Errorf("failed to copy file content: %w", err)
	}

	// 确保内容写入磁盘
	err = destinationFile.Sync()
	if err != nil {
		return fmt.Errorf("failed to sync destination file: %w", err)
	}

	return nil
}

func MoveFile(src, dst string) error {
	err := CopyFile(src, dst)
	if err != nil {
		return err
	}

	// 删除源文件
	err = os.Remove(src)
	if err != nil {
		return fmt.Errorf("failed to remove source file: %w", err)
	}

	return nil
}

func MD5(video string) (string, error) {
	file, err := os.Open(video)
	if err != nil {
		return "", err
	}
	defer file.Close()

	hash := md5.New()
	if _, err = io.Copy(hash, file); err != nil {
		return "", err
	}
	hashSum := hash.Sum(nil)
	return hex.EncodeToString(hashSum), nil
}
