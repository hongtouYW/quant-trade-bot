# Flash Quant - 产品需求文档 (PRD)

> **作者**: 📋 John (Product Manager)
> **日期**: 2026-04-08
> **版本**: v0.1
> **依赖**: project-brief.md, market-research.md
> **下游**: architecture.md, test-design.md, tech-spec.md

---

## 📖 文档说明

本文档是 Flash Quant 项目的**单一需求事实来源 (Single Source of Truth)**。
所有架构决策、测试设计、代码实现都必须**可追溯回本文档的某条 FR/NFR**。

**符号约定**:
- `FR-XXX` = Functional Requirement (功能需求)
- `NFR-XXX` = Non-Functional Requirement (非功能需求)
- `BR-XXX` = Business Rule (业务规则,不可商榷)
- `AC-XXX` = Acceptance Criteria (验收标准)

---

## 🎯 1. 产品愿景

### 1.1 一句话定位
> **Flash Quant 是一个基于多周期量价异动检测的爆发性信号自动交易系统,通过严格的过滤器和渐进式资金管理,在高杠杆短线市场中实现稳健盈利。**

### 1.2 解决的核心问题
| 痛点 | 现状 | Flash Quant 方案 |
|---|---|---|
| 错过爆发性机会 | 人工监控 200+ 币种不可能 | 自动 30s 扫描 + WebSocket 实时 |
| 假信号亏损 | 80% 爆量是插针/操纵 | 三层过滤器 (Wick + CVD + Funding) |
| 高杠杆爆仓 | 100x 一次反向就清零 | 20x 分级 + 硬性止损 + 多层断路器 |
| 心理崩溃 | 直接重仓导致低点关停 | 4 阶段渐进式资金 0→500→2000→10000 |
| 黑天鹅 | 闪崩瞬间爆仓 | 异常波动熔断 + 时段控制 |

### 1.3 不做什么 (Out of Scope)
- ❌ 现货交易 (只做永续合约)
- ❌ 多用户 SaaS (个人专用)
- ❌ Telegram 机器人 (Phase 1 不做,后期可加)
- ❌ 回测平台 (用 Phase 1 模拟盘代替)
- ❌ 复用现有 v6/quant_bot 任何代码
- ❌ 100x 杠杆 (业务规则禁止)
- ❌ Tier D 垃圾币 (业务规则禁止)

---

## 👤 2. 目标用户

### 2.1 用户画像
- **唯一用户**: Hongtou
- **本金**: 10,000 USDT
- **背景**: 已有 v6/quant_bot/signal_trader 项目运维经验
- **技术能力**: 能看代码,能改参数,有 Python/Linux 基础
- **风险偏好**: 单笔可承受 -50% ROI,总账户 -30% 紧张
- **心理特征**: 资金越大压力越大 → 必须渐进式

### 2.2 使用场景

**场景 A: 监控**
> Hongtou 早上打开 Web Dashboard,查看昨日所有信号 / 触发的交易 / 当日 P&L / 各 Tier 表现。如果发现异常 (如某 Tier 胜率突然下降),手动暂停或调参。

**场景 B: 实时**
> 系统检测到 BTC 5min 量比 7x + 涨幅 2.3% + Wick/CVD/Funding 三过滤通过 → 自动开 20x 多单 300U → Dashboard 实时显示 → 30 分钟后触发 +30% ROI 阶梯止盈,平 60% 仓位。

**场景 C: 风控**
> 系统连续 3 笔亏损 → 自动将下一笔仓位减半 (300U → 150U) → 第 5 笔亦亏 → 暂停 4 小时 → Dashboard 红色告警。

**场景 D: 黑天鹅**
> SEC 公告导致 ETH 5min 跌 6% → 异常波动熔断触发 → 全系统暂停 30 分钟 → 现有持仓保留,新信号拒绝。

---

## 📋 3. 功能需求 (Functional Requirements)

### 3.1 信号扫描模块

#### FR-001: Tier 1 极速爆破扫描
**Why**: 捕捉 30min-2h 内的爆发性短线机会,这是项目的核心特色。

