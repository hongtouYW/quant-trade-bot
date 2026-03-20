# Telegram Signal Tracker

Telegram 群信号跟踪 + 模拟交易仪表盘。监听 Telegram 群组信号，自动解析、自动模拟开仓、止盈止损自动平仓，全程 Telegram 群通知。

## 部署信息

| 项目 | 值 |
|------|-----|
| 服务器路径 | `/opt/telegram-signal-tracker/` |
| 端口 | 5112 |
| URL | http://139.162.41.38:5112/ |
| Supervisor | `telegram-signal-dashboard` |
| 日志 | `/opt/telegram-signal-tracker/logs/telegram-signal-dashboard.log` |
| 数据库 | `telegram_signals.db` (SQLite) |
| Telegram Session | `tg_session.session` |

## Telegram 通知

| 项目 | 值 |
|------|-----|
| Bot | @tgsigl01_bot |
| 通知群 | Tg-Signal (chat_id: -5242590434) |
| 监听源 | kokoworld886 群 (Telethon user client) |

## 工作流程

```
kokoworld886 群消息
    ↓
parse_signal_message() 解析
    ↓ (币种/方向/入场价/止损/止盈/杠杆)
自动模拟开仓 (200U/笔)
    ↓
Bot → Tg-Signal 群通知 (开仓信息 + 原始消息)
    ↓
position_monitor 每30s检查
    ↓ (触发止损/止盈)
自动平仓 → Bot 群通知 (盈亏金额/百分比/手续费)
```

## 架构

单文件 Flask 应用 (`telegram_signal_dashboard.py`)

### 核心模块

- **数据库**: SQLite, 4 张表 (signals, trades, account_config, daily_pnl)
- **价格源**: Binance Futures API (`fapi.binance.com`)
- **监听**: Telethon 监听群组 `kokoworld886`，自动解析信号
- **仓位监控**: 后台线程每30s轮询，自动止盈止损平仓
- **通知**: Bot API 发送开仓/平仓通知到 Tg-Signal 群

### 信号解析能力

- `#BTC 69000-70000附近空` → 入场价取范围平均值
- `市價0.31-0.307附近多` → 支持市价+范围格式
- `目標67000-66000` → 止盈取第一个目标
- `止損 65800` / `止盈 72000` → 直接提取
- `（20-50x）` → 杠杆取范围最大值

### API 路由

| 路由 | 方法 | 说明 |
|------|------|------|
| `/` | GET | 主仪表盘页面 |
| `/api/stats` | GET | 统计数据 |
| `/api/signal` | POST | 添加信号 |
| `/api/signals` | GET | 信号列表 |
| `/api/categories` | GET | 信号分类 |
| `/api/signal/<id>/activate` | PUT | 激活信号(开仓) |
| `/api/signal/<id>/close` | PUT | 手动平仓 |
| `/api/signal/<id>/cancel` | PUT | 取消信号 |
| `/api/trades` | GET | 交易列表 |
| `/api/trade/<id>` | GET | 交易详情 |
| `/api/positions` | GET | 当前持仓 |
| `/api/daily-pnl` | GET | 每日盈亏 |
| `/api/config` | GET/PUT | 账户配置 |
| `/api/telegram/status` | GET | Telegram 连接状态 |
| `/api/telegram/auth/send-code` | POST | 发送验证码 |
| `/api/telegram/auth/verify` | POST | 验证登录 |
| `/api/telegram/fetch-history` | POST | 拉取历史消息 |

### 账户配置

- 初始资金: 2000 USDT
- 手续费率: 0.05%
- 资金费率: 0.01%
- 单笔仓位: 10% 可用资金

## 部署命令

```bash
# 查看状态
ssh trading-server "supervisorctl status telegram-signal-dashboard"

# 重启
ssh trading-server "supervisorctl restart telegram-signal-dashboard"

# 查看日志
ssh trading-server "tail -100 /opt/telegram-signal-tracker/logs/telegram-signal-dashboard.log"

# 部署代码更新
scp telegram_signal_dashboard.py trading-server:/opt/telegram-signal-tracker/
ssh trading-server "supervisorctl restart telegram-signal-dashboard"
```

## 变更记录

- **2026-03-18**: 从 /opt/trading-bot/quant-trade-bot/ 搬迁到独立目录
- **2026-03-19**: 接入 Telegram Bot 通知 — 开仓/平仓自动推送到 Tg-Signal 群
- **2026-03-19**: 优化信号解析器 — 支持价格范围、附近、目標等中文格式
