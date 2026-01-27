#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""MEME买入分析"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import ccxt
import pandas as pd
import numpy as np
import json

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

symbol = 'MEME/USDT'
watch_price = 0.008810

print(f'\n📊 {symbol} 详细买入分析')
print('=' * 70)

# 获取多周期数据
ohlcv_15m = exchange.fetch_ohlcv(symbol, '15m', limit=100)
ohlcv_1h = exchange.fetch_ohlcv(symbol, '1h', limit=100)
ohlcv_4h = exchange.fetch_ohlcv(symbol, '4h', limit=100)
ohlcv_1d = exchange.fetch_ohlcv(symbol, '1d', limit=30)

df_15m = pd.DataFrame(ohlcv_15m, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
df_1h = pd.DataFrame(ohlcv_1h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
df_4h = pd.DataFrame(ohlcv_4h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
df_1d = pd.DataFrame(ohlcv_1d, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])

current = df_15m['close'].iloc[-1]
print(f'当前价格: ${current:.6f}')

# 计算RSI
def calc_rsi(prices, period=14):
    deltas = np.diff(prices)
    gain = np.where(deltas > 0, deltas, 0)
    loss = np.where(deltas < 0, -deltas, 0)
    avg_gain = np.mean(gain[:period])
    avg_loss = np.mean(loss[:period])
    if avg_loss == 0: return 100
    rs = avg_gain / avg_loss
    return 100 - (100 / (1 + rs))

rsi_15m = calc_rsi(df_15m['close'].values)
rsi_1h = calc_rsi(df_1h['close'].values)
rsi_4h = calc_rsi(df_4h['close'].values)
rsi_1d = calc_rsi(df_1d['close'].values)

print(f'\nRSI指标:')
print(f'  15分钟: {rsi_15m:.1f}')
print(f'  1小时: {rsi_1h:.1f}')
print(f'  4小时: {rsi_4h:.1f}')
print(f'  日线: {rsi_1d:.1f}')

# 趋势分析
ma20_1d = df_1d['close'].rolling(20).mean().iloc[-1]
ma7_1d = df_1d['close'].rolling(7).mean().iloc[-1]
trend = "上升" if ma7_1d > ma20_1d else "下降"
print(f'\n日线趋势:')
print(f'  MA7: ${ma7_1d:.6f}')
print(f'  MA20: ${ma20_1d:.6f}')
print(f'  趋势: {trend}')

# 价格变化
price_7d_ago = df_1d['close'].iloc[-8]
price_30d_ago = df_1d['close'].iloc[0]
change_7d = ((current - price_7d_ago) / price_7d_ago) * 100
change_30d = ((current - price_30d_ago) / price_30d_ago) * 100
change_watch = ((current - watch_price) / watch_price) * 100

print(f'\n价格变化:')
print(f'  7天: {change_7d:+.2f}%')
print(f'  30天: {change_30d:+.2f}%')
print(f'  vs 关注价${watch_price:.6f}: {change_watch:+.2f}%')

# 成交量
avg_vol_7d = df_1d['volume'].tail(7).mean()
current_vol = df_1d['volume'].iloc[-1]
vol_ratio = current_vol / avg_vol_7d if avg_vol_7d > 0 else 0

print(f'\n成交量:')
print(f'  当前: {current_vol:,.0f}')
print(f'  7日均: {avg_vol_7d:,.0f}')
print(f'  比率: {vol_ratio:.2f}x')

# 支撑位和阻力位
low_7d = df_1d['low'].tail(7).min()
high_7d = df_1d['high'].tail(7).max()
position = ((current - low_7d) / (high_7d - low_7d) * 100) if high_7d > low_7d else 50

print(f'\n支撑/阻力:')
print(f'  7日最低: ${low_7d:.6f}')
print(f'  7日最高: ${high_7d:.6f}')
print(f'  当前位置: {position:.1f}%')

# 买入信号评分
score = 0
reasons = []

print(f'\n💡 买入分析:')

# RSI超卖
if rsi_1h < 30 or rsi_4h < 30:
    print('  ✅ RSI超卖，反弹可能性大')
    score += 30
    reasons.append('RSI超卖')
elif rsi_15m < 40:
    print('  ⚠️ RSI偏低，有反弹空间')
    score += 15
    reasons.append('RSI偏低')
else:
    print('  ❌ RSI正常，不超卖')

# 趋势
if ma7_1d < ma20_1d:
    print('  ❌ 日线下降趋势，风险较高')
    score -= 20
else:
    print('  ✅ 日线上升趋势')
    score += 20
    reasons.append('趋势向上')

# 跌幅分析
if change_7d < -30:
    print(f'  ⚠️ 7天暴跌{abs(change_7d):.1f}%，可能严重超跌')
    score += 25
    reasons.append('严重超跌')
elif change_7d < -20:
    print(f'  ⚠️ 7天跌幅{abs(change_7d):.1f}%，可能超跌')
    score += 15
    reasons.append('超跌')
elif change_7d > 10:
    print(f'  ❌ 7天已涨{change_7d:.1f}%，追高风险')
    score -= 15
else:
    print(f'  ⚠️ 7天变化{change_7d:+.1f}%')

# 支撑位
if current < low_7d * 1.03:
    print('  ✅ 接近7日低点，强支撑')
    score += 20
    reasons.append('支撑位')
elif current < low_7d * 1.10:
    print('  ⚠️ 接近支撑区域')
    score += 10
else:
    print('  ⚠️ 远离支撑位')

# 成交量
if vol_ratio > 2.0:
    print('  ✅ 成交量放大，关注度高')
    score += 15
    reasons.append('放量')
elif vol_ratio > 1.5:
    print('  ⚠️ 成交量略增')
    score += 5

# vs关注价
if change_watch < -80:
    print(f'  ⚠️⚠️ 相比关注价暴跌{abs(change_watch):.1f}%！')
    print('     风险：可能继续下跌或已死亡')
    print('     机会：如果项目没死，反弹空间巨大')

print('\n' + '=' * 70)
print(f'📊 综合评分: {score}/100')

if score >= 60:
    decision = '✅ 建议买入'
    risk = '中等'
elif score >= 40:
    decision = '⚠️ 谨慎买入（小仓位）'
    risk = '较高'
elif score >= 20:
    decision = '⚠️ 观察等待更好机会'
    risk = '高'
else:
    decision = '❌ 不建议买入'
    risk = '极高'

print(f'决策: {decision}')
print(f'风险等级: {risk}')
print(f'信号理由: {", ".join(reasons) if reasons else "无明显信号"}')

print('\n📌 如果买入建议:')
print(f'  入场价: ${current:.6f}')
print(f'  止损: ${current * 0.92:.6f} (-8%)')
print(f'  止盈1: ${current * 1.15:.6f} (+15%)')
print(f'  止盈2: ${current * 1.30:.6f} (+30%)')
print(f'  仓位: 总资金的1-3%（高风险币种）')
print(f'  杠杆: 不建议使用（或最多2x）')

print('\n⚠️ 特别提醒:')
print(f'  1. MEME币相比关注价已跌{abs(change_watch):.1f}%')
print(f'  2. 需确认项目是否还在运营')
print(f'  3. 建议分批买入，不要一次性重仓')
print(f'  4. 设置严格止损，控制风险')
print('=' * 70)
