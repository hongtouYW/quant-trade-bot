#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ADA市场分析 - 实盘模拟交易监控"""

import requests
import json
from datetime import datetime

def get_ada_market_data():
    """获取ADA市场数据"""
    try:
        # CoinGecko API
        url = 'https://api.coingecko.com/api/v3/simple/price?ids=cardano&vs_currencies=usd&include_24hr_change=true'
        response = requests.get(url, timeout=10)
        if response.status_code == 200:
            data = response.json()
            return {
                'price': data['cardano']['usd'],
                'change_24h': data['cardano']['usd_24h_change']
            }
    except Exception as e:
        print(f"CoinGecko失败: {e}")
    
    # 备用：Binance API
    try:
        url = 'https://api.binance.com/api/v3/ticker/price?symbol=ADAUSDT'
        response = requests.get(url, timeout=10)
        data = response.json()
        return {'price': float(data['price']), 'change_24h': 0}
    except Exception as e:
        return None

def ada_technical_analysis(current_price):
    """ADA技术分析"""
    
    # 关键价位
    levels = {
        'strong_resistance': 1.00,   # 强阻力
        'mid_resistance': 0.80,      # 中阻力  
        'weak_resistance': 0.60,     # 弱阻力
        'weak_support': 0.40,        # 弱支撑
        'mid_support': 0.30,         # 中支撑
        'strong_support': 0.20       # 强支撑
    }
    
    # 历史参考
    historical_entry = 0.3478  # 历史做空价格
    
    print("🎯 ADA/USDT 技术分析")
    print("=" * 50)
    print(f"💰 当前价格: ${current_price:.4f}")
    print(f"📊 历史做空价: ${historical_entry:.4f}")
    print(f"📈 价格变化: {((current_price - historical_entry) / historical_entry) * 100:+.2f}%")
    
    print(f"\n🎯 关键价位分析:")
    print(f"强阻力: ${levels['strong_resistance']:.2f} (心理关口)")
    print(f"中阻力: ${levels['mid_resistance']:.2f}")  
    print(f"弱阻力: ${levels['weak_resistance']:.2f}")
    print(f"当前位: ${current_price:.4f}")
    print(f"弱支撑: ${levels['weak_support']:.2f}")
    print(f"中支撑: ${levels['mid_support']:.2f}")
    print(f"强支撑: ${levels['strong_support']:.2f}")
    
    # 位置判断
    if current_price > levels['weak_resistance']:
        position = "高位区间"
        risk = "高"
        strategy = "观望或轻仓做空"
    elif current_price > levels['weak_support']:
        position = "中位区间" 
        risk = "中"
        strategy = "区间交易"
    else:
        position = "低位区间"
        risk = "低"
        strategy = "考虑做多"
        
    print(f"\n📊 当前位置: {position}")
    print(f"⚠️ 风险等级: {risk}")
    print(f"💡 策略建议: {strategy}")
    
    return levels, position, risk, strategy

def ada_trading_suggestion(current_price, change_24h):
    """ADA交易建议"""
    
    levels, position, risk, strategy = ada_technical_analysis(current_price)
    
    print(f"\n💼 实盘模拟交易建议:")
    print("=" * 50)
    
    # 基于价格位置的具体建议
    if current_price > 0.60:
        print("🔴 做空信号:")
        print(f"   入场价: ${current_price:.4f}")
        print(f"   止损: ${current_price * 1.05:.4f} (+5%)")
        print(f"   止盈: ${current_price * 0.90:.4f} (-10%)")
        print(f"   杠杆: 3x (保守)")
        print(f"   仓位: 5-10% (轻仓试探)")
        
    elif current_price < 0.35:
        print("🟢 做多信号:")
        print(f"   入场价: ${current_price:.4f}")
        print(f"   止损: ${current_price * 0.90:.4f} (-10%)")
        print(f"   止盈: ${current_price * 1.20:.4f} (+20%)")
        print(f"   杠杆: 5x")
        print(f"   仓位: 10-15% (中等仓位)")
        
    else:
        print("⚡ 区间震荡:")
        print(f"   策略: 高抛低吸")
        print(f"   做空位: ${0.55:.2f} 附近")
        print(f"   做多位: ${0.35:.2f} 附近")
        print(f"   仓位: 5% (小仓位)")
        
    # 市场情绪判断
    if change_24h > 10:
        sentiment = "极度贪婪"
    elif change_24h > 5:
        sentiment = "贪婪"
    elif change_24h > -5:
        sentiment = "中性"
    elif change_24h > -10:
        sentiment = "恐慌"
    else:
        sentiment = "极度恐慌"
        
    print(f"\n📊 市场情绪: {sentiment} (24H: {change_24h:+.2f}%)")
    
    # 风险提示
    print(f"\n⚠️ 风险提示:")
    print(f"   - ADA属于山寨币，波动较大")
    print(f"   - 当前位置风险等级: {risk}")  
    print(f"   - 建议使用模拟交易测试策略")
    print(f"   - 严格执行止损，控制风险")

def main():
    """主函数"""
    print("🚀 ADA实盘模拟交易监控系统")
    print(f"⏰ 分析时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60)
    
    # 获取市场数据
    market_data = get_ada_market_data()
    
    if not market_data:
        print("❌ 无法获取ADA价格数据")
        return
        
    current_price = market_data['price']
    change_24h = market_data.get('change_24h', 0)
    
    print(f"💰 ADA/USDT: ${current_price:.4f}")
    print(f"📊 24H变化: {change_24h:+.2f}%")
    
    # 技术分析和交易建议
    ada_trading_suggestion(current_price, change_24h)
    
    print("\n" + "=" * 60)
    print("📱 监控建议:")
    print("   - 设置价格预警: $0.35, $0.45, $0.60")
    print("   - 关注成交量变化")
    print("   - 跟踪BTC走势（ADA与BTC相关性较高）")
    print("   - 定期更新止损价位")

if __name__ == "__main__":
    main()