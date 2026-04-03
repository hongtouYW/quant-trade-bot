# SignalHive — 社交媒体交易信号聚合平台

> 产品提案 v2.0 | 2026-04-03

## 一、产品定位

社交媒体交易信号的聚合、评估与自动化执行平台。

**双模块架构：**
- **模块1 (实时信号)** — Telegram + X 文字消息 → 快速提取 → 即时 Paper Trade
- **模块2 (内容分析)** — YouTube/Bilibili/微博/Facebook 视频+长文 → 转文字 → 深度分析 → 趋势信号 → Paper Trade

两个模块共享：信号评估框架 + Paper Trade 引擎 + 每日评估 + 排行榜

---

## 二、目标用户

| 画像 | 需求 | 付费意愿 |
|------|------|---------|
| 跟单小白 | 一键跟单 | 低 |
| **半自动交易者** (MVP目标) | 信号聚合 + 自主决策 + 执行 | 中 |
| 量化玩家 | API + 回测数据 | 高 |

---

## 三、模块1 — 实时消息信号（Telegram + X）

### 核心逻辑

```
TG群/X账号 → 实时监听 → 关键词预筛 → LLM提取 → 可执行信号 → Paper Trade
```

### 功能

| 功能 | 说明 |
|------|------|
| 渠道管理 | 添加 TG 群/X 账号，实时监听消息 |
| 信号提取 | 两层过滤：关键词(免费) → LLM解析(~$0.01/次) |
| 信号展示 | 币种/方向/置信度/入场位/止盈止损/原文链接/TTL倒计时 |
| 10群独立运作 | 每个群独立采集+信号，互不干扰 |
| 自动Paper Trade | 信号触发 → 策略模板匹配 → 模拟交易（含滑点） |
| 每日评估 | 每天评估每个群的信号成功率和可信度 |
| 周排行榜 | 第7天起生成群/博主排名（准确率+ROI） |

### 信号类型: ActionSignal (可执行信号)

```json
{
  "type": "action",
  "coin": "BTC", "direction": "LONG", "confidence": 0.82,
  "entry_hint": "68000", "tp_hint": "72000", "sl_hint": "66000",
  "ttl_seconds": 3600,
  "source_url": "https://t.me/..."
}
```

### 特征
- 时效性：秒级-分钟级
- 信号有明确的 entry/tp/sl
- 可直接用于自动化交易
- 评估标准：信号触发后价格是否到达 tp/sl

### 每日评估报告（每群独立）

```
📊 群: @crypto_signals_vip | 2026-04-03
───────────────────────
今日信号: 8条
成功: 5 | 失败: 2 | 未结: 1
成功率: 71.4%
可信度评分: 7.2/10
平均ROI: +2.8%
最佳信号: BTC LONG +5.3%
最差信号: ETH SHORT -3.1%
───────────────────────
累计(7天): 成功率 68% | 排名 #3/10
```

---

## 四、模块2 — 社交内容分析（YouTube/Bilibili/微博/Facebook）

### 核心逻辑

```
视频/长文 → 转文字(ASR/字幕) → 内容摘要 → 深度分析 → 趋势信号 → 策略生成 → Paper Trade
```

### 功能

| 功能 | 说明 |
|------|------|
| 渠道管理 | 添加 YouTube/Bilibili 频道、微博/Facebook 账号 |
| 内容转文字 | 优先抓取平台自带字幕/文案，无字幕时 ASR 转录 |
| 内容分析 | LLM 摘要 → 提取观点/币种/方向/论据/时间框架 |
| 信号筛选 | 从分析中筛选可交易信号（明确的 vs 模糊的） |
| 策略生成 | 基于博主观点自动生成策略参数 |
| Paper Trade | 趋势信号 → 策略匹配 → 模拟交易 |
| 每日评估 | 每天评估每个博主的观点准确度 |
| 排行榜 | 博主准确率+ROI排名 |

### 内容转文字策略

| 平台 | 优先方案 | Fallback |
|------|---------|----------|
| YouTube | 自动字幕 API (免费) | Whisper ASR ($0.006/min) |
| Bilibili | CC字幕 + 简介文案 (免费) | Whisper ASR |
| 微博 | 帖子文案 (免费) | 图片OCR (如有) |
| Facebook | 帖子文案 (免费) | 视频ASR |

