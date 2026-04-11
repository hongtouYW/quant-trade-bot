# Define base image
FROM debian:12-slim
MAINTAINER kingsley
LABEL author="kingsley"
LABEL org.opencontainers.image.authors="kingsley"

# Set timezone
ENV TZ=Asia/Singapore
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Create working directory
WORKDIR /home/cut_cluster_status_log
COPY cut_cluster_status_log /home/cut_cluster_status_log

ARG UPDATE_NOTES="None"
LABEL update_notes="$UPDATE_NOTES"

ENTRYPOINT ["/home/cut_cluster_status_log/cut_cluster_status_log"]