# 改动记录: Sprint 1 — Core + Filters + Tests

## 基本信息
- **时间**: 2026-04-09 02:00
- **改动者**: 💻 Amelia (Dev) / Claude
- **改动类型**: 🆕 代码实现 (Sprint 1 第一批)
- **关联**: tech-spec.md Sprint 1 + Sprint 3 (过滤器提前做)

---

## 改动原因 (Why)

Hongtou 确认 "开工,不用问 yes"。开始 Sprint 1 代码实现。
因为本地无法连接 Binance (网络封锁), Spike 推迟到服务器验证。
先实现不依赖网络的模块: core/ + config/ + filters/ + tests/。

---

## 改动内容 (What)

### 项目结构创建
```
flash_quant/
├── core/
│   ├── __init__.py
│   ├── constants.py        (BR-001~008 硬编码, get_leverage_tier)
│   ├── exceptions.py       (6 个自定义异常)
│   └── logger.py           (JSON 结构化日志)
├── config/
│   ├── __init__.py
│   └── settings.py         (.env 加载 + Settings 类)
├── filters/
│   ├── __init__.py
│   ├── wick_filter.py      (FR-010 反插针)
│   ├── cvd_filter.py       (FR-011 CVD)
│   ├── funding_filter.py   (FR-012 费率)
│   └── blacklist_filter.py (BR-002/003 黑名单)
├── tests/
│   ├── unit/
│   │   ├── test_constants.py      (8 tests)
│   │   ├── test_wick_filter.py    (14 tests)
│   │   ├── test_cvd_filter.py     (10 tests)
│   │   ├── test_funding_filter.py (9 tests)
│   │   └── test_blacklist_filter.py (9 tests)
│   └── integration/
├── spike_ws_test.py        (Spike 脚本, 待服务器验证)
└── (其他空目录已创建)
```

### 测试结果
- **56 tests, 56 passed, 0 failed** ✅
- 覆盖: constants, wick_filter, cvd_filter, funding_filter, blacklist_filter

### 关键 Bug Fix
- CVD 过滤器 short 方向负数 tolerance 计算错误
  - 原因: `cvd_low * (1 + tolerance)` 在负数时会让阈值更极端
  - 修复: 改用 range-based tolerance 计算

### Spike 结论
- 本地网络无法访问 Binance (REST + WS 都不通, Google 正常)
- Spike 脚本已写好, 待 Vultr Tokyo 服务器部署后验证
- 不阻塞 Sprint 1-5 的本地开发

---

## 影响范围 (Impact)

- ✅ 新建 12 个 Python 文件
- ✅ 新建 5 个测试文件 (56 个测试用例)
- ✅ 所有过滤器 (Critical 模块) 已实现并测试
- ✅ BR-001~008 硬编码在 constants.py

---

## 后续步骤

1. ⏳ models/ — 7 张数据库表 (SQLAlchemy)
2. ⏳ risk/ — 风控引擎
3. ⏳ data/ + ws/ — 数据层
4. ⏳ scanner/ + executor/ — 扫描器 + 下单器
5. ⏳ web/ — Dashboard

---

**状态**: ✅ 已完成