### 内容分析流水线

```
原始内容(视频/文)
    ↓
第一步: 内容摘要 (LLM, ~$0.02-0.05/次)
    "博主分析了BTC的日线图，认为突破68000后将快速上涨至72000，
     建议在回踩67500附近做多，止损66000。时间框架1-2周。"
    ↓
第二步: 结构化提取 (LLM, ~$0.01/次)
    {
      "mentioned_coins": ["BTC"],
      "sentiment": "bullish",
      "key_levels": {"support": 67500, "resistance": 72000},
      "timeframe": "1-2 weeks",
      "actionability": 0.7  ← 有多具体可执行
    }
    ↓
第三步: 信号判定
    actionability ≥ 0.6 → 生成 TrendSignal
    actionability < 0.6 → 仅记录观点，不生成交易信号
```

### 信号类型: TrendSignal (趋势信号)

```json
{
  "type": "trend",
  "coin": "BTC", "direction": "LONG", "confidence": 0.70,
  "entry_zone": "67000-68000",
  "target_zone": "71000-73000",
  "invalidation": "66000",
  "timeframe": "1-2 weeks",
  "reasoning": "日线突破+成交量放大",
  "source_url": "https://youtube.com/...",
  "content_summary": "..."
}
```

### 特征
- 时效性：小时级-天级
- 信号是"区间"而非精确价位
- 适合波段/趋势交易
- 评估标准：在 timeframe 内价格是否进入 target_zone

### 每日评估报告（每博主独立）

```
📊 博主: CryptoKing (YouTube) | 2026-04-03
───────────────────────
本周分析视频: 5个
提取信号: 3条
成功: 2 | 失败: 0 | 进行中: 1
观点准确度: 8.1/10
Paper Trade ROI: +4.5%
───────────────────────
分析风格: 技术面为主，偏中长线
擅长币种: BTC, ETH
弱点: 山寨币判断偏差大
```

---

## 五、共享层

### 统一信号评估框架

两种信号用不同权重：

**ActionSignal 评分 (模块1):**
```
评分 = LLM置信度(0.3) + 博主准确率(0.3) + 跨源共识(0.2) + 时效性(0.2)
```

**TrendSignal 评分 (模块2):**
```
评分 = 内容深度(0.25) + 博主准确率(0.30) + 跨源共识(0.25) + 可执行度(0.20)
```

### 统一排行榜

| 排名 | 来源 | 模块 | 准确率 | ROI | 信号数 | 评分 |
|------|------|------|--------|-----|--------|------|
| #1 | @crypto_guru (TG) | M1 | 73% | +4.2% | 45 | 8.5 |
| #2 | CryptoKing (YouTube) | M2 | 80% | +3.8% | 12 | 8.2 |
| #3 | @btc_signals (X) | M1 | 68% | +2.8% | 82 | 7.6 |
| #4 | 币圈老王 (Bilibili) | M2 | 75% | +2.1% | 8 | 7.3 |
| ... | | | | | | |

可按 模块/平台/时间范围 筛选。

### Paper Trade 引擎（共享）

两个模块共用同一个 Paper Trade 引擎，但执行逻辑不同：

| | ActionSignal (M1) | TrendSignal (M2) |
|--|---|---|
| 入场 | 信号价格 ± 滑点 | entry_zone 中间价 |
| 止盈 | tp_hint 精确价 | target_zone 下沿 |
| 止损 | sl_hint 精确价 | invalidation 价 |
| 超时 | TTL 到期平仓 | timeframe 到期评估 |
| 仓位 | 策略模板决定 | 默认更小仓位(趋势更不确定) |

---

## 六、系统架构（双模块）

