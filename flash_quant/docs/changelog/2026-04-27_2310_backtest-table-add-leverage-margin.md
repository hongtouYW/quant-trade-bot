# 2026-04-27 23:10 — 回测交易明细表加 杠杆/保证金/持有时间/盈亏%

## 背景

前端 `/backtest` 的"交易明细 (最近 50 笔)"列不够,
不知道每笔的杠杆和保证金, 持有时间字段还指错 (`t.hold_hours` 不存在),
也没有单笔 ROI%。

## 改动

### 表头变化

```
旧: 日期 币种 方向 层级 入场 出场 盈亏 持仓(h) 原因
新: 日期 币种 方向 杠杆 保证金 入场 出场 盈亏 盈亏% 持有时间 原因
```

- 删除 `层级` (爆仓猎手单层, 不需要)
- 加 `杠杆` (50x)
- 加 `保证金` (300 U)
- 加 `盈亏%` = pnl / margin × 100 (ROI on margin, 比价格变动更直观)
- `持有时间` 修正字段: `t.hold_hours` → `t.hold_min`, 显示 `XX min`

### 数据层

`analysis/liquidation_strategy_backtest.py`:
```python
trades.append({
    ...
    'leverage': LEVERAGE,            # 新增
    'margin': round(pos['margin'], 2),  # 新增
    ...
})
```

### 部署

| 项 | 状态 |
|---|---|
| `web/templates/backtest.html` | ✅ scp |
| `analysis/liquidation_strategy_backtest.py` | ✅ scp (下次回测会带上) |
| `/opt/flash_quant/backtest_result.json` | ✅ 现存 32 笔已 patch leverage=50 margin=300 |
| `flash-quant-web` 重启 | ✅ |

### 验证

```
curl /backtest → 32 行 "300 U", 33 处 "50x", "+89.9%" ROI 显示正常
```

例: HBAR/USDT 入场 0.351121, 出场 0.357785, 价格 +1.9%,
50x 杠杆 × 300U 保证金 → +269.7 U, **ROI +89.9%**, 持有 15 min。
