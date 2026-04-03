# SignalHive — 技术提案

> 技术架构与实施规格 | 2026年4月

---

## 1. 系统概述

SignalHive 是社交媒体交易信号聚合平台。本文档为开发团队提供完整技术设计。

**核心流水线：**
```
采集器 → Redis Stream → 提取引擎(关键词+LLM) → 评估器(评分) → 匹配器(策略) → 执行器(模拟/实盘)
```

---

## 2. 架构

### 2.1 组件图

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SignalHive 服务器                              │
│                                                                     │
│  ┌──────────────┐    ┌─────────────┐    ┌────────────────────────┐  │
│  │   采集器       │    │ Redis Stream │    │       核心引擎          │  │
│  │              │    │             │    │                        │  │
│  │  telegram ───┼──→│  raw_msgs   │──→│ 预筛选 → LLM提取       │  │
│  │  twitter  ───┼──→│             │    │ → 评估器 → 匹配器      │  │
│  │  weibo    ───┼──→│             │    │ → 执行器               │  │
│  └──────────────┘    └─────────────┘    └────────────────────────┘  │
│                                                │                    │
│                                                ▼                    │
│  ┌──────────────┐    ┌─────────────┐    ┌──────────────┐           │
│  │   Flask API   │    │    MySQL     │    │  ccxt/交易所   │           │
│  │  (REST + UI)  │    │  (全部数据)   │    │  (交易执行)    │           │
│  └──────────────┘    └─────────────┘    └──────────────┘           │
│         │                                                           │
│         ▼                                                           │
│  ┌──────────────┐                                                   │
│  │   Nginx       │ ← 端口 80/443                                    │
│  └──────────────┘                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 进程模型

| 进程 | 职责 | 管理方式 |
|------|------|---------|
| `signalhive-web` | Flask API + Dashboard | Supervisord |
| `signalhive-collector-tg` | Telegram 监听 | Supervisord |
| `signalhive-collector-twitter` | Twitter 监听 | Supervisord (P3) |
| `signalhive-engine` | 提取+评估+匹配+执行循环 | Supervisord |

每个采集器独立进程运行。引擎从 Redis Stream 消费。

---

## 3. 数据采集层

### 3.1 采集器接口

```python
from abc import ABC, abstractmethod
from typing import AsyncIterator
from dataclasses import dataclass
from datetime import datetime
from enum import Enum

class HealthStatus(Enum):
    HEALTHY = "healthy"
    DEGRADED = "degraded"
    DOWN = "down"

@dataclass
class RawMessage:
    channel_id: int
    platform: str
    author_name: str
    message_text: str
    message_url: str | None
    timestamp: datetime

class BaseCollector(ABC):
    @abstractmethod
    async def connect(self) -> bool:
        """建立平台连接"""

    @abstractmethod
    async def listen(self) -> AsyncIterator[RawMessage]:
        """监听消息流"""

    @abstractmethod
    async def health_check(self) -> HealthStatus:
        """健康检查，每5分钟调用"""

    @abstractmethod
    async def disconnect(self) -> None:
        """优雅关闭"""
```

### 3.2 Telegram 采集器

基于现有 Signal Tracker (端口 5112) 改造：
- 重构为 `BaseCollector` 接口实现
- 输出 `RawMessage` 到 Redis Stream（而非直接处理）
- 添加 `health_check()`：活跃群组 10 分钟无消息则告警
- 支持通过 API 动态添加/移除频道（无需重启）

```python
class TelegramCollector(BaseCollector):
    def __init__(self, api_id, api_hash, session_name):
        self.client = TelegramClient(session_name, api_id, api_hash)
        self.channels: dict[int, ChannelConfig] = {}

    async def add_channel(self, channel_url: str) -> int:
        """动态加入并开始监听频道"""

    async def remove_channel(self, channel_id: int) -> bool:
        """停止监听并可选退出频道"""
```

### 3.3 Twitter/X 采集器（Phase 3）

- Twitter API v2，使用 `tweepy.StreamingClient`
- 按用户 ID 过滤（博主账号）
- 速率限制：Basic 套餐 50万条/月（$100/月）
- 降级方案：流不可用时每 60 秒轮询用户时间线

