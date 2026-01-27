# -*- coding: utf-8 -*-
"""
简化多时间框架策略 - 不使用talib
日线趋势 + 15分钟入场
"""

import json
from datetime import datetime

def analyze_trend(data):
    """简单的趋势分析"""
    if len(data) < 20:
        return {'direction': 'neutral', 'strength': 0}
    
    # 简单移动平均
    prices = [float(d['close']) for d in data[-20:]]
    ma5 = sum(prices[-5:]) / 5
    ma20 = sum(prices) / 20
    current_price = prices[-1]
    
    # 趋势判断
    if current_price > ma5 > ma20:
        direction = 'bullish'
        strength = min(0.8, (current_price - ma20) / ma20 * 10)
    elif current_price < ma5 < ma20:
        direction = 'bearish'
        strength = min(0.8, (ma20 - current_price) / ma20 * 10)
    else:
        direction = 'neutral'
        strength = 0
    
    return {
        'direction': direction,
        'strength': strength,
        'current_price': current_price,
        'ma5': ma5,
        'ma20': ma20
    }

def find_entry_signal(data_15m, trend_direction):
    """15分钟入场信号"""
    if len(data_15m) < 10:
        return {'signal': 'hold', 'confidence': 0}
    
    prices = [float(d['close']) for d in data_15m[-10:]]
    volumes = [float(d['volume']) for d in data_15m[-10:]]
    
    current_price = prices[-1]
    prev_price = prices[-2]
    avg_volume = sum(volumes[-5:]) / 5
    current_volume = volumes[-1]
    
    confidence = 0
    signal = 'hold'
    
    # 价格变化
    price_change = (current_price - prev_price) / prev_price
    
    # 成交量确认
    volume_confirm = current_volume > avg_volume * 1.2
    
    if trend_direction == 'bullish':
        if price_change > 0.005 and volume_confirm:  # 0.5%以上上涨且量放大
            signal = 'buy'
            confidence = 0.6
        elif price_change > 0.002:  # 小幅上涨
            signal = 'buy'
            confidence = 0.4
    elif trend_direction == 'bearish':
        if price_change < -0.005 and volume_confirm:  # 0.5%以上下跌且量放大
            signal = 'sell' 
            confidence = 0.6
        elif price_change < -0.002:  # 小幅下跌
            signal = 'sell'
            confidence = 0.4
    
    return {
        'signal': signal,
        'confidence': confidence,
        'price': current_price,
        'price_change': price_change,
        'volume_confirm': volume_confirm
    }

def simulate_multi_timeframe_analysis():
    """模拟多时间框架分析"""
    print("🎯 多时间框架策略分析")
    print("=" * 50)
    
    # 模拟数据（实际应用中从交易所获取）
    symbols = ['BTC/USDT', 'ETH/USDT']
    
    results = []
    
    for symbol in symbols:
        print(f"\n📊 分析 {symbol}")
        
        # 模拟日线数据（简化）
        daily_data = []
        base_price = 45000 if 'BTC' in symbol else 2500
        
        for i in range(30):
            price = base_price * (1 + (i * 0.002))  # 模拟上涨趋势
            daily_data.append({
                'close': str(price),
                'volume': str(1000 + i * 10)
            })
        
        # 模拟15分钟数据
        m15_data = []
        for i in range(20):
            price = base_price * 1.05 * (1 + (i * 0.001))
            m15_data.append({
                'close': str(price),
                'volume': str(500 + i * 5)
            })
        
        # 分析趋势
        trend = analyze_trend(daily_data)
        print(f"📈 日线趋势: {trend['direction']} (强度: {trend['strength']:.2f})")
        
        # 寻找入场信号
        entry = find_entry_signal(m15_data, trend['direction'])
        print(f"⚡ 15分钟信号: {entry['signal']} (信心: {entry['confidence']:.2f})")
        
        if entry['signal'] != 'hold' and entry['confidence'] > 0.3:
            position_size = 1000 * entry['confidence']  # 根据信心度计算仓位
            
            analysis_result = {
                'symbol': symbol,
                'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'trend': trend,
                'entry': entry,
                'recommended_position': position_size,
                'strategy': 'multi_timeframe_1d_15m'
            }
            results.append(analysis_result)
            
            print(f"💰 建议仓位: ${position_size:.2f}")
            print(f"📊 当前价格: ${entry['price']:.2f}")
    
    # 保存结果
    if results:
        filename = 'multi_timeframe_analysis.json'
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2)
        
        print(f"\n✅ 发现 {len(results)} 个交易机会")
        print(f"📁 结果已保存到: {filename}")
        
        # 总结
        print("\n📋 策略总结:")
        print("🕐 时间框架: 日线看趋势 + 15分钟找入场")
        print("📊 信号确认: 价格突破 + 成交量放大")
        print("💰 仓位管理: 根据信心度分配")
        
    else:
        print("\n⏸️  当前无明确交易机会")
        
    return results

if __name__ == "__main__":
    print("🚀 简化多时间框架策略测试")
    print("💡 不依赖复杂指标库，使用基础技术分析")
    
    try:
        results = simulate_multi_timeframe_analysis()
        
        # 生成交易建议
        if results:
            print(f"\n🎯 交易建议:")
            for i, result in enumerate(results, 1):
                print(f"  {i}. {result['symbol']}: {result['entry']['signal'].upper()}")
                print(f"     入场价格: ${result['entry']['price']:.2f}")
                print(f"     信心度: {result['entry']['confidence']:.1%}")
        
    except Exception as e:
        print(f"\n❌ 分析出错: {e}")
        
    print(f"\n⏰ 分析完成 - {datetime.now().strftime('%H:%M:%S')}")