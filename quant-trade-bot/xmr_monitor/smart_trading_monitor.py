#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
智能交易监控系统 (2合1)
1. 如果有持仓 -> 监控盈亏，发送持仓更新
2. 如果没有持仓 -> 扫描买入信号，发现机会时通知
"""

import requests
import json
import time
import os
import pandas as pd
import numpy as np
import sqlite3
from datetime import datetime

class SmartTradingMonitor:
    """智能交易监控 - 持仓监控 + 信号扫描二合一"""
    
    def __init__(self, position_file='../my_xmr_position.json'):
        self.position_file = position_file
        self.position = None
        self.telegram_available = self._init_telegram()
        
        # 监控币种列表（用于信号扫描）
        self.watch_symbols = ['XMR', 'MEMES', 'AXS']
        
        # 策略参数
        self.rsi_threshold = (30, 70)  # RSI超买超卖阈值
        self.volume_spike_threshold = 2.0  # 成交量异常阈值
        self.price_change_threshold = 5.0  # 价格变化阈值
        
        # 初始化数据库
        self.init_database()
        
        # 加载持仓
        self.load_position()
        
        if self.position:
            print(f"✅ 模式: 持仓监控 ({self.position['symbol']})")
        else:
            print(f"ℹ️  模式: 信号扫描 (监控 {len(self.watch_symbols)} 个币种)")
        
        print(f"📱 Telegram通知: {'✅启用' if self.telegram_available else '❌未启用'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_paths = [
                '../config/config.json',
                os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'config', 'config.json')
            ]
            
            for path in config_paths:
                if os.path.exists(path):
                    with open(path, 'r', encoding='utf-8') as f:
                        config = json.load(f)
                        telegram_config = config.get('telegram', {})
                        self.bot_token = telegram_config.get('bot_token')
                        self.chat_id = telegram_config.get('chat_id')
                        
                        if self.bot_token and self.chat_id:
                            return True
        except Exception as e:
            print(f"❌ Telegram配置失败: {e}")
        return False
    
    def load_position(self):
        """加载持仓信息"""
        try:
            paths = [
                self.position_file,
                os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'my_xmr_position.json'),
                '/Users/hongtou/newproject/quant-trade-bot/my_xmr_position.json'
            ]
            
            for path in paths:
                if os.path.exists(path):
                    with open(path, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    
                    if data.get('status') == 'OPEN':
                        self.position = {
                            'symbol': data['symbol'],
                            'entry_price': data['entry_price'],
                            'leverage': data.get('leverage', 1),
                            'position_size': data.get('position_size', 0),
                            'stop_loss': data.get('stop_loss'),
                            'take_profit': data.get('take_profit'),
                            'side': data.get('side', 'LONG')
                        }
                        return True
                    else:
                        return False
        except Exception as e:
            print(f"❌ 加载持仓失败: {e}")
        return False
    
    def get_price(self, symbol='XMR'):
        """获取价格 - CoinGecko"""
        coin_id_map = {
            'XMR': 'monero',
            'MEMES': 'memecoin',
            'AXS': 'axie-infinity'
        }
        
        coin_id = coin_id_map.get(symbol)
        
        if not coin_id:
            return None
        
        try:
            url = f'https://api.coingecko.com/api/v3/simple/price?ids={coin_id}&vs_currencies=usd'
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                price = data[coin_id]['usd']
                return price
        except Exception as e:
            print(f"❌ {symbol} 价格获取失败: {e}")
        
        return None
    
    def init_database(self):
        """初始化数据库"""
        try:
            db_path = '../data/db/strategy_signals.db'
            os.makedirs(os.path.dirname(db_path), exist_ok=True)
            
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            # 创建策略信号表
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS strategy_signals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    symbol TEXT NOT NULL,
                    signal_type TEXT NOT NULL,
                    price REAL NOT NULL,
                    confidence REAL NOT NULL,
                    strategy_name TEXT NOT NULL,
                    parameters TEXT,
                    status TEXT DEFAULT 'active'
                )
            ''')
            
            # 创建模拟交易表
            cursor.execute('''
                CREATE TABLE IF NOT EXISTS paper_positions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    symbol TEXT NOT NULL,
                    direction TEXT NOT NULL,
                    entry_price REAL NOT NULL,
                    amount REAL NOT NULL,
                    leverage REAL DEFAULT 1.0,
                    stop_loss REAL,
                    take_profit REAL,
                    open_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                    close_time DATETIME,
                    close_price REAL,
                    pnl REAL DEFAULT 0.0,
                    status TEXT DEFAULT 'open'
                )
            ''')
            
            conn.commit()
            conn.close()
            print("✅ 策略数据库初始化完成")
        except Exception as e:
            print(f"❌ 数据库初始化失败: {e}")
    
    def get_kline_data(self, symbol='XMRUSDT', limit=100):
        """获取K线数据（Binance）"""
        try:
            url = f"https://api.binance.com/api/v3/klines"
            params = {
                'symbol': symbol,
                'interval': '15m',
                'limit': limit
            }
            response = requests.get(url, params=params, timeout=10)
            if response.status_code == 200:
                data = response.json()
                df = pd.DataFrame(data, columns=[
                    'timestamp', 'open', 'high', 'low', 'close', 'volume',
                    'close_time', 'quote_volume', 'trades', 'taker_buy_base',
                    'taker_buy_quote', 'ignore'
                ])
                df['close'] = df['close'].astype(float)
                df['volume'] = df['volume'].astype(float)
                return df
        except Exception as e:
            print(f"❌ K线数据获取失败: {e}")
        return None
    
    def calculate_rsi(self, prices, period=14):
        """计算RSI"""
        if len(prices) < period + 1:
            return 50  # 默认值
        
        deltas = np.diff(prices)
        gain = np.where(deltas > 0, deltas, 0)
        loss = np.where(deltas < 0, -deltas, 0)
        
        avg_gain = np.mean(gain[-period:])
        avg_loss = np.mean(loss[-period:])
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def scan_buy_signals(self):
        """扫描买入信号"""
        signals = []
        
        print(f"\n{'='*60}")
        print(f"🔍 扫描买入信号 - {datetime.now().strftime('%H:%M:%S')}")
        print(f"{'='*60}")
        
        for symbol in self.watch_symbols:
            try:
                # 获取价格
                price = self.get_price(symbol)
                if not price:
                    continue
                
                print(f"\n{symbol}: ${price:.2f}")
                
                # 获取K线数据分析技术指标
                binance_symbol = f"{symbol}USDT"
                df = self.get_kline_data(binance_symbol)
                
                if df is None or len(df) < 50:
                    print(f"  ⚠️ 数据不足")
                    continue
                
                # 计算技术指标
                rsi = self.calculate_rsi(df['close'].values)
                ma20 = df['close'].rolling(20).mean().iloc[-1]
                ma50 = df['close'].rolling(50).mean().iloc[-1]
                volume_avg = df['volume'].rolling(20).mean().iloc[-1]
                volume_current = df['volume'].iloc[-1]
                volume_ratio = volume_current / volume_avg if volume_avg > 0 else 1
                
                # 策略评分系统
                score = 0
                signals_detail = []
                
                # RSI超卖信号
                if rsi < self.rsi_threshold[0]:
                    score += 30
                    signals_detail.append(f"RSI超卖({rsi:.1f})")
                elif rsi > self.rsi_threshold[1]:
                    score -= 20
                    signals_detail.append(f"RSI超买({rsi:.1f})")
                
                # 均线信号
                if price > ma20 > ma50:
                    score += 20
                    signals_detail.append("均线多头")
                elif price < ma20 < ma50:
                    score -= 15
                    signals_detail.append("均线空头")
                
                # 成交量信号
                if volume_ratio > self.volume_spike_threshold:
                    score += 15
                    signals_detail.append(f"成交量爆发({volume_ratio:.1f}x)")
                
                # 价格变化信号
                price_change_24h = ((df['close'].iloc[-1] - df['close'].iloc[-24]) / df['close'].iloc[-24]) * 100 if len(df) >= 24 else 0
                if abs(price_change_24h) > self.price_change_threshold:
                    if price_change_24h < 0:
                        score += 10
                        signals_detail.append(f"深度回调({price_change_24h:.1f}%)")
                
                # 输出分析结果
                print(f"  📊 RSI: {rsi:.1f}")
                print(f"  📈 MA20/50: ${ma20:.2f}/${ma50:.2f}")
                print(f"  📊 成交量比: {volume_ratio:.1f}x")
                print(f"  📊 24h变化: {price_change_24h:+.1f}%")
                print(f"  🎯 信号: {', '.join(signals_detail) if signals_detail else '无明显信号'}")
                print(f"  ⭐ 评分: {score}/100")
                
                # 高分信号入库和开仓
                if score >= 60:
                    signal_data = {
                        'symbol': symbol,
                        'price': price,
                        'score': score,
                        'rsi': rsi,
                        'signals': signals_detail
                    }
                    
                    # 保存信号到数据库
                    self.save_signal_to_db(symbol, 'BUY', price, score/100, 'MultiStrategy', 
                                          json.dumps({'rsi': rsi, 'ma_trend': 'bullish' if price > ma20 > ma50 else 'neutral',
                                                    'volume_ratio': volume_ratio, 'signals': signals_detail}))
                    
                    # 开启模拟仓位
                    self.open_paper_position(symbol, 'LONG', price, score)
                    
                    signals.append(signal_data)
                    print(f"  🚀 **买入信号触发** - 已入库并开仓!")
            
            except Exception as e:
                print(f"  ❌ {symbol} 分析失败: {e}")
        
        return signals
    
    def save_signal_to_db(self, symbol, signal_type, price, confidence, strategy_name, parameters):
        """保存信号到数据库"""
        try:
            db_path = '../data/db/strategy_signals.db'
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO strategy_signals 
                (symbol, signal_type, price, confidence, strategy_name, parameters)
                VALUES (?, ?, ?, ?, ?, ?)
            ''', (symbol, signal_type, price, confidence, strategy_name, parameters))
            
            conn.commit()
            conn.close()
            print(f"✅ 信号已保存: {symbol} {signal_type} @ ${price:.2f}")
            
        except Exception as e:
            print(f"❌ 信号保存失败: {e}")
    
    def open_paper_position(self, symbol, direction, price, confidence_score):
        """开启模拟仓位"""
        try:
            # 计算仓位参数
            leverage = 2.0 if confidence_score >= 80 else 1.5
            amount = 100  # 基础金额100U
            stop_loss = price * 0.95 if direction == 'LONG' else price * 1.05  # 5%止损
            take_profit = price * 1.10 if direction == 'LONG' else price * 0.90  # 10%止盈
            
            db_path = '../data/db/strategy_signals.db'
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO paper_positions 
                (symbol, direction, entry_price, amount, leverage, stop_loss, take_profit)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', (symbol, direction, price, amount, leverage, stop_loss, take_profit))
            
            conn.commit()
            conn.close()
            
            print(f"📈 模拟开仓: {symbol} {direction} @ ${price:.2f} | 杠杆:{leverage}x | 止损:${stop_loss:.2f} | 止盈:${take_profit:.2f}")
            
            # Telegram通知
            self.send_signal_notification(symbol, direction, price, confidence_score, leverage, stop_loss, take_profit)
            
        except Exception as e:
            print(f"❌ 模拟开仓失败: {e}")
    
    def send_signal_notification(self, symbol, direction, price, score, leverage, stop_loss, take_profit):
        """发送信号通知"""
        if not self.telegram_available:
            return
            
        message = f"""🚀 <b>交易信号</b>

