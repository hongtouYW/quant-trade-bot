CREATE DATABASE IF NOT EXISTS video_cut;

CREATE TABLE video_cut.subtitle_service_logs_v2
(
    `timestamp` DateTime64(3, 'Asia/Shanghai') DEFAULT now(),
    `level` String,
    `id` String,
    `message` String,
    `pod_name` String,
    `namespace` String,
    `node_name` String
)
    ENGINE = MergeTree
PARTITION BY toYYYYMMDD(timestamp)
ORDER BY timestamp
TTL timestamp + INTERVAL 31 DAY
SETTINGS index_granularity = 8192, ttl_only_drop_parts = 1;

CREATE USER IF NOT EXISTS subtitle_service
IDENTIFIED WITH plaintext_password BY 'your_secure_password';

GRANT SELECT, INSERT ON subtitle_service.* TO subtitle_service;
