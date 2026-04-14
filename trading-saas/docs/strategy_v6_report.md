# Report V6 回测策略完整文档

> **来源**: `/opt/trading-bot/quant-trade-bot/xmr_monitor/backtest_engine.py`
> **Preset**: `STRATEGY_PRESETS['v6']` (trading_assistant_dashboard.py)
> **说明**: 纯粹基于回测引擎代码，不涉及 SaaS 线上逻辑

---

## 1. 策略参数 (Preset Config)

```python
{
    'v6b_mode': True,              # 使用 analyze_signal_v6b() 评分函数
    'min_score': 70,               # SHORT 最低评分
    'long_min_score': 85,          # LONG 最低评分（更严格）
    'cooldown': 1,                 # 平仓后冷却 1 根 K 线（1小时）
    'max_leverage': 3,             # 最大杠杆 3x
    'max_positions': 15,           # 最多同时持仓 15 个
    'max_same_direction': 15,      # 同方向最多 15 个
    'short_bias': 1.05,            # SHORT 方向评分 ×1.05 加成
    'enable_trend_filter': True,   # 启用 MA20 斜率趋势过滤
    'long_ma_slope_threshold': 0.02, # LONG 方向 MA20 斜率阈值
    'roi_stop_loss': -10,          # 止损: ROI 跌到 -10% 平仓
    'roi_trailing_start': 6,       # 移动止盈启动: ROI 达 +6%
    'roi_trailing_distance': 3,    # 移动止盈回撤距离: 3%
}
```

- **不启用 v5_mode**, 因此无 TP1/TP2 部分止盈
- **不启用 dynamic_leverage**, 使用旧版仓位计算
- **不启用 dynamic_tpsl / fixed_tp_mode / leverage_based_tpsl**

---

## 2. 信号评分系统 — `analyze_signal_v6b()`

### 2.1 基础评分（满分 100 分，与 v4.2 相同）

#### RSI(14) — 30 分
| 条件 | 分数 | 方向投票 |
|---|---|---|
| RSI < 30 | 30 | LONG +1 |
| RSI > 70 | 30 | SHORT +1 |
| RSI < 45 | 15 | LONG +1 |
| RSI > 55 | 15 | SHORT +1 |
| 45 ≤ RSI ≤ 55 | 5 | 无 |

#### MA 趋势 — 30 分
计算 MA7、MA20、MA50（50根不够时 MA50=MA20）

| 条件 | 分数 | 方向投票 |
|---|---|---|
| price > MA7 > MA20 > MA50 (完美上升) | 30 | LONG +2 |
| price < MA7 < MA20 < MA50 (完美下降) | 30 | SHORT +2 |
| price > MA7 > MA20 | 15 | LONG +1 |
| price < MA7 < MA20 | 15 | SHORT +1 |
| 其他 | 5 | 无 |

#### 成交量 — 20 分
`volume_ratio = 最近1根成交量 / 最近20根平均成交量`

| 条件 | 分数 |
|---|---|
| ratio > 1.5 | 20 |
| ratio > 1.2 | 15 |
| ratio > 1.0 | 10 |
| ratio ≤ 1.0 | 5 |

#### 价格位置 — 20 分
`price_position = (当前价 - 50根最低) / (50根最高 - 50根最低)`

| 条件 | 分数 | 方向投票 |
|---|---|---|
| pos < 0.2 | 20 | LONG +1 |
| pos > 0.8 | 20 | SHORT +1 |
| pos < 0.35 | 10 | LONG +1 |
| pos > 0.65 | 10 | SHORT +1 |
| 0.35 ≤ pos ≤ 0.65 | 5 | 无 |

### 2.2 方向判定

```
if LONG 票数 > SHORT 票数 → LONG
elif SHORT 票数 > LONG 票数 → SHORT
else → RSI < 50 则 LONG, 否则 SHORT
```

### 2.3 RSI/趋势冲突惩罚

```python
rsi_dir = "LONG" if rsi < 50 else "SHORT"
trend_dir = "LONG" if price > ma20 else "SHORT"
if rsi_dir != trend_dir:
    total_score *= 0.85  # 打 85 折
```

### 2.4 V6 额外加分（满分 +25 分）

#### MACD(12,26,9) — 最高 +10
| 条件 | 加分 |
|---|---|
| MACD 方向一致 + 出现交叉 | +10 |
| MACD 方向一致 + 动量增强 | +6 |
| MACD 方向一致（仅方向） | +3 |
| MACD 方向不一致 | +0 |

> 动量增强 = histogram > 0 且 histogram > prev_histogram (LONG)，或 histogram < 0 且 histogram < prev_histogram (SHORT)

