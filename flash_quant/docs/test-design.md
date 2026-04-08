# Flash Quant - 测试设计文档

> **作者**: 🧪 Murat (Master Test Architect)
> **日期**: 2026-04-08
> **版本**: v0.1
> **依赖**: prd.md, architecture.md
> **下游**: tech-spec.md (Amelia 实现)

---

## 📖 文档说明

本文档定义 Flash Quant 的**测试策略和质量门**。

我的核心原则:
- **风险驱动测试** — 测试深度与影响匹配
- **测试金字塔** — 单元 > 集成 > E2E
- **质量门有数据支撑** — 不靠"看起来 OK"
- **API 测试是一等公民** — 不只是 UI 支撑
- **抖动测试是技术债** — 必须修

---

## 🎯 1. 测试策略总览

### 1.1 测试金字塔

```
                  ╱ ╲
                 ╱ E ╲       <- E2E (5%)
                ╱─────╲         实盘小额 (Phase 2)
               ╱       ╲
              ╱  集成   ╲    <- Integration (25%)
             ╱───────────╲      Pipeline / Executor / WS
            ╱             ╲
           ╱     单元      ╲  <- Unit (70%)
          ╱─────────────────╲    Filters / Risk / Models
```

**目标覆盖率**:
- 单元测试: ≥ 80% (关键模块 100%)
- 集成测试: 关键路径 100%
- E2E: Phase 2 实盘验证

### 1.2 测试分级 (按风险)

| 等级 | 模块 | 测试深度 |
|---|---|---|
| 🔴 **Critical** | filters/, risk/, executor/binance | 100% 单元 + 集成 + 边界测试 + 错误注入 |
| 🟡 **High** | scanner/, ws/, data/cvd | 单元 + 集成 |
| 🟢 **Medium** | web/, models/, config/ | 单元 + 冒烟测试 |
| ⚪ **Low** | scripts/, dashboard CSS | 手动测试 |

**Why**: 钱在 Critical 模块, 必须打透。Web 出 bug 大不了刷新, 不会爆仓。

---

## 🧪 2. 单元测试详细设计

### 2.1 Wick Filter (反插针)

**风险等级**: 🔴 Critical
**目标覆盖率**: 100%

#### 测试矩阵

| 测试 ID | 输入 (O/H/L/C) | 预期 | 说明 |
|---|---|---|---|
| TC-WICK-001 | 100/102/100/101.5 | ✅ Pass | 大阳线,实体 75% |
| TC-WICK-002 | 100/100.5/98/98.5 | ✅ Pass | 大阴线,实体 75% |
| TC-WICK-003 | 100/110/99/100.5 | ❌ Fail | 长上影,插针多 |
| TC-WICK-004 | 100/100.5/90/99.5 | ❌ Fail | 长下影,插针空 |
| TC-WICK-005 | 100/105/95/100 | ❌ Fail | 十字星 (实体 0) |
| TC-WICK-006 | 100/100/100/100 | ❌ Fail | 一字线 (total=0) |
| TC-WICK-007 | 100/103/100/102 | ✅ Pass | 实体 67% (恰好 > 0.55) |
| TC-WICK-008 | 100/103/100/101.5 | ❌ Fail | 实体 50% (< 0.55) |
| TC-WICK-009 | 真实历史 BTC 插针 K线 | ❌ Fail | 2024-08-05 黑色星期一 |
| TC-WICK-010 | 真实健康 K线 | ✅ Pass | 同期对照组 |

#### 边界条件
- 价格为 0 → ZeroDivisionError 防护
- 负价格 → 拒绝 + 日志
- 价格类型不一致 (str vs Decimal) → 类型检查

---

### 2.2 CVD Filter (累积成交量差)

**风险等级**: 🔴 Critical
**目标覆盖率**: 100%

#### 测试矩阵

