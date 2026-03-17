# Trading SaaS 系统完整文档

> 最后更新：2026-03-17
> 服务器：139.162.41.38 | SaaS 端口 80 (nginx) / 5200 (gunicorn)

---

## 一、系统架构

```
用户浏览器
    │
    ├── nginx (port 80) ── 静态文件 + 反代
    │                          │
    │                    gunicorn (port 5200)
    │                          │
    │                    Flask App
    │                     ├── API Routes (JWT auth)
    │                     ├── WebSocket (Socket.IO)
    │                     ├── Trading Engine (per-agent threads)
    │                     └── MySQL (trading_saas DB)
    │
    └── Telegram Bot API ── 通知推送
```

**技术栈**：Flask + SQLAlchemy + Gunicorn + MySQL + ccxt + Socket.IO
**前端**：React (JSX) + Vite 构建
**交易所**：Binance Futures / Bitget Swap

---

## 二、角色与权限

| 功能 | Admin | Agent | 公开 |
|------|-------|-------|------|
| 登录 | ✓ | ✓ | ✓ |
| 注册（需 Admin 审批） | - | - | ✓ |
| 创建/编辑/停用 Agent | ✓ | - | - |
| 开关 Agent 交易权限 | ✓ | - | - |
| 重置 Agent 密码 | ✓ | - | - |
| 查看所有 Agent 交易/余额 | ✓ | - | - |
| 启停任意 Agent Bot | ✓ | - | - |
| 排行榜 | ✓ | - | - |
| 审计日志 | ✓ | - | - |
| 账单：关闭/审批/标记已付 | ✓ | - | - |
| 收入总览 | ✓ | - | - |
| 个人 Dashboard | - | ✓ | - |
| 设置 API Key / Telegram | - | ✓ | - |
| 策略切换 / 参数调整 | - | ✓ | - |
| 启停自己的 Bot | - | ✓ | - |
| 查看交易历史 / 导出 CSV | - | ✓ | - |
| 账单查看 | - | ✓ | - |
| 通知中心 | - | ✓ | - |

---

## 三、API 路由总览

### 3.1 认证 (`/api/auth`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/admin/login` | POST | Admin 登录，返回 JWT |
| `/agent/login` | POST | Agent 登录，返回 JWT |
| `/agent/register` | POST | Agent 自注册（默认不启用交易） |
| `/refresh` | POST | 刷新 JWT |
| `/change-password` | POST | 修改密码 |

### 3.2 Admin 端 (`/api/admin`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/dashboard` | GET | 总览：Agent 数、活跃 Bot、总 PnL |
| `/agents` | GET/POST | 列表 / 创建 Agent |
| `/agents/<id>` | GET/PUT/DELETE | 详情 / 编辑 / 停用 |
| `/agents/<id>/toggle-trading` | POST | 切换交易权限 |
| `/agents/<id>/reset-password` | POST | 重置密码 |
| `/agents/<id>/trades` | GET | 查看 Agent 交易记录 |
| `/audit-log` | GET | 审计日志（分页、过滤） |
| `/leaderboard` | GET | 排行榜 |

### 3.3 Admin Bot 管理 (`/api/admin/bots`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/` | GET | 所有 Bot 状态 |
| `/<id>/start` | POST | 启动 |
| `/<id>/stop` | POST | 停止 |
| `/<id>/restart` | POST | 重启 |

### 3.4 Admin 账单 (`/api/admin/billing`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/revenue` | GET | 收入总览 |
| `/periods` | GET | 所有账单周期 |
| `/periods/<agent_id>/close` | POST | 关闭当月账单 |
| `/periods/<id>/approve` | POST | 审批 |
| `/periods/<id>/paid` | POST | 标记已付 |