**需求**:
- 每 30 秒扫描一次目标币种
- 监控范围: Binance 永续合约 Tier A + B (~50 个币)
- 触发条件 (全部满足):
  - 5min 量比 ≥ 5x (vs 近 20 根均量)
  - 5min 价格变化 |Δ| ≥ 2%
  - 1h OI 增长 ≥ 8%
  - Taker Buy/Sell Ratio: 多 ≥ 1.5 / 空 ≤ 0.67
  - 必须通过 Wick + CVD + Funding 三层过滤
- 时段限制: UTC 8:00-22:00

**AC**:
- 系统每 30s 完成一次全量扫描,延迟 < 5s
- 信号触发后 500ms 内调用下单接口
- 扫描日志可在 Dashboard 查看

---

#### FR-002: Tier 2 趋势爆破扫描
**Why**: 捕捉 15min 周期的趋势启动信号,持仓 2-8h,补充 Tier 1 的覆盖。

**需求**:
- 每 60 秒扫描一次
- 监控范围: Tier A + B (~50 个币)
- 触发条件 (全部满足):
  - 15min MACD 柱状图首次转色 (变正/变负)
  - 15min RSI 突破 65 (多) 或跌破 35 (空)
  - 15min EMA9 上穿/下穿 EMA21
  - 1h 成交量 > 24h 均量 × 1.5
  - 通过三层过滤
- 时段限制: 全天

---

#### FR-003: Tier 3 1H 方向扫描
**Why**: 长周期方向单,持仓 6-20h,捕捉中线趋势。

**需求**:
- 每 1H 整点扫描 (利用 K线收盘)
- 监控范围: Tier A 蓝筹 (~15 个币)
- 评分系统 (满分 100 + 25 加分):
  - RSI 评分 (0-25)
  - MA 评分 (0-25)
  - Volume 评分 (0-25)
  - Position 评分 (0-25)
  - MACD 加分 (0-10)
  - ADX 加分 (0-10)
  - BB 加分 (0-5)
- 触发条件: 综合评分 ≥ 75
- 注: 与 v6 类似但**完全独立实现**,不复用代码

---

### 3.2 信号过滤模块

#### FR-010: 反插针过滤器 (Wick Filter)
**Why**: Mary 调研显示 80% 爆量是插针,这是项目盈亏分水岭。

**需求**:
```python
def wick_filter(open, high, low, close) -> bool:
    body = abs(close - open)
    upper_wick = high - max(open, close)
    lower_wick = min(open, close) - low
    total = body + upper_wick + lower_wick
    if total == 0:
        return False
    body_ratio = body / total
    return body_ratio >= 0.55
```

**AC**:
- 单元测试覆盖 10 种 K 线形态 (大阳/大阴/十字/上影/下影/锤头/吊颈等)
- 测试用例必须包含已知插针历史 K 线

---

#### FR-011: CVD 过滤器
**Why**: 验证主动买卖盘真实性,过滤抽流动性假涨。

**需求**:
- 实时维护每个币种的 CVD (Cumulative Volume Delta) 时序
- 通过 Binance WebSocket `aggTrade` 流计算
- CVD 计算逻辑:
  ```python
  for trade in agg_trades:
      if trade.is_buyer_maker:
          cvd -= trade.quantity  # 主动卖
      else:
          cvd += trade.quantity  # 主动买
  ```
- 过滤逻辑:
  ```python
  def cvd_filter(price_series, cvd_series, lookback=20) -> bool:
      price_high = max(price_series[-lookback:])
      cvd_high = max(cvd_series[-lookback:])
      tolerance = 0.1
      return (price_series[-1] >= price_high * (1 - tolerance) and
              cvd_series[-1] >= cvd_high * (1 - tolerance))
  ```

**AC**:
- WebSocket 断线 5s 内自动重连
- CVD 序列内存维护,Redis 持久化备份
- 单元测试验证 CVD 计算正确性

---

#### FR-012: 资金费率过滤器
**Why**: Mary 调研显示极端费率币种容易被反向收割。

**需求**:
- 每 30s 拉取所有目标币种的实时资金费率
- 过滤规则: `|funding_rate| < 0.0008` (0.08%)
- 缓存 8h 内费率历史,识别"费率快速变化"币种

