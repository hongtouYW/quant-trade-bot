# SignalHive — Technical Proposal

> Technical Architecture & Implementation Specification | April 2026

---

## 1. System Overview

SignalHive is a social media trading signal aggregation platform. This document covers the full technical design for development teams.

**Core pipeline:**
```
Collectors → Redis Stream → Extractor (keyword + LLM) → Evaluator (scoring) → Matcher (strategy) → Executor (paper/live)
```

---

## 2. Architecture

### 2.1 Component Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                          SignalHive Server                          │
│                                                                     │
│  ┌──────────────┐    ┌─────────────┐    ┌────────────────────────┐  │
│  │  Collectors   │    │ Redis Stream │    │     Core Engine        │  │
│  │              │    │             │    │                        │  │
│  │  telegram ───┼──→│  raw_msgs   │──→│ PreFilter → LLM Extract│  │
│  │  twitter  ───┼──→│             │    │ → Evaluator → Matcher  │  │
│  │  weibo    ───┼──→│             │    │ → Executor             │  │
│  └──────────────┘    └─────────────┘    └────────────────────────┘  │
│                                                │                    │
│                                                ▼                    │
│  ┌──────────────┐    ┌─────────────┐    ┌──────────────┐           │
│  │   Flask API   │    │    MySQL     │    │  ccxt/Exchange│           │
│  │  (REST + UI)  │    │  (all data)  │    │  (trade exec) │           │
│  └──────────────┘    └─────────────┘    └──────────────┘           │
│         │                                                           │
│         ▼                                                           │
│  ┌──────────────┐                                                   │
│  │   Nginx       │ ← port 80/443                                    │
│  └──────────────┘                                                   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Process Model

| Process | Role | Managed By |
|---------|------|-----------|
| `signalhive-web` | Flask API + Dashboard | Supervisord |
| `signalhive-collector-tg` | Telegram listener | Supervisord |
| `signalhive-collector-twitter` | Twitter listener | Supervisord (P3) |
| `signalhive-engine` | Extract + Evaluate + Match + Execute loop | Supervisord |

Each collector runs as an independent process. Engine consumes from Redis Stream.

---

## 3. Data Collection Layer

### 3.1 Collector Interface

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
        """Establish connection to platform."""

    @abstractmethod
    async def listen(self) -> AsyncIterator[RawMessage]:
        """Yield messages as they arrive."""

    @abstractmethod
    async def health_check(self) -> HealthStatus:
        """Return current health. Called every 5 minutes."""

    @abstractmethod
    async def disconnect(self) -> None:
        """Graceful shutdown."""
```

### 3.2 Telegram Collector

Built on existing Signal Tracker (port 5112). Key modifications:
- Refactor to implement `BaseCollector` interface
- Output `RawMessage` to Redis Stream instead of direct processing
- Add `health_check()`: alert if no messages received in 10 minutes for an active group
- Support dynamic channel add/remove via API (no restart required)

```python
class TelegramCollector(BaseCollector):
    def __init__(self, api_id, api_hash, session_name):
        self.client = TelegramClient(session_name, api_id, api_hash)
        self.channels: dict[int, ChannelConfig] = {}

    async def add_channel(self, channel_url: str) -> int:
        """Dynamically join and start listening to a channel."""

    async def remove_channel(self, channel_id: int) -> bool:
        """Stop listening and optionally leave channel."""
```

### 3.3 Twitter/X Collector (Phase 3)

- Twitter API v2 with `tweepy.StreamingClient`
- Filter by user IDs (influencer accounts)
- Rate limit: 500K tweets/month on Basic ($100/mo)
- Fallback: polling user timelines every 60s if stream unavailable

### 3.4 Message Queue

**Redis Stream** between collectors and engine:

```
Stream key: signalhive:raw_messages
Consumer group: engine_group

Entry format:
{
    "channel_id": "42",
    "platform": "telegram",
    "author": "crypto_whale",
    "text": "BTC looking strong, targeting 72k...",
    "url": "https://t.me/group/12345",
    "ts": "2026-04-03T14:30:00Z"
}
```

- Consumer group ensures each message processed exactly once
- Pending entries auto-reclaimed after 5 minutes (crash recovery)
- Stream trimmed to last 100K entries (configurable)

---

## 4. Signal Extraction Engine

### 4.1 Two-Layer Pipeline

```
Layer 1: PreFilter (cost = 0)
├── Keyword match: coin names, trading terms (long/short/buy/sell/入场/做多/止损...)
├── Pattern match: price levels (\d+\.?\d*), leverage mentions
├── Minimum length check (skip < 10 chars)
└── Result: pass_prefilter = True/False (expect 10-20% pass rate)