### 3.5 Agent 端 (`/api/agent`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/dashboard` | GET | 个人总览 |
| `/profile` | GET/PUT | 个人资料 |
| `/api-keys` | PUT/DELETE | 设置/删除 API Key |
| `/api-keys/status` | GET | API Key 状态 |
| `/api-keys/verify` | POST | 验证 API Key 连通性 |
| `/balance` | GET | 实时 USDT 余额 |
| `/telegram` | GET/PUT | Telegram 配置 |
| `/telegram/test` | POST | 发送测试消息 |
| `/trading/config` | GET/PUT | 交易配置 |
| `/trading/strategies` | GET | 策略预设列表 |
| `/trading/strategy/<ver>` | POST | 切换策略 |

### 3.6 Agent Bot 控制 (`/api/agent/bot`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/status` | GET | Bot 状态 + 持仓数 |
| `/logs` | GET | 最近 100 条日志 |
| `/signals` | GET | 最近一次扫描结果 |
| `/start` | POST | 启动 |
| `/stop` | POST | 停止 |
| `/pause` | POST | 暂停（仍监控止损） |
| `/resume` | POST | 恢复 |

### 3.7 交易数据 (`/api/agent/trades`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/positions` | GET | 持仓列表（含实时 PnL） |
| `/history` | GET | 历史交易（分页、过滤） |
| `/<id>` | GET | 单笔交易详情 |
| `/stats` | GET | 统计：胜率、盈利因子、最大回撤 |
| `/daily` | GET | 每日 PnL 汇总 |
| `/symbols` | GET | 已交易币种列表 |
| `/export/csv` | GET | 导出 CSV |

### 3.8 市场数据 (`/api/market`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/price/<symbol>` | GET | 当前价格 |
| `/kline/<symbol>` | GET | K 线数据 |

### 3.9 通知 (`/api/agent/notifications`)

| 路由 | 方法 | 说明 |
|------|------|------|
| `/` | GET | 通知列表 |
| `/unread-count` | GET | 未读数 |
| `/<id>/read` | POST | 标记已读 |
| `/read-all` | POST | 全部标记已读 |
| `/<id>` | DELETE | 删除 |

---

## 四、数据库模型

### 4.1 核心用户

| 表 | 关键字段 | 说明 |
|---|---|---|
| `admins` | username, email, password_hash, is_active | 管理员 |
| `agents` | admin_id, username, is_active, is_trading_enabled, profit_share_pct | 交易员 |

### 4.2 配置

| 表 | 关键字段 | 说明 |
|---|---|---|
| `agent_api_keys` | agent_id, exchange, api_key_enc, api_secret_enc, encryption_iv, permissions_verified | API 密钥（AES-256 加密） |
| `agent_telegram_config` | agent_id, bot_token_enc, chat_id, is_enabled | Telegram 通知配置 |
| `agent_trading_config` | agent_id, strategy_version, max_leverage, max_positions, min_score, roi_stop_loss, roi_trailing_start, roi_trailing_distance, tp1_roi, tp1_close_ratio, use_atr_stop, short_bias, ... | 交易策略参数 |
| `strategy_presets` | version, label, config(JSON), is_active | 预设策略模板 |

### 4.3 交易

| 表 | 关键字段 | 说明 |
|---|---|---|
| `trades` | agent_id, symbol, direction, entry_price, exit_price, amount, leverage, pnl, roi, fee, funding_fee, score, close_reason, peak_roi, tp1_hit, strategy_version | 交易记录 |
| `daily_stats` | agent_id, date, trades_closed, win_trades, total_pnl, total_fees | 每日统计 |

### 4.4 系统

| 表 | 关键字段 | 说明 |
|---|---|---|
| `bot_state` | agent_id, status, last_scan_at, risk_score, scan_count, pid | Bot 运行状态 |
| `billing_periods` | agent_id, period_start, period_end, gross_pnl, high_water_mark, commission_amount, status | 账单周期 |
| `audit_log` | user_type, user_id, action, details(JSON), ip_address | 审计日志 |
| `notifications` | agent_id, type, title, message, is_read | 通知 |

