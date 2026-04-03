# SignalHive — 实施手册

> 从零到上线的完整执行指南 | 2026-04

---

## 总览

```
Phase 0 (准备)     Phase 1 (MVP)      Phase 2 (策略)     Phase 3 (扩展)     Phase 4 (商业化)
第0周               第1-4周             第5-8周             第9-12周            第13-16周
───────────────── ─────────────────── ─────────────────── ─────────────────── ───────────────────
项目初始化          TG信号闭环          策略+Paper Trade    多平台+Admin        实盘+付费
```

---

## Phase 0 — 项目准备（第0周，2-3天）

### Day 1：项目初始化

**上午 — 项目骨架**

```bash
# 1. 创建项目目录
mkdir -p /Users/hongtou/newproject/signalhive
cd /Users/hongtou/newproject/signalhive

# 2. 初始化 Git
git init
echo "venv/\n__pycache__/\n*.pyc\n.env\n*.log" > .gitignore

# 3. 创建目录结构
mkdir -p app/{models,collectors,engine,api,admin,templates/admin,static,utils}
mkdir -p tests/{unit,integration}
mkdir -p docs
mkdir -p scripts
touch app/__init__.py app/models/__init__.py app/collectors/__init__.py
touch app/engine/__init__.py app/api/__init__.py app/admin/__init__.py

# 4. 创建虚拟环境
python3.12 -m venv venv
source venv/bin/activate

# 5. 安装基础依赖
pip install flask sqlalchemy pymysql redis gunicorn ccxt
pip install python-telegram-client anthropic  # 或 openai
pip freeze > requirements.txt
```

**下午 — 配置文件**

- [ ] 创建 `config.py` — DB连接、Redis连接、LLM API Key、Telegram API 凭证
- [ ] 创建 `.env` — 所有敏感配置（不入 Git）
- [ ] 创建 `app/__init__.py` — Flask app factory (`create_app()`)
- [ ] 首次 `git commit`

```python
# config.py 骨架
import os

class Config:
    SECRET_KEY = os.getenv('SECRET_KEY', 'dev-key')
    SQLALCHEMY_DATABASE_URI = os.getenv('DATABASE_URI', 'mysql+pymysql://...')
    REDIS_URL = os.getenv('REDIS_URL', 'redis://localhost:6379/0')
    LLM_API_KEY = os.getenv('LLM_API_KEY')
    LLM_MODEL = os.getenv('LLM_MODEL', 'claude-sonnet-4-6')
    TELEGRAM_API_ID = os.getenv('TELEGRAM_API_ID')
    TELEGRAM_API_HASH = os.getenv('TELEGRAM_API_HASH')
    TELEGRAM_ALERT_CHAT_ID = os.getenv('TELEGRAM_ALERT_CHAT_ID')
```

### Day 2：数据库 + 基础模型

**上午 — 建库建表**

- [ ] SSH 到服务器，创建数据库和用户

```sql
CREATE DATABASE signalhive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'signalhive'@'localhost' IDENTIFIED BY '生成一个强密码';
GRANT ALL ON signalhive.* TO 'signalhive'@'localhost';
```

- [ ] 创建全部 6 张核心表（从技术提案复制 SQL）
- [ ] 本地验证连接

**下午 — SQLAlchemy 模型**

- [ ] `app/models/channel.py` — Channel 模型
- [ ] `app/models/message.py` — RawMessage 模型
- [ ] `app/models/signal.py` — Signal 模型
- [ ] `app/models/accuracy.py` — SourceAccuracy 模型
- [ ] `app/models/strategy.py` — Strategy 模型
- [ ] `app/models/trade.py` — SignalTrade 模型
- [ ] `app/models/__init__.py` — 导出所有模型 + `db = SQLAlchemy()`
- [ ] 写一个 `scripts/init_db.py` 验证模型与表的映射

```
git commit -m "feat: 项目初始化 + 数据库模型"
```

### Day 3：基础设施确认

- [ ] 确认服务器 Redis 已安装且运行（`redis-cli ping`）
- [ ] 确认 LLM API Key 可用（写一个 `scripts/test_llm.py` 测试调用）
- [ ] 确认 Telegram API 凭证可用（复用现有 signal-tracker 的 session）
- [ ] 创建 `scripts/test_connections.py` — 一键验证所有外部依赖

