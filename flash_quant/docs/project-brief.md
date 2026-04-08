# Flash Quant - 爆发性信号量化交易系统

> **项目简报 (Project Brief)**
> 创建日期: 2026-04-08
> 创建者: Hongtou + BMad Team (Party Mode 共识)
> 状态: 📋 规划中 (Phase 0)

---

## 🎯 项目愿景

构建一个**爆发性信号扫描 + 自动交易系统**,通过多周期量价异动检测,捕捉加密永续合约市场的短期爆发机会,实现**资金渐进式稳健增长**。

### 一句话定位
> "**渐进式爆破量化系统** - 心理、资金、策略三方同步成长。"

### 核心理念
- **不是赌博工具**,是机构级短线策略
- **数据驱动 + 风险优先**
- **模拟先行,渐进投入**
- **完全独立**,不依赖现有任何项目

---

## 👤 用户背景

- **用户**: Hongtou
- **本金**: 10,000 USDT
- **现有系统**: trading-saas v6 (港股式趋势策略)、quant_bot、signal_trader、signalhive
- **沟通语言**: 中文
- **风险偏好**: 渐进式 — 单笔可承受 -50% ROI,但总账户回撤 -30% 会紧张

---

## 💡 核心需求

### 必须 (Must Have)
1. ✅ 监控 5min - 1H 周期的量价波动
2. ✅ 检测爆发性信号(高量比 + 趋势启动)
3. ✅ 支持 50x - 100x 杠杆交易(实际采用 10x-20x 稳健级)
4. ✅ 短期持仓 (1-2 小时极速 / 2-8 小时趋势 / 6-20 小时方向)
5. ✅ 支持 Binance 合约自动开单
6. ✅ Web Dashboard 实时监控
7. ✅ 模拟盘 (Paper Trading) 模式
8. ✅ 完全独立项目(独立 DB / 独立服务器)

### 不需要 (Out of Scope)
- ❌ Telegram 通知 (暂不需要)
- ❌ 复用现有 v6/quant_bot 代码
- ❌ 多用户 SaaS 模式 (个人使用)
- ❌ 100x 杠杆 (风险过大,降为 20x)

---

## 💰 资金管理设计

### 渐进式 4 阶段投入

| 阶段 | 资金 | 时长 | Gate 条件 |
|---|---|---|---|
| **Phase 1: 模拟盘** | 0 U (虚拟) | 2 周 | 200+ 笔信号, 胜率 ≥ 55% |
| **Phase 2: 小额实盘** | 500 U | 2 周 | 净盈亏 ≥ 0, 滑点 < 0.1% |
| **Phase 3: 中额实盘** | 2,000 U | 1 个月 | 月化 ≥ 10%, 最大回撤 < 15% |
| **Phase 4: 全额实盘** | 10,000 U | 长期 | 持续监控,触发熔断暂停 |

### 单笔交易参数

```yaml
单笔保证金: 300 U
杠杆: 20x (爆发型) / 15x (趋势型) / 10x (方向型)
单笔名义价值: 6000 U (300 × 20)
单笔最大亏损: 150 U (-50% ROI = -2.5% 价格反向)
单笔最大盈利: 阶梯止盈 +30% / +60% / +120% ROI
最大同时持仓: 5 笔
保留现金: ≥ 70% 总资金
```

### 风控刹车 (断路器)

```yaml
单日亏损上限: -3% (-300 U) → 暂停当日交易
单周亏损上限: -8% (-800 U) → 暂停 24 小时
单月亏损上限: -15% (-1500 U) → 暂停 72 小时 + 人工审核
连亏 3 笔: 仓位减半
连亏 5 笔: 暂停 4 小时
同币种冷却: 平仓后 2 小时内不再开
新币黑名单: 上市 < 7 天的币不交易
资金费率过滤: |费率| > 0.1% 的币不开单
```

---

## 🎯 三层策略架构

### Tier 1: 极速爆破单 (Scalper)
```yaml
扫描周期: 30 秒
触发条件:
  - 5min 量比 ≥ 5x (近 20 根均量)
  - 5min 涨跌幅 ≥ 2%
  - 1H OI 增长 ≥ 8%
  - Taker Buy/Sell Ratio: 多 > 1.5 / 空 < 0.67
持仓时间: 30min - 2h
杠杆: 20x
止损: -10% ROI (-0.5% 价格)
止盈: 阶梯 +15% / +30% / +60% ROI 分批
仓位: 300 U
监控范围: Binance 永续合约 Top 100 (按 24h 成交量)
```

