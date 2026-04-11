#!/bin/bash
version=$1
if [ -z "$version" ]; then
    echo "Error: version is empty"
    exit 1
fi
docker login https://hub.minggogogo.com:8888
docker tag cut_cluster_status_log:latest hub.minggogogo.com:8888/library/cut_cluster_status_log:"v$version"
docker push hub.minggogogo.com:8888/library/cut_cluster_status_log:"v$version"
docker tag hub.minggogogo.com:8888/library/cut_cluster_status_log:"v$version" hub.minggogogo.com:8888/library/cut_cluster_status_log:latest
docker push hub.minggogogo.com:8888/library/cut_cluster_status_log:latest