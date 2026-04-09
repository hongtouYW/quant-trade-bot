# Flash Quant - 系统架构文档

> **作者**: 🏗️ Winston (System Architect)
> **日期**: 2026-04-08
> **版本**: v0.1
> **依赖**: project-brief.md, market-research.md, prd.md
> **下游**: test-design.md, tech-spec.md

---

## 📖 文档说明

本文档定义 Flash Quant 的**系统架构和技术决策**。
所有代码实现必须遵守本文档定义的模块边界、数据流、技术栈选型。

每条决策都包含 **Why** (理由) 和 **Trade-off** (权衡)。

---

## 🎯 1. 架构原则

### 1.1 设计哲学
1. **Boring Technology First** — 用 Flask/MySQL/Redis 这种久经考验的栈,不用新潮框架
2. **Single Responsibility** — 每个模块只做一件事
3. **Hard-coded Business Rules** — 8 条 BR 写在代码里,不在配置文件 (防止误改)
4. **Fail-Safe Defaults** — 任何异常情况下,默认拒绝交易而不是继续
5. **Observable by Default** — 所有关键操作都有日志和监控点
6. **Stateless Where Possible** — 状态尽量持久化到 DB,内存只做缓存

### 1.2 反模式 (我不会做的)
- ❌ 微服务拆分 (单进程足够,微服务徒增运维负担)
- ❌ 消息队列 (Redis pub/sub 已够用)
- ❌ Kubernetes (Supervisord 足够)
- ❌ 复杂的依赖注入框架 (直接 import)
- ❌ ORM 高级特性 (SQLAlchemy 只用 Core,不用复杂关系)

---

## 🏛 2. 系统总览

### 2.1 高层架构图

