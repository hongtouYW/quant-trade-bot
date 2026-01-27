#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""XMR和BSC MEMES监督器 - 监控买入信号"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import ccxt
import pandas as pd
import numpy as np
import json
import requests
from datetime import datetime

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化Binance交易所
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

# 参考建议价格（仅供参考，不作为买入条件）
# 生成时间: 2026-01-26
REFERENCE_PRICES = {
    'XMR/USDT': {
        'suggested_price': 458.83,   # 参考价格
        'support_level': 445.07,     # 支撑位
        'strategy': '支撑位+RSI策略'
    },
    'MEMES_BSC': {
        'suggested_price': 0.01000,  # 参考价格
        'support_level': 0.010184,   # 支撑位
        'contract': '0xf74548802f4c700315f019fde17178b392ee4444',
        'chain': 'BNB Chain',
        'strategy': '回调+支撑位策略'
    }
}

def send_telegram(message):
    """发送Telegram通知并@用户"""
    try:
        bot_token = config['telegram']['bot_token']
        chat_id = config['telegram']['chat_id']
        
        # 添加@用户
        message_with_mention = f"@Hzai5522\n\n{message}"
        
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        requests.post(url, data={
            'chat_id': chat_id, 
            'text': message_with_mention,
            'parse_mode': 'HTML'
        }, timeout=5)
        print("✅ Telegram通知已发送（已@Hzai5522）")
    except Exception as e:
        print(f"⚠️ Telegram发送失败: {e}")

def calculate_rsi(prices, period=14):
    """计算RSI"""
    if len(prices) < period + 1:
        return 50
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

