# Define base image
FROM alpine:3.21.3
MAINTAINER kingsley
LABEL author="kingsley"
LABEL org.opencontainers.image.authors="kingsley"

# [MUST] Install dependencies
RUN apk add libc6-compat
# Install dependencies
RUN apk add libwebp

# Set timezone
ENV TZ=Asia/Singapore
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Create working directory
WORKDIR /opt/metrics_service
COPY metrics_service /opt/metrics_service

ARG UPDATE_NOTES="None"
LABEL update_notes="$UPDATE_NOTES"

ENTRYPOINT ["/opt/metrics_service/metrics_service"]