```
┌─────────────────────────────────────────────────────────────────┐
│                     Vultr Tokyo Server (新)                     │
│                                                                  │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────────┐    │
│  │   Binance    │   │   Binance    │   │   Web Browser    │    │
│  │  WebSocket   │   │   REST API   │   │   (Hongtou)      │    │
│  └──────┬───────┘   └──────┬───────┘   └────────┬─────────┘    │
│         │                  │                     │              │
│         ↓ kline/aggTrade   ↓ funding/OI          ↓ HTTPS        │
│  ┌──────────────────────────────────────┐  ┌─────────────┐     │
│  │       Data Ingestion Layer           │  │   Nginx     │     │
│  │  - WebSocket Manager                 │  │   :443      │     │
│  │  - REST Poller (30s loop)            │  └──────┬──────┘     │
│  │  - Kline Cache (in-memory deque)     │         │            │
│  │  - CVD Calculator                    │         ↓            │
│  └──────────────┬───────────────────────┘  ┌─────────────┐     │
│                 │                           │  Gunicorn   │     │
│                 ↓                           │  Flask App  │     │
│  ┌──────────────────────────────────────┐  │  :5114      │     │
│  │      Signal Scanner Layer            │  │             │     │
│  │  - Tier 1 Scalper (30s loop)         │  │  Routes:    │     │
│  │  - Tier 2 Trend (60s loop)           │  │  - /        │     │
│  │  - Tier 3 Direction (1H tick)        │  │  - /signals │     │
│  └──────────────┬───────────────────────┘  │  - /trades  │     │
│                 │                           │  - /risk    │     │
│                 ↓                           │  - /config  │     │
│  ┌──────────────────────────────────────┐  │  - /api/*   │     │
│  │       Filter Layer                   │  └──────┬──────┘     │
│  │  - Wick Filter                       │         │            │
│  │  - CVD Filter                        │         │            │
│  │  - Funding Filter                    │         │            │
│  │  - Blacklist Filter (Tier D, 新币)   │         │            │
│  └──────────────┬───────────────────────┘         │            │
│                 │ (signal passes)                  │            │
│                 ↓                                   │            │
│  ┌──────────────────────────────────────┐         │            │
│  │      Risk Manager                    │←────────┤            │
│  │  - 单笔风控 (BR-001, BR-005)         │         │            │
│  │  - 连亏断路器 (FR-031)               │         │            │
│  │  - 时段断路器 (FR-032)               │         │            │
│  │  - 黑天鹅熔断 (FR-033)               │         │            │
│  │  - 同币冷却 (FR-034)                 │         │            │
│  └──────────────┬───────────────────────┘         │            │
│                 │ (approved)                       │            │
│                 ↓                                   │            │
│  ┌──────────────────────────────────────┐         │            │
│  │       Executor Layer                 │         │            │
│  │  ┌─────────────┐  ┌──────────────┐  │         │            │
│  │  │ Paper Exec  │  │ Binance Exec │  │         │            │
│  │  │ (Phase 1)   │  │ (Phase 2+)   │  │         │            │
│  │  └─────────────┘  └──────────────┘  │         │            │
│  └──────────────┬───────────────────────┘         │            │
│                 │                                   │            │
│                 ↓                                   ↓            │
│  ┌──────────────────────────────────────────────────────┐      │
│  │              MySQL: flash_quant_db                    │      │
│  │  - signals / trades / positions / daily_stats         │      │
│  │  - circuit_breaker / audit_log / kline_cache          │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │              Redis (Cache + PubSub)                   │      │
│  │  - K线缓存 / CVD 序列 / 实时价格 / WebSocket 推送    │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  Supervisord:                                                   │
│  - flash-quant-web (gunicorn, 1 worker)                         │
│  - flash-quant-engine (扫描器主循环, 1 process)                 │
│  - flash-quant-ws (WebSocket 数据采集, 1 process)               │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 数据流向

```
1. Binance WebSocket → Kline Cache (in-memory)
2. Binance WebSocket aggTrade → CVD Calculator → Redis
3. Scanner 每 N 秒读 Cache + Redis → 触发候选信号
4. Filter 过滤候选信号 → 写 signals 表 (含过滤结果)
5. Risk Manager 检查 → 通过则交给 Executor
6. Executor 下单 (Paper / Binance) → 写 trades + positions 表
7. Web App 通过 Redis Pub/Sub 推送实时更新到浏览器
```

**关键设计**: 整个流程是**单向的 pipeline**, 不允许反向调用。这样调试和测试都简单。

---

## 🧩 3. 模块架构

### 3.1 模块清单

```
flash_quant/
├── app.py                          # Flask 入口 (Gunicorn 用)
├── engine.py                       # 扫描器引擎入口 (独立进程)
├── ws_collector.py                 # WebSocket 数据采集 (独立进程)
│
├── core/                           # 核心业务模块
│   ├── __init__.py
│   ├── constants.py                # 业务常量 (BR 硬编码在这里)
│   ├── exceptions.py               # 自定义异常
│   └── logger.py                   # 结构化日志
│
├── data/                           # 数据层
│   ├── __init__.py
│   ├── kline_cache.py              # 内存 K 线缓存 (deque)
│   ├── cvd_calculator.py           # CVD 计算
│   ├── market_data.py              # 市场数据 API (funding/OI/symbols)
│   └── redis_client.py             # Redis 封装
│
├── ws/                             # WebSocket 层
│   ├── __init__.py
│   ├── binance_ws.py               # Binance WebSocket 客户端
│   └── stream_handler.py           # 流处理 (kline/aggTrade/ticker)
│
├── scanner/                        # 扫描器层
│   ├── __init__.py
│   ├── base.py                     # ScannerBase 抽象基类
│   ├── tier1_scalper.py            # Tier 1 30s 扫描
│   ├── tier2_trend.py              # Tier 2 60s 扫描
│   └── tier3_direction.py          # Tier 3 1H 扫描
│
├── filters/                        # 过滤器层
│   ├── __init__.py
│   ├── base.py                     # FilterBase 抽象基类
│   ├── wick_filter.py              # 反插针
│   ├── cvd_filter.py               # CVD 过滤
│   ├── funding_filter.py           # 资金费率过滤
│   └── blacklist_filter.py         # Tier D / 新币黑名单
│
├── risk/                           # 风控层
│   ├── __init__.py
│   ├── risk_manager.py             # 主入口
│   ├── position_risk.py            # 单笔风控
│   ├── circuit_breaker.py          # 各种断路器
│   └── black_swan.py               # 黑天鹅熔断
│
├── executor/                       # 执行层
│   ├── __init__.py
│   ├── base.py                     # ExecutorBase 抽象基类
│   ├── paper_executor.py           # 模拟盘
│   └── binance_executor.py         # Binance 实盘
│
├── models/                         # 数据模型 (SQLAlchemy)
│   ├── __init__.py
│   ├── base.py
│   ├── signal.py
│   ├── trade.py
│   ├── position.py
│   ├── daily_stat.py
│   ├── circuit_breaker.py
│   └── audit_log.py
│
├── web/                            # Web Dashboard
│   ├── __init__.py
│   ├── routes/
│   │   ├── __init__.py
│   │   ├── home.py
│   │   ├── signals.py
│   │   ├── trades.py
│   │   ├── risk.py
│   │   ├── config.py
│   │   └── api.py                  # JSON API for AJAX
│   ├── templates/
│   │   ├── base.html
│   │   ├── home.html
│   │   ├── signals.html
│   │   └── ...
│   └── static/
│       ├── css/
│       ├── js/
│       └── img/
│
├── config/                         # 配置
│   ├── __init__.py
│   ├── settings.py                 # 主配置 (从 .env 读取)
│   └── tier_params.py              # Tier 参数 (FR-001~003)
│
├── tests/                          # 测试
│   ├── unit/
│   │   ├── test_wick_filter.py
│   │   ├── test_cvd_filter.py
│   │   ├── test_risk_manager.py
│   │   └── ...
│   └── integration/
│       ├── test_scanner_pipeline.py
│       └── test_executor.py
│
├── scripts/                        # 工具脚本
│   ├── init_db.py
│   ├── seed_blacklist.py
│   └── healthcheck.py
│
├── requirements.txt
├── .env.example
├── supervisord.conf
├── nginx.conf
├── gunicorn.conf.py
└── README.md
```

### 3.2 模块依赖图 (单向)

```
┌──────────┐     ┌──────────┐     ┌──────────┐
│ ws_      │     │ engine   │     │ app.py   │
│collector │     │ (scanner)│     │ (web)    │
└────┬─────┘     └────┬─────┘     └────┬─────┘
     │                │                │
     ↓                ↓                ↓
