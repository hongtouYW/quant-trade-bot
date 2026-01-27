#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests

r = requests.get("https://api.coingecko.com/api/v3/coins/monero")
if r.status_code == 200:
    data = r.json()
    market = data['market_data']
    
    current = market['current_price']['usd']
    change_1h = market.get('price_change_percentage_1h_in_currency', {}).get('usd', 0)
    change_24h = market['price_change_percentage_24h']
    high_24h = market['high_24h']['usd']
    low_24h = market['low_24h']['usd']
    
    print("=== XMR 实时市场情绪 ===")
    print(f"当前价格: ${current:.2f}")
    print(f"24h区间: ${low_24h:.2f} - ${high_24h:.2f}")
    print(f"24h最低点: ${low_24h:.2f}")
    print(f"\n1小时涨跌: {change_1h:+.2f}%")
    print(f"24小时涨跌: {change_24h:+.2f}%")
    
    position = (current - low_24h) / (high_24h - low_24h) * 100
    print(f"\n24h价格位置: {position:.0f}%")
    
    if current <= 460:
        print("\n已跌破$460止损线！")
    elif current <= 463:
        print("\n接近$460止损线")
    elif current > 460 and current <= 465:
        print("\n刚从$460反弹")
    
    if low_24h <= 460 and current > 460:
        bounce = current - 460
        print(f"\n从$460支撑位反弹: +${bounce:.2f}")
        print("确认有买盘支撑在$460")
    
    if change_1h > 1:
        sentiment = "短期快速反弹（买盘强）"
    elif change_1h > 0:
        sentiment = "小幅反弹（有支撑）"
    elif change_1h > -1:
        sentiment = "震荡（多空博弈）"
    else:
        sentiment = "继续下跌（卖压大）"
    
    print(f"\n当前情绪: {sentiment}")
    
    entry = 480.43
    margin = 3583.61
    
    roi = ((current - entry) / entry) * 100 * 20
    loss = (roi / 100) * margin
    remaining = margin + loss
    
    print(f"\n=== 你的仓位 ===")
    print(f"开仓价: ${entry:.2f}")
    print(f"当前价: ${current:.2f}")
    print(f"ROI: {roi:+.1f}%")
    print(f"盈亏: ${loss:+.0f}")
    print(f"剩余保证金: ${remaining:.0f}")
    
    print(f"\n=== 关键判断 ===")
    
    if current > 460 and low_24h <= 460:
        print("$460出现支撑买盘（你观察正确！）")
        print("这是关键位置，多空分水岭")
        
        if current > 463:
            print("\n如果守住$463:")
            print("  可观望，等$465-470减仓30-50%")
        else:
            print("\n虽有支撑，但仍在危险区:")
            print("  $462-465反弹立即减仓30%")
            print("  再跌破$460必须止损")
    
    if current <= 460:
        stop_roi = ((460 - entry) / entry) * 100 * 20
        stop_loss = (stop_roi / 100) * margin
        print(f"\n已触发止损！")
        print(f"止损损失: {stop_roi:+.1f}% (${stop_loss:+.0f})")
        print(f"建议: 立即平仓50-70%")
    
    print(f"\n=== 操作建议 ===")
    
    if current > 465:
        print("守住$465: 持仓观望，等$470-475减仓")
    elif current > 463:
        print("在$463-465: 观望，准备减仓")
    elif current > 460:
        print("在$460-463（支撑位）:")
        print("  1. 反弹到$462-465 -> 减仓30-50%")
        print("  2. 再跌破$460 -> 必须止损")
        print("  3. 不要期待大反弹")
    else:
        print("跌破$460: 立即止损")

else:
    print("API请求失败")