### 3.4 消息队列

采集器与引擎之间使用 **Redis Stream**：

```
Stream key: signalhive:raw_messages
Consumer group: engine_group

消息格式:
{
    "channel_id": "42",
    "platform": "telegram",
    "author": "crypto_whale",
    "text": "BTC看起来很强势，目标72k...",
    "url": "https://t.me/group/12345",
    "ts": "2026-04-03T14:30:00Z"
}
```

- Consumer group 保证每条消息仅处理一次
- 待处理消息 5 分钟后自动回收（崩溃恢复）
- Stream 修剪至最近 10万 条（可配置）

---

## 4. 信号提取引擎

### 4.1 两层流水线

```
第一层: 预筛选 (成本=0)
├── 关键词匹配: 币名、交易术语 (long/short/buy/sell/入场/做多/止损...)
├── 模式匹配: 价格数字 (\d+\.?\d*)、杠杆提及
├── 最短长度检查 (跳过 <10 字符)
└── 结果: pass_prefilter = True/False (预期 10-20% 通过率)

第二层: LLM 提取 (成本 ≈ $0.01-0.03/次)
├── 仅处理 pass_prefilter = True 的消息
├── 通过 function calling / JSON mode 输出结构化数据
└── 结果: SignalData 或 null (并非每条预筛消息都是真信号)
```

### 4.2 LLM Prompt 设计

```python
EXTRACTION_SYSTEM_PROMPT = """
你是加密货币交易信号提取器。分析消息并提取交易信号（如存在）。
如无可执行信号则返回 null。

规则：
- 仅提取明确或强烈暗示的交易信号
- 置信度 0.0-1.0，基于信号的具体性和可执行性
- 根据时间周期设置 TTL：超短线=900s，日内=3600s，波段=86400s
- 始终完整保留原始消息文本
"""

EXTRACTION_OUTPUT_SCHEMA = {
    "type": "object",
    "properties": {
        "coin": {"type": "string"},
        "direction": {"enum": ["LONG", "SHORT"]},
        "confidence": {"type": "number", "minimum": 0, "maximum": 1},
        "entry_hint": {"type": "string"},
        "tp_hint": {"type": "string"},
        "sl_hint": {"type": "string"},
        "reasoning": {"type": "string"},
        "ttl_seconds": {"type": "integer"},
        "source_text": {"type": "string"}
    },
    "required": ["coin", "direction", "confidence", "reasoning", "source_text"]
}
```

### 4.3 成本估算

| 场景 | 消息/天 | 预筛通过 | LLM调用/天 | 成本/天 |
|------|---------|---------|------------|---------|
| 5个TG群 | 2,500 | 375 (15%) | 375 | $3.75-$11.25 |
| 20个TG群 | 10,000 | 1,500 | 1,500 | $15-$45 |
| 20 TG + 10 Twitter | 15,000 | 2,250 | 2,250 | $22.50-$67.50 |

规模化（20个群）月成本：**$450-$1,350** — 约 15-45 个专业版用户即可覆盖。

---

## 5. 信号评估

### 5.1 评分公式

```python
def calculate_signal_score(signal: SignalData, channel: Channel) -> float:
    # 权重
    W_CONFIDENCE = 0.30  # LLM 置信度
    W_ACCURACY   = 0.30  # 信号源历史准确率
    W_CONSENSUS  = 0.20  # 跨源共识度
    W_FRESHNESS  = 0.20  # 时效性

    # 1. LLM 置信度 (0-1)
    conf_score = signal.confidence

    # 2. 信号源历史准确率 (0-1, 30天指数衰减)
    accuracy = get_source_accuracy(channel.id, signal.author)
    acc_score = accuracy.rate if accuracy.total_signals >= 10 else 0.5  # 新源默认值

    # 3. 跨源共识度 (-1到1, 归一化至 0-1)
    consensus = get_consensus(signal.coin, signal.direction, window_minutes=60)
    cons_score = (consensus + 1) / 2

    # 4. 时效性 (1.0 → 0.0 在TTL内线性衰减)
    age_seconds = (now() - signal.created_at).total_seconds()
    fresh_score = max(0, 1 - age_seconds / signal.ttl_seconds)

    return (conf_score * W_CONFIDENCE +
            acc_score * W_ACCURACY +
            cons_score * W_CONSENSUS +
            fresh_score * W_FRESHNESS) * 100  # 缩放至 0-100
```