💎 币种: {symbol}
📈 方向: {direction}
💰 价格: ${price:.2f}
⭐ 评分: {score}/100
🔥 杠杆: {leverage}x

🛡️ 止损: ${stop_loss:.2f}
🎯 止盈: ${take_profit:.2f}

⏰ {datetime.now().strftime('%H:%M:%S')}
🤖 智能策略监控系统"""
        
        self.send_telegram_message(message)
    
    def get_kline_data(self, symbol='XMRUSDT', limit=100):
        """获取K线数据（Binance）"""
        try:
            url = f"https://api.binance.com/api/v3/klines"
            params = {
                'symbol': symbol,
                'interval': '15m',
                'limit': limit
            }
            response = requests.get(url, params=params, timeout=10)
            if response.status_code == 200:
                data = response.json()
                df = pd.DataFrame(data, columns=[
                    'timestamp', 'open', 'high', 'low', 'close', 'volume',
                    'close_time', 'quote_volume', 'trades', 'taker_buy_base',
                    'taker_buy_quote', 'ignore'
                ])
                df['close'] = df['close'].astype(float)
                df['volume'] = df['volume'].astype(float)
                return df
        except Exception as e:
            print(f"❌ K线数据获取失败: {e}")
        return None
    
    def calculate_rsi(self, prices, period=14):
        """计算RSI"""
        if len(prices) < period + 1:
            return 50  # 默认值
        
        deltas = np.diff(prices)
        gains = np.where(deltas > 0, deltas, 0)
        losses = np.where(deltas < 0, -deltas, 0)
        
        avg_gain = np.mean(gains[:period])
        avg_loss = np.mean(losses[:period])
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
                    confidence += 15
                
                # 2. 金叉
                if price > ma20 > ma50:
                    buy_signals.append("均线多头")
                    confidence += 25
                elif price > ma20:
                    buy_signals.append("价格突破MA20")
                    confidence += 15
                
                # 3. 成交量放大
                if volume_ratio > 1.5:
                    buy_signals.append("成交量放大")
                    confidence += 20
                
                # 如果有买入信号
                if buy_signals and confidence >= 40:
                    signals.append({
                        'symbol': symbol,
                        'price': price,
                        'rsi': rsi,
                        'volume_ratio': volume_ratio,
                        'confidence': confidence,
                        'reasons': buy_signals
                    })
                    print(f"  ✅ 买入信号 (信心度: {confidence}%)")
                    print(f"     理由: {', '.join(buy_signals)}")
                
                time.sleep(0.5)  # 避免API限制
                
            except Exception as e:
                print(f"  ❌ {symbol} 分析失败: {e}")
        
        return signals
    
    def send_telegram_message(self, message):
        """发送Telegram消息"""
        if not self.telegram_available:
            return
        
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            payload = {
                "chat_id": self.chat_id,
                "text": message,
                "parse_mode": "HTML"
            }
            response = requests.post(url, json=payload, timeout=10)
            if response.status_code == 200:
                print("✅ Telegram通知已发送")
            else:
                print(f"❌ Telegram发送失败: {response.status_code}")
        except Exception as e:
            print(f"❌ Telegram发送错误: {e}")
    
    def send_buy_signals(self, signals):
        """发送买入信号通知"""
        if not signals:
            return
        
        timestamp = datetime.now().strftime('%H:%M:%S')
        message = f"""🚨 <b>买入信号提醒</b> (技术指标)

