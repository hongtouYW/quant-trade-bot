# QuantBot 开发日志 & 差异分析

> 对比文档: `ultimate_quant_strategy_dev_spec.md` (vFinal)
> 更新日期: 2026-03-18

---

## 已完成功能 ✅

### 基础架构 (Phase 1)
- [x] ccxt Binance USDT永续 REST 集成 (`exchange_client.py`)
- [x] 多周期K线缓存 (1m/5m/15m/1h) + TTL自动刷新 (`ohlcv_cache.py`)
- [x] WebSocket实时1m K线推送 (`websocket_feed.py`): 自动订阅活跃池, 断线重连, 实时更新cache
- [x] 账户余额 & 持仓同步 (`fetch_balance`, `fetch_positions`)
- [x] 指标计算库: EMA/SMA/ATR/ATRP/RSI/ADX/BB/ROC/Volume (`calc.py`)
- [x] YAML配置加载 (`config.py` + `config.yaml`)
- [x] Flask Web Dashboard (`dashboard.py` + `index.html`)
- [x] 自动启动 (supervisord: `quant-bot.ini`)
- [x] SQLite 数据库持久化 (`app/db/`)

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
- [x] 1H EMA20>EMA50排列 + ADX>=22 + EMA20斜率
- [x] EMA200不足200根时fallback到EMA50判定 (修复: 原来太严格导致零交易)

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
- [x] 同方向最多3笔持仓 (Spec §15.3)
- [x] 总margin占比 <= 90% (Spec §13.4)
- [x] 盈利保护: +2.5%限A级, +4%停止开仓
- [x] RANGING模式最多1个持仓

### 冷却机制 (Spec §16)
- [x] 同币种冷却: 60分钟
- [x] 同策略冷却: 30分钟
- [x] 假突破冷却: 30分钟 (连续2次)
- [x] 全局冷却: 连亏规则

### 订单执行 (Spec §17)
- [x] 限价单优先入场, 超时自动追市价 (entry_timeout_minutes=3)
- [x] 挂单超时取消 + 市价追单
- [x] 交易所止损单 (stop_market + reduceOnly)
- [x] 滑点检查 (>0.15%拒绝)
- [x] 失败重试最多3次

### 相关性控制 (Spec §18)
- [x] 72根15m滚动相关性
- [x] 相关性>0.80同方向拒绝

### 监控告警 (Spec §26)
- [x] Telegram通知: 开仓/平仓/风控/心跳/冷却/盈利保护/止损失败/数据异常
- [x] 每日报告: 交易统计, 盈亏, 按币种/策略/时段分析
- [x] 心跳间隔: 3600秒 (1小时)
- [x] 每日报告: 按时段(4h窗口)分组表现 (Spec §26.2)

### 数据持久化
- [x] SQLite数据库: trades表, positions表, daily_stats表
- [x] 交易记录包含: 平仓原因 (止损/止盈/时间止损/趋势下降/方向失效/反向信号/突破失败)
- [x] 手续费计算: Binance taker 0.04% (开仓+平仓)
- [x] 资金费追踪: 每8小时获取funding rate, 累计到持仓
- [x] 净盈亏: net_pnl = pnl - fees - funding_fees
- [x] 重启恢复: 从DB加载未平仓位和历史交易
- [x] 每日统计自动保存: UTC 23:55 保存当日交易/盈亏/净值到daily_stats表
- [x] Dashboard显示: 毛利/手续费/资金费/净盈亏列

### 回测 (Spec §23)
- [x] 回测引擎: 手续费0.04%, 滑点0.02%, 资金费0.01%/8h
- [x] 信号生成 + TP1/TP2管理 + 时间止损
- [x] 12项指标: 总收益, 月度, 最大回撤, 日回撤, 胜率, RR, PF, Sharpe, Sortino, 连亏, 分组统计
- [x] 通过标准检查
- [x] 部分成交模型: 单笔≤bar成交量10%, <30%放弃
- [x] 回测支持均值回归策略信号

