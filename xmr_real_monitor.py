# -*- coding: utf-8 -*-
"""
XMR/USDT 实时价格监控和技术分析
修正版 - 支持多交易所价格获取
"""

import ccxt
import requests
import pandas as pd
import numpy as np
import time
import json
from datetime import datetime, timedelta
from flask import Flask, render_template_string, jsonify

class XMRRealMonitor:
    """XMR实时监控器（修正版）"""
    
    def __init__(self):
        self.symbol = 'XMR/USDT'
        self.exchanges = self._init_exchanges()
        self.current_price = 0
        self.current_data = {}
        
        print(f"🔍 XMR 实时监控系统启动")
        print(f"📊 支持多交易所价格获取")
    
    def _init_exchanges(self):
        """初始化支持XMR的交易所"""
        exchanges = {}
        
        # Kraken (主要XMR交易所)
        try:
            exchanges['kraken'] = ccxt.kraken({
                'enableRateLimit': True,
                'timeout': 30000
            })
        except:
            pass
        
        # Bitfinex
        try:
            exchanges['bitfinex'] = ccxt.bitfinex({
                'enableRateLimit': True,
                'timeout': 30000
            })
        except:
            pass
        
        # Gate.io
        try:
            exchanges['gateio'] = ccxt.gateio({
                'enableRateLimit': True,
                'timeout': 30000
            })
        except:
            pass
        
        return exchanges
    
    def get_real_xmr_price(self):
        """获取真实XMR价格"""
        prices = []
        
        # 方法1: CoinGecko API
        try:
            response = requests.get(
                'https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd',
                timeout=10
            )
            data = response.json()
            cg_price = data['monero']['usd']
            prices.append(('CoinGecko', cg_price, 0))
            print(f"✅ CoinGecko: ${cg_price:.2f}")
        except Exception as e:
            print(f"❌ CoinGecko失败: {e}")
        
        # 方法2: 交易所价格
        for exchange_name, exchange in self.exchanges.items():
            try:
                # 尝试不同的交易对
                symbols_to_try = ['XMR/USDT', 'XMR/USD', 'XMRUSDT']
                
                for symbol in symbols_to_try:
                    try:
                        ticker = exchange.fetch_ticker(symbol)
                        price = ticker.get('last', 0)
                        change = ticker.get('percentage', 0) or 0
                        
                        if price and price > 0:
                            prices.append((f"{exchange_name}:{symbol}", price, change))
                            print(f"✅ {exchange_name} {symbol}: ${price:.2f} (24h: {change:+.2f}%)")
                            break
                    except:
                        continue
            except Exception as e:
                print(f"❌ {exchange_name}失败: {str(e)[:50]}")
        
        if prices:
            # 使用价格中位数作为最准确的价格
            price_values = [p[1] for p in prices]
            median_price = np.median(price_values)
            
            # 找到最接近中位数的价格源
            best_source = min(prices, key=lambda x: abs(x[1] - median_price))
            
            self.current_price = best_source[1]
            return {
                'price': best_source[1],
                'change_24h': best_source[2],
                'source': best_source[0],
                'all_prices': prices,
                'median_price': median_price
            }
        
        return None
    
    def get_historical_data(self):
        """获取历史数据用于技术分析"""
        # 使用Kraken获取历史数据（如果可用）
        if 'kraken' in self.exchanges:
            try:
                exchange = self.exchanges['kraken']
                
                # 获取1小时、4小时、1日数据
                ohlcv_1h = exchange.fetch_ohlcv('XMR/USD', '1h', limit=100)
                ohlcv_4h = exchange.fetch_ohlcv('XMR/USD', '4h', limit=50)
                ohlcv_1d = exchange.fetch_ohlcv('XMR/USD', '1d', limit=30)
                
                # 转换为DataFrame
                dfs = {}
                for tf, data in [('1h', ohlcv_1h), ('4h', ohlcv_4h), ('1d', ohlcv_1d)]:
                    df = pd.DataFrame(data, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
                    df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
                    df.set_index('timestamp', inplace=True)
                    dfs[tf] = self.calculate_technical_indicators(df)
                
                return dfs
                
            except Exception as e:
                print(f"❌ 获取历史数据失败: {e}")
        
        return None
    
    def calculate_technical_indicators(self, df):
        """计算技术指标"""
        # 移动平均线
        df['ma5'] = df['close'].rolling(window=5).mean()
        df['ma10'] = df['close'].rolling(window=10).mean()
        df['ma20'] = df['close'].rolling(window=20).mean()
        df['ma50'] = df['close'].rolling(window=50).mean()
        
        # RSI
        delta = df['close'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=14).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=14).mean()
        rs = gain / loss
        df['rsi'] = 100 - (100 / (1 + rs))
        
        # MACD
        exp1 = df['close'].ewm(span=12, adjust=False).mean()
        exp2 = df['close'].ewm(span=26, adjust=False).mean()
        df['macd'] = exp1 - exp2
        df['macd_signal'] = df['macd'].ewm(span=9, adjust=False).mean()
        
        # 布林带
        df['bb_middle'] = df['close'].rolling(window=20).mean()
        bb_std = df['close'].rolling(window=20).std()
        df['bb_upper'] = df['bb_middle'] + (bb_std * 2)
        df['bb_lower'] = df['bb_middle'] - (bb_std * 2)
        
        # ATR
        df['high_low'] = df['high'] - df['low']
        df['high_close'] = np.abs(df['high'] - df['close'].shift())
        df['low_close'] = np.abs(df['low'] - df['close'].shift())
        df['tr'] = df[['high_low', 'high_close', 'low_close']].max(axis=1)
        df['atr'] = df['tr'].rolling(window=14).mean()
        
        return df
    
    def analyze_market_trend(self, historical_data):
        """分析市场趋势"""
        if not historical_data:
            return None
        
        analysis = {
            'timestamp': datetime.now(),
            'current_price': self.current_price,
            'trends': {}
        }
        
        for timeframe, df in historical_data.items():
            if len(df) < 20:
                continue
                
            latest = df.iloc[-1]
            prev = df.iloc[-2] if len(df) > 1 else latest
            
            # 趋势判断
            trend = 'neutral'
            strength = 0
            
            if timeframe == '1d':
                # 日线主趋势
                if latest['close'] > latest['ma20'] and latest['ma5'] > latest['ma20']:
                    trend = 'bullish'
                    strength = min(0.9, (latest['close'] - latest['ma20']) / latest['ma20'] * 10)
                elif latest['close'] < latest['ma20'] and latest['ma5'] < latest['ma20']:
                    trend = 'bearish'
                    strength = min(0.9, (latest['ma20'] - latest['close']) / latest['ma20'] * 10)
            
            elif timeframe == '4h':
                # 4小时中期趋势
                if latest['macd'] > latest['macd_signal'] and latest['rsi'] > 50:
                    trend = 'bullish'
                elif latest['macd'] < latest['macd_signal'] and latest['rsi'] < 50:
                    trend = 'bearish'
            
            elif timeframe == '1h':
                # 1小时短期趋势
                price_change = (latest['close'] - prev['close']) / prev['close'] * 100
                if price_change > 1 and latest['rsi'] < 70:
                    trend = 'bullish'
                elif price_change < -1 and latest['rsi'] > 30:
                    trend = 'bearish'
            
            analysis['trends'][timeframe] = {
                'trend': trend,
                'strength': strength,
                'rsi': latest['rsi'],
                'macd': latest['macd'],
                'bb_position': self._get_bb_position(latest),
                'atr': latest['atr']
            }
        
        return analysis
    
    def _get_bb_position(self, data):
        """获取布林带位置"""
        price = data['close']
        if price > data['bb_upper']:
            return 'above_upper'
        elif price < data['bb_lower']:
            return 'below_lower'
        elif price > data['bb_middle']:
            return 'upper_half'
        else:
            return 'lower_half'
    
    def calculate_stop_loss_take_profit(self, current_price, atr, signal_type):
        """计算止损止盈"""
        atr_multiplier = 2.0
        risk_reward_ratio = 2.5
        
        if signal_type == 'long':
            stop_loss = current_price - (atr * atr_multiplier)
            take_profit = current_price + (atr * atr_multiplier * risk_reward_ratio)
        else:  # short
            stop_loss = current_price + (atr * atr_multiplier)
            take_profit = current_price - (atr * atr_multiplier * risk_reward_ratio)
        
        risk_amount = abs(current_price - stop_loss)
        reward_amount = abs(take_profit - current_price)
        
        return {
            'entry': current_price,
            'stop_loss': stop_loss,
            'take_profit': take_profit,
            'risk_amount': risk_amount,
            'reward_amount': reward_amount,
            'risk_reward_ratio': reward_amount / risk_amount if risk_amount > 0 else 0,
            'risk_percentage': (risk_amount / current_price) * 100,
            'reward_percentage': (reward_amount / current_price) * 100
        }
    
    def generate_trading_signals(self, analysis):
        """生成交易信号"""
        if not analysis or not analysis.get('trends'):
            return {
                'signal': 'hold',
                'confidence': 0,
                'reason': '数据不足',
                'entry_suggestions': []
            }
        
        trends = analysis['trends']
        signal_strength = 0
        signals = []
        
        # 多时间框架分析
        daily_trend = trends.get('1d', {}).get('trend', 'neutral')
        h4_trend = trends.get('4h', {}).get('trend', 'neutral')
        h1_trend = trends.get('1h', {}).get('trend', 'neutral')
        
        # 信号逻辑
        if daily_trend == 'bullish' and h4_trend == 'bullish':
            signals.append("日线+4H多头趋势")
            signal_strength += 3
            overall_signal = 'buy'
        elif daily_trend == 'bearish' and h4_trend == 'bearish':
            signals.append("日线+4H空头趋势")
            signal_strength += 3
            overall_signal = 'sell'
        else:
            overall_signal = 'hold'
        
        # RSI信号
        if '1h' in trends:
            h1_rsi = trends['1h']['rsi']
            if h1_rsi < 30:
                signals.append(f"1H RSI超卖({h1_rsi:.1f})")
                signal_strength += 1
                if overall_signal == 'hold':
                    overall_signal = 'buy'
            elif h1_rsi > 70:
                signals.append(f"1H RSI超买({h1_rsi:.1f})")
                signal_strength += 1
                if overall_signal == 'hold':
                    overall_signal = 'sell'
        
        # 生成入场建议
        entry_suggestions = []
        if overall_signal in ['buy', 'sell'] and '1h' in trends:
            atr = trends['1h']['atr']
            risk_reward = self.calculate_stop_loss_take_profit(
                self.current_price, atr, 'long' if overall_signal == 'buy' else 'short'
            )
            entry_suggestions.append({
                'type': 'long' if overall_signal == 'buy' else 'short',
                **risk_reward
            })
        
        return {
            'signal': overall_signal,
            'confidence': min(signal_strength * 20, 100),
            'reason': ' | '.join(signals) if signals else '无明确信号',
            'entry_suggestions': entry_suggestions
        }
    
    def run_analysis(self):
        """运行完整分析"""
        print(f"\n🔍 {datetime.now().strftime('%H:%M:%S')} XMR 实时分析")
        print("-" * 60)
        
        # 获取实时价格
        price_data = self.get_real_xmr_price()
        if not price_data:
            print("❌ 无法获取价格数据")
            return None
        
        print(f"💰 当前价格: ${price_data['price']:.2f}")
        print(f"📈 24H变化: {price_data['change_24h']:+.2f}%")
        print(f"📊 数据源: {price_data['source']}")
        
        # 显示所有价格源
        print(f"\n📋 价格对比:")
        for source, price, change in price_data['all_prices']:
            print(f"   {source}: ${price:.2f} (24h: {change:+.2f}%)")
        
        # 获取技术分析数据
        historical_data = self.get_historical_data()
        if historical_data:
            # 分析趋势
            analysis = self.analyze_market_trend(historical_data)
            
            # 生成信号
            signals = self.generate_trading_signals(analysis)
            
            # 显示分析结果
            print(f"\n📊 多时间框架趋势:")
            for tf, trend_data in analysis['trends'].items():
                trend_emoji = "🟢" if trend_data['trend'] == 'bullish' else "🔴" if trend_data['trend'] == 'bearish' else "🟡"
                print(f"   {tf}: {trend_emoji} {trend_data['trend']} (RSI: {trend_data['rsi']:.1f})")
            
            # 交易信号
            signal_color = "🟢" if signals['signal'] == 'buy' else "🔴" if signals['signal'] == 'sell' else "🟡"
            print(f"\n🎯 交易信号: {signal_color} {signals['signal'].upper()}")
            print(f"🎯 信心度: {signals['confidence']:.0f}%")
            print(f"📋 信号原因: {signals['reason']}")
            
            # 入场建议
            if signals['entry_suggestions']:
                print(f"\n📍 入场建议:")
                for suggestion in signals['entry_suggestions']:
                    print(f"   {suggestion['type'].upper()}:")
                    print(f"   💰 入场价格: ${suggestion['entry']:.2f}")
                    print(f"   🛡️ 止损: ${suggestion['stop_loss']:.2f}")
                    print(f"   🎯 止盈: ${suggestion['take_profit']:.2f}")
                    print(f"   ⚖️ 风险收益比: 1:{suggestion['risk_reward_ratio']:.2f}")
                    print(f"   📊 风险比例: {suggestion['risk_percentage']:.2f}%")
            
            self.current_data = {
                'price_data': price_data,
                'analysis': analysis,
                'signals': signals,
                'timestamp': datetime.now().isoformat()
            }
            
            return self.current_data
        
        else:
            print("⚠️ 无法获取技术分析数据，仅显示价格信息")
            self.current_data = {
                'price_data': price_data,
                'timestamp': datetime.now().isoformat()
            }
            return self.current_data
    
    def continuous_monitoring(self, interval=300):
        """连续监控"""
        print(f"\n🚀 开始连续监控 XMR")
        print(f"⏰ 更新间隔: {interval//60}分钟")
        print("按 Ctrl+C 停止监控")
        
        try:
            while True:
                self.run_analysis()
                print(f"\n⏰ 下次更新: {(datetime.now() + timedelta(seconds=interval)).strftime('%H:%M:%S')}")
                print("="*60)
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")

def main():
    """主程序"""
    monitor = XMRRealMonitor()
    
    print("\n🚀 XMR 监控系统")
    print("=" * 50)
    print("选择模式:")
    print("1. 单次分析")
    print("2. 连续监控 (5分钟)")
    
    try:
        choice = input("\n请选择 (1-2): ").strip()
        
        if choice == '1':
            monitor.run_analysis()
        elif choice == '2':
            monitor.continuous_monitoring(interval=300)
        else:
            print("❌ 无效选择")
    
    except KeyboardInterrupt:
        print("\n👋 程序已退出")
    except Exception as e:
        print(f"❌ 程序出错: {e}")

if __name__ == "__main__":
    main()