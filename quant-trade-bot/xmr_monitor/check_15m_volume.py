#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests
from datetime import datetime

url = 'https://api.binance.com/api/v3/klines'
params = {'symbol': 'XMRUSDT', 'interval': '15m', 'limit': 10}
response = requests.get(url, params=params)
klines = response.json()

print('=== XMR 15分钟K线数据 (最近10根) ===')
print('时间                开盘      最高      最低      收盘      成交量(XMR)    成交额($)')
print('=' * 95)

total_volume = 0
total_amount = 0

for i, kline in enumerate(klines):
    timestamp = datetime.fromtimestamp(kline[0]/1000).strftime('%Y-%m-%d %H:%M')
    open_price = float(kline[1])
    high = float(kline[2])
    low = float(kline[3])
    close = float(kline[4])
    volume = float(kline[5])
    amount = float(kline[7])
    
    total_volume += volume
    total_amount += amount
    
    marker = '*' if i == len(klines)-1 else ' '
    print(f'{marker} {timestamp}  ${open_price:7.2f}  ${high:7.2f}  ${low:7.2f}  ${close:7.2f}  {volume:10.2f}  ${amount:12,.0f}')

print('=' * 95)
print(f'\n📊 统计数据:')
print(f'   总成交量: {total_volume:.2f} XMR')
print(f'   总成交额: ${total_amount:,.0f}')
print(f'   平均每15分钟: {total_volume/10:.2f} XMR (${total_amount/10:,.0f})')
print(f'   预估1小时: {total_volume/10*4:.2f} XMR')
print(f'   预估24小时: {total_volume/10*96:.2f} XMR')

current_price = float(klines[-1][4])
print(f'\n💰 当前价格: ${current_price:.2f}')

your_position = 153.955
avg_15m = total_volume/10
avg_1h = avg_15m * 4

print(f'\n🔍 流动性分析:')
print(f'   你的持仓: {your_position} XMR')
print(f'   15分钟平均量: {avg_15m:.2f} XMR')
print(f'   持仓占15分钟量: {(your_position/avg_15m)*100:.1f}%')
print(f'   持仓占1小时量: {(your_position/avg_1h)*100:.1f}%')

if your_position/avg_15m > 1:
    print(f'\n   ⚠️ 警告: 持仓 > 15分钟量，建议分{int(your_position/avg_15m)+1}批平仓')
elif your_position/avg_15m > 0.5:
    print(f'   ⚠️ 注意: 持仓较大，建议分2-3批平仓，每批{your_position/3:.1f} XMR')
else:
    print(f'   ✅ 流动性充足，单次平仓影响较小')

# 计算滑点风险
if your_position/avg_15m > 1:
    print(f'   预估滑点: 2-5%')
elif your_position/avg_15m > 0.5:
    print(f'   预估滑点: 1-2%')
else:
    print(f'   预估滑点: <1%')