```
✅ MySQL: 连接成功
✅ Redis: PONG
✅ LLM API: 响应正常 (延迟 1.2s)
✅ Telegram: 已授权
```

**Phase 0 检查点：**
- [ ] 项目骨架完整，能 `flask run` 启动空应用
- [ ] 6张表已建，SQLAlchemy 模型映射正确
- [ ] 所有外部依赖连接验证通过
- [ ] Git 有初始提交

---

## Phase 1 — Telegram 信号闭环（第1-4周）

### Week 1：采集器 + Redis Stream

**Day 1-2：BaseCollector + TelegramCollector**

- [ ] `app/collectors/base.py` — 定义 BaseCollector 抽象类

```python
# 要实现的方法:
# connect() → bool
# listen() → AsyncIterator[RawMessage]
# health_check() → HealthStatus
# disconnect() → None
```

- [ ] `app/collectors/telegram_collector.py` — 基于 Telethon 实现
  - 从现有 signal-tracker 的代码中提取核心监听逻辑
  - 改为输出到 Redis Stream 而非直接处理
  - 支持动态 add_channel / remove_channel

**Day 3：Redis Stream 集成**

- [ ] `app/utils/redis_client.py` — Redis 连接 + Stream 操作封装

```python
# 核心方法:
# publish_message(stream, data) — 采集器写入
# consume_messages(stream, group, consumer) — 引擎消费
# get_stream_lag() — 监控用
```

- [ ] 测试：TG Collector → Redis Stream → 能消费到消息

**Day 4-5：动态渠道管理 API**

- [ ] `app/api/channels.py` — Flask Blueprint

```
POST /api/channels    — 添加渠道（存DB + 通知Collector加入）
GET  /api/channels    — 列出渠道
DELETE /api/channels/:id — 移除渠道
GET  /api/channels/:id/health — 健康状态
```

- [ ] Collector 监听 Redis 的控制频道，收到 add/remove 指令后动态调整
- [ ] 简单的 Web 页面：一个输入框添加 TG 群链接

```
git commit -m "feat: TG采集器 + Redis Stream + 渠道管理API"
```

**Week 1 检查点：**
- [ ] 添加 TG 群链接 → 60秒内开始收到消息
- [ ] 消息写入 Redis Stream 可被消费
- [ ] 渠道增删不需要重启

---

### Week 2：信号提取引擎

**Day 1-2：预筛选器**

- [ ] `app/engine/prefilter.py`

```python
# 关键词表（中英文）:
CRYPTO_KEYWORDS = ['btc', 'eth', 'sol', 'long', 'short', 'buy', 'sell',
                   '做多', '做空', '入场', '止损', '止盈', '开单', '平仓', ...]
PRICE_PATTERN = r'\d{2,6}\.?\d{0,2}'  # 匹配价格数字

def should_process(message: str) -> bool:
    """关键词+模式匹配，通过率目标 10-20%"""
```

- [ ] 写单元测试：验证预筛准确率（准备 50 条真实TG消息做测试集）
- [ ] 消息经预筛后标记 `passed_prefilter`，存入 raw_messages 表

**Day 3-4：LLM 提取器**

- [ ] `app/engine/extractor.py`

```python
# 核心流程:
# 1. 构造 prompt（system + user message）
# 2. 调用 LLM API，要求 JSON 结构化输出
# 3. 解析响应 → SignalData 或 None
# 4. 存入 signals 表
```

- [ ] LLM 调用封装：重试（3次）、超时（10s）、错误记录
- [ ] 手动测试 20 条真实消息，检查提取质量
- [ ] 记录每次调用的 token 用量和成本

**Day 5：引擎主循环**

- [ ] `app/engine/runner.py` — 引擎消费循环

```python
# 主循环:
# while True:
#   messages = consume_from_redis(block=5000)  # 5s阻塞等待
#   for msg in messages:
#       save_to_raw_messages(msg)
#       if prefilter.should_process(msg.text):
#           signal = extractor.extract(msg)
#           if signal:
#               save_signal(signal)
#               ack_message(msg)
```