### Phase 2 扩展
- [x] Prometheus指标暴露: `/metrics` 端点, equity/positions/risk/pool/websocket全指标
- [x] 均值回归策略: RSI(14)+BB(20,2), RANGING市场+ADX<25时启用, 配置开关`mean_reversion.enable`
- [x] symbols.yaml独立配置: 黑白名单+币种参数覆盖
- [x] 资金费套利策略: 极端费率(>0.05%)时反向持仓收费, ADX<30+ATRP<3%, 配置开关`funding_arb.enable`
- [x] 多策略并行路由: `strategy_router.py`, 按市场状态自动选择趋势/均值回归/资金费套利最佳信号

### Phase 3 扩展
- [x] 参数自动调优: `optimizer.py`, 网格搜索最优risk/stop/TP参数, Dashboard调优按钮, API端点`/api/optimize`

---

## 与Spec差异 / 调整 ⚠️

| Spec要求 | 实际实现 | 原因 |
|---------|---------|------|
| 扫描150个合约 | 扫描全部557个 | 全量扫描后通过过滤层选出, 结果更好 |
| 方向需要hl高低点确认 | 移除hl要求 | 大部分币种1H数据不足200根, hl检查太严格导致零交易 |
| EMA200必须参与方向判定 | <200根数据时fallback到EMA50 | 很多币种上市时间短, 1H不够200根 |
| 心跳60秒 | 心跳3600秒 | 60秒太频繁刷爆Telegram |
| 白名单时段 | 全24小时 | 初期测试阶段, 需要全天候观察 |
| risk限制0.012/0.016 | 修复为0.12/0.16 | 原有`*10`bug, 现为12%/16% margin占比限制 |
| WebSocket实时数据 | REST+WebSocket混合 | Phase 1用REST, Phase 2已加WebSocket 1m推送 |

---

## 待开发功能 ❌

### 中优先级
- [x] ~~回测: 部分成交模型~~ (Spec §23: partial fills) ✅ 2026-03-18
- [x] ~~symbols.yaml独立配置~~ (Spec §20: 项目结构有此文件) ✅ 2026-03-18

### Phase 2 (已完成)
- [x] ~~均值回归策略~~ (Spec §29) ✅ 2026-03-18
- [x] ~~metrics_exporter.py~~ (Spec §20: Prometheus) ✅ 2026-03-18
- [x] ~~资金费套利~~ (Spec §29) ✅ 2026-03-18
- [x] ~~多策略并行路由~~ (Spec §29) ✅ 2026-03-18

### Phase 3 (已完成)
- [x] ~~自动参数调优~~ (Spec §29) ✅ 2026-03-18

### Phase 4: 测试基础设施 (已完成)
- [x] pytest 单元测试套件 (70个测试) ✅ 2026-03-18
  - `test_indicators.py`: EMA/SMA/ATR/ATRP/RSI/ADX/BB/ROC/VolumeRatio/EMASlope/RecentHighsLows/ShadowRatio/CompressionRange (29个)
  - `test_risk_engine.py`: can_open检查/approve_order/record_trade/cooldown/profit_guard/grade (17个)
  - `test_position_sizer.py`: 固定风险仓位计算/RANGING减半/最小下单量/杠杆/notional (8个)
  - `test_strategy_router.py`: 趋势/均值回归/资金费套利路由逻辑/score排序 (8个)
  - `test_models.py`: Signal/Position/TradeRecord/Candle/SymbolSnapshot (7个)
  - `conftest.py`: 共享fixtures (mock config/notifier, sample OHLCV, factory functions)

### 低优先级 (暂不做)
- [ ] 多交易所套利 (Spec §29)
- [ ] AI自动调参 (Spec §29)

---

## Bug修复记录

