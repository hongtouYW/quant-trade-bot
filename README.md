# Quant Trading Bot - 量化交易机器人

## 📁 项目结构

```
quant-trade-bot/
├── config/                      # 配置文件目录
│   ├── config.json             # 主配置文件（API密钥等）
│   ├── config.json.example     # 配置模板
│   ├── config_manager.py       # 配置管理器
│   ├── config_multi_timeframe.py  # 多时间框架配置
│   ├── secure_config.py        # 安全配置
│   ├── server_config_template.json  # 服务器配置模板
│   └── cron_config.txt         # 定时任务配置
│
├── src/                         # 源代码目录
│   ├── core/                    # 核心交易系统
│   │   ├── main.py             # 主程序入口
│   │   ├── integrated_trading_system.py  # 集成交易系统
│   │   ├── enhanced_paper_trading.py     # 增强模拟交易
│   │   ├── live_paper_trading.py         # 实时模拟交易
│   │   ├── paper_trading_env.py          # 模拟交易环境
│   │   └── realtime_trader.py            # 实时交易器
│   │
│   ├── strategy/                # 交易策略
│   │   ├── enhanced_strategy.py          # 增强策略
│   │   ├── simple_enhanced_strategy.py   # 简化增强策略
│   │   ├── simple_multi_timeframe.py     # 简单多时间框架
│   │   └── real_time_multi_timeframe.py  # 实时多时间框架
│   │
│   ├── dashboard/               # 仪表板和Web界面
│   │   ├── dashboard.py         # 主仪表板
│   │   ├── web_dashboard.py     # Web仪表板
│   │   ├── web_monitor.py       # Web监控
│   │   ├── simple_dashboard.py  # 简单仪表板
│   │   ├── simple_dashboard_enhanced.py  # 增强简单仪表板
│   │   ├── market_monitor_dashboard.py   # 市场监控仪表板
│   │   ├── trading_dashboard_app.py      # 交易仪表板应用
│   │   ├── unified_dashboard.py          # 统一仪表板
│   │   ├── history_app.py       # 历史数据应用
│   │   └── trading_history_app.py        # 交易历史应用
│   │
│   ├── database/                # 数据库管理
│   │   ├── database_framework.py         # 数据库框架
│   │   ├── database_analyzer.py          # 数据库分析器
│   │   ├── database_status.py            # 数据库状态
│   │   ├── database_ui.py                # 数据库UI
│   │   ├── migration_tool.py             # 迁移工具
│   │   └── data_migration_tool.py        # 数据迁移工具
│   │
│   ├── tools/                   # 工具脚本
│   │   ├── big_money_tracker.py          # 大资金追踪
│   │   ├── potential_coin_scanner.py     # 潜力币扫描
│   │   ├── market_risk_assessment.py     # 市场风险评估
│   │   ├── system_readiness_checker.py   # 系统就绪检查
│   │   ├── generate_report.py            # 报告生成器
│   │   ├── view_trading_records.py       # 查看交易记录
│   │   ├── generate_yearly_data.py       # 生成年度数据
│   │   ├── generate_custom_yearly_data.py  # 生成自定义年度数据
│   │   ├── generate_simple_data.py       # 生成简单数据
│   │   └── trading_simulator.py          # 交易模拟器
│   │
│   └── security/                # 安全模块
│       ├── api_security.py      # API安全
│       ├── concurrency_protection.py  # 并发保护
│       └── exception_handler.py       # 异常处理
│
├── data/                        # 数据文件目录
│   ├── db/                      # 数据库文件
│   │   ├── paper_trading.db     # 模拟交易数据库
│   │   ├── trading_data.db      # 交易数据数据库
│   │   └── trading_simulator.db # 交易模拟数据库
│   │
│   └── reports/                 # 报告和数据文件
│       ├── backtest_*.json      # 回测报告
│       ├── test_report_*.json   # 测试报告
│       ├── test_report_*.html   # HTML测试报告
│       ├── system_readiness_report_*.json  # 系统就绪报告
│       ├── latest_status.json   # 最新状态
│       ├── latest_trades.json   # 最新交易
│       └── yearly_comparison.json  # 年度对比
│
├── scripts/                     # 脚本目录
│   ├── start_trading_system.sh  # 启动交易系统
│   ├── start_paper_trading.sh   # 启动模拟交易
│   ├── start_web_dashboard.sh   # 启动Web仪表板
│   ├── start_web_monitor.sh     # 启动Web监控
│   ├── start_enhanced_dashboard.sh  # 启动增强仪表板
│   ├── start_history_app.sh     # 启动历史应用
│   ├── start_test_trading.sh    # 启动测试交易
│   ├── deploy.sh                # 部署脚本
│   ├── check_server.sh          # 检查服务器
│   ├── github_setup.sh          # GitHub设置
│   └── quick_start.sh           # 快速启动
│
├── tests/                       # 测试目录
│   ├── unit/                    # 单元测试
│   │   ├── test_system.py      # 系统测试
│   │   └── test_long_short.py  # 多空测试
│   ├── integration/             # 集成测试
│   ├── performance/             # 性能测试
│   ├── test_data/               # 测试数据
│   ├── test_reports/            # 测试报告
│   └── README.md                # 测试说明
│
├── docs/                        # 文档目录
│   ├── README.md                # 主文档
│   ├── DATABASE_GUIDE.md        # 数据库指南
│   ├── DEPLOYMENT_GUIDE.md      # 部署指南
│   ├── DEPLOYMENT_CHECKLIST.md  # 部署检查清单
│   ├── PAPER_TRADING_GUIDE.md   # 模拟交易指南
│   ├── FEATURES.md              # 功能说明
│   ├── FILE_ORGANIZATION.md     # 文件组织
│   ├── SYSTEM_STATUS.md         # 系统状态
│   ├── TESTING_CHECKLIST.md     # 测试检查清单
│   ├── TRADING_SYSTEM_README.md # 交易系统说明
│   ├── README_AUTO_GIT.md       # 自动Git说明
│   ├── AUTO_GIT_STATUS.md       # 自动Git状态
│   ├── push_status.md           # 推送状态
│   └── quick_test_guide.md      # 快速测试指南
│
├── logs/                        # 日志目录
├── templates/                   # HTML模板目录
├── utils/                       # 工具函数目录
├── xmr_monitor/                 # XMR监控目录
├── strategy/                    # 策略相关（旧）
├── strategy_tests/              # 策略测试（旧）
├── .config/                     # 配置（系统）
├── .env                         # 环境变量
├── .env.example                 # 环境变量示例
├── .gitignore                   # Git忽略文件
├── requirements.txt             # Python依赖
└── FILE_REORGANIZATION_PLAN.md  # 文件重组计划

```