```
git commit -m "feat: 预筛选 + LLM信号提取引擎"
```

**Week 2 检查点：**
- [ ] TG 消息 → 预筛 → LLM提取 → signals 表有记录
- [ ] 预筛通过率在 10-20% 范围
- [ ] LLM 提取出的信号结构完整（coin, direction, confidence 等）

---

### Week 3：评分 + API + Dashboard

**Day 1-2：信号评估器**

- [ ] `app/engine/evaluator.py` — 多维评分

```python
# 评分组件:
# 1. LLM 置信度 (0.3)
# 2. 博主历史准确率 (0.3) — 初期数据不足时默认 0.5
# 3. 跨源共识度 (0.2) — 查询60分钟内同币种同方向的信号数
# 4. 时效性 (0.2) — 线性衰减
```

- [ ] 评分后更新 signals 表的 `final_score` 字段
- [ ] 设置 TTL：计算 `expires_at = created_at + ttl_seconds`

**Day 3：信号 API**

- [ ] `app/api/signals.py` — Flask Blueprint

```
GET /api/signals          — 列表（筛选: coin, direction, min_score, status）
GET /api/signals/:id      — 详情（含原文）
GET /api/signals/digest   — 每日 Top 5
```

- [ ] 自动过期逻辑：查询时检查 `expires_at`，过期的标记为 `expired`

**Day 4-5：信号 Dashboard**

- [ ] `app/templates/dashboard.html` — 主页面

```
页面布局:
┌─────────────────────────────────────────┐
│  SignalHive Dashboard        [添加渠道]  │
├─────────────────────────────────────────┤
│  📊 今日摘要: BTC偏多(3/5信号), 共12条   │
├─────────────────────────────────────────┤
│  信号列表（按评分排序）                    │
│  ┌───────────────────────────────────┐  │
│  │ 🟢 BTC LONG | 评分 82 | TTL 45m  │  │
│  │ 来源: @crypto_guru | 置信度 0.85  │  │
│  │ 入场 68000 | 止盈 72000 | 止损 66k │  │
│  │ [查看原文]                        │  │
│  └───────────────────────────────────┘  │
│  ┌───────────────────────────────────┐  │
│  │ ⚪ ETH SHORT | 评分 61 | TTL 20m  │  │
│  │ (已过期信号标灰)                    │  │
│  └───────────────────────────────────┘  │
├─────────────────────────────────────────┤
│  我的渠道: TG群A ✅ | TG群B ✅ | TG群C ⚠️│
└─────────────────────────────────────────┘
```

- [ ] 前端用 Jinja2 + 简单 JS（或 htmx），不引入 React
- [ ] 信号卡片：币种、方向、评分色条、TTL 倒计时、原文链接
- [ ] 过期信号灰色显示
- [ ] 渠道状态栏

```
git commit -m "feat: 信号评分 + API + Dashboard"
```

**Week 3 检查点：**
- [ ] Dashboard 能看到实时信号流
- [ ] 信号按评分排序，过期自动标灰
- [ ] 点击可看原文
- [ ] 每日摘要显示 Top 5

---

### Week 4：监控 + 告警 + 测试

**Day 1-2：健康监控**

- [ ] `app/utils/health_monitor.py`

```python
# 定时任务（每5分钟）:
# 1. 检查每个 Collector 的 health_check()
# 2. 检查 Redis Stream lag
# 3. 检查 LLM API 状态（最近5分钟错误率）
# 4. 检查每个活跃渠道的最后消息时间
# 异常 → 发 Telegram 告警
```

- [ ] `app/utils/telegram_alert.py` — 告警发送（复用现有的 TG 通知逻辑）

**Day 3：TTL 清理 + 定时任务**

- [ ] 信号过期处理：每分钟扫描 `expires_at < now()`，更新 status = 'expired'
- [ ] 每日凌晨 3:00 生成信号摘要（Top 5 + 方向统计）
- [ ] Raw messages 保留 30 天，之后归档/删除

**Day 4-5：测试 + 修 bug**