---

## 五、交易引擎

### 5.1 组件架构

```
BotManager (单例)
    │
    ├── AgentBot-1 (线程)
    │     ├── SignalAnalyzer  ── 信号评分
    │     ├── OrderExecutor   ── 交易所下单 (ccxt)
    │     └── RiskManager     ── 风控状态机
    │
    ├── AgentBot-2 (线程)
    │     └── ...
    │
    └── Watchdog (守护线程) ── 自动重启崩溃 Bot
```

### 5.2 BotManager

- **start_bot / stop_bot / restart_bot**：管理每个 Agent 的交易线程
- **Watchdog**：每 60s 检查 DB 中 status=running 但线程已死的 Bot，自动重启（最多 3 次/5min）

### 5.3 AgentBot 主循环

```
每 60 秒一轮扫描:
│
├─ 热更新配置（每 5 轮从 DB reload）
│
├─ 检查已有持仓
│   ├─ 更新 peak_roi
│   ├─ V5: TP1 分批止盈检查
│   ├─ 止损检查（ROI <= -10%）
│   ├─ Trailing Stop 检查（峰值回撤 >= 3%）
│   ├─ 最大持仓时间检查（48h 强平）
│   └─ 固定价格 SL/TP 检查
│
├─ 风控检查（RiskManager）
│
└─ 扫描新信号
    ├─ max_positions 限制
    ├─ 冷却期检查（v6 全局 / 其他单币种）
    ├─ 信号评分（v4/v5/v6 路由）
    ├─ min_score / long_min_score 过滤
    ├─ MA20 slope 趋势过滤
    ├─ v4 特殊过滤（85+LONG 跳过）
    ├─ 最小资金 100U 检查
    └─ 开仓（评分制仓位计算 + 交易所下单）
```

### 5.4 策略版本对比

| 特性 | v4.2 | v5.0 | v6.0 |
|------|------|------|------|
| 信号评分 | RSI+MA+Vol+PricePos (100分) | MACD+BB+RSI+ADX 三重确认 | v4.2 基础 + MACD/ADX/BB 加分 (+25) |
| 最高分 | 100 | ~100 | ~131 |
| 杠杆 | 3x | 10x | 3x |
| 最大持仓 | 15 | 5 | 15 |
| SHORT 门槛 | 60 | 75 | 70 |
| LONG 门槛 | 70 | 75 | 85 |
| 仓位计算 | 评分制 | ATR-based risk sizing | 评分制 |
| 止损 | 固定 ROI -10% | ATR 动态止损 | 固定 ROI -10% |
| 止盈 | Trailing 6%/3% | TP1 +10%(平50%) + TP2 +20% trailing | Trailing 6%/3% |
| 冷却 | 单币种 30min | 单币种 | 全局 cooldown_minutes |
| SHORT 偏好 | 1.05x + 85+LONG跳过 | 无 | 1.05x |
| 趋势过滤 | MA20/MA50 slope | ADX gate | MA20 slope |
| BTC 过滤 | 有 | 无 | 无 |

### 5.5 SignalAnalyzer

**监控币种**：67 个（T1/T2/T3 分级），排除 SKIP_COINS

**指标计算**：
- RSI(14)
- MACD(12,26,9) — 含 crossover 检测
- Bollinger Bands(20, 2σ) — %B 百分比
- ADX(14) — 趋势强度
- ATR(14) — 波动率
- MA(7, 20, 50) — 移动平均

**评分函数**：
- `analyze_signal()` — v4.2
- `analyze_signal_v5()` — v5.0
- `analyze_signal_v6()` — v6.0

### 5.6 OrderExecutor (ccxt)

- **支持交易所**：Binance Futures / Bitget Swap
- **功能**：set_leverage, open_position, close_position, reduce_position, get_price, get_balance, get_open_positions
- **Binance 特殊处理**：`DOT/USDT:USDT` → `DOT/USDT` symbol 映射