### Tier 2: 趋势爆破单 (Trend Breaker)
```yaml
扫描周期: 1 分钟
触发条件:
  - 15min MACD 柱状图首次转色
  - 15min RSI 突破 65 (多) 或跌破 35 (空)
  - 15min EMA9 上穿/下穿 EMA21
  - 1H 成交量 > 24H 均量 × 1.5
持仓时间: 2h - 8h
杠杆: 15x
止损: -10% ROI
止盈: trailing stop (6% 启动 + 3% 回撤)
仓位: 300 U
监控范围: Binance 永续 Top 50 中盘币
```

### Tier 3: 1H 方向单 (Direction)
```yaml
扫描周期: 1H 整点
触发条件:
  - 完整技术指标评分体系 (RSI + MA + Vol + Position + MACD + ADX + BB)
  - 评分 ≥ 75 (满分 100+ 加分项)
持仓时间: 6h - 20h
杠杆: 10x
止损: -10% ROI
止盈: trailing stop
仓位: 300 U
监控范围: Binance 永续主流 15 币
注: 与现有 v6 策略类似但完全独立实现,不复用代码
```

---

## 🛡 信号过滤器 (核心生存技能)

### 1. 反插针过滤器 (Wick Filter)
```python
def is_healthy_candle(open, high, low, close):
    body = abs(close - open)
    upper_wick = high - max(open, close)
    lower_wick = min(open, close) - low
    total = upper_wick + lower_wick + body
    return body / total >= 0.6  # 实体占比 ≥ 60%
```
**作用**: 过滤掉长上下影线的假突破 K 线 (插针操纵)。

### 2. CVD 过滤器 (Cumulative Volume Delta)
```python
def is_real_breakout(price_series, cvd_series):
    price_high = max(price_series[-20:])
    cvd_high = max(cvd_series[-20:])
    # 价格创新高时, CVD 必须同步创新高
    return price_series[-1] >= price_high and cvd_series[-1] >= cvd_high
```
**作用**: 验证主动买卖盘真实性,过滤抽流动性假涨。

### 3. 资金费率过滤器
```python
def is_safe_funding(funding_rate):
    return abs(funding_rate) < 0.001  # 0.1%
```
**作用**: 避开费率异常的"被收割"币种。

### 预期效果
| 指标 | 不加过滤器 | 加过滤器 |
|---|---|---|
| 日均信号数 | 50+ | 8-15 |
| 假信号率 | ~85% | ~40% |
| 胜率 | ~35% | ~60% |
| 月化预期 | -20% | +15-30% |

---

## 🏗 技术架构

### 项目目录
```
/Users/hongtou/newproject/flash_quant/
├── docs/
│   ├── project-brief.md       (本文档)
│   ├── prd.md                  (产品需求文档 - 待John撰写)
│   ├── architecture.md         (系统架构 - 待Winston撰写)
│   ├── tech-spec.md            (技术规格 - 待Amelia撰写)
│   ├── test-design.md          (测试设计 - 待Murat撰写)
│   └── market-research.md      (市场调研 - 待Mary撰写)
├── app.py                      (Flask 入口)
├── scanner/
│   ├── tier1_scalper.py        (30s 极速扫描)
│   ├── tier2_trend.py          (1min 趋势扫描)
│   └── tier3_direction.py      (1H 方向扫描)
├── filters/
│   ├── wick_filter.py          (反插针过滤)
│   ├── cvd_filter.py           (CVD 过滤)
│   └── funding_filter.py       (费率过滤)
├── executor/
│   ├── binance_executor.py     (Binance 实盘)
│   └── paper_executor.py       (模拟盘)
├── risk/
│   └── risk_manager.py         (风控引擎)
├── models/
│   ├── signal.py
│   ├── trade.py
│   └── daily_stat.py
├── dashboard/
│   ├── templates/
│   └── static/
├── data/
│   └── kline_cache.py          (实时 K 线缓存)
├── ws/
│   └── binance_ws.py           (WebSocket 订阅)
├── config/
│   └── settings.py
├── tests/
└── requirements.txt
```

### 技术栈
- **语言**: Python 3.11+
- **框架**: Flask + SQLAlchemy
- **数据库**: MySQL (独立 schema `flash_quant_db`)
- **交易所**: ccxt (Binance 主用)
- **数据**: Binance WebSocket 实时流 + REST 补充
- **计算**: NumPy + TA-Lib + Pandas
- **缓存**: Redis (可选, deque + memory 优先)
- **进程管理**: Supervisord
- **Web**: Nginx + Gunicorn
- **部署**: Vultr Tokyo High Frequency

### 服务器配置
```yaml
提供商: Vultr
类型: Tokyo High Frequency
配置: 2 CPU / 4GB RAM / 80GB NVMe
价格: $24/月
机房: 东京 (距离 Binance 亚太节点 < 5ms)
预期延迟: Binance API ~30ms (vs 老服务器 250ms+)
SSH 端口: 待定 (建议非 22)
```