Layer 2: LLM Extraction (cost ≈ $0.01-0.03/call)
├── Only processes messages where pass_prefilter = True
├── Structured output via function calling / JSON mode
└── Result: SignalData or null (not every pre-filtered message is a real signal)
```

### 4.2 LLM Prompt Design

```python
EXTRACTION_SYSTEM_PROMPT = """
You are a crypto trading signal extractor. Analyze the message and extract
trading signals if present. Return null if no actionable signal found.

Rules:
- Only extract explicit or strongly implied trading signals
- Confidence 0.0-1.0 based on how specific and actionable the signal is
- Set TTL based on timeframe: scalp=900s, intraday=3600s, swing=86400s
- Always preserve the original source text exactly
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

### 4.3 Cost Estimation

| Scenario | Messages/day | Pre-filter pass | LLM calls/day | Cost/day |
|----------|-------------|-----------------|---------------|----------|
| 5 TG groups | 2,500 | 375 (15%) | 375 | $3.75-$11.25 |
| 20 TG groups | 10,000 | 1,500 | 1,500 | $15-$45 |
| 20 TG + 10 Twitter | 15,000 | 2,250 | 2,250 | $22.50-$67.50 |

Monthly at scale (20 groups): **$450-$1,350/mo** — covered by ~15-45 Pro subscribers.

---

## 5. Signal Evaluation

### 5.1 Scoring Formula

```python
def calculate_signal_score(signal: SignalData, channel: Channel) -> float:
    # Component weights
    W_CONFIDENCE = 0.30
    W_ACCURACY   = 0.30
    W_CONSENSUS  = 0.20
    W_FRESHNESS  = 0.20

    # 1. LLM confidence (0-1)
    conf_score = signal.confidence

    # 2. Source historical accuracy (0-1, exponential decay over 30 days)
    accuracy = get_source_accuracy(channel.id, signal.author)
    acc_score = accuracy.rate if accuracy.total_signals >= 10 else 0.5  # default for new sources

    # 3. Cross-source consensus (-1 to 1, normalized to 0-1)
    consensus = get_consensus(signal.coin, signal.direction, window_minutes=60)
    cons_score = (consensus + 1) / 2

    # 4. Time freshness (1.0 → 0.0 linear decay over TTL)
    age_seconds = (now() - signal.created_at).total_seconds()
    fresh_score = max(0, 1 - age_seconds / signal.ttl_seconds)

    return (conf_score * W_CONFIDENCE +
            acc_score * W_ACCURACY +
            cons_score * W_CONSENSUS +
            fresh_score * W_FRESHNESS) * 100  # scale to 0-100
```

### 5.2 Conflict Resolution

Not binary — weighted:
- 3 sources LONG + 1 source SHORT → consensus = +0.5 (moderate bullish)
- 2 LONG + 2 SHORT → consensus = 0.0 (neutral, flag as conflicted)
- Signal score naturally reduced by low consensus component
- UI highlights conflicted signals with amber indicator

### 5.3 Source Accuracy Tracking

```python
def update_accuracy(channel_id: int, author: str, signal: Signal, outcome: str):
    """Called when signal expires or trade closes."""
    acc = get_or_create_accuracy(channel_id, author)
    acc.total_signals += 1
    if outcome == 'win':
        acc.win_count += 1
    elif outcome == 'loss':
        acc.loss_count += 1

    # Exponential decay: recent signals weighted 2x vs 30-day-old signals
    acc.accuracy_rate = calculate_weighted_accuracy(channel_id, author, window_days=30)
    acc.last_updated = now()
```

Alert triggered when: accuracy drops > 20% in 7 days.

---

## 6. Strategy & Execution

### 6.1 Strategy Templates

```python
STRATEGY_TEMPLATES = {
    "conservative": {
        "entry_delay_seconds": 900,    # wait 15 min after signal
        "position_pct": 0.03,          # 3% of capital per trade
        "leverage": 1,
        "tp_pct": 0.05,               # 5% take profit
        "sl_pct": 0.03,               # 3% stop loss
        "min_signal_score": 75,
        "require_consensus": True,
    },
    "balanced": {
        "entry_delay_seconds": 0,
        "position_pct": 0.05,          # 5%
        "leverage": 3,
        "tp_pct": 0.08,
        "sl_pct": 0.05,
        "min_signal_score": 65,
        "require_consensus": False,
    },
    "aggressive": {
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

### 6.2 Execution Flow

```
Signal scored ≥ min_score
  → Check strategy bindings (which channels trigger this strategy)
  → Check position limits (max open positions)
  → If entry_delay > 0: schedule delayed check
  → Calculate position size: capital × position_pct / leverage
  → Paper mode: simulate fill at current price + random slippage (0.05-0.2%)
  → Live mode: ccxt create_order (market or limit)
  → Set TP/SL orders
  → Log to signal_trades table
  → Send Telegram notification
```

### 6.3 Paper Trade Realism

```python
def simulate_fill(price: float, direction: str) -> float:
    """Add realistic slippage to paper trade fills."""
    slippage_pct = random.uniform(0.0005, 0.002)  # 0.05% - 0.2%
    if direction == "LONG":
        return price * (1 + slippage_pct)
    else:
        return price * (1 - slippage_pct)
```

---

## 7. Database Schema

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

## 8. API Endpoints

### User-Facing

```
POST   /api/channels              Add a channel
GET    /api/channels              List user's channels
DELETE /api/channels/:id          Remove channel
GET    /api/channels/:id/health   Channel health status

GET    /api/signals               List signals (filters: coin, direction, min_score, status)
GET    /api/signals/:id           Signal detail with source text
GET    /api/signals/digest        Daily top-5 digest

POST   /api/strategies            Create strategy
GET    /api/strategies            List strategies
PUT    /api/strategies/:id        Update strategy
DELETE /api/strategies/:id        Delete strategy

GET    /api/trades                List trades (filters: is_paper, status, coin)
GET    /api/trades/stats          Performance stats (PnL, winrate, drawdown)

GET    /api/replay/:signal_id     Signal replay analysis
```

### Admin

```
GET    /admin/api/channels/all    All channels across users
GET    /admin/api/trends          Trend heatmap data
GET    /admin/api/leaderboard     Influencer accuracy ranking
GET    /admin/api/consensus       Cross-channel consensus matrix
GET    /admin/api/anomalies       Recent anomaly alerts
GET    /admin/api/health          System-wide health dashboard
```

---

## 9. Monitoring & Alerting

| Check | Frequency | Alert Condition | Channel |
|-------|-----------|-----------------|---------|
| Collector heartbeat | 5 min | No messages in 10 min for active channel | Telegram |
| LLM API health | 1 min | Error rate > 5% or latency > 10s | Telegram |
| Signal volume | 1 hour | Volume deviates > 3σ from rolling average | Telegram |
| Accuracy drop | Daily | Any source drops > 20% in 7 days | Telegram |
| Redis Stream lag | 1 min | Consumer lag > 1000 entries | Telegram |
| Disk / memory | 5 min | > 85% usage | Telegram |

---

## 10. Deployment

### Directory Structure

```
/opt/signalhive/
├── app/
│   ├── __init__.py
│   ├── models/           # SQLAlchemy models
│   ├── collectors/       # BaseCollector + platform implementations
│   ├── engine/
│   │   ├── prefilter.py
│   │   ├── extractor.py  # LLM integration
│   │   ├── evaluator.py  # Scoring
│   │   ├── matcher.py    # Strategy matching
│   │   └── executor.py   # Paper + live trading
│   ├── api/              # Flask blueprints
│   ├── admin/            # Admin routes
│   ├── templates/        # Jinja2 (MVP frontend)
│   └── utils/
├── config.py
├── requirements.txt
├── gunicorn.conf.py
└── supervisord.conf
```

### Supervisord Config

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

### Server Requirements

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| CPU | 2 cores | 4 cores |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB | 50 GB |
| Redis | 6.0+ | 7.0+ |
| MySQL | 8.0+ | 8.0+ |
| Python | 3.11+ | 3.12+ |

---

## 11. Implementation Phases

### Phase 1: Telegram Signal Loop (Week 1-4)

| Week | Deliverable |
|------|------------|
| W1 | DB schema + BaseCollector interface + TG Collector refactor |
| W2 | Redis Stream integration + PreFilter + LLM Extractor |
| W3 | Evaluator (scoring) + Signal API + Basic Dashboard |
| W4 | TTL system + Health monitoring + Telegram alerts + Testing |

**Definition of Done:**
- [ ] Add TG group via web → listening within 60s
- [ ] Messages pre-filtered → LLM extracted → scored signals on dashboard
- [ ] Signals show: coin, direction, score, source link, TTL countdown
- [ ] Expired signals auto-greyed
- [ ] Collector health alert within 5 min of failure

### Phase 2: Strategy + Paper Trade (Week 5-8)

| Week | Deliverable |
|------|------------|
| W5 | Strategy model + 3 templates + strategy API |
| W6 | Matcher (signal→strategy binding) + Paper Trade executor |
| W7 | Source accuracy tracking + accuracy API + leaderboard |
| W8 | Performance dashboard (PnL/winrate/drawdown) + Testing |

### Phase 3: Multi-Platform + Admin (Week 9-12)

| Week | Deliverable |
|------|------------|
| W9 | Twitter Collector + API integration |
| W10 | Admin: trend heatmap + consensus matrix |
| W11 | Admin: leaderboard + anomaly detection |
| W12 | Weibo Collector (if feasible) + Integration testing |

### Phase 4: Live Trading + Monetization (Week 13-16)

| Week | Deliverable |
|------|------------|
| W13 | Live executor (Binance/Bitget via ccxt) + risk controls |
| W14 | Subscription system + payment integration |
| W15 | Signal replay engine + API layer (webhooks) |
| W16 | Load testing + security audit + production deploy |

---

## 12. Security Considerations

- **API keys:** Encrypted at rest (AES-256), never logged
- **Exchange credentials:** Stored encrypted, withdrawal permissions NEVER requested
- **Rate limiting:** Per-user API rate limits to prevent abuse
- **Input validation:** All user inputs sanitized (channel URLs, strategy params)
- **Auth:** JWT tokens with refresh, session expiry
- **HTTPS:** Enforced via Nginx, HSTS headers
- **Audit log:** All trades, strategy changes, and channel modifications logged
