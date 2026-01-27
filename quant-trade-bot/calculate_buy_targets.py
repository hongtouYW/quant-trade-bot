#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""基于技术分析计算XMR和MEMES合理买入价"""

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

exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

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
    return 100 - (100 / (1 + rs))

def analyze_xmr_target():
    """分析XMR合理买入价"""
    print("\n" + "="*70)
    print("📊 XMR/USDT 合理买入价分析")
    print("="*70)
    
    # 获取多周期数据
    ohlcv_1d = exchange.fetch_ohlcv('XMR/USDT', '1d', limit=30)
    ohlcv_4h = exchange.fetch_ohlcv('XMR/USDT', '4h', limit=100)
    ohlcv_1h = exchange.fetch_ohlcv('XMR/USDT', '1h', limit=100)
    
    df_1d = pd.DataFrame(ohlcv_1d, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
    df_4h = pd.DataFrame(ohlcv_4h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
    df_1h = pd.DataFrame(ohlcv_1h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
    
    current = df_1h['close'].iloc[-1]
    
    print(f"\n当前价格: ${current:.2f}")
    
    # 计算支撑位
    support_7d = df_1d['low'].tail(7).min()
    support_30d = df_1d['low'].tail(30).min()
    support_recent = df_4h['low'].tail(20).min()
    
    print(f"\n支撑位分析:")
    print(f"  7日最低: ${support_7d:.2f}")
    print(f"  30日最低: ${support_30d:.2f}")
    print(f"  近期支撑: ${support_recent:.2f}")
    
    # 计算移动平均
    ma20 = df_1d['close'].rolling(20).mean().iloc[-1]
    ma50 = df_1d['close'].rolling(50).mean().iloc[-1] if len(df_1d) >= 50 else ma20
    
    print(f"\n均线分析:")
    print(f"  MA20 (日线): ${ma20:.2f}")
    print(f"  MA50 (日线): ${ma50:.2f}")
    
    # RSI分析
    rsi_1h = calculate_rsi(df_1h['close'].values)
    rsi_4h = calculate_rsi(df_4h['close'].values)
    
    print(f"\nRSI指标:")
    print(f"  1小时: {rsi_1h:.1f}")
    print(f"  4小时: {rsi_4h:.1f}")
    
    # 计算建议买入价（多个策略）
    buy_targets = []
    
    # 1. 支撑位策略
    buy_targets.append({
        'price': support_recent * 1.02,
        'reason': '近期支撑位上方2%',
        'confidence': 0.7
    })
    
    # 2. RSI超卖回补策略
    if rsi_1h < 30:
        buy_targets.append({
            'price': current,
            'reason': 'RSI超卖，当前价可买',
            'confidence': 0.8
        })
    elif rsi_1h < 40:
        buy_targets.append({
            'price': current * 0.98,
            'reason': 'RSI偏低，回调2%可买',
            'confidence': 0.6
        })
    
    # 3. 均线策略
    if current < ma20:
        buy_targets.append({
            'price': ma20 * 0.95,
            'reason': 'MA20下方5%',
            'confidence': 0.65
        })
    
    # 4. 7日低点策略
    buy_targets.append({
        'price': support_7d * 1.03,
        'reason': '7日低点上方3%',
        'confidence': 0.75
    })
    
    # 综合推荐（取加权平均）
    if buy_targets:
        weighted_price = sum(t['price'] * t['confidence'] for t in buy_targets) / sum(t['confidence'] for t in buy_targets)
        best_target = max(buy_targets, key=lambda x: x['confidence'])
        
        print(f"\n💡 买入建议:")
        print(f"\n  主推价格: ${best_target['price']:.2f}")
        print(f"  理由: {best_target['reason']}")
        print(f"  信心度: {best_target['confidence']:.0%}")
        
        print(f"\n  综合价格: ${weighted_price:.2f}")
        print(f"  (多策略加权平均)")
        
        print(f"\n  分批建议:")
        print(f"    第1批: ${best_target['price']:.2f} (40%)")
        print(f"    第2批: ${best_target['price'] * 0.97:.2f} (30%)")
        print(f"    第3批: ${support_7d * 1.01:.2f} (30%)")
        
        return best_target['price'], weighted_price
    
    return current * 0.98, current * 0.98

def analyze_memes_target():
    """分析MEMES (BSC) 合理买入价"""
    print("\n" + "="*70)
    print("📊 MEMES (BSC链) 合理买入价分析")
    print("="*70)
    
    current = 0.01273  # 当前价格
    daily_change = -19.22  # 24h变化
    
    print(f"\n当前价格: ${current:.6f}")
    print(f"24h变化: {daily_change:+.2f}%")
    print(f"合约: 0xf74548802f4c700315f019fde17178b392ee4444")
    
    # 估算支撑位（基于当前价格和跌幅）
    # 假设7日低点约在当前价格-20%
    support_estimate = current * 0.80
    
    print(f"\n估算支撑位:")
    print(f"  预估支撑: ${support_estimate:.6f}")
    
    # BSC链代币策略
    buy_targets = []
    
    # 1. 回调策略 - 24h暴跌后反弹
    buy_targets.append({
        'price': current * 0.95,  # 当前价-5%
        'reason': '24h暴跌后，再回调5%买入',
        'confidence': 0.6
    })
    
    # 2. 支撑位策略
    buy_targets.append({
        'price': support_estimate * 1.05,  # 支撑位上方5%
        'reason': '预估支撑位上方5%',
        'confidence': 0.7
    })
    
    # 3. 整数位策略
    buy_targets.append({
        'price': 0.01000,  # $0.01整数位
        'reason': '心理支撑位$0.01',
        'confidence': 0.8
    })
    
    # 4. 保守策略
    buy_targets.append({
        'price': current * 0.85,  # -15%
        'reason': '等待进一步回调15%',
        'confidence': 0.5
    })
    
    # 综合推荐
    weighted_price = sum(t['price'] * t['confidence'] for t in buy_targets) / sum(t['confidence'] for t in buy_targets)
    best_target = max(buy_targets, key=lambda x: x['confidence'])
    
    print(f"\n💡 买入建议:")
    print(f"\n  主推价格: ${best_target['price']:.6f}")
    print(f"  理由: {best_target['reason']}")
    print(f"  信心度: {best_target['confidence']:.0%}")
    
    print(f"\n  综合价格: ${weighted_price:.6f}")
    print(f"  (多策略加权平均)")
    
    print(f"\n  分批建议:")
    print(f"    第1批: ${best_target['price']:.6f} (30%)")
    print(f"    第2批: ${weighted_price * 0.95:.6f} (30%)")
    print(f"    第3批: ${support_estimate * 1.03:.6f} (40%)")
    
    print(f"\n  ⚠️ 风险提示:")
    print(f"    - BSC链代币，流动性风险")
    print(f"    - 需要在DEX或Bitget交易")
    print(f"    - 建议小仓位试探（总资金1-3%）")
    
    return best_target['price'], weighted_price

if __name__ == "__main__":
    try:
        # 分析XMR
        xmr_best, xmr_avg = analyze_xmr_target()
        
        # 分析MEMES
        memes_best, memes_avg = analyze_memes_target()
        
        # 总结
        print("\n" + "="*70)
        print("📋 合理买入价总结")
        print("="*70)
        print(f"\nXMR/USDT:")
        print(f"  推荐买入: ${xmr_best:.2f}")
        print(f"  综合价格: ${xmr_avg:.2f}")
        
        print(f"\nMEMES (BSC):")
        print(f"  推荐买入: ${memes_best:.6f}")
        print(f"  综合价格: ${memes_avg:.6f}")
        
        print("\n💡 使用说明:")
        print("  - 这些价格将更新到监控系统")
        print("  - 价格达到或低于建议价时，发送买入信号")
        print("  - 建议分批买入，不要一次性重仓")
        print("="*70)
        
    except Exception as e:
        print(f"\n❌ 分析失败: {e}")
        import traceback
        traceback.print_exc()
