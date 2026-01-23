# -*- coding: utf-8 -*-
"""
实时量化交易模拟系统
整合价格监控、策略分析、模拟交易
"""

import time
import ccxt
import pandas as pd
import numpy as np
import sqlite3
import threading
import logging
from datetime import datetime, timedelta
from typing import Dict, List
import json

from trading_simulator import TradingSimulator
from strategy_engine import StrategyEngine

logger = logging.getLogger(__name__)

class RealTimeTrader:
    """实时交易模拟器"""
    
    def __init__(self, initial_balance: float = 1000.0):
        self.simulator = TradingSimulator(initial_balance)
        self.strategy_engine = StrategyEngine()
        self.is_running = False
        self.trading_pairs = [
            'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'DOGE/USDT'
        ]
        self.price_data = {}  # 存储价格历史数据
        self.last_signals = {}  # 存储最新信号
        self.monitoring_interval = 60  # 秒
        
        # 交易参数
        self.risk_per_trade = 0.02  # 每笔交易风险2%
        self.max_positions = 5  # 最大持仓数
        self.leverage_settings = {
            'BTC/USDT': 2.0,
            'ETH/USDT': 3.0,
            'BNB/USDT': 2.0,
            'SOL/USDT': 5.0,
            'DOGE/USDT': 10.0
        }
        
        logger.info(f"🚀 实时交易模拟器初始化完成")
        logger.info(f"💰 初始资金: ${initial_balance}U")
        logger.info(f"💱 监控交易对: {', '.join(self.trading_pairs)}")
    
    def start_monitoring(self):
        """开始实时监控"""
        if self.is_running:
            logger.warning("⚠️ 监控已在运行中")
            return
        
        self.is_running = True
        logger.info("🎯 开始实时价格监控和策略分析")
        
        # 启动监控线程
        monitoring_thread = threading.Thread(target=self._monitoring_loop)
        monitoring_thread.daemon = True
        monitoring_thread.start()
        
        # 启动交易执行线程
        trading_thread = threading.Thread(target=self._trading_loop)
        trading_thread.daemon = True
        trading_thread.start()
        
        logger.info(f"✅ 监控系统已启动，检查间隔: {self.monitoring_interval}秒")
    
    def stop_monitoring(self):
        """停止监控"""
        self.is_running = False
        logger.info("🛑 停止实时监控")
    
    def _monitoring_loop(self):
        """价格监控循环"""
        while self.is_running:
            try:
                # 更新所有交易对的价格数据
                for symbol in self.trading_pairs:
                    self._update_price_data(symbol)
                
                # 更新持仓
                self.simulator.update_positions()
                
                # 记录账户快照
                self.simulator.record_balance_snapshot()
                
                # 显示状态
                self._display_status()
                
                time.sleep(self.monitoring_interval)
                
            except Exception as e:
                logger.error(f"❌ 监控循环错误: {e}")
                time.sleep(30)
    
    def _trading_loop(self):
        """交易执行循环"""
        while self.is_running:
            try:
                # 分析所有交易对
                for symbol in self.trading_pairs:
                    if symbol in self.price_data and len(self.price_data[symbol]) >= 20:
                        self._analyze_and_trade(symbol)
                
                # 检查止损止盈
                self._check_exit_conditions()
                
                time.sleep(30)  # 交易检查间隔30秒
                
            except Exception as e:
                logger.error(f"❌ 交易循环错误: {e}")
                time.sleep(60)
    
    def _update_price_data(self, symbol: str):
        """更新价格数据"""
        try:
            current_price = self.simulator.get_current_price(symbol)
            if current_price <= 0:
                return
            
            timestamp = datetime.now()
            
            if symbol not in self.price_data:
                self.price_data[symbol] = []
            
            # 生成OHLCV数据 (模拟)
            if len(self.price_data[symbol]) == 0:
                # 首次数据
                ohlcv = {
                    'timestamp': timestamp,
                    'open': current_price,
                    'high': current_price * 1.005,
                    'low': current_price * 0.995,
                    'close': current_price,
                    'volume': np.random.uniform(100, 1000)
                }
            else:
                # 基于前一个价格生成数据
                prev_close = self.price_data[symbol][-1]['close']
                price_change = (current_price - prev_close) / prev_close
                
                ohlcv = {
                    'timestamp': timestamp,
                    'open': prev_close,
                    'high': max(prev_close, current_price) * (1 + abs(price_change) * 0.5),
                    'low': min(prev_close, current_price) * (1 - abs(price_change) * 0.5),
                    'close': current_price,
                    'volume': np.random.uniform(100, 1000)
                }
            
            self.price_data[symbol].append(ohlcv)
            
            # 保留最近100个数据点
            if len(self.price_data[symbol]) > 100:
                self.price_data[symbol] = self.price_data[symbol][-100:]
            
        except Exception as e:
            logger.error(f"❌ 更新价格数据失败 {symbol}: {e}")
    
    def _analyze_and_trade(self, symbol: str):
        """分析并执行交易"""
        try:
            # 转换为DataFrame
            df = pd.DataFrame(self.price_data[symbol])
            if len(df) < 20:
                return
            
            # 策略分析
            analysis = self.strategy_engine.analyze_symbol(df, symbol)
            signal_data = analysis['final_signal']
            
            # 保存信号
            self.last_signals[symbol] = analysis
            self._save_signal_to_db(analysis)
            
            # 检查是否应该交易
            if self._should_trade(symbol, signal_data):
                self._execute_trade(symbol, signal_data)
            
        except Exception as e:
            logger.error(f"❌ 分析交易失败 {symbol}: {e}")
    
    def _should_trade(self, symbol: str, signal_data: Dict) -> bool:
        """判断是否应该交易"""
        try:
            signal = signal_data.get('signal', 'hold')
            confidence = signal_data.get('confidence', 0.0)
            
            # 基本条件检查
            if signal == 'hold' or confidence < 0.6:
                return False
            
            # 检查最大持仓限制
            if len(self.simulator.positions) >= self.max_positions:
                logger.info(f"⚠️ 已达到最大持仓数限制 ({self.max_positions})")
                return False
            
            # 检查是否已有该交易对的持仓
            for pos in self.simulator.positions.values():
                if pos['symbol'] == symbol:
                    logger.info(f"⚠️ {symbol} 已有持仓，跳过")
                    return False
            
            # 检查余额
            required_balance = self.simulator.current_balance * self.risk_per_trade
            if required_balance < 10:  # 最小10U
                logger.warning(f"⚠️ 余额不足，无法开仓")
                return False
            
            return True
            
        except Exception as e:
            logger.error(f"❌ 交易条件检查失败: {e}")
            return False
    
    def _execute_trade(self, symbol: str, signal_data: Dict):
        """执行交易"""
        try:
            signal = signal_data['signal']
            confidence = signal_data['confidence']
            
            # 确定交易方向
            direction = 'long' if signal == 'buy' else 'short'
            side = 'buy' if signal == 'buy' else 'sell'
            
            # 获取杠杆设置
            leverage = self.leverage_settings.get(symbol, 1.0)
            
            # 根据置信度调整风险
            risk_multiplier = min(2.0, confidence * 2)
            adjusted_risk = self.risk_per_trade * risk_multiplier
            
            # 执行开仓
            success = self.simulator.open_position(
                symbol=symbol,
                side=side,
                direction=direction,
                position_type='futures',
                leverage=leverage,
                risk_percent=adjusted_risk
            )
            
            if success:
                logger.info(f"✅ 交易执行成功: {symbol} {direction} {leverage}x")
                logger.info(f"📊 信号置信度: {confidence:.1%}")
                logger.info(f"📝 交易原因: {signal_data.get('reason', '无')}")
            else:
                logger.warning(f"❌ 交易执行失败: {symbol}")
            
        except Exception as e:
            logger.error(f"❌ 执行交易失败: {e}")
    
    def _check_exit_conditions(self):
        """检查退出条件"""
        try:
            for position_id, position in list(self.simulator.positions.items()):
                symbol = position['symbol']
                direction = position['direction']
                entry_price = position['entry_price']
                leverage = position['leverage']
                
                current_price = self.simulator.get_current_price(symbol)
                if current_price <= 0:
                    continue
                
                # 计算盈亏百分比
                if direction == 'long':
                    pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                else:
                    pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
                
                # 止损条件 (-5%)
                if pnl_percent <= -5:
                    logger.info(f"🛑 触发止损: {symbol} {direction} ({pnl_percent:.1f}%)")
                    self.simulator.close_position(position_id, "stop_loss")
                
                # 止盈条件 (+10%)
                elif pnl_percent >= 10:
                    logger.info(f"🎯 触发止盈: {symbol} {direction} ({pnl_percent:.1f}%)")
                    self.simulator.close_position(position_id, "take_profit")
                
                # 反向信号退出
                elif self._check_reverse_signal(symbol, direction):
                    logger.info(f"🔄 反向信号退出: {symbol} {direction}")
                    self.simulator.close_position(position_id, "reverse_signal")
            
        except Exception as e:
            logger.error(f"❌ 检查退出条件失败: {e}")
    
    def _check_reverse_signal(self, symbol: str, current_direction: str) -> bool:
        """检查是否有反向信号"""
        try:
            if symbol not in self.last_signals:
                return False
            
            latest_signal = self.last_signals[symbol]['final_signal']
            signal = latest_signal.get('signal', 'hold')
            confidence = latest_signal.get('confidence', 0.0)
            
            # 强反向信号
            if confidence > 0.7:
                if current_direction == 'long' and signal == 'sell':
                    return True
                elif current_direction == 'short' and signal == 'buy':
                    return True
            
            return False
            
        except:
            return False
    
    def _save_signal_to_db(self, analysis: Dict):
        """保存信号到数据库"""
        try:
            conn = sqlite3.connect(self.simulator.db_path)
            cursor = conn.cursor()
            
            signal_data = analysis['final_signal']
            
            cursor.execute('''
            INSERT INTO strategy_signals (symbol, signal, confidence, reason, price)
            VALUES (?, ?, ?, ?, ?)
            ''', (
                analysis['symbol'],
                signal_data['signal'],
                signal_data['confidence'],
                signal_data['reason'],
                analysis['price']
            ))
            
            conn.commit()
            conn.close()
            
        except Exception as e:
            logger.error(f"❌ 保存信号失败: {e}")
    
    def _display_status(self):
        """显示当前状态"""
        try:
            summary = self.simulator.get_account_summary()
            
            print(f"\n{'='*60}")
            print(f"🕐 {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} - 实时交易状态")
            print(f"{'='*60}")
            
            # 账户信息
            print(f"💰 账户余额: ${summary['current_balance']:.2f}U")
            print(f"💎 总权益: ${summary['total_equity']:.2f}U")
            
            color = "🟢" if summary['total_pnl'] >= 0 else "🔴"
            print(f"{color} 总盈亏: {summary['total_pnl_percent']:+.2f}% (${summary['total_pnl']:+.2f}U)")
            print(f"📊 持仓数量: {summary['open_positions']}")
            
            # 当前价格
            print(f"\n📈 当前价格:")
            for symbol in self.trading_pairs[:3]:  # 显示前3个
                if symbol in self.price_data and self.price_data[symbol]:
                    price = self.price_data[symbol][-1]['close']
                    
                    signal_info = ""
                    if symbol in self.last_signals:
                        sig = self.last_signals[symbol]['final_signal']
                        signal_emoji = {"buy": "🟢", "sell": "🔴", "hold": "⚫"}.get(sig['signal'], "⚫")
                        signal_info = f" {signal_emoji} {sig['signal'].upper()} ({sig['confidence']:.1%})"
                    
                    print(f"  {symbol}: ${price:.2f}{signal_info}")
            
            # 当前持仓
            if summary['open_positions'] > 0:
                print(f"\n📋 当前持仓:")
                for pos_id, pos in self.simulator.positions.items():
                    current_price = self.simulator.get_current_price(pos['symbol'])
                    if current_price > 0:
                        if pos['direction'] == 'long':
                            pnl_pct = ((current_price - pos['entry_price']) / pos['entry_price']) * 100 * pos['leverage']
                        else:
                            pnl_pct = ((pos['entry_price'] - current_price) / pos['entry_price']) * 100 * pos['leverage']
                        
                        pnl_color = "🟢" if pnl_pct >= 0 else "🔴"
                        print(f"  {pos['symbol']} {pos['direction']} {pos['leverage']}x: {pnl_color} {pnl_pct:+.1f}%")
            
            print(f"{'='*60}\n")
            
        except Exception as e:
            logger.error(f"❌ 显示状态失败: {e}")
    
    def get_trading_summary(self) -> Dict:
        """获取交易摘要"""
        try:
            conn = sqlite3.connect(self.simulator.db_path)
            
            # 获取交易统计
            query = '''
            SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN pnl_amount > 0 THEN 1 ELSE 0 END) as winning_trades,
                AVG(pnl_percent) as avg_return,
                SUM(pnl_amount) as total_pnl,
                MAX(pnl_percent) as best_trade,
                MIN(pnl_percent) as worst_trade
            FROM trades 
            WHERE status = 'closed'
            '''
            
            df = pd.read_sql_query(query, conn)
            stats = df.iloc[0].to_dict()
            
            # 计算胜率
            if stats['total_trades'] > 0:
                stats['win_rate'] = (stats['winning_trades'] / stats['total_trades']) * 100
            else:
                stats['win_rate'] = 0
            
            # 获取账户摘要
            account_summary = self.simulator.get_account_summary()
            
            conn.close()
            
            return {
                'account': account_summary,
                'trading_stats': stats,
                'active_positions': len(self.simulator.positions),
                'monitoring_pairs': self.trading_pairs
            }
            
        except Exception as e:
            logger.error(f"❌ 获取交易摘要失败: {e}")
            return {}

if __name__ == "__main__":
    # 启动实时交易模拟器
    trader = RealTimeTrader(initial_balance=1000.0)
    trader.start_monitoring()
    
    try:
        print("🚀 实时交易模拟器已启动")
        print("按 Ctrl+C 停止运行")
        
        while True:
            time.sleep(60)
            
    except KeyboardInterrupt:
        print("\n🛑 收到停止信号")
        trader.stop_monitoring()
        
        # 显示最终摘要
        summary = trader.get_trading_summary()
        if summary:
            print(f"\n📊 最终交易摘要:")
            print(f"💰 最终余额: ${summary['account']['current_balance']:.2f}U")
            print(f"📈 总回报率: {summary['account']['total_pnl_percent']:+.2f}%")
            if summary['trading_stats']['total_trades'] > 0:
                print(f"🎯 胜率: {summary['trading_stats']['win_rate']:.1f}%")
                print(f"📊 总交易数: {summary['trading_stats']['total_trades']}")
        
        print("👋 交易模拟器已停止")