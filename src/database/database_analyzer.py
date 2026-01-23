#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
数据库查询和分析工具
提供便捷的数据查询和分析功能
"""

import os
from datetime import datetime, timedelta
from database_framework import TradingDataManager
import json


class DatabaseAnalyzer:
    """数据库分析工具"""
    
    def __init__(self):
        self.db_manager = TradingDataManager()
    
    def run_interactive_query(self):
        """运行交互式查询"""
        while True:
            print("\n" + "=" * 50)
            print("📊 数据库查询分析工具")
            print("=" * 50)
            print("1. 📈 交易统计概览")
            print("2. 🎯 策略信号分析")
            print("3. 💰 盈亏详情")
            print("4. 🔍 自定义查询")
            print("5. 📱 导出数据")
            print("0. ❌ 退出")
            
            choice = input("\n选择操作 (0-5): ").strip()
            
            if choice == '0':
                print("👋 再见!")
                break
            elif choice == '1':
                self.show_trading_overview()
            elif choice == '2':
                self.show_signal_analysis()
            elif choice == '3':
                self.show_pnl_details()
            elif choice == '4':
                self.custom_query()
            elif choice == '5':
                self.export_data()
            else:
                print("❌ 无效选择，请重试")
    
    def show_trading_overview(self):
        """显示交易统计概览"""
        print("\n📈 交易统计概览")
        print("-" * 30)
        
        # 基本统计
        stats = self.db_manager.get_performance_stats()
        print(f"总交易数: {stats['total_trades']}")
        print(f"获胜交易: {stats['winning_trades']}")
        print(f"失败交易: {stats['losing_trades']}")
        print(f"胜率: {stats['win_rate']:.1f}%")
        print(f"总盈亏: ${stats['total_pnl']:.2f}")
        print(f"平均每笔盈亏: ${stats['avg_pnl']:.2f}")
        
        # 最近交易
        recent_trades = self.db_manager.get_trades(limit=5)
        if recent_trades:
            print(f"\n📋 最近5笔交易:")
            for trade in recent_trades:
                pnl_status = "✅" if trade.pnl >= 0 else "❌"
                print(f"   {pnl_status} {trade.symbol} {trade.side.upper()} "
                      f"${trade.amount:.2f} @ ${trade.price:.4f} "
                      f"盈亏: ${trade.pnl:.2f}")
    
    def show_signal_analysis(self):
        """显示策略信号分析"""
        print("\n🎯 策略信号分析")
        print("-" * 30)
        
        # 获取所有信号
        signals = self.db_manager.get_signals(limit=100)
        
        if not signals:
            print("❌ 暂无信号数据")
            return
        
        # 按策略统计
        strategy_stats = {}
        signal_type_stats = {}
        
        for signal in signals:
            # 策略统计
            if signal.strategy_name not in strategy_stats:
                strategy_stats[signal.strategy_name] = 0
            strategy_stats[signal.strategy_name] += 1
            
            # 信号类型统计
            if signal.signal_type not in signal_type_stats:
                signal_type_stats[signal.signal_type] = 0
            signal_type_stats[signal.signal_type] += 1
        
        print(f"📊 策略分布:")
        for strategy, count in strategy_stats.items():
            print(f"   {strategy}: {count} 个信号")
        
        print(f"\n📈 信号类型分布:")
        for signal_type, count in signal_type_stats.items():
            print(f"   {signal_type.upper()}: {count} 个信号")
        
        # 最近信号
        print(f"\n📋 最近5个信号:")
        for signal in signals[:5]:
            confidence_emoji = "🔥" if signal.confidence > 0.8 else "⭐" if signal.confidence > 0.6 else "💡"
            print(f"   {confidence_emoji} {signal.symbol} {signal.signal_type.upper()} "
                  f"置信度: {signal.confidence:.1%} "
                  f"策略: {signal.strategy_name}")
    
    def show_pnl_details(self):
        """显示盈亏详情"""
        print("\n💰 盈亏详情")
        print("-" * 30)
        
        trades = self.db_manager.get_trades(limit=50)
        
        if not trades:
            print("❌ 暂无交易数据")
            return
        
        # 分组统计
        winning_trades = [t for t in trades if t.pnl > 0]
        losing_trades = [t for t in trades if t.pnl < 0]
        break_even = [t for t in trades if t.pnl == 0]
        
        print(f"🏆 获胜交易: {len(winning_trades)} 笔")
        if winning_trades:
            total_win = sum(t.pnl for t in winning_trades)
            avg_win = total_win / len(winning_trades)
            max_win = max(t.pnl for t in winning_trades)
            print(f"   总盈利: ${total_win:.2f}")
            print(f"   平均盈利: ${avg_win:.2f}")
            print(f"   最大盈利: ${max_win:.2f}")
        
        print(f"\n💥 失败交易: {len(losing_trades)} 笔")
        if losing_trades:
            total_loss = sum(t.pnl for t in losing_trades)
            avg_loss = total_loss / len(losing_trades)
            max_loss = min(t.pnl for t in losing_trades)
            print(f"   总亏损: ${total_loss:.2f}")
            print(f"   平均亏损: ${avg_loss:.2f}")
            print(f"   最大亏损: ${max_loss:.2f}")
        
        print(f"\n⚖️ 保本交易: {len(break_even)} 笔")
        
        # 按币种统计
        symbol_pnl = {}
        for trade in trades:
            if trade.symbol not in symbol_pnl:
                symbol_pnl[trade.symbol] = 0
            symbol_pnl[trade.symbol] += trade.pnl
        
        if symbol_pnl:
            print(f"\n📊 按币种盈亏:")
            for symbol, pnl in sorted(symbol_pnl.items(), key=lambda x: x[1], reverse=True):
                status = "🟢" if pnl > 0 else "🔴" if pnl < 0 else "⚪"
                print(f"   {status} {symbol}: ${pnl:.2f}")
    
    def custom_query(self):
        """自定义查询"""
        print("\n🔍 自定义查询")
        print("-" * 30)
        print("1. 查看指定币种交易")
        print("2. 查看指定策略信号")
        print("3. 查看指定日期范围数据")
        print("4. 查看高置信度信号")
        
        sub_choice = input("\n选择查询类型 (1-4): ").strip()
        
        if sub_choice == '1':
            symbol = input("输入币种代码 (例如: BTCUSDT): ").strip().upper()
            trades = self.db_manager.get_trades(symbol=symbol)
            print(f"\n📊 {symbol} 交易记录 ({len(trades)} 笔):")
            for trade in trades:
                pnl_status = "✅" if trade.pnl >= 0 else "❌"
                print(f"   {pnl_status} {trade.side.upper()} ${trade.amount:.2f} @ ${trade.price:.4f} "
                      f"盈亏: ${trade.pnl:.2f} 时间: {trade.timestamp}")
        
        elif sub_choice == '2':
            strategy = input("输入策略名称: ").strip()
            signals = self.db_manager.get_signals(strategy=strategy)
            print(f"\n🎯 {strategy} 策略信号 ({len(signals)} 个):")
            for signal in signals:
                confidence_emoji = "🔥" if signal.confidence > 0.8 else "⭐"
                print(f"   {confidence_emoji} {signal.symbol} {signal.signal_type.upper()} "
                      f"置信度: {signal.confidence:.1%} 时间: {signal.timestamp}")
        
        elif sub_choice == '3':
            days = input("输入查询天数 (例如: 7): ").strip()
            try:
                days = int(days)
                since_date = datetime.now() - timedelta(days=days)
                trades = self.db_manager.get_trades(since=since_date)
                print(f"\n📅 最近 {days} 天交易记录 ({len(trades)} 笔):")
                for trade in trades:
                    pnl_status = "✅" if trade.pnl >= 0 else "❌"
                    print(f"   {pnl_status} {trade.symbol} {trade.side.upper()} "
                          f"${trade.amount:.2f} 盈亏: ${trade.pnl:.2f}")
            except ValueError:
                print("❌ 无效天数")
        
        elif sub_choice == '4':
            min_confidence = input("输入最小置信度 (0-1, 例如: 0.8): ").strip()
            try:
                min_confidence = float(min_confidence)
                signals = self.db_manager.get_signals(min_confidence=min_confidence)
                print(f"\n🔥 置信度 >= {min_confidence:.1%} 的信号 ({len(signals)} 个):")
                for signal in signals:
                    print(f"   🎯 {signal.symbol} {signal.signal_type.upper()} "
                          f"置信度: {signal.confidence:.1%} 策略: {signal.strategy_name}")
            except ValueError:
                print("❌ 无效置信度")
    
    def export_data(self):
        """导出数据"""
        print("\n📱 导出数据")
        print("-" * 30)
        print("1. 导出所有交易记录")
        print("2. 导出所有策略信号")
        print("3. 导出统计报告")
        
        sub_choice = input("\n选择导出类型 (1-3): ").strip()
        
        if sub_choice == '1':
            trades = self.db_manager.get_trades(limit=1000)
            trades_data = []
            for trade in trades:
                trades_data.append({
                    'symbol': trade.symbol,
                    'side': trade.side,
                    'amount': trade.amount,
                    'price': trade.price,
                    'strategy': trade.strategy,
                    'fee': trade.fee,
                    'pnl': trade.pnl,
                    'timestamp': trade.timestamp.isoformat()
                })
            
            filename = f"trades_export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(trades_data, f, indent=2, ensure_ascii=False)
            print(f"✅ 交易记录已导出到: {filename}")
        
        elif sub_choice == '2':
            signals = self.db_manager.get_signals(limit=1000)
            signals_data = []
            for signal in signals:
                signals_data.append({
                    'symbol': signal.symbol,
                    'strategy_name': signal.strategy_name,
                    'signal_type': signal.signal_type,
                    'confidence': signal.confidence,
                    'price': signal.price,
                    'reason': signal.reason,
                    'timestamp': signal.timestamp.isoformat()
                })
            
            filename = f"signals_export_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(signals_data, f, indent=2, ensure_ascii=False)
            print(f"✅ 策略信号已导出到: {filename}")
        
        elif sub_choice == '3':
            stats = self.db_manager.get_performance_stats()
            trades = self.db_manager.get_trades(limit=100)
            signals = self.db_manager.get_signals(limit=100)
            
            report = {
                'generated_at': datetime.now().isoformat(),
                'performance_stats': stats,
                'recent_trades_count': len(trades),
                'recent_signals_count': len(signals),
                'database_info': {
                    'file': 'trading_data.db',
                    'size_kb': os.path.getsize('trading_data.db') // 1024 if os.path.exists('trading_data.db') else 0
                }
            }
            
            filename = f"database_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(report, f, indent=2, ensure_ascii=False)
            print(f"✅ 统计报告已导出到: {filename}")


def main():
    """主函数"""
    print("🚀 启动数据库分析工具...")
    
    analyzer = DatabaseAnalyzer()
    
    # 检查数据库连接
    try:
        stats = analyzer.db_manager.get_performance_stats()
        print(f"✅ 数据库连接成功，包含 {stats['total_trades']} 笔交易记录")
    except Exception as e:
        print(f"❌ 数据库连接失败: {e}")
        return
    
    analyzer.run_interactive_query()


if __name__ == '__main__':
    main()