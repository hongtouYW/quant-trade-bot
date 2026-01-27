#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests
import json

# 使用CoinGecko获取XMR价格历史
url = "https://api.coingecko.com/api/v3/coins/monero/market_chart"
params = {'vs_currency': 'usd', 'days': '15', 'interval': 'hourly'}

print("正在获取XMR 15天K线数据...")
r = requests.get(url, params=params)

if r.status_code == 200:
    data = r.json()
    
    # 提取价格和成交量
    prices_data = data.get('prices', [])
    volumes_data = data.get('total_volumes', [])
    
    if prices_data:
        # 取最近100条数据
        recent_100 = prices_data[-100:]
        recent_vol = volumes_data[-100:]
        
        prices = [p[1] for p in recent_100]
        volumes = [v[1] for v in recent_vol]
        
        current_price = prices[-1]
        
        # 计算简单MACD（EMA12, EMA26, Signal9）
        def calculate_ema(data, period):
            multiplier = 2 / (period + 1)
            ema = [sum(data[:period]) / period]
            for price in data[period:]:
                ema.append((price - ema[-1]) * multiplier + ema[-1])
            return ema
        
        # 计算EMA
        ema12 = calculate_ema(prices, 12)
        ema26 = calculate_ema(prices, 26)
        
        # MACD线 = EMA12 - EMA26
        macd = [ema12[i] - ema26[i] for i in range(len(ema26))]
        
        # 信号线 = MACD的9日EMA
        signal = calculate_ema(macd, 9)
        
        # 柱状图 = MACD - Signal
        histogram = [macd[i] - signal[i] for i in range(len(signal))]
        
        # 计算均线
        def calculate_ma(data, period):
            if len(data) < period:
                return None
            return sum(data[-period:]) / period
        
        ma5 = calculate_ma(prices, 5)
        ma15 = calculate_ma(prices, 15)
        ma30 = calculate_ma(prices, 30)
        
        print(f"\n=== XMR 技术分析 ===")
        print(f"当前价格: ${current_price:.2f}")
        print(f"数据点数: {len(prices)}")
        
        print(f"\n=== MACD 指标 ===")
        print(f"MACD线: {macd[-1]:.2f}")
        print(f"信号线: {signal[-1]:.2f}")
        print(f"柱状图: {histogram[-1]:.2f}")
        
        # 最近5根柱状图
        recent_hist = histogram[-5:]
        print(f"\n最近5根柱状图:")
        for i, h in enumerate(recent_hist):
            trend = "↑" if i > 0 and h > recent_hist[i-1] else "↓" if i > 0 else "-"
            print(f"  {trend} {h:.2f}")
        
        # 判断趋势
        if histogram[-1] > histogram[-2] > histogram[-3]:
            macd_trend = "🟢 连续上升（好转中）"
        elif histogram[-1] > histogram[-2]:
            macd_trend = "📈 开始回升"
        elif histogram[-1] < histogram[-2] < histogram[-3]:
            macd_trend = "🔴 持续下降"
        else:
            macd_trend = "🟡 震荡中"
        
        print(f"\nMACD趋势: {macd_trend}")
        
        # 金叉/死叉
        if macd[-1] > signal[-1] and macd[-2] <= signal[-2]:
            cross = "🚀 刚刚金叉！看涨"
        elif macd[-1] < signal[-1] and macd[-2] >= signal[-2]:
            cross = "📉 刚刚死叉！看跌"
        elif macd[-1] > signal[-1]:
            cross = "✅ 金叉状态（MACD在信号线上方）"
        else:
            cross = "⚠️ 死叉状态（MACD在信号线下方）"
        
        print(f"交叉状态: {cross}")
        
        print(f"\n=== 均线系统 ===")
        print(f"MA5:  ${ma5:.2f}")
        print(f"MA15: ${ma15:.2f}")
        print(f"MA30: ${ma30:.2f}")
        print(f"当前: ${current_price:.2f}")
        
        # 均线排列
        if current_price > ma5 > ma15:
            ma_signal = "🚀 多头排列"
        elif current_price < ma5 < ma15:
            ma_signal = "📉 空头排列"
        elif current_price > ma15:
            ma_signal = "📈 在15均线上方"
        else:
            ma_signal = "⚠️ 在15均线下方"
        
        print(f"排列: {ma_signal}")
        print(f"距15均线: {((current_price - ma15) / ma15 * 100):+.2f}%")
        
        print(f"\n=== 成交量分析（最近100小时）===")
        avg_vol = sum(volumes) / len(volumes)
        recent_vol_5 = volumes[-5:]
        avg_recent = sum(recent_vol_5) / 5
        
        print(f"100小时平均量: ${avg_vol:,.0f}")
        print(f"最近5小时平均: ${avg_recent:,.0f}")
        print(f"活跃度: {(avg_recent / avg_vol * 100):.0f}%")
        
        # 统计涨跌时的成交量
        up_vol = []
        down_vol = []
        for i in range(1, len(prices)):
            if prices[i] > prices[i-1]:
                up_vol.append(volumes[i])
            else:
                down_vol.append(volumes[i])
        
        avg_up = sum(up_vol) / len(up_vol) if up_vol else 0
        avg_down = sum(down_vol) / len(down_vol) if down_vol else 0
        
        print(f"\n上涨时平均量: ${avg_up:,.0f}")
        print(f"下跌时平均量: ${avg_down:,.0f}")
        
        if avg_up > avg_down * 1.2:
            vol_signal = "🟢 买盘强（上涨放量）"
        elif avg_down > avg_up * 1.2:
            vol_signal = "🔴 卖盘强（下跌放量）"
        else:
            vol_signal = "🟡 买卖均衡"
        
        print(f"量能: {vol_signal}")
        
        # 综合判断
        print(f"\n=== 综合判断 ===")
        signals = []
        
        if histogram[-1] > histogram[-2]:
            signals.append("✅ MACD上升")
        else:
            signals.append("❌ MACD下降")
        
        if macd[-1] > signal[-1]:
            signals.append("✅ MACD金叉")
        else:
            signals.append("❌ MACD死叉")
        
        if current_price > ma15:
            signals.append("✅ 价格>MA15")
        else:
            signals.append("❌ 价格<MA15")
        
        if avg_up > avg_down:
            signals.append("✅ 买盘强")
        else:
            signals.append("❌ 卖盘强")
        
        for s in signals:
            print(f"  {s}")
        
        bullish = sum(1 for s in signals if '✅' in s)
        
        if bullish >= 3:
            conclusion = "🟢 偏多，有上升趋势可能"
            advice = "可以持仓观望，等$470-475反弹减仓"
        elif bullish <= 1:
            conclusion = "🔴 偏空，下跌压力大"
            advice = "建议$465附近减仓或设严格止损$460"
        else:
            conclusion = "🟡 方向不明，震荡中"
            advice = "$463以下减仓，$470以上持仓"
        
        print(f"\n结论: {conclusion}")
        print(f"建议: {advice}")
        
        # 你的仓位
        entry = 480.43
        print(f"\n=== 你的仓位 (开仓${entry:.2f}) ===")
        roi = ((current_price - entry) / entry) * 100 * 20
        print(f"当前ROI: {roi:+.1f}%")
        
        if current_price >= 475:
            risk = "✅ 可减仓区域"
        elif current_price >= 470:
            risk = "📊 观望区域"
        elif current_price >= 463:
            risk = "⚠️ 警戒区域"
        else:
            risk = "🚨 危险区域"
        
        print(f"风险: {risk}")
        
    else:
        print("未获取到价格数据")
else:
    print(f"API请求失败: {r.status_code}")
