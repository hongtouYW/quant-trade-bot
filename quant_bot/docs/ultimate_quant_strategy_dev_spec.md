# 加密货币量化交易完整开发规格书（Ultimate Version）

> 版本：vFinal
> 
> 目标：把前述 Blueprint、V1、融合版、增强版、V3 的有效部分整合成一份**可直接进入开发、回测、模拟盘、实盘灰度**的完整 Markdown 规格书。
>
> 适用范围：USDT 永续合约、10x 杠杆、初始本金 2000U、日内多次交易、优先做趋势行情，严格控制回撤。
>
> 核心原则：**先做出稳定可验证的单主策略系统，再逐步增加增强模块。**

---

# 目录

1. 项目目标与边界
2. 总体策略定位
3. 账户与风险约束
4. 市场与交易标的范围
5. 数据源与周期设计
6. 系统总体架构
7. 候选池过滤
8. 趋势评分与市场状态识别
9. 交易方向判定
10. 主入场策略
11. 精细入场确认
12. 禁做条件与假突破过滤
13. 仓位与杠杆管理
14. 止损、止盈、移动保护
15. 组合级风控
16. 冷却机制与日内控制
17. 订单执行规则
18. 相关性控制
19. 配置文件模板
20. Python 项目结构
21. 核心数据结构定义
22. 核心模块伪代码
23. 回测要求
24. 模拟盘要求
25. 实盘灰度上线流程
26. 监控与告警
27. 开发任务拆解
28. 默认参数建议
29. 第一阶段不做的内容
30. 最终执行结论

---

## 1. 项目目标与边界

### 1.1 项目目标

构建一套可程序化执行的加密货币永续合约量化系统，满足以下目标：

- 使用 **10x 杠杆**。
- 初始本金 **2000U**。
- 扫描范围最多 **150 个 USDT 永续交易对**。
- 日内允许多次交易，但以**高质量频率**为主，不追求机械刷单。
- 目标优先级为：
  1. **控制回撤**
  2. **保证系统稳定执行**
  3. **在趋势日获取利润**
  4. 再考虑提高收益率

### 1.2 现实约束

本系统不承诺“稳定日赚 5%”。

对于 2000U + 10x 的组合，真正可持续的目标应是：

- 日亏损严格控制在 **-3%** 以内。
- 日内正常波动目标：**+0.5% ~ +2.0%**。
- 趋势行情优异日：**+2% ~ +4%**。
- 出现连续亏损或市场无趋势时，允许少做甚至不做。

### 1.3 开发边界

本规格书覆盖：

- 策略逻辑
- 风控逻辑
- 工程结构
- 回测要求
- 模拟盘流程
- 实盘灰度流程

本规格书不覆盖：

- 交易所开户
- API 申请教程
- 法律合规意见
- 资金托管方案

---

## 2. 总体策略定位

### 2.1 主要策略类型

本系统采用：

> **趋势跟随 + 回踩承接/窄幅整理后突破 的单主策略系统**

不是多策略宇宙，不在第一阶段同时启用：

- 均值回归
- 资金费率套利
- 高频做市
- 自动调参引擎
- 多策略路由器

### 2.2 为什么选单主策略

原因如下：

- 容易回测归因
- 容易找出亏损来源
- 容易做参数优化
- 更适合 2000U 小资金
- 更适合第一阶段上线

### 2.3 时间框架设计

- **1H**：大方向过滤、趋势质量评估、市场状态识别
- **15m**：主入场触发
- **1m**：精细确认和执行优化

---

## 3. 账户与风险约束

### 3.1 账户设定

- 初始本金：`2000 USDT`
- 杠杆：`10x`
- 交易市场：`USDT 永续`
- 默认交易方向：`双向，支持做多和做空`

### 3.2 风险原则

使用**固定风险法**，不是固定仓位法。

也就是：

- 每笔交易，先确定可承受亏损金额。
- 再根据止损距离倒推实际下单数量。

### 3.3 核心风险参数