### 5.2 冲突解决

非二元判断，而是加权处理：
- 3源做多 + 1源做空 → 共识度 = +0.5（中等看涨）
- 2多 + 2空 → 共识度 = 0.0（中性，标记为冲突）
- 低共识度自然拉低信号总评分
- 前端用琥珀色指示器高亮冲突信号

### 5.3 信号源准确率追踪

```python
def update_accuracy(channel_id: int, author: str, signal: Signal, outcome: str):
    """信号过期或交易关闭时调用"""
    acc = get_or_create_accuracy(channel_id, author)
    acc.total_signals += 1
    if outcome == 'win':
        acc.win_count += 1
    elif outcome == 'loss':
        acc.loss_count += 1

    # 指数衰减：近期信号权重为30天前信号的2倍
    acc.accuracy_rate = calculate_weighted_accuracy(channel_id, author, window_days=30)
    acc.last_updated = now()
```

告警触发条件：准确率在 7 天内下降超过 20%。

---

## 6. 策略与执行

### 6.1 策略模板

```python
STRATEGY_TEMPLATES = {
    "conservative": {          # 保守型
        "entry_delay_seconds": 900,    # 信号后等15分钟
        "position_pct": 0.03,          # 每笔3%资金
        "leverage": 1,
        "tp_pct": 0.05,               # 5%止盈
        "sl_pct": 0.03,               # 3%止损
        "min_signal_score": 75,
        "require_consensus": True,
    },
    "balanced": {              # 平衡型
        "entry_delay_seconds": 0,
        "position_pct": 0.05,          # 5%
        "leverage": 3,
        "tp_pct": 0.08,
        "sl_pct": 0.05,
        "min_signal_score": 65,
        "require_consensus": False,
    },
    "aggressive": {            # 激进型
        "entry_delay_seconds": 0,
        "position_pct": 0.10,          # 10%
        "leverage": 10,
        "tp_pct": 0.15,
        "sl_pct": 0.08,
        "min_signal_score": 55,
        "require_consensus": False,
    },
}
```

### 6.2 执行流程

```
信号评分 ≥ min_score
  → 检查策略绑定（哪些渠道触发此策略）
  → 检查持仓限制（最大持仓数）
  → 如果 entry_delay > 0：调度延迟检查
  → 计算仓位：资金 × position_pct / 杠杆
  → 模拟模式：当前价格 + 随机滑点 (0.05-0.2%) 模拟成交
  → 实盘模式：ccxt create_order（市价或限价）
  → 设置止盈/止损单
  → 写入 signal_trades 表
  → 发送 Telegram 通知
```

### 6.3 Paper Trade 真实性

```python
def simulate_fill(price: float, direction: str) -> float:
    """为模拟交易添加真实滑点"""
    slippage_pct = random.uniform(0.0005, 0.002)  # 0.05% - 0.2%
    if direction == "LONG":
        return price * (1 + slippage_pct)
    else:
        return price * (1 - slippage_pct)
```

---

## 7. 数据库 Schema

