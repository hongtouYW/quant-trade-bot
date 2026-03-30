# yelangawei/backend_mingshun [video_cut_go] 深度审查
**日期:** 2026-03-30 | Go + Redis + ClickHouse + K8s + FFmpeg + rsync

## Bug (7个)
- 🔴 API密钥硬编码
- 🔴 ffmpeg命令注入风险(字符串拼接到/bin/bash -c)
- 🟡 http.NewRequest错误未检查→nil panic
- 🟡 rsync成功后缺break→重复执行11次!
- 🟡 修改全局DefaultTransport竞态
- 🟡 url.Parse错误忽略→nil panic
- 🟢 http.Client无超时→永久阻塞

## 优化: BLPop用Background()非ctx; StrictHostKeyChecking禁用; HttpByCurl→标准库
## 缺陷: rsync成功+回调失败无补偿; 消息无幂等处理
## 评分: 5/10
