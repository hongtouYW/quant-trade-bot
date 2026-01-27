#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
智能交易监控系统 - 专注实盘
监控币种: XMR, MEMES, AXS
功能: 持仓监控 + 买卖信号扫描
"""

import requests
import json
import time
import os
import pandas as pd
import numpy as np
from datetime import datetime

class SmartTradingMonitor:
    """智能交易监控 - 持仓监控 + 信号扫描"""
    
    def __init__(self, position_file='../my_xmr_position.json'):
        self.position_file = position_file
        self.position = None
        self.telegram_available = self._init_telegram()
        
        # 监控币种列表 - 你的要求
        self.watch_symbols = ['XMR', 'MEMES', 'AXS']
        
        # 加载持仓
        self.load_position()
        
        if self.position:
            print(f"✅ 模式: 持仓监控 ({self.position['symbol']})")
        else:
            print(f"ℹ️  模式: 信号扫描 (监控 {', '.join(self.watch_symbols)})")
        
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
                if coin_id in data:
                    price = data[coin_id]['usd']
                    return price
        except Exception as e:
            print(f"❌ {symbol} 价格获取失败: {e}")
        
        return None
    
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
            return 50
        
        deltas = np.diff(prices)
        gains = np.where(deltas > 0, deltas, 0)
        losses = np.where(deltas < 0, -deltas, 0)
        
        avg_gain = np.mean(gains[-period:])
        avg_loss = np.mean(losses[-period:])
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def scan_buy_signals(self):
        """扫描买入信号 - XMR, MEMES, AXS"""
        signals = []
        
        print(f"\n{'='*60}")
        print(f"🔍 扫描买卖信号 - {datetime.now().strftime('%H:%M:%S')}")
        print(f"{'='*60}")
        
        for symbol in self.watch_symbols:
            try:
                # 获取价格
                price = self.get_price(symbol)
                if not price:
                    continue
                
                print(f"\n{symbol}: ${price:.6f if price < 1 else price:.2f}")
                
                # 获取K线数据
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
                
                print(f"  RSI: {rsi:.1f}")
                print(f"  MA20: ${ma20:.6f if ma20 < 1 else ma20:.2f}, MA50: ${ma50:.6f if ma50 < 1 else ma50:.2f}")
                print(f"  成交量: {volume_ratio:.2f}x")
                
                # 买入/卖出信号检测
                buy_signals = []
                sell_signals = []
                confidence = 0
                signal_type = None
                
                # 买入信号
                if rsi < 35:
                    buy_signals.append("RSI超卖")
                    confidence += 30
                    signal_type = 'BUY'
                elif rsi < 40:
                    buy_signals.append("RSI偏低")
                    confidence += 15
                    signal_type = 'BUY'
                
                if price > ma20 > ma50:
                    buy_signals.append("均线多头")
                    confidence += 25
                    signal_type = 'BUY'
                elif price > ma20:
                    buy_signals.append("突破MA20")
                    confidence += 15
                    signal_type = 'BUY'
                
                if volume_ratio > 1.5:
                    buy_signals.append("成交量放大")
                    confidence += 20
                
                # 卖出信号
                if rsi > 70:
                    sell_signals.append("RSI超买")
                    confidence += 30
                    signal_type = 'SELL'
                elif rsi > 65:
                    sell_signals.append("RSI偏高")
                    confidence += 15
                    signal_type = 'SELL'
                
                if price < ma20 < ma50:
                    sell_signals.append("均线空头")
                    confidence += 25
                    signal_type = 'SELL'
                elif price < ma20:
                    sell_signals.append("跌破MA20")
                    confidence += 15
                    signal_type = 'SELL'
                
                # 发现信号
                if buy_signals and confidence >= 40:
                    signals.append({
                        'symbol': symbol,
                        'type': 'BUY',
                        'price': price,
                        'rsi': rsi,
                        'volume_ratio': volume_ratio,
                        'confidence': confidence,
                        'reasons': buy_signals
                    })
                    print(f"  ✅ 买入信号 (信心度: {confidence}%)")
                    print(f"     理由: {', '.join(buy_signals)}")
                elif sell_signals and confidence >= 40:
                    signals.append({
                        'symbol': symbol,
                        'type': 'SELL',
                        'price': price,
                        'rsi': rsi,
                        'volume_ratio': volume_ratio,
                        'confidence': confidence,
                        'reasons': sell_signals
                    })
                    print(f"  ⚠️ 卖出信号 (信心度: {confidence}%)")
                    print(f"     理由: {', '.join(sell_signals)}")
                
                time.sleep(0.5)
                
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
    
    def send_trading_signals(self, signals):
        """发送交易信号通知"""
        if not signals:
            return
        
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        buy_signals = [s for s in signals if s['type'] == 'BUY']
        sell_signals = [s for s in signals if s['type'] == 'SELL']
        
        message = f"""📊 <b>交易信号提醒</b>