- [ ] 端到端测试：添加 TG 群 → 等待真实消息 → 验证信号出现在 Dashboard
- [ ] 压力测试：模拟 1000 条消息涌入，验证 Redis + 引擎处理能力
- [ ] 修复发现的 bug
- [ ] 写 `scripts/deploy_p1.sh` — Phase 1 部署脚本

```
git commit -m "feat: 健康监控 + 告警 + P1完成"
```

### Phase 1 部署

```bash
# 部署到服务器
scp -r signalhive/ trading-server:/opt/signalhive/
ssh trading-server

# 服务器上
cd /opt/signalhive
python3.12 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# 配置 supervisord
cat >> /etc/supervisord.d/signalhive.ini << 'EOF'
[program:signalhive-web]
command=/opt/signalhive/venv/bin/gunicorn -c gunicorn.conf.py "app:create_app()"
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
EOF

supervisorctl reread && supervisorctl update
```

- [ ] 配置 Nginx 反代（选一个端口，比如 5300）
- [ ] 验证：浏览器打开 Dashboard，能看到实时信号
- [ ] 监控告警正常触发

### ✅ Phase 1 完成标准

```
□ 用户通过 Web 添加 TG 群 → 60秒内监听
□ 消息经预筛(10-20%通过) → LLM提取 → 结构化信号
□ Dashboard 展示信号: 币种/方向/评分/原文/TTL
□ 过期信号自动标灰
□ 每日 Top 5 摘要
□ 采集器故障 5 分钟内 TG 告警
□ 服务器部署运行稳定
```

---

## Phase 2 — 策略 + Paper Trade（第5-8周）

### Week 5：策略系统

**Day 1-2：策略模板 + API**

- [ ] `app/models/strategy.py` 已有模型，添加业务逻辑
- [ ] `app/api/strategies.py` — CRUD API
- [ ] 3个内置模板的默认配置（保守/平衡/激进）
- [ ] 策略绑定渠道：选择哪些渠道的信号触发

**Day 3-4：策略创建页面**

- [ ] `app/templates/strategy_create.html`

```
页面流程:
1. 选模板 (保守/平衡/激进/自定义)
2. 预填参数（用户可调整）
3. 选择绑定渠道
4. 设定模拟/实盘模式
5. 激活
```

**Day 5：策略匹配器**

- [ ] `app/engine/matcher.py`

```python
# 新信号进来后:
# 1. 查询所有 active 策略
# 2. 过滤: 信号渠道在策略绑定列表内?
# 3. 过滤: 信号评分 >= 策略的 min_signal_score?
# 4. 过滤: require_consensus? 检查共识度
# 5. 通过 → 传给执行器
```

```
git commit -m "feat: 策略模板 + 匹配器"
```

### Week 6：Paper Trade 执行器

**Day 1-2：执行器核心**

- [ ] `app/engine/executor.py`

```python
# 执行流程:
# 1. 检查持仓限制
# 2. entry_delay > 0 → 延迟队列（Redis delayed task）
# 3. 获取当前价格（ccxt fetch_ticker）
# 4. 计算仓位 = 资金 × position_pct
# 5. Paper 模式: simulate_fill(price, direction)
# 6. 创建 signal_trades 记录 (status=open)
# 7. 设置止盈止损价格
# 8. 发送 TG 通知: "📈 Paper开仓 BTC LONG @68050 | 信号评分82"
```

**Day 3-4：止盈止损监控**

- [ ] 定时任务（每 60 秒）：扫描所有 open 的 paper trades
- [ ] 获取当前价格，检查是否触发 TP 或 SL
- [ ] 触发 → 平仓，计算 PnL，更新记录
- [ ] 发送 TG 通知: "📊 Paper平仓 BTC LONG | PnL +3.2% | 触发止盈"

**Day 5：信号结果回写**

- [ ] 交易关闭后，回写 signal 的 `actual_result` (win/loss)
- [ ] 更新 `source_accuracy` 表

```
git commit -m "feat: Paper Trade 执行器 + 止盈止损"
```

### Week 7：准确率追踪 + 排行榜

**Day 1-2：准确率计算引擎**

- [ ] `app/engine/accuracy_tracker.py`

