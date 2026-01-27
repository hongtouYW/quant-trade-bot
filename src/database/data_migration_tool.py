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
                    self._add_trade_record(trade_data, 'history')\n    \n    def _migrate_backtest_data(self, data, file_path):\n        \"\"\"迁移回测数据\"\"\"\n        if 'trades' in data:\n            for trade_data in data['trades']:\n                self._add_trade_record(trade_data, 'backtest')\n        \n        # 迁移回测统计信息\n        if 'total_return' in data:\n            self.db_manager.add_signal(\n                symbol='BACKTEST',\n                strategy_name='backtest_summary', \n                signal_type='info',\n                confidence=1.0,\n                price=0,\n                reason=f\"总收益: {data['total_return']}, 胜率: {data.get('win_rate', 'N/A')}\"\n            )\n    \n    def _migrate_strategy_signals(self, data, file_path):\n        \"\"\"迁移策略信号数据\"\"\"\n        if isinstance(data, list):\n            for signal_data in data:\n                self._add_signal_record(signal_data)\n        elif isinstance(data, dict):\n            # 处理多时间框架分析结果\n            if 'results' in data:\n                for result in data['results']:\n                    self._add_signal_from_analysis(result)\n    \n    def _migrate_market_scan(self, data, file_path):\n        \"\"\"迁移市场扫描数据\"\"\"\n        if isinstance(data, list):\n            for coin_data in data:\n                if 'symbol' in coin_data:\n                    signal_type = 'buy' if coin_data.get('score', 0) > 0.7 else 'neutral'\n                    self.db_manager.add_signal(\n                        symbol=coin_data['symbol'],\n                        strategy_name='market_scanner',\n                        signal_type=signal_type,\n                        confidence=coin_data.get('score', 0.5),\n                        price=coin_data.get('price', 0),\n                        reason=f\"扫描评分: {coin_data.get('score', 'N/A')}\"\n                    )\n    \n    def _add_trade_record(self, trade_data, source_type):\n        \"\"\"添加交易记录\"\"\"\n        try:\n            symbol = trade_data.get('symbol', 'UNKNOWN')\n            side = trade_data.get('side', trade_data.get('action', 'buy'))\n            amount = float(trade_data.get('amount', 0))\n            price = float(trade_data.get('price', 0))\n            pnl = float(trade_data.get('pnl', 0))\n            fee = float(trade_data.get('fee', 0))\n            \n            self.db_manager.add_trade(\n                symbol=symbol,\n                side=side,\n                amount=amount,\n                price=price,\n                strategy=source_type,\n                fee=fee,\n                pnl=pnl\n            )\n        except Exception as e:\n            print(f\"     ⚠️ 跳过无效交易记录: {e}\")\n    \n    def _add_signal_record(self, signal_data):\n        \"\"\"添加信号记录\"\"\"\n        try:\n            symbol = signal_data.get('symbol', 'UNKNOWN')\n            strategy = signal_data.get('strategy', 'unknown')\n            signal_type = signal_data.get('signal', signal_data.get('type', 'neutral'))\n            confidence = float(signal_data.get('confidence', 0.5))\n            price = float(signal_data.get('price', 0))\n            reason = signal_data.get('reason', '')\n            \n            self.db_manager.add_signal(\n                symbol=symbol,\n                strategy_name=strategy,\n                signal_type=signal_type,\n                confidence=confidence,\n                price=price,\n                reason=reason\n            )\n        except Exception as e:\n            print(f\"     ⚠️ 跳过无效信号记录: {e}\")\n    \n    def _add_signal_from_analysis(self, analysis_data):\n        \"\"\"从分析结果添加信号\"\"\"\n        try:\n            symbol = analysis_data.get('symbol', 'UNKNOWN')\n            entry_signal = analysis_data.get('entry', {})\n            \n            if entry_signal and entry_signal.get('signal'):\n                self.db_manager.add_signal(\n                    symbol=symbol,\n                    strategy_name='multi_timeframe',\n                    signal_type=entry_signal.get('signal', 'neutral'),\n                    confidence=float(entry_signal.get('confidence', 0.5)),\n                    price=float(entry_signal.get('price', 0)),\n                    reason=f\"多时间框架分析: {analysis_data.get('trend', {}).get('direction', 'unknown')}\"\n                )\n        except Exception as e:\n            print(f\"     ⚠️ 跳过无效分析记录: {e}\")\n    \n    def print_migration_summary(self):\n        \"\"\"打印迁移总结\"\"\"\n        print(\"\\n\" + \"=\" * 40)\n        print(\"📊 数据迁移总结\")\n        print(\"=\" * 40)\n        print(f\"✅ 成功迁移文件: {len(self.migrated_files)}\")\n        print(f\"❌ 迁移失败: {len(self.errors)}\")\n        \n        if self.migrated_files:\n            print(\"\\n📁 已迁移文件:\")\n            for file_path in self.migrated_files:\n                print(f\"   - {file_path}\")\n        \n        if self.errors:\n            print(\"\\n💥 错误信息:\")\n            for error in self.errors:\n                print(f\"   - {error}\")\n        \n        # 显示数据库统计\n        stats = self.db_manager.get_performance_stats()\n        print(f\"\\n📈 数据库统计:\")\n        print(f\"   - 总交易记录: {stats['total_trades']}\")\n        print(f\"   - 胜率: {stats['win_rate']:.1f}%\")\n        print(f\"   - 总盈亏: {stats['total_pnl']:.2f}\")\n        \n        # 统计信号数量\n        signals = self.db_manager.get_signals(limit=1000)\n        print(f\"   - 策略信号数: {len(signals)}\")\n\n\ndef main():\n    \"\"\"主函数\"\"\"\n    migration_tool = DataMigrationTool()\n    migration_tool.migrate_all_data()\n    \n    print(f\"\\n🎯 数据迁移完成!\")\n    print(f\"💾 SQLite数据库文件: trading_data.db\")\n    print(f\"📊 可以使用 database_framework.py 查询数据\")\n\n\nif __name__ == '__main__':\n    main()