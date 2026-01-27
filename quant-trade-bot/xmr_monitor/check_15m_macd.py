#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import requests
import pandas as pd
from datetime import datetime

print("正在获取XMR 15分钟K线数据...\n")

# 尝试不同的API来获取XMR 15分钟K线
# 由于币安可能没有XMR，我们用其他方式

# 方法1：使用OKX API（如果有XMR）
try:
    # 获取最近100根15分钟K线
    url = "https://www.okx.com/api/v5/market/candles"
    params = {
        'instId': 'XMR-USDT',
        'bar': '15m',
        'limit': 100
    }
    
    r = requests.get(url, params=params, timeout=10)
    
    if r.status_code == 200:
        data = r.json()
        if data.get('code') == '0' and data.get('data'):
            klines = data['data']
            
            # OKX返回格式: [timestamp, open, high, low, close, volume, ...]
            times = [datetime.fromtimestamp(int(k[0])/1000) for k in klines]
            closes = [float(k[4]) for k in klines]
            volumes = [float(k[5]) for k in klines]
            highs = [float(k[2]) for k in klines]
            lows = [float(k[3]) for k in klines]
            
            # 反转数据（从旧到新）
            closes.reverse()
            volumes.reverse()
            times.reverse()
            
            print(f"✅ 成功获取 {len(closes)} 根15分钟K线")
            print(f"时间范围: {times[0].strftime('%Y-%m-%d %H:%M')} 至 {times[-1].strftime('%Y-%m-%d %H:%M')}")
            
            current_price = closes[-1]
            
            # 计算MACD (12, 26, 9)
            df = pd.DataFrame({'close': closes})
            ema12 = df['close'].ewm(span=12, adjust=False).mean()
            ema26 = df['close'].ewm(span=26, adjust=False).mean()
            macd_line = ema12 - ema26
            signal_line = macd_line.ewm(span=9, adjust=False).mean()
            histogram = macd_line - signal_line
            
            # 计算均线
            ma5 = df['close'].rolling(5).mean()
            ma15 = df['close'].rolling(15).mean()
            ma30 = df['close'].rolling(30).mean()
            
            print(f"\n=== XMR 15分钟K线技术分析 ===")
            print(f"当前价格: ${current_price:.2f}")
            print(f"最新时间: {times[-1].strftime('%Y-%m-%d %H:%M')}")
            
            print(f"\n=== MACD指标 (12,26,9) ===")
            print(f"MACD线: {macd_line.iloc[-1]:.3f}")
            print(f"信号线: {signal_line.iloc[-1]:.3f}")
            print(f"柱状图: {histogram.iloc[-1]:.3f}")
            
            # 最近5根柱状图趋势
            recent_hist = histogram.iloc[-5:].values
            print(f"\n最近5根柱状图变化:")
            for i in range(len(recent_hist)):
                if i > 0:
                    change = recent_hist[i] - recent_hist[i-1]
                    trend = "↑" if change > 0 else "↓"
                else:
                    trend = " "
                print(f"  {trend} {recent_hist[i]:.3f}")
            
            # MACD趋势判断
            if histogram.iloc[-1] > histogram.iloc[-2] > histogram.iloc[-3]:
                macd_trend = "🟢 连续上升（好转）"
            elif histogram.iloc[-1] > histogram.iloc[-2]:
                macd_trend = "📈 开始回升"
            elif histogram.iloc[-1] < histogram.iloc[-2] < histogram.iloc[-3]:
                macd_trend = "🔴 持续下降"
            else:
                macd_trend = "🟡 震荡中"
            
            print(f"\nMACD趋势: {macd_trend}")
            
            # 金叉/死叉判断
            if macd_line.iloc[-1] > signal_line.iloc[-1] and macd_line.iloc[-2] <= signal_line.iloc[-2]:
                cross = "🚀 刚金叉！看涨"
            elif macd_line.iloc[-1] < signal_line.iloc[-1] and macd_line.iloc[-2] >= signal_line.iloc[-2]:
                cross = "📉 刚死叉！看跌"
            elif macd_line.iloc[-1] > signal_line.iloc[-1]:
                cross = "✅ 金叉状态"
            else:
                cross = "⚠️ 死叉状态"
            
            print(f"交叉状态: {cross}")
            
            # 柱状图是否转正
            if histogram.iloc[-1] > 0 and histogram.iloc[-2] <= 0:
                hist_signal = "🎉 柱状图转正！"
            elif histogram.iloc[-1] < 0 and histogram.iloc[-2] >= 0:
                hist_signal = "⚠️ 柱状图转负"
            elif histogram.iloc[-1] > 0:
                hist_signal = "在零轴上方"
            else:
                hist_signal = "在零轴下方"
            
            print(f"柱状图位置: {hist_signal}")
            
            print(f"\n=== 均线系统（15分钟）===")
            print(f"MA5:  ${ma5.iloc[-1]:.2f}")
            print(f"MA15: ${ma15.iloc[-1]:.2f}")
            print(f"MA30: ${ma30.iloc[-1]:.2f}")
            print(f"当前: ${current_price:.2f}")
            
            # 均线排列
            if current_price > ma5.iloc[-1] > ma15.iloc[-1]:
                ma_signal = "🚀 多头排列"
            elif current_price < ma5.iloc[-1] < ma15.iloc[-1]:
                ma_signal = "📉 空头排列"
            elif current_price > ma15.iloc[-1]:
                ma_signal = "📈 在MA15上方"
            else:
                ma_signal = "⚠️ 在MA15下方"
            
            print(f"排列: {ma_signal}")
            
            ma15_dist = ((current_price - ma15.iloc[-1]) / ma15.iloc[-1]) * 100
            print(f"距MA15: {ma15_dist:+.2f}%")
            
            print(f"\n=== 成交量分析（最近100根15分钟）===")
            avg_vol = sum(volumes) / len(volumes)
            recent_5_vol = volumes[-5:]
            avg_recent = sum(recent_5_vol) / 5
            
            print(f"100根平均量: ${avg_vol:,.0f}")
            print(f"最近5根平均: ${avg_recent:,.0f}")
            print(f"活跃度: {(avg_recent/avg_vol*100):.0f}%")
            
            # 上涨和下跌时的成交量
            up_vol = []
            down_vol = []
            for i in range(1, len(closes)):
                if closes[i] > closes[i-1]:
                    up_vol.append(volumes[i])
                else:
                    down_vol.append(volumes[i])
            
            avg_up = sum(up_vol) / len(up_vol) if up_vol else 0
            avg_down = sum(down_vol) / len(down_vol) if down_vol else 0
            
            print(f"\n上涨时平均量: ${avg_up:,.0f}")
            print(f"下跌时平均量: ${avg_down:,.0f}")
            
            if avg_up > avg_down * 1.2:
                vol_signal = "🟢 买盘强"
            elif avg_down > avg_up * 1.2:
                vol_signal = "🔴 卖盘强"
            else:
                vol_signal = "🟡 均衡"
            
            print(f"量能: {vol_signal}")
            
            # 综合判断
            print(f"\n=== 综合判断 ===")
            signals = []
            
            if histogram.iloc[-1] > histogram.iloc[-2]:
                signals.append("✅ MACD上升")
            else:
                signals.append("❌ MACD下降")
            
            if macd_line.iloc[-1] > signal_line.iloc[-1]:
                signals.append("✅ MACD金叉")
            else:
                signals.append("❌ MACD死叉")
            
            if current_price > ma15.iloc[-1]:
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
                conclusion = "🟢 有上升趋势"
                advice = "可持仓观望，$470-475减仓30%"
            elif bullish <= 1:
                conclusion = "🔴 下跌趋势"
                advice = "$465反弹立即减仓50%"
            else:
                conclusion = "🟡 震荡"
                advice = "$463以下减仓，$470以上观望"
            
            print(f"\n结论: {conclusion}")
            print(f"建议: {advice}")
            
            # 你的仓位
            entry = 480.43
            margin = 3583.61
            
            print(f"\n=== 你的仓位（开仓${entry:.2f}）===")
            roi = ((current_price - entry) / entry) * 100 * 20
            loss = (roi / 100) * margin
            print(f"当前ROI: {roi:+.1f}%")
            print(f"盈亏: ${loss:+.0f}")
            
            if current_price >= 475:
                risk = "✅ 减仓区"
            elif current_price >= 470:
                risk = "📊 观望区"
            elif current_price >= 463:
                risk = "⚠️ 警戒区"
            else:
                risk = "🚨 危险区"
            
            print(f"风险: {risk}")
            
        else:
            print(f"OKX API返回错误: {data}")
    else:
        print(f"OKX API请求失败: {r.status_code}")

except Exception as e:
    print(f"错误: {e}")
    print("\n由于无法获取15分钟K线，建议:")
    print("1. 手动在交易所查看15分钟MACD")
    print("2. 观察MACD柱状图是否从负转正")
    print("3. 观察价格是否站上MA15均线")
