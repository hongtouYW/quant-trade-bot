# Quant V4-Lite 量化交易系统

基于技术指标的加密货币永续合约量化交易系统。支持多策略信号检测、4层风控、模拟/实盘双模式，以及完整的回测框架。

## 核心功能

- 5套交易策略: 趋势跟踪、箱体突破、均值回归、波动突破、资金费套利
- 6种市场状态识别 + 策略路由
- 4层风控体系: 单笔/日内/系统/账户级别
- 3级币种筛选: 流动性 -> 趋势评分 -> 相关性去重
- V4增强: 订单簿分析、智能下单(maker/chase/market)、多交易所共识
- 时段参数覆盖 (亚洲/欧洲/美洲)
- PostgreSQL 交易记录持久化
- Flask 中文监控面板 (port 5210)
- Telegram 告警通知

## 项目结构

```
quant-v4lite/
├── config/                  # 配置文件
│   ├── config.yaml          # 生产配置
│   ├── config.dev.yaml      # 开发配置
│   ├── config.backtest.yaml # 回测配置
│   └── economic_calendar.json
├── src/
│   ├── main.py              # 入口
│   ├── core/                # 配置、模型、枚举、异常
│   ├── exchange/            # 交易所接口 (Binance ccxt)
│   ├── data/                # 数据采集、缓存、数据库
│   ├── indicators/          # 技术指标 (EMA/ADX/RSI/ATR/BB/MACD)
│   ├── analysis/            # 市场分析 (状态识别/筛选/相关性/订单簿)
│   ├── strategy/            # 交易策略 (5套)
│   ├── risk/                # 风控 (仓位/止损/风控/组合/时段)
│   ├── execution/           # 订单与持仓管理
│   ├── backtest/            # 回测引擎
│   ├── adaptive/            # 自适应参数
│   ├── bot/                 # 主引擎
│   ├── web/                 # Flask 监控面板
│   └── utils/               # 工具 (日志/Telegram/辅助函数)
├── scripts/                 # 脚本 (历史数据/回测/部署)
├── tests/                   # 测试 (103个)
├── Dockerfile
└── docker-compose.yaml
```

## 快速开始

### 环境准备

```bash
pip install -r requirements.txt
```

### 配置

1. 复制 `.env.example` 为 `.env`，填入 API Key 和数据库信息
2. 选择配置文件:
   - 生产: `config/config.yaml`
   - 开发: `config/config.dev.yaml` (DEBUG日志, 30币种, 60秒扫描)
   - 回测: `config/config.backtest.yaml` (全策略开启, V4模块关闭)

### 运行

```bash
# 模拟交易 (默认)
python src/main.py

# 指定配置
python src/main.py --config config/config.dev.yaml

# 回测
python scripts/run_backtest.py --days 30

# 拉取历史数据
python scripts/fetch_history.py --days 90
```

## 测试

```bash
# 全部测试 (103个)
pytest tests/ -v

# 单模块测试
pytest tests/test_indicators.py -v
pytest tests/test_risk.py -v
pytest tests/test_backtest.py -v
```

## 部署

### 手动部署

```bash
bash scripts/deploy.sh
```

### Docker 部署

```bash
docker-compose up -d
```

服务组件: bot + web (port 5210) + redis + postgres

### Supervisord

生产环境通过 supervisord 管理进程，部署在 `/opt/quant-v4lite/`。

## 监控

- Web 面板: `http://<server>:5210/`
- Telegram: 开仓/平仓/风控/日报通知
- 日志: `logs/trading.log`
- 状态文件: `status.json` (主引擎每轮写入)

## 技术栈

- Python 3.9+, asyncio
- ccxt (交易所接口)
- pandas, numpy, ta (技术指标)
- asyncpg (PostgreSQL), aioredis (Redis)
- Flask (Web面板)
- matplotlib (回测图表)
