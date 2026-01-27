# 🎯 智能交易监控系统 (2合1)

## 📊 功能说明

这是一个智能的二合一监控系统，会根据持仓状态自动切换模式：

### 模式1: 持仓监控 ✅
**触发条件**: `my_xmr_position.json` 中 `status = "OPEN"`

**功能**:
- 每5分钟监控持仓盈亏
- 发送Telegram通知包含:
  - 💰 现价
  - 📈 入场价
  - 📊 涨跌幅
  - 💎 杠杆倍数
  - 💵 ROI
  - 💰 盈亏金额
  - ⏰ 更新时间

### 模式2: 信号扫描 🔍
**触发条件**: 无活跃持仓 (status ≠ "OPEN")

**功能**:
- 扫描 XMR, BTC, ETH, ADA 买入机会
- 分析技术指标:
  - RSI (相对强弱指标)
  - MA20/MA50 (移动平均线)
  - 成交量变化
- 发现买入信号时发送Telegram通知

## 🚀 使用方法

### 启动监控
```bash
cd /Users/hongtou/newproject/quant-trade-bot/xmr_monitor
./start_smart_trading.sh
```

### 查看日志
```bash
tail -f logs/smart_trading_monitor.log
```

### 停止监控
```bash
kill $(pgrep -f smart_trading_monitor.py)
```

### 快速测试
```bash
python3 test_smart_trading.py
```

## 📱 Telegram通知格式

### 持仓更新通知
```
🎯 XMR 持仓更新 🟢 做多

💰 现价: $460.42
📈 入场: $464.65
📊 涨跌: -0.91%
💎 杠杆: 10x

━━━━━━━━━━━━━━
💵 ROI: 🔴-9.10%
💰 盈亏: 📉$-91.00U

⏰ 更新时间: 00:05:19
```

### 买入信号通知
```
🚨 买入信号提醒 (技术指标)

⏰ 扫描时间: 00:05:19
📊 发现 1 个机会
━━━━━━━━━━━━━━

1. XMR 📈
💰 现价: $460.42
📊 RSI: 71.6
📈 成交量: 1.86x
💡 信心度: 45%
📝 理由: 均线多头, 成交量放大

建议:
🛡️ 止损: $437.40 (-5%)
🎯 止盈: $497.25 (+8%)
```

## 🔧 配置文件

### 持仓信息
文件: `/Users/hongtou/newproject/quant-trade-bot/my_xmr_position.json`

```json
{
  "symbol": "XMR/USDT",
  "entry_price": 464.65,
  "leverage": 10,
  "position_size": 1000,
  "status": "OPEN"  // OPEN=持仓监控, CLOSED=信号扫描
}
```

### 监控币种
可在 `smart_trading_monitor.py` 中修改:
```python
self.watch_symbols = ['XMR', 'BTC', 'ETH', 'ADA']
```

## 📊 技术指标说明

### RSI (相对强弱指标)
- < 35: 超卖，可能买入机会
- 35-40: 偏低
- 40-70: 正常
- > 70: 超买，谨慎

### 均线信号
- 价格 > MA20 > MA50: 多头排列，强势
- 价格 > MA20: 短期向上
- 价格 < MA20 < MA50: 空头排列

### 成交量
- > 1.5倍平均: 放量
- < 0.8倍平均: 缩量

## ✅ 优势特性

1. **智能切换**: 自动根据持仓状态切换模式
2. **无骚扰**: 没持仓不发持仓通知，有持仓不发买入信号
3. **技术分析**: 多维度技术指标综合判断
4. **风险建议**: 每个信号都给出止损止盈建议
5. **实时监控**: 5分钟更新，及时把握机会

## 🔄 与原有系统对比

### 原系统
- `xmr_simple_telegram.py`: 只监控持仓
- `xmr_memes_monitor.py`: 只扫描买入信号
- 需要手动启动两个脚本

### 新系统 ✨
- `smart_trading_monitor.py`: 二合一自动切换
- 一个脚本搞定所有需求
- 智能判断，零配置

---

**当前状态**: 
- 进程PID: 89020
- 运行模式: 持仓监控 (XMR/USDT)
- 通知间隔: 5分钟