| 测试 ID | 场景 | 预期 |
|---|---|---|
| TC-CVD-001 | 价格创新高, CVD 同步创新高 | ✅ Pass |
| TC-CVD-002 | 价格创新高, CVD 持平 | ❌ Fail (背离) |
| TC-CVD-003 | 价格创新高, CVD 下降 | ❌ Fail (强背离) |
| TC-CVD-004 | 价格新低, CVD 新低 | ✅ Pass (空头) |
| TC-CVD-005 | 价格新低, CVD 持平 | ❌ Fail |
| TC-CVD-006 | 数据不足 (< 20 根) | ❌ Fail |
| TC-CVD-007 | CVD 序列全为 0 | ❌ Fail |
| TC-CVD-008 | tolerance 边界 (10.0% 偏差) | ✅ Pass |
| TC-CVD-009 | tolerance 边界 (10.1% 偏差) | ❌ Fail |
| TC-CVD-010 | 真实历史 BTC 强突破数据 | ✅ Pass |

#### CVD 计算单元测试

| TC-CVD-CALC-001 | aggTrade buyer_maker=True | CVD -= qty (主动卖) |
| TC-CVD-CALC-002 | aggTrade buyer_maker=False | CVD += qty (主动买) |
| TC-CVD-CALC-003 | 100 笔混合交易 | 累积值正确 |
| TC-CVD-CALC-004 | WebSocket 重连后 | 从最新点继续累积 |

---

### 2.3 Risk Manager (风控)

**风险等级**: 🔴 Critical
**目标覆盖率**: 100%

#### 5 大风控模块测试

**单笔风控 (FR-030)**:
| TC-RISK-001 | 仓位 = 300U | ✅ 通过 |
| TC-RISK-002 | 仓位 = 301U | ❌ 拒绝 (BR-005) |
| TC-RISK-003 | 杠杆 = 20x | ✅ 通过 |
| TC-RISK-004 | 杠杆 = 21x | ❌ 拒绝 (BR-001) |
| TC-RISK-005 | 同时持仓 5 笔 | ❌ 第 6 笔拒绝 |
| TC-RISK-006 | 止损单未挂 | ❌ 5s 后强平 (BR-006) |

**连亏断路器 (FR-031)**:
| TC-CB-001 | 连亏 2 笔 | 仓位正常 |
| TC-CB-002 | 连亏 3 笔 | 仓位减半 |
| TC-CB-003 | 连亏 5 笔 | 暂停 4h |
| TC-CB-004 | 连亏 8 笔 | 暂停 24h |
| TC-CB-005 | 连亏 4 笔后 1 胜 | 计数重置 |
| TC-CB-006 | 跨 24h 窗口 | 旧记录失效 |

**时段断路器 (FR-032)**:
| TC-CB-007 | 单日亏 -2.9% | 正常 |
| TC-CB-008 | 单日亏 -3.0% | 暂停当日 |
| TC-CB-009 | 单周亏 -8.0% | 暂停 24h |
| TC-CB-010 | 单月亏 -15.0% | 暂停 72h + 人工 |
| TC-CB-011 | 暂停期间已持仓 | 不强平 |
| TC-CB-012 | 暂停期间新信号 | 拒绝 |

**黑天鹅熔断 (FR-033)**:
| TC-BS-001 | BTC 5min 波幅 4.9% | 正常 |
| TC-BS-002 | BTC 5min 波幅 5.0% | 触发熔断 30min |
| TC-BS-003 | 熔断期间 | 全系统拒绝新单 |
| TC-BS-004 | 熔断期间已持仓 | 不强平 |
| TC-BS-005 | 30 分钟后 | 自动恢复 |

**同币冷却 (FR-034)**:
| TC-COOL-001 | 平仓 BTC 后 1.5h | ❌ 拒绝 |
| TC-COOL-002 | 平仓 BTC 后 2.1h | ✅ 通过 |
| TC-COOL-003 | 平仓 BTC, 开 ETH | ✅ 通过 (不同币) |

---

### 2.4 Position Sizing (仓位计算)

| TC-POS-001 | 工作日 | margin = 300 |
| TC-POS-002 | 周六 | margin = 150 (BR-008 减半) |
| TC-POS-003 | 周日 | margin = 150 (BR-008) |
| TC-POS-004 | 连亏 3 笔后 | margin = 150 (FR-031) |
| TC-POS-005 | 周末 + 连亏 3 | margin = 75 (双重减半) |

