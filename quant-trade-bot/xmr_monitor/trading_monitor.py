#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
交易风控助手 - 实盘监控专用
监控: XMR, MEMES, AXS
功能: 持仓监控 + 买卖信号
"""

import requests
import json
import time
import os
from datetime import datetime

class TradingMonitor:
    """交易监控 - 简化版无pandas依赖"""
    
    def __init__(self):
        self.position = None
        self.telegram_available = self._init_telegram()
        # 扩展监控列表：原有 + 新发现的潜力币
        self.watch_symbols = ['XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK']
        self.last_scan_time = 0  # 记录上次全市场扫描时间
        
        self.load_position()
        
        if self.position:
            print(f"✅ 持仓监控: {self.position['symbol']}")
        else:
            print(f"✅ 信号扫描: {', '.join(self.watch_symbols)}")
        
        print(f"📱 Telegram: {'✅' if self.telegram_available else '❌'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_path = '/Users/hongtou/newproject/quant-trade-bot/config/config.json'
            if os.path.exists(config_path):
                with open(config_path, 'r') as f:
                    config = json.load(f)
                    tg = config.get('telegram', {})
                    self.bot_token = tg.get('bot_token')
                    self.chat_id = tg.get('chat_id')
                    return bool(self.bot_token and self.chat_id)
        except:
            pass
        return False
    
    def load_position(self):
        """加载持仓"""
        try:
            path = '/Users/hongtou/newproject/quant-trade-bot/my_xmr_position.json'
            with open(path, 'r') as f:
                data = json.load(f)
            
            if data.get('status') == 'OPEN':
                self.position = {
                    'symbol': data['symbol'].replace('/USDT', ''),
                    'entry_price': data['entry_price'],
                    'leverage': data.get('leverage', 1),
                    'position_size': data.get('position_size', 0),
                    'stop_loss': data.get('stop_loss'),
                    'take_profit': data.get('take_profit'),
                    'side': data.get('side', 'LONG')
                }
                return True
        except:
            pass
        return False
    
    def get_price(self, symbol):
        """获取价格"""
        coin_map = {
            'XMR': 'monero',
            'MEMES': 'memecoin',
            'AXS': 'axie-infinity',
            'ROSE': 'oasis-network',
            'XRP': 'ripple',
            'SOL': 'solana',
            'DUSK': 'dusk-network'
        }
        coin_id = coin_map.get(symbol)
        
        if not coin_id:
            return None
        
        try:
            url = f'https://api.coingecko.com/api/v3/simple/price?ids={coin_id}&vs_currencies=usd'
            r = requests.get(url, timeout=10)
            if r.status_code == 200:
                return r.json()[coin_id]['usd']
        except:
            pass
        return None
    
    def get_binance_kline(self, symbol):
        """获取币安K线"""
        try:
            url = "https://api.binance.com/api/v3/klines"
            params = {'symbol': f'{symbol}USDT', 'interval': '15m', 'limit': 100}
            r = requests.get(url, params=params, timeout=10)
            if r.status_code == 200:
                data = r.json()
                closes = [float(k[4]) for k in data]
                volumes = [float(k[5]) for k in data]
                return closes, volumes
        except:
            pass
        return None, None
    
    def calculate_rsi(self, prices, period=14):
        """简单RSI计算"""
        if len(prices) < period + 1:
            return 50
        
        gains, losses = [], []
        for i in range(1, len(prices)):
            change = prices[i] - prices[i-1]
            gains.append(max(change, 0))
            losses.append(max(-change, 0))
        
        avg_gain = sum(gains[-period:]) / period
        avg_loss = sum(losses[-period:]) / period
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        return 100 - (100 / (1 + rs))
    
    def scan_signals(self):
        """扫描交易信号"""
        signals = []
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        print(f"\n{'='*50}")
        print(f"🔍 扫描信号 - {timestamp}")
        print(f"{'='*50}")
        
        for symbol in self.watch_symbols:
            try:
                price = self.get_price(symbol)
                if not price:
                    continue
                
                print(f"\n{symbol}: ${price:.6f if price < 1 else price:.2f}")
                
                closes, volumes = self.get_binance_kline(symbol)
                if not closes:
                    print("  ⚠️ 数据不足")
                    continue
                
                # 技术指标
                rsi = self.calculate_rsi(closes)
                ma20 = sum(closes[-20:]) / 20
                ma50 = sum(closes[-50:]) / 50
                vol_avg = sum(volumes[-20:]) / 20
                vol_ratio = volumes[-1] / vol_avg if vol_avg > 0 else 1
                
                print(f"  RSI: {rsi:.1f}, MA20: ${ma20:.2f}, Vol: {vol_ratio:.2f}x")
                
                # 信号判断
                reasons = []
                confidence = 0
                sig_type = None
                
                # 买入
                if rsi < 35:
                    reasons.append("RSI超卖")
                    confidence += 30
                    sig_type = 'BUY'
                
                if price > ma20 > ma50:
                    reasons.append("多头排列")
                    confidence += 25
                    sig_type = 'BUY'
                
                if vol_ratio > 1.5:
                    reasons.append("放量")
                    confidence += 20
                
                # 卖出
                if rsi > 70:
                    reasons.append("RSI超买")
                    confidence += 30
                    sig_type = 'SELL'
                
                if price < ma20 < ma50:
                    reasons.append("空头排列")
                    confidence += 25
                    sig_type = 'SELL'
                
                if reasons and confidence >= 40:
                    signals.append({
                        'symbol': symbol,
                        'type': sig_type,
                        'price': price,
                        'rsi': rsi,
                        'confidence': confidence,
                        'reasons': reasons
                    })
                    emoji = "📈" if sig_type == 'BUY' else "📉"
                    print(f"  {emoji} {sig_type} 信号 ({confidence}%): {', '.join(reasons)}")
                
                time.sleep(0.5)
            except Exception as e:
                print(f"  ❌ 错误: {e}")
        
        return signals
    
    def send_telegram(self, message):
        """发送Telegram"""
        if not self.telegram_available:
            return
        
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            data = {"chat_id": self.chat_id, "text": message, "parse_mode": "HTML"}
            r = requests.post(url, json=data, timeout=10)
            if r.status_code == 200:
                print("✅ Telegram已发送")
        except:
            pass
    
    def send_signals(self, signals):
        """发送信号通知"""
        if not signals:
            return
        
        buys = [s for s in signals if s['type'] == 'BUY']
        sells = [s for s in signals if s['type'] == 'SELL']
        
        msg = f"""【交易助手】🤖