```sql
CREATE TABLE channels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    platform ENUM('telegram','twitter','weibo','youtube','facebook'),
    source_url VARCHAR(500) NOT NULL,
    source_name VARCHAR(200),
    status ENUM('active','paused','error','connecting') DEFAULT 'connecting',
    last_message_at DATETIME,
    health_status JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
);

CREATE TABLE raw_messages (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    channel_id INT NOT NULL,
    message_text TEXT,
    message_url VARCHAR(500),
    author_name VARCHAR(200),
    passed_prefilter BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel_time (channel_id, created_at)
);

CREATE TABLE signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_id BIGINT NOT NULL,
    channel_id INT NOT NULL,
    coin VARCHAR(20) NOT NULL,
    direction ENUM('LONG','SHORT'),
    llm_confidence DECIMAL(4,3),
    final_score DECIMAL(5,2),
    entry_hint DECIMAL(20,8),
    tp_hint DECIMAL(20,8),
    sl_hint DECIMAL(20,8),
    reasoning TEXT,
    source_text TEXT,
    ttl_seconds INT DEFAULT 3600,
    expires_at DATETIME,
    status ENUM('active','expired','executed','invalidated') DEFAULT 'active',
    actual_result ENUM('win','loss','pending','expired') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coin_status (coin, status),
    INDEX idx_score (final_score DESC),
    INDEX idx_expires (expires_at)
);

CREATE TABLE source_accuracy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel_id INT NOT NULL,
    author_name VARCHAR(200),
    total_signals INT DEFAULT 0,
    win_count INT DEFAULT 0,
    loss_count INT DEFAULT 0,
    accuracy_rate DECIMAL(5,4),
    avg_roi DECIMAL(8,4),
    last_updated DATETIME,
    UNIQUE KEY uk_channel_author (channel_id, author_name)
);

CREATE TABLE strategies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100),
    template_type ENUM('conservative','balanced','aggressive','custom'),
    config JSON,
    bound_channels JSON,
    min_signal_score DECIMAL(5,2) DEFAULT 60,
    is_paper BOOLEAN DEFAULT TRUE,
    status ENUM('active','paused') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status)
);

CREATE TABLE signal_trades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    signal_id INT NOT NULL,
    strategy_id INT NOT NULL,
    user_id INT NOT NULL,
    coin VARCHAR(20),
    direction ENUM('LONG','SHORT'),
    entry_price DECIMAL(20,8),
    exit_price DECIMAL(20,8),
    quantity DECIMAL(20,8),
    leverage INT DEFAULT 1,
    pnl DECIMAL(20,8),
    roi DECIMAL(8,4),
    is_paper BOOLEAN DEFAULT TRUE,
    status ENUM('open','closed','cancelled'),
    opened_at DATETIME,
    closed_at DATETIME,
    INDEX idx_user_status (user_id, status),
    INDEX idx_signal (signal_id)
);
```

---

## 8. API 接口

### 用户端

```
POST   /api/channels              添加渠道
GET    /api/channels              列出用户渠道
DELETE /api/channels/:id          移除渠道
GET    /api/channels/:id/health   渠道健康状态

GET    /api/signals               信号列表 (筛选: coin, direction, min_score, status)
GET    /api/signals/:id           信号详情（含原文）
GET    /api/signals/digest        每日 Top 5 摘要

POST   /api/strategies            创建策略
GET    /api/strategies            策略列表
PUT    /api/strategies/:id        更新策略
DELETE /api/strategies/:id        删除策略

GET    /api/trades                交易列表 (筛选: is_paper, status, coin)
GET    /api/trades/stats          绩效统计 (PnL, 胜率, 回撤)

GET    /api/replay/:signal_id     信号回放分析
```

### Admin 端

```
GET    /admin/api/channels/all    全部渠道（跨用户）
GET    /admin/api/trends          趋势热力图数据
GET    /admin/api/leaderboard     博主准确率排名
GET    /admin/api/consensus       跨渠道共识矩阵
GET    /admin/api/anomalies       近期异常告警
GET    /admin/api/health          全系统健康面板
```

---

## 9. 监控与告警

| 检查项 | 频率 | 告警条件 | 通知渠道 |
|--------|------|---------|---------|
| 采集器心跳 | 5分钟 | 活跃渠道10分钟无消息 | Telegram |
| LLM API 健康 | 1分钟 | 错误率>5% 或延迟>10s | Telegram |
| 信号量 | 1小时 | 量偏离滚动均值 >3σ | Telegram |
| 准确率下降 | 每日 | 任何源7天内下降>20% | Telegram |
| Redis Stream 延迟 | 1分钟 | 消费延迟>1000条 | Telegram |
| 磁盘/内存 | 5分钟 | 使用率>85% | Telegram |

---

## 10. 部署

### 目录结构