### 5.7 RiskManager

**风险评分 (0-10)**：
- 连续亏损次数
- 当前回撤百分比
- 当日 PnL 限制
- 最大回撤限制
- 多空比例失衡

**风控动作**：
| 等级 | 行为 |
|------|------|
| LOW (0-3) | 正常交易 |
| MEDIUM (4-5) | max_positions 缩减 30% |
| HIGH (6-7) | max_positions 缩减 50% |
| CRITICAL (8-10) | 停止开新仓，仅监控 SL/TP |

---

## 六、费用与账单

### 6.1 交易费用

| 费用类型 | 来源 | 计算 |
|----------|------|------|
| 开仓手续费 | 交易所订单回报 | 实际值 |
| 平仓手续费 | 交易所订单回报 | 实际值 |
| 分批平仓费 | v5 TP1 部分平仓 | 实际值 |
| 资金费率 | Binance Income API | 实际值（每 8h） |

### 6.2 账单周期（月结）

```
月末 → 关闭当月周期
→ 计算 gross_pnl（期间已平仓交易总和）
→ HWM 逻辑：ending_capital > high_water_mark 时才收佣金
→ commission = (ending_capital - HWM) × profit_share_pct
→ Admin 审批 → 标记已付
```

---

## 七、安全机制

| 数据 | 加密方式 | 存储位置 |
|------|----------|----------|
| API Key + Secret | AES-256 + IV | agent_api_keys |
| Telegram Bot Token | AES-256 + IV | agent_telegram_config |
| 密码 | bcrypt | admins / agents |
| JWT | HS256 | 客户端 localStorage |
| 通信 | HTTPS (nginx) | 传输层 |

**审计日志追踪**：登录、创建Agent、修改配置、开平仓、账单操作 — 全部记录 IP + 时间 + 详情

---

## 八、实时功能

### WebSocket (Socket.IO)

| 事件 | 数据 | 触发时机 |
|------|------|----------|
| `connected` | {agent_id} | 连接成功 |
| `bot_status` | 状态数据 | 每次扫描 |
| `trade_event` | 交易详情 | 开仓/平仓 |
| `notification` | 通知内容 | 新通知 |
| `signal_update` | 扫描结果 | 每次扫描 |

---

## 九、前端页面

### Admin 端

| 页面 | 路径 | 功能 |
|------|------|------|
| Dashboard | `/admin` | KPI 总览 |
| Agents | `/admin/agents` | Agent 管理（创建/编辑/停用/开关交易） |
| Bots | `/admin/bots` | Bot 状态监控 + 启停控制 |
| Audit Log | `/admin/audit` | 操作审计 |
| Leaderboard | `/admin/leaderboard` | 排行榜 |
| Revenue | `/admin/revenue` | 收入/账单管理 |

### Agent 端

| 页面 | 路径 | 功能 |
|------|------|------|
| Dashboard | `/agent` | 个人总览（资金、PnL、胜率） |
| Positions | `/agent/positions` | 实时持仓 + 未实现 PnL |
| History | `/agent/history` | 交易历史 + 过滤 + CSV 导出 |
| Stats | `/agent/stats` | 详细统计 |
| Bot Control | `/agent/bot` | 启停 Bot + 日志 + 信号面板 |
| Settings | `/agent/settings` | API Key / Telegram / 策略配置 |
| Billing | `/agent/billing` | 账单查看 |
| FAQ | `/agent/faq` | 帮助 |

---

## 十、文件结构

