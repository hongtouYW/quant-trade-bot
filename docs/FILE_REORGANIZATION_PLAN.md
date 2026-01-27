# 项目文件整理方案

## 📋 整理目标
将根目录文件按功能分类到对应目录，保持项目结构清晰。

## 📁 目录结构规划

```
quant-trade-bot/
├── config/                      # 配置文件
│   ├── config.json
│   ├── config.json.example
│   ├── config_multi_timeframe.py
│   ├── config_manager.py
│   ├── secure_config.py
│   ├── server_config_template.json
│   ├── cron_config.txt
│   └── .env.example
├── docs/                        # 文档
│   ├── README.md
│   ├── DATABASE_GUIDE.md
│   ├── DEPLOYMENT_GUIDE.md
│   ├── DEPLOYMENT_CHECKLIST.md
│   ├── PAPER_TRADING_GUIDE.md
│   ├── FEATURES.md
│   ├── FILE_ORGANIZATION.md
│   ├── SYSTEM_STATUS.md
│   ├── TESTING_CHECKLIST.md
│   ├── TRADING_SYSTEM_README.md
│   ├── README_AUTO_GIT.md
│   ├── AUTO_GIT_STATUS.md
│   ├── push_status.md
│   └── quick_test_guide.md
├── scripts/                     # 启动和部署脚本
│   ├── start_*.sh
│   ├── deploy.sh
│   ├── check_server.sh
│   ├── github_setup.sh
│   └── quick_start.sh
├── src/                         # 源代码
│   ├── core/                    # 核心交易系统
│   │   ├── main.py
│   │   ├── integrated_trading_system.py
│   │   ├── enhanced_paper_trading.py
│   │   ├── live_paper_trading.py
│   │   ├── paper_trading_env.py
│   │   └── realtime_trader.py
│   ├── strategy/                # 策略文件
│   │   ├── enhanced_strategy.py
│   │   ├── simple_enhanced_strategy.py
│   │   ├── simple_multi_timeframe.py
│   │   └── real_time_multi_timeframe.py
│   ├── dashboard/               # 仪表板
│   │   ├── dashboard.py
│   │   ├── web_dashboard.py
│   │   ├── web_monitor.py
│   │   ├── simple_dashboard.py
│   │   ├── simple_dashboard_enhanced.py
│   │   ├── market_monitor_dashboard.py
│   │   ├── trading_dashboard_app.py
│   │   ├── unified_dashboard.py
│   │   ├── history_app.py
│   │   └── trading_history_app.py
│   ├── database/                # 数据库
│   │   ├── database_framework.py
│   │   ├── database_analyzer.py
│   │   ├── database_status.py
│   │   ├── database_ui.py
│   │   ├── migration_tool.py
│   │   └── data_migration_tool.py
│   ├── tools/                   # 工具脚本
│   │   ├── big_money_tracker.py
│   │   ├── potential_coin_scanner.py
│   │   ├── market_risk_assessment.py
│   │   ├── system_readiness_checker.py
│   │   ├── generate_report.py
│   │   ├── view_trading_records.py
│   │   ├── generate_yearly_data.py
│   │   ├── generate_custom_yearly_data.py
│   │   ├── generate_simple_data.py
│   │   └── trading_simulator.py
│   └── security/                # 安全模块
│       ├── api_security.py
│       ├── concurrency_protection.py
│       └── exception_handler.py
├── data/                        # 数据文件
│   ├── db/                      # 数据库文件
│   │   ├── paper_trading.db
│   │   ├── trading_data.db
│   │   └── trading_simulator.db
│   ├── reports/                 # 报告文件
│   │   ├── *.json
│   │   └── *.html
│   └── backups/                 # 备份文件
├── tests/                       # 测试文件
│   ├── unit/
│   │   ├── test_long_short.py
│   │   └── test_system.py
│   ├── integration/
│   ├── performance/
│   ├── test_data/
│   └── test_reports/
├── logs/                        # 日志文件
├── templates/                   # HTML模板
├── utils/                       # 工具函数
├── xmr_monitor/                 # XMR监控
├── strategy/                    # 策略相关（保留）
├── strategy_tests/              # 策略测试（保留）
└── .config/                     # 配置（保留）
```

## 🔄 迁移计划

### 第一步：创建必要目录
- [x] config/
- [x] docs/
- [ ] src/core/
- [ ] src/strategy/
- [ ] src/dashboard/
- [ ] src/database/
- [ ] src/tools/
- [ ] src/security/
- [ ] data/db/
- [ ] data/reports/

### 第二步：移动文件（按优先级）

#### 优先级1：配置文件
- config.json → config/
- config.json.example → config/
- config_*.py → config/
- secure_config.py → config/
- server_config_template.json → config/
- cron_config.txt → config/
- .env.example → config/

#### 优先级2：文档
- *.md → docs/

#### 优先级3：脚本
- start_*.sh → scripts/
- deploy.sh, check_server.sh → scripts/
- github_setup.sh, quick_start.sh → scripts/

#### 优先级4：核心代码
- main.py → src/core/
- integrated_trading_system.py → src/core/
- enhanced_paper_trading.py → src/core/
- live_paper_trading.py → src/core/
- paper_trading_env.py → src/core/
- realtime_trader.py → src/core/

#### 优先级5：策略代码
- enhanced_strategy.py → src/strategy/
- simple_enhanced_strategy.py → src/strategy/
- simple_multi_timeframe.py → src/strategy/
- real_time_multi_timeframe.py → src/strategy/

#### 优先级6：仪表板
- dashboard.py → src/dashboard/
- web_*.py → src/dashboard/
- simple_dashboard*.py → src/dashboard/
- *_dashboard*.py → src/dashboard/
- unified_dashboard.py → src/dashboard/
- history_app.py → src/dashboard/
- trading_history_app.py → src/dashboard/

#### 优先级7：数据库
- database_*.py → src/database/
- *_migration*.py → src/database/

#### 优先级8：工具
- big_money_tracker.py → src/tools/
- potential_coin_scanner.py → src/tools/
- market_risk_assessment.py → src/tools/
- system_readiness_checker.py → src/tools/
- generate_*.py → src/tools/
- view_trading_records.py → src/tools/
- trading_simulator.py → src/tools/

#### 优先级9：安全模块
- api_security.py → src/security/
- concurrency_protection.py → src/security/
- exception_handler.py → src/security/

#### 优先级10：数据文件
- *.db → data/db/
- *.json (报告) → data/reports/
- *.html → data/reports/

#### 优先级11：测试文件
- test_*.py → tests/unit/
- test_report_*.* → tests/test_reports/
- system_readiness_report_*.json → tests/test_reports/

### 第三步：更新引用路径
- 更新所有import语句
- 更新配置文件路径
- 更新启动脚本路径
- 更新文档中的路径引用

### 第四步：清理
- 删除临时文件
- 删除重复文件
- 更新.gitignore

## ⚠️ 注意事项
1. 移动前先git commit当前状态
2. 使用git mv保持版本历史
3. 移动后立即更新引用
4. 逐步测试，确保功能正常
5. 更新README文档

## 🎯 预期效果
- ✅ 根目录文件从80+减少到10+
- ✅ 文件分类清晰，易于查找
- ✅ 符合Python项目最佳实践
- ✅ 便于后续维护和扩展