---

### 2.5 Blacklist Filter

| TC-BL-001 | Tier A 蓝筹 (BTC) | ✅ Pass |
| TC-BL-002 | Tier C 山寨 24h $50M | ✅ Pass |
| TC-BL-003 | Tier D 24h $5M | ❌ Fail (BR-002) |
| TC-BL-004 | 上市 6 天的币 | ❌ Fail (BR-003) |
| TC-BL-005 | 上市 7 天的币 | ✅ Pass (边界) |
| TC-BL-006 | 上市 100 天的币 | ✅ Pass |

---

## 🔌 3. 集成测试设计

### 3.1 Scanner Pipeline 集成测试

**目标**: 验证从扫描 → 过滤 → 风控 → 执行的完整数据流。

```
test_scanner_pipeline_happy_path:
  1. Mock K线数据 (健康大阳线 + 量比 6x)
  2. Mock CVD 数据 (与价格同步)
  3. Mock funding rate (0.05%)
  4. 触发 Tier 1 扫描
  5. 验证: 信号生成 → 通过过滤器 → 通过风控 → 调用 paper executor
  6. 验证: signals 表写入 final_decision='executed'
  7. 验证: trades 表写入新记录
  8. 验证: positions 表写入新持仓
```

```
test_scanner_pipeline_filtered:
  1. Mock K线数据 (插针 K线)
  2. 触发 Tier 1 扫描
  3. 验证: signals 表写入 final_decision='filtered'
  4. 验证: filter_reason='wick_filter_failed'
  5. 验证: trades 表无新记录
```

```
test_scanner_pipeline_blocked_by_risk:
  1. Setup: circuit_breaker active (manual)
  2. Mock 健康信号
  3. 触发扫描
  4. 验证: signals 表写入 final_decision='blocked'
  5. 验证: filter_reason='circuit_breaker_active'
```

### 3.2 Executor 集成测试

```
test_paper_executor_full_lifecycle:
  1. 开仓 → 验证 trades + positions 写入
  2. 模拟价格上涨 +30% ROI
  3. 阶梯止盈 → 平 30% 仓位
  4. 模拟价格继续涨 +60% ROI
  5. 阶梯止盈 → 再平 30%
  6. 模拟价格回落,触发 trailing stop
  7. 全部平仓 → trades.status='closed'
  8. 验证 P&L 计算正确 (含手续费)
```

```
test_binance_executor_with_mock:
  使用 ccxt mock,验证:
  - 下单参数正确 (含杠杆/止损/workingType)
  - API 失败重试 3 次
  - 服务端止损单挂载验证
```

### 3.3 WebSocket 集成测试

```
test_ws_reconnection:
  1. 启动 WS collector
  2. 强制断开连接
  3. 验证: 1s, 2s, 4s, 8s 指数退避重连
  4. 验证: 重连后从最新 K线继续
  5. 验证: CVD 序列连续 (无断点)
```

```
test_ws_data_quality:
  1. 订阅 BTC kline 5m
  2. 接收 100 根 K线
  3. 验证: 时间戳连续, 无丢失
  4. 验证: K线 OHLC 与 REST API 对账误差 < 0.01%
```

---

## 🎯 4. Phase 1 验收测试 (硬性 5 项 Gate)

### 测试场景设计

#### AC-1: 过滤器有效性 (≥ 30% 提升)

**实验设计**: A/B 对照
```
分组 A (控制组):
  - 跑 14 天 Tier 1 扫描
  - 不加任何过滤器
  - 记录所有信号 → 模拟交易 → 计算胜率

分组 B (实验组):
  - 跑同样 14 天数据 (回放)
  - 加入 Wick + CVD + Funding 三层过滤
  - 同样模拟交易 → 计算胜率

通过条件: 胜率(B) - 胜率(A) ≥ 0.30
```

**实施**:
- 双扫描器并行 (一个加过滤,一个不加)
- 共享同一 K线数据源
- 14 天后对比统计

---

#### AC-2: 信号数量 (Tier 1 日均 10-20)

