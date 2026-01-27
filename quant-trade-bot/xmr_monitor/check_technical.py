#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests
import pandas as pd
import numpy as np
from datetime import datetime

def calculate_macd(prices, fast=12, slow=26, signal=9):
    """计算MACD指标"""
    ema_fast = pd.Series(prices).ewm(span=fast, adjust=False).mean()
    ema_slow = pd.Series(prices).ewm(span=slow, adjust=False).mean()
    macd_line = ema_fast - ema_slow
    signal_line = macd_line.ewm(span=signal, adjust=False).mean()
    histogram = macd_line - signal_line
    return macd_line.values, signal_line.values, histogram.values

def calculate_ma(prices, period):
    """计算移动平均线"""
    return pd.Series(prices).rolling(window=period).mean().values

# 获取K线数据（1小时级别，最近200根）
url = "https://api.coingecko.com/api/v3/coins/monero/market_chart"
params = {
    'vs_currency': 'usd',
    'days': '15',
    'interval': 'hourly'
}

print("正在获取XMR技术分析数据...")
response = requests.get(url, params=params)
data = response.json()

# 提取价格数据
price_data = data['prices']
volume_data = data['total_volumes']

times = [datetime.fromtimestamp(p[0]/1000) for p in price_data]
prices = [p[1] for p in price_data]
volumes = [v[1] for v in volume_data]

print(f"\n获取到 {len(prices)} 根K线数据")

# 计算MACD
macd_line, signal_line, histogram = calculate_macd(prices)

# 计算均线
ma5 = calculate_ma(prices, 5)
ma15 = calculate_ma(prices, 15)
ma30 = calculate_ma(prices, 30)

# 当前数据
current_price = prices[-1]
current_macd = macd_line[-1]
current_signal = signal_line[-1]
current_histogram = histogram[-1]
prev_histogram = histogram[-2]

print(f"\n=== XMR 技术分析 (最新数据) ===")
print(f"当前价格: ${current_price:.2f}")
print(f"时间: {times[-1].strftime('%Y-%m-%d %H:%M')}")

print(f"\n=== MACD 指标 ===")
print(f"MACD线: {current_macd:.2f}")
print(f"信号线: {current_signal:.2f}")
print(f"柱状图: {current_histogram:.2f}")
print(f"前一根: {prev_histogram:.2f}")

# MACD趋势判断
if current_histogram > 0 and prev_histogram < 0:
    macd_signal = "🚀 金叉！看涨信号"
elif current_histogram < 0 and prev_histogram > 0:
    macd_signal = "📉 死叉！看跌信号"
elif current_histogram > prev_histogram and current_histogram > 0:
    macd_signal = "📈 上涨趋势加强"
elif current_histogram > prev_histogram and current_histogram < 0:
    macd_signal = "⚡ 下跌减缓，可能反转"
elif current_histogram < prev_histogram and current_histogram > 0:
    macd_signal = "⚠️ 上涨动能减弱"
else:
    macd_signal = "📉 下跌趋势持续"

print(f"MACD信号: {macd_signal}")

# 最近3根柱状图趋势
recent_hist = histogram[-5:]
print(f"\n最近5根柱状图: {' → '.join([f'{h:.1f}' for h in recent_hist])}")
if all(recent_hist[i] < recent_hist[i+1] for i in range(len(recent_hist)-1)):
    print("趋势: 🟢 连续上升（好转）")
elif all(recent_hist[i] > recent_hist[i+1] for i in range(len(recent_hist)-1)):
    print("趋势: 🔴 连续下降（恶化）")
else:
    print("趋势: 🟡 震荡中")

print(f"\n=== 均线系统 ===")
print(f"MA5 (5小时): ${ma5[-1]:.2f}")
print(f"MA15 (15小时): ${ma15[-1]:.2f}")
print(f"MA30 (30小时): ${ma30[-1]:.2f}")

