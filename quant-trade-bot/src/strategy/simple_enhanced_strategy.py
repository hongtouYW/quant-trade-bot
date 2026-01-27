#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
简化版增强策略 - 不依赖talib
使用pandas计算技术指标
"""

import ccxt
import pandas as pd
import numpy as np
from datetime import datetime
import time


class SimpleEnhancedStrategy:
    """简化版增强策略"""
    
    def __init__(self, exchange):
        self.exchange = exchange
        
        # 策略参数
        self.timeframes = {
            'trend': '1d',      # 日线看趋势
            'entry': '15m'      # 15分钟找入场
        }
        
        # 技术指标参数
        self.rsi_period = 14
        self.rsi_oversold = 30
        self.rsi_overbought = 70
        
        self.ema_fast = 20
        self.ema_slow = 50
        
        self.bb_period = 20
        self.bb_std = 2
        
        # 成交量阈值
        self.volume_threshold = 1.5
        
    def fetch_ohlcv(self, symbol, timeframe, limit=100):
        """获取K线数据"""
        try:
            ohlcv = self.exchange.fetch_ohlcv(symbol, timeframe, limit=limit)
            df = pd.DataFrame(ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
            return df
        except Exception as e:
            print(f"❌ 获取数据失败 {symbol} {timeframe}: {e}")
            return None
    
    def calculate_rsi(self, series, period=14):
        """计算RSI"""
        delta = series.diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
        rs = gain / loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def calculate_indicators(self, df):
        """计算技术指标"""
        # RSI
        df['rsi'] = self.calculate_rsi(df['close'], self.rsi_period)
        
        # EMA
        df['ema_fast'] = df['close'].ewm(span=self.ema_fast, adjust=False).mean()
        df['ema_slow'] = df['close'].ewm(span=self.ema_slow, adjust=False).mean()
        
        # MACD (简化版)
        ema12 = df['close'].ewm(span=12, adjust=False).mean()
        ema26 = df['close'].ewm(span=26, adjust=False).mean()
        df['macd'] = ema12 - ema26
        df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
        df['macd_hist'] = df['macd'] - df['macd_signal']
        
        # Bollinger Bands
        df['bb_middle'] = df['close'].rolling(window=self.bb_period).mean()
        bb_std = df['close'].rolling(window=self.bb_period).std()
        df['bb_upper'] = df['bb_middle'] + (bb_std * self.bb_std)
        df['bb_lower'] = df['bb_middle'] - (bb_std * self.bb_std)
        
        # 成交量均线
        df['volume_sma'] = df['volume'].rolling(window=20).mean()
        
        return df
    
    def identify_trend(self, df):
        """识别趋势"""
        latest = df.iloc[-1]
        
        # EMA趋势
        if latest['ema_fast'] > latest['ema_slow']:
            ema_trend = 'bullish'
        elif latest['ema_fast'] < latest['ema_slow']:
            ema_trend = 'bearish'
        else:
            ema_trend = 'neutral'
        
        # MACD趋势
        if latest['macd'] > latest['macd_signal'] and latest['macd_hist'] > 0:
            macd_trend = 'bullish'
        elif latest['macd'] < latest['macd_signal'] and latest['macd_hist'] < 0:
            macd_trend = 'bearish'
        else:
            macd_trend = 'neutral'
        
        # 综合判断
        if ema_trend == 'bullish' and macd_trend == 'bullish':
            return 'strong_bullish'
        elif ema_trend == 'bullish' or macd_trend == 'bullish':
            return 'bullish'
        elif ema_trend == 'bearish' and macd_trend == 'bearish':
            return 'strong_bearish'
        elif ema_trend == 'bearish' or macd_trend == 'bearish':
            return 'bearish'
        else:
            return 'neutral'
    
    def find_entry_signal(self, df_trend, df_entry):
        """寻找入场信号"""
        trend = self.identify_trend(df_trend)
        
        if trend == 'neutral':
            return None
        
        latest = df_entry.iloc[-1]
        prev = df_entry.iloc[-2]
        
        signal = None
        
        # 多头信号
        if 'bullish' in trend:
            conditions = []
            
            # RSI从超卖反弹
            if latest['rsi'] > self.rsi_oversold and prev['rsi'] <= self.rsi_oversold:
                conditions.append("RSI超卖反弹")
            
            # MACD金叉
            if latest['macd'] > latest['macd_signal'] and prev['macd'] <= prev['macd_signal']:
                conditions.append("MACD金叉")
            
            # 价格触及布林带下轨反弹
            if latest['close'] > latest['bb_lower'] and prev['close'] <= prev['bb_lower']:
                conditions.append("布林带下轨反弹")
            
            # 价格突破EMA
            if latest['close'] > latest['ema_fast'] and prev['close'] <= prev['ema_fast']:
                conditions.append("突破快线EMA")
            
            # 成交量放大
            volume_surge = latest['volume'] > latest['volume_sma'] * self.volume_threshold
            
            # 至少2个条件 + 成交量
            if len(conditions) >= 2 and volume_surge:
                signal = {
                    'type': 'buy',
                    'price': latest['close'],
                    'conditions': conditions,
                    'strength': 'strong' if trend == 'strong_bullish' else 'normal',
                    'stop_loss': latest['bb_lower'],
                    'take_profit': latest['bb_upper']
                }
        
        # 空头信号
        elif 'bearish' in trend:
            conditions = []
            
            # RSI从超买回落
            if latest['rsi'] < self.rsi_overbought and prev['rsi'] >= self.rsi_overbought:
                conditions.append("RSI超买回落")
            
            # MACD死叉
            if latest['macd'] < latest['macd_signal'] and prev['macd'] >= prev['macd_signal']:
                conditions.append("MACD死叉")
            
            # 价格触及布林带上轨回落
            if latest['close'] < latest['bb_upper'] and prev['close'] >= prev['bb_upper']:
                conditions.append("布林带上轨回落")
            
            # 价格跌破EMA
            if latest['close'] < latest['ema_fast'] and prev['close'] >= prev['ema_fast']:
                conditions.append("跌破快线EMA")
            
            # 成交量放大
            volume_surge = latest['volume'] > latest['volume_sma'] * self.volume_threshold
            
            # 至少2个条件 + 成交量
            if len(conditions) >= 2 and volume_surge:
                signal = {
                    'type': 'sell',
                    'price': latest['close'],
                    'conditions': conditions,
                    'strength': 'strong' if trend == 'strong_bearish' else 'normal',
                    'stop_loss': latest['bb_upper'],
                    'take_profit': latest['bb_lower']
                }
        
        return signal
    
    def analyze_symbol(self, symbol):
        """分析单个交易对"""
        print(f"\n{'='*60}")
        print(f"📊 分析 {symbol}")
        print(f"{'='*60}")
        
        # 获取日线数据
        df_trend = self.fetch_ohlcv(symbol, self.timeframes['trend'], limit=100)
        if df_trend is None:
            return None
        
        df_trend = self.calculate_indicators(df_trend)
        trend = self.identify_trend(df_trend)
        
        print(f"📈 日线趋势: {trend}")
        
        # 获取15分钟数据
        df_entry = self.fetch_ohlcv(symbol, self.timeframes['entry'], limit=100)
        if df_entry is None:
            return None
        
        df_entry = self.calculate_indicators(df_entry)
        
        # 查找信号
        signal = self.find_entry_signal(df_trend, df_entry)
        
        if signal:
            print(f"\n🎯 发现{signal['type'].upper()}信号 ({signal['strength']})")
            print(f"   价格: ${signal['price']:.2f}")
            print(f"   条件: {', '.join(signal['conditions'])}")
            print(f"   止损: ${signal['stop_loss']:.2f}")
            print(f"   止盈: ${signal['take_profit']:.2f}")
            
            # 风险收益比
            if signal['type'] == 'buy':
                risk = signal['price'] - signal['stop_loss']
                reward = signal['take_profit'] - signal['price']
            else:
                risk = signal['stop_loss'] - signal['price']
                reward = signal['price'] - signal['take_profit']
            
            rr_ratio = reward / risk if risk > 0 else 0
            print(f"   风险收益比: 1:{rr_ratio:.2f}")
        else:
            print("\n⏳ 暂无交易信号")
        
        # 显示技术指标
        latest = df_entry.iloc[-1]
        print(f"\n📊 技术指标 (15分钟):")
        print(f"   RSI: {latest['rsi']:.2f}")
        print(f"   MACD: {latest['macd']:.2f} / Signal: {latest['macd_signal']:.2f}")
        print(f"   价格: ${latest['close']:.2f}")
        print(f"   BB上: ${latest['bb_upper']:.2f} / 中: ${latest['bb_middle']:.2f} / 下: ${latest['bb_lower']:.2f}")
        print(f"   成交量: {latest['volume']:.0f} (均值: {latest['volume_sma']:.0f})")
        
        return signal
    
    def scan_markets(self, symbols):
        """扫描市场"""
        print(f"\n🔍 开始扫描 {len(symbols)} 个交易对...")
        print(f"⏰ 时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        
        signals = {}
        
        for symbol in symbols:
            try:
                signal = self.analyze_symbol(symbol)
                if signal:
                    signals[symbol] = signal
                time.sleep(1)
            except Exception as e:
                print(f"❌ 分析失败 {symbol}: {e}")
        
        # 总结
        print(f"\n{'='*60}")
        print(f"📋 扫描总结")
        print(f"{'='*60}")
        print(f"发现 {len(signals)} 个交易机会:")
        
        for symbol, signal in signals.items():
            emoji = "📈" if signal['type'] == 'buy' else "📉"
            print(f"{emoji} {symbol}: {signal['type'].upper()} @ ${signal['price']:.2f}")
        
        return signals


def main():
    """主程序"""
    print("🚀 启动简化版策略引擎...")
    
    # 初始化交易所
    exchange = ccxt.binance({
        'enableRateLimit': True,
        'timeout': 30000
    })
    
    # 创建策略
    strategy = SimpleEnhancedStrategy(exchange)
    
    # 监控的交易对
    symbols = [
        'BTC/USDT',
        'ETH/USDT',
        'XMR/USDT',
        'BNB/USDT',
        'SOL/USDT'
    ]
    
    # 扫描市场
    signals = strategy.scan_markets(symbols)
    
    return signals


if __name__ == "__main__":
    main()
