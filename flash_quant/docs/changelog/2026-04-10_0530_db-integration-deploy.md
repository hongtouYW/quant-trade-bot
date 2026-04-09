# 改动记录: DB 集成 + 服务器部署

## 基本信息
- **时间**: 2026-04-10 05:30 (MYT)
- **改动者**: Claude
- **改动类型**: 🔄 核心改动 — Mock 数据 → 真实 MySQL 读写

---

## 改动原因 (Why)

Hongtou 要求 "所有功能保存 DB" + "部署到 139.162.31.86 先看效果"。
去掉所有 mock 模拟数据,改为真实 Binance WebSocket 数据 → MySQL 存储 → Dashboard 读取。

---

## 改动内容 (What)

### 新增文件
| 文件 | 说明 |
|---|---|
| `models/db_ops.py` | **数据库操作函数** — 全部 DB 读写集中在这里 (信号/交易/持仓/日统计/审计/Dashboard查询/日历数据) |

### 修改文件
| 文件 | 改动 |
|---|---|
| `executor/paper_executor.py` | 重写: 从内存操作 → **全部写 DB** (trades + positions + audit_logs) |
| `scanner/tier1_scalper.py` | 加 `save_signal()` — 每个信号写入 `signals` 表 |
| `engine.py` | 从 DB 读持仓数 (`count_open_trades` + `get_open_symbols`) |
| `app.py` | **完全重写**: 删除 mock 数据, 全部从 DB 查询 |
| `config/settings.py` | 支持 SQLite (本地) / MySQL (服务器) 自动切换 |
| `ws/binance_ws.py` | 修复 websockets v16 重连 `no running event loop` 错误 |
| `web/templates/home.html` | 适配 DB 字段名 |

### 服务器部署
| 操作 | 详情 |
|---|---|
| 服务器 | 139.162.31.86:5114 (SSH port 26026) |
| MySQL | `CREATE DATABASE flash_quant_db` + user `flash_quant_user` |
| 安装包 | websockets, SQLAlchemy, redis, structlog |
| DB 建表 | 6 张表全部创建 (signals/trades/positions/daily_stats/circuit_breakers/audit_logs) |
| Supervisord | flash-quant-web + flash-quant-engine 两个进程 |
| Binance WS | **已连接**, 延迟 1-5ms, 50 symbols × 3 intervals = 150 streams |
| Spike 验证 | BTC close=72426.40 lat=1ms ✅ |

### 数据流
```
Binance WebSocket → kline_cache (内存) → tier1_scalper (30s扫描)
  → wick/cvd/funding 过滤 → save_signal (MySQL signals表)
  → risk_manager 检查 → paper_executor 开仓 (MySQL trades+positions表)
  → position_monitor (30s) → 止损/止盈/超时 → 平仓 (MySQL 更新)
  → Dashboard (Flask) → 从 MySQL 读取 → 显示
```

---

## Spike 测试结果 (服务器)
```
✅ Binance WS 连接: 成功
✅ 延迟: 1-5ms (远超预期的 30ms)
✅ BTC 实时价格: 72426.40
✅ 50 symbols 150 streams: 连接正常
```

---

## 影响范围 (Impact)

- ✅ Dashboard 从 mock → 真实 DB 数据
- ✅ 信号/交易/持仓全部持久化到 MySQL
- ✅ 服务器已部署运行
- ⚠️ K线 warmup 需 ~100 分钟 (20根 5min K线)
- ⚠️ 平静行情信号稀少 (量比 ≥ 5x 条件严格)

---

**状态**: ✅ 已完成