- 单笔风险：`账户净值 0.4%`
- 单日最大亏损：`账户净值 3.0%`
- 最大连续亏损笔数：`5`
- 最大同时持仓数：`3`
- 同方向最大总风险暴露：`1.2%`
- 组合总风险暴露：`1.6%`

### 3.4 盈利保护逻辑

- 当日净收益达到 `+2.5%`：进入谨慎模式，只允许 A 级信号。
- 当日净收益达到 `+4.0%`：停止开新仓，仅管理已有仓位。

---

## 4. 市场与交易标的范围

### 4.1 扫描市场

优先支持以下类型：

- Binance USDT Perpetual
- Bybit USDT Perpetual

第一阶段建议只接一个交易所，避免跨交易所差异增加复杂度。

### 4.2 候选池范围

- 扫描最多 `150` 个 USDT 永续币对。
- 真正进入活跃交易池的数量：`10 ~ 15` 个。

### 4.3 标的筛选目标

不是找“最会跳的币”，而是找：

- 有趋势
- 有量能
- 有承接
- 点差合理
- 滑点可控
- 不容易乱插针

---

## 5. 数据源与周期设计

### 5.1 必须数据

#### 行情数据
- 最新价
- 1m / 5m / 15m / 1H K 线
- 成交量
- 标记价格
- 资金费率
- 持仓量（Open Interest）
- 买卖盘基础深度
- bid/ask spread

#### 账户数据
- 账户净值
- 可用保证金
- 持仓
- 挂单
- 已实现盈亏
- 未实现盈亏

### 5.2 计算指标

需要支持以下指标：

- EMA(7, 21, 20, 50, 55, 200)
- ATR(14)
- ATRP（ATR / close）
- ADX(14)
- RSI(14)
- Bollinger Bands(20, 2)
- ROC
- 成交量均值与量比
- recent high / recent low
- K 线影线结构

### 5.3 数据刷新频率

- 1m K 线：实时 / WebSocket
- 15m K 线：K 线收盘触发主检查
- 1H K 线：每根收盘重算方向评分与市场状态
- 账户与持仓：每 `2~5 秒` 刷新

---

## 6. 系统总体架构

系统分为以下模块：

1. `market_data`：行情与缓存
2. `universe_selector`：候选池筛选
3. `trend_scoring`：趋势评分
4. `regime_filter`：市场状态识别
5. `signal_engine`：主入场信号
6. `entry_refiner`：1m 精细确认
7. `risk_engine`：风险控制
8. `position_sizer`：仓位计算
9. `execution_engine`：下单与撤单
10. `position_manager`：持仓管理
11. `correlation_guard`：相关性去重
12. `cooldown_engine`：连亏抑制与冷却
13. `backtester`：回测引擎
14. `sim_runner`：模拟盘执行
15. `reporting`：报表与监控

---

## 7. 候选池过滤

### 7.1 第一层硬过滤

从全市场里先过滤掉明显不适合做的币：

```python
def build_candidate_pool(markets):
    candidates = []
    for m in markets:
        if m.quote != 'USDT':
            continue
        if m.type != 'swap':
            continue
        if m.is_active is False:
            continue
        if m.volume_24h < 8_000_000:
            continue
        if m.open_interest < 3_000_000:
            continue
        if m.spread_pct > 0.04:
            continue
        if m.listing_days < 14:
            continue
        if abs(m.funding_rate) > 0.0075:
            continue
        candidates.append(m)
    return candidates
```

### 7.2 第二层波动过滤

对于已经通过第一层的币，再进行波动过滤：

- `ATRP_1h < 0.25%`：太死，不做
- `ATRP_1h > 4.5%`：太野，不做
- 优选区间：`0.45% ~ 2.5%`

### 7.3 第三层流动性过滤

- 最近 1 小时成交额需维持在设定阈值以上
- 深度不够、盘口太薄直接淘汰
- 最近 12 根 5m K 线若出现多次异常长上影/下影，则降权或淘汰

### 7.4 最终活跃池

对过滤后的币进行评分排序，只保留：

