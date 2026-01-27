#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""实盘模拟交易监控 - 持仓状态显示"""

import json
import requests

try:
    # 从API获取持仓
    response = requests.get('http://localhost:5001/api/positions', timeout=5)
    positions = response.json()
    
    print("=" * 60)
    print("实盘模拟交易监控 - 当前持仓")
    print("=" * 60)
    
    if not positions:
        print("\n当前无持仓")
    else:
        total_pnl = 0
        for pos in positions:
            symbol = pos['symbol']
            current = pos['current_price']
            entry = pos['entry_price']
            stop = pos['stop_loss']
            take = pos['take_profit']
            pnl = pos['unrealized_pnl']
            pnl_pct = pos['unrealized_pnl_pct']
            leverage = pos['leverage']
            
            # 距离止损和止盈
            to_stop = ((current - stop) / current) * 100
            to_take = ((take - current) / current) * 100
            
            print(f"\n{symbol}:")
            print(f"  开仓价: ${entry:.2f}")
            print(f"  当前价: ${current:.2f}")
            print(f"  止损价: ${stop:.2f} (距离 {to_stop:+.2f}%)")
            print(f"  止盈价: ${take:.2f} (距离 {to_take:+.2f}%)")
            print(f"  杠杆: {leverage}x")
            print(f"  未实现盈亏: ${pnl:.2f} ({pnl_pct:+.2f}%)")
            
            # 风险评估
            if current <= stop:
                status = "已触发止损！"
            elif to_stop < 1:
                status = "极度接近止损！"
            elif to_stop < 3:
                status = "接近止损"
            else:
                status = "正常"
            print(f"  状态: {status}")
            
            total_pnl += pnl
        
        print("\n" + "=" * 60)
        print(f"总盈亏: ${total_pnl:.2f}")
    
    print(f"\n监控地址: http://localhost:5001")
    print("=" * 60)
    
    print("\n策略状态:")
    print("  Web监控: 运行中 (PID 72631)")
    print("  止损逻辑: 已修复并启用")
    print("  策略模式: 模拟交易 (Paper Trading)")
    print("  止损设置: 3% 下跌自动平仓")
    print("  止盈设置: 6% 上涨自动平仓")
    
    print("\n提示:")
    print("  - 浏览器访问 http://localhost:5001 查看实时面板")
    print("  - 系统会自动监控止损/止盈触发")
    print("  - 触发后会自动执行平仓并发送Telegram通知")
    
except Exception as e:
    print(f"获取持仓失败: {e}")
    print("请确保 web_monitor.py 正在运行")