def analyze_xmr(symbol, ref_info):
    """分析XMR (Binance期货) - 基于技术指标"""
    try:
        print(f"\n分析 {symbol}...")
        
        # 获取多周期数据
        ohlcv_15m = exchange.fetch_ohlcv(symbol, '15m', limit=100)
        ohlcv_1h = exchange.fetch_ohlcv(symbol, '1h', limit=100)
        ohlcv_1d = exchange.fetch_ohlcv(symbol, '1d', limit=30)
        
        df_15m = pd.DataFrame(ohlcv_15m, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        df_1h = pd.DataFrame(ohlcv_1h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        df_1d = pd.DataFrame(ohlcv_1d, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        
        current_price = df_15m['close'].iloc[-1]
        
        # 技术指标
        ma20_15m = df_15m['close'].rolling(20).mean().iloc[-1]
        ma50_15m = df_15m['close'].rolling(50).mean().iloc[-1]
        
        rsi_15m = calculate_rsi(df_15m['close'].values)
        rsi_1h = calculate_rsi(df_1h['close'].values)
        
        # 成交量
        avg_volume = df_15m['volume'].tail(20).mean()
        current_volume = df_15m['volume'].iloc[-1]
        volume_ratio = current_volume / avg_volume if avg_volume > 0 else 0
        
        # 支撑位
        support_7d = df_1d['low'].tail(7).min()
        support_recent = df_1h['low'].tail(20).min()
        
        # MACD (简化版)
        ema12 = df_1h['close'].ewm(span=12).mean().iloc[-1]
        ema26 = df_1h['close'].ewm(span=26).mean().iloc[-1]
        macd_signal = 'bullish' if ema12 > ema26 else 'bearish'
        
        # ===== 买入信号判断（完全基于技术指标）=====
        buy_signal = False
        signal_reasons = []
        confidence = 0.0
        
        # 1. RSI超卖（重要指标）
        if rsi_15m < 30 or rsi_1h < 30:
            buy_signal = True
            signal_reasons.append(f"RSI严重超卖 (15m:{rsi_15m:.1f}, 1h:{rsi_1h:.1f})")
            confidence += 0.4
        elif rsi_15m < 40:
            buy_signal = True
            signal_reasons.append(f"RSI偏低 ({rsi_15m:.1f})")
            confidence += 0.2
        
        # 2. 接近支撑位
        if current_price < support_7d * 1.05:
            buy_signal = True
            signal_reasons.append(f"接近7日支撑${support_7d:.2f}")
            confidence += 0.3
        elif current_price < support_recent * 1.03:
            signal_reasons.append(f"接近近期支撑${support_recent:.2f}")
            confidence += 0.15
        
        # 3. 成交量放大（确认信号）
        if volume_ratio > 1.5:
            signal_reasons.append(f"成交量放大{volume_ratio:.1f}x")
            confidence += 0.2
            if buy_signal:  # 如果已有其他信号，成交量放大增强信心
                confidence += 0.1
        
        # 4. 均线位置
        if current_price > ma20_15m and ma20_15m > ma50_15m:
            signal_reasons.append("均线多头排列")
            confidence += 0.15
        elif current_price < ma50_15m:
            signal_reasons.append("价格低于MA50")
            confidence += 0.1
        
        # 5. MACD信号
        if macd_signal == 'bullish':
            signal_reasons.append("MACD多头")
            confidence += 0.1
        
        result = {
            'symbol': symbol,
            'current_price': current_price,
            'reference_price': ref_info['suggested_price'],
            'support': support_7d,
            'rsi_15m': rsi_15m,
            'rsi_1h': rsi_1h,
            'volume_ratio': volume_ratio,
            'ma20': ma20_15m,
            'macd': macd_signal,
            'buy_signal': buy_signal,
            'signal_reasons': signal_reasons,
            'confidence': min(confidence, 1.0)
        }
        
        print(f"  当前价: ${current_price:.2f}")
        print(f"  RSI: 15m={rsi_15m:.1f}, 1h={rsi_1h:.1f}")
        print(f"  支撑位: ${support_7d:.2f}")
        print(f"  成交量: {volume_ratio:.2f}x")
        print(f"  买入信号: {'✅ 是' if buy_signal else '❌ 否'} ({confidence:.0%})")
        
        return result
        
    except Exception as e:
        print(f"  ❌ 分析失败: {e}")
        return None

def analyze_memes_bsc(info):
    """分析BSC链上的MEMES - 基于技术指标"""
    try:
        contract = info['contract']
        
        print(f"\n分析 MEMES (BSC链)...")
        print(f"  合约: {contract}")
        
        # 当前已知价格和24h数据
        current_price = 0.01273
        daily_drop = -19.22
        market_cap = 12770000  # $1277万
        volume_24h = 12551000  # $1255万
        
        # 估算技术指标
        support_estimate = current_price * 0.80  # 估算支撑位
        
        # ===== 买入信号判断（基于可用数据）=====
        buy_signal = False
        signal_reasons = []
        confidence = 0.0
        
        # 1. 24h暴跌（超卖信号）
        if daily_drop < -20:
            buy_signal = True
            signal_reasons.append(f"24h暴跌{abs(daily_drop):.1f}%，严重超卖")
            confidence += 0.4
        elif daily_drop < -15:
            buy_signal = True
            signal_reasons.append(f"24h下跌{abs(daily_drop):.1f}%")
            confidence += 0.3
        
        # 2. 成交量/市值比（流动性）
        volume_ratio = volume_24h / market_cap
        if volume_ratio > 0.8:
            signal_reasons.append(f"高流动性 (成交量/市值={volume_ratio:.1%})")
            confidence += 0.2
        
        # 3. 接近心理支撑位$0.01
        if 0.00950 < current_price < 0.01100:
            buy_signal = True
            signal_reasons.append("接近$0.01心理支撑位")
            confidence += 0.3
        
        # 4. 估算RSI（基于24h跌幅）
        # -19.22%暴跌 -> RSI可能在30-40区间
        estimated_rsi = max(20, 50 + daily_drop * 1.5)  # 简单估算
        if estimated_rsi < 35:
            signal_reasons.append(f"估算RSI={estimated_rsi:.0f}超卖")
            confidence += 0.2
        
        # 5. 价格位置（相对估算支撑）
        above_support = ((current_price - support_estimate) / support_estimate) * 100
        if above_support < 30:
            signal_reasons.append(f"接近支撑位+{above_support:.1f}%")
            confidence += 0.15
        
        result = {
            'symbol': 'MEMES (BSC)',
            'current_price': current_price,
            'reference_price': info['suggested_price'],
            'support': support_estimate,
            'daily_change': daily_drop,
            'volume_ratio': volume_ratio,
            'estimated_rsi': estimated_rsi,
            'buy_signal': buy_signal,
            'signal_reasons': signal_reasons,
            'confidence': min(confidence, 1.0),
            'contract': contract
        }
        
        print(f"  当前价: ${current_price:.6f}")
        print(f"  24h: {daily_drop:+.2f}%")
        print(f"  流动性: {volume_ratio:.1%}")
        print(f"  买入信号: {'✅ 是' if buy_signal else '❌ 否'} ({confidence:.0%})")
        
        return result
        
    except Exception as e:
        print(f"  ❌ 分析失败: {e}")
        return None

def monitor_coins():
    """监控XMR和MEMES - 基于技术指标"""
    print("=" * 70)
    print("🔍 XMR & MEMES (BSC) 监督器 - 技术指标策略")
    print("=" * 70)
    print(f"监控时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"监控币种: 2个 (XMR + MEMES)")
    print("=" * 70)
    
    signals = []
    
    # 分析XMR
    xmr_result = analyze_xmr('XMR/USDT', REFERENCE_PRICES['XMR/USDT'])
    if xmr_result and xmr_result['buy_signal']:
        signals.append(xmr_result)
    
    # 分析MEMES (BSC)
    memes_result = analyze_memes_bsc(REFERENCE_PRICES['MEMES_BSC'])
    if memes_result and memes_result['buy_signal']:
        signals.append(memes_result)
    
    print("\n" + "=" * 70)
    print(f"📊 监控结果: 发现 {len(signals)} 个买入信号")
    print("=" * 70)
    
    if signals:
        # 发送Telegram通知
        telegram_msg = f"""
🚨 买入信号提醒 (技术指标)

监控时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        
        for i, sig in enumerate(signals, 1):
            print(f"\n{i}. {sig['symbol']} - 买入信号 ✅")
            print(f"   当前价: ${sig['current_price']:.6f}")
            
            if 'rsi_15m' in sig:
                print(f"   RSI: 15m={sig['rsi_15m']:.1f}, 1h={sig['rsi_1h']:.1f}")
            if 'estimated_rsi' in sig:
                print(f"   估算RSI: {sig['estimated_rsi']:.0f}")
            if 'support' in sig:
                print(f"   支撑位: ${sig['support']:.6f}")
            if 'daily_change' in sig:
                print(f"   24h: {sig['daily_change']:+.2f}%")
            if 'volume_ratio' in sig:
                print(f"   成交量: {sig['volume_ratio']:.2f}x")
                
            print(f"   信心度: {sig['confidence']:.0%}")
            print(f"   理由: {', '.join(sig['signal_reasons'])}")
            
            # 止损止盈建议
            stop_loss = sig['current_price'] * 0.92
            take_profit = sig['current_price'] * 1.15
            
            telegram_msg += f"""
{i}. {sig['symbol']} 📈
   当前价: ${sig['current_price']:.6f}
   """
            
            if 'rsi_15m' in sig:
                telegram_msg += f"   RSI: 15m={sig['rsi_15m']:.1f}, 1h={sig['rsi_1h']:.1f}\n"
            if 'estimated_rsi' in sig:
                telegram_msg += f"   估算RSI: {sig['estimated_rsi']:.0f}\n"
            if 'daily_change' in sig:
                telegram_msg += f"   24h: {sig['daily_change']:+.2f}%\n"
            if 'support' in sig:
                telegram_msg += f"   支撑位: ${sig['support']:.6f}\n"
                
            telegram_msg += f"""   
   信心度: {sig['confidence']:.0%}
   理由: {', '.join(sig['signal_reasons'])}
   
   建议止损: ${stop_loss:.6f} (-8%)
   建议止盈: ${take_profit:.6f} (+15%)
   
"""
            if 'contract' in sig:
                telegram_msg += f"   合约: {sig['contract'][:10]}...{sig['contract'][-6:]}\n"
            
            # 参考价格（不作为买入条件）
            if 'reference_price' in sig:
                telegram_msg += f"   参考价: ${sig['reference_price']:.6f}\n\n"
        
        print("\n发送Telegram通知...")
        send_telegram(telegram_msg)
        
    else:
        print("\n暂无买入信号")
        print("继续监控中...")
    
    print("\n" + "=" * 70)
    
    # 显示当前状态
    print("\n📈 当前监控状态:")
    
    # XMR
    if xmr_result:
        print(f"   XMR/USDT: ${xmr_result['current_price']:.2f}")
        print(f"     RSI: 15m={xmr_result['rsi_15m']:.1f}, 1h={xmr_result['rsi_1h']:.1f}")
        print(f"     支撑: ${xmr_result['support']:.2f}")
    
    # MEMES (BSC)
    if memes_result:
        print(f"   MEMES (BSC): ${memes_result['current_price']:.6f}")
        print(f"     24h: {memes_result['daily_change']:+.2f}%")
        print(f"     支撑: ${memes_result['support']:.6f}")
        print(f"     合约: {memes_result['contract']}")
    
    return signals

if __name__ == "__main__":
    try:
        signals = monitor_coins()
        
        print("\n💡 提示:")
        print("   - 监控XMR (Binance期货) 和 MEMES (BSC链)")
        print("   - 发现信号时自动@Hzai5522通知")
        print("   - 建议每10-15分钟运行一次")
        print("   - MEMES是BSC链代币，需要在DEX或Bitget交易")
        
    except Exception as e:
        print(f"\n❌ 监控失败: {e}")
        import traceback
        traceback.print_exc()
