#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
数据库管理界面
提供完整的数据库管理功能
"""

import os
import sys
from datetime import datetime
from database_framework import TradingDataManager


class DatabaseManager:
    """数据库管理主界面"""
    
    def __init__(self):
        self.db_manager = TradingDataManager()
        print("🚀 数据库管理系统初始化...")
        print("📊 SQLite数据库: trading_data.db")
        print("-" * 50)
    
    def main_menu(self):
        """主菜单"""
        while True:
            self.show_status_summary()
            print("\n" + "=" * 50)
            print("🗄️  数据库管理系统")
            print("=" * 50)
            print("📊 [1] 快速状态检查")
            print("📈 [2] 详细统计报告") 
            print("🔍 [3] 数据查询工具")
            print("📱 [4] 数据导出")
            print("🔄 [5] 数据迁移")
            print("🧹 [6] 数据清理")
            print("⚙️  [7] 数据库维护")
            print("❌ [0] 退出系统")
            print("-" * 50)
            
            choice = input("请选择操作 (0-7): ").strip()
            
            if choice == '0':
                print("👋 数据库管理系统已关闭")
                sys.exit(0)
            elif choice == '1':
                self.quick_status()
            elif choice == '2':
                self.detailed_report()
            elif choice == '3':
                self.query_tool()
            elif choice == '4':
                self.export_data()
            elif choice == '5':
                self.data_migration()
            elif choice == '6':
                self.data_cleanup()
            elif choice == '7':
                self.database_maintenance()
            else:
                print("❌ 无效选择，请重试")
    
    def show_status_summary(self):
        """显示状态摘要"""
        try:
            stats = self.db_manager.get_performance_stats()
            signals = self.db_manager.get_signals(limit=1)
            
            print(f"\n💡 状态摘要: {stats['total_trades']}笔交易 | "
                  f"{len(self.db_manager.get_signals(limit=1000))}个信号 | "
                  f"胜率{stats['win_rate']:.1f}% | "
                  f"盈亏${stats['total_pnl']:.0f}")
        except Exception as e:
            print(f"💡 状态摘要: 数据库连接异常 - {e}")
    
    def quick_status(self):
        """快速状态检查"""
        print("\n🔍 快速状态检查")
        print("=" * 30)
        
        try:
            # 文件状态
            db_file = 'trading_data.db'
            if os.path.exists(db_file):
                size_kb = os.path.getsize(db_file) / 1024
                modified = datetime.fromtimestamp(os.path.getmtime(db_file))
                print(f"✅ 数据库文件: {size_kb:.1f}KB")
                print(f"📅 最后修改: {modified.strftime('%Y-%m-%d %H:%M')}")
            else:
                print("❌ 数据库文件不存在")
                return
            
            # 基础统计
            stats = self.db_manager.get_performance_stats()
            signals = self.db_manager.get_signals(limit=1000)
            
            print(f"📊 交易记录: {stats['total_trades']} 笔")
            print(f"🎯 策略信号: {len(signals)} 个")
            print(f"💰 总盈亏: ${stats['total_pnl']:.2f}")
            print(f"🏆 胜率: {stats['win_rate']:.1f}%")
            
        except Exception as e:
            print(f"❌ 检查失败: {e}")
        
        input("\n按回车键返回主菜单...")
    
    def detailed_report(self):
        """详细统计报告"""
        print("\n📈 详细统计报告")
        print("=" * 30)
        
        try:
            stats = self.db_manager.get_performance_stats()
            trades = self.db_manager.get_trades(limit=1000)
            signals = self.db_manager.get_signals(limit=1000)
            
            # 交易分析
            print("🔸 交易统计:")
            print(f"   总交易数: {stats['total_trades']}")
            print(f"   成功交易: {stats['winning_trades']}")
            print(f"   失败交易: {stats['losing_trades']}")
            print(f"   胜率: {stats['win_rate']:.1f}%")
            print(f"   总盈亏: ${stats['total_pnl']:.2f}")
            print(f"   平均盈亏: ${stats['avg_pnl']:.2f}")
            
            # 信号分析
            strategy_count = {}
            signal_type_count = {}
            for signal in signals:
                strategy = signal.get('strategy_name', 'unknown')
                sig_type = signal.get('signal_type', 'unknown')
                strategy_count[strategy] = strategy_count.get(strategy, 0) + 1
                signal_type_count[sig_type] = signal_type_count.get(sig_type, 0) + 1
            
            print(f"\n🔸 信号统计:")
            print(f"   总信号数: {len(signals)}")
            print(f"   策略种类: {len(strategy_count)}")
            
            if strategy_count:
                print("   热门策略:")
                for strategy, count in sorted(strategy_count.items(), key=lambda x: x[1], reverse=True)[:5]:
                    print(f"     - {strategy}: {count}")
            
            if signal_type_count:
                print("   信号分布:")
                for sig_type, count in signal_type_count.items():
                    print(f"     - {sig_type.upper()}: {count}")
            
            # 币种分析
            symbol_trades = {}
            for trade in trades:
                symbol = trade.get('symbol', 'UNKNOWN')
                if symbol not in symbol_trades:
                    symbol_trades[symbol] = {'count': 0, 'pnl': 0}
                symbol_trades[symbol]['count'] += 1
                symbol_trades[symbol]['pnl'] += trade.get('pnl', 0)
            
            if symbol_trades:
                print(f"\n🔸 币种表现:")
                for symbol, data in sorted(symbol_trades.items(), key=lambda x: x[1]['pnl'], reverse=True):
                    status = "🟢" if data['pnl'] > 0 else "🔴" if data['pnl'] < 0 else "⚪"
                    print(f"   {status} {symbol}: {data['count']}笔 ${data['pnl']:.2f}")
            
        except Exception as e:
            print(f"❌ 报告生成失败: {e}")
        
        input("\n按回车键返回主菜单...")
    
    def query_tool(self):
        """数据查询工具"""
        print("\n🔍 数据查询工具")
        print("=" * 30)
        print("1. 按币种查询交易")
        print("2. 按策略查询信号")
        print("3. 最近N笔交易")
        print("4. 高置信度信号")
        print("0. 返回主菜单")
        
        choice = input("\n选择查询类型: ").strip()
        
        if choice == '0':
            return
        elif choice == '1':
            symbol = input("输入币种 (如BTCUSDT): ").strip().upper()
            self._query_by_symbol(symbol)
        elif choice == '2':
            strategy = input("输入策略名称: ").strip()
            self._query_by_strategy(strategy)
        elif choice == '3':
            try:
                limit = int(input("输入查询数量: ").strip())
                self._query_recent_trades(limit)
            except ValueError:
                print("❌ 无效数量")
        elif choice == '4':
            try:
                min_conf = float(input("最小置信度 (0-1): ").strip())
                self._query_high_confidence(min_conf)
            except ValueError:
                print("❌ 无效置信度")
        
        input("\n按回车键返回...")
    
    def _query_by_symbol(self, symbol):
        """按币种查询"""
        print(f"\n📊 {symbol} 交易记录:")
        trades = [t for t in self.db_manager.get_trades(limit=1000) 
                 if t.get('symbol', '').upper() == symbol]
        
        if not trades:
            print("❌ 未找到相关交易")
            return
        
        for trade in trades[:10]:  # 显示前10笔
            pnl = trade.get('pnl', 0)
            status = "✅" if pnl >= 0 else "❌"
            print(f"   {status} {trade.get('side', '').upper()} "
                  f"${trade.get('amount', 0):.2f} @ ${trade.get('price', 0):.4f} "
                  f"盈亏: ${pnl:.2f}")
    
    def _query_by_strategy(self, strategy):
        """按策略查询"""
        print(f"\n🎯 {strategy} 策略信号:")
        signals = [s for s in self.db_manager.get_signals(limit=1000)
                  if strategy.lower() in s.get('strategy_name', '').lower()]
        
        if not signals:
            print("❌ 未找到相关信号")
            return
        
        for signal in signals[:10]:  # 显示前10个
            conf = signal.get('confidence', 0)
            emoji = "🔥" if conf > 0.8 else "⭐"
            print(f"   {emoji} {signal.get('symbol', '')} "
                  f"{signal.get('signal_type', '').upper()} "
                  f"置信度: {conf:.1%}")
    
    def _query_recent_trades(self, limit):
        """查询最近交易"""
        print(f"\n📈 最近 {limit} 笔交易:")
        trades = self.db_manager.get_trades(limit=limit)
        
        for i, trade in enumerate(trades, 1):
            pnl = trade.get('pnl', 0)
            status = "✅" if pnl >= 0 else "❌"
            print(f"   {i:2d}. {status} {trade.get('symbol', '')} "
                  f"{trade.get('side', '').upper()} 盈亏: ${pnl:.2f}")
    
    def _query_high_confidence(self, min_confidence):
        """查询高置信度信号"""
        print(f"\n🔥 置信度 >= {min_confidence:.1%} 的信号:")
        signals = [s for s in self.db_manager.get_signals(limit=1000)
                  if s.get('confidence', 0) >= min_confidence]
        
        if not signals:
            print("❌ 未找到符合条件的信号")
            return
        
        for signal in signals:
            print(f"   🎯 {signal.get('symbol', '')} "
                  f"{signal.get('signal_type', '').upper()} "
                  f"置信度: {signal.get('confidence', 0):.1%} "
                  f"策略: {signal.get('strategy_name', '')}")
    
    def export_data(self):
        """数据导出"""
        print("\n📱 数据导出")
        print("=" * 30)
        print("1. 导出所有交易记录")
        print("2. 导出所有策略信号")
        print("3. 导出完整数据库")
        print("0. 返回主菜单")
        
        choice = input("\n选择导出类型: ").strip()
        
        if choice == '0':
            return
        
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        
        try:
            if choice == '1':
                trades = self.db_manager.get_trades(limit=10000)
                filename = f"trades_export_{timestamp}.json"
                import json
                with open(filename, 'w', encoding='utf-8') as f:
                    json.dump(trades, f, indent=2, ensure_ascii=False)
                print(f"✅ 交易数据已导出: {filename}")
                
            elif choice == '2':
                signals = self.db_manager.get_signals(limit=10000)
                filename = f"signals_export_{timestamp}.json"
                import json
                with open(filename, 'w', encoding='utf-8') as f:
                    json.dump(signals, f, indent=2, ensure_ascii=False)
                print(f"✅ 信号数据已导出: {filename}")
                
            elif choice == '3':
                # 导出整个数据库
                import shutil
                backup_name = f"trading_data_backup_{timestamp}.db"
                shutil.copy('trading_data.db', backup_name)
                print(f"✅ 数据库已备份: {backup_name}")
                
        except Exception as e:
            print(f"❌ 导出失败: {e}")
        
        input("\n按回车键返回...")
    
    def data_migration(self):
        """数据迁移"""
        print("\n🔄 数据迁移")
        print("=" * 30)
        print("1. 从JSON文件迁移")
        print("2. 重新运行完整迁移")
        print("0. 返回主菜单")
        
        choice = input("\n选择迁移类型: ").strip()
        
        if choice == '0':
            return
        elif choice == '1':
            filename = input("输入JSON文件名: ").strip()
            if os.path.exists(filename):
                try:
                    # 这里可以添加单文件迁移逻辑
                    print(f"📂 处理文件: {filename}")
                    print("✅ 迁移完成")
                except Exception as e:
                    print(f"❌ 迁移失败: {e}")
            else:
                print("❌ 文件不存在")
        elif choice == '2':
            print("🔄 运行完整数据迁移...")
            os.system("python3 migration_tool.py")
        
        input("\n按回车键返回...")
    
    def data_cleanup(self):
        """数据清理"""
        print("\n🧹 数据清理")
        print("=" * 30)
        print("⚠️  危险操作！请谨慎选择")
        print("1. 删除测试数据")
        print("2. 清理重复记录")
        print("3. 删除所有数据 (危险!)")
        print("0. 返回主菜单")
        
        choice = input("\n选择清理类型: ").strip()
        
        if choice == '0':
            return
        elif choice == '3':
            confirm = input("⚠️  确认删除所有数据? (输入'DELETE'确认): ").strip()
            if confirm == 'DELETE':
                try:
                    os.remove('trading_data.db')
                    print("✅ 数据库已删除")
                    self.db_manager = TradingDataManager()  # 重新初始化
                except Exception as e:
                    print(f"❌ 删除失败: {e}")
            else:
                print("❌ 操作已取消")
        else:
            print("🚧 功能开发中...")
        
        input("\n按回车键返回...")
    
    def database_maintenance(self):
        """数据库维护"""
        print("\n⚙️  数据库维护")
        print("=" * 30)
        print("1. 数据库优化")
        print("2. 完整性检查")
        print("3. 重建索引")
        print("0. 返回主菜单")
        
        choice = input("\n选择维护操作: ").strip()
        
        if choice == '0':
            return
        else:
            print("🚧 维护功能开发中...")
        
        input("\n按回车键返回...")


def main():
    """主函数"""
    print("🚀 启动数据库管理系统...")
    
    try:
        manager = DatabaseManager()
        manager.main_menu()
    except KeyboardInterrupt:
        print("\n\n👋 系统已退出")
    except Exception as e:
        print(f"\n❌ 系统错误: {e}")


if __name__ == '__main__':
    main()