⏰ 扫描时间: {timestamp}
📈 买入信号: {len(buy_signals)}
📉 卖出信号: {len(sell_signals)}
━━━━━━━━━━━━━━"""
        
        # 买入信号
        for i, sig in enumerate(buy_signals, 1):
            stop_loss = sig['price'] * 0.95
            take_profit = sig['price'] * 1.08
            
            message += f"""

<b>买入 {i}. {sig['symbol']}</b> 📈
💰 现价: ${sig['price']:.6f if sig['price'] < 1 else sig['price']:.2f}
📊 RSI: {sig['rsi']:.1f}
📈 成交量: {sig['volume_ratio']:.2f}x
💡 信心度: {sig['confidence']}%
📝 理由: {', '.join(sig['reasons'])}

建议:
🛡️ 止损: ${stop_loss:.6f if stop_loss < 1 else stop_loss:.2f} (-5%)
🎯 止盈: ${take_profit:.6f if take_profit < 1 else take_profit:.2f} (+8%)"""
        
        # 卖出信号
        for i, sig in enumerate(sell_signals, 1):
            message += f"""

<b>卖出 {i}. {sig['symbol']}</b> 📉
💰 现价: ${sig['price']:.6f if sig['price'] < 1 else sig['price']:.2f}
📊 RSI: {sig['rsi']:.1f}
📉 成交量: {sig['volume_ratio']:.2f}x
⚠️ 信心度: {sig['confidence']}%
📝 理由: {', '.join(sig['reasons'])}"""
        
        self.send_telegram_message(message)
    
    def send_position_update(self, current_price, pnl_data):
        """发送持仓更新"""
        roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
        pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
        
        symbol = self.position['symbol'].replace('/USDT', '')
        side_emoji = "🔴 做空" if self.position['side'] == 'SHORT' else "🟢 做多"
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        # 计算距离止损止盈
        stop_loss = self.position.get('stop_loss')
        take_profit = self.position.get('take_profit')
        
        stop_loss_distance = ""
        take_profit_distance = ""
        
        if stop_loss:
            dist = ((current_price - stop_loss) / current_price * 100)
            stop_loss_distance = f"\n🛡️ 止损: ${stop_loss:.2f} (距离{dist:+.2f}%)"
        
        if take_profit:
            dist = ((take_profit - current_price) / current_price * 100)
            take_profit_distance = f"\n🎯 止盈: ${take_profit:.2f} (距离{dist:+.2f}%)"
        
        message = f"""🎯 <b>{symbol} 持仓更新</b> {side_emoji}

💰 现价: ${current_price:.2f}
📈 入场: ${self.position['entry_price']:.2f}
📊 涨跌: {pnl_data['price_change_percent']:+.2f}%
💎 杠杆: {self.position['leverage']}x

━━━━━━━━━━━━━━
💵 ROI: {roi_emoji}{pnl_data['roi']:+.2f}%
💰 盈亏: {pnl_emoji}${pnl_data['pnl_amount']:+.2f}U{stop_loss_distance}{take_profit_distance}

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
        """运行监控 - 5分钟间隔"""
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
                        
                        # Telegram更新
                        self.send_position_update(current_price, pnl_data)
                else:
                    # 模式2: 信号扫描
                    signals = self.scan_buy_signals()
                    
                    if signals:
                        print(f"\n✅ 发现 {len(signals)} 个交易信号")
                        self.send_trading_signals(signals)
                    else:
                        print(f"\nℹ️  暂无强烈交易信号")
                
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
