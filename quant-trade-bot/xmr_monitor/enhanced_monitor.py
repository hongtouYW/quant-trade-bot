#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
【交易助手】🤖 增强版监控系统
监控：XMR, MEMES, AXS, ROSE, XRP, SOL, DUSK
功能：持仓监控 + 智能信号扫描 + 推荐新机会
"""

import requests
import json
import time
import os
from datetime import datetime

class EnhancedMonitor:
    """增强版交易监控"""
    
    def __init__(self):
        self.position = None
        self.telegram_available = self._init_telegram()
        # 监控列表：原有 + 新发现的潜力币
        self.watch_symbols = ['XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK']
        
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
            if os.path.exists(path):
                with open(path, 'r') as f:
                    data = json.load(f)
                    if data.get('status') == 'OPEN':
                        self.position = data
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
        """获取Binance K线数据"""
        symbol_map = {
            'XMR': 'XMRUSDT',
            'MEMES': 'MEMESUSDT',
            'AXS': 'AXSUSDT',
            'ROSE': 'ROSEUSDT',
            'XRP': 'XRPUSDT',
            'SOL': 'SOLUSDT',
            'DUSK': 'DUSKUSDT'
        }
        
        binance_symbol = symbol_map.get(symbol)
        if not binance_symbol:
            return None
        
        try:
            url = "https://api.binance.com/api/v3/klines"
            params = {'symbol': binance_symbol, 'interval': '1h', 'limit': 100}
            r = requests.get(url, params=params, timeout=10)
            if r.status_code == 200:
                return r.json()
        except:
            pass
        return None
    
    def calculate_rsi(self, prices, period=14):
        """计算RSI"""
        if len(prices) < period + 1:
            return 50
        
        deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
        gains = [d if d > 0 else 0 for d in deltas]
        losses = [-d if d < 0 else 0 for d in deltas]
        
        avg_gain = sum(gains[:period]) / period
        avg_loss = sum(losses[:period]) / period
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def scan_signals(self):
        """扫描交易信号 - 智能评分系统"""
        signals = []
        
        for symbol in self.watch_symbols:
            try:
                price = self.get_price(symbol)
                if not price:
                    continue
                
                kline = self.get_binance_kline(symbol)
                if not kline:
                    continue
                
                closes = [float(x[4]) for x in kline]
                volumes = [float(x[5]) for x in kline]
                
                # 计算指标
                rsi = self.calculate_rsi(closes)
                ma7 = sum(closes[-7:]) / 7 if len(closes) >= 7 else price
                ma20 = sum(closes[-20:]) / 20 if len(closes) >= 20 else price
                ma50 = sum(closes[-50:]) / 50 if len(closes) >= 50 else price
                
                avg_volume = sum(volumes[-20:]) / 20 if len(volumes) >= 20 else volumes[-1]
                volume_ratio = volumes[-1] / avg_volume if avg_volume > 0 else 1
                
                # 智能评分系统（0-100分）
                score = 0
                reasons = []
                
                # 1. RSI评分（最高40分）
                if rsi < 30:
                    score += 40
                    reasons.append(f'RSI严重超卖({rsi:.0f})')
                elif rsi < 40:
                    score += 25
                    reasons.append(f'RSI超卖({rsi:.0f})')
                elif rsi > 70:
                    score += 30
                    reasons.append(f'RSI超买({rsi:.0f})')
                
                # 2. 趋势评分（最高25分）
                if ma7 > ma20 > ma50 and price > ma20:
                    score += 25
                    reasons.append('多头排列')
                elif ma7 < ma20 < ma50 and price < ma20:
                    score += 25
                    reasons.append('空头排列')
                elif price > ma20:
                    score += 10
                    reasons.append('价格>MA20')
                
                # 3. 成交量评分（最高20分）
                if volume_ratio > 1.5:
                    score += 20
                    reasons.append(f'放量{volume_ratio:.1f}x')
                elif volume_ratio > 1.2:
                    score += 10
                    reasons.append('成交活跃')
                
                # 4. 价格位置评分（最高15分）
                if price < ma50 * 0.95:
                    score += 15
                    reasons.append('深度回调')
                elif price < ma50:
                    score += 10
                    reasons.append('低于MA50')
                
                # 生成信号（评分≥55才推荐）
                if score >= 55:
                    signal_type = 'BUY'
                    if rsi > 70 or (ma7 < ma20 < ma50 and price < ma20):
                        signal_type = 'SELL'
                    
                    signals.append({
                        'symbol': symbol,
                        'type': signal_type,
                        'price': price,
                        'rsi': rsi,
                        'score': score,
                        'reason': ', '.join(reasons),
                        'ma20': ma20,
                        'ma50': ma50,
                        'volume_ratio': volume_ratio
                    })
                    
            except Exception as e:
                print(f"  ❌ {symbol} 扫描失败: {e}")
        
        return signals
    
    def send_signals(self, signals):
        """发送信号通知"""
        if not signals:
            return
        
        # 按评分排序
        signals.sort(key=lambda x: x['score'], reverse=True)
        
        buys = [s for s in signals if s['type'] == 'BUY']
        sells = [s for s in signals if s['type'] == 'SELL']
        
        msg = f"""【交易助手】🤖