- Top `10 ~ 15`
- 分数必须高于最低阈值

---

## 8. 趋势评分与市场状态识别

## 8.1 双层趋势评分

本系统采用两层评分：

### 第一层：动量评分（0~60）

用于发现“正在动的币”。

| 维度 | 分值 | 规则 |
|------|------|------|
| 5m ROC | 15 | 最近 6 根 5m 变化率越清晰越高 |
| EMA7/21/55 排列 | 15 | 排列整齐加分 |
| 成交量放大 | 10 | 当前量 / 均量 |
| ATRP | 10 | 在优选区间内加分 |
| taker buy/sell 倾向 | 10 | 顺势资金更优 |

### 第二层：趋势质量评分（0~40）

用于过滤“只是突然异动”的币。

| 维度 | 分值 | 规则 |
|------|------|------|
| 1H EMA20/50/200 排列 | 15 | 结构越标准越高 |
| 1H ADX | 10 | ADX > 22 优秀 |
| 与 EMA20 的相对位置 | 5 | 不贴线更好 |
| 15m 回踩恢复 | 5 | 有承接更好 |
| 波动稳定性 | 5 | 少插针更好 |

### 8.2 最终趋势分数

```python
final_score = momentum_score + quality_score
```

评级：

- `>= 78`：A 级，可优先交易
- `70 ~ 77`：B 级，可交易
- `62 ~ 69`：C 级，只能在空仓且环境好时交易
- `< 62`：不交易

## 8.3 市场状态识别（精简版）

只保留 3 种状态，不做复杂 regime 系统：

1. `TRENDING`：趋势市
2. `RANGING`：震荡市
3. `EXTREME`：极端波动市

### 判断逻辑

#### TRENDING
满足大部分条件：

- 1H ADX >= 22
- EMA20/50/200 方向一致
- 价格位于 EMA20 同侧
- 近 8 根 15m 有明确高低点推进

#### RANGING
满足以下特征：

- 1H ADX < 18
- EMA20/50 缠绕
- 最近 20 根 15m 区间收敛明显

#### EXTREME
满足任一：

- ATRP 突然放大到异常水平
- 最近 3 根 15m 中有 2 根长实体异常 K 线
- funding / news 导致快速失真行情

### 使用规则

- `TRENDING`：允许主策略正常交易
- `RANGING`：只允许最强 A 级突破信号，且仓位减半
- `EXTREME`：禁止开新仓

---

## 9. 交易方向判定

方向判定只用 1H，避免 5m/15m 容易反复。

### 9.1 做多方向条件

全部满足：

- `close > EMA20 > EMA50 > EMA200`
- `ADX >= 22`
- `EMA20 slope > 0`
- `近 3 根 1H 的高点/低点抬高`

### 9.2 做空方向条件

全部满足：

- `close < EMA20 < EMA50 < EMA200`
- `ADX >= 22`
- `EMA20 slope < 0`
- `近 3 根 1H 的高点/低点降低`

### 9.3 中性处理

若多空方向都不满足，则该币不参与主策略。

---

## 10. 主入场策略

本系统只保留一套主策略，但允许两种触发形态。

### 主策略名称

> `Trend Pullback / Compression Breakout`

### 10.1 形态 A：趋势回踩承接入场

适合标准趋势中的二次上车。

#### 做多条件

1. 1H 方向为做多
2. 15m 回踩到 EMA21 附近或局部结构支撑位
3. 回踩期间成交量下降
4. 出现止跌确认 K 线：
   - 阳线吞没
   - 长下影
   - 收盘重新站上 EMA7/EMA21
5. 下一根或当前根量能恢复

#### 做空条件

镜像执行。

### 10.2 形态 B：窄幅整理后突破入场

适合趋势中途整理后的延续。

#### 做多条件

1. 1H 方向为做多
2. 最近 20 根 15m 构成较窄区间
3. 区间宽度小于阈值，例如 `< 3%`
4. 当前 15m 收盘突破近 20 根高点
5. 当前成交量 > 近 20 根均量 * 1.3