---

### 3.3 交易执行模块

#### FR-020: 模拟下单器 (Paper Executor)
**Why**: Phase 1 必须用模拟盘验证,不能直接实盘。

**需求**:
- 接收信号 → 模拟下单 → 写入 `trades` 表 (mode='paper')
- 价格使用 Binance 实时市价
- 滑点模拟: 多单 +0.05%, 空单 -0.05%
- 手续费模拟: Taker 0.05% (Binance VIP 0)
- 持仓管理: 内存 + DB 双写

**AC**:
- 1000 笔模拟交易后,DB 数据完整无丢失
- 与实盘下单器接口完全一致 (策略代码无需改)

---

#### FR-021: Binance 实盘下单器
**Why**: Phase 2+ 实盘核心。

**需求**:
- 使用 ccxt 库连接 Binance USDT-M Futures
- 下单类型: 限价单 (优先) → 失败回退市价单
- 必须使用币安服务端止损单 (`STOP_MARKET`, `workingType=MARK_PRICE`)
- 阶梯止盈通过 `Reduce-Only` 限价单实现
- 所有订单状态实时同步到 DB

**AC**:
- API 失败重试 3 次,失败后 TG/Dashboard 告警
- 下单延迟监控 (P50/P95/P99)
- 订单状态轮询每 5s 一次

---

### 3.4 风险控制模块

#### FR-030: 单笔风控
**Why**: 防止单笔失控亏损。

**需求**:
- 硬性止损: -10% ROI (无论 Tier)
- 必须在下单同时挂止损单 (服务端,不依赖本地)
- 仓位上限: 300 U / 笔
- 最大持仓数: 5 笔同时
- 止损单价格 = 入场价 × (1 ± stop_pct / leverage)

---

#### FR-031: 连亏断路器
**Why**: 防止策略失效时连续放血。

**需求**:
- 连亏 3 笔 → 下一笔仓位减半 (300 → 150)
- 连亏 5 笔 → 暂停 4 小时
- 连亏 8 笔 → 暂停 24 小时 + 强制人工审核
- 计数窗口: 最近 24h 内的交易
- 计数重置: 出现 1 笔盈利后清零

---

#### FR-032: 时段断路器
**Why**: 防止单时间段亏损过大。

**需求**:
- 单日亏损 ≥ 3% (300U on 10000U) → 暂停当日剩余时间
- 单周亏损 ≥ 8% (800U) → 暂停 24 小时
- 单月亏损 ≥ 15% (1500U) → 暂停 72 小时 + 人工审核
- 暂停期间已有持仓**不强平**, 但不开新单

---

#### FR-033: 异常波动熔断 (黑天鹅)
**Why**: Mary 提示的黑天鹅风险,必须有保护。

**需求**:
- 监控全市场 5min K线波幅
- 任意 Tier A 币种 5min 波幅 > 5% → 全系统熔断 30 分钟
- 监控指标: BTC + ETH 共同波动 → 强信号
- 熔断期间禁止新单,持仓保留

---

#### FR-034: 同币冷却
**Why**: 防止单一币种重复交易增加风险。

**需求**:
- 平仓后,该币种 2 小时内不再开新单
- 适用于所有 Tier
- 冷却期可在 Dashboard 手动重置

---

### 3.5 Web Dashboard

#### FR-040: 实时监控面板
**Why**: 用户唯一的人机交互入口,必须直观。

**需求**:

**首页 (/)**:
- 总资产 + 当日 P&L + 当月 P&L
- 当前持仓表格 (币种/方向/杠杆/入场/当前/ROI/止损)
- 当前断路器状态 (绿/黄/红)
- 各 Tier 今日信号统计
- 资金曲线图 (近 30 天)

**信号页 (/signals)**:
- 所有触发的信号 (含被过滤掉的,标注原因)
- 筛选: Tier / 币种 / 时段 / 是否成交
- 每条信号可点击查看详情 (K 线 / 过滤器结果 / 决策路径)

