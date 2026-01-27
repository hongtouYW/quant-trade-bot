#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
数据迁移工具
将现有JSON数据迁移到SQLite数据库
"""

import os
import json
import glob
from datetime import datetime
from database_framework import TradingDataManager

class DataMigrationTool:
    """数据迁移工具"""
    
    def __init__(self):
        self.db_manager = TradingDataManager()
        self.migrated_files = []
        self.errors = []
    
    def migrate_all_data(self):
        """迁移所有JSON数据"""
        print("🔄 开始数据迁移...")
        print("=" * 40)
        
        # 查找所有JSON文件
        json_files = glob.glob("*.json")
        print(f"📁 找到 {len(json_files)} 个JSON文件")
        
        for json_file in json_files:
            self.migrate_single_file(json_file)
        
        self.print_migration_summary()
    
    def migrate_single_file(self, file_path):
        """迁移单个JSON文件"""
        try:
            print(f"📂 处理文件: {file_path}")
            
            with open(file_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
            
            # 根据文件名判断数据类型
            if self._is_trading_history(file_path):
                self._migrate_trading_history(data, file_path)
            elif self._is_backtest_data(file_path):
                self._migrate_backtest_data(data, file_path)
            elif self._is_strategy_analysis(file_path):
                self._migrate_strategy_signals(data, file_path)
            elif self._is_market_scan(file_path):
                self._migrate_market_scan(data, file_path)
            else:
                print(f"   ⏸️ 跳过未知格式文件")
                return
            
            self.migrated_files.append(file_path)
            print(f"   ✅ 迁移完成")
            
        except Exception as e:
            error_msg = f"迁移文件 {file_path} 失败: {e}"
            self.errors.append(error_msg)
            print(f"   ❌ {error_msg}")
    
    def _is_trading_history(self, file_path):
        """判断是否为交易历史文件"""
        keywords = ['trading_history', 'trade_history', 'trades']
        return any(keyword in file_path.lower() for keyword in keywords)
    
    def _is_backtest_data(self, file_path):
        """判断是否为回测数据文件"""
        keywords = ['backtest', 'strategy_backtest']
        return any(keyword in file_path.lower() for keyword in keywords)
    
    def _is_strategy_analysis(self, file_path):
        """判断是否为策略分析文件"""
        keywords = ['strategy_analysis', 'multi_timeframe']
        return any(keyword in file_path.lower() for keyword in keywords)
    
    def _is_market_scan(self, file_path):
        """判断是否为市场扫描文件"""
        keywords = ['potential_coins', 'big_money', 'scan']
        return any(keyword in file_path.lower() for keyword in keywords)
    
    def _migrate_trading_history(self, data, file_path):
        """迁移交易历史数据"""
        if isinstance(data, list):
            for trade_data in data:
                self._add_trade_record(trade_data, 'history')
        elif isinstance(data, dict):
            if 'trades' in data:
                for trade_data in data['trades']:
                    self._add_trade_record(trade_data, 'history')
            elif 'trade_history' in data:
                for trade_data in data['trade_history']:
                    self._add_trade_record(trade_data, 'history')
    
    def _migrate_backtest_data(self, data, file_path):
        """迁移回测数据"""
        if 'trades' in data:
            for trade_data in data['trades']:
                self._add_trade_record(trade_data, 'backtest')
        
        # 迁移回测统计信息
        if 'total_return' in data:
            self.db_manager.add_signal(
                symbol='BACKTEST',
                strategy_name='backtest_summary', 
                signal_type='info',
                confidence=1.0,
                price=0,
                reason=f"总收益: {data['total_return']}, 胜率: {data.get('win_rate', 'N/A')}"
            )
    
    def _migrate_strategy_signals(self, data, file_path):
        """迁移策略信号数据"""
        if isinstance(data, list):
            for signal_data in data:
                self._add_signal_record(signal_data)
        elif isinstance(data, dict):
            # 处理多时间框架分析结果
            if 'results' in data:
                for result in data['results']:
                    self._add_signal_from_analysis(result)
    
    def _migrate_market_scan(self, data, file_path):
        """迁移市场扫描数据"""
        if isinstance(data, list):
            for coin_data in data:
                if 'symbol' in coin_data:
                    signal_type = 'buy' if coin_data.get('score', 0) > 0.7 else 'neutral'
                    self.db_manager.add_signal(
                        symbol=coin_data['symbol'],
                        strategy_name='market_scanner',
                        signal_type=signal_type,
                        confidence=coin_data.get('score', 0.5),
                        price=coin_data.get('price', 0),
                        reason=f"扫描评分: {coin_data.get('score', 'N/A')}"
                    )
    
    def _add_trade_record(self, trade_data, source_type):
        """添加交易记录"""
        try:
            symbol = trade_data.get('symbol', 'UNKNOWN')
            side = trade_data.get('side', trade_data.get('action', 'buy'))
            amount = float(trade_data.get('amount', 0))
            price = float(trade_data.get('price', 0))
            pnl = float(trade_data.get('pnl', 0))
            fee = float(trade_data.get('fee', 0))
            
            self.db_manager.add_trade(
                symbol=symbol,
                side=side,
                amount=amount,
                price=price,
                strategy=source_type,
                fee=fee,
                pnl=pnl
            )
        except Exception as e:
            print(f"     ⚠️ 跳过无效交易记录: {e}")
    
    def _add_signal_record(self, signal_data):
        """添加信号记录"""
        try:
            symbol = signal_data.get('symbol', 'UNKNOWN')
            strategy = signal_data.get('strategy', 'unknown')
            signal_type = signal_data.get('signal', signal_data.get('type', 'neutral'))
            confidence = float(signal_data.get('confidence', 0.5))
            price = float(signal_data.get('price', 0))
            reason = signal_data.get('reason', '')
            
            self.db_manager.add_signal(
                symbol=symbol,
                strategy_name=strategy,
                signal_type=signal_type,
                confidence=confidence,
                price=price,
                reason=reason
            )
        except Exception as e:
            print(f"     ⚠️ 跳过无效信号记录: {e}")
    
    def _add_signal_from_analysis(self, analysis_data):
        """从分析结果添加信号"""
        try:
            symbol = analysis_data.get('symbol', 'UNKNOWN')
            entry_signal = analysis_data.get('entry', {})
            
            if entry_signal and entry_signal.get('signal'):
                self.db_manager.add_signal(
                    symbol=symbol,
                    strategy_name='multi_timeframe',
                    signal_type=entry_signal.get('signal', 'neutral'),
                    confidence=float(entry_signal.get('confidence', 0.5)),
                    price=float(entry_signal.get('price', 0)),
                    reason=f"多时间框架分析: {analysis_data.get('trend', {}).get('direction', 'unknown')}"
                )
        except Exception as e:
            print(f"     ⚠️ 跳过无效分析记录: {e}")
    
    def print_migration_summary(self):
        """打印迁移总结"""
        print("\n" + "=" * 40)
        print("📊 数据迁移总结")
        print("=" * 40)
        print(f"✅ 成功迁移文件: {len(self.migrated_files)}")
        print(f"❌ 迁移失败: {len(self.errors)}")
        
        if self.migrated_files:
            print("\n📁 已迁移文件:")
            for file_path in self.migrated_files:
                print(f"   - {file_path}")
        
        if self.errors:
            print("\n💥 错误信息:")
            for error in self.errors:
                print(f"   - {error}")
        
        # 显示数据库统计
        stats = self.db_manager.get_performance_stats()
        print(f"\n📈 数据库统计:")
        print(f"   - 总交易记录: {stats['total_trades']}")
        print(f"   - 胜率: {stats['win_rate']:.1f}%")
        print(f"   - 总盈亏: {stats['total_pnl']:.2f}")
        
        # 统计信号数量
        signals = self.db_manager.get_signals(limit=1000)
        print(f"   - 策略信号数: {len(signals)}")


def main():
    """主函数"""
    migration_tool = DataMigrationTool()
    migration_tool.migrate_all_data()
    
    print(f"\n🎯 数据迁移完成!")
    print(f"💾 SQLite数据库文件: trading_data.db")
    print(f"📊 可以使用 database_framework.py 查询数据")


if __name__ == '__main__':
    main()