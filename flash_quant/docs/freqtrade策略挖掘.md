# Freqtrade 开源策略挖掘

> **日期**: 2026-04-15
> **来源**: github.com/freqtrade + freqst.com + 社区

---

## 📊 FreqST 排行榜 Top 10 (实际跑分)

| 策略 | 月化 | 胜/负 | 胜率 | 核心指标 |
|---|---|---|---|---|
| **ichiV1** | **27.13%** | 82/28 | **75%** | 云图 (Ichimoku) |
| HarmonicDivergence | 16.24% | 158/89 | 64% | 和谐形态 |
| Babico_SMA5xBBmid | 12.15% | 4/1 | 80% | SMA + 布林带 |
| TheForce | 9.03% | 330/230 | 59% | 多指标 |
| WaveTrendStra | 8.73% | 9/4 | 69% | Wave Trend |
| NostalgiaForInfinityXw | 6.69% | 10/0 | **100%** | (知名策略) |

---

## 🏆 值得回测的 3 个策略

### 策略 1: DoubleEMACrossoverWithTrend (最简单, 最可靠)

**回测: 655 笔, +122.5% 利润 (2018-2020)**

```
逻辑:
  买入: EMA9 上穿 EMA21 + 价格在 EMA200 上方
  卖出: EMA9 下穿 EMA21 或 价格跌破 EMA200
  止损: -20%

翻译:
  短期均线穿越长期均线 = 趋势启动
  价格在 200 均线上方 = 大趋势向上
  → 只在上涨趋势中做多
```

**为什么值得试:**
- 逻辑和 LUKE 很像 (顺趋势交易)
- EMA200 趋势过滤 (我们之前也加了)
- -20% 止损 (比我们的 -10% 宽, 不会被扫)
- 655 笔足够的样本量
- **我们已经有 EMA 计算代码!**

---

### 策略 2: EMAPriceCrossoverWithThreshold (最稳)

**回测: 272 笔, +118.5% 利润**

```
逻辑:
  买入: 价格上穿 EMA800
  卖出: 价格跌破 EMA800 × 99% (1% 缓冲)
  止损: -15%, trailing stop
  
翻译:
  只看一条超长均线 (800 根 1H = 33 天)
  价格突破 33 日均线 = 大趋势转多 → 买入
  价格跌破 → 卖出
  1% 缓冲 = 不会因为小波动来回切换
```

**为什么值得试:**
- 极其简单 (只有 1 个指标)
- 信号极少 (月均 ~10 笔) → 不会频繁交易
- 大周期趋势 → 方向准确率高
- trailing stop → 让利润奔跑

---

### 策略 3: SmoothOperator (BuyMeAnIcecream)

**回测: 月化 +0.84%, 回撤仅 0.43%, 胜率 83.3%**

```
逻辑:
  市场状态过滤: 只在熊市/震荡市交易, 牛市不做
  买入: RSI + MACD + BB 综合
  止损: -6.1%
  ROI 阶梯止盈: 14.6% → 3.6% → 0%
  
特点:
  避开牛市 (牛市追高容易亏)
  在震荡/熊市做反弹
  胜率 83.3% 非常高
```

**为什么值得试:**
- **83.3% 胜率!** (我们需要的就是高胜率)
- 市场状态过滤 (解决我们震荡市亏损问题)
- 回撤极小 (0.43%)
- 但月化只有 0.84% (年化 ~10%)

---

## 📋 对比

| 策略 | 胜率 | 年化 | 复杂度 | 我们能做吗 |
|---|---|---|---|---|
| DoubleEMA | ~55% | ~60%+ | ⭐ 简单 | ✅ 已有代码 |
| EMA800 | ~50% | ~50%+ | ⭐ 极简 | ✅ 改几行 |
| SmoothOperator | **83%** | ~10% | ⭐⭐⭐ 中等 | ✅ 需要加市场状态 |
| ichiV1 | 75% | 27%/月 | ⭐⭐⭐⭐ 复杂 | ⚠️ 需要实现云图 |

---

## 🎯 推荐: 先回测 DoubleEMACrossoverWithTrend

**理由:**
1. 逻辑最简单 (3 个 EMA + 穿越)
2. 回测样本大 (655 笔)
3. 盈利 +122.5%
4. 我们已经有 EMA 代码
5. 和 LUKE 交易员风格最接近 (顺趋势)
6. **止损 -20% 而不是 -10%** → 不会被正常波动扫

```python
# 核心逻辑 (伪代码):
if EMA9 > EMA21 and price > EMA200:
    buy()  # 短均穿长均 + 大趋势向上

if EMA9 < EMA21 or price < EMA200:
    sell()  # 反穿或跌破大趋势
```

**要回测吗?**

---

## Sources

- [freqtrade-strategies (官方)](https://github.com/freqtrade/freqtrade-strategies)
- [strategies-that-work (Paul Csapak)](https://github.com/paulcpk/freqtrade-strategies-that-work)
- [BuyMeAnIcecream 策略](https://github.com/BuyMeAnIcecream/freqtrade-strategies)
- [FreqST 策略排行榜](https://freqst.com/)
- [nateemma 自定义策略](https://github.com/nateemma/strategies)
