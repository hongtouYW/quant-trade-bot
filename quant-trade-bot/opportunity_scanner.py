#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
【交易助手】🤖 机会扫描器
扫描Binance期货市场，找出高潜力币种
"""

import ccxt
import requests
import json
import time
from datetime import datetime

class OpportunityScanner:
    """全市场机会扫描器"""
    
    def __init__(self):
        # 读取配置
        with open('config/config.json', 'r') as f:
            config = json.load(f)
        
        # 初始化交易所
        self.exchange = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })
        
        # Telegram配置
        self.bot_token = config['telegram']['bot_token']
        self.chat_id = config['telegram']['chat_id']
        
        print("✅ 机会扫描器初始化完成")
    
    def send_telegram(self, message):
        """发送Telegram"""
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            requests.post(url, data={
                'chat_id': self.chat_id,
                'text': f"【交易助手】🤖\n\n{message}",
                'parse_mode': 'HTML'
            }, timeout=5)
        except Exception as e:
            print(f"⚠️ Telegram发送失败: {e}")
    
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
    
    def analyze_coin(self, symbol):
        """分析单个币种"""
        try:
            # 获取24h ticker
            ticker = self.exchange.fetch_ticker(symbol)
            
            # 获取K线数据
            ohlcv_1h = self.exchange.fetch_ohlcv(symbol, '1h', limit=100)
            ohlcv_15m = self.exchange.fetch_ohlcv(symbol, '15m', limit=100)
            
            if not ohlcv_1h or len(ohlcv_1h) < 50:
                return None
            
            # 提取价格
            closes_1h = [x[4] for x in ohlcv_1h]
            closes_15m = [x[4] for x in ohlcv_15m]
            volumes = [x[5] for x in ohlcv_1h]
            
            current_price = ticker['last']
            volume_24h = ticker['quoteVolume']  # USDT成交量
            
            # 计算指标
            rsi_1h = self.calculate_rsi(closes_1h)
            rsi_15m = self.calculate_rsi(closes_15m)
            
            # 均线
            ma20 = sum(closes_1h[-20:]) / 20
            ma50 = sum(closes_1h[-50:]) / 50
            
            # 波动率（24h涨跌幅）
            change_24h = ticker['percentage']
            
            # 成交量分析
            avg_volume = sum(volumes[-24:]) / 24
            volume_ratio = volumes[-1] / avg_volume if avg_volume > 0 else 0
            
            # 趋势判断
            trend = "上涨" if ma20 > ma50 and current_price > ma20 else \
                    "下跌" if ma20 < ma50 and current_price < ma20 else "震荡"
            
            return {
                'symbol': symbol,
                'price': current_price,
                'change_24h': change_24h,
                'volume_24h': volume_24h,
                'rsi_1h': rsi_1h,
                'rsi_15m': rsi_15m,
                'ma20': ma20,
                'ma50': ma50,
                'volume_ratio': volume_ratio,
                'trend': trend
            }
            
        except Exception as e:
            print(f"  ❌ {symbol}: {e}")
            return None
    
    def calculate_score(self, data):
        """计算潜力评分（0-100）"""
        score = 0
        reasons = []
        
        # 1. RSI超卖机会（30分）
        if data['rsi_1h'] < 30:
            score += 30
            reasons.append(f"RSI严重超卖({data['rsi_1h']:.1f})")
        elif data['rsi_1h'] < 40:
            score += 20
            reasons.append(f"RSI偏低({data['rsi_1h']:.1f})")
        elif 40 <= data['rsi_1h'] <= 60:
            score += 10
            reasons.append("RSI中性区")
        
        # 2. 成交量（25分）- 高成交量=高关注度
        if data['volume_24h'] > 1000000000:  # >10亿
            score += 25
            reasons.append(f"超高成交量({data['volume_24h']/1e9:.1f}B)")
        elif data['volume_24h'] > 500000000:  # >5亿
            score += 20
            reasons.append(f"高成交量({data['volume_24h']/1e6:.0f}M)")
        elif data['volume_24h'] > 100000000:  # >1亿
            score += 10
            reasons.append(f"中等成交量({data['volume_24h']/1e6:.0f}M)")
        
        # 3. 波动率（20分）- 有波动才有机会
        abs_change = abs(data['change_24h'])
        if 5 <= abs_change <= 15:
            score += 20
            reasons.append(f"理想波动({data['change_24h']:+.1f}%)")
        elif 3 <= abs_change < 5:
            score += 15
            reasons.append(f"适度波动({data['change_24h']:+.1f}%)")
        elif abs_change < 2:
            score += 5
            reasons.append("波动较小")
        
        # 4. 趋势（15分）
        if data['trend'] == "上涨":
            score += 15
            reasons.append("趋势向上")
        elif data['trend'] == "震荡":
            score += 10
            reasons.append("横盘整理")
        
        # 5. 成交量放大（10分）
        if data['volume_ratio'] > 1.5:
            score += 10
            reasons.append(f"成交量激增({data['volume_ratio']:.1f}x)")
        elif data['volume_ratio'] > 1.2:
            score += 5
            reasons.append("成交量放大")
        
        return score, reasons
    
    def scan_market(self, min_score=50, top_n=15):
        """扫描市场"""
        print("=" * 70)
        print("【交易助手】🤖 全市场机会扫描")
        print("=" * 70)
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"🎯 目标：找出高潜力币种（评分≥{min_score}）")
        print("=" * 70)
        
        # 获取所有USDT永续合约
        print("\n📡 获取市场数据...")
        markets = self.exchange.load_markets()
        usdt_futures = [
            symbol for symbol in markets.keys()
            if symbol.endswith('/USDT:USDT') and markets[symbol]['active']
        ]
        
        print(f"✅ 找到 {len(usdt_futures)} 个USDT永续合约")
        print(f"\n🔍 开始分析（预计需要 {len(usdt_futures) * 0.5:.0f} 秒）...")
        
        opportunities = []
        analyzed = 0
        
        for symbol in usdt_futures[:100]:  # 限制前100个，避免API限流
            analyzed += 1
            if analyzed % 10 == 0:
                print(f"  进度: {analyzed}/{min(100, len(usdt_futures))}")
            
            data = self.analyze_coin(symbol)
            if data:
                score, reasons = self.calculate_score(data)
                data['score'] = score
                data['reasons'] = reasons
                
                if score >= min_score:
                    opportunities.append(data)
            
            time.sleep(0.1)  # 避免API限流
        
        # 按评分排序
        opportunities.sort(key=lambda x: x['score'], reverse=True)
        
        print("\n" + "=" * 70)
        print(f"📊 扫描完成：发现 {len(opportunities)} 个机会")
        print("=" * 70)
        
        if not opportunities:
            print("\n😔 暂未发现高潜力币种")
            print("建议：降低评分阈值或等待市场波动")
            return []
        
        # 显示Top N
        display_list = opportunities[:top_n]
        
        print(f"\n🏆 Top {len(display_list)} 高潜力币种：\n")
        
        telegram_msg = f"""🔍 <b>市场扫描结果</b>

