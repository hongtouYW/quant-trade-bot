# 🧪 模拟交易1周测试指南

## 🎯 测试目标
运行模拟交易系统1周，验证策略有效性

## ⚙️ 当前策略配置（选项C - 中线）

```
📊 多时间框架策略:
├─ 趋势判断: 日线 (1d) - 判断做多/做空方向
├─ 入场信号: 15分钟 (15m) - 寻找精确入场点
└─ 风险控制: 5分钟 (5m) - 监控止损止盈

⏰ 运行频率:
├─ 扫描新机会: 每5分钟
├─ 检查持仓: 每30秒
└─ 日报发送: 每天凌晨1:00

💰 交易设置:
├─ 初始资金: $1000
├─ 杠杆倍数: 3x
├─ 止损: -3%
├─ 止盈: +6%
└─ 交易对: BTC, ETH, XMR, BNB, SOL
```

## 🚀 启动测试

### 步骤1: 准备配置（首次）
```bash
cd /Users/hongtou/newproject/quant-trade-bot

# 复制配置模板
cp config.json.example config.json

# 编辑配置文件（填写API密钥）
nano config.json
```

### 步骤2: 启动系统
```bash
# 一键启动
./start_test_trading.sh

# 或手动启动
python3 integrated_trading_system.py
```

### 步骤3: 同时启动Web监控
```bash
# 新开一个终端窗口
cd /Users/hongtou/newproject/quant-trade-bot
python3 web_monitor.py
```

访问: http://localhost:5001

## 📊 监控和查看

### 实时查看日志
```bash
tail -f trading_test.log
```

### 查看交易记录
```bash
python3 view_trading_records.py
```

### Web界面监控
浏览器打开: http://localhost:5001
- 实时余额和收益率
- 当前持仓
- 交易历史
- 各币种表现

## 📈 1周测试检查点

### 每天检查 (推荐)
```bash
# 快速查看
python3 view_trading_records.py

# 检查内容:
✓ 当前余额
✓ 收益率
✓ 交易次数
✓ 胜率
✓ 是否有持仓
✓ Telegram通知是否正常
```

### 1周后全面分析
```bash
# 生成完整报告
python3 generate_report.py --summary 7

# 分析重点:
✓ 总收益率 (目标: >0%)
✓ 胜率 (目标: >50%)
✓ 最大回撤 (目标: <20%)
✓ 交易频率 (预期: 5-15笔/周)
✓ 各币种表现
✓ 策略是否稳定
```

## 🔍 关键指标说明

### ✅ 好的表现
- 收益率: +5% ~ +15%
- 胜率: 50% ~ 70%
- 交易次数: 5-15笔
- 最大单笔亏损: <5%
- 有稳定盈利币种

### ⚠️ 需要调整
- 收益率: <-10%
- 胜率: <40%
- 交易次数: 0-2笔 (信号太少)
- 交易次数: >30笔 (过度交易)
- 所有币种都亏损

## 🛠️ 常用命令

```bash
# 启动系统
./start_test_trading.sh

# 查看进程
ps aux | grep integrated_trading_system

# 停止系统
pkill -f integrated_trading_system

# 查看日志
tail -f trading_test.log

# 查看交易
python3 view_trading_records.py

# 备份数据库
cp paper_trading.db backups/test_$(date +%Y%m%d).db

# 查看数据库
sqlite3 paper_trading.db
```

## 📱 Telegram通知

测试期间你会收到:
1. **交易通知**: 每笔买入/卖出
2. **日报**: 每天凌晨1点
3. **风险提醒**: 超过止损/止盈

## 🎯 1周后决策

### 如果表现好 ✅
- 继续运行2周
- 考虑增加初始资金
- 优化参数
- 准备上线服务器

### 如果表现一般 ⚙️
- 分析哪些币种表现好
- 调整止损止盈比例
- 尝试其他时间框架
- 优化入场条件

### 如果表现差 ⚠️
- 检查是否市场环境不佳
- 尝试选项A(短线)或选项B(长线)
- 调整技术指标参数
- 减少杠杆倍数

## 💡 测试技巧

1. **不要频繁修改**: 让策略运行完整1周
2. **记录市场环境**: 震荡/上涨/下跌
3. **对比手动判断**: 看策略是否比你更好
4. **关注胜率和盈亏比**: 比单纯收益率重要
5. **耐心观察**: 1周数据可能不够，建议2-4周

## 📞 问题排查

### 系统不交易
- 检查API密钥是否正确
- 查看日志是否有错误
- 确认网络连接正常
- 市场可能没有信号

### Telegram收不到通知
- 检查Bot Token和Chat ID
- 手动测试发送
- 查看错误日志

### Web界面无数据
- 确认web_monitor.py正在运行
- 检查数据库是否有数据
- 刷新浏览器

---

**祝测试顺利！🎉**

有问题随时查看日志或联系支持。
