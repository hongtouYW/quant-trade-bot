#!/bin/bash
read -rp "Enter update notes: " updateNotes

# 更新代码仓库
if ! git pull; then
    echo "Error: Failed to pull from git repository."
    exit 1
fi

# 构建Go项目

cd ../../../cmd/subtitle_service || exit 1
if ! go build -o subtitle_service .; then
    echo "Error: Failed to build Go project."
    exit 1
fi

if ! cp subtitle_service ../../deploy/docker/subtitle_service/; then
    echo "Error: Failed to copy Go exe."
    exit 1
fi
cd ../../deploy/docker/subtitle_service || exit 1

# 构建Docker镜像
if ! docker build --build-arg UPDATE_NOTES="$updateNotes" -t 'subtitle_service:latest' .; then
    echo "Error: Failed to build Docker image."
    exit 1
fi

echo "All commands executed successfully."