⏰ {datetime.now().strftime('%H:%M:%S')}
📊 发现 <b>{len(opportunities)}</b> 个机会

━━━━━━━━━━━━━━
🏆 <b>Top {len(display_list)}</b>
"""
        
        for i, opp in enumerate(display_list, 1):
            symbol_clean = opp['symbol'].replace('/USDT:USDT', '')
            
            print(f"{i:2d}. {symbol_clean:8s} | 评分: {opp['score']:3.0f}")
            print(f"     价格: ${opp['price']:.6f} | 24h: {opp['change_24h']:+.2f}%")
            print(f"     成交: {opp['volume_24h']/1e6:.0f}M | RSI: {opp['rsi_1h']:.0f}")
            print(f"     趋势: {opp['trend']} | 理由: {', '.join(opp['reasons'][:2])}")
            print()
            
            # 止损止盈建议
            if opp['trend'] in ['上涨', '震荡'] and opp['rsi_1h'] < 60:
                direction = "做多"
                stop_loss = opp['price'] * 0.95
                take_profit = opp['price'] * 1.10
            else:
                direction = "做空"
                stop_loss = opp['price'] * 1.05
                take_profit = opp['price'] * 0.90
            
            telegram_msg += f"""
{i}. <b>{symbol_clean}</b> ⭐{opp['score']:.0f}
   💰 ${opp['price']:.4f} ({opp['change_24h']:+.1f}%)
   📊 {opp['volume_24h']/1e6:.0f}M | RSI {opp['rsi_1h']:.0f}
   🎯 {direction} | 止损 ${stop_loss:.4f}
"""
        
        # 发送Telegram
        print("\n📱 发送Telegram通知...")
        self.send_telegram(telegram_msg)
        
        print("\n" + "=" * 70)
        print("💡 提示：")
        print("   - 高评分 = 高成交量 + RSI超卖 + 适度波动")
        print("   - 建议选择评分≥70的币种")
        print("   - 分批建仓，严格止损")
        print("   - 目标：用3-5次交易赚回3400U")
        print("=" * 70)
        
        return opportunities

if __name__ == "__main__":
    try:
        scanner = OpportunityScanner()
        
        print("\n🎯 目标：找回3400U亏损")
        print("策略：扫描高潜力币种，捕捉反弹机会\n")
        
        opportunities = scanner.scan_market(min_score=50, top_n=15)
        
        if opportunities:
            print(f"\n✅ 已发送Top {min(15, len(opportunities))} 个机会到Telegram")
        
    except KeyboardInterrupt:
        print("\n\n👋 扫描已停止")
    except Exception as e:
        print(f"\n❌ 扫描失败: {e}")
        import traceback
        traceback.print_exc()