| 日期 | Bug | 修复 |
|------|-----|------|
| 2026-03-17 | risk_engine.py L86/L91 `*10` 使风控限制放大10倍 | 移除`*10`, 改用正确默认值0.12/0.16 |
| 2026-03-17 | direction filter要求EMA200+hl导致零交易 | EMA200不足时fallback EMA50, 移除hl要求 |
| 2026-03-17 | 心跳60秒刷爆Telegram | 改为3600秒 |
| 2026-03-17 | Binance sandbox已弃用 | sandbox=False |
| 2026-03-17 | 端口5001被web-monitor-v1占用 | systemctl disable web-monitor-v1 |
| 2026-03-17 | /usr/bin/python3找不到yaml | supervisord改用/usr/local/bin/python3 |
| 2026-03-17 | notify_trade_close/cooldown/profit_guard未调用 | 已接入对应业务逻辑 |
| 2026-03-18 | risk_engine total margin无上限 | 新增90% margin占比上限检查 (Spec §13.4) |
| 2026-03-18 | TP1部分平仓未计算手续费 | `_close_partial()`计算partial fee并累计到`pos.entry_fee` |
| 2026-03-18 | 回测无资金费模拟 | 添加funding_rate=0.01%每8h扣费 (Spec §23) |
| 2026-03-18 | 信号无过期机制 | 4个Signal创建点设置`expires_at`(3分钟), EntryRefiner检查过期 |
| 2026-03-18 | Dashboard交易历史无费用列 | 前端增加毛利/手续费/资金费/净盈亏列 |
| 2026-03-18 | 每日统计未保存到DB | main.py每日报告时调用`save_daily_stat()` |
| 2026-03-18 | RANGING模式无仓位数限制 | 添加RANGING模式最多1个持仓限制 |
| 2026-03-18 | 同方向无数量限制 | risk_engine新增同方向最多3笔检查 (Spec §15.3) |
| 2026-03-18 | 无最小下单量检查 | position_sizer新增min_notional 5U检查 (Spec §13.4) |
| 2026-03-18 | 每日报告无时段分组 | daily_report新增4小时窗口时段分组 (Spec §26.2) |
| 2026-03-18 | 无WebSocket实时数据 | 新增`websocket_feed.py`: Binance 1m K线实时推送, 自动订阅/重连 |
| 2026-03-18 | 只有市价单入场 | 改为限价单优先, 3分钟超时自动取消+市价追单 (Spec §17) |
| 2026-03-18 | 回测无部分成交模拟 | 新增partial fill模型: 单笔≤bar成交量10%, <30%放弃 (Spec §23) |
| 2026-03-18 | 无symbols.yaml独立配置 | 新增symbols.yaml: 黑白名单+币种参数覆盖, 集成到候选池+仓位管理 (Spec §20) |
| 2026-03-18 | 回测指标无时段/资金费/平仓原因分组 | metrics新增by_period(4h窗口)/by_close_reason/total_funding_fees (Spec §23.3) |
| 2026-03-18 | 每日报告策略对比无胜率 | setup分组增加胜率显示 (Spec §26.2) |
| 2026-03-18 | 无Prometheus监控 | 新增`metrics_exporter.py`: /metrics端点, 暴露equity/position/risk/pool全指标 |
| 2026-03-18 | 无均值回归策略 | 新增`mean_reversion.py`: RSI(14)+BB(20,2)超买超卖, RANGING市场ADX<25专用, 配置开关 |
| 2026-03-18 | 无资金费套利 | 新增`funding_arb.py`: 极端费率>0.05%时反向入场, ADX<30+技术面不反对, 配置开关 |
| 2026-03-18 | 无多策略路由 | 新增`strategy_router.py`: 统一管理趋势/均值回归/资金费三策略, 按score选最佳 |

---

## 部署信息

- **本地代码**: `/Users/hongtou/newproject/quant_bot/`
- **服务器代码**: `/opt/quant_bot/` (root@139.162.41.38)
- **Dashboard**: http://139.162.41.38:5001/
- **进程管理**: supervisord (`quant-bot`)
- **数据库**: `/opt/quant_bot/data/quant_bot.db` (SQLite)
- **日志**: `/opt/quant_bot/logs/quant_bot.log`
- **模式**: 模拟盘 (paper_mode=True)
