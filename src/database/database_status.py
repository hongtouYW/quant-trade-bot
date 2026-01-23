#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
数据库状态检查工具
快速查看数据库状态和统计信息
"""

import os
from database_framework import TradingDataManager
from datetime import datetime

def check_database_status():
    """检查数据库状态"""
    print("🔍 数据库状态检查")
    print("=" * 40)
    
    # 检查数据库文件
    db_file = 'trading_data.db'
    if os.path.exists(db_file):
        file_size = os.path.getsize(db_file)
        print(f"📁 数据库文件: {db_file}")
        print(f"📏 文件大小: {file_size:,} 字节 ({file_size/1024:.1f} KB)")
        print(f"🕐 最后修改: {datetime.fromtimestamp(os.path.getmtime(db_file))}")
    else:
        print("❌ 数据库文件不存在")
        return
    
    # 连接数据库并获取统计
    try:
        db_manager = TradingDataManager()
        
        # 交易统计
        stats = db_manager.get_performance_stats()
        print(f"\n📊 交易统计:")
        print(f"   总交易数: {stats['total_trades']}")
        print(f"   获胜交易: {stats['winning_trades']}")
        print(f"   失败交易: {stats['losing_trades']}")
        print(f"   胜率: {stats['win_rate']:.1f}%")
        print(f"   总盈亏: ${stats['total_pnl']:.2f}")
        print(f"   平均盈亏: ${stats['avg_pnl']:.2f}")
        
        # 信号统计
        signals = db_manager.get_signals(limit=1000)
        print(f"\n🎯 信号统计:")
        print(f"   总信号数: {len(signals)}")
        
        # 按策略统计
        strategy_count = {}
        signal_type_count = {}
        
        for signal in signals:
            # 处理字典格式的信号数据
            strategy_name = signal.get('strategy_name', 'unknown')
            signal_type = signal.get('signal_type', 'unknown')
            
            strategy_count[strategy_name] = strategy_count.get(strategy_name, 0) + 1
            signal_type_count[signal_type] = signal_type_count.get(signal_type, 0) + 1
        
        if strategy_count:
            print(f"   策略分布:")
            for strategy, count in sorted(strategy_count.items(), key=lambda x: x[1], reverse=True):
                print(f"     - {strategy}: {count}")
        
        if signal_type_count:
            print(f"   信号类型分布:")
            for signal_type, count in sorted(signal_type_count.items(), key=lambda x: x[1], reverse=True):
                print(f"     - {signal_type.upper()}: {count}")
        
        # 最近活动
        recent_trades = db_manager.get_trades(limit=3)
        if recent_trades:
            print(f"\n📈 最近交易:")
            for trade in recent_trades:
                pnl = trade.get('pnl', 0)
                pnl_status = "✅" if pnl >= 0 else "❌"
                print(f"   {pnl_status} {trade.get('symbol', 'N/A')} {trade.get('side', 'N/A').upper()} "
                      f"${trade.get('amount', 0):.2f} 盈亏: ${pnl:.2f}")
        
        recent_signals = db_manager.get_signals(limit=3)
        if recent_signals:
            print(f"\n🎯 最近信号:")
            for signal in recent_signals:
                confidence = signal.get('confidence', 0)
                confidence_emoji = "🔥" if confidence > 0.8 else "⭐"
                print(f"   {confidence_emoji} {signal.get('symbol', 'N/A')} {signal.get('signal_type', 'N/A').upper()} "
                      f"置信度: {confidence:.1%}")
        
        print(f"\n✅ 数据库状态正常")
        
    except Exception as e:
        print(f"❌ 数据库连接错误: {e}")

def main():
    """主函数"""
    check_database_status()

if __name__ == '__main__':
    main()