```python
# 每次信号有结果时:
# 1. 更新 source_accuracy 表
# 2. 30天滚动窗口 + 指数衰减
# 3. 新源(信号<10条)标记为 "数据不足"
# 4. 准确率7天内跌>20% → 告警
```

**Day 3-4：排行榜 API + 页面**

- [ ] `GET /api/leaderboard` — 按准确率/ROI 排序
- [ ] `app/templates/leaderboard.html` — 排行榜页面

```
排行榜:
#1 🏆 @crypto_guru   | 准确率 73% | 平均ROI +4.2% | 信号 45条
#2 🥈 @btc_signals   | 准确率 68% | 平均ROI +2.8% | 信号 82条
#3 🥉 @trade_master  | 准确率 61% | 平均ROI +1.5% | 信号 30条
...
⚠️ @moon_boy        | 准确率 32% | 平均ROI -5.1% | 信号 28条 ← 准确率骤降!
```

**Day 5：信号溯源**

- [ ] 信号详情页添加"查看原文"链接（直接跳转 TG 消息 URL）
- [ ] 显示完整原始消息文本 + 博主信息

```
git commit -m "feat: 准确率追踪 + 排行榜"
```

### Week 8：绩效看板 + 测试

**Day 1-3：Paper Trade 绩效看板**

- [ ] `app/templates/performance.html`

```
绩效面板:
┌──────────────────────────────────┐
│  总 PnL: +$245.30 | 胜率: 62%   │
│  总交易: 34 | 胜: 21 | 负: 13   │
│  最大回撤: -8.3% | 连胜: 5      │
├──────────────────────────────────┤
│  📈 PnL 曲线图 (简单折线图)       │
├──────────────────────────────────┤
│  最近交易:                        │
│  ✅ BTC LONG +3.2% | 4月5日      │
│  ❌ ETH SHORT -2.1% | 4月5日     │
│  ✅ SOL LONG +5.8% | 4月4日      │
└──────────────────────────────────┘
```

- [ ] `GET /api/trades/stats` — 统计 API（PnL、胜率、回撤、夏普比率）
- [ ] 简单图表：用 Chart.js 画 PnL 曲线

**Day 4-5：端到端测试**

- [ ] 完整流程：信号进来 → 匹配策略 → 自动 Paper Trade → 止盈止损 → 更新准确率
- [ ] 修 bug + 部署 Phase 2 到服务器

```
git commit -m "feat: 绩效看板 + P2完成"
```

### ✅ Phase 2 完成标准

```
□ 用户可创建策略（3模板 + 自定义）并绑定渠道
□ 信号自动匹配策略 → Paper Trade 开仓
□ 止盈止损自动监控 + 自动平仓
□ 博主准确率实时追踪 + 排行榜可用
□ 绩效看板: PnL/胜率/回撤/交易历史
□ 信号溯源: 点击看原文
```

---

## Phase 3 — 多平台 + Admin（第9-12周）

### Week 9：Twitter 采集器

- [ ] `app/collectors/twitter_collector.py` — 实现 BaseCollector
- [ ] Twitter API v2 注册 + 配置
- [ ] 流模式 或 轮询模式（60s/次）
- [ ] 渠道管理页面支持添加 Twitter 账号
- [ ] 测试：添加 Twitter 账号 → 信号正常提取

### Week 10：Admin 趋势分析

- [ ] `app/admin/trends.py` — 趋势分析 API
- [ ] Admin Dashboard：趋势热力图
  - X轴: 币种 | Y轴: 时间 | 颜色: 提及频率+方向
- [ ] 共识矩阵：渠道 A vs B vs C 的方向一致性
- [ ] 大盘方向指示器：综合所有渠道的加权方向

### Week 11：Admin 排行榜 + 异常检测

- [ ] Admin 版博主排行榜（全量数据，非单用户视角）
- [ ] 异常检测引擎：
  - 信号量暴增（偏离 3σ）
  - 渠道离线
  - 准确率骤降
- [ ] 异常事件日志 + Telegram 告警

### Week 12：微博 + 集成测试

