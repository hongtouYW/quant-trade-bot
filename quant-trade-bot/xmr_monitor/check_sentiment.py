#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests
from datetime import datetime

# 获取XMR当前价格和市场数据
r1 = requests.get('https://api.binance.com/api/v3/ticker/24hr?symbol=XMRUSDT')
data = r1.json()

current_price = float(data['lastPrice'])
high_24h = float(data['highPrice'])
low_24h = float(data['lowPrice'])
change_24h = float(data['priceChangePercent'])
volume_24h = float(data['volume'])

# 获取5分钟K线看短期趋势
r2 = requests.get('https://api.binance.com/api/v3/klines?symbol=XMRUSDT&interval=5m&limit=12')
klines = r2.json()

print('=== XMR 市场情绪分析 ===')
print(f'当前价格: ${current_price:.2f}')
print(f'24h涨跌: {change_24h:+.2f}%')
print(f'24h区间: ${low_24h:.2f} - ${high_24h:.2f}')
print(f'24h交易量: {volume_24h:,.0f} XMR')

# 分析短期趋势（最近1小时）
prices = [float(k[4]) for k in klines]
volumes = [float(k[5]) for k in klines]

price_change_1h = ((prices[-1] - prices[0]) / prices[0]) * 100
avg_volume = sum(volumes) / len(volumes)
recent_volume = volumes[-1]

print(f'\n=== 短期趋势（1小时）===')
print(f'1小时涨跌: {price_change_1h:+.2f}%')
print(f'最新5分钟量: {recent_volume:.0f} XMR')
print(f'1小时平均量: {avg_volume:.0f} XMR')
print(f'成交活跃度: {(recent_volume/avg_volume)*100:.0f}%')

# 判断趋势
if price_change_1h > 2:
    trend = '🚀 强势上涨'
elif price_change_1h > 0.5:
    trend = '📈 温和上涨'
elif price_change_1h > -0.5:
    trend = '📊 震荡整理'
elif price_change_1h > -2:
    trend = '📉 温和下跌'
else:
    trend = '💥 快速下跌'

# 判断情绪
if change_24h > 5:
    sentiment = '极度乐观（贪婪）'
elif change_24h > 2:
    sentiment = '乐观（偏多）'
elif change_24h > -2:
    sentiment = '中性（观望）'
elif change_24h > -5:
    sentiment = '悲观（恐慌）'
else:
    sentiment = '极度悲观（恐慌抛售）'

print(f'\n=== 情绪判断 ===')
print(f'短期趋势: {trend}')
print(f'市场情绪: {sentiment}')

# 你的仓位状态
entry_price = 480.43
margin = 3583.61
leverage = 20
position_value = margin * leverage
position_xmr = position_value / entry_price

loss_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
loss_amount = (loss_percent / 100) * margin

print(f'\n=== 你的仓位状态 ===')
print(f'开仓价: ${entry_price:.2f}')
print(f'当前价: ${current_price:.2f}')
print(f'价格差: ${current_price - entry_price:+.2f} ({((current_price - entry_price) / entry_price) * 100:+.2f}%)')
print(f'20x杠杆ROI: {loss_percent:+.2f}%')
print(f'盈亏: ${loss_amount:+.2f}')

if current_price >= 475:
    action = '🎯 建议减仓30%'
elif current_price >= 470:
    action = '⚡ 准备减仓'
elif current_price >= 463:
    action = '📊 持仓观望'
elif current_price >= 460:
    action = '⚠️ 接近止损！'
else:
    action = '🚨 立即止损！'

print(f'操作建议: {action}')

# 距离关键价位
print(f'\n=== 关键价位距离 ===')
print(f'距离$475减仓位: ${475 - current_price:+.2f} ({((475 - current_price) / current_price) * 100:+.2f}%)')
print(f'距离$470预警位: ${470 - current_price:+.2f} ({((470 - current_price) / current_price) * 100:+.2f}%)')
print(f'距离$463警戒位: ${463 - current_price:+.2f} ({((463 - current_price) / current_price) * 100:+.2f}%)')
print(f'距离$460止损位: ${460 - current_price:+.2f} ({((460 - current_price) / current_price) * 100:+.2f}%)')