```python
def test_ac2_signal_count():
    df = query_signals(
        tier='tier1',
        date_range='14 days',
        decision='executed'
    )
    daily_avg = df.groupby('date').count().mean()
    assert 10 <= daily_avg <= 20
```

**调试预案**:
- 如果 < 10 → 阈值过严, 调低量比/价格变化
- 如果 > 20 → 阈值过松, 调高过滤标准

---

#### AC-3: 整体胜率 (≥ 55%)

```python
def test_ac3_win_rate():
    trades = query_trades(
        mode='paper',
        status='closed',
        count=200  # 至少 200 笔
    )
    wins = sum(1 for t in trades if t.pnl > 0)
    win_rate = wins / len(trades)
    assert win_rate >= 0.55
```

**前置**: 必须有至少 200 笔已平仓交易

---

#### AC-4: 盈亏比 (≥ 1.5)

```python
def test_ac4_profit_factor():
    trades = query_trades(mode='paper', status='closed')
    avg_win = sum(t.pnl for t in trades if t.pnl > 0) / wins_count
    avg_loss = abs(sum(t.pnl for t in trades if t.pnl < 0) / losses_count)
    profit_factor = avg_win / avg_loss
    assert profit_factor >= 1.5
```

---

#### AC-5: 信号延迟 (P99 < 500ms)

```python
def test_ac5_signal_latency():
    latencies = query_signal_latencies(period='14 days')
    p99 = numpy.percentile(latencies, 99)
    assert p99 < 500  # ms
```

**测量点**:
```
T0 = K线收盘时间 (Binance 服务器)
T1 = 扫描器接收到 K线
T2 = 过滤器完成
T3 = 风控完成
T4 = 下单接口被调用
信号延迟 = T4 - T0
```

**目标分解**:
- T1 - T0: < 100ms (WebSocket)
- T2 - T1: < 50ms (过滤器)
- T3 - T2: < 50ms (风控)
- T4 - T3: < 300ms (下单准备)

---

## ⚡ 5. 性能测试

### 5.1 负载测试

| 测试 | 场景 | 通过条件 |
|---|---|---|
| LT-001 | 200 币 30s 扫描 | 单次扫描 < 5s |
| LT-002 | 1000 信号 / 小时 | 系统不崩 |
| LT-003 | 100 并发 Web 请求 | P99 < 2s |
| LT-004 | WS 同时订阅 200 个 stream | 内存 < 1GB |

### 5.2 压力测试

```
ST-001: 24 小时持续运行
  - 模拟全市场 200 币
  - 验证: 无内存泄漏
  - 验证: 无连接泄漏
  - 验证: 无 Redis key 堆积

ST-002: WebSocket 断线 100 次
  - 强制断开 100 次
  - 验证: 100% 自动恢复
  - 验证: CVD 数据完整
```

### 5.3 抖动测试 (Flakiness)

**Murat 的强硬要求**: 任何不稳定的测试必须修, 不能跳过。

```
flakiness_check:
  - 每个测试连续运行 10 次
  - 失败率 > 0% → 标记为 flaky
  - flaky 测试必须修复或删除, 不允许 retry hack
```

---

## 🚨 6. 错误注入测试 (Chaos Testing)

| 注入点 | 场景 | 预期行为 |
|---|---|---|
| Binance API 5xx | 下单时返回 500 | 重试 3 次, 失败告警 |
| Binance API 超时 | 下单 10s 无响应 | 取消 + 告警 |
| MySQL 断线 | 写入时连接断 | 重连 + 重试 |
| Redis 断线 | 读取 CVD 时断 | 降级到内存 |
| WebSocket 断线 | 数据流中断 | 指数退避重连 |
| 时钟跳变 | NTP 同步导致时间倒流 | 拒绝旧数据 |
| 磁盘满 | 写日志失败 | 切换 stderr + 告警 |
| OOM | 内存不足 | Supervisord 自动重启 |

---

## 📊 7. 测试基础设施

### 7.1 测试栈

