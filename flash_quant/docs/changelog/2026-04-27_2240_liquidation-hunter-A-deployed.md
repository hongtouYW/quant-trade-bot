# 2026-04-27 22:40 — 爆仓猎手 A 方案部署 (50x + 量比 5-10x 过滤)

## 演化路径

```
首版 10x  (vol 5x+)  → +303 (+3.0%),  WR 45%, DD 4.7%
30x      (vol 5x+)  → +908 (+9.1%),  WR 45%, DD 13.8%
50x      (vol 5x+)  → +1513 (+15.1%), WR 45%, DD 22.4%
A方案 50x (vol 5-10x) → +2245 (+22.5%), WR 56%, DD 4.8% ⭐
```

## A 方案改动

```python
# scanner/liquidation_hunter.py
VOLUME_RATIO_MIN = 5.0
VOLUME_RATIO_MAX = 10.0   # 新增上限 — 砍 10-30x 真崩盘区

# Filter
if vol_ratio < VOLUME_RATIO_MIN or vol_ratio > VOLUME_RATIO_MAX:
    return None
```

## 为什么有效

回测发现量比分段表现极不一致:

```
量比 5-7x:   71% WR, +1978  ⭐⭐⭐ 真甜蜜点
量比 7-10x:  44% WR,  +268  ⭐
量比 10-15x: 31% WR,  -540  ❌ 真崩盘 (接刀)
量比 15-30x: 41% WR,  -296  ❌ 真崩盘
量比 30+x:   40% WR,  +103  noise
```

10-30x 区间不是反弹机会, 是真崩盘 — 价格还会继续跌, 我们做多就是接刀。

## 部署清单

| 项目 | 状态 |
|---|---|
| `scanner/liquidation_hunter.py` | ✅ 加 VOL_MAX=10 |
| 服务器代码 | ✅ scp 上传 |
| 引擎重启 | ✅ pid 275821 |
| 启动验证 | ✅ liq_hunter.started leverage=50 vol 5-10 |
| Dashboard 回测页 | ✅ 显示 A 方案结果 |
| DB 清零 | ✅ signals/trades/positions/daily_stats 全清 |
| 本金重置 | ✅ 回到 10000U |

## 当前参数

```yaml
策略: 爆仓猎手 A 方案 (paper trade)
入场: 5min 跌幅 ≥ -3% AND 量比 5-10x → 做多
止损: 价格 -1% (ROI -50% @ 50x)
止盈: 价格 +2% (ROI +100% @ 50x)
超时: 60 分钟
杠杆: 50x
仓位: 300U/笔
最大同持: 3 笔 (32 笔/年, 实际很难撞)
监控: 50 个币 (Binance 永续)
```

## 验收 (1 周)

- 触发频次接近回测预期 (~2-3 笔/月 → 一周约 0-1 笔)
- 实盘 WR 接近 56% (回测预期)
- 单笔盈亏比接近 1.47
- 滑点是否吃掉边际收益

如果一周没触发: 等机会 (本来就稀疏)
如果触发但 SL 比例高: 检查滑点 / 入场价偏移
