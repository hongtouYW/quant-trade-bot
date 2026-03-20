# QuantBot 开发日志 & 差异分析

> 对比文档: `ultimate_quant_strategy_dev_spec.md` (vFinal)
> 更新日期: 2026-03-19

---

## 已完成功能 ✅

### 基础架构 (Phase 1)
- [x] ccxt Binance USDT永续 REST 集成 (`exchange_client.py`)
- [x] 多周期K线缓存 (1m/5m/15m/1h) + TTL自动刷新 (`ohlcv_cache.py`)
- [x] WebSocket实时1m K线推送 (`websocket_feed.py`): 自动订阅活跃池, 断线重连, 实时更新cache
- [x] 账户数据流 (`account_feed.py`): 后台线程2~5秒刷新余额/持仓 (Spec §5.1/§5.3)
- [x] 指标计算库: EMA/SMA/ATR/ATRP/RSI/ADX/BB/ROC/Volume (`calc.py`)
- [x] 全局常量定义 (`constants.py`): 方向/状态/策略/平仓原因/风控默认值 (Spec §20)
- [x] YAML配置加载 (`config.py` + `config.yaml`)
- [x] Flask Web Dashboard (`dashboard.py` + `index.html`)
- [x] 自动启动 (supervisord: `quant-bot.ini`)
- [x] SQLite 数据库持久化 (`app/db/`)
- [x] 日志模块 (`monitoring/logger.py`): 控制台+文件轮转+错误分流+交易日志 (Spec §20)

### 候选池过滤 (Spec §7)
- [x] Layer 1 硬性过滤: 24h成交量>=8M, OI>=3M, 点差<=0.04%, 上市>=14天, 资金费<=0.0075
- [x] Layer 2 波动率过滤: ATRP 0.25%~4.5% (最优0.45%~2.5%)
- [x] Layer 3 流动性过滤: 1h成交量检查, 12根5m异常上下影线过滤
- [x] Layer 4 活跃池: 评分排序, 保留top 12个

### 趋势评分 & 市场状态 (Spec §8)
- [x] 动量层 0-60: 5m ROC(15), EMA 7/21/55排列(15), 成交量(10), ATRP(10), 买卖方向(10)
- [x] 质量层 0-40: 1H EMA 20/50/200排列(15), ADX(10), EMA20距离(5), 回踩恢复(5), 波动稳定性(5)
- [x] 等级: A>=78, B>=70, C>=62, D<62
- [x] 市场状态: TRENDING / RANGING / EXTREME

### 方向判定 (Spec §9)
- [x] 做多: close > EMA20 > EMA50 (>EMA200) + ADX>=22 + slope>0
- [x] 做空: close < EMA20 < EMA50 (<EMA200) + ADX>=22 + slope<0
- [x] EMA200不足200根时fallback到EMA50判定

### 入场策略 (Spec §10)
- [x] Setup A: 趋势回踩 (15m回踩EMA21 + 反转K线 + 成交量恢复)
- [x] Setup B: 压缩突破 (20根<3%区间 + 突破 + 成交量>1.3x)
- [x] 优先级: 回踩 > 突破, 同时触发取高分者

### 1m精细确认 (Spec §11)
- [x] 4条件取2: 5根高点突破, EMA9, 成交量>1.2x, higher low
- [x] 最大等待3根1m K线 (信号3分钟过期)
- [x] 入场偏移>0.35 ATR放弃

### 假突破过滤 (Spec §12)
- [x] 5条件: 上影线, 成交量不足, 1m回撤, 阻力位接近, ATR扩张
- [x] 命中>=2条则拒绝
- [x] 假突破2次同方向触发冷却

### 仓位管理 (Spec §13)
- [x] 固定风险: risk_amount = equity * 0.004
- [x] notional = risk_amount / (stop_distance / entry_price)
- [x] RANGING市场风险减半至0.2%
- [x] 单笔最大名义值 <= 30% equity
- [x] 最小下单量检查: notional < 5U 放弃 (Spec §13.4)
- [x] 总margin <= 90% (Spec §13.4)

### 止损/止盈/移动保护 (Spec §14)
- [x] 初始止损: ATR * 1.2 或结构止损
- [x] TP1 = 1.5R 平仓50%
- [x] TP2 = 2.8R 平仓剩余
- [x] TP1后移动止损至保本
- [x] 结构化移动止盈: 跟随15m higher low / lower high
- [x] 时间止损: 75分钟无进展
- [x] 早退: 评分<55, 1H方向失效, 反向强信号, 突破失败

### 组合风控 (Spec §15)
- [x] 日亏损限制 -3%
- [x] 连续3亏冷却30分钟
- [x] 连续5亏停止当日交易
- [x] 同方向最大风险1.2%
- [x] 总组合最大风险1.6%
- [x] 同方向最多3笔持仓
- [x] 盈利保护: +2.5%限A级, +4%停止开仓

