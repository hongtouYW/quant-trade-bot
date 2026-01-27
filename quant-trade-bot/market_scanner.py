#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""市场机会扫描器 - 使用策略筛选有交易机会的货币"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import ccxt
import pandas as pd
import numpy as np
import json
from datetime import datetime
from config.config_multi_timeframe import TRADING_PAIRS, STRATEGY_CONFIG

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化交易所
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

def send_telegram(message):
    """发送Telegram通知"""
    try:
        import requests
        bot_token = config['telegram']['bot_token']
        chat_id = config['telegram']['chat_id']
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        requests.post(url, data={'chat_id': chat_id, 'text': message}, timeout=5)
        print("✅ Telegram通知已发送")
    except Exception as e:
        print(f"⚠️ Telegram发送失败: {e}")

def calculate_rsi(prices, period=14):
    """计算RSI"""
    deltas = np.diff(prices)
    gain = np.where(deltas > 0, deltas, 0)
    loss = np.where(deltas < 0, -deltas, 0)
    
    avg_gain = np.mean(gain[:period])
    avg_loss = np.mean(loss[:period])
    
    if avg_loss == 0:
        return 100
    
    rs = avg_gain / avg_loss
    rsi = 100 - (100 / (1 + rs))
    return rsi

def calculate_macd(prices):
    """计算MACD"""
    ema12 = pd.Series(prices).ewm(span=12).mean()
    ema26 = pd.Series(prices).ewm(span=26).mean()
    macd = ema12 - ema26
    signal = macd.ewm(span=9).mean()
    return macd.iloc[-1], signal.iloc[-1]