```
/opt/signalhive/
├── app/
│   ├── __init__.py
│   ├── models/           # SQLAlchemy 模型
│   ├── collectors/       # BaseCollector + 平台实现
│   ├── engine/
│   │   ├── prefilter.py  # 关键词预筛选
│   │   ├── extractor.py  # LLM 集成
│   │   ├── evaluator.py  # 信号评分
│   │   ├── matcher.py    # 策略匹配
│   │   └── executor.py   # 模拟+实盘交易
│   ├── api/              # Flask 蓝图
│   ├── admin/            # Admin 路由
│   ├── templates/        # Jinja2 (MVP 前端)
│   └── utils/
├── config.py
├── requirements.txt
├── gunicorn.conf.py
└── supervisord.conf
```

### Supervisord 配置

```ini
[program:signalhive-web]
command=/opt/signalhive/venv/bin/gunicorn -c gunicorn.conf.py app:create_app()
directory=/opt/signalhive
autostart=true
autorestart=true

[program:signalhive-collector-tg]
command=/opt/signalhive/venv/bin/python -m app.collectors.telegram_runner
directory=/opt/signalhive
autostart=true
autorestart=true

[program:signalhive-engine]
command=/opt/signalhive/venv/bin/python -m app.engine.runner
directory=/opt/signalhive
autostart=true
autorestart=true
```

### 服务器要求

| 资源 | 最低 | 推荐 |
|------|------|------|
| CPU | 2核 | 4核 |
| 内存 | 2 GB | 4 GB |
| 磁盘 | 20 GB | 50 GB |
| Redis | 6.0+ | 7.0+ |
| MySQL | 8.0+ | 8.0+ |
| Python | 3.11+ | 3.12+ |

---

## 11. 实施阶段

### Phase 1：Telegram 信号闭环（第1-4周）

| 周 | 交付物 |
|----|--------|
| W1 | DB Schema + BaseCollector 接口 + TG Collector 重构 |
| W2 | Redis Stream 集成 + 预筛选 + LLM 提取器 |
| W3 | 评估器(评分) + 信号 API + 基础 Dashboard |
| W4 | TTL 系统 + 健康监控 + Telegram 告警 + 测试 |

**完成标准：**
- [ ] 通过 Web 添加 TG 群 → 60秒内开始监听
- [ ] 消息预筛 → LLM 提取 → 评分后信号展示在 Dashboard
- [ ] 信号展示：币种、方向、评分、原文链接、TTL 倒计时
- [ ] 过期信号自动标灰
- [ ] 采集器故障 5 分钟内告警

### Phase 2：策略 + Paper Trade（第5-8周）

| 周 | 交付物 |
|----|--------|
| W5 | 策略模型 + 3个模板 + 策略 API |
| W6 | 匹配器（信号→策略绑定）+ Paper Trade 执行器 |
| W7 | 信号源准确率追踪 + 准确率 API + 排行榜 |
| W8 | 绩效 Dashboard（PnL/胜率/回撤）+ 测试 |

### Phase 3：多平台 + Admin（第9-12周）

| 周 | 交付物 |
|----|--------|
| W9 | Twitter 采集器 + API 集成 |
| W10 | Admin：趋势热力图 + 共识矩阵 |
| W11 | Admin：排行榜 + 异常检测 |
| W12 | 微博采集器（如可行）+ 集成测试 |

### Phase 4：实盘 + 商业化（第13-16周）

| 周 | 交付物 |
|----|--------|
| W13 | 实盘执行器（Binance/Bitget via ccxt）+ 风控 |
| W14 | 订阅系统 + 支付集成 |
| W15 | 信号回放引擎 + API 层（Webhook） |
| W16 | 压力测试 + 安全审计 + 生产部署 |

---

## 12. 安全考量

- **API 密钥：** 静态加密存储（AES-256），绝不写入日志
- **交易所凭证：** 加密存储，**绝不** 请求提币权限
- **频率限制：** 按用户的 API 调用限制，防止滥用
- **输入验证：** 所有用户输入（渠道 URL、策略参数）均做清洗
- **认证：** JWT Token + Refresh Token，会话过期机制
- **HTTPS：** 通过 Nginx 强制，启用 HSTS 头
- **审计日志：** 所有交易、策略变更、渠道操作均记录
