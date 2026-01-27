#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""测试智能监控 - 单次运行"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from smart_position_monitor import SmartPositionMonitor

# 创建监控实例
monitor = SmartPositionMonitor()

if monitor.position:
    print("\n" + "="*60)
    print("🧪 测试智能监控...")
    print("="*60)
    
    # 获取当前价格
    current_price = monitor.get_price()
    
    if current_price:
        # 计算盈亏
        pnl_data = monitor.calculate_pnl(current_price)
        
        # 显示状态
        monitor.display_status(current_price, pnl_data)
        
        # 发送测试通知
        print("\n📤 发送Telegram测试通知...")
        monitor.send_position_update(current_price, pnl_data)
        
        print("\n✅ 测试完成！")
    else:
        print("❌ 无法获取价格")
else:
    print("ℹ️  当前无活跃持仓")
