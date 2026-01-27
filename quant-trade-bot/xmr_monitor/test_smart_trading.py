#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""测试智能交易监控 - 快速测试"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from smart_trading_monitor import SmartTradingMonitor

print("🧪 测试智能交易监控...")
print("="*60)

monitor = SmartTradingMonitor()

if monitor.position:
    print("\n✅ 当前模式: 持仓监控")
    print(f"   币种: {monitor.position['symbol']}")
    print(f"   入场价: ${monitor.position['entry_price']:.2f}")
    print(f"   杠杆: {monitor.position['leverage']}x")
    
    # 测试获取价格
    symbol = monitor.position['symbol'].replace('/USDT', '')
    print(f"\n📊 获取{symbol}价格...")
    price = monitor.get_price(symbol)
    if price:
        print(f"   现价: ${price:.2f}")
        pnl = monitor.calculate_pnl(price)
        print(f"   ROI: {pnl['roi']:+.2f}%")
        print(f"   盈亏: ${pnl['pnl_amount']:+.2f}U")
else:
    print("\n✅ 当前模式: 信号扫描")
    print(f"   监控币种: {', '.join(monitor.watch_symbols)}")
    
    # 测试扫描信号
    print(f"\n🔍 开始扫描买入信号...")
    signals = monitor.scan_buy_signals()
    
    if signals:
        print(f"\n✅ 发现 {len(signals)} 个买入机会:")
        for sig in signals:
            print(f"\n   {sig['symbol']}: ${sig['price']:.2f}")
            print(f"   理由: {', '.join(sig['reasons'])}")
            print(f"   信心度: {sig['confidence']}%")
    else:
        print("\nℹ️  暂无强烈买入信号")

print("\n✅ 测试完成!")
