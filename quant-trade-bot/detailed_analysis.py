#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
【交易助手】🤖 详细技术指标分析
针对高潜力币种做深度分析
"""

import ccxt
import json
from datetime import datetime

class DetailedAnalyzer:
    """详细技术分析器"""
    
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
        
        print("✅ 详细分析器初始化完成\n")
    
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
    
    def calculate_macd(self, prices):
        """计算MACD"""
        if len(prices) < 26:
            return 0, 0, 0
        
        # 简化版EMA
        def ema(data, period):
            multiplier = 2 / (period + 1)
            ema_val = data[0]
            for price in data[1:]:
                ema_val = (price - ema_val) * multiplier + ema_val
            return ema_val
        
        ema12 = ema(prices[-26:], 12)
        ema26 = ema(prices[-26:], 26)
        macd = ema12 - ema26
        
        # Signal line (9-day EMA of MACD)
        signal = macd * 0.9  # 简化
        histogram = macd - signal
        
        return macd, signal, histogram
    
    def find_support_resistance(self, highs, lows, current_price):
        """寻找支撑位和阻力位"""
        # 最近20根K线的支撑/阻力
        recent_highs = highs[-20:]
        recent_lows = lows[-20:]
        
        # 阻力位：当前价上方的近期高点
        resistance = [h for h in recent_highs if h > current_price]
        resistance_level = min(resistance) if resistance else current_price * 1.05
        
        # 支撑位：当前价下方的近期低点
        support = [l for l in recent_lows if l < current_price]
        support_level = max(support) if support else current_price * 0.95
        
        return support_level, resistance_level
    
    def analyze_symbol_detailed(self, symbol):
        """详细分析单个币种"""
        try:
            print("=" * 80)
            print(f"【{symbol.replace('/USDT:USDT', '')}】详细技术分析")
            print("=" * 80)
            
            # 获取多周期数据
            ticker = self.exchange.fetch_ticker(symbol)
            ohlcv_15m = self.exchange.fetch_ohlcv(symbol, '15m', limit=100)
            ohlcv_1h = self.exchange.fetch_ohlcv(symbol, '1h', limit=100)
            ohlcv_4h = self.exchange.fetch_ohlcv(symbol, '4h', limit=100)
            ohlcv_1d = self.exchange.fetch_ohlcv(symbol, '1d', limit=50)
            
            # 提取数据
            closes_15m = [x[4] for x in ohlcv_15m]
            closes_1h = [x[4] for x in ohlcv_1h]
            closes_4h = [x[4] for x in ohlcv_4h]
            closes_1d = [x[4] for x in ohlcv_1d]
            
            highs_1h = [x[2] for x in ohlcv_1h]
            lows_1h = [x[3] for x in ohlcv_1h]
            volumes_1h = [x[5] for x in ohlcv_1h]
            
            current_price = ticker['last']
            
            print(f"\n📊 基本信息")
            print(f"   当前价格: ${current_price:.6f}")
            print(f"   24h涨跌: {ticker['percentage']:+.2f}%")
            print(f"   24h成交: ${ticker['quoteVolume']/1e6:.1f}M")
            print(f"   24h最高: ${ticker['high']:.6f}")
            print(f"   24h最低: ${ticker['low']:.6f}")
            
            # RSI 多周期
            print(f"\n📈 RSI 指标（超卖<30，超买>70）")
            rsi_15m = self.calculate_rsi(closes_15m)
            rsi_1h = self.calculate_rsi(closes_1h)
            rsi_4h = self.calculate_rsi(closes_4h)
            rsi_1d = self.calculate_rsi(closes_1d)
            
            print(f"   15分钟: {rsi_15m:.1f} {'🔴超卖' if rsi_15m < 30 else '🟢超买' if rsi_15m > 70 else '⚪中性'}")
            print(f"   1小时:  {rsi_1h:.1f} {'🔴超卖' if rsi_1h < 30 else '🟢超买' if rsi_1h > 70 else '⚪中性'}")
            print(f"   4小时:  {rsi_4h:.1f} {'🔴超卖' if rsi_4h < 30 else '🟢超买' if rsi_4h > 70 else '⚪中性'}")
            print(f"   日线:   {rsi_1d:.1f} {'🔴超卖' if rsi_1d < 30 else '🟢超买' if rsi_1d > 70 else '⚪中性'}")
            
            # 均线
            print(f"\n📉 移动均线")
            ma7 = sum(closes_1h[-7:]) / 7
            ma20 = sum(closes_1h[-20:]) / 20
            ma50 = sum(closes_1h[-50:]) / 50
            
            print(f"   MA7:  ${ma7:.6f} {'✅上方' if current_price > ma7 else '❌下方'}")
            print(f"   MA20: ${ma20:.6f} {'✅上方' if current_price > ma20 else '❌下方'}")
            print(f"   MA50: ${ma50:.6f} {'✅上方' if current_price > ma50 else '❌下方'}")
            
            if ma7 > ma20 > ma50:
                print(f"   趋势: 🚀 多头排列（看涨）")
            elif ma7 < ma20 < ma50:
                print(f"   趋势: 📉 空头排列（看跌）")
            else:
                print(f"   趋势: ↔️ 震荡整理")
            
            # MACD
            print(f"\n📊 MACD 指标")
            macd, signal, histogram = self.calculate_macd(closes_1h)
            print(f"   MACD线:   {macd:.6f}")
            print(f"   信号线:   {signal:.6f}")
            print(f"   柱状图:   {histogram:.6f} {'🟢金叉' if histogram > 0 else '🔴死叉'}")
            
            if macd > signal and macd > 0:
                print(f"   信号: 🚀 强烈看涨")
            elif macd > signal:
                print(f"   信号: ✅ 看涨")
            elif macd < signal and macd < 0:
                print(f"   信号: 📉 强烈看跌")
            else:
                print(f"   信号: ❌ 看跌")
            
            # 支撑/阻力
            print(f"\n🎯 关键价位")
            support, resistance = self.find_support_resistance(highs_1h, lows_1h, current_price)
            
            support_pct = ((current_price - support) / current_price) * 100
            resistance_pct = ((resistance - current_price) / current_price) * 100
            
            print(f"   支撑位: ${support:.6f} (-{support_pct:.2f}%)")
            print(f"   阻力位: ${resistance:.6f} (+{resistance_pct:.2f}%)")
            
            # 成交量分析
            print(f"\n💹 成交量分析")
            avg_volume_20 = sum(volumes_1h[-20:]) / 20
            current_volume = volumes_1h[-1]
            volume_ratio = current_volume / avg_volume_20 if avg_volume_20 > 0 else 0
            
            print(f"   当前成交: {current_volume/1e6:.2f}M")
            print(f"   20h均量: {avg_volume_20/1e6:.2f}M")
            print(f"   成交倍数: {volume_ratio:.2f}x {'🔥放量' if volume_ratio > 1.5 else '✅正常' if volume_ratio > 0.8 else '⚠️缩量'}")
            
            # 波动率
            print(f"\n📊 波动率")
            high_24h = ticker['high']
            low_24h = ticker['low']
            volatility = ((high_24h - low_24h) / low_24h) * 100
            
            print(f"   24h波动: {volatility:.2f}%")
            print(f"   风险等级: {'🔥高' if volatility > 10 else '⚠️中' if volatility > 5 else '✅低'}")
            
            # 综合建议
            print(f"\n🎯 交易建议")
            
            signals = []
            confidence = 0
            
            # 做多信号
            if rsi_1h < 40 and macd > signal:
                signals.append("RSI超卖+MACD金叉")
                confidence += 30
            if current_price > ma20 and ma20 > ma50:
                signals.append("多头排列")
                confidence += 25
            if volume_ratio > 1.5 and ticker['percentage'] > 0:
                signals.append("放量上涨")
                confidence += 20
            if rsi_15m < 30:
                signals.append("15分钟RSI严重超卖")
                confidence += 25
            
            # 做空信号
            if rsi_1h > 70 and macd < signal:
                signals.append("RSI超买+MACD死叉（做空）")
                confidence += 30
            if current_price < ma20 and ma20 < ma50:
                signals.append("空头排列（做空）")
                confidence += 25
            
            if signals:
                print(f"   信号强度: {confidence}%")
                print(f"   触发条件: {', '.join(signals)}")
                
                if confidence >= 50:
                    direction = "做多" if "做空" not in str(signals) else "做空"
                    
                    if direction == "做多":
                        entry_price = current_price
                        stop_loss = support * 0.98
                        take_profit = resistance * 0.95
                        
                        print(f"\n   💰 做多建议:")
                        print(f"      入场价: ${entry_price:.6f}")
                        print(f"      止损价: ${stop_loss:.6f} ({((stop_loss-entry_price)/entry_price*100):.2f}%)")
                        print(f"      止盈价: ${take_profit:.6f} ({((take_profit-entry_price)/entry_price*100):.2f}%)")
                        print(f"      建议杠杆: 5-10x")
                        print(f"      建议仓位: 30-50%")
                    else:
                        entry_price = current_price
                        stop_loss = resistance * 1.02
                        take_profit = support * 1.05
                        
                        print(f"\n   📉 做空建议:")
                        print(f"      入场价: ${entry_price:.6f}")
                        print(f"      止损价: ${stop_loss:.6f} ({((stop_loss-entry_price)/entry_price*100):.2f}%)")
                        print(f"      止盈价: ${take_profit:.6f} ({((take_profit-entry_price)/entry_price*100):.2f}%)")
                        print(f"      建议杠杆: 5-10x")
                        print(f"      建议仓位: 20-40%")
                else:
                    print(f"\n   ⚠️ 信号强度不足，建议观望")
            else:
                print(f"   暂无明确信号，建议观望")
            
            print("\n" + "=" * 80 + "\n")
            
        except Exception as e:
            print(f"❌ 分析失败: {e}\n")

if __name__ == "__main__":
    try:
        analyzer = DetailedAnalyzer()
        
        # 分析Top潜力币种
        symbols = [
            'ROSE/USDT:USDT',   # 评分75
            'XRP/USDT:USDT',    # 评分60
            'DUSK/USDT:USDT',   # 评分60
            'SOL/USDT:USDT',    # 评分50
            'AXS/USDT:USDT',    # 评分50
        ]
        
        print("🔍 开始详细技术分析...")
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
        
        for symbol in symbols:
            analyzer.analyze_symbol_detailed(symbol)
        
        print("✅ 分析完成！")
        print("\n💡 提示：")
        print("   - 信号强度≥50%才考虑入场")
        print("   - 严格执行止损止盈")
        print("   - 建议分批建仓，不要满仓")
        print("   - RSI超卖+MACD金叉是最强信号")
        
    except KeyboardInterrupt:
        print("\n\n👋 分析已停止")
    except Exception as e:
        print(f"\n❌ 分析失败: {e}")
        import traceback
        traceback.print_exc()
