#!/bin/bash
version=$1
if [ -z "$version" ]; then
    echo "Error: version is empty"
    exit 1
fi
docker login https://hub.minggogogo.com:8888
docker tag video_cut_go:latest hub.minggogogo.com:8888/library/video_cut_go:"v$version"
docker push hub.minggogogo.com:8888/library/video_cut_go:"v$version"
docker tag hub.minggogogo.com:8888/library/video_cut_go:"v$version" hub.minggogogo.com:8888/library/video_cut_go:latest
docker push hub.minggogogo.com:8888/library/video_cut_go:latest