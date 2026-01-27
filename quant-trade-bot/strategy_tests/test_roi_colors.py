#!/usr/bin/env python3
# -*- coding: utf-8 -*-

from xmr_contract_monitor import XMRContractMonitor

# 创建监控实例
print("🎯 测试XMR监控系统 - 投资回报率和颜色显示")
print("="*50)

monitor = XMRContractMonitor(entry_price=502.41, leverage=10)

# 获取当前价格
current_price = monitor.get_current_price()
print(f"💰 当前价格: ${current_price:.2f}")

# 计算盈亏数据
pnl_data = monitor.calculate_pnl(current_price)

print(f"📊 详细盈亏数据:")
print(f"   📈 入场价格: ${monitor.entry_price:.2f}")
print(f"   📊 价格变化: {pnl_data['price_change_percent']:+.2f}%")
print(f"   💎 杠杆倍数: {monitor.leverage}x")

# 显示投资回报率（带百分比）
roi_percent = pnl_data['roi']
print(f"   💵 投资回报率: {roi_percent:+.2f}%")

# 显示盈亏金额（带颜色）
pnl_amount = pnl_data['unrealized_pnl_usd'] 
if pnl_amount >= 0:
    color_code = '\033[92m'  # 绿色
    emoji = '🟢'
else:
    color_code = '\033[91m'  # 红色
    emoji = '🔴'
reset_code = '\033[0m'

print(f"   💰 盈亏金额: {color_code}{emoji}${pnl_amount:+.2f}U{reset_code}")
print(f"   💰 Telegram显示: {emoji}${pnl_amount:+.2f}U")

print("\n✅ 投资回报率百分比和颜色显示功能测试完成！")