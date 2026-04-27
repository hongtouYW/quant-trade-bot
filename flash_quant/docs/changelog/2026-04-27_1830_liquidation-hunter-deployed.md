# 2026-04-27 18:30 — 部署爆仓猎手策略

## 背景

之前 7 轮自研算法 + LUKE 跟单全部失败:
| 轮次 | 策略 | 结果 |
|---|---|---|
| 1-3 | 爆发追涨 (Tier1 各种变体) | -10% 到 -76% |
| 4 | RSI 回踩做多 | -98% |
| 5 | 资金费率套利 | +0.2% (太低) |
| 6-7 | DoubleEMA / DoubleEMA+ADX | -92% 到 -96% |
| Live | LUKE 跟单 | 回测 +205%, 实盘 -14% |

## 新发现 — 爆仓猎手

### 研究阶段
- 数据: 30 主流币 × 2025 全年 5min K线 (~3M 根)
- 事件定义: 5min 跌幅 ≥ -3% AND 量比 ≥ 5x (代理爆仓瀑布)
- 发现: 266 个事件, 1h 内反弹 ≥ 1% 的概率 **87.1%**, 反弹 ≥ 2% 的概率 62.7%
- 平均最大反弹: +9.34% (1h)
- 这是首次发现统计显著的 alpha

### 策略回测 (2025 全年)
```
入场: 5min 跌幅 ≥ -3% AND 量比 ≥ 5x → 做多 (反弹交易)
止损: -1% 价格 / -10% ROI (10x 杠杆)
止盈: +2% 价格 / +20% ROI
超时: 60 分钟
杠杆: 10x
仓位: 300U/笔, 最多 3 笔同持

结果:
  10000 U → 10303 U  (+3.0%)
  77 笔, 胜率 45.5%, PF 1.44
  最大回撤 4.7%
  这是 8 轮中第一个不亏的真实策略
```

## 改动

### 新增文件
- `flash_quant/scanner/liquidation_hunter.py` — 实盘扫描器
- `flash_quant/analysis/liquidation_hunter_research.py` — 事件统计研究
- `flash_quant/analysis/liquidation_strategy_backtest.py` — 策略回测

### 修改文件
- `flash_quant/engine.py`:
  - 引入 LiquidationHunter
  - 停用 Tier1Scalper / Tier2TrendScanner / Tier3Direction (回测验证亏损)
  - asyncio.gather 现在只跑 ws + rest + liq_hunter + position_monitor + daily_stats

## 部署

服务器 139.162.31.86 已重启 flash-quant-engine, 验证日志:
```
liq_hunter.started symbols=50 drop_threshold=-0.03 vol_ratio_min=5.0
```

## 计划 (Paper Trade 一周)

- 监控接下来 7 天的实盘表现
- 比对回测 +3% / 45% WR / 4.7% DD 是否一致
- 如果维持类似指标 → 考虑优化版 (量比 5-7x + 跌幅 -4~-5% 甜蜜点)
- 如果显著差于回测 → 检查滑点/执行延迟问题

## 数据观察点 (后续优化用)

回测中已发现的甜蜜点:
- **量比 5-7x**: 胜率 69%, PnL +342 ⭐⭐⭐
- **跌幅 -4~-5%**: 胜率 58%, PnL +179 ⭐⭐⭐
- **量比 10-15x**: 胜率 31% ❌ (真崩盘, 接刀)

下次优化方向: 加严过滤, 只取甜蜜点。理论上可推到 +10~15%.
