# Define base image
FROM alpine:3.21.3
MAINTAINER kingsley
LABEL author="kingsley"
LABEL org.opencontainers.image.authors="kingsley"

# [MUST] Install dependencies
RUN apk add libc6-compat tzdata
# Install dependencies
RUN apk add rsync openssh-client

# Set timezone
ENV TZ=Asia/Singapore
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Create working directory
WORKDIR /opt/subtitle_service
COPY subtitle_service /opt/subtitle_service

ARG UPDATE_NOTES="None"
LABEL update_notes="$UPDATE_NOTES"

ENTRYPOINT ["/opt/subtitle_service/subtitle_service"]