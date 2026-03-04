# Paper Trader v0.6 发布说明

**版本代号：** Data Framework
**发布日期：** 2026-01-29
**访问地址：** http://139.162.41.38:5111/

---

## 版本亮点

### 数据库框架升级
- **5个表** - 完整的数据追踪体系
- **账户快照** - 每分钟记录资金状态
- **配置中心** - 参数可数据库管理

---

## 数据库结构

```
paper_trader.db
├── real_trades        (交易记录)
├── account_snapshots  (账户快照)
├── signal_history     (信号历史)
├── daily_stats        (每日统计)
└── config             (配置中心)
```

### 1. account_snapshots (账户快照)

| 字段 | 说明 |
|------|------|
| timestamp | 记录时间 |
| balance | 账户余额 |
| equity | 账户权益 |
| positions_value | 持仓价值 |
| unrealized_pnl | 未实现盈亏 |
| realized_pnl | 已实现盈亏 |
| max_drawdown | 最大回撤 |
| positions_count | 持仓数量 |

### 2. signal_history (信号历史)

| 字段 | 说明 |
|------|------|
| symbol | 币种 |
| signal_type | 信号类型 |
| score | 信号评分 |
| price | 当时价格 |
| atr_pct | ATR百分比 |
| executed | 是否执行 |

### 3. daily_stats (每日统计)

| 字段 | 说明 |
|------|------|
| date | 日期 |
| trades_count | 交易数 |
| win_count | 盈利数 |
| loss_count | 亏损数 |
| total_pnl | 总盈亏 |
| win_rate | 胜率 |

### 4. config (配置中心)

| 配置项 | 默认值 |
|--------|--------|
| initial_capital | 2000 |
| target_profit | 3400 |
| max_positions | 10 |
| default_leverage | 10 |
| stop_loss_pct | 0.015 |
| use_atr_stop | 1 |

---

## real_trades 新增字段

- entry_rsi - 入场RSI
- entry_trend - 入场趋势
- atr_pct - ATR百分比
- max_profit - 最大盈利
- max_loss - 最大亏损
- duration_minutes - 持仓时长

---

## 技术改进

### paper_trader.py 新增方法
- `record_snapshot()` - 记录账户快照
- `record_signal()` - 记录信号历史
- `update_daily_stats()` - 更新每日统计
- `get_config()` - 读取配置

---

## 系统状态

**当前持仓：** 6个
**快照频率：** 每分钟
**数据完整性：** ✅

---

**版本控制：**
- 上一版本: `v0.5` (ATR Smart Stop)
- 当前版本: `v0.6` (Data Framework)

**部署状态：** ✅ 已部署
