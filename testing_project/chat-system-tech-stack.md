# 聊天系统 — 技术栈总结

## 前端

| 技术 | 用途 |
|---|---|
| React / Vue / Next.js | Web 端界面 |
| React Native / Flutter | 移动端（可选） |
| Socket.IO Client | WebSocket 实时通信 |

## 后端

| 技术 | 用途 |
|---|---|
| Node.js + Socket.IO 或 Go + Gorilla WebSocket | 实时消息服务 |
| Redis | 消息队列、在线状态缓存、Pub/Sub |
| PostgreSQL / MySQL | 用户数据、聊天记录持久化 |
| Nginx | 反向代理 + WebSocket 升级 |

## 基础设施（可选，按规模扩展）

| 技术 | 用途 |
|---|---|
| Docker | 容器化部署 |
| RabbitMQ / Kafka | 大规模消息队列 |

## 核心三要素

1. **WebSocket 实时通信** — 双向即时消息推送
2. **消息持久化** — 聊天记录存储与查询
3. **在线状态管理** — 用户上下线、已读未读

## 最小可用版本（MVP）

- 1 个 WebSocket 服务（Node.js / Go）
- 1 个数据库（PostgreSQL）
- 1 个前端页面（React）

> 先跑通核心流程，再按需扩展功能。

## 待确认事项

- [ ] 聊天类型：一对一 / 群聊 / 客服 / 社区？
- [ ] 目标用户与规模
- [ ] 核心功能：文字 / 图片 / 语音 / 视频？
- [ ] 商业模式与使用场景