### 冷却机制 (Spec §16)
- [x] 同币种冷却: 60分钟
- [x] 同策略冷却: 30分钟
- [x] 假突破冷却: 30分钟 (连续2次)
- [x] 全局冷却: 连亏规则

### 订单执行 (Spec §17)
- [x] 订单路由器 (`order_router.py`): 限价入场→超时追市价→重试→止损挂单
- [x] 限价单优先入场, 超时自动追市价 (entry_timeout_minutes=3)
- [x] 挂单超时取消 + 市价追单
- [x] 交易所止损单 (stop_market + reduceOnly)
- [x] 滑点检查 (>0.15%拒绝)
- [x] 失败重试最多2次 (Spec §17.3)
- [x] 孤儿挂单清理 (Spec §17.4)

### 相关性控制 (Spec §18)
- [x] 72根15m滚动相关性
- [x] 相关性>0.80同方向拒绝

### 监控告警 (Spec §26)
- [x] Telegram通知: 开仓/平仓/风控/心跳/冷却/盈利保护/止损失败/数据异常
- [x] 每日报告: 交易统计, 盈亏, 按币种/策略/时段分析
- [x] 心跳间隔: 60秒 (Spec §19)
- [x] Prometheus指标暴露: `/metrics` 端点

### 回测 (Spec §23)
- [x] 回测引擎: 手续费0.04%, 滑点0.02%, 资金费0.01%/8h
- [x] 成交模型 (`fill_model.py`): 独立模块, 滑点/手续费/资金费/部分成交计算
- [x] 回测报告 (`reports.py`): 文本摘要+JSON完整输出 (Spec §23.3)
- [x] 信号生成 + TP1/TP2管理 + 时间止损
- [x] 12项指标: 总收益, 月度, 最大回撤, 日回撤, 胜率, RR, PF, Sharpe, Sortino, 连亏, 分组统计
- [x] 通过标准检查: DD≤15%, WR≥38%, RR≥1.7, PF≥1.25, n≥200
- [x] 部分成交模型: 单笔≤bar成交量10%, <30%放弃
- [x] Regime过滤: EXTREME禁止开仓 (与实盘一致)
- [x] 参数优化器 (`optimizer.py`): 网格搜索最优参数组合

### 模拟盘 (Spec §24)
- [x] 模拟盘执行器 (`sim/paper_runner.py`): 信号优雅退出, 日志配置
- [x] 运行脚本 (`scripts/run_paper.py`)

### 项目结构 (Spec §20) - 100%对齐
- [x] `app/main.py`
- [x] `app/config.py`
- [x] `app/constants.py`
- [x] `app/models/market.py`
- [x] `app/data/exchange_client.py`
- [x] `app/data/websocket_feed.py`
- [x] `app/data/ohlcv_cache.py`
- [x] `app/data/account_feed.py`
- [x] `app/indicators/calc.py`
- [x] `app/universe/candidate_pool.py`
- [x] `app/universe/trend_scoring.py`
- [x] `app/strategy/signal_engine.py`
- [x] `app/risk/position_sizer.py`
- [x] `app/risk/risk_engine.py`
- [x] `app/risk/correlation_guard.py`
- [x] `app/risk/cooldown_engine.py`
- [x] `app/execution/order_router.py`
- [x] `app/execution/slippage_guard.py`
- [x] `app/execution/stop_manager.py`
- [x] `app/execution/position_manager.py`
- [x] `app/backtest/engine.py`
- [x] `app/backtest/fill_model.py`
- [x] `app/backtest/metrics.py`
- [x] `app/backtest/reports.py`
- [x] `app/sim/paper_runner.py`
- [x] `app/monitoring/logger.py`
- [x] `app/monitoring/notifier.py`
- [x] `app/monitoring/metrics_exporter.py`
- [x] `config/config.yaml`
- [x] `config/symbols.yaml`
- [x] `scripts/run_backtest.py`
- [x] `scripts/run_paper.py`
- [x] `scripts/run_live.py`

### 测试 (Phase 4)
- [x] pytest 单元测试套件 (70个测试)
  - `test_indicators.py` (29个)
  - `test_risk_engine.py` (17个)
  - `test_position_sizer.py` (8个)
  - `test_strategy_router.py` (8个)
  - `test_models.py` (7个)
  - `conftest.py`: 共享fixtures

### Phase 2 扩展 (已完成, 第一阶段默认关闭)
- [x] 均值回归策略: RSI(14)+BB(20,2), 配置开关 `mean_reversion.enable: false`
- [x] 资金费套利策略: 极端费率反向持仓, 配置开关 `funding_arb.enable: false`
- [x] 多策略并行路由: `strategy_router.py`

