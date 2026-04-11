#!/bin/bash
read -rp "Enter version: " version
if [ -z "$version" ]; then
    echo "Error: version is empty"
    exit 1
fi
docker login https://hub.minggogogo.com:8888
docker tag metrics_service:latest hub.minggogogo.com:8888/library/metrics_service:"v$version"
docker push hub.minggogogo.com:8888/library/metrics_service:"v$version"
docker tag hub.minggogogo.com:8888/library/metrics_service:"v$version" hub.minggogogo.com:8888/library/metrics_service:latest
docker push hub.minggogogo.com:8888/library/metrics_service:latest