#### 做空条件

镜像执行。

### 10.3 优先级

优先级：

1. 回踩承接入场
2. 整理后突破

如果同一时间两者都成立，只取评分更高的一种。

---

## 11. 精细入场确认

主信号在 15m 触发后，切到 1m 做更精确确认。

### 11.1 确认目标

- 降低追高/追空
- 提高盈亏比
- 避免刚突破就回抽吃止损

### 11.2 1m 确认逻辑

#### 做多确认

满足任意两项：

- 1m 突破最近 5 根小高点
- 1m 收盘重新站上 EMA9
- 1m 成交量放大到最近 10 根均量的 1.2 倍以上
- 出现更高低点结构

#### 做空确认

镜像执行。

### 11.3 执行规则

- 最多等待 `3 根 1m`
- 若 3 根内不确认，信号失效
- 若确认时价格偏离预设入场价超过 `0.35 ATR(15m)`，放弃

---

## 12. 禁做条件与假突破过滤

这是系统最关键的防亏模块之一。

### 12.1 禁做条件

满足任一则禁止开仓：

- 市场状态为 `EXTREME`
- 当前币分数 < 最低交易阈值
- 点差超标
- 即将发生高波动事件
- 同方向已有高度相关仓位
- 当日已达到日亏损上限
- 连亏触发冷却
- 当前时间落在禁做时段

### 12.2 假突破过滤器

#### 做多时，出现以下任一则放弃：

- 突破 K 线上影过长，收盘不够强
- 突破时量能不足
- 突破后下一根 1m 立刻回到区间内部
- 15m 突破位置离 1H 关键阻力太近
- 15m ATR 已显著放大，说明可能已晚于最佳点

#### 做空时镜像执行。

### 12.3 结构健康检查

禁止交易以下结构：

- 最近 12 根 15m 中插针过多
- 连续三次假突破同方向
- 盘整过宽而不是“真压缩”
- 量能异常但无结构配合

---

## 13. 仓位与杠杆管理

### 13.1 核心思想

仓位由风险决定，而非主观拍脑袋。

### 13.2 单笔风险金额

```python
risk_amount = equity * risk_per_trade
```

默认：

```python
risk_per_trade = 0.004
```

### 13.3 下单数量计算

```python
stop_distance = abs(entry_price - stop_loss)
position_notional = risk_amount / (stop_distance / entry_price)
position_margin = position_notional / leverage
```

### 13.4 限制条件

- 若所需保证金超过可用保证金设定比例，则缩小仓位
- 若仓位过小低于交易所最小下单单位，则放弃该单
- 单笔名义仓位不超过账户净值的 `25% ~ 30%`
- 总名义暴露不超过账户净值的 `90%`

### 13.5 RANGING 状态下处理

若环境被识别为 `RANGING`，但仍允许 A 级突破单：

- 单笔风险减半至 `0.2%`
- 只允许 1 笔新仓

---

## 14. 止损、止盈、移动保护

## 14.1 初始止损

采用 `ATR + 结构` 结合：

```python
stop_distance = max(1.2 * ATR_15m, structure_stop_distance)
```

默认使用：

- 趋势回踩单：以回踩低点/高点外加缓冲作为结构止损
- 突破单：以整理区间另一侧或 ATR 止损作为初始止损

### 14.2 分批止盈

建议两档：

- `TP1 = 1.5R`，平 `50%`
- `TP2 = 2.8R`，平 `50%`

### 14.3 到达 TP1 后

- 止损移动到保本或小幅盈利区
- 若趋势评分继续提升，可允许持有 TP2
- 若趋势评分明显下降，可提前平剩余仓位

### 14.4 移动保护

当价格进入强势延续阶段时，可启用移动止盈：

- 做多：跟随最近若干根 15m 的 higher low
- 做空：跟随最近若干根 15m 的 lower high

### 14.5 时间止损

以下情况强制出场：