#### ADX(14) — 最高 +8
| 条件 | 加分 |
|---|---|
| ADX ≥ 35 | +8 |
| ADX ≥ 25 | +5 |
| ADX ≥ 20 | +2 |
| ADX < 20 | +0 |

#### Bollinger Bands(20, 2σ) — 最高 +7
| 条件 | 加分 |
|---|---|
| LONG + %B < 0.1, 或 SHORT + %B > 0.9 | +7 |
| LONG + %B < 0.25, 或 SHORT + %B > 0.75 | +4 |
| 其他 | +0 |

### 2.5 SHORT 偏好加成

```python
if direction == "SHORT":
    total_score = int(total_score * 1.05)
```

### 2.6 总分范围

- 最低理论分: ~5 (所有维度最低)
- 基础满分: 100 → 惩罚后 85 → +25 加分 → ×1.05 SHORT = **最高约 115~131**
- 无惩罚满分: 100 + 25 = 125 → ×1.05 = **131**

---

## 3. 开仓过滤 (run_backtest)

信号经过评分后，按以下顺序过滤：

### 3.1 冷却期检查
```python
cooldown_bars = 1  # 每次平仓后等待 1 根 K 线
if i <= cooldown_until:
    continue  # 跳过
```
> 注意：这是全局冷却，任何币种平仓都会触发

### 3.2 仓位上限
```python
if len(positions) >= 15:
    continue
```

### 3.3 最低资金
```python
if capital <= 50:
    continue
```

### 3.4 评分过滤
```python
if score < 70:           # min_score
    continue
if direction == 'LONG' and score < 85:  # long_min_score
    continue
```

### 3.5 MA20 斜率趋势过滤
```python
# 计算 MA20 在 20 根内的变化率
ma20_now = 最近20根的均值
ma20_prev = 第-25到第-5根的均值
ma20_slope = (ma20_now - ma20_prev) / ma20_prev

# LONG 方向: MA20 下跌超过 2% 则拒绝
if direction == 'LONG' and ma20_slope < -0.02:
    continue

# SHORT 方向: MA20 上涨超过 1% 则拒绝（默认阈值）
if direction == 'SHORT' and ma20_slope > 0.01:
    continue
```

### 3.6 方向限制
```python
# 同方向最多 15 个（实际等于不限制）
if direction == 'LONG' and long_count >= 15:
    continue
if direction == 'SHORT' and short_count >= 15:
    continue
```

### 3.7 可用资金
```python
available = capital - 已用保证金
if available < 50:
    continue
```

---

## 4. 仓位计算 — `calculate_position_size()`

V6 使用**旧版**仓位计算（非 dynamic_leverage 模式），max_leverage=3：

| 评分 | 仓位大小 | 杠杆 |
|---|---|---|
| ≥ 85 | min(400, 可用×25%) | min(5, `high_score_leverage` or 3) |
| ≥ 75 | min(300, 可用×20%) | min(3, 3) = 3 |
| ≥ 70 | min(200, 可用×15%) | min(3, 3) = 3 |
| ≥ 60 | min(150, 可用×10%) | min(3, 3) = 3 |
| ≥ 55 | min(100, 可用×8%) | min(3, 3) = 3 |
| < 55 | 不开仓 | - |

> V6 preset 未设 `high_score_leverage`，所以 85+ 评分杠杆也是 min(5, 3) = **3x**
> 实际上 V6 所有仓位都是 **3x 杠杆**

最小开仓量: 50 USDT

---

## 5. 止损/止盈逻辑 — `_check_position_bar()`

V6 使用 ROI 模式（基于本金收益率），**不使用**动态止盈止损：

### 5.1 止损
```python
roi_stop_loss = -10  # ROI 跌到 -10% 触发止损
```

计算方式：
```python
# LONG
roi = ((当前价 - 开仓价) / 开仓价) × 杠杆 × 100

# SHORT
roi = ((开仓价 - 当前价) / 开仓价) × 杠杆 × 100

if roi <= -10:
    平仓，原因 = "ROI止损"
```

> 3x 杠杆下，-10% ROI ≈ 价格反向移动 3.33%

### 5.2 移动止盈（Trailing Stop）
```python
roi_trailing_start = 6    # ROI 达 +6% 启动追踪
roi_trailing_distance = 3  # 从 ROI 峰值回撤 3% 则平仓
```

运作流程：
1. 持仓 ROI 达到 +6% → 激活移动止盈，记录 `peak_roi = 6`
2. ROI 继续上升 → 更新 `peak_roi`（如 ROI=12% → peak=12%）
3. ROI 从峰值回撤超过 3% → 平仓
   - 例: peak=12%, 当前ROI=8.5% → 回撤 3.5% > 3% → 平仓

> 3x 杠杆下，+6% ROI ≈ 价格顺向移动 2%

