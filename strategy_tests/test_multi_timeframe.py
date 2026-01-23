#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
多时间框架策略测试器
测试日线趋势 + 15分钟入场的组合效果
"""

import sys
import os

# 添加项目根目录到Python路径
project_root = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, project_root)

from multi_timeframe_strategy import MultiTimeFrameStrategy
import json
from datetime import datetime

def test_strategy():
    """测试多时间框架策略"""
    print("🎯 测试多时间框架策略")
    print("=" * 50)
    
    strategy = MultiTimeFrameStrategy()
    
    # 运行单次分析
    results = strategy.run_analysis()
    
    if results:
        print(f"\n✅ 发现 {len(results)} 个交易机会:")
        print("-" * 30)
        
        for i, result in enumerate(results, 1):
            print(f"\n📊 机会 {i}: {result['symbol']}")
            print(f"   📈 日线趋势: {result['trend']['direction']} (强度: {result['trend']['strength']:.2f})")
            print(f"   ⚡ 入场信号: {result['entry']['signal']} (信心: {result['entry']['confidence']:.2f})")
            print(f"   💰 建议仓位: {result['position']['size']:.4f}")
            print(f"   🔧 杠杆倍数: {result['position']['leverage']}x")
            print(f"   💵 交易金额: ${result['position']['value']:.2f}")
            
        # 保存详细结果
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f'strategy_test_result_{timestamp}.json'
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2, default=str)
            
        print(f"\n📁 详细结果已保存到: {filename}")
    else:
        print("\n⏸️  当前市场无明确交易机会")
        print("💡 建议:")
        print("   - 等待趋势明确")
        print("   - 关注关键支撑阻力位")
        print("   - 监控成交量变化")

def show_config():
    """显示策略配置"""
    print("\n📋 当前策略配置:")
    print("-" * 20)
    print("🕐 时间框架:")
    print("   日线趋势: 判断主要方向")
    print("   15分钟入场: 寻找精准入场点")
    print("\n🎯 信号条件:")
    print("   趋势确认: MA + MACD + RSI")
    print("   入场触发: 突破 + 背离 + 金叉死叉")
    print("\n💰 资金管理:")
    print("   单笔风险: 2%")
    print("   最大杠杆: 3倍")
    print("   仓位调整: 基于信心度")

if __name__ == "__main__":
    print("🚀 多时间框架策略测试器")
    print("💡 日线看趋势 + 15分钟找入场")
    
    # 显示配置
    show_config()
    
    # 运行测试
    try:
        test_strategy()
    except KeyboardInterrupt:
        print("\n👋 测试被用户中断")
    except Exception as e:
        print(f"\n❌ 测试出错: {e}")
        print("💡 请检查网络连接和API配置")
    
    print(f"\n⏰ 测试完成 - {datetime.now().strftime('%H:%M:%S')}")