- 开仓后 `75 分钟` 内未有效离开成本区
- 超过规定持仓时间且浮盈不足
- 行情转弱且趋势评分跌破阈值

### 14.6 提前离场条件

满足任一：

- 趋势评分跌破 `55`
- 1H 方向结构失效
- 出现反向强信号
- 突破失败并重新回到整理区间

---

## 15. 组合级风控

### 15.1 日亏损限制

- 当日净值回撤达到 `-3%`：立刻停止开新仓
- 仅允许平仓，不允许加仓、不允许抄底、不允许翻向赌回本

### 15.2 连亏限制

- 连续亏损 `3` 笔：冷却 `30 分钟`
- 连续亏损 `5` 笔：当日停止交易

### 15.3 同方向风险暴露

- 同方向最多 `3` 笔
- 同方向总风险不超过 `1.2%`
- 高相关标的不允许重复重仓

### 15.4 浮盈保护

- 当日盈利超过 `2.5%`，只允许 A 级信号
- 当日盈利超过 `4%`，关闭开仓权限

---

## 16. 冷却机制与日内控制

### 16.1 冷却触发条件

- 连续亏损
- 连续 2 笔突破假信号
- 同一币种同方向连续打止损 2 次

### 16.2 冷却规则

- 同一币种冷却：`60 分钟`
- 同一策略冷却：`30 分钟`
- 全局冷却：按连亏规则执行

### 16.3 分时段白名单

第一阶段不做自动调参，但允许配置历史更稳定的交易时段。

例如：

- 白名单时段：`08:00-12:00`, `15:00-19:00`, `20:00-01:00`（按服务器时区配置）
- 黑名单时段：流动性差、假突破多的时段

实际时段必须由历史回测结果确认。

---

## 17. 订单执行规则

### 17.1 下单方式

第一阶段建议：

- 入场：优先限价，必要时市价追单
- 止损：必须使用市价止损或交易所保护止损
- 止盈：限价分批止盈

### 17.2 滑点控制

若预计滑点超过阈值，则取消交易。

### 17.3 重试机制

- 下单失败重试最多 `2` 次
- 若交易所报错 / 网络异常，则记录并停止该币种当次交易

### 17.4 挂单管理

- 超时未成交取消
- 若信号失效立即撤单
- 不允许遗留孤儿挂单

---

## 18. 相关性控制

### 18.1 目的

避免同时持有多个高度联动币，例如：

- BTC / ETH / SOL 同方向同时开
- 某板块代币高度联动同时暴露

### 18.2 简化规则

使用最近 72 根 15m 收益率做滚动相关性：

- 若相关系数 > `0.80`
- 且方向相同
- 且已有持仓

则：

- 新信号降级
- 或直接拒绝开仓

### 18.3 优先保留原则

若高相关信号冲突：

- 保留分数更高者
- 保留流动性更好者
- 保留结构更干净者

---

## 19. 配置文件模板

```yaml
account:
  initial_balance: 2000
  leverage: 10
  max_margin_usage_pct: 0.90

market:
  exchange: binance
  quote: USDT
  max_scan_symbols: 150
  active_pool_size: 12

filters:
  min_24h_volume: 8000000
  min_open_interest: 3000000
  max_spread_pct: 0.04
  min_listing_days: 14
  max_abs_funding_rate: 0.0075
  min_atrp_1h: 0.25
  max_atrp_1h: 4.50

trend_filter:
  adx_min: 22
  score_min_trade: 62
  score_min_a_grade: 78

execution:
  risk_per_trade: 0.004
  stop_atr_multiple: 1.2
  tp1_r_multiple: 1.5
  tp2_r_multiple: 2.8
  max_positions: 3
  max_same_direction_risk: 0.012
  max_total_risk: 0.016
  entry_timeout_minutes: 3
  max_entry_drift_atr: 0.35
  max_holding_minutes: 75

risk:
  daily_loss_limit_pct: 0.03
  profit_guard_mode_pct: 0.025
  daily_stop_trading_profit_pct: 0.04
  cooldown_after_3_losses_minutes: 30
  stop_after_5_losses: true

regime:
  enable: true
  ranging_trade_half_risk: true
  block_extreme_market: true

correlation:
  enable: true
  window_bars: 72
  max_same_dir_corr: 0.80

schedule:
  whitelist_hours: [8,9,10,11,15,16,17,18,20,21,22,23,0]

monitoring:
  telegram_alerts: true
  save_trades_to_db: true
  heartbeat_seconds: 60
```

