# 改动记录: 测试设计 + 技术规格

## 基本信息
- **时间**: 2026-04-08 16:00
- **改动者**: 🧪 Murat (TEA) + 💻 Amelia (Dev)
- **改动类型**: 📄 新增文档 (并行)
- **依赖**: prd.md, architecture.md
- **下游**: Phase 1 代码实现

---

## 改动原因 (Why)

按 BMad B 流程,架构完成后需要并行产出:
1. Murat: 测试设计 - 定义质量门和测试策略
2. Amelia: 技术规格 - 提供可实施的代码骨架

完成这两份后,Phase 0 规划阶段结束,即可进入 Sprint 1 实现。

---

## 改动内容 (What)

### 新增文件
| 文件 | 行数 | 作者 |
|---|---|---|
| `docs/test-design.md` | ~600 | 🧪 Murat |
| `docs/tech-spec.md` | ~900 | 💻 Amelia |
| `docs/changelog/2026-04-08_1600_test-design-and-tech-spec.md` | - | 本文档 |

---

## test-design.md 核心内容

### 测试金字塔
- 单元测试 70% (覆盖率 ≥80%, 关键模块 100%)
- 集成测试 25% (关键路径 100%)
- E2E 5% (Phase 2 实盘)

### 风险分级
- 🔴 Critical: filters/, risk/, executor/binance (100% 覆盖)
- 🟡 High: scanner/, ws/, data/cvd
- 🟢 Medium: web/, models/, config/
- ⚪ Low: scripts/, dashboard CSS

### 单元测试设计 (60+ 测试用例)
- Wick Filter: 10 个测试 (TC-WICK-001~010)
- CVD Filter: 10 个测试 + CVD 计算 4 个
- Risk Manager: 24 个测试 (单笔/连亏/时段/黑天鹅/同币)
- Position Sizing: 5 个测试 (周末+连亏组合)
- Blacklist: 6 个测试

### 集成测试
- Scanner Pipeline (happy/filtered/blocked)
- Executor Lifecycle
- WebSocket 重连和数据质量

### Phase 1 验收测试 (5 项硬性 Gate)
| AC | 指标 | 通过线 |
|---|---|---|
| AC-1 | 过滤器有效性 | ≥ 30% 提升 (A/B 对照) |
| AC-2 | 信号数量 | 日均 10-20 |
| AC-3 | 胜率 | ≥ 55% |
| AC-4 | 盈亏比 | ≥ 1.5 |
| AC-5 | 延迟 P99 | < 500ms |

### 性能测试 + 错误注入测试 (Chaos)
- 24h 持续运行测试
- WebSocket 断线 100 次重连测试
- API 5xx / MySQL 断 / Redis 断 / OOM 等 8 种故障注入

### 4 个质量门
- Gate 1: 代码提交 (单元 + 覆盖率)
- Gate 2: Phase 1 → Phase 2 (5项 AC)
- Gate 3: Phase 2 → Phase 3 (实盘验证)
- Gate 4: Phase 3 → Phase 4 (月化 + 回撤)

---

## tech-spec.md 核心内容

### 1. 技术清单
- requirements.txt (16 个核心包)
- .env.example 模板 (含所有环境变量)

### 2. 8 个核心模块的接口规格 + 伪代码
- `core/constants.py` - 8 条 BR 硬编码常量
- `filters/wick_filter.py` - 完整实现 (FR-010)
- `filters/cvd_filter.py` - 完整实现 (FR-011)
- `data/cvd_calculator.py` - aggTrade → CVD 计算 (FR-011)
- `scanner/tier1_scalper.py` - Tier 1 完整伪代码 (FR-001)
- `risk/risk_manager.py` - 风控总入口 (FR-030~034)
- `executor/paper_executor.py` - 模拟盘 (FR-020)
- `web/routes/api.py` - Dashboard JSON API

### 3. 数据库迁移脚本
- `scripts/init_db.py`

### 4. 启动入口
- `engine.py` - 扫描器进程
- `app.py` - Flask Web 进程

### 5. 部署清单
- `scripts/setup_server.sh` - Vultr 服务器一键初始化
- 完整 10 步部署流程

### 6. 7 个 Sprint 实施顺序 (Day-by-Day)
| Sprint | 内容 | 天数 |
|---|---|---|
| 1 | 基础设施 (constants/logger/models/db) | Day 1-2 |
| 2 | 数据层 (kline/cvd/ws) | Day 3-4 |
| 3 | 过滤器 + 风控 | Day 5-6 |
| 4 | 扫描器 + 执行器 | Day 7-8 |
| 5 | Web Dashboard | Day 9-10 |
| 6 | 部署 + 验证 | Day 11-12 |
| 7 | 200 笔验收 | Day 13-14 |

总计 14 天完成 Phase 1 MVP。

---

## 影响范围 (Impact)

- ✅ 不影响代码 (纯文档)
- ✅ Phase 0 规划阶段全部完成
- ✅ 可直接进入 Sprint 1 代码实现
- ⚠️ 需要 Hongtou 在 Sprint 1 开始前回答 5 个 Open Questions

---

## Phase 0 完成总览

| 文档 | 作者 | 行数 | 状态 |
|---|---|---|---|
| project-brief.md | Claude (Party) | ~350 | ✅ |
| market-research.md | 📊 Mary | ~480 | ✅ |
| prd.md | 📋 John | ~480 | ✅ |
| architecture.md | 🏗️ Winston | ~700 | ✅ |
| test-design.md | 🧪 Murat | ~600 | ✅ |
| tech-spec.md | 💻 Amelia | ~900 | ✅ |
| **总计** | **6 agents** | **~3500** | **✅ 全部完成** |

---

## 后续步骤 (Next Steps)

1. ⏳ Hongtou 审阅全部 6 份规划文档
2. ⏳ Hongtou 回答 5 个 Open Questions:
   - Q1: API Key 策略
   - Q2: Dashboard 登录
   - Q3: SSH 端口
   - Q4: 域名
   - Q5: DB 实例
3. ⏳ Hongtou 购买 Vultr Tokyo 服务器 ($24/月)
4. ⏳ 启动 Sprint 1: 基础设施搭建

---

## 回滚方法 (Rollback)

```bash
rm /Users/hongtou/newproject/flash_quant/docs/test-design.md
rm /Users/hongtou/newproject/flash_quant/docs/tech-spec.md
rm /Users/hongtou/newproject/flash_quant/docs/changelog/2026-04-08_1600_test-design-and-tech-spec.md
```

---

**状态**: ✅ 已完成