---

## 配置参数 (严格对齐 Spec §19/§28)

| 参数 | Spec建议 | 当前值 | 状态 |
|------|----------|--------|------|
| leverage | 10x | 10 | ✅ |
| risk_per_trade | 0.004 | 0.004 | ✅ |
| stop_atr_multiple | 1.2 | 1.2 | ✅ |
| tp1_r_multiple | 1.5 | 1.5 | ✅ |
| tp2_r_multiple | 2.8 | 2.8 | ✅ |
| max_positions | 3 | 3 | ✅ |
| adx_min | 22 | 22 | ✅ |
| score_min_trade | 62 | 62 | ✅ |
| max_holding_minutes | 75 | 75 | ✅ |
| daily_loss_limit_pct | 0.03 | 0.03 | ✅ |
| cooldown_3_losses | 30min | 30 | ✅ |
| stop_after_5_losses | true | true | ✅ |
| profit_guard_pct | 0.025 | 0.025 | ✅ |
| profit_stop_pct | 0.04 | 0.04 | ✅ |
| block_extreme_market | true | true | ✅ |
| heartbeat_seconds | 60 | 60 | ✅ |
| whitelist_hours | [8-11,15-18,20-23,0] | [8-11,15-18,20-23,0] | ✅ |
| mean_reversion | Phase 1不做 | enable: false | ✅ |
| funding_arb | Phase 1不做 | enable: false | ✅ |

---

## 与Spec差异 / 调整 ⚠️

| Spec要求 | 实际实现 | 原因 |
|---------|---------|------|
| 扫描150个合约 | 扫描全部557个 | 全量扫描后通过过滤层选出, 结果更好 |
| 方向需要hl高低点确认 | 移除hl要求 | 大部分币种1H数据不足200根, hl检查太严格导致零交易 |
| EMA200必须参与方向判定 | <200根数据时fallback到EMA50 | 很多币种上市时间短 |
| indicators目录各指标独立文件 | 统一在calc.py中 | 代码量不大, 单文件更易维护 |
| strategy目录分setup_pullback.py等 | 统一在signal_engine.py中 | 功能耦合度高, 分拆无收益 |

---

## 低优先级 (暂不做)
- [ ] 多交易所套利 (Spec §29)
- [ ] AI自动调参 (Spec §29)
- [ ] 高频超短 scalp (Spec §29)

---

## Bug修复记录

| 日期 | Bug | 修复 |
|------|-----|------|
| 2026-03-17 | risk_engine.py `*10` 使风控限制放大10倍 | 移除`*10`, 改用正确默认值 |
| 2026-03-17 | direction filter要求EMA200+hl导致零交易 | fallback EMA50, 移除hl |
| 2026-03-17 | Binance sandbox已弃用 | sandbox=False |
| 2026-03-18 | risk_engine total margin无上限 | 新增90% margin占比上限检查 |
| 2026-03-18 | TP1部分平仓未计算手续费 | 添加partial fee计算 |
| 2026-03-18 | 回测无资金费模拟 | 添加funding_rate=0.01%每8h |
| 2026-03-18 | 信号无过期机制 | 设置`expires_at`(3分钟) |
| 2026-03-18 | RANGING模式无仓位数限制 | 添加最多1个持仓限制 |
| 2026-03-18 | 同方向无数量限制 | 新增同方向最多3笔检查 |
| 2026-03-18 | 无最小下单量检查 | 新增min_notional 5U检查 |
| 2026-03-18 | 回测引擎不模拟regime过滤 | 添加EXTREME过滤 (与实盘一致) |
| 2026-03-19 | 参数偏离spec默认值 | 全部恢复到Spec §19/§28标准 |
| 2026-03-19 | EXTREME模式被修改允许做空 | 恢复Spec §8.3: 禁止开新仓 |
| 2026-03-19 | mean_reversion/funding_arb默认开启 | 恢复Spec §29: Phase 1关闭 |
| 2026-03-19 | 缺失6个spec要求的模块 | 补齐account_feed/constants/order_router/fill_model/reports/logger/paper_runner |

---

## 部署信息

- **本地代码**: `/Users/hongtou/newproject/quant_bot/`
- **服务器代码**: `/opt/quant_bot/` (root@139.162.41.38)
- **Dashboard**: http://139.162.41.38:5001/
- **进程管理**: supervisord (`quant-bot`)
- **数据库**: `/opt/quant_bot/data/quant_bot.db` (SQLite)
- **日志**: `/opt/quant_bot/logs/quant_bot.log`
- **模式**: 模拟盘 (paper_mode=True)
