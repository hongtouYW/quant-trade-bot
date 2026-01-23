#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
测试做多做空功能和报表
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from enhanced_paper_trading import EnhancedPaperTradingBot

print("🧪 测试做多做空功能和报表...")
print()

# 创建机器人
bot = EnhancedPaperTradingBot(initial_balance=1000, leverage=3)

# 获取价格
btc_price = bot.get_current_price('BTC/USDT')
eth_price = bot.get_current_price('ETH/USDT')

if btc_price and eth_price:
    # 测试做多
    print("\n" + "="*60)
    print("测试1: 做多 BTC/USDT")
    print("="*60)
    quantity_btc, position_value, margin = bot.calculate_position_size('BTC/USDT', btc_price)
    bot.simulate_buy('BTC/USDT', btc_price, quantity_btc, position_type='long')
    
    # 测试做空（注意：实际交易系统需要支持做空，这里只是演示图标）
    print("\n" + "="*60)
    print("测试2: 做空 ETH/USDT")
    print("="*60)
    quantity_eth, position_value, margin = bot.calculate_position_size('ETH/USDT', eth_price)
    bot.simulate_buy('ETH/USDT', eth_price, quantity_eth, position_type='short')
    
    # 显示持仓
    bot.display_portfolio()
    
    # 生成报表
    print("\n" + "="*60)
    print("测试3: 生成每日报表")
    print("="*60)
    bot.send_daily_report()
    
    print("\n✅ 测试完成！")
    print("\n💡 查看交易记录:")
    print("   python3 view_trading_records.py --all")
    print("\n💡 查看今日报表:")
    print("   python3 generate_report.py")