## 🚀 快速开始

### 1. 安装依赖
```bash
pip install -r requirements.txt
```

### 2. 配置
```bash
cp config/config.json.example config/config.json
# 编辑 config/config.json 添加你的API密钥
```

### 3. 启动交易系统
```bash
# 启动模拟交易
./scripts/start_paper_trading.sh

# 启动Web仪表板
./scripts/start_web_dashboard.sh

# 启动Web监控
./scripts/start_web_monitor.sh
```

## 📊 功能特性

- ✅ 多时间框架交易策略（1d/15m/5m）
- ✅ 模拟交易系统（支持3x杠杆）
- ✅ 实时价格监控和Telegram通知
- ✅ Web仪表板实时数据展示
- ✅ 数据库记录所有交易
- ✅ 每日自动报告生成
- ✅ 多币种支持（BTC/ETH/XMR/BNB/SOL）
- ✅ 完整的测试框架

## 📚 文档

详细文档请查看 `docs/` 目录：
- [部署指南](docs/DEPLOYMENT_GUIDE.md)
- [数据库指南](docs/DATABASE_GUIDE.md)
- [模拟交易指南](docs/PAPER_TRADING_GUIDE.md)
- [测试指南](tests/README.md)

## 🔧 配置说明

主要配置文件位于 `config/` 目录：
- `config.json` - API密钥、Telegram配置等
- `config_multi_timeframe.py` - 多时间框架策略配置
- `secure_config.py` - 安全配置管理

## 🧪 测试

```bash
# 运行所有测试
python -m pytest tests/

# 运行单元测试
python -m pytest tests/unit/

# 运行集成测试
python -m pytest tests/integration/
```

## 📈 数据管理

数据文件位于 `data/` 目录：
- `data/db/` - SQLite数据库文件
- `data/reports/` - 交易报告和统计数据

## 🛡️ 安全

安全相关模块位于 `src/security/`：
- API密钥加密存储
- 并发访问保护
- 异常处理和日志记录

## 📦 项目结构优势

1. **清晰的分类**：按功能分类，易于查找和维护
2. **可扩展性**：模块化设计，便于添加新功能
3. **标准化**：符合Python项目最佳实践
4. **易于测试**：测试文件独立管理
5. **文档齐全**：所有文档集中管理

## 🤝 贡献

欢迎提交Issue和Pull Request！

## 📄 许可证

MIT License