```
┌──────────────────────────────┐  ┌──────────────────────────────────┐
│   模块1: 实时信号             │  │   模块2: 内容分析                  │
│                              │  │                                  │
│  TG Collector ──┐            │  │  YouTube Collector ──┐           │
│  X Collector  ──┤→ Redis     │  │  Bilibili Collector ──┤→ Media   │
│                 │  Stream    │  │  Weibo Collector   ──┤  Queue   │
│                 ▼            │  │  Facebook Collector──┤          │
│           PreFilter          │  │                      ▼          │
│                │             │  │              Video→Text (ASR)    │
│                ▼             │  │                      │          │
│           LLM Extract        │  │                      ▼          │
│           (ActionSignal)     │  │              LLM Content Analyze │
│                              │  │              (TrendSignal)       │
└──────────────┬───────────────┘  └──────────────────┬───────────────┘
               │                                     │
               ▼                                     ▼
┌──────────────────────────────────────────────────────────────────┐
│                         共享层                                    │
│                                                                  │
│  信号评估器 → 策略匹配 → Paper Trade → 每日评估 → 排行榜           │
│                                                                  │
│  MySQL | Redis | Flask API | Dashboard | TG通知                   │
└──────────────────────────────────────────────────────────────────┘
```

### 技术选型

| 组件 | 选型 |
|------|------|
| 后端 | Flask (复用 trading-saas) |
| 数据库 | MySQL |
| 消息队列 | Redis Stream |
| LLM | Claude API / OpenAI |
| ASR | 平台字幕优先, Whisper API fallback |
| 交易 | ccxt (多交易所) |
| 前端 | Jinja2 (MVP) → React (V2) |
| 部署 | Gunicorn + Nginx + Supervisord |

---

## 七、数据模型（v2 新增/修改）

原有6张表保留，新增/修改如下：

```sql
-- channels 表增加字段
ALTER TABLE channels ADD COLUMN module ENUM('realtime','content') NOT NULL DEFAULT 'realtime';
ALTER TABLE channels ADD COLUMN content_type ENUM('text','video','mixed') DEFAULT 'text';

-- signals 表增加字段
ALTER TABLE signals ADD COLUMN signal_type ENUM('action','trend') NOT NULL DEFAULT 'action';
ALTER TABLE signals ADD COLUMN entry_zone VARCHAR(50);         -- 模块2: "67000-68000"
ALTER TABLE signals ADD COLUMN target_zone VARCHAR(50);        -- 模块2: "71000-73000"
ALTER TABLE signals ADD COLUMN invalidation DECIMAL(20,8);     -- 模块2: 失效价位
ALTER TABLE signals ADD COLUMN timeframe VARCHAR(50);          -- 模块2: "1-2 weeks"
ALTER TABLE signals ADD COLUMN content_summary TEXT;           -- 模块2: 内容摘要

-- 新增: 内容转文字记录
CREATE TABLE content_transcripts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel_id INT NOT NULL,
    content_url VARCHAR(500) NOT NULL,
    content_title VARCHAR(500),
    platform ENUM('youtube','bilibili','weibo','facebook'),
    transcript_method ENUM('subtitle','asr','text_post') NOT NULL,
    raw_transcript TEXT,
    summary TEXT,
    analysis JSON,                -- LLM分析结果
    mentioned_coins JSON,         -- ["BTC","ETH","SOL"]
    overall_sentiment ENUM('bullish','bearish','neutral','mixed'),
    actionability DECIMAL(3,2),   -- 0.0-1.0
    language VARCHAR(10),
    duration_seconds INT,         -- 视频时长
    transcript_cost DECIMAL(8,4), -- ASR成本
    analysis_cost DECIMAL(8,4),   -- LLM成本
    processed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel_id),
    INDEX idx_sentiment (overall_sentiment),
    INDEX idx_actionability (actionability DESC)
);

-- 新增: 每日评估报告
CREATE TABLE daily_evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel_id INT NOT NULL,
    eval_date DATE NOT NULL,
    module ENUM('realtime','content') NOT NULL,
    total_signals INT DEFAULT 0,
    win_count INT DEFAULT 0,
    loss_count INT DEFAULT 0,
    pending_count INT DEFAULT 0,
    success_rate DECIMAL(5,4),
    credibility_score DECIMAL(4,2),   -- 0-10
    avg_roi DECIMAL(8,4),
    best_signal_id INT,
    worst_signal_id INT,
    report_json JSON,                  -- 完整报告数据
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_channel_date (channel_id, eval_date),
    INDEX idx_date (eval_date)
);

-- 新增: 排行榜快照（每周生成）
CREATE TABLE leaderboard_snapshots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    snapshot_date DATE NOT NULL,
    period ENUM('daily','weekly','monthly') DEFAULT 'weekly',
    rankings JSON,     -- 排名数据数组
    module ENUM('realtime','content','all'),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_period (snapshot_date, period)
);
```

