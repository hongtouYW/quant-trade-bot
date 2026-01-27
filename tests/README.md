# 测试文件目录

## 📁 目录说明
此目录用于存放所有测试相关的文件，方便统一管理和统计。

## 📋 文件分类

### 🧪 单元测试
- 测试单个功能模块
- 命名格式: `test_<模块名>.py`

### 🔄 集成测试
- 测试多个模块协同工作
- 命名格式: `integration_test_<功能名>.py`

### 📊 性能测试
- 测试系统性能和压力
- 命名格式: `performance_test_<功能名>.py`

### 🎯 功能测试
- 测试完整业务流程
- 命名格式: `functional_test_<功能名>.py`

### 📝 测试数据
- 测试用的数据文件
- 存放在 `test_data/` 子目录

### 📈 测试报告
- 测试结果和报告
- 存放在 `test_reports/` 子目录
- 命名格式: `test_report_<日期>_<时间>.json/html`

## 🚀 使用方法

### 运行所有测试
```bash
python -m pytest tests/
```

### 运行单个测试文件
```bash
python tests/test_xxx.py
```

### 运行特定测试
```bash
python -m pytest tests/test_xxx.py::test_function_name
```

## 📊 测试覆盖率
```bash
python -m pytest --cov=. tests/
```

## 📝 编写测试注意事项
1. 每个测试文件以 `test_` 开头
2. 每个测试函数以 `test_` 开头
3. 使用清晰的测试命名，说明测试的功能
4. 添加必要的注释说明测试目的
5. 测试完成后清理测试数据

## 🗂️ 建议的目录结构
```
tests/
├── README.md                    # 本文件
├── test_data/                   # 测试数据
│   ├── sample_trades.json
│   └── sample_config.json
├── test_reports/                # 测试报告
│   ├── test_report_20260123.json
│   └── test_report_20260123.html
├── unit/                        # 单元测试
│   ├── test_strategy.py
│   ├── test_database.py
│   └── test_telegram.py
├── integration/                 # 集成测试
│   ├── test_trading_flow.py
│   └── test_api_integration.py
└── performance/                 # 性能测试
    ├── test_database_performance.py
    └── test_strategy_speed.py
```

## 📌 现有测试文件迁移
将现有的测试文件逐步迁移到此目录：
- `test_system.py` → `tests/integration/test_trading_system.py`
- `test_long_short.py` → `tests/unit/test_long_short.py`
- `test_report_*.json` → `tests/test_reports/`
- `xmr_monitor/test_xmr_apis.py` → `tests/unit/test_xmr_apis.py`

## 🎯 测试目标
- ✅ 单元测试覆盖率 > 80%
- ✅ 所有核心功能有集成测试
- ✅ 关键路径有性能测试
- ✅ 定期执行回归测试
