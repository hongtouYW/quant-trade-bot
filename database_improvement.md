# 数据库改进方案

## 一、现有问题

| 字段 | 状态 | 问题 |
|------|------|------|
| fee | 后期补算 | 应在交易时实时计算 |
| funding_fee | 全部为0 | 期货每8小时有资金费，需定时记录 |
| score | 全部为0 | 入场时的评分应记录 |
| reason | 全部为空 | 交易原因应记录 |

## 二、建议新增的表

### 1. account_snapshots - 账户快照（追踪资金曲线）
```sql
CREATE TABLE account_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
    total_capital REAL,          -- 总资金
    available_capital REAL,      -- 可用资金
    margin_used REAL,            -- 占用保证金
    unrealized_pnl REAL,         -- 未实现盈亏
    realized_pnl REAL,           -- 已实现盈亏
    total_fees REAL,             -- 累计手续费
    total_funding_fees REAL,     -- 累计资金费
    open_positions INTEGER,      -- 持仓数量
    daily_pnl REAL,              -- 当日盈亏
    max_drawdown REAL            -- 最大回撤
);
```

### 2. funding_history - 资金费记录
```sql
CREATE TABLE funding_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
    trade_id INTEGER,            -- 关联的交易ID
    symbol TEXT,
    funding_rate REAL,           -- 资金费率
    position_value REAL,         -- 持仓价值
    funding_fee REAL,            -- 资金费金额
    direction TEXT,              -- 持仓方向
    FOREIGN KEY (trade_id) REFERENCES real_trades(id)
);
```

### 3. trade_signals - 交易信号记录
```sql
CREATE TABLE trade_signals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
    symbol TEXT,
    signal_type TEXT,            -- buy/sell/hold
    score INTEGER,               -- 评分
    rsi REAL,
    trend TEXT,                  -- bullish/bearish/neutral
    volume_ratio REAL,           -- 成交量比率
    reasons TEXT,                -- JSON格式的原因列表
    executed INTEGER DEFAULT 0,  -- 是否执行了交易
    trade_id INTEGER             -- 关联的交易ID
);
```

### 4. daily_stats - 每日统计
```sql
CREATE TABLE daily_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT UNIQUE,            -- 日期
    starting_capital REAL,       -- 起始资金
    ending_capital REAL,         -- 结束资金
    trades_opened INTEGER,       -- 开仓数
    trades_closed INTEGER,       -- 平仓数
    win_trades INTEGER,          -- 盈利交易数
    loss_trades INTEGER,         -- 亏损交易数
    total_pnl REAL,              -- 当日盈亏
    total_fees REAL,             -- 当日手续费
    total_funding_fees REAL,     -- 当日资金费
    best_trade REAL,             -- 最佳交易
    worst_trade REAL,            -- 最差交易
    max_drawdown REAL            -- 当日最大回撤
);
```

## 三、real_trades 表增强

```sql
-- 添加新字段
ALTER TABLE real_trades ADD COLUMN entry_score INTEGER DEFAULT 0;     -- 入场时评分
ALTER TABLE real_trades ADD COLUMN entry_rsi REAL;                    -- 入场时RSI
ALTER TABLE real_trades ADD COLUMN entry_trend TEXT;                  -- 入场时趋势
ALTER TABLE real_trades ADD COLUMN actual_entry_price REAL;           -- 实际成交价（含滑点）
ALTER TABLE real_trades ADD COLUMN actual_exit_price REAL;            -- 实际平仓价（含滑点）
ALTER TABLE real_trades ADD COLUMN slippage REAL DEFAULT 0;           -- 滑点
ALTER TABLE real_trades ADD COLUMN max_profit REAL DEFAULT 0;         -- 持仓期间最大盈利
ALTER TABLE real_trades ADD COLUMN max_loss REAL DEFAULT 0;           -- 持仓期间最大亏损
ALTER TABLE real_trades ADD COLUMN duration_minutes INTEGER;          -- 持仓时长（分钟）
ALTER TABLE real_trades ADD COLUMN close_reason TEXT;                 -- 平仓原因(止盈/止损/手动/信号)
```

## 四、account_config 建议配置项

| key | 说明 | 示例值 |
|-----|------|--------|
| initial_capital | 初始资金 | 2000 |
| target_profit | 目标利润 | 3400 |
| max_position_size | 单笔最大仓位 | 500 |
| max_positions | 最大持仓数 | 10 |
| default_leverage | 默认杠杆 | 3 |
| stop_loss_pct | 默认止损比例 | 1.5 |
| take_profit_pct | 默认止盈比例 | 2.5 |
| fee_rate | 手续费率 | 0.001 |
| min_score | 最低入场评分 | 60 |
| trading_enabled | 是否启用交易 | 1 |

## 五、实施优先级

1. **高优先级**（立即实施）
   - 修复 fee 实时计算
   - 记录 score 和 reason
   - 创建 account_snapshots 表

2. **中优先级**（下一步）
   - 创建 funding_history 表
   - 创建 daily_stats 表
   - 添加 real_trades 增强字段

3. **低优先级**（后续优化）
   - 创建 trade_signals 表
   - 添加更多 account_config 配置
