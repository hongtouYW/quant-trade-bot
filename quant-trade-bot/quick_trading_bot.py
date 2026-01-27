#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
快速实盘模拟交易系统 - 紧急启动版
每日至少5次交易，多币种监控，完整止损系统
"""

import time
import ccxt
import requests
import sqlite3
import threading
import json
import os
from datetime import datetime, timedelta
from typing import Dict, List

class QuickTradingBot:
    """快速交易机器人"""
    
    def __init__(self):
        self.balance = 1000.0  # 初始资金
        self.trading_pairs = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'DOGE/USDT', 'ADA/USDT', 
                             'MATIC/USDT', 'DOT/USDT', 'LINK/USDT', 'UNI/USDT', 'LTC/USDT', 
                             'BCH/USDT', 'XRP/USDT', 'ATOM/USDT', 'AVAX/USDT', 'FTM/USDT']
        self.positions = {}  # 当前持仓
        self.daily_trades = 0  # 当日交易次数
        self.target_daily_trades = 5  # 目标每日交易次数
        
        # 风险管理
        self.risk_per_trade = 0.02  # 每笔2%风险
        self.stop_loss_pct = 0.05   # 5%止损
        self.take_profit_pct = 0.10 # 10%止盈
        self.max_positions = 5      # 最大5个持仓
        self.positions_per_symbol = 1  # 每个币种最多1个持仓
        self.concurrent_monitoring = True  # 并发监控开关
        
        # 初始化数据库
        self.init_database()
        
        # 交易所API
        self.exchange = ccxt.binance({
            'sandbox': False,
            'enableRateLimit': True
        })
        
        # 加载Telegram配置
        self.telegram_available = self._init_telegram()
        
        print("🚀 快速交易系统启动")
        print(f"💰 资金: ${self.balance}U")
        print(f"🎯 目标: 每日{self.target_daily_trades}次交易")
        print(f"💱 监控: {', '.join(self.trading_pairs)}")
        print(f"📱 Telegram: {'✅' if self.telegram_available else '❌'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_path = 'config/config.json'
            if os.path.exists(config_path):
                with open(config_path, 'r') as f:
                    config = json.load(f)
                    telegram_config = config.get('telegram', {})
                    self.bot_token = telegram_config.get('bot_token')
                    self.chat_id = telegram_config.get('chat_id')
                    return bool(self.bot_token and self.chat_id)
        except Exception as e:
            print(f"❌ Telegram配置失败: {e}")
        return False
    
    def send_telegram_message(self, message):
        """发送Telegram消息"""
        if not self.telegram_available:
            return False
        
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            payload = {
                "chat_id": self.chat_id,
                "text": message,
                "parse_mode": "HTML"
            }
            response = requests.post(url, data=payload, timeout=10)
            return response.status_code == 200
        except Exception as e:
            print(f"❌ Telegram发送失败: {e}")
            return False
    
    def init_database(self):
        """初始化数据库"""
        db_path = 'data/db/quick_trading.db'
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # 创建持仓表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS positions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT NOT NULL,
                direction TEXT NOT NULL,
                entry_price REAL NOT NULL,
                amount REAL NOT NULL,
                leverage REAL DEFAULT 1.0,
                stop_loss REAL,
                take_profit REAL,
                open_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                status TEXT DEFAULT 'open'
            )
        ''')
        
        # 创建交易记录表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS quick_trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                symbol TEXT NOT NULL,
                side TEXT NOT NULL,
                price REAL NOT NULL,
                amount REAL NOT NULL,
                pnl REAL DEFAULT 0.0,
                reason TEXT,
                balance_after REAL
            )
        ''')
        
        conn.commit()
        conn.close()
    
    def get_price(self, symbol):
        """获取实时价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取{symbol}价格失败: {e}")
            return None
    
    def calculate_position_size(self, price, direction='long'):
        """计算仓位大小"""
        risk_amount = self.balance * self.risk_per_trade
        stop_loss_distance = price * self.stop_loss_pct
        
        if direction == 'long':
            amount = risk_amount / stop_loss_distance
        else:  # short
            amount = risk_amount / stop_loss_distance
            
        return min(amount, self.balance * 0.2 / price)  # 最大20%资金
    
    def open_position(self, symbol, direction, price):
        """开仓"""
        if len(self.positions) >= self.max_positions:
            print(f"⚠️ 已达最大持仓数量 {self.max_positions}")
            return False
        
        # 检查该币种是否已有持仓
        symbol_positions = [pos for pos in self.positions.values() if pos['symbol'] == symbol]
        if len(symbol_positions) >= self.positions_per_symbol:
            print(f"⚠️ {symbol} 已有持仓，跳过")
            return False
        
        amount = self.calculate_position_size(price, direction)
        
        if direction == 'long':
            stop_loss = price * (1 - self.stop_loss_pct)
            take_profit = price * (1 + self.take_profit_pct)
        else:  # short
            stop_loss = price * (1 + self.stop_loss_pct)
            take_profit = price * (1 - self.take_profit_pct)
        
        # 保存到数据库
        db_path = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            INSERT INTO positions (symbol, direction, entry_price, amount, stop_loss, take_profit)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (symbol, direction, price, amount, stop_loss, take_profit))
        
        cursor.execute('''
            INSERT INTO quick_trades (symbol, side, price, amount, reason, balance_after)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (symbol, 'buy' if direction == 'long' else 'sell', price, amount, 'auto_signal', self.balance))
        
        position_id = cursor.lastrowid
        conn.commit()
        conn.close()
        
        # 更新内存持仓
        self.positions[position_id] = {
            'symbol': symbol,
            'direction': direction,
            'entry_price': price,
            'amount': amount,
            'stop_loss': stop_loss,
            'take_profit': take_profit
        }
        
        self.daily_trades += 1
        
        # Telegram通知
        direction_emoji = "🟢" if direction == 'long' else "🔴"
        message = f"""{direction_emoji} <b>开仓信号</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━
🤖 <b>量化助理提醒</b>

🎯 <b>{symbol} {direction.upper()}</b>
💰 入场价: ${price:.4f}
📦 数量: {amount:.6f}
🛡️ 止损: ${stop_loss:.4f}
🎯 止盈: ${take_profit:.4f}

💰 余额: ${self.balance:.2f}U
📊 今日交易: {self.daily_trades}/{self.target_daily_trades}
⏰ {datetime.now().strftime('%H:%M:%S')}"""
        
        self.send_telegram_message(message)
        
        print(f"✅ {direction_emoji} {symbol} {direction} 开仓")
        print(f"   💰 价格: ${price:.4f}")
        print(f"   🛡️ 止损: ${stop_loss:.4f}")
        print(f"   🎯 止盈: ${take_profit:.4f}")
        
        return True
    
    def close_position(self, position_id, current_price, reason="manual"):
        """平仓"""
        if position_id not in self.positions:
            return False
            
        position = self.positions[position_id]
        symbol = position['symbol']
        direction = position['direction']
        entry_price = position['entry_price']
        amount = position['amount']
        
        # 计算盈亏
        if direction == 'long':
            pnl = (current_price - entry_price) * amount
        else:  # short
            pnl = (entry_price - current_price) * amount
        
        pnl_percent = (pnl / (entry_price * amount)) * 100
        
        # 更新余额
        self.balance += pnl
        
        # 更新数据库
        db_path = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            UPDATE positions SET status='closed' WHERE id=?
        ''', (position_id,))
        
        cursor.execute('''
            INSERT INTO quick_trades (symbol, side, price, amount, pnl, reason, balance_after)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ''', (symbol, 'sell' if direction == 'long' else 'buy', current_price, amount, pnl, reason, self.balance))
        
        conn.commit()
        conn.close()
        
        # 移除内存持仓
        del self.positions[position_id]
        
        # Telegram通知
        pnl_emoji = "🟢" if pnl > 0 else "🔴"
        message = f"""{pnl_emoji} <b>平仓完成</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━
🤖 <b>量化助理提醒</b>

🎯 <b>{symbol} {direction.upper()}</b>
💰 入场价: ${entry_price:.4f}
💰 出场价: ${current_price:.4f}
💵 盈亏: {pnl_emoji}${pnl:+.2f} ({pnl_percent:+.1f}%)

💰 余额: ${self.balance:.2f}U
📝 原因: {reason}
⏰ {datetime.now().strftime('%H:%M:%S')}"""
        
        self.send_telegram_message(message)
        
        print(f"✅ {pnl_emoji} {symbol} {direction} 平仓")
        print(f"   💵 盈亏: ${pnl:+.2f} ({pnl_percent:+.1f}%)")
        
        return True
    
    def check_stop_loss_take_profit(self):
        """检查止损止盈"""
        for position_id, position in list(self.positions.items()):
            symbol = position['symbol']
            current_price = self.get_price(symbol)
            
            if current_price is None:
                continue
            
            direction = position['direction']
            stop_loss = position['stop_loss']
            take_profit = position['take_profit']
            
            # 检查止损
            if direction == 'long' and current_price <= stop_loss:
                self.close_position(position_id, current_price, "stop_loss")
            elif direction == 'short' and current_price >= stop_loss:
                self.close_position(position_id, current_price, "stop_loss")
            
            # 检查止盈
            elif direction == 'long' and current_price >= take_profit:
                self.close_position(position_id, current_price, "take_profit")
            elif direction == 'short' and current_price <= take_profit:
                self.close_position(position_id, current_price, "take_profit")
    
    def generate_signals(self):
        """生成交易信号 - 简单策略"""
        signals = []
        
        for symbol in self.trading_pairs:
            # 检查是否已有该币种持仓
            has_position = any(pos['symbol'] == symbol for pos in self.positions.values())
            if has_position:
                continue
                
            price = self.get_price(symbol)
            if price is None:
                continue
            
            # 简单策略：基于随机但有一定逻辑的信号生成
            # 实际应该使用技术指标
            import random
            
            # 模拟RSI超卖/超买信号
            rsi_signal = random.choice(['oversold', 'overbought', 'neutral', 'neutral'])
            
            if rsi_signal == 'oversold' and random.random() > 0.7:
                signals.append({
                    'symbol': symbol,
                    'direction': 'long',
                    'price': price,
                    'confidence': random.uniform(0.6, 0.9)
                })
            elif rsi_signal == 'overbought' and random.random() > 0.8:
                signals.append({
                    'symbol': symbol, 
                    'direction': 'short',
                    'price': price,
                    'confidence': random.uniform(0.6, 0.8)
                })
        
        return signals
    
    def run_trading_cycle(self):
        """运行一次交易周期"""
        print(f"\n🔄 {datetime.now().strftime('%H:%M:%S')} 交易周期开始")
        
        # 1. 检查止损止盈
        self.check_stop_loss_take_profit()
        
        # 2. 生成新信号（如果今日交易次数未满）
        if self.daily_trades < self.target_daily_trades:
            signals = self.generate_signals()
            
            for signal in signals:
                if self.daily_trades >= self.target_daily_trades:
                    break
                    
                if signal['confidence'] > 0.7:  # 只执行高置信度信号
                    self.open_position(
                        signal['symbol'],
                        signal['direction'], 
                        signal['price']
                    )
        
        # 3. 显示状态
        print(f"💰 余额: ${self.balance:.2f}U")
        print(f"📊 今日交易: {self.daily_trades}/{self.target_daily_trades}")
        print(f"📦 当前持仓: {len(self.positions)}个")
        
        for pos_id, pos in self.positions.items():
            current_price = self.get_price(pos['symbol'])
            if current_price:
                if pos['direction'] == 'long':
                    pnl_pct = ((current_price - pos['entry_price']) / pos['entry_price']) * 100
                else:
                    pnl_pct = ((pos['entry_price'] - current_price) / pos['entry_price']) * 100
                
                pnl_emoji = "🟢" if pnl_pct > 0 else "🔴"
                print(f"   {pnl_emoji} {pos['symbol']} {pos['direction']}: {pnl_pct:+.1f}%")
    
    def reset_daily_counter(self):
        """重置每日计数器"""
        current_date = datetime.now().date()
        if not hasattr(self, 'last_reset_date') or self.last_reset_date != current_date:
            self.daily_trades = 0
            self.last_reset_date = current_date
            print(f"🔄 每日交易计数器已重置")
    
    def run(self):
        """主运行循环"""
        print("🚀 快速交易系统开始运行")
        print("按 Ctrl+C 停止")
        print("=" * 50)
        
        # 发送启动通知
        start_msg = f"""🚀 <b>快速交易系统启动</b>
━━━━━━━━━━━━━━━━━━━━━━━━━━
💰 初始资金: ${self.balance:.2f}U
🎯 每日目标: {self.target_daily_trades}次交易
💱 监控币种: {len(self.trading_pairs)}个
🛡️ 止损: {self.stop_loss_pct*100:.0f}%
🎯 止盈: {self.take_profit_pct*100:.0f}%
⏰ 启动时间: {datetime.now().strftime('%H:%M:%S')}"""
        
        self.send_telegram_message(start_msg)
        
        try:
            while True:
                # 重置每日计数器
                self.reset_daily_counter()
                
                # 运行交易周期
                self.run_trading_cycle()
                
                # 等待30秒
                time.sleep(30)
                
        except KeyboardInterrupt:
            print("\n👋 交易系统已停止")
            if self.telegram_available:
                self.send_telegram_message("⏹️ 快速交易系统已停止")
    
    def run_trading_cycle(self):
        """运行交易周期"""
        print(f"\n{'='*50}")
        print(f"🔄 交易周期 - {datetime.now().strftime('%H:%M:%S')}")
        print(f"💰 余额: ${self.balance:.2f}U | 持仓: {len(self.positions)} | 今日交易: {self.daily_trades}/{self.target_daily_trades}")
        print(f"{'='*50}")
        
        # 1. 检查现有持仓的止损止盈
        self.check_stop_loss_take_profit()
        
        # 2. 多币种并发监控新机会
        if self.daily_trades < self.target_daily_trades or len(self.positions) < self.max_positions:
            self.monitor_multiple_pairs()
        
        # 3. 显示状态
        self.display_status()
    
    def monitor_multiple_pairs(self):
        """多币种并发监控"""
        def monitor_single_pair(pair):
            try:
                price = self.get_price(pair)
                if price:
                    # 简单的动量策略
                    signal = self.generate_momentum_signal(pair, price)
                    if signal and signal['action'] == 'buy':
                        direction = 'long' if signal['direction'] == 'up' else 'short'
                        self.open_position(pair, direction, price)
                        print(f"🚀 {pair} {direction} 开仓 @ ${price:.4f}")
                    
                    # 检查现有持仓的止损止盈
                    self.check_exits_for_symbol(pair, price)
                    
            except Exception as e:
                print(f"❌ {pair} 监控错误: {e}")
        
        if self.concurrent_monitoring:
            # 并发监控
            threads = []
            for pair in self.trading_pairs:
                thread = threading.Thread(target=monitor_single_pair, args=(pair,))
                thread.start()
                threads.append(thread)
            
            # 等待所有线程完成
            for thread in threads:
                thread.join()
        else:
            # 顺序监控
            for pair in self.trading_pairs:
                monitor_single_pair(pair)
    
    def generate_momentum_signal(self, symbol, current_price):
        """生成动量信号"""
        try:
            # 简单的价格动量策略
            import random
            
            # 模拟动量指标(实际中应该使用真实数据)
            momentum_score = random.uniform(-100, 100)
            volume_spike = random.uniform(0.5, 3.0)
            
            # 交易频率控制
            if self.daily_trades >= self.target_daily_trades:
                threshold = 80  # 提高阈值
            else:
                threshold = 60  # 降低阈值增加交易机会
            
            if momentum_score > threshold and volume_spike > 1.5:
                return {
                    'action': 'buy',
                    'direction': 'up',
                    'confidence': min(momentum_score / 100, 1.0),
                    'reason': f'Momentum({momentum_score:.1f}) + Volume({volume_spike:.1f}x)'
                }
            elif momentum_score < -threshold and volume_spike > 1.5:
                return {
                    'action': 'buy', 
                    'direction': 'down',
                    'confidence': min(abs(momentum_score) / 100, 1.0),
                    'reason': f'Reverse({momentum_score:.1f}) + Volume({volume_spike:.1f}x)'
                }
                
            return None
            
        except Exception as e:
            print(f"❌ {symbol} 信号生成失败: {e}")
            return None
    
    def check_exits_for_symbol(self, symbol, current_price):
        """检查特定币种的退出条件"""
        positions_to_close = []
        
        for pos_id, position in self.positions.items():
            if position['symbol'] != symbol:
                continue
                
            direction = position['direction']
            entry_price = position['entry_price']
            stop_loss = position['stop_loss']
            take_profit = position['take_profit']
            
            should_close = False
            close_reason = ""
            
            if direction == 'long':
                if current_price <= stop_loss:
                    should_close = True
                    close_reason = "stop_loss"
                elif current_price >= take_profit:
                    should_close = True
                    close_reason = "take_profit"
            else:  # short
                if current_price >= stop_loss:
                    should_close = True
                    close_reason = "stop_loss"
                elif current_price <= take_profit:
                    should_close = True
                    close_reason = "take_profit"
            
            if should_close:
                positions_to_close.append((pos_id, close_reason, current_price))
        
        # 关闭满足条件的持仓
        for pos_id, reason, price in positions_to_close:
            self.close_position(pos_id, price, reason)
    
    def display_status(self):
        """显示状态"""
        if self.positions:
            print("\n📊 当前持仓:")
            for pos_id, pos in self.positions.items():
                current_price = self.get_price(pos['symbol'])
                if current_price:
                    if pos['direction'] == 'long':
                        pnl_pct = ((current_price - pos['entry_price']) / pos['entry_price']) * 100
                    else:
                        pnl_pct = ((pos['entry_price'] - current_price) / pos['entry_price']) * 100
                    
                    pnl_emoji = "🟢" if pnl_pct > 0 else "🔴"
                    print(f"  {pnl_emoji} {pos['symbol']} {pos['direction']} | 入场:${pos['entry_price']:.4f} | 现价:${current_price:.4f} | PNL:{pnl_pct:+.1f}%")
        else:
            print("📭 暂无持仓")
    
    def reset_daily_counter(self):
        """重置每日计数器"""
        now = datetime.now()
        if not hasattr(self, 'last_reset') or now.date() != self.last_reset.date():
            self.daily_trades = 0
            self.last_reset = now
            print(f"🔄 每日交易计数器已重置")

def main():
    """主函数"""
    trader = QuickTradingBot()
    trader.run()

if __name__ == "__main__":
    main()