### 数据库
```sql
-- 完全独立, 不与现有 trading_saas 共享
CREATE DATABASE flash_quant_db;

-- 主要表
- signals          (扫描出的信号记录)
- trades           (交易记录)
- positions        (当前持仓)
- daily_stats      (日统计)
- circuit_breaker  (断路器状态)
- audit_log        (操作审计)
```

---

## 📅 开发路线图

### Phase 0: 规划阶段 (当前)
- [x] 需求讨论 (Party Mode 完成)
- [x] 项目简报 (本文档)
- [ ] PRD 撰写 (John)
- [ ] 市场调研 (Mary)
- [ ] 架构设计 (Winston)
- [ ] 技术规格 (Amelia)
- [ ] 测试设计 (Murat)

### Phase 1: MVP 模拟盘 (Week 1-2)
- [ ] Vultr Tokyo 服务器搭建
- [ ] MySQL + Nginx + Supervisord 基础环境
- [ ] Binance WebSocket 实时数据接入
- [ ] Tier 1 扫描器 + 反插针过滤器
- [ ] Paper Trader 模拟下单
- [ ] Web Dashboard (实时信号 + 模拟 P&L)
- [ ] 跑满 200 笔信号验证

### Phase 2: 实盘小额 (Week 3-4)
- [ ] Tier 2 + Tier 3 扫描器上线
- [ ] CVD 过滤器 + 资金费率过滤器
- [ ] Binance 实盘下单接入
- [ ] 风控断路器引擎
- [ ] 500 U 实盘验证
- [ ] 滑点 / 手续费 / API 稳定性检测

### Phase 3: 实盘中额 (Month 2)
- [ ] 完整阶梯止盈逻辑
- [ ] Trailing stop 优化
- [ ] 2000 U 实盘验证
- [ ] 月化 ≥ 10% 验证

### Phase 4: 全额运行 (Month 3+)
- [ ] 10000 U 全额投入
- [ ] 持续监控 + 优化
- [ ] 策略迭代 (基于实盘数据)

---

## 📊 成功指标 (KPIs)

### Phase 1 (模拟盘) 验收
- ✅ 200+ 笔信号触发
- ✅ 模拟胜率 ≥ 55%
- ✅ 模拟盈亏比 ≥ 1.5
- ✅ 系统稳定运行 14 天无崩溃
- ✅ 信号延迟 < 500ms

### Phase 2-4 (实盘) 验收
- ✅ 实盘月化 ≥ 10%
- ✅ 最大回撤 < 15%
- ✅ 单笔滑点 < 0.1%
- ✅ API 成功率 > 99.5%
- ✅ 断路器从未误触

---

## ⚠️ 已识别风险

| 风险 | 影响 | 缓解措施 |
|---|---|---|
| API 限速 | 高 | WebSocket 优先,REST 备用 |
| 滑点过大 | 高 | Tokyo 机房 + 限价单优先 |
| 假信号率高 | 高 | 三层过滤器 (Wick + CVD + Funding) |
| 心理压力导致误操作 | 中 | 渐进式资金投入 |
| 极端行情爆仓 | 高 | 单笔止损硬性 + 日/周/月断路器 |
| 服务器宕机 | 中 | 健康检查 + 自动重启 + 持仓状态持久化 |
| 策略过拟合 | 中 | Walk-Forward 回测 + 实盘验证 |

---

## 🎤 团队签字 (Party Mode 共识)

| 角色 | 名字 | 状态 |
|---|---|---|
| 📋 PM | John | ✅ 已签字 |
| 🏗️ 架构师 | Winston | ✅ 已签字 |
| 📊 分析师 | Mary | ✅ 已签字 |
| 🧪 TEA | Murat | ✅ 已签字 |
| 💻 Dev | Amelia | ✅ 已签字 |
| 🧙 Master | BMad Master | ✅ 已签字 |

---

## 📌 后续步骤

1. ✅ **本文档** (project-brief.md) - 已完成
2. ⏳ **市场调研** (Mary): 验证爆量事件假设, 输出 `market-research.md`
3. ⏳ **PRD** (John): 详细产品需求, 输出 `prd.md`
4. ⏳ **架构设计** (Winston): 系统架构图 + 技术决策, 输出 `architecture.md`
5. ⏳ **测试设计** (Murat): 测试策略 + Gate 条件, 输出 `test-design.md`
6. ⏳ **技术规格** (Amelia): 实现细节 + API 设计, 输出 `tech-spec.md`
7. ⏳ **代码实现**: Phase 1 MVP 启动

---

**联系**: Hongtou
**项目**: Flash Quant
**版本**: v0.1 (Project Brief)
**最后更新**: 2026-04-08