- [ ] 微博采集器评估（能做就做，不能做就跳过）
- [ ] 全平台集成测试
- [ ] Admin 页面打磨
- [ ] 部署 Phase 3

### ✅ Phase 3 完成标准

```
□ Twitter 信号采集正常
□ Admin 趋势热力图可用
□ Admin 跨渠道共识矩阵可用
□ Admin 博主排行榜可用
□ 异常检测 + 告警正常
```

---

## Phase 4 — 实盘 + 商业化（第13-16周）

### Week 13：实盘执行器

- [ ] `app/engine/executor.py` — 添加 live 模式
- [ ] ccxt 连接 Binance Futures / Bitget USDT-M
- [ ] 实盘安全检查：
  - API Key 只授予交易权限，**绝不** 提币权限
  - 单笔最大金额限制
  - 每日最大亏损限制（触发自动暂停）
  - 最大持仓数限制
- [ ] Paper → Live 切换需二次确认
- [ ] 实盘 TP/SL 用交易所服务端止损单

### Week 14：付费系统

- [ ] 用户层级模型：Free / Basic / Pro / API
- [ ] 功能限制逻辑：
  - Free: 3渠道, 10信号/天, 无交易
  - Basic: 10渠道, 无限信号, Paper Trade
  - Pro: 无限, 实盘, 回放, 排行榜
  - API: 信号流 API + Webhook
- [ ] 支付集成（Stripe 或其他）
- [ ] 试用期逻辑（7天 Pro 试用）

### Week 15：信号回放 + API

- [ ] 信号回放引擎：选历史信号 → 按不同策略模拟 → 展示结果对比
- [ ] 外部 API：
  - `GET /api/v1/signals/stream` — SSE 信号流
  - Webhook 配置：信号触发时 POST 到用户 URL
  - API Key 管理

### Week 16：上线准备

- [ ] 压力测试：模拟 100 用户 + 50 渠道
- [ ] 安全审计：
  - [ ] API Key 加密存储
  - [ ] 输入验证（SQL注入、XSS）
  - [ ] 频率限制
  - [ ] HTTPS + HSTS
- [ ] 合规：免责声明 + 风险提示 + 用户协议
- [ ] 监控 Grafana 面板（可选）
- [ ] 生产环境部署 + 域名配置
- [ ] 准备上线公告

### ✅ Phase 4 完成标准

```
□ 实盘交易可用（含完整风控）
□ 付费系统正常运作
□ 信号回放功能可用
□ API 层可用（SSE + Webhook）
□ 安全审计通过
□ 合规文档就位
□ 生产环境稳定运行
```

---

## 持续运营检查清单

### 每日

- [ ] 检查 Telegram 告警频道，确认无异常
- [ ] 查看信号量是否正常（和昨天比）
- [ ] 检查 LLM API 成本

### 每周

- [ ] 审查博主准确率排行榜变化
- [ ] 检查预筛选通过率（是否需要调整关键词）
- [ ] 审查 LLM 提取质量（抽检 20 条信号）
- [ ] 备份数据库

### 每月

- [ ] 清理过期 raw_messages（>30天）
- [ ] 更新关键词库（新币种、新术语）
- [ ] 审查用户增长和转化率
- [ ] 成本分析（LLM + 服务器 + API）

---

## 快速参考

### 项目路径

```
本地: /Users/hongtou/newproject/signalhive/
服务器: /opt/signalhive/
端口: 5300 (待定)
数据库: signalhive
```

### 关键命令

```bash
# 本地开发
cd /Users/hongtou/newproject/signalhive
source venv/bin/activate
flask run --port 5300

# 服务器部署
ssh trading-server
supervisorctl status signalhive-web signalhive-collector-tg signalhive-engine
supervisorctl restart signalhive-web

# 日志
tail -f /var/log/supervisor/signalhive-web-stdout.log
tail -f /var/log/supervisor/signalhive-engine-stdout.log

# 数据库
mysql -u signalhive -p signalhive
```

### 依赖文档

- [商业提案](SIGNALHIVE_商业提案.md)
- [技术提案](SIGNALHIVE_技术提案.md)
- [产品提案](SIGNALHIVE_PROPOSAL.md)