┌─────────┐    ┌──────────┐     ┌──────────┐
│  data/  │←───│ scanner/ │     │  web/    │
│  ws/    │    │ filters/ │     │ routes/  │
└─────────┘    │  risk/   │     └──────────┘
               │executor/ │           │
               └──────────┘           │
                    │                 │
                    ↓                 ↓
               ┌───────────────────────┐
               │       models/         │
               │   (SQLAlchemy ORM)    │
               └───────────────────────┘
                          │
                          ↓
               ┌───────────────────────┐
               │  MySQL + Redis        │
               └───────────────────────┘
```

**严禁的依赖**:
- ❌ models 不能 import scanner/executor
- ❌ filters 不能 import risk/executor
- ❌ data 不能 import 任何业务模块
- ❌ web 只通过 models 和 redis 读取状态,不直接调用 scanner

---

## 🛠 4. 技术栈选型

### 4.1 核心技术栈

| 类别 | 技术 | 版本 | Why |
|---|---|---|---|
| **语言** | Python | 3.11+ | 你已熟练,生态完善 |
| **Web框架** | Flask | 3.0+ | 轻量,你已用过 (v6/quant_bot) |
| **WSGI** | Gunicorn | 21+ | 标准,稳定 |
| **数据库** | MySQL | 8.0+ | 你已用过,事务支持 |
| **ORM** | SQLAlchemy Core | 2.0+ | 不用 ORM 高级特性 |
| **缓存** | Redis | 7+ | K线/CVD/PubSub |
| **交易所** | ccxt | 4+ | 标准库,支持 Binance |
| **WebSocket** | websockets | 12+ | 异步,稳定 |
| **TA库** | TA-Lib | 0.4+ | C 实现,快 |
| **数值计算** | NumPy + Pandas | latest | 标准 |
| **测试** | pytest + pytest-asyncio | latest | 标准 |
| **进程管理** | Supervisord | 4+ | 你已用过 |
| **反向代理** | Nginx | 1.24+ | 你已用过 |
| **日志** | structlog | 24+ | JSON 结构化日志 |

### 4.2 关键技术决策

#### 决策 1: WebSocket vs REST 轮询
**决策**: 优先 WebSocket, REST 仅作为补充。
**Why**:
- REST 限速 1200/min/IP, 200 币 × 30s 扫描需 400 req/min, 没有余量
- WebSocket 实时,延迟 < 50ms, 不消耗限速
- aggTrade 流是 CVD 计算的唯一可行方式
**Trade-off**:
- WebSocket 断线重连复杂度高
- 需要本地维护状态
**应对**: 用成熟的 `python-binance` 或 `websockets` 库,有指数退避重连

---

#### 决策 2: 三个独立进程 vs 单一进程
**决策**: 三个独立进程 (web / engine / ws_collector),通过 Redis + DB 通信。
**Why**:
- WebSocket 需要 asyncio,Web 用同步 Flask,混在一起复杂
- 进程隔离,一个崩溃不影响其他
- Supervisord 自动重启
**Trade-off**:
- 进程间通信通过 Redis,有 1-50ms 延迟
**应对**: Redis 在本地,延迟 < 1ms,可接受

---

#### 决策 3: 内存 K线缓存 (deque) vs Redis K线缓存
**决策**: K线缓存用**进程内 deque**,CVD 用 Redis。
**Why**:
- K线只在 engine 进程使用,内存 deque 最快
- CVD 需要 ws_collector 和 engine 共享,必须 Redis
**Trade-off**:
- engine 重启会丢失 K线缓存,需要从 REST 重新加载
**应对**: 启动时调用 REST 拉取最近 100 根 K线作为 warm-up

---

#### 决策 4: SQLAlchemy ORM vs Core
**决策**: 只用 SQLAlchemy **Core** (Table + insert/select),不用 ORM 关系映射。
**Why**:
- ORM 隐藏 SQL,bug 难追
- 你的 v6 项目用 ORM 已经踩过坑 (AuditLog json.dumps)
- Core 性能更好
**Trade-off**:
- 代码稍长
**应对**: 写一些 helper 函数,可读性可以接受

---

#### 决策 5: Dashboard 用 Server-Side Rendering vs SPA
**决策**: **SSR (Jinja2 模板) + AJAX 局部更新**,不用 React/Vue。
**Why**:
- 项目个人用,没必要复杂前端
- Jinja2 你已熟练
- AJAX 已足够实现实时更新
**Trade-off**:
- 不如 SPA 流畅
**应对**: 关键页面 (首页) 加 5s 自动刷新,WebSocket 推送关键告警

---

## 💾 5. 数据库设计

### 5.1 Schema: `flash_quant_db`

#### `signals` - 信号记录表
```sql
CREATE TABLE signals (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    timestamp DATETIME NOT NULL,
    tier ENUM('tier1', 'tier2', 'tier3') NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    direction ENUM('long', 'short') NOT NULL,
    score FLOAT,                          -- Tier3 评分
    price DECIMAL(20, 8) NOT NULL,
    volume_ratio FLOAT,                   -- 量比
    price_change_pct FLOAT,               -- 价格变化%
    oi_change_pct FLOAT,                  -- OI 变化%
    taker_ratio FLOAT,                    -- Taker buy/sell
    funding_rate FLOAT,
    body_ratio FLOAT,                     -- Wick filter 结果
    cvd_aligned BOOLEAN,                  -- CVD filter 结果
    funding_passed BOOLEAN,               -- Funding filter 结果
    blacklist_passed BOOLEAN,
    final_decision ENUM('passed', 'filtered', 'blocked', 'executed') NOT NULL,
    filter_reason VARCHAR(255),           -- 被过滤的原因
    raw_data JSON,                        -- 原始数据快照
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_timestamp (timestamp),
    INDEX idx_symbol_tier (symbol, tier),
    INDEX idx_decision (final_decision)
);
```

#### `trades` - 交易记录表
```sql
CREATE TABLE trades (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    signal_id BIGINT,                     -- 关联 signals.id
    mode ENUM('paper', 'live') NOT NULL,
    tier ENUM('tier1', 'tier2', 'tier3') NOT NULL,
    symbol VARCHAR(20) NOT NULL,
    direction ENUM('long', 'short') NOT NULL,
    leverage INT NOT NULL,
    margin DECIMAL(20, 8) NOT NULL,       -- 保证金
    entry_price DECIMAL(20, 8) NOT NULL,
    exit_price DECIMAL(20, 8),
    quantity DECIMAL(20, 8) NOT NULL,
    status ENUM('open', 'closed', 'cancelled') NOT NULL,
    open_time DATETIME NOT NULL,
    close_time DATETIME,
    pnl DECIMAL(20, 8),                   -- 盈亏 (USDT)
    pnl_pct FLOAT,                        -- ROI %
    fee DECIMAL(20, 8),                   -- 手续费
    close_reason VARCHAR(50),             -- stop_loss/take_profit/timeout/manual
    binance_order_id VARCHAR(50),         -- 实盘订单 ID
    stop_loss_order_id VARCHAR(50),       -- 止损单 ID
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_open_time (open_time),
    INDEX idx_symbol (symbol),
    INDEX idx_mode_tier (mode, tier)
);
```

#### `positions` - 当前持仓表
```sql
CREATE TABLE positions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    trade_id BIGINT NOT NULL,             -- 关联 trades.id
    symbol VARCHAR(20) NOT NULL,
    direction ENUM('long', 'short') NOT NULL,
    leverage INT NOT NULL,
    margin DECIMAL(20, 8) NOT NULL,
    entry_price DECIMAL(20, 8) NOT NULL,
    quantity DECIMAL(20, 8) NOT NULL,
    current_price DECIMAL(20, 8),
    unrealized_pnl DECIMAL(20, 8),
    stop_loss_price DECIMAL(20, 8) NOT NULL,
    take_profit_levels JSON,              -- 阶梯止盈
    open_time DATETIME NOT NULL,
    max_hold_until DATETIME NOT NULL,     -- 超时强平时间
    last_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_symbol_active (symbol),
    INDEX idx_open_time (open_time)
);
```

#### `daily_stats` - 日统计表
```sql
CREATE TABLE daily_stats (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL UNIQUE,
    starting_balance DECIMAL(20, 8) NOT NULL,
    ending_balance DECIMAL(20, 8) NOT NULL,
    total_trades INT DEFAULT 0,
    winning_trades INT DEFAULT 0,
    losing_trades INT DEFAULT 0,
    total_pnl DECIMAL(20, 8) DEFAULT 0,
    total_fee DECIMAL(20, 8) DEFAULT 0,
    max_drawdown_pct FLOAT,
    win_rate FLOAT,
    profit_factor FLOAT,
    tier1_trades INT DEFAULT 0,
    tier2_trades INT DEFAULT 0,
    tier3_trades INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

#### `circuit_breaker` - 断路器状态表
```sql
CREATE TABLE circuit_breaker (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    type ENUM('consecutive_loss', 'daily_loss', 'weekly_loss', 'monthly_loss', 'black_swan', 'manual') NOT NULL,
    status ENUM('active', 'expired') NOT NULL,
    triggered_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    reason VARCHAR(255),
    metadata JSON,

    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
);
```

#### `audit_log` - 审计日志表
```sql
CREATE TABLE audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    actor VARCHAR(50) NOT NULL,           -- system / user / agent_name
    action VARCHAR(100) NOT NULL,
    target VARCHAR(100),
    details JSON NOT NULL,                -- 永远 json.dumps()
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',

    INDEX idx_timestamp (timestamp),
    INDEX idx_action (action),
    INDEX idx_severity (severity)
);
```

### 5.2 已知陷阱 (从你之前项目继承的经验)

| 陷阱 | 应对 |
|---|---|
| AuditLog details 必须 json.dumps | 在 model 层强制类型检查 |
| db.session 异常必须 rollback | 用 try/except + context manager |
| ccxt symbol 格式 `XXX/USDT:USDT` | 入库前 split(':')[0] |
| leverage 可能为 None | `int(pos.get('leverage') or 1)` |
| Fees 用实际值不用估算 | 从 Binance order response 取 |
| SSH heredoc + f-string 冲突 | 写文件 + scp 上传 |

---

## 🚀 6. 部署架构

### 6.1 服务器配置

```yaml
provider: Vultr
plan: Tokyo High Frequency (HFC-2c-4gb)
specs:
  cpu: 2 vCPU
  ram: 4 GB
  disk: 80 GB NVMe
  bandwidth: 3 TB/月
location: Tokyo, Japan (NRT)
price: $24/月
```

### 6.2 软件栈

```yaml
os: Ubuntu 22.04 LTS
python: 3.11 (apt + venv)
mysql: 8.0 (apt)
redis: 7 (apt)
nginx: 1.24 (apt)
supervisord: 4 (apt)
ta-lib: 0.4 (源码编译)
```

### 6.3 Supervisord 进程

```ini
[program:flash-quant-web]
command=/opt/flash_quant/venv/bin/gunicorn -c gunicorn.conf.py app:app
directory=/opt/flash_quant
user=root
autostart=true
autorestart=true
stderr_logfile=/var/log/flash-quant/web.err.log
stdout_logfile=/var/log/flash-quant/web.out.log

[program:flash-quant-engine]
command=/opt/flash_quant/venv/bin/python engine.py
directory=/opt/flash_quant
user=root
autostart=true
autorestart=true
stopwaitsecs=30
stderr_logfile=/var/log/flash-quant/engine.err.log
stdout_logfile=/var/log/flash-quant/engine.out.log

[program:flash-quant-ws]
command=/opt/flash_quant/venv/bin/python ws_collector.py
directory=/opt/flash_quant
user=root
autostart=true
autorestart=true
stopwaitsecs=10
stderr_logfile=/var/log/flash-quant/ws.err.log
stdout_logfile=/var/log/flash-quant/ws.out.log
```

### 6.4 Nginx 配置

```nginx
server {
    listen 80;
    server_name _;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name _;

    ssl_certificate /etc/letsencrypt/live/.../fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/.../privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:5114;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /ws {
        proxy_pass http://127.0.0.1:5114;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $websocket;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}
```

### 6.5 部署流程

```bash
# 1. 服务器初始化
apt update && apt install -y python3.11 python3.11-venv mysql-server redis-server nginx supervisor

# 2. 安装 TA-Lib
cd /tmp && wget http://prdownloads.sourceforge.net/ta-lib/ta-lib-0.4.0-src.tar.gz
tar -xzf ta-lib-0.4.0-src.tar.gz && cd ta-lib && ./configure && make && make install

# 3. 项目部署
git clone <repo> /opt/flash_quant
cd /opt/flash_quant
python3.11 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# 4. 数据库初始化
mysql -e "CREATE DATABASE flash_quant_db;"
python scripts/init_db.py

# 5. 配置环境变量
cp .env.example .env
nano .env  # 填入 Binance API Key 等

# 6. 启动服务
systemctl reload supervisor
supervisorctl status
```

---

## 🔐 7. 8 条业务规则的硬编码位置

| BR | 文件 | 函数 |
|---|---|---|
| BR-001 (分级杠杆) | `core/constants.py` | `LEVERAGE_TIERS = {...}` |
| BR-002 (Tier D 黑名单) | `filters/blacklist_filter.py` | `is_tier_d()` |
| BR-003 (新币黑名单) | `filters/blacklist_filter.py` | `is_new_listing()` |
| BR-004 (渐进式资金) | `core/constants.py` | `PHASE_CAPITAL_LIMITS` (枚举) |
| BR-005 (单笔 ≤ 300U) | `core/constants.py` | `MAX_MARGIN_PER_TRADE = 300` |
| BR-006 (服务端止损) | `executor/binance_executor.py` | `place_order_with_stop()` |
| BR-007 (Tier 1 时段) | `scanner/tier1_scalper.py` | `_is_trading_hours()` |
| BR-008 (周末减仓) | `risk/position_risk.py` | `calculate_position_size()` |

**关键**: 这些都是 **Python 常量或硬编码逻辑**, 不在 .env 或 config.yaml 里。
**Why**: 防止改配置就能绕过业务铁律。

---

## 📊 8. 性能预算

| 指标 | 预算 | 实现方式 |
|---|---|---|
| Tier 1 扫描周期 | 30s | asyncio 定时器 + WebSocket 数据 |
| Tier 1 扫描时长 | < 5s | 50 币并行处理 |
| 信号→下单延迟 | < 500ms | 内存计算 + 异步调用 |
| Dashboard 加载 | < 2s | 缓存 + 索引优化 |
| WebSocket 推送 | < 1s | Redis Pub/Sub |
| MySQL 查询 P99 | < 100ms | 索引完备 |

---

## 🛡 9. 容错和恢复

### 9.1 故障场景与应对

| 故障 | 检测 | 恢复 |
|---|---|---|
| WebSocket 断线 | 5s 无消息 | 指数退避重连 (1s, 2s, 4s, 8s, 16s) |
| Binance API 错误 | HTTP 5xx | 重试 3 次,失败后告警 |
| MySQL 连接断 | SQLAlchemy 异常 | Connection pool 自动重连 |
| Redis 断 | 连接异常 | 重试 3 次,失败后降级 (内存缓存) |
| Engine 进程崩溃 | Supervisord | 自动重启,从 DB 恢复持仓状态 |
| 服务器宕机 | 外部监控 | 重启后从 DB 恢复,持仓由服务端止损保护 |

### 9.2 状态恢复策略

**Engine 重启时**:
1. 从 `positions` 表读取所有 `open` 持仓
2. 从 Binance API 验证持仓真实存在
3. 不一致则报警 + 暂停交易
4. 一致则继续监控

**WS Collector 重启时**:
1. 从 REST API 拉取最近 100 根 K 线
2. 写入 Redis 作为 warm-up
3. 重新订阅 WebSocket 流
4. 标记 `data_ready=true`

**Engine 启动前必须等待**: `data_ready==true && positions_recovered==true`

---

## ⚠️ 10. 已知架构限制

| 限制 | 影响 | 缓解 |
|---|---|---|
| 单服务器单点故障 | 服务器宕机即停止交易 | 服务端止损 + Vultr SLA 99.9% |
| 单一交易所 | Binance 故障即停止 | Phase 4 可加 Bitget 备份 |
| 内存 K线缓存 | 重启需 warm-up | 启动 1-2 分钟内不交易 |
| 单进程扫描 | CPU 瓶颈 | 50 币种规模够用,扩展时再多进程 |
| Redis 单实例 | 缓存丢失 | Redis 仅缓存,丢失可重建 |

---

## 🎤 Winston 签字

> "🏗️ **架构完成。**
>
> 这套架构遵循我的核心原则: **用最 boring 的技术,把 8 条 BR 钉死在代码里**。
>
> 几个关键点:
> 1. **单服务器三进程** — 简单到能让你 2 年后还看得懂
> 2. **WebSocket + Redis + MySQL** — 你已经在用的栈,零学习成本
> 3. **8 条 BR 全部硬编码** — 改 .env 也绕不过去
> 4. **状态全部持久化** — 重启不丢失,服务端止损是最后防线
> 5. **数据流单向 pipeline** — 调试和测试都简单
>
> 我的担忧:
> - 单服务器单点故障 (Vultr 99.9% SLA, 接受)
> - WebSocket 断线重连复杂度 (库已成熟, 接受)
>
> 现在交棒给 **Murat** 设计测试! 🧪"

---

**文档版本**: v0.1
**状态**: ✅ 已完成
**下一步**: 🧪 Murat 撰写 Test Design (`test-design.md`)