### 5.3 K线内止损/止盈检查

```python
# 先检查止损（用 K 线的 low/high）
if LONG:
    worst_roi = ((low - entry) / entry) × lev × 100
if SHORT:
    worst_roi = ((entry - high) / entry) × lev × 100

if worst_roi <= roi_stop_loss:
    # 计算精确止损价，以此平仓

# 再检查移动止盈（用 K 线的 high/low 更新 peak）
if LONG:
    best_roi = ((high - entry) / entry) × lev × 100
if SHORT:
    best_roi = ((entry - low) / entry) × lev × 100
```

---

## 6. 费用计算 — `_calc_pnl()`

```python
fee_rate = 0.0005  # 0.05% (单边)

# 开仓费 + 平仓费
fee = amount × leverage × fee_rate × 2

# 资金费率（估算）
funding_rate = 0.0001  # 每8小时 0.01%
holding_hours = (bar_count × 1)  # 1小时K线
funding_periods = holding_hours / 8
funding_fee = amount × leverage × funding_rate × funding_periods

# 盈亏
if LONG:
    raw_pnl = (exit_price - entry_price) / entry_price × amount × leverage
if SHORT:
    raw_pnl = (entry_price - exit_price) / entry_price × amount × leverage

net_pnl = raw_pnl - fee - funding_fee
```

---

## 7. 完整交易流程

```
每根 1H K线:
│
├─ 资金检查 (capital <= 0 → 破产终止)
│
├─ V5 TP1 检查 → 跳过（v6 未启用 v5_mode）
│
├─ 遍历所有持仓 → _check_position_bar()
│   ├─ 止损触发? → 平仓，记录 trade，capital += pnl
│   ├─ 移动止盈触发? → 平仓，记录 trade
│   └─ 设置 cooldown_until = 当前 bar + 1
│
├─ 记录资金曲线（每 interval 根 K 线）
│
└─ 开仓逻辑:
    ├─ 冷却期? → 跳过
    ├─ 仓位满? → 跳过
    ├─ 资金不足? → 跳过
    ├─ analyze_signal_v6b(最近100根K线) → score, analysis
    ├─ score < 70? → 跳过
    ├─ LONG 且 score < 85? → 跳过
    ├─ MA20 斜率过滤 → 不符合则跳过
    ├─ 方向限制检查 → 超限则跳过
    ├─ 可用资金 < 50? → 跳过
    ├─ calculate_position_size() → amount, leverage(3x)
    ├─ amount < 50? → 跳过
    └─ 建仓，设置 stop_loss / trailing 参数
```

---

## 8. 策略特征总结

| 特征 | V6 值 |
|---|---|
| 本金 | 10000 USDT |
| 评分体系 | v4.2 基础(100) + MACD/ADX/BB 加分(+25) |
| 最高分 | ~131 (含 SHORT bias) |
| SHORT 开仓门槛 | ≥ 70 分 |
| LONG 开仓门槛 | ≥ 85 分（更严格） |
| 杠杆 | 固定 3x |
| 最大持仓 | 20 个 |
| 冷却期 | 单币种冷却 1 小时 |
| 止损 | ROI -10%（≈价格反向 3.33%），无 min_hold 保护 |
| 移动止盈启动 | ROI +6%（≈价格顺向 2%） |
| 移动止盈回撤 | 从峰值回撤 3% |
| SHORT 偏好 | 评分 ×1.05 |
| 趋势过滤 | MA20 斜率: LONG < -2% 拒绝, SHORT > +1% 拒绝 |
| 手续费 | 0.05% 双边 |
| 资金费率 | 0.01% / 8h |
| K 线周期 | 1 小时 |
| Lookback | 100 根 K 线 |
| 扫描频率 | 止损检查每 60s，开仓扫描每 1H 整点 |
| 最小开仓量 | 100 USDT |
| 最大持仓时间 | 48 小时 |
| 币种 | 133 个（22 跳过） |
| 服务器 | 139.162.31.86 (SSH 26026) |

### 为什么能每年盈利？

1. **SHORT 偏好设计**: SHORT 门槛低(70)、LONG 门槛高(85)、SHORT 评分 ×1.05 → 偏做空，在加密货币的频繁回调中获利
2. **高持仓数 + 低杠杆**: 15 个仓位 × 3x → 分散风险，单笔亏损影响小
3. **宽松冷却 + 严格过滤**: 只等 1 小时但评分门槛高 → 不错过机会但只做高质量信号
4. **移动止盈**: 不设固定止盈目标，让利润奔跑 → 大行情中吃到更多利润
5. **RSI/趋势冲突惩罚**: 减少在震荡区间误判的交易
6. **MA20 斜率过滤**: 阻止逆势交易（下跌趋势中不做多，上涨趋势中不做空）
