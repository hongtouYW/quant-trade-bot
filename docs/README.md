# 量化交易机器人

一个基于Python的加密货币量化交易系统，支持多个交易所并具备实时监控功能。

## 功能特性

- 🚀 **多交易所支持**: Binance、Bitget等主流交易所
- 📊 **多种策略**: 移动平均线(MA)、相对强弱指数(RSI)、MACD等技术指标
- ⚡ **实时监控**: Web仪表板实时显示交易状态和账户信息
- 📱 **Telegram通知**: 交易信号和重要事件的即时推送
- 🛡️ **风险管理**: 止损、止盈、仓位控制等安全机制
- 📈 **回测功能**: 历史数据策略验证

## 项目结构

```
quant-trade-bot/
├── main.py                 # 主交易程序
├── simple_dashboard.py     # 简化监控面板
├── dashboard.py           # 完整监控面板
├── backtest.py            # 回测模块
├── config.json.example    # 配置文件示例
├── requirements.txt       # 依赖包列表
├── strategy/              # 交易策略模块
│   ├── __init__.py
│   ├── ma_strategy.py     # 移动平均线策略
│   ├── rsi_strategy.py    # RSI策略
│   └── macd_strategy.py   # MACD策略
└── utils/                 # 工具模块
    ├── __init__.py
    ├── data_fetcher.py     # 数据获取
    ├── notifier.py         # 通知模块
    ├── order_manager.py    # 订单管理
    └── risk_manager.py     # 风险管理
```

## 安装和配置

### 1. 安装依赖

```bash
pip install -r requirements.txt
```

### 2. 配置设置

1. 复制配置文件示例：
   ```bash
   cp config.json.example config.json
   ```

2. 编辑 `config.json` 填入你的API密钥：
   - 交易所API密钥和密码
   - Telegram机器人令牌和聊天ID
   - 交易参数设置

### 3. 创建Telegram机器人

1. 在Telegram中搜索 `@BotFather`
2. 发送 `/newbot` 创建新机器人
3. 获取bot token并填入配置文件
4. 获取你的chat_id（可以发送消息给 `@userinfobot`）

## 使用方法

### 启动交易机器人

```bash
python3 main.py
```

### 启动监控面板

```bash
# 简化版（推荐）
python3 simple_dashboard.py

# 完整版
python3 dashboard.py
```

访问 `http://localhost:5001` 查看实时监控数据。

### 运行回测

```bash
python3 backtest.py
```

## 安全注意事项

⚠️ **重要提醒**：

1. **永远不要**将包含真实API密钥的 `config.json` 提交到版本控制
2. 使用交易所的**测试网络**进行初期测试
3. 从**小额资金**开始，逐步增加投入
4. 定期备份配置和日志文件
5. 监控交易结果并及时调整策略

## 支持的交易所

- ✅ Binance
- ✅ Bitget  
- 🔄 其他交易所（基于ccxt库可扩展）

## 技术栈

- **Python 3.9+**
- **ccxt**: 加密货币交易库
- **Flask**: Web监控面板
- **pandas**: 数据分析
- **requests**: HTTP请求处理

## 许可证

此项目仅供学习和研究使用。交易有风险，投资需谨慎。

## 免责声明

本软件仅供教育和研究目的。使用本软件进行交易的任何损失，开发者不承担责任。请在充分了解风险的情况下谨慎使用。