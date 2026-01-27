# 🚀 实盘模拟交易系统 v2.0 - 使用指南

## ✅ 系统功能

### 核心特性
- ✅ **自动交易**: 策略扫描 → 信号识别 → 自动开仓/平仓
- ✅ **杠杆支持**: 默认3倍杠杆，放大收益
- ✅ **数据库记录**: SQLite完整记录所有交易
- ✅ **Telegram通知**: 实时推送买卖信息
- ✅ **费用统计**: 自动计算滑点、手续费
- ✅ **风险管理**: 自动止损止盈

## 🚀 快速启动

### 方式1: 一键启动（推荐）
```bash
./start_paper_trading.sh
```

### 方式2: 直接运行
```bash
python3 integrated_trading_system.py
```

## 📊 系统配置

### 资金配置
```python
initial_balance = 1000      # 初始资金 $1000
leverage = 3                # 3倍杠杆
```

### 风险配置
```python
risk_per_trade = 2%         # 单笔风险 2%
max_position_size = 30%     # 最大仓位 30%
stop_loss = 3%              # 止损 3%
take_profit = 6%            # 止盈 6%
```

### 费用配置
```python
maker_fee = 0.1%            # Maker手续费
taker_fee = 0.1%            # Taker手续费
slippage = 0.05%            # 滑点
```

## 📱 Telegram通知示例

### 开仓通知
```
📈 开仓 - BTC/USDT
━━━━━━━━━━━━━━
买入详情
━━━━━━━━━━━━━━
价格: $89,558.76
数量: 0.003351
杠杆: 3x
━━━━━━━━━━━━━━
💵 仓位价值: $300.15
💎 保证金: $100.05
💸 手续费: $0.30
━━━━━━━━━━━━━━
🛡️ 止损: $86,871.99
🎯 止盈: $94,932.28
━━━━━━━━━━━━━━
💰 剩余余额: $899.65
```

### 平仓通知
```
📉 平仓 - BTC/USDT
━━━━━━━━━━━━━━
卖出详情
━━━━━━━━━━━━━━
价格: $92,500.00
数量: 0.003351
杠杆: 3x
原因: 止盈
━━━━━━━━━━━━━━
💵 仓位价值: $310.00
💸 手续费: $0.31
🟢 盈亏: +$29.54 (+29.46%)
━━━━━━━━━━━━━━
💰 当前余额: $929.19
📊 总盈亏: +$29.54
💸 总手续费: $0.61
📈 胜率: 100.0%
```

## 💾 数据库说明

### 数据库文件
`paper_trading.db` - SQLite数据库

### 数据表结构

#### trades 表 - 交易记录
```sql
- id: 交易ID
- timestamp: 时间
- symbol: 交易对
- side: 买/卖
- price: 价格
- quantity: 数量
- leverage: 杠杆
- cost: 成本
- fee: 手续费
- pnl: 盈亏
- pnl_pct: 盈亏百分比
- reason: 原因
- balance_after: 交易后余额
```

#### positions 表 - 持仓记录
```sql
- symbol: 交易对
- quantity: 数量
- entry_price: 入场价
- entry_time: 入场时间
- leverage: 杠杆
- stop_loss: 止损价
- take_profit: 止盈价
- cost: 成本
- status: 状态 (open/closed)
```

#### stats 表 - 统计数据
```sql
- timestamp: 时间
- balance: 余额
- total_pnl: 总盈亏
- total_trades: 总交易数
- winning_trades: 盈利次数
- losing_trades: 亏损次数
- win_rate: 胜率
- total_fees: 总手续费
```

## 📊 查看交易记录

### 查看所有信息
```bash
python3 view_trading_records.py --all
```

### 只查看交易记录
```bash
python3 view_trading_records.py --trades
```

### 只查看持仓
```bash
python3 view_trading_records.py --positions
```

### 只查看统计
```bash
python3 view_trading_records.py --stats
```

### 指定记录数量
```bash
python3 view_trading_records.py --trades --limit 50
```

## 💡 杠杆交易说明

### 杠杆如何工作

**示例**: 3倍杠杆买入BTC

1. **不用杠杆**:
   - 买入 $300 的BTC
   - 需要资金: $300
   - 价格涨10%: 赚 $30 (+10%)

2. **3倍杠杆**:
   - 买入 $300 的BTC
   - 需要保证金: $100 ($300/3)
   - 价格涨10%: 赚 $90 (+90%)
   - 价格跌10%: 亏 $90 (-90%)

### 风险提示
- ⚠️ 杠杆放大收益，也放大亏损
- ⚠️ 严格执行止损，控制风险
- ⚠️ 不要超过最大仓位限制

## 📈 交易流程

### 1. 市场扫描 (每5分钟)
```
🔍 扫描 BTC/USDT, ETH/USDT, XMR/USDT, BNB/USDT, SOL/USDT
📊 分析日线趋势
📊 分析15分钟入场点
```

### 2. 信号识别
```
✅ 至少2个技术指标确认
✅ 成交量放大1.5倍
✅ 符合趋势方向
✅ 风险收益比 ≥ 1:2
```

### 3. 自动开仓
```
📈 计算仓位大小
💰 模拟下单（含滑点）
💸 扣除手续费
💾 保存到数据库
📱 发送Telegram通知
```

### 4. 持续监控 (每30秒)
```
🔍 检查当前价格
🛡️ 检查止损触发
🎯 检查止盈触发
```

### 5. 自动平仓
```
📉 触发止损/止盈
💰 模拟平仓（含滑点）
💸 扣除手续费
📊 计算盈亏
💾 保存到数据库
📱 发送Telegram通知
```

## 🔧 常见问题

### Q: 没有收到Telegram通知？
A: 检查 config.json 中的 telegram 配置是否正确

### Q: 一直没有交易信号？
A: 正常，系统等待高质量信号（多个条件同时满足）

### Q: 如何修改杠杆？
A: 编辑 integrated_trading_system.py，修改 `leverage=3` 参数

### Q: 如何查看历史记录？
A: 运行 `python3 view_trading_records.py --all`

### Q: 数据库在哪里？
A: quant-trade-bot/paper_trading.db

### Q: 如何调整风险？
A: 编辑 enhanced_paper_trading.py 中的风险参数

## 📁 相关文件

- `enhanced_paper_trading.py` - 增强版交易引擎
- `integrated_trading_system.py` - 集成系统
- `simple_enhanced_strategy.py` - 策略引擎
- `view_trading_records.py` - 记录查看工具
- `start_paper_trading.sh` - 启动脚本
- `paper_trading.db` - 数据库文件

## ⚠️ 重要提醒

1. **这是模拟交易**，不会真实下单
2. 杠杆交易风险大，请谨慎
3. 定期检查交易记录
4. 根据市场调整参数
5. 建议先观察1-2周

## 🎯 示例操作

### 启动系统
```bash
./start_paper_trading.sh
```

### 查看实时状态（另开终端）
```bash
# 查看交易记录
python3 view_trading_records.py --all

# 持续监控
watch -n 5 'python3 view_trading_records.py --stats'
```

### 查看数据库（SQLite命令行）
```bash
sqlite3 paper_trading.db

# 查看所有表
.tables

# 查看最近交易
SELECT * FROM trades ORDER BY timestamp DESC LIMIT 5;

# 查看当前持仓
SELECT * FROM positions WHERE status='open';

# 退出
.quit
```

---

**版本**: v2.0  
**最后更新**: 2026-01-23  
**状态**: ✅ 完整功能，可用于模拟交易
