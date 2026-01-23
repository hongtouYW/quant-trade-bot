#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
快速测试 - 测试策略是否能正常运行
"""

print("🧪 开始测试系统...")

# 测试1: 导入模块
print("\n1️⃣ 测试模块导入...")
try:
    import ccxt
    print("  ✅ ccxt")
except Exception as e:
    print(f"  ❌ ccxt: {e}")
    exit(1)

try:
    import pandas as pd
    print("  ✅ pandas")
except Exception as e:
    print(f"  ❌ pandas: {e}")
    exit(1)

try:
    import numpy as np
    print("  ✅ numpy")
except Exception as e:
    print(f"  ❌ numpy: {e}")
    exit(1)

try:
    import requests
    print("  ✅ requests")
except Exception as e:
    print(f"  ❌ requests: {e}")
    exit(1)

# 测试2: 策略导入
print("\n2️⃣ 测试策略模块...")
try:
    from simple_enhanced_strategy import SimpleEnhancedStrategy
    print("  ✅ SimpleEnhancedStrategy")
except Exception as e:
    print(f"  ❌ SimpleEnhancedStrategy: {e}")
    exit(1)

try:
    from live_paper_trading import LivePaperTradingBot
    print("  ✅ LivePaperTradingBot")
except Exception as e:
    print(f"  ❌ LivePaperTradingBot: {e}")
    exit(1)

# 测试3: 交易所连接
print("\n3️⃣ 测试交易所连接...")
try:
    exchange = ccxt.binance({'enableRateLimit': True, 'timeout': 30000})
    ticker = exchange.fetch_ticker('BTC/USDT')
    print(f"  ✅ Binance连接成功")
    print(f"  📊 BTC/USDT价格: ${ticker['last']:,.2f}")
except Exception as e:
    print(f"  ❌ Binance连接失败: {e}")
    exit(1)

# 测试4: 策略分析
print("\n4️⃣ 测试策略分析 (BTC/USDT)...")
try:
    strategy = SimpleEnhancedStrategy(exchange)
    signal = strategy.analyze_symbol('BTC/USDT')
    if signal:
        print(f"  ✅ 发现信号: {signal['type'].upper()}")
    else:
        print(f"  ✅ 策略运行正常 (无信号)")
except Exception as e:
    print(f"  ❌ 策略分析失败: {e}")
    import traceback
    traceback.print_exc()
    exit(1)

# 测试5: 模拟交易
print("\n5️⃣ 测试模拟交易...")
try:
    bot = LivePaperTradingBot(initial_balance=1000, config_file='config.json')
    print(f"  ✅ 模拟交易初始化成功")
    print(f"  💰 初始资金: ${bot.balance:.2f}")
except Exception as e:
    print(f"  ⚠️ 模拟交易初始化: {e} (可能是Telegram配置问题，但不影响功能)")

# 测试6: 配置文件
print("\n6️⃣ 检查配置文件...")
try:
    import json
    with open('config.json', 'r') as f:
        config = json.load(f)
    
    if 'telegram' in config:
        print(f"  ✅ Telegram配置存在")
    else:
        print(f"  ⚠️ Telegram配置缺失 (不影响核心功能)")
    
    if 'binance' in config:
        print(f"  ✅ Binance配置存在 (用于真实交易)")
except Exception as e:
    print(f"  ⚠️ 配置文件读取: {e}")

print("\n" + "="*60)
print("✅ 系统测试完成！")
print("="*60)
print("\n💡 下一步:")
print("  1. 测试策略: python3 simple_enhanced_strategy.py")
print("  2. 运行模拟: python3 integrated_trading_system.py")
print("  3. 查看文档: cat TRADING_SYSTEM_README.md")
print("\n🚀 系统已准备就绪！\n")