---

## 20. Python 项目结构

```text
quant_bot/
├── app/
│   ├── main.py
│   ├── config.py
│   ├── constants.py
│   ├── models/
│   │   ├── market.py
│   │   ├── signal.py
│   │   ├── position.py
│   │   └── trade.py
│   ├── data/
│   │   ├── exchange_client.py
│   │   ├── websocket_feed.py
│   │   ├── ohlcv_cache.py
│   │   └── account_feed.py
│   ├── indicators/
│   │   ├── ema.py
│   │   ├── atr.py
│   │   ├── adx.py
│   │   ├── rsi.py
│   │   └── bollinger.py
│   ├── universe/
│   │   ├── candidate_pool.py
│   │   ├── trend_scoring.py
│   │   └── regime_filter.py
│   ├── strategy/
│   │   ├── direction_filter.py
│   │   ├── setup_pullback.py
│   │   ├── setup_compression_breakout.py
│   │   ├── entry_refiner.py
│   │   └── fake_breakout_filter.py
│   ├── risk/
│   │   ├── position_sizer.py
│   │   ├── risk_engine.py
│   │   ├── correlation_guard.py
│   │   └── cooldown_engine.py
│   ├── execution/
│   │   ├── order_router.py
│   │   ├── slippage_guard.py
│   │   ├── stop_manager.py
│   │   └── position_manager.py
│   ├── backtest/
│   │   ├── engine.py
│   │   ├── fill_model.py
│   │   ├── metrics.py
│   │   └── reports.py
│   ├── sim/
│   │   └── paper_runner.py
│   └── monitoring/
│       ├── logger.py
│       ├── notifier.py
│       └── metrics_exporter.py
├── config/
│   ├── config.yaml
│   └── symbols.yaml
├── tests/
├── scripts/
│   ├── run_backtest.py
│   ├── run_paper.py
│   └── run_live.py
├── requirements.txt
└── README.md
```

---

## 21. 核心数据结构定义

```python
from dataclasses import dataclass
from datetime import datetime
from typing import List, Optional

@dataclass
class Candle:
    ts: datetime
    open: float
    high: float
    low: float
    close: float
    volume: float

@dataclass
class SymbolSnapshot:
    symbol: str
    volume_24h: float
    open_interest: float
    spread_pct: float
    funding_rate: float
    listing_days: int
    momentum_score: float
    quality_score: float
    final_score: float
    direction: int   # 1 long, -1 short, 0 neutral
    regime: str

@dataclass
class Signal:
    symbol: str
    direction: int
    setup_type: str
    score: float
    entry_price: float
    stop_loss: float
    take_profits: List[tuple]
    risk_reward: float
    created_at: datetime
    expires_at: datetime

@dataclass
class Position:
    symbol: str
    direction: int
    entry_price: float
    size: float
    margin: float
    stop_loss: float
    tp1: float
    tp2: float
    opened_at: datetime
    strategy_tag: str
```

---

## 22. 核心模块伪代码

### 22.1 主循环