📊 <b>交易信号</b>

⏰ {datetime.now().strftime('%H:%M:%S')}
📈 买入: {len(buys)} | 📉 卖出: {len(sells)}
━━━━━━━━━━━━━━"""
        
        for s in buys:
            msg += f"\n\n<b>买入 {s['symbol']}</b> 📈\n💰 ${s['price']:.6f if s['price'] < 1 else s['price']:.2f}\n📊 RSI: {s['rsi']:.1f}\n💡 {s['confidence']}%\n📝 {', '.join(s['reasons'])}"
        
        for s in sells:
            msg += f"\n\n<b>卖出 {s['symbol']}</b> 📉\n💰 ${s['price']:.6f if s['price'] < 1 else s['price']:.2f}\n📊 RSI: {s['rsi']:.1f}\n⚠️ {s['confidence']}%\n📝 {', '.join(s['reasons'])}"
        
        self.send_telegram(msg)
    
    def send_position_update(self, price, pnl):
        """发送持仓更新"""
        symbol = self.position['symbol']
        side = "🔴做空" if self.position['side'] == 'SHORT' else "🟢做多"
        roi_emoji = "🟢" if pnl['roi'] >= 0 else "🔴"
        pnl_emoji = "📈" if pnl['pnl'] >= 0 else "📉"
        
        msg = f"""【交易助手】🤖

🎯 {symbol} 持仓 {side}

💰 现价: ${price:.2f}
📈 入场: ${self.position['entry_price']:.2f}
📊 涨跌: {pnl['change']:+.2f}%
💎 杠杆: {self.position['leverage']}x

━━━━━━━━━━━━━━
💵 ROI: {roi_emoji}{pnl['roi']:+.2f}%
💰 盈亏: {pnl_emoji}${pnl['pnl']:+.2f}U

⏰ {datetime.now().strftime('%H:%M:%S')}"""
        
        self.send_telegram(msg)
    
    def calc_pnl(self, price):
        """计算盈亏"""
        entry = self.position['entry_price']
        leverage = self.position['leverage']
        size = self.position['position_size']
        is_short = self.position['side'] == 'SHORT'
        
        change = (entry - price) / entry * 100 if is_short else (price - entry) / entry * 100
        roi = change * leverage
        pnl = (roi / 100) * size
        
        return {'change': change, 'roi': roi, 'pnl': pnl}
    
    def run(self, interval=300):
        """运行监控"""
        print(f"\n🚀 监控启动 ({interval}秒间隔)\n")
        
        try:
            while True:
                self.load_position()
                
                if self.position:
                    # 持仓监控
                    symbol = self.position['symbol']
                    price = self.get_price(symbol)
                    
                    if price:
                        pnl = self.calc_pnl(price)
                        color = "\033[92m" if pnl['pnl'] >= 0 else "\033[91m"
                        print(f"\n📊 {datetime.now().strftime('%H:%M:%S')} {symbol}")
                        print(f"💰 ${price:.2f} | {color}ROI {pnl['roi']:+.2f}% | ${pnl['pnl']:+.2f}U\033[0m")
                        print("-" * 50)
                        
                        self.send_position_update(price, pnl)
                else:
                    # 信号扫描
                    signals = self.scan_signals()
                    if signals:
                        print(f"\n✅ 发现 {len(signals)} 个信号")
                        self.send_signals(signals)
                    else:
                        print("\nℹ️  无强信号")
                
                time.sleep(interval)
        
        except KeyboardInterrupt:
            print("\n👋 监控停止")
            self.send_telegram("【交易助手】⏹️ 监控已停止")

if __name__ == "__main__":
    monitor = TradingMonitor()
    monitor.run(interval=300)
