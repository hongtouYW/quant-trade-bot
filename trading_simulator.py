# -*- coding: utf-8 -*-
"""
量化交易模拟器 - 实时策略模拟交易系统
支持多币种、杠杆、做多做空、手续费计算
"""

import sqlite3
import ccxt
import pandas as pd
import numpy as np
import time
import json
from datetime import datetime, timedelta
from typing import Dict, List, Optional, Tuple
import threading
import logging

# 配置日志
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class TradingSimulator:
    """交易模拟器主类"""
    
    def __init__(self, initial_balance: float = 1000.0):
        self.initial_balance = initial_balance
        self.current_balance = initial_balance
        self.db_path = 'trading_simulator.db'
        self.exchanges = {}
        self.positions = {}  # 当前持仓
        self.is_running = False
        
        # 初始化数据库
        self.init_database()
        # 初始化交易所
        self.init_exchanges()
        
        logger.info(f"🏦 交易模拟器初始化完成，初始资金: ${self.initial_balance}U")
    
    def init_database(self):
        """初始化数据库"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 创建交易记录表
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS trades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            symbol TEXT NOT NULL,
            side TEXT NOT NULL,  -- 'buy' 或 'sell'
            direction TEXT NOT NULL,  -- 'long' 或 'short'
            type TEXT NOT NULL,  -- 'spot' 或 'futures'
            amount REAL NOT NULL,
            price REAL NOT NULL,
            leverage REAL DEFAULT 1.0,
            fee_rate REAL DEFAULT 0.001,
            fee_amount REAL DEFAULT 0.0,
            pnl_percent REAL DEFAULT 0.0,
            pnl_amount REAL DEFAULT 0.0,
            balance_before REAL NOT NULL,
            balance_after REAL NOT NULL,
            status TEXT DEFAULT 'open'  -- 'open', 'closed', 'partial'
        )
        ''')
        
        # 创建持仓表
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS positions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            symbol TEXT NOT NULL,
            direction TEXT NOT NULL,  -- 'long' 或 'short'
            type TEXT NOT NULL,  -- 'spot' 或 'futures'
            amount REAL NOT NULL,
            entry_price REAL NOT NULL,
            current_price REAL DEFAULT 0.0,
            leverage REAL DEFAULT 1.0,
            unrealized_pnl REAL DEFAULT 0.0,
            unrealized_pnl_percent REAL DEFAULT 0.0,
            open_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'open'
        )
        ''')
        
        # 创建策略信号表
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS strategy_signals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            symbol TEXT NOT NULL,
            signal TEXT NOT NULL,  -- 'buy', 'sell', 'hold'
            confidence REAL DEFAULT 0.0,
            reason TEXT,
            price REAL NOT NULL,
            executed BOOLEAN DEFAULT 0
        )
        ''')
        
        # 创建账户余额历史表
        cursor.execute('''
        CREATE TABLE IF NOT EXISTS balance_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            balance REAL NOT NULL,
            total_pnl REAL DEFAULT 0.0,
            total_pnl_percent REAL DEFAULT 0.0,
            open_positions INTEGER DEFAULT 0
        )
        ''')
        
        conn.commit()
        conn.close()
        logger.info("✅ 数据库初始化完成")
    
    def init_exchanges(self):
        """初始化交易所"""
        # Binance
        try:
            self.exchanges['binance'] = ccxt.binance({
                'apiKey': '',  # 模拟不需要真实API
                'secret': '',
                'sandbox': True,  # 使用沙箱环境
                'enableRateLimit': True,
            })
        except:
            pass
        
        # Kraken (用于获取价格数据)
        try:
            self.exchanges['kraken'] = ccxt.kraken({
                'enableRateLimit': True,
            })
        except:
            pass
            
        logger.info(f"📡 已初始化 {len(self.exchanges)} 个交易所连接")
    
    def get_current_price(self, symbol: str) -> float:
        """获取当前价格"""
        try:
            # 尝试从多个交易所获取价格
            for exchange_name, exchange in self.exchanges.items():
                try:
                    ticker = exchange.fetch_ticker(symbol)
                    return float(ticker['last'])
                except:
                    continue
            
            # 如果所有交易所都失败，使用备用价格数据
            return self._get_fallback_price(symbol)
        except Exception as e:
            logger.error(f"❌ 获取价格失败 {symbol}: {e}")
            return 0.0
    
    def _get_fallback_price(self, symbol: str) -> float:
        """备用价格获取方法"""
        price_map = {
            'BTC/USDT': 45000.0,
            'ETH/USDT': 2500.0,
            'XMR/USDT': 150.0,
            'BNB/USDT': 300.0,
            'SOL/USDT': 100.0,
            'DOGE/USDT': 0.1,
        }
        base_price = price_map.get(symbol, 100.0)
        # 添加随机波动 ±2%
        variation = np.random.uniform(-0.02, 0.02)
        return base_price * (1 + variation)
    
    def calculate_position_size(self, balance: float, risk_percent: float = 0.02) -> float:
        """计算仓位大小"""
        return balance * risk_percent
    
    def calculate_fees(self, amount: float, price: float, fee_rate: float = 0.001) -> float:
        """计算手续费"""
        return amount * price * fee_rate
    
    def open_position(self, symbol: str, side: str, direction: str, 
                     position_type: str = 'spot', leverage: float = 1.0, 
                     risk_percent: float = 0.02) -> bool:
        """开仓"""
        try:
            current_price = self.get_current_price(symbol)
            if current_price <= 0:
                logger.error(f"❌ 无法获取价格: {symbol}")
                return False
            
            # 计算仓位大小
            position_value = self.calculate_position_size(self.current_balance, risk_percent)
            if position_type == 'futures':
                position_value *= leverage
            
            amount = position_value / current_price
            fee_amount = self.calculate_fees(amount, current_price)
            
            # 检查余额
            required_balance = position_value + fee_amount
            if position_type == 'futures':
                required_balance = position_value / leverage + fee_amount
                
            if required_balance > self.current_balance:
                logger.warning(f"⚠️ 余额不足: 需要 ${required_balance:.2f}, 当前 ${self.current_balance:.2f}")
                return False
            
            # 记录交易
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            balance_before = self.current_balance
            balance_after = self.current_balance - required_balance
            
            cursor.execute('''
            INSERT INTO trades (symbol, side, direction, type, amount, price, leverage, 
                              fee_amount, balance_before, balance_after)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (symbol, side, direction, position_type, amount, current_price, 
                  leverage, fee_amount, balance_before, balance_after))
            
            trade_id = cursor.lastrowid
            
            # 记录持仓
            cursor.execute('''
            INSERT INTO positions (symbol, direction, type, amount, entry_price, 
                                 current_price, leverage)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', (symbol, direction, position_type, amount, current_price, 
                  current_price, leverage))
            
            position_id = cursor.lastrowid
            
            conn.commit()
            conn.close()
            
            # 更新余额
            self.current_balance = balance_after
            
            # 更新本地持仓记录
            self.positions[position_id] = {
                'id': position_id,
                'symbol': symbol,
                'direction': direction,
                'type': position_type,
                'amount': amount,
                'entry_price': current_price,
                'leverage': leverage,
                'open_time': datetime.now()
            }
            
            logger.info(f"✅ 开仓成功: {symbol} {direction} {amount:.6f} @ ${current_price:.2f}")
            logger.info(f"💰 余额: ${balance_before:.2f} -> ${balance_after:.2f}")
            
            return True
            
        except Exception as e:
            logger.error(f"❌ 开仓失败: {e}")
            return False
    
    def close_position(self, position_id: int, close_reason: str = "manual") -> bool:
        """平仓"""
        try:
            if position_id not in self.positions:
                logger.warning(f"⚠️ 持仓不存在: {position_id}")
                return False
            
            position = self.positions[position_id]
            current_price = self.get_current_price(position['symbol'])
            
            if current_price <= 0:
                logger.error(f"❌ 无法获取价格: {position['symbol']}")
                return False
            
            # 计算盈亏
            entry_price = position['entry_price']
            amount = position['amount']
            leverage = position['leverage']
            direction = position['direction']
            
            if direction == 'long':
                pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
            else:  # short
                pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
            
            position_value = amount * current_price
            pnl_amount = (pnl_percent / 100) * (amount * entry_price)
            fee_amount = self.calculate_fees(amount, current_price)
            
            # 更新余额
            balance_before = self.current_balance
            balance_after = self.current_balance + position_value - fee_amount
            
            # 更新数据库
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            # 记录平仓交易
            cursor.execute('''
            INSERT INTO trades (symbol, side, direction, type, amount, price, leverage, 
                              fee_amount, pnl_percent, pnl_amount, balance_before, balance_after, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (position['symbol'], 'sell', position['direction'], position['type'], 
                  amount, current_price, leverage, fee_amount, pnl_percent, pnl_amount,
                  balance_before, balance_after, 'closed'))
            
            # 更新持仓状态
            cursor.execute('''
            UPDATE positions SET status = 'closed', current_price = ?, 
                               unrealized_pnl = ?, unrealized_pnl_percent = ?
            WHERE id = ?
            ''', (current_price, pnl_amount, pnl_percent, position_id))
            
            conn.commit()
            conn.close()
            
            # 更新余额
            self.current_balance = balance_after
            
            # 移除本地持仓
            del self.positions[position_id]
            
            color = "🟢" if pnl_amount >= 0 else "🔴"
            logger.info(f"✅ 平仓成功: {position['symbol']} {direction}")
            logger.info(f"{color} 盈亏: {pnl_percent:+.2f}% (${pnl_amount:+.2f}U)")
            logger.info(f"💰 余额: ${balance_before:.2f} -> ${balance_after:.2f}")
            
            return True
            
        except Exception as e:
            logger.error(f"❌ 平仓失败: {e}")
            return False
    
    def update_positions(self):
        """更新所有持仓的未实现盈亏"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            for position_id, position in self.positions.items():
                current_price = self.get_current_price(position['symbol'])
                if current_price <= 0:
                    continue
                
                entry_price = position['entry_price']
                leverage = position['leverage']
                direction = position['direction']
                amount = position['amount']
                
                if direction == 'long':
                    unrealized_pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                else:  # short
                    unrealized_pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
                
                unrealized_pnl = (unrealized_pnl_percent / 100) * (amount * entry_price)
                
                # 更新数据库
                cursor.execute('''
                UPDATE positions SET current_price = ?, unrealized_pnl = ?, 
                                   unrealized_pnl_percent = ?
                WHERE id = ?
                ''', (current_price, unrealized_pnl, unrealized_pnl_percent, position_id))
            
            conn.commit()
            conn.close()
            
        except Exception as e:
            logger.error(f"❌ 更新持仓失败: {e}")
    
    def get_account_summary(self) -> Dict:
        """获取账户摘要"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            # 计算总未实现盈亏
            total_unrealized_pnl = 0
            cursor.execute('SELECT unrealized_pnl FROM positions WHERE status = "open"')
            for row in cursor.fetchall():
                total_unrealized_pnl += row[0] or 0
            
            # 计算总已实现盈亏
            cursor.execute('SELECT SUM(pnl_amount) FROM trades WHERE status = "closed"')
            total_realized_pnl = cursor.fetchone()[0] or 0
            
            # 计算总权益
            total_equity = self.current_balance + total_unrealized_pnl
            total_pnl = total_realized_pnl + total_unrealized_pnl
            total_pnl_percent = (total_pnl / self.initial_balance) * 100
            
            # 获取持仓数量
            cursor.execute('SELECT COUNT(*) FROM positions WHERE status = "open"')
            open_positions = cursor.fetchone()[0]
            
            conn.close()
            
            return {
                'current_balance': self.current_balance,
                'total_equity': total_equity,
                'total_pnl': total_pnl,
                'total_pnl_percent': total_pnl_percent,
                'realized_pnl': total_realized_pnl,
                'unrealized_pnl': total_unrealized_pnl,
                'open_positions': open_positions,
                'initial_balance': self.initial_balance
            }
            
        except Exception as e:
            logger.error(f"❌ 获取账户摘要失败: {e}")
            return {}
    
    def record_balance_snapshot(self):
        """记录余额快照"""
        try:
            summary = self.get_account_summary()
            
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
            INSERT INTO balance_history (balance, total_pnl, total_pnl_percent, open_positions)
            VALUES (?, ?, ?, ?)
            ''', (summary['current_balance'], summary['total_pnl'], 
                  summary['total_pnl_percent'], summary['open_positions']))
            
            conn.commit()
            conn.close()
            
        except Exception as e:
            logger.error(f"❌ 记录余额快照失败: {e}")

if __name__ == "__main__":
    # 测试交易模拟器
    simulator = TradingSimulator(initial_balance=1000.0)
    
    print("🎯 交易模拟器测试")
    print("=" * 50)
    
    # 开仓测试
    success = simulator.open_position('BTC/USDT', 'buy', 'long', 'spot', leverage=1.0, risk_percent=0.1)
    if success:
        print("✅ 开仓成功")
    
    # 更新持仓
    simulator.update_positions()
    
    # 获取账户摘要
    summary = simulator.get_account_summary()
    print(f"\n📊 账户摘要:")
    print(f"💰 当前余额: ${summary['current_balance']:.2f}")
    print(f"💎 总权益: ${summary['total_equity']:.2f}")
    print(f"📈 总盈亏: {summary['total_pnl_percent']:+.2f}% (${summary['total_pnl']:+.2f})")
    print(f"🔢 持仓数量: {summary['open_positions']}")