---

## 八、风险与对策（v2 新增）

| 风险 | 模块 | 对策 |
|------|------|------|
| LLM误判(假信号) | M1+M2 | 两层过滤 + 置信度阈值 + 人工确认 |
| 信号延迟(>30s) | M1 | TTL机制 + 过期自动丢弃 |
| 博主准确率衰减 | M1+M2 | 30天滚动 + 指数衰减 + 骤降告警 |
| 多源冲突 | M1+M2 | 加权一致性 + 冲突高亮 + 用户确认 |
| 数据源断裂 | M1+M2 | 5分钟心跳 + 告警 + 降级 |
| Paper→实盘偏差 | M1+M2 | 模拟滑点 + 风险提示 |
| LLM成本失控 | M1+M2 | 预筛降80-90%调用 + 用量监控 |
| **视频转文字失败** | **M2** | **字幕优先 + ASR fallback + 失败告警** |
| **长内容LLM成本高** | **M2** | **先摘要(短)再分析(结构化), 两步控成本** |
| **趋势信号时效低** | **M2** | **区分ActionSignal/TrendSignal, 不同评估标准** |
| **多语言内容** | **M2** | **LLM多语言理解 + 语言标记** |
| **版权/爬虫风险** | **M2** | **仅公开内容 + 官方API** |
| 合规 | M1+M2 | Day1免责 + 不构成投资建议 |

---

## 九、交付计划（v2 双模块）

| 阶段 | 周期 | 模块 | 目标 | 关键交付 |
|------|------|------|------|---------|
| **P1** | W1-4 | M1 | TG信号闭环 | TG Collector + LLM提取 + Dashboard + TTL + 健康监控 |
| **P2** | W5-8 | M1 | Paper Trade + 评估 | 策略模板 + Paper Trade + 每日评估 + 第7天排行榜 |
| **P3** | W9-12 | M1 | X平台 + 排行榜完善 | X Collector + 10群独立运作 + 跨群排名 + Admin面板 |
| **P4** | W13-16 | M2 | YouTube+Bilibili | 视频转文字 + 内容分析 + TrendSignal + Paper Trade |
| **P5** | W17-20 | M2 | 微博+Facebook + 评估 | 更多平台 + 每日评估 + 统一排行榜 |
| **P6** | W21-24 | 共享 | 实盘 + 商业化 | 实盘交易 + 付费分层 + API + 信号回放 |

---

## 十、商业模式

| 层级 | 价格 | 功能 |
|------|------|------|
| 免费 | $0 | 3渠道(仅M1), 10条信号/日, 无Paper Trade |
| 基础 | $29/月 | 10渠道(M1), Paper Trade, 策略模板, 每日评估 |
| 专业 | $79/月 | M1无限 + M2(5频道), 实盘, 排行榜, 信号回放 |
| 全能 | $149/月 | M1+M2无限, 实盘, 排行榜, API, 高级分析 |
| API | $199/月 | 信号流API, 回测导出, Webhook |

---

## 十一、复用现有基础设施

| 现有 | 复用 |
|------|------|
| Signal Tracker (5112) | TG Collector |
| trading-saas Flask (80) | Web框架+认证 |
| agent_bot | Paper Trade+实盘执行 |
| MySQL + ccxt | 存储+交易所 |
| TG通知 + Supervisord | 告警+部署 |

---

## 十二、合规(Day 1)

- 免责声明: 信息聚合工具, 不构成投资建议
- 风险提示: 交易前弹窗
- 数据声明: 信号来自第三方, 不保证准确性
- 用户协议: 自担交易风险
- 内容版权: 仅采集公开内容, 使用官方API