**交易页 (/trades)**:
- 历史交易记录
- 胜率 / 盈亏比 / 最大回撤 统计
- 按 Tier / 币种 分组分析

**风控页 (/risk)**:
- 当前断路器状态详情
- 连亏记录
- 手动暂停/恢复按钮
- 紧急平仓所有持仓按钮 (二次确认)

**配置页 (/config)**:
- 各 Tier 参数实时调整
- 黑名单管理
- API Key 配置 (加密存储)

---

#### FR-041: 实时数据推送
**Why**: 用户希望看到实时变化,不是手动刷新。

**需求**:
- WebSocket 推送 (Server-Sent Events 或 WebSocket)
- 推送内容: 持仓变化 / 新信号 / 价格更新 / 风控告警
- 推送频率: 持仓 1s, 价格 5s, 信号实时

---

### 3.6 数据持久化

#### FR-050: 数据库设计
**Why**: 完全独立,不污染现有数据库。

**需求**:
- MySQL 实例: 复用 139.162.31.86 (新服务器) 或新 Vultr Tokyo
- Schema 名: `flash_quant_db` (完全独立)
- 主要表:
  - `signals` (扫描出的信号)
  - `trades` (交易记录)
  - `positions` (当前持仓)
  - `daily_stats` (日统计)
  - `circuit_breaker` (断路器状态)
  - `audit_log` (操作审计)
  - `kline_cache` (K 线缓存,可选)
  - `cvd_cache` (CVD 缓存,可选)

**AC**:
- 所有表必须有 `created_at`, `updated_at` 时间戳
- 关键操作必须写 audit_log
- 定期备份策略 (每日全量 + 每小时增量)

---

## 🚦 4. 非功能需求 (Non-Functional Requirements)

### NFR-001: 性能
- 信号扫描延迟: P99 < 5s
- 信号触发到下单: P99 < 500ms
- Dashboard 页面加载: P99 < 2s
- WebSocket 推送延迟: P99 < 1s
- 系统支持单 24h 内 1000+ 笔交易

### NFR-002: 可用性
- 系统年可用率 ≥ 99.5%
- 单次异常恢复时间 ≤ 5 分钟
- 持仓状态必须持久化,重启后可恢复
- 健康检查接口 `/health`

### NFR-003: 安全性
- API Key 必须加密存储 (使用现有 ENCRYPTION_MASTER_KEY 或新建)
- Dashboard 必须有登录认证 (Session 或 JWT)
- 紧急操作 (强平/暂停) 必须二次确认
- 所有写操作记录 audit_log

### NFR-004: 可观测性
- 结构化日志 (JSON 格式)
- 关键指标 Prometheus exporter
- 错误自动告警 (Phase 2 加 TG 通知)
- 24h 内日志保留

### NFR-005: 可维护性
- 代码必须有单元测试 (覆盖率 ≥ 70%)
- 关键模块必须有集成测试
- 配置外置 (`.env` 或 `config.yaml`)
- 详尽的 README 和部署文档

---

## 🚫 5. 业务规则 (Business Rules - 不可商榷)

### BR-001: 杠杆上限
**规则**: 任何情况下杠杆 ≤ 20x。
**Why**: Mary 调研 + Hongtou 心理承受度共同确认。
**违反后果**: 系统拒绝下单,记录 audit_log。

### BR-002: Tier D 黑名单
**规则**: 24h 成交量 < $10M 的币永不交易。
**Why**: 95% 假信号率,1万U不值得赌山寨。
**违反后果**: 扫描器自动跳过。

### BR-003: 新币黑名单
**规则**: 上市 < 7 天的币永不交易。
**Why**: 数据不足,波动剧烈。
**违反后果**: 扫描器自动跳过。

### BR-004: 渐进式资金
**规则**: 必须按 Phase 1 → 2 → 3 → 4 顺序投入,不允许跳跃。
**Why**: 心理承受度 + 策略验证。
**违反后果**: 配置文件硬编码,代码层面不允许跳过。

### BR-005: 单笔仓位上限
**规则**: 单笔保证金 ≤ 300 U (即使阶段升级)。
**Why**: 即使本金 1万U,单笔风险也不能超过 1.5%。
**违反后果**: 下单接口拒绝。