```
/opt/trading-saas/
├── app/
│   ├── api/                     # API 路由
│   │   ├── auth.py             # 登录/注册/刷新
│   │   ├── admin.py            # Admin CRUD
│   │   ├── agent.py            # Agent 配置/余额
│   │   ├── trading.py          # 交易数据/统计
│   │   ├── bot_control.py      # Bot 管理
│   │   ├── billing.py          # 账单
│   │   ├── market.py           # 市场数据
│   │   ├── notifications.py    # 通知
│   │   └── ws_events.py        # WebSocket
│   ├── engine/                  # 交易引擎
│   │   ├── bot_manager.py      # Bot 管理器（单例）
│   │   ├── agent_bot.py        # 单 Agent 交易线程
│   │   ├── signal_analyzer.py  # 信号分析 + 仓位计算
│   │   ├── order_executor.py   # ccxt 交易所下单
│   │   └── risk_manager.py     # 风控
│   ├── models/                  # 数据模型
│   │   ├── admin.py / agent.py
│   │   ├── agent_config.py     # ApiKey, Telegram, TradingConfig
│   │   ├── trade.py            # Trade, DailyStat
│   │   ├── bot_state.py
│   │   ├── audit.py / notification.py
│   │   ├── billing.py
│   │   └── strategy_preset.py
│   ├── services/                # 业务逻辑
│   │   ├── auth_service.py
│   │   ├── encryption_service.py
│   │   ├── billing_service.py
│   │   ├── notification_service.py
│   │   └── audit_service.py
│   ├── middleware/               # 中间件
│   │   ├── auth_middleware.py   # JWT 装饰器
│   │   ├── rate_limiter.py
│   │   └── error_handler.py
│   ├── __init__.py              # App 工厂
│   ├── config.py
│   └── extensions.py            # db, jwt, cors, socketio
├── frontend/src/                # React 前端
│   ├── pages/admin/            # Admin 页面
│   ├── pages/agent/            # Agent 页面
│   ├── pages/auth/             # 登录/注册
│   ├── components/             # 通用组件
│   ├── contexts/               # AuthContext, LanguageContext
│   └── hooks/                  # useApi, useSocket
├── deploy/
│   └── gunicorn.conf.py
├── docs/
│   ├── system_overview.md      # 本文档
│   └── strategy_v6_report.md   # v6 策略详细文档
├── CHANGELOG.md
└── wsgi.py
```

---

## 十一、部署信息

| 项目 | 路径 | 端口 | 管理方式 |
|------|------|------|----------|
| SaaS 主站 | /opt/trading-saas/ | 80 (nginx) / 5200 (gunicorn) | kill -HUP + API restart |
| Paper Trader | /opt/trading-bot/ | 5111 | supervisord (auto-trader-v6) |
| BTC Strategy | /opt/btc-strategy/ | - | supervisord |

**Gunicorn**：`preload_app=False`，HUP 信号 reload worker
**Bot 重启**：`POST /api/admin/bots/<id>/restart`（需 Admin JWT）

---

## 十二、关键配置参考

### Agent 2 当前配置 (v6.0)

```
strategy_version: v6.0
initial_capital: 1000
max_positions: 15
max_leverage: 3
min_score: 70
long_min_score: 85
roi_stop_loss: -10
roi_trailing_start: 6
roi_trailing_distance: 3
short_bias: 1.05
cooldown_minutes: 30
```

### 仓位计算（v6 评分制）

| 评分 | 仓位 | 杠杆 |
|------|------|------|
| >= 85 | min(150, 可用×8%) | 3x |
| >= 80 | min(250, 可用×15%) | 3x |
| >= 75 | min(350, 可用×22%) | 3x |
| >= 70 | min(250, 可用×15%) | 3x |
| >= 60 | min(150, 可用×10%) | 3x |

最小开仓：50U | 最小可用资金：100U

### 平仓触发条件

| 条件 | 触发 |
|------|------|
| ROI <= -10% | 止损（3h 内保护除外，除非 ROI < -15%） |
| ROI >= +6% 且峰值回撤 >= 3% | Trailing Stop |
| 价格触及 stop_loss 价格 | 固定价格止损 |
| 价格触及 take_profit 价格 | 固定价格止盈 |
| 持仓 > 48h | 强制平仓 |
| 风控 CRITICAL | 停止开仓（现有仓位继续监控） |
