#!/bin/bash
read -rp "Enter update notes: " updateNotes

# 更新代码仓库
if ! git pull; then
    echo "Error: Failed to pull from git repository."
    exit 1
fi

# 构建Go项目

if ! go build -o resource/video_cut_go ../../../; then
    echo "Error: Failed to build Go project."
    exit 1
fi

# 构建Docker镜像
if ! docker build --build-arg UPDATE_NOTES="$updateNotes" -t 'video_cut_go:latest' .; then
    echo "Error: Failed to build Docker image."
    exit 1
fi

echo "All commands executed successfully."