🔍 发现 <b>{len(signals)}</b> 个交易机会

⏰ {datetime.now().strftime('%H:%M:%S')}
📈 买入: {len(buys)} | 📉 卖出: {len(sells)}
━━━━━━━━━━━━━━"""
        
        for i, s in enumerate(signals, 1):
            emoji = "📈" if s['type'] == 'BUY' else "📉"
            stars = "⭐" * min(int(s['score']/20), 5)
            
            # 计算止损止盈
            if s['type'] == 'BUY':
                stop_loss = s['price'] * 0.95
                take_profit = s['price'] * 1.10
                action = "做多"
            else:
                stop_loss = s['price'] * 1.05
                take_profit = s['price'] * 0.90
                action = "做空"
            
            msg += f"""

{i}. {emoji} <b>{s['symbol']}</b> {stars}
   💰 ${s['price']:.6f}
   📊 RSI {s['rsi']:.0f} | 评分 {s['score']:.0f}
   🎯 {action} | 止损 ${stop_loss:.6f} | 止盈 ${take_profit:.6f}
   💡 {s['reason']}"""
        
        self.send_telegram(msg)
    
    def calculate_pnl(self, current_price):
        """计算盈亏"""
        entry = self.position['entry_price']
        leverage = self.position['leverage']
        size = self.position['position_size']
        side = self.position['side']
        
        if side == 'LONG':
            change = ((current_price - entry) / entry) * 100
        else:
            change = ((entry - current_price) / entry) * 100
        
        roi = change * leverage
        pnl = size * (roi / 100)
        
        return {'change': change, 'roi': roi, 'pnl': pnl}
    
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
    
    def send_telegram(self, message):
        """发送Telegram"""
        if not self.telegram_available:
            return
        
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            requests.post(url, data={
                'chat_id': self.chat_id,
                'text': message,
                'parse_mode': 'HTML'
            }, timeout=5)
        except:
            pass
    
    def run(self, interval=300):
        """主循环"""
        print(f"\n🚀 监控启动 (间隔{interval}秒)")
        
        try:
            while True:
                print(f"\n⏰ {datetime.now().strftime('%H:%M:%S')} 检查中...")
                
                if self.position:
                    # 持仓监控模式
                    symbol_short = self.position['symbol'].split('/')[0]
                    price = self.get_price(symbol_short)
                    
                    if price:
                        pnl = self.calculate_pnl(price)
                        print(f"  {symbol_short}: ${price:.2f} | ROI: {pnl['roi']:+.2f}%")
                        self.send_position_update(price, pnl)
                    else:
                        print(f"  ❌ 获取价格失败")
                else:
                    # 信号扫描模式
                    print(f"  扫描 {len(self.watch_symbols)} 个币种...")
                    signals = self.scan_signals()
                    
                    if signals:
                        print(f"\n✅ 发现 {len(signals)} 个信号")
                        self.send_signals(signals)
                    else:
                        print("\nℹ️  无强信号")
                
                time.sleep(interval)
        
        except KeyboardInterrupt:
            print("\n👋 监控停止")
            self.send_telegram("【交易助手】🤖\n\n⏹️ 监控已停止")

if __name__ == "__main__":
    monitor = EnhancedMonitor()
    monitor.run(interval=300)
