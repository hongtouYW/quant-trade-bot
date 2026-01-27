#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""XMR和MEMES监督器 - 监控买入信号"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import ccxt
import pandas as pd
import numpy as np
import json
from datetime import datetime

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

# 历史关注价格
WATCH_PRICES = {
    'XMR/USDT': 480.0,
    'MEME/USDT': 0.008810  # Binance上的MEME (注意：不是用户关注的那个)
}

# 注意：用户关注的MEMES是BSC链上的代币
# 合约地址：0xf74548802f4c700315f019fde17178b392ee4444
# 关注价格：$0.008810，当前约$0.01273
# Binance期货不支持该币种交易

def send_telegram(message):
    """发送Telegram通知并@用户"""
    try:
        import requests
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

def analyze_coin(symbol, watch_price):
    """分析单个币种"""
    try:
        print(f"\n分析 {symbol}...")
        
        # 获取15分钟K线数据
        ohlcv_15m = exchange.fetch_ohlcv(symbol, '15m', limit=100)
        df_15m = pd.DataFrame(ohlcv_15m, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        
        # 当前价格
        current_price = df_15m['close'].iloc[-1]
        
        # 技术指标
        ma20 = df_15m['close'].rolling(20).mean().iloc[-1]
        ma50 = df_15m['close'].rolling(50).mean().iloc[-1]
        rsi_15m = calculate_rsi(df_15m['close'].values)
        macd_15m, signal_15m = calculate_macd(df_15m['close'].values)
        
        # 成交量分析
        avg_volume = df_15m['volume'].tail(20).mean()
        current_volume = df_15m['volume'].iloc[-1]
        volume_ratio = current_volume / avg_volume if avg_volume > 0 else 0
        
        # 价格变化
        price_change = ((current_price - watch_price) / watch_price) * 100
        
        # 买入信号判断
        buy_signal = False
        signal_reasons = []
        confidence = 0.0
        
        # 1. RSI超卖
        if rsi_15m < 30:
            buy_signal = True
            signal_reasons.append(f"RSI超卖 ({rsi_15m:.1f})")
            confidence += 0.3
        elif rsi_15m < 40:
            signal_reasons.append(f"RSI偏低 ({rsi_15m:.1f})")
            confidence += 0.1
        
        # 2. MACD金叉
        if macd_15m > signal_15m:
            prev_macd = calculate_macd(df_15m['close'].values[:-1])
            if prev_macd[0] <= prev_macd[1]:  # 刚刚金叉
                buy_signal = True
                signal_reasons.append("MACD金叉")
                confidence += 0.3
        
        # 3. 价格在均线上方
        if current_price > ma20:
            signal_reasons.append("价格>MA20")
            confidence += 0.1
        
        # 4. 成交量放大
        if volume_ratio > 1.5:
            buy_signal = True
            signal_reasons.append(f"成交量放大 ({volume_ratio:.1f}x)")
            confidence += 0.2
        
        # 5. 价格已经大幅下跌
        if price_change < -10:
            buy_signal = True
            signal_reasons.append(f"已下跌{abs(price_change):.1f}%")
            confidence += 0.2
        
        result = {
            'symbol': symbol,
            'current_price': current_price,
            'watch_price': watch_price,
            'price_change': price_change,
            'rsi': rsi_15m,
            'macd': macd_15m,
            'signal': signal_15m,
            'volume_ratio': volume_ratio,
            'ma20': ma20,
            'buy_signal': buy_signal,
            'signal_reasons': signal_reasons,
            'confidence': min(confidence, 1.0)
        }
        
        print(f"  当前价: ${current_price:.6f}")
        print(f"  vs 关注价: {price_change:+.2f}%")
        print(f"  RSI: {rsi_15m:.1f}")
        print(f"  买入信号: {'✅ 是' if buy_signal else '❌ 否'}")
        
        return result
        
    except Exception as e:
        print(f"  ❌ 分析失败: {e}")
        return None

def monitor_coins():
    """监控XMR和MEMES"""
    print("=" * 70)
    print("🔍 XMR & MEMES 监督器")
    print("=" * 70)
    print(f"监控时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"监控币种: {len(WATCH_PRICES)}个")
    print("=" * 70)
    
    signals = []
    
    for symbol, watch_price in WATCH_PRICES.items():
        result = analyze_coin(symbol, watch_price)
        if result and result['buy_signal']:
            signals.append(result)
    
    print("\n" + "=" * 70)
    print(f"📊 监控结果: 发现 {len(signals)} 个买入信号")
    print("=" * 70)
    
    if signals:
        # 发送Telegram通知
        telegram_msg = f"""
🚨 买入信号提醒

监控时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        
        for i, sig in enumerate(signals, 1):
            print(f"\n{i}. {sig['symbol']} - 买入信号 ✅")
            print(f"   当前价: ${sig['current_price']:.6f}")
            print(f"   关注价: ${sig['watch_price']:.6f} ({sig['price_change']:+.2f}%)")
            print(f"   RSI: {sig['rsi']:.1f}")
            print(f"   成交量: {sig['volume_ratio']:.2f}x")
            print(f"   信心度: {sig['confidence']:.0%}")
            print(f"   理由: {', '.join(sig['signal_reasons'])}")
            
            # 计算止损止盈
            stop_loss = sig['current_price'] * 0.98
            take_profit = sig['current_price'] * 1.04
            
            telegram_msg += f"""
{i}. {sig['symbol']} 📈
   当前价: ${sig['current_price']:.6f}
   关注价: ${sig['watch_price']:.6f}
   变化: {sig['price_change']:+.2f}%
   
   RSI: {sig['rsi']:.1f}
   成交量: {sig['volume_ratio']:.2f}x
   信心度: {sig['confidence']:.0%}
   
   理由: {', '.join(sig['signal_reasons'])}
   
   建议止损: ${stop_loss:.6f} (-2%)
   建议止盈: ${take_profit:.6f} (+4%)
   
"""
        
        print("\n发送Telegram通知...")
        send_telegram(telegram_msg)
        
    else:
        print("\n暂无买入信号")
        print("继续监控中...")
    
    print("\n" + "=" * 70)
    
    # 显示当前状态（即使没有信号）
    print("\n📈 当前监控状态:")
    for symbol, watch_price in WATCH_PRICES.items():
        try:
            ticker = exchange.fetch_ticker(symbol)
            current_price = ticker['last']
            change = ((current_price - watch_price) / watch_price) * 100
            print(f"   {symbol}: ${current_price:.6f} ({change:+.2f}% vs ${watch_price:.6f})")
        except:
            print(f"   {symbol}: 获取价格失败")
    
    return signals

if __name__ == "__main__":
    try:
        signals = monitor_coins()
        
        print("\n💡 提示:")
        print("   - 监控XMR和MEMES买入信号")
        print("   - 发现信号时自动@Hzai5522通知")
        print("   - 建议每5-15分钟运行一次")
        print("   - 可以添加到crontab定时任务")
        
    except Exception as e:
        print(f"\n❌ 监控失败: {e}")
        import traceback
        traceback.print_exc()
