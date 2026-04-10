# 改动记录: Tier 2 + Tier 3 + REST数据 + Dashboard增强

## 基本信息
- **时间**: 2026-04-11 04:00 (MYT)
- **改动者**: Claude
- **改动类型**: 🆕 新功能 (FR-002, FR-003) + 🔄 增强

---

## 改动原因 (Why)

Party Mode 团队评审发现:
- PRD 完成度只有 64% (9/14 FR)
- 只有 Tier 1 极速扫描器, 阈值 (量比≥5x + 涨幅≥2%) 在平静行情几乎不触发
- 缺少 REST 数据补充, 过滤器 (Funding/OI/Taker) 形同虚设
- Dashboard 缺资金曲线图和信号统计

团队一致决定: 一次性补全 Tier 2 + Tier 3 + REST + Dashboard

---

## 新增文件

| 文件 | 行数 | 说明 |
|---|---|---|
| `data/rest_poller.py` | ~120 | REST 定时拉取: 每30s tickers(funding+volume+price), 每5min OI |
| `data/indicators.py` | ~220 | 纯Python TA指标: EMA/SMA/RSI/MACD/ADX/BB/EMA穿越/量比 |
| `scanner/tier2_trend.py` | ~180 | **FR-002 Tier 2 趋势扫描器**: 60s周期, 15min MACD转色+RSI突破+EMA穿越+成交量确认 |
| `scanner/tier3_direction.py` | ~210 | **FR-003 Tier 3 方向扫描器**: 1H整点, 7指标评分(RSI+MA+Vol+Pos+MACD+ADX+BB), ≥75触发 |

## 修改文件

| 文件 | 改动 |
|---|---|
| `engine.py` | 完全重写: 三层扫描器 + REST poller + 三周期warmup(5m+15m+1h) |
| `web/templates/home.html` | 新增: 资金曲线图(Canvas) + 今日信号统计(T1/T2/T3) + 评分列 |
| `app.py` | 新增: equity_curve数据 + signals_today统计 + tier分组计数 |

---

## 技术详情

### REST Poller (`data/rest_poller.py`)
- 用 ccxt 的 `fetch_tickers()` 一次性拉全市场数据
- 每 30s: 51个币的 price + 24h_volume + funding_rate
- 每 5min: 27个币的 Open Interest
- 写入 `market_data` 缓存, scanner 直接读

### TA 指标 (`data/indicators.py`)
- **纯 Python 实现**, 不依赖 TA-Lib (服务器不需要编译)
- EMA / SMA / RSI(14) / MACD(12,26,9) / ADX(14) / Bollinger Bands(20,2)
- EMA 穿越检测 (golden_cross / death_cross)
- 所有函数返回 List[float], 方便取最新值

### Tier 2 趋势扫描 (`scanner/tier2_trend.py`)
按 PRD FR-002:
```
触发条件 (全部满足):
1. 15min MACD 柱状图首次转色 (正→负 或 负→正)
2. 15min RSI 突破 65 (多) 或跌破 35 (空)  
3. 15min EMA9 上穿/下穿 EMA21
4. 1H 成交量 > 前期均量 × 1.5
5. Wick + Funding 过滤
```
- 60s 扫描周期
- 50 个币
- 全天运行 (不受 UTC 8-22 限制)
- 去重: 同一 kline_timestamp 只处理一次

### Tier 3 方向扫描 (`scanner/tier3_direction.py`)
按 PRD FR-003:
```
评分系统 (满分 125):
- RSI 评分 (0-25): RSI 偏离50越远分越高
- MA 评分 (0-25): 价格 vs EMA20 偏离度
- Volume 评分 (0-25): 量比越高分越高
- Position 评分 (0-25): 价格在20日范围的位置
- MACD 加分 (0-10): 柱状图趋势加速
- ADX 加分 (0-10): 趋势强度 > 20
- BB 加分 (0-5): 布林带突破
```
- 1H 整点扫描 (分钟 0-1 时触发)
- 15 个 Tier A 蓝筹币
- 评分 ≥ 75 触发
- 方向由指标投票决定

### Engine 集成 (`engine.py`)
```
asyncio.gather(
    ws.run(),           # WebSocket 150 streams
    rest.run(),         # REST 30s/5min 轮询
    tier1.run(),        # 30s 极速
    tier2.run(),        # 60s 趋势
    tier3.run(),        # 1H 方向
    position_monitor(), # 30s 止损/止盈
)
```
- Warmup 三个周期: 5m(25根) + 15m(50根) + 1h(50根)
- 50 个币约 20s 完成 warmup

### Dashboard 增强
- **资金曲线图**: Canvas 绘制30天余额曲线, 10000U 基准线, 盈亏渐变色
- **今日信号统计**: T1: X / T2: Y / T3: Z 分层显示
- **评分列**: Tier 3 信号的综合评分

---

## PRD 完成度变化

| 类别 | 之前 | 之后 |
|---|---|---|
| **FR 完成** | 9/14 (64%) | **12/14 (86%)** |
| **缺失 FR** | FR-002,003,021,041,配置页 | FR-021(Phase2), FR-041(Phase2) |
| **过滤器** | 2/3 生效 | **3/3 数据就绪** |
| **扫描层** | 1/3 | **3/3** |

### 还未完成 (Phase 2)
- FR-021: Binance 实盘下单器
- FR-041: 实时数据推送 (WebSocket/SSE)
- 配置页 (/config)
- aggTrade + CVD 完整启用

---

## 服务器运行状态

```
139.162.31.86:5114

flash-quant-engine: RUNNING
flash-quant-web: RUNNING

Tier 1: 30s扫描, 50 coins ✅
Tier 2: 60s扫描, 50 coins ✅  
Tier 3: 1H扫描, 15 coins ✅
REST:   30s tickers + 5min OI ✅
WS:     150 streams, 0 errors ✅
```

---

## 回滚方法

```bash
# 如需回退到只有 Tier 1 的版本
git log --oneline -5  # 找到上一个 commit
git checkout <commit> -- flash_quant/engine.py
# 删除新文件
rm flash_quant/data/rest_poller.py
rm flash_quant/data/indicators.py
rm flash_quant/scanner/tier2_trend.py
rm flash_quant/scanner/tier3_direction.py
```

---

**状态**: ✅ 已完成