```python
async def main_loop():
    while True:
        refresh_account_state()
        refresh_positions()

        if not risk_engine.can_open_new_positions():
            manage_open_positions()
            await sleep_short()
            continue

        if is_time_to_refresh_pool():
            candidates = universe_selector.build()
            scored = trend_scoring.score_all(candidates)
            active_pool = pick_top_symbols(scored)

        for symbol in active_pool:
            if correlation_guard.block(symbol):
                continue
            if cooldown_engine.block(symbol):
                continue

            snapshot = get_symbol_snapshot(symbol)
            if snapshot.regime == 'EXTREME':
                continue
            if snapshot.final_score < cfg.filters.score_min_trade:
                continue

            direction = direction_filter.get_direction(symbol)
            if direction == 0:
                continue

            setup = signal_engine.find_setup(symbol, direction)
            if not setup:
                continue

            if fake_breakout_filter.reject(setup):
                continue

            refined = entry_refiner.confirm(setup)
            if not refined:
                continue

            order_plan = position_sizer.build_plan(refined)
            if not risk_engine.approve(order_plan):
                continue

            execution_engine.place_entry(order_plan)

        manage_open_positions()
        await sleep_short()
```

### 22.2 信号引擎

```python
def find_setup(symbol, direction):
    setup_a = detect_pullback_entry(symbol, direction)
    setup_b = detect_compression_breakout(symbol, direction)

    setups = [s for s in [setup_a, setup_b] if s is not None]
    if not setups:
        return None

    return sorted(setups, key=lambda x: x.score, reverse=True)[0]
```

### 22.3 仓位计算

```python
def build_order_plan(signal, equity, leverage, risk_pct):
    risk_amount = equity * risk_pct
    stop_distance = abs(signal.entry_price - signal.stop_loss)
    if stop_distance <= 0:
        return None

    notional = risk_amount / (stop_distance / signal.entry_price)
    margin = notional / leverage

    return {
        'symbol': signal.symbol,
        'direction': signal.direction,
        'entry': signal.entry_price,
        'stop': signal.stop_loss,
        'notional': notional,
        'margin': margin,
        'tp1': signal.take_profits[0],
        'tp2': signal.take_profits[1],
    }
```

### 22.4 持仓管理

```python
def manage_position(pos, latest_price, latest_score):
    if hit_stop(pos, latest_price):
        close_position(pos, reason='stop_loss')
        return

    if hit_tp1(pos, latest_price) and not pos.tp1_done:
        close_partial(pos, pct=0.5, reason='tp1')
        move_stop_to_breakeven(pos)

    if hit_tp2(pos, latest_price):
        close_position(pos, reason='tp2')
        return

    if holding_too_long_without_progress(pos):
        close_position(pos, reason='time_stop')
        return

    if latest_score < 55:
        close_position(pos, reason='trend_score_drop')
        return

    trail_if_needed(pos, latest_price)
```

---

## 23. 回测要求

### 23.1 回测必须包含的真实成本

不得只做理想化回测，必须纳入：

- 手续费
- 滑点
- 资金费率
- 最小下单单位
- 挂单未成交与部分成交
- 10x 杠杆下的真实保证金影响

### 23.2 回测周期建议

最少回测：

- `6 个月` 历史数据

更建议：

- `12 个月` 数据
- 覆盖趋势市、震荡市、极端市

### 23.3 回测输出指标

必须输出：

- 总收益率
- 月收益率
- 最大回撤
- 日最大回撤
- 胜率
- 平均盈亏比
- Profit Factor
- Sharpe / Sortino
- 最大连亏笔数
- 按币种分组表现
- 按时段分组表现
- 按 setup 类型分组表现

### 23.4 回测通过标准

第一阶段建议通过门槛：

- 最大回撤 `<= 15%`
- 单日极端回撤可控
- 胜率 `>= 38%`
- 平均盈亏比 `>= 1.7`
- Profit Factor `>= 1.25`
- 至少 200 笔以上样本

---

## 24. 模拟盘要求

### 24.1 模拟盘目的

验证以下事项：

- 实时数据稳定性
- 下单逻辑正确性
- 持仓管理正确性
- 监控与告警链路
- 冷却机制是否生效
- 回测和实时表现差距

### 24.2 模拟盘周期

建议：

- 最少 `14 天`
- 更理想：`21 ~ 30 天`

### 24.3 模拟盘通过标准

- 无严重执行 bug
- 报表链路完整
- 策略逻辑与回测一致
- 最大回撤未超预期太多

---

## 25. 实盘灰度上线流程