```yaml
框架: pytest 8+
异步: pytest-asyncio
Mock: pytest-mock + responses (HTTP mock)
覆盖: pytest-cov
快照: pytest-snapshot (可选)
基准: pytest-benchmark
固定数据: factory_boy (生成 K线/Trade fixtures)
```

### 7.2 测试数据

**目录**: `tests/fixtures/`

```
tests/fixtures/
├── kline/
│   ├── btc_5min_normal.json        (健康大阳线)
│   ├── btc_5min_wick_pump.json     (插针多)
│   ├── btc_5min_wick_dump.json     (插针空)
│   ├── btc_5min_doji.json          (十字星)
│   └── historical/
│       └── 2024-08-05_black_monday.json  (黑色星期一真实数据)
├── trades/
│   ├── paper_200_sample.json
│   └── live_50_sample.json
└── cvd/
    ├── synced.json                 (CVD 与价格同步)
    └── divergent.json              (背离)
```

### 7.3 CI 配置 (Phase 2 加)

```yaml
# .github/workflows/test.yml (Phase 2)
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: pip install -r requirements-dev.txt
      - run: pytest tests/unit -v --cov=. --cov-fail-under=80
      - run: pytest tests/integration -v
      - name: Flakiness check
        run: pytest tests/unit --count=10 -x
```

**Phase 1 不做 CI**, 本地手动跑就够了。

---

## 🚦 8. 质量门 (Quality Gates)

### Gate 1: 代码提交 (Pre-commit)
- ✅ 所有单元测试通过
- ✅ 覆盖率 ≥ 80%
- ✅ 无 flaky test

### Gate 2: Phase 1 → Phase 2
- ✅ AC-1 过滤器有效性 ≥ 30% 提升
- ✅ AC-2 信号数量 10-20/天
- ✅ AC-3 胜率 ≥ 55%
- ✅ AC-4 盈亏比 ≥ 1.5
- ✅ AC-5 延迟 P99 < 500ms
- ✅ 200+ 笔模拟交易
- ✅ 14 天稳定运行无崩溃
- ✅ 所有集成测试通过

### Gate 3: Phase 2 → Phase 3
- ✅ 500U 实盘 14 天
- ✅ 净盈亏 ≥ 0
- ✅ 实际滑点 < 0.1%
- ✅ API 成功率 > 99.5%
- ✅ 断路器无误触

### Gate 4: Phase 3 → Phase 4
- ✅ 2000U 实盘 1 个月
- ✅ 月化 ≥ 10%
- ✅ 最大回撤 < 15%
- ✅ 风控全部正常工作

**任何一个 Gate 不通过 → 不晋级**, 不许商量。

---

## ⚠️ 9. 已知测试限制

| 限制 | 影响 | 应对 |
|---|---|---|
| 无法模拟真实滑点 | Phase 1 P&L 偏乐观 | Phase 2 实盘补偿 |
| 无法模拟交易所宕机 | 极端场景未覆盖 | Chaos testing 部分弥补 |
| 无法回测黑天鹅 | 黑天鹅熔断未实战验证 | 模拟数据 + 监控告警 |
| 历史数据可能有偏差 | 回测胜率不等于实盘 | Phase 2 小额验证 |

---

## 🎤 Murat 签字

> "🧪 **测试设计完成。**
>
> 我的核心观点:
> 1. **AC-Phase1 5 项 Gate 是真正的门**, 不是橡皮图章 — 不通过就不上实盘
> 2. **Critical 模块 (filters/risk/executor) 必须 100% 单元覆盖**
> 3. **Flaky test 是技术债**, 必须修, 不允许 retry hack
> 4. **Phase 1 不做 CI**, 本地 pytest 够用
> 5. **错误注入测试不可省**, 这是发现隐藏 bug 的关键
>
> 我的担忧:
> - 模拟盘无法 100% 复现真实滑点 → Phase 2 必须实盘验证
> - 黑天鹅熔断无法回测 → 必须靠监控告警兜底
>
> 现在交棒给 **Amelia** 写技术规格! 💻"

---

**文档版本**: v0.1
**状态**: ✅ 已完成
**下一步**: 💻 Amelia 撰写 Tech Spec
