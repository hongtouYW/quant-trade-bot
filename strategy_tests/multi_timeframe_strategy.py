# -*- coding: utf-8 -*-
"""
多时间框架量化策略 - 日线趋势 + 15分钟入场
策略逻辑：
- 日线：判断主趋势方向（MA20、MACD、RSI）
- 15分钟：寻找精准入场点（突破、回调、背离）
"""

import ccxt
import pandas as pd
import numpy as np
import json
import time
from datetime import datetime
import talib

class MultiTimeFrameStrategy:
    def __init__(self):
        self.exchange = ccxt.binance({
            'apiKey': '',
            'secret': '',
            'sandbox': True,  # 测试模式
            'enableRateLimit': True,
        })
        
        # 交易配置
        self.symbols = ['BTC/USDT', 'ETH/USDT']
        self.capital = 1000
        self.risk_per_trade = 0.02  # 每笔2%风险
        self.max_leverage = 3
        
        # 时间框架
        self.trend_timeframe = '1d'      # 日线看趋势
        self.entry_timeframe = '15m'     # 15分钟找入场
        
        print("🎯 多时间框架策略初始化")
        print(f"📊 趋势框架: {self.trend_timeframe}")
        print(f"⚡ 入场框架: {self.entry_timeframe}")

    def get_market_data(self, symbol, timeframe, limit=100):
        """获取市场数据"""
        try:
            ohlcv = self.exchange.fetch_ohlcv(symbol, timeframe, limit=limit)
            df = pd.DataFrame(ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
            return df
        except Exception as e:
            print(f"❌ 获取数据失败 {symbol} {timeframe}: {e}")
            return None

    def analyze_daily_trend(self, df_daily):
        """分析日线趋势"""
        if len(df_daily) < 50:
            return {'direction': 'neutral', 'strength': 0}
            
        # 计算技术指标
        df_daily['ma20'] = talib.MA(df_daily['close'], timeperiod=20)
        df_daily['ma50'] = talib.MA(df_daily['close'], timeperiod=50)
        df_daily['rsi'] = talib.RSI(df_daily['close'], timeperiod=14)
        
        # MACD
        df_daily['macd'], df_daily['macdsignal'], df_daily['macdhist'] = talib.MACD(df_daily['close'])
        
        # 趋势判断逻辑
        latest = df_daily.iloc[-1]
        prev = df_daily.iloc[-2]
        
        signals = []
        
        # 1. 均线趋势
        if latest['close'] > latest['ma20'] > latest['ma50']:
            signals.append(1)  # 上升趋势
        elif latest['close'] < latest['ma20'] < latest['ma50']:
            signals.append(-1)  # 下降趋势
        else:
            signals.append(0)  # 震荡
            
        # 2. MACD趋势
        if latest['macd'] > latest['macdsignal'] and latest['macdhist'] > 0:
            signals.append(1)
        elif latest['macd'] < latest['macdsignal'] and latest['macdhist'] < 0:
            signals.append(-1)
        else:
            signals.append(0)
            
        # 3. RSI过滤
        if 30 < latest['rsi'] < 70:
            rsi_signal = 0  # 中性区间
        elif latest['rsi'] > 70:
            rsi_signal = -0.5  # 超买，减弱多头信号
        elif latest['rsi'] < 30:
            rsi_signal = 0.5   # 超卖，增强多头信号
            
        # 综合判断
        trend_score = sum(signals)
        
        if trend_score >= 2:
            direction = 'bullish'
            strength = min(0.8, (trend_score + rsi_signal) / 3)
        elif trend_score <= -2:
            direction = 'bearish'
            strength = min(0.8, abs(trend_score + rsi_signal) / 3)
        else:
            direction = 'neutral'
            strength = 0
            
        return {
            'direction': direction,
            'strength': strength,
            'ma_trend': latest['close'] > latest['ma20'],
            'macd_bullish': latest['macd'] > latest['macdsignal'],
            'rsi': latest['rsi'],
            'price': latest['close']
        }

    def find_entry_signals(self, df_15m, trend_direction):
        """在15分钟线寻找入场信号"""
        if len(df_15m) < 30 or trend_direction == 'neutral':
            return {'signal': 'hold', 'confidence': 0}
            
        # 计算15分钟技术指标
        df_15m['ema12'] = talib.EMA(df_15m['close'], timeperiod=12)
        df_15m['ema26'] = talib.EMA(df_15m['close'], timeperiod=26)
        df_15m['rsi'] = talib.RSI(df_15m['close'], timeperiod=14)
        df_15m['bb_upper'], df_15m['bb_middle'], df_15m['bb_lower'] = talib.BBANDS(df_15m['close'])
        
        latest = df_15m.iloc[-1]
        prev = df_15m.iloc[-2]
        
        signals = []
        confidence = 0
        
        if trend_direction == 'bullish':
            # 多头入场信号
            
            # 1. EMA金叉
            if (latest['ema12'] > latest['ema26'] and 
                prev['ema12'] <= prev['ema26']):
                signals.append('buy')
                confidence += 0.3
                
            # 2. 回调到支撑位
            if (latest['close'] <= df_15m['bb_middle'].iloc[-1] and 
                latest['close'] > df_15m['bb_lower'].iloc[-1]):
                signals.append('buy_dip')
                confidence += 0.2
                
            # 3. RSI从超卖反弹
            if latest['rsi'] > 35 and prev['rsi'] <= 30:
                signals.append('buy_oversold')
                confidence += 0.25
                
            # 4. 突破阻力
            resistance = df_15m['high'].rolling(10).max().iloc[-2]
            if latest['close'] > resistance:
                signals.append('breakout_buy')
                confidence += 0.35
                
        elif trend_direction == 'bearish':
            # 空头入场信号
            
            # 1. EMA死叉
            if (latest['ema12'] < latest['ema26'] and 
                prev['ema12'] >= prev['ema26']):
                signals.append('sell')
                confidence += 0.3
                
            # 2. 反弹到阻力位
            if (latest['close'] >= df_15m['bb_middle'].iloc[-1] and 
                latest['close'] < df_15m['bb_upper'].iloc[-1]):
                signals.append('sell_rally')
                confidence += 0.2
                
            # 3. RSI从超买回落
            if latest['rsi'] < 65 and prev['rsi'] >= 70:
                signals.append('sell_overbought')
                confidence += 0.25
                
            # 4. 跌破支撑
            support = df_15m['low'].rolling(10).min().iloc[-2]
            if latest['close'] < support:
                signals.append('breakout_sell')
                confidence += 0.35
        
        # 确定最终信号
        if signals and confidence > 0.4:
            if any('buy' in s for s in signals):
                return {
                    'signal': 'buy',
                    'confidence': min(confidence, 0.9),
                    'price': latest['close'],
                    'signals': signals,
                    'rsi': latest['rsi']
                }
            elif any('sell' in s for s in signals):
                return {
                    'signal': 'sell', 
                    'confidence': min(confidence, 0.9),
                    'price': latest['close'],
                    'signals': signals,
                    'rsi': latest['rsi']
                }
        
        return {'signal': 'hold', 'confidence': 0}

    def calculate_position_size(self, price, confidence):
        """根据信心度计算仓位大小"""
        base_risk = self.capital * self.risk_per_trade
        position_size = base_risk * confidence / price
        
        # 根据信心度调整杠杆
        leverage = int(1 + (confidence * (self.max_leverage - 1)))
        
        return {
            'size': position_size,
            'leverage': leverage,
            'value': position_size * price * leverage
        }

    def analyze_symbol(self, symbol):
        """分析单个交易对"""
        print(f"\n📈 分析 {symbol}")
        
        # 获取日线数据
        df_daily = self.get_market_data(symbol, self.trend_timeframe, 100)
        if df_daily is None:
            return None
            
        # 获取15分钟数据 
        df_15m = self.get_market_data(symbol, self.entry_timeframe, 200)
        if df_15m is None:
            return None
            
        # 分析日线趋势
        trend = self.analyze_daily_trend(df_daily)
        print(f"📊 日线趋势: {trend['direction']} (强度: {trend['strength']:.2f})")
        
        # 在15分钟线寻找入场点
        entry = self.find_entry_signals(df_15m, trend['direction'])
        
        if entry['signal'] != 'hold':
            position = self.calculate_position_size(entry['price'], entry['confidence'])
            print(f"⚡ 15分钟信号: {entry['signal']} (信心: {entry['confidence']:.2f})")
            print(f"💰 建议仓位: {position['size']:.4f} {symbol.split('/')[0]}")
            print(f"🔧 杠杆: {position['leverage']}x")
            
            return {
                'symbol': symbol,
                'trend': trend,
                'entry': entry,
                'position': position,
                'timestamp': datetime.now()
            }
        else:
            print(f"⏸️  暂无入场信号")
            return None

    def run_analysis(self):
        """运行完整分析"""
        print("🚀 启动多时间框架分析")
        print("=" * 50)
        
        results = []
        
        for symbol in self.symbols:
            try:
                analysis = self.analyze_symbol(symbol)
                if analysis:
                    results.append(analysis)
            except Exception as e:
                print(f"❌ 分析 {symbol} 出错: {e}")
                continue
                
        # 保存结果
        if results:
            with open('multi_timeframe_signals.json', 'w', encoding='utf-8') as f:
                json.dump(results, f, ensure_ascii=False, indent=2, default=str)
            print(f"\n✅ 发现 {len(results)} 个交易机会")
        else:
            print("\n⏸️  当前无交易机会")
            
        return results

    def run_continuous(self, interval=900):  # 15分钟检查一次
        """持续运行策略"""
        print(f"🔄 开始持续监控 (间隔: {interval//60} 分钟)")
        
        while True:
            try:
                results = self.run_analysis()
                
                print(f"\n⏰ {datetime.now().strftime('%H:%M:%S')} 分析完成")
                print(f"⏳ {interval//60} 分钟后下次检查...")
                time.sleep(interval)
                
            except KeyboardInterrupt:
                print("\n👋 策略停止")
                break
            except Exception as e:
                print(f"❌ 运行错误: {e}")
                time.sleep(60)

if __name__ == "__main__":
    strategy = MultiTimeFrameStrategy()
    
    # 单次分析
    results = strategy.run_analysis()
    
    # 询问是否持续运行
    if results:
        choice = input("\n🤔 是否开启持续监控? (y/n): ")
        if choice.lower() == 'y':
            strategy.run_continuous()