### 第 0 阶段：离线回测

完成全部指标验证。

### 第 1 阶段：模拟盘

零资金风险，验证执行。

### 第 2 阶段：小资金实盘

建议：

- 初期使用 `500U ~ 1000U`
- 风险减半：`0.2%/trade`
- 最大持仓 1~2 笔

### 第 3 阶段：标准实盘

当以下条件连续满足后，恢复到标准配置：

- 连续 2~4 周执行稳定
- 没有严重失控回撤
- 订单执行偏差可接受

---

## 26. 监控与告警

### 26.1 必要告警

- 程序心跳中断
- 数据流中断
- 交易所接口异常
- 下单失败
- 止损未成功挂出
- 日亏损触发
- 达到盈利保护阈值
- 连续亏损触发冷却

### 26.2 必要报表

每日生成：

- 开仓次数
- 平仓次数
- 胜率
- 盈亏比
- 当日净收益
- 触发了哪些风控规则
- 哪些币表现好 / 差
- 哪个 setup 表现更好

---

## 27. 开发任务拆解

### Phase 1：基础设施

- 接交易所 REST / WebSocket
- K 线缓存
- 账户与持仓同步
- 指标计算库

### Phase 2：选币与评分

- 候选池过滤
- 双层评分
- 方向判定
- 市场状态识别

### Phase 3：信号与执行

- 回踩信号
- 突破信号
- 1m 精细确认
- 假突破过滤
- 仓位计算
- 下单、止损、止盈

### Phase 4：风控与持仓管理

- 日亏损控制
- 连亏冷却
- 相关性去重
- 浮盈保护
- 时间止损
- 移动止盈

### Phase 5：回测与模拟盘

- 回测引擎
- 绩效分析
- 模拟盘 runner

### Phase 6：监控与报表

- Telegram 通知
- 日报 / 周报
- 基础 dashboard

---

## 28. 默认参数建议

这是第一版建议直接使用的默认值：

| 参数 | 建议值 |
|------|--------|
| 杠杆 | 10x |
| 单笔风险 | 0.4% |
| 日亏损上限 | 3.0% |
| 最大同时持仓 | 3 |
| 候选池扫描 | 150 |
| 活跃池 | 10~15 |
| 最低交易分数 | 62 |
| A 级交易分数 | 78 |
| ADX 阈值 | 22 |
| 初始止损 | 1.2 ATR 或结构止损 |
| TP1 | 1.5R |
| TP2 | 2.8R |
| 时间止损 | 75 分钟 |
| 连亏 3 笔冷却 | 30 分钟 |
| 连亏 5 笔停机 | 是 |
| 盈利保护启动 | +2.5% |
| 盈利停止开仓 | +4.0% |

---

## 29. 第一阶段不做的内容

以下内容**明确不进入第一阶段开发主版本**：

- 多策略并行打分路由
- 均值回归主策略
- 资金费率套利模块
- 自动调参 / 自适应引擎
- 多交易所套利
- 高频超短 scalp
- AI 自动改参数

原因：

这些东西会明显增加：

- 复杂度
- 过拟合风险
- 排查难度
- 实盘失真风险

等第一版稳定后，再做增强版分支开发。

---

## 30. 最终执行结论

### 30.1 这份规格书的定位

这不是概念草案，而是一份：

> **开发团队可以直接按模块拆任务、实现、回测、模拟盘、灰度上线的完整 md 规格书**

### 30.2 最推荐的执行顺序

1. 先完成基础数据与指标模块
2. 再完成候选池、评分、方向判定
3. 再完成单主策略与风控
4. 先做回测
5. 再做模拟盘
6. 最后做小资金实盘

### 30.3 最重要的原则

- 不追求第一天就最强
- 不为了好看把系统做得太复杂
- 先把“会亏在哪里”搞清楚
- 先把系统活下来，再慢慢增强

### 30.4 一句话总结

> **这套系统的核心不是“多开仓”，而是“只做高质量趋势单，并用固定风险把回撤锁死”。**