⏰ 扫描时间: {timestamp}
📊 发现 {len(signals)} 个机会
━━━━━━━━━━━━━━"""
        
        for i, sig in enumerate(signals, 1):
            stop_loss = sig['price'] * 0.95
            take_profit = sig['price'] * 1.08
            
            message += f"""

{i}. <b>{sig['symbol']}</b> 📈
💰 现价: ${sig['price']:.2f}
📊 RSI: {sig['rsi']:.1f}
📈 成交量: {sig['volume_ratio']:.2f}x
💡 信心度: {sig['confidence']}%
📝 理由: {', '.join(sig['reasons'])}

建议:
🛡️ 止损: ${stop_loss:.2f} (-5%)
🎯 止盈: ${take_profit:.2f} (+8%)"""
        
        self.send_telegram_message(message)
    
    def send_position_update(self, current_price, pnl_data):
        """发送持仓更新"""
        roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
        pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
        
        symbol = self.position['symbol'].replace('/USDT', '')
        side_emoji = "🔴 做空" if self.position['side'] == 'SHORT' else "🟢 做多"
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        message = f"""🎯 <b>{symbol} 持仓更新</b> {side_emoji}

💰 现价: ${current_price:.2f}
📈 入场: ${self.position['entry_price']:.2f}
📊 涨跌: {pnl_data['price_change_percent']:+.2f}%
💎 杠杆: {self.position['leverage']}x