# 均线排列
if current_price > ma5[-1] > ma15[-1] > ma30[-1]:
    ma_signal = "🚀 多头排列（强势）"
elif current_price < ma5[-1] < ma15[-1] < ma30[-1]:
    ma_signal = "📉 空头排列（弱势）"
elif current_price > ma15[-1]:
    ma_signal = "📈 价格在15均线上方（偏强）"
else:
    ma_signal = "⚠️ 价格在15均线下方（偏弱）"

print(f"均线信号: {ma_signal}")

# 价格与均线距离
ma15_distance = ((current_price - ma15[-1]) / ma15[-1]) * 100
print(f"距离15均线: {ma15_distance:+.2f}%")

# 分析最近100根K线的买卖量
print(f"\n=== 成交量分析 (最近100小时) ===")
recent_100_volumes = volumes[-100:]
recent_100_prices = prices[-100:]

avg_volume = np.mean(recent_100_volumes)
current_volume = volumes[-1]
volume_ratio = (current_volume / avg_volume) * 100

print(f"平均成交量: ${avg_volume:,.0f}")
print(f"当前成交量: ${current_volume:,.0f}")
print(f"成交活跃度: {volume_ratio:.0f}%")

# 统计上涨和下跌时的成交量
up_volumes = []
down_volumes = []
for i in range(1, len(recent_100_prices)):
    if recent_100_prices[i] > recent_100_prices[i-1]:
        up_volumes.append(recent_100_volumes[i])
    else:
        down_volumes.append(recent_100_volumes[i])

avg_up_volume = np.mean(up_volumes) if up_volumes else 0
avg_down_volume = np.mean(down_volumes) if down_volumes else 0

print(f"\n上涨时平均量: ${avg_up_volume:,.0f}")
print(f"下跌时平均量: ${avg_down_volume:,.0f}")

if avg_up_volume > avg_down_volume * 1.2:
    volume_signal = "🟢 买盘强劲（上涨放量）"
elif avg_down_volume > avg_up_volume * 1.2:
    volume_signal = "🔴 卖盘强劲（下跌放量）"
else:
    volume_signal = "🟡 买卖均衡"

print(f"量能信号: {volume_signal}")

# 综合判断
print(f"\n=== 综合判断 ===")
bullish_count = 0
bearish_count = 0

if current_histogram > prev_histogram:
    bullish_count += 1
else:
    bearish_count += 1

if current_price > ma15[-1]:
    bullish_count += 1
else:
    bearish_count += 1

if avg_up_volume > avg_down_volume:
    bullish_count += 1
else:
    bearish_count += 1

if current_histogram > 0:
    bullish_count += 1
else:
    bearish_count += 1

print(f"看涨信号: {bullish_count}/4")
print(f"看跌信号: {bearish_count}/4")

if bullish_count >= 3:
    final_signal = "🟢 偏多，有上升趋势"
    action = "可以持仓观望，等待$470-475反弹减仓"
elif bearish_count >= 3:
    final_signal = "🔴 偏空，下跌风险较大"
    action = "建议设置严格止损$460，或反弹$465-470减仓"
else:
    final_signal = "🟡 震荡，方向不明"
    action = "观望为主，$463以下减仓，$470以上加仓"

print(f"\n最终判断: {final_signal}")
print(f"操作建议: {action}")

# 你的仓位风险评估
entry_price = 480.43
margin = 3583.61
print(f"\n=== 你的仓位风险 ===")
print(f"开仓价: ${entry_price:.2f}")
print(f"当前价: ${current_price:.2f}")
loss_pct = ((current_price - entry_price) / entry_price) * 100 * 20
print(f"当前ROI: {loss_pct:+.1f}%")

if current_price < 463:
    risk = "🚨 高风险：接近止损"
elif current_price < 470:
    risk = "⚠️ 中风险：需密切关注"
elif current_price < 475:
    risk = "📊 可控：观望为主"
else:
    risk = "✅ 较安全：可考虑减仓"

print(f"风险等级: {risk}")