def analyze_symbol(symbol):
    """分析单个交易对"""
    try:
        # 获取日线数据（趋势）
        ohlcv_1d = exchange.fetch_ohlcv(symbol, '1d', limit=50)
        df_1d = pd.DataFrame(ohlcv_1d, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        
        # 获取15分钟数据（入场）
        ohlcv_15m = exchange.fetch_ohlcv(symbol, '15m', limit=100)
        df_15m = pd.DataFrame(ohlcv_15m, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        
        # 当前价格
        current_price = df_15m['close'].iloc[-1]
        
        # 日线分析
        ma20 = df_1d['close'].rolling(20).mean().iloc[-1]
        ma50 = df_1d['close'].rolling(50).mean().iloc[-1]
        rsi_1d = calculate_rsi(df_1d['close'].values)
        macd_1d, signal_1d = calculate_macd(df_1d['close'].values)
        
        # 判断日线趋势
        trend = "震荡"
        trend_strength = 0.5
        
        if current_price > ma20 > ma50 and macd_1d > signal_1d:
            trend = "上涨"
            trend_strength = 0.7 if rsi_1d < 70 else 0.5
        elif current_price < ma20 < ma50 and macd_1d < signal_1d:
            trend = "下跌"
            trend_strength = 0.7 if rsi_1d > 30 else 0.5
        
        # 15分钟分析
        rsi_15m = calculate_rsi(df_15m['close'].values)
        macd_15m, signal_15m = calculate_macd(df_15m['close'].values)
        
        # 成交量分析
        avg_volume = df_15m['volume'].tail(20).mean()
        current_volume = df_15m['volume'].iloc[-1]
        volume_ratio = current_volume / avg_volume if avg_volume > 0 else 0
        
        # 入场信号
        signal = "持有"
        confidence = 0.3
        
        # 做多信号
        if trend == "上涨":
            if rsi_15m < 40 and macd_15m > signal_15m:
                signal = "做多"
                confidence = 0.7 if volume_ratio > 1.2 else 0.5
            elif rsi_15m < 50 and current_price > ma20:
                signal = "做多"
                confidence = 0.5
        
        # 做空信号
        elif trend == "下跌":
            if rsi_15m > 60 and macd_15m < signal_15m:
                signal = "做空"
                confidence = 0.7 if volume_ratio > 1.2 else 0.5
            elif rsi_15m > 50 and current_price < ma20:
                signal = "做空"
                confidence = 0.5
        
        # 震荡市场
        else:
            if rsi_15m < 30:
                signal = "做多"
                confidence = 0.4
            elif rsi_15m > 70:
                signal = "做空"
                confidence = 0.4
        
        return {
            'symbol': symbol,
            'current_price': current_price,
            'trend': trend,
            'trend_strength': trend_strength,
            'signal': signal,
            'confidence': confidence,
            'rsi_1d': rsi_1d,
            'rsi_15m': rsi_15m,
            'volume_ratio': volume_ratio,
            'ma20': ma20,
            'ma50': ma50
        }
        
    except Exception as e:
        print(f"❌ {symbol} 分析失败: {e}")
        return None

def scan_market():
    """扫描市场寻找机会"""
    print("=" * 70)
    print("🔍 市场机会扫描器")
    print("=" * 70)
    print(f"扫描时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"监控币种: {len(TRADING_PAIRS['active_pairs'])}个")
    print("=" * 70)
    
    opportunities = []
    
    for symbol in TRADING_PAIRS['active_pairs']:
        print(f"\n分析 {symbol}...", end=" ")
        result = analyze_symbol(symbol)
        
        if result:
            print(f"✓")
            
            # 根据方案B配置筛选
            min_trend_strength = STRATEGY_CONFIG['multi_timeframe']['trend_strength_threshold']
            min_confidence = STRATEGY_CONFIG['multi_timeframe']['entry_confidence_threshold']
            
            if result['signal'] != "持有":
                if result['trend_strength'] >= min_trend_strength and result['confidence'] >= min_confidence:
                    opportunities.append(result)
                    print(f"   ✨ 发现机会: {result['signal']} (信心度: {result['confidence']:.2f})")
        else:
            print(f"✗")
    
    print("\n" + "=" * 70)
    print(f"📊 扫描结果: 发现 {len(opportunities)} 个交易机会")
    print("=" * 70)
    
    if not opportunities:
        print("\n暂无符合条件的交易机会")
        print("建议: 继续观察市场，等待更好的入场点")
    else:
        # 按信心度排序
        opportunities.sort(key=lambda x: x['confidence'], reverse=True)
        
        # 准备Telegram消息
        telegram_msg = f"""
🔍 市场扫描发现 {len(opportunities)} 个交易机会！

扫描时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        
        for i, opp in enumerate(opportunities, 1):
            print(f"\n{i}. {opp['symbol']}")
            print(f"   当前价: ${opp['current_price']:.4f}")
            print(f"   日线趋势: {opp['trend']} (强度: {opp['trend_strength']:.2f})")
            print(f"   15分钟RSI: {opp['rsi_15m']:.1f}")
            print(f"   成交量倍数: {opp['volume_ratio']:.2f}x")
            print(f"   📈 信号: {opp['signal']}")
            print(f"   🎯 信心度: {opp['confidence']:.2f}")
            
            # 计算建议止损止盈
            if opp['signal'] == "做多":
                stop_loss = opp['current_price'] * 0.98  # -2%
                take_profit = opp['current_price'] * 1.04  # +4%
                print(f"   止损价: ${stop_loss:.4f} (-2%)")
                print(f"   止盈价: ${take_profit:.4f} (+4%)")
                
                telegram_msg += f"""
{i}. {opp['symbol']} - 做多信号 📈
   当前价: ${opp['current_price']:.4f}
   趋势: {opp['trend']} | 信心: {opp['confidence']:.0%}
   止损: ${stop_loss:.4f} | 止盈: ${take_profit:.4f}
   RSI: {opp['rsi_15m']:.1f}
"""
            elif opp['signal'] == "做空":
                stop_loss = opp['current_price'] * 1.02  # +2%
                take_profit = opp['current_price'] * 0.96  # -4%
                print(f"   止损价: ${stop_loss:.4f} (+2%)")
                print(f"   止盈价: ${take_profit:.4f} (-4%)")
                
                telegram_msg += f"""
{i}. {opp['symbol']} - 做空信号 📉
   当前价: ${opp['current_price']:.4f}
   趋势: {opp['trend']} | 信心: {opp['confidence']:.0%}
   止损: ${stop_loss:.4f} | 止盈: ${take_profit:.4f}
   RSI: {opp['rsi_15m']:.1f}
"""
        
        # 发送Telegram通知
        print("\n发送Telegram通知...")
        send_telegram(telegram_msg)
    
    print("\n" + "=" * 70)
    return opportunities

if __name__ == "__main__":
    try:
        opportunities = scan_market()
        
        print("\n💡 提示:")
        print("   - 这是模拟交易扫描")
        print("   - 信号仅供参考，不构成投资建议")
        print("   - 实际交易需要更多确认")
        print("   - 可以设置定时任务每5分钟运行一次")
        
    except Exception as e:
        print(f"\n❌ 扫描失败: {e}")
        import traceback
        traceback.print_exc()
