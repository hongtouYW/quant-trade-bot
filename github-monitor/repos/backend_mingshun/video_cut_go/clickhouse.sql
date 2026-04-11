-- 1. 创建数据库
CREATE DATABASE IF NOT EXISTS video_cut;

-- 2. 创建表 logs
-- 重建建议（仅当你可以重建表数据时使用）
CREATE TABLE video_cut.logs_v2
(
    `timestamp` DateTime64(3, 'Asia/Shanghai') DEFAULT now(),
    `level` String,
    `service` String,
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

-- 3. 创建用户 video_cut_go（使用明文密码）
CREATE USER IF NOT EXISTS video_cut_go
IDENTIFIED WITH plaintext_password BY 'your_secure_password';

-- 4. 授权用户访问 video_cut 数据库（只允许 SELECT 和 INSERT）
GRANT SELECT, INSERT ON video_cut.* TO video_cut_go;