━━━━━━━━━━━━━━
💵 ROI: {roi_emoji}{pnl_data['roi']:+.2f}%
💰 盈亏: {pnl_emoji}${pnl_data['pnl_amount']:+.2f}U

⏰ 更新时间: {timestamp}"""
        
        self.send_telegram_message(message)
    
    def calculate_pnl(self, current_price):
        """计算盈亏"""
        if not self.position:
            return None
        
        entry_price = self.position['entry_price']
        leverage = self.position['leverage']
        position_size = self.position['position_size']
        is_short = self.position['side'] == 'SHORT'
        
        if is_short:
            price_change_percent = (entry_price - current_price) / entry_price * 100
        else:
            price_change_percent = (current_price - entry_price) / entry_price * 100
        
        roi = price_change_percent * leverage
        pnl_amount = (roi / 100) * position_size
        
        return {
            'price_change_percent': price_change_percent,
            'roi': roi,
            'pnl_amount': pnl_amount
        }
    
    def run_monitoring(self, interval=300):
        """运行监控"""
        print(f"\n🚀 智能监控启动 (间隔{interval}秒)")
        print("按 Ctrl+C 停止监控\n")
        
        try:
            while True:
                # 重新加载持仓状态
                has_position = self.load_position()
                
                if has_position:
                    # 模式1: 持仓监控
                    symbol = self.position['symbol'].replace('/USDT', '')
                    current_price = self.get_price(symbol)
                    
                    if current_price:
                        pnl_data = self.calculate_pnl(current_price)
                        
                        # 终端显示
                        color = "\033[92m" if pnl_data['pnl_amount'] >= 0 else "\033[91m"
                        reset = "\033[0m"
                        timestamp = datetime.now().strftime('%H:%M:%S')
                        
                        print(f"\n📊 {timestamp} {symbol} 持仓")
                        print(f"💰 现价: ${current_price:.2f}")
                        print(f"💵 ROI: {color}{pnl_data['roi']:+.2f}%{reset}")
                        print(f"💰 盈亏: {color}${pnl_data['pnl_amount']:+.2f}U{reset}")
                        print("-" * 50)
                        
                        # 发送Telegram更新
                        self.send_position_update(current_price, pnl_data)
                else:
                    # 模式2: 信号扫描
                    signals = self.scan_buy_signals()
                    
                    if signals:
                        print(f"\n✅ 发现 {len(signals)} 个买入信号")
                        self.send_buy_signals(signals)
                    else:
                        print(f"\nℹ️  暂无强烈买入信号")
                
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")
            if self.telegram_available:
                self.send_telegram_message("⏹️ 智能监控已停止")

def main():
    """主函数"""
    monitor = SmartTradingMonitor()
    monitor.run_monitoring(interval=300)  # 5分钟间隔

if __name__ == "__main__":
    main()