### BR-006: 止损必须服务端
**规则**: 所有持仓必须有 Binance 服务端止损单。
**Why**: 本地策略可能崩溃,服务端止损是最后防线。
**违反后果**: 开仓后 5s 内未挂止损 → 强平。

### BR-007: 时段限制
**规则**: Tier 1 仅在 UTC 8:00-22:00 运行。
**Why**: 深夜流动性差,假信号飙升。
**违反后果**: 扫描器自动暂停。

### BR-008: 周末减仓
**规则**: 周六周日所有 Tier 仓位减半。
**Why**: Mary 调研: 周末流动性下降,假突破比例上升。
**违反后果**: 仓位计算模块自动 ×0.5。

---

## ✅ 6. Phase 1 验收标准 (Acceptance Criteria)

### AC-Phase1: MVP 模拟盘验收 (硬性 5 项)

**必须全部通过才能进入 Phase 2 实盘:**

| # | 指标 | 通过线 | 测量方法 |
|---|---|---|---|
| AC-1 | 过滤器有效性 | 加过滤 vs 不加,胜率提升 ≥ 30% | A/B 对照 |
| AC-2 | 信号数量 | Tier 1 日均 10-20 信号 | 14 天统计 |
| AC-3 | 整体胜率 | ≥ 55% | 200+ 笔交易 |
| AC-4 | 盈亏比 | ≥ 1.5 | 200+ 笔交易 |
| AC-5 | 信号延迟 | 99% 信号 < 500ms | 系统监控 |

**任何一项不达标 → 不进入 Phase 2,需要调参重测。**

---

## 📅 7. Phase 路线图

### Phase 0: 规划 (当前)
- [x] project-brief.md
- [x] market-research.md
- [x] prd.md ← 当前
- [ ] architecture.md
- [ ] test-design.md
- [ ] tech-spec.md

### Phase 1: MVP 模拟盘 (Week 1-2)
- 服务器搭建 (Vultr Tokyo)
- DB + Nginx + Supervisord
- WebSocket 数据接入
- Tier 1 + 反插针过滤器
- Paper Executor
- Dashboard MVP
- 跑 14 天 / 200 笔信号

### Phase 2: 实盘小额 (Week 3-4)
- Tier 2 + Tier 3 上线
- CVD + Funding 过滤器
- Binance 实盘下单
- 完整风控引擎
- 500 U 实盘验证

### Phase 3: 实盘中额 (Month 2)
- 阶梯止盈优化
- Trailing stop 优化
- 2000 U 实盘
- 月化 ≥ 10% 验证

### Phase 4: 全额运行 (Month 3+)
- 10000 U 全额投入
- 持续监控
- 策略迭代

---

## ❓ 8. 待决问题 (Open Questions)

| # | 问题 | 待回答者 |
|---|---|---|
| Q1 | API Key 用现有 quant_bot 的还是新建? | Hongtou |
| Q2 | Dashboard 是否需要登录? (仅你一个人用) | Hongtou |
| Q3 | 新服务器 SSH 端口? | Hongtou |
| Q4 | 域名? (如 flash.xxx.com 或直接 IP) | Hongtou |
| Q5 | 数据库实例: 复用 139.162.31.86 还是新建? | Hongtou |

**这些问题不阻塞后续设计**, Architecture 阶段可以先用占位符。

---

## 🎤 John 签字

> "📋 **PRD 完成。**
>
> 这份文档把 Mary 的调研全部转化成了可执行需求,所有 BR 都是不可商榷的硬规则。
>
> 我特别强调几点:
> 1. **AC-Phase1 是真正的 Gate**, 不是橡皮图章 — 不达标就不能上实盘
> 2. **8 条 BR 是法律**, Winston 在架构设计时必须把它们硬编码
> 3. **5 个 Open Questions** 不阻塞但要在实施前回答
>
> 现在交棒给 **Winston** 设计架构! 🏗️"

---

**文档版本**: v0.1
**状态**: ✅ 已完成
**下一步**: 🏗️ Winston 撰写 Architecture (`architecture.md`)
