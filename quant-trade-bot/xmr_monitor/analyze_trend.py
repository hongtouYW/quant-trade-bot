#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests

url = "https://api.coingecko.com/api/v3/coins/monero"
r = requests.get(url)

if r.status_code == 200:
    data = r.json()
    market = data['market_data']
    
    current = market['current_price']['usd']
    change_24h = market['price_change_percentage_24h']
    change_7d = market['price_change_percentage_7d']
    change_14d = market['price_change_percentage_14d']
    high_24h = market['high_24h']['usd']
    low_24h = market['low_24h']['usd']
    
    print("=== XMR 价格趋势分析 ===")
    print(f"当前价格: ${current:.2f}")
    print(f"24h区间: ${low_24h:.2f} - ${high_24h:.2f}")
    print(f"\n=== 涨跌幅 ===")
    print(f"24小时: {change_24h:+.2f}%")
    print(f"7天: {change_7d:+.2f}%")
    print(f"14天: {change_14d:+.2f}%")
    
    print(f"\n=== 趋势判断 ===")
    
    if change_24h > 2:
        trend_24h = "短期上涨"
    elif change_24h > -2:
        trend_24h = "短期震荡"
    else:
        trend_24h = "短期下跌"
    
    if change_7d > 5:
        trend_7d = "中期上涨"
    elif change_7d > -5:
        trend_7d = "中期震荡"
    else:
        trend_7d = "中期下跌"
    
    print(f"24h趋势: {trend_24h}")
    print(f"7d趋势: {trend_7d}")
    
    print(f"\n=== MACD 推断 ===")
    
    if change_24h > 0 and change_7d > 0:
        macd = "可能好转（价格连续上涨）"
    elif change_24h > 0 and change_7d < 0:
        macd = "短期反弹（但中期仍跌）"
    elif change_24h < 0 and change_7d > 0:
        macd = "短期回调（中期上涨中）"
    else:
        macd = "持续走弱"
    
    print(f"MACD状态: {macd}")
    
    price_position = (current - low_24h) / (high_24h - low_24h) * 100
    print(f"\n24h价格位置: {price_position:.0f}%")
    
    if price_position > 70:
        position = "接近24h高点（可能回调）"
    elif price_position > 50:
        position = "中上位置（偏强）"
    elif price_position > 30:
        position = "中下位置（偏弱）"
    else:
        position = "接近24h低点（可能反弹）"
    
    print(f"位置评价: {position}")
    
    entry = 480.43
    margin = 3583.61
    
    print(f"\n=== 你的仓位分析 ===")
    print(f"开仓价: ${entry:.2f}")
    print(f"当前价: ${current:.2f}")
    price_diff = ((current - entry) / entry) * 100
    roi = price_diff * 20
    loss = (roi / 100) * margin
    
    print(f"价格变化: {price_diff:+.2f}%")
    print(f"20x ROI: {roi:+.1f}%")
    print(f"盈亏: ${loss:+.0f}")
    
    print(f"\n=== 操作建议 ===")
    
    if change_24h > 2 and change_7d > 0:
        advice = "有上升趋势，可持仓观望，$475附近减仓30%"
    elif change_24h > 0:
        advice = "短期反弹中，可等$470-475减仓，注意回落"
    elif current < 463:
        advice = "接近$460止损线，建议立即减仓50%"
    elif current < 470:
        advice = "风险区域，建议$465-470反弹减仓30-50%"
    else:
        advice = "观望为主，$475附近减仓"
    
    print(advice)
    
else:
    print(f"API错误: {r.status_code}")
