# -*- coding: utf-8 -*-
"""
XMRUSDT 专用监控系统
实时监控价格、技术指标和交易机会
"""

import ccxt
import pandas as pd
import numpy as np
import time
import json
import requests
import signal
import sys
from datetime import datetime, timedelta
from flask import Flask, render_template_string, jsonify

class XMRMonitor:
    """XMRUSDT专用监控器"""
    
    def __init__(self):
        self.symbol = 'XMR/USDT'
        self.exchange = ccxt.binance({
            'apiKey': 'NyxDEPtbMbsxE3fsoHVLVqkp11T5xX1zSnWlVCHnHfKqc9I5xE78cZgOGon5BQ7H',
            'secret': 'Kr8sY3nAMHxsJxxQvgsOGGFaRUumYK8Hcwq93fC4jSSqvF87QDjLr4RVxLJz5HKp',
            'enableRateLimit': True,
            'timeout': 30000,
            'sandbox': False
        })
        
        # 风险管理参数
        self.risk_level = 0.02  # 2%风险
        self.atr_multiplier = 2.0  # ATR倍数用于止损
        self.profit_target_ratio = 2.5  # 风险收益比
        
        # 监控数据存储
        self.current_data = {}
        self.analysis_history = []
        
        print(f"🔍 XMRUSDT 监控系统启动")
        print(f"📊 交易所: Binance")
        print(f"⚡ 实时更新中...")
    
    def fetch_market_data(self):
        """获取市场数据"""
        try:
            # 获取实时ticker
            ticker = self.exchange.fetch_ticker(self.symbol)
            
            # 获取K线数据
            ohlcv_1h = self.exchange.fetch_ohlcv(self.symbol, '1h', limit=100)
            ohlcv_4h = self.exchange.fetch_ohlcv(self.symbol, '4h', limit=50)
            ohlcv_1d = self.exchange.fetch_ohlcv(self.symbol, '1d', limit=30)
            
            # 转换为DataFrame
            df_1h = pd.DataFrame(ohlcv_1h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df_4h = pd.DataFrame(ohlcv_4h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df_1d = pd.DataFrame(ohlcv_1d, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            
            # 转换时间戳
            for df in [df_1h, df_4h, df_1d]:
                df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
                df.set_index('timestamp', inplace=True)
            
            return {
                'ticker': ticker,
                '1h': df_1h,
                '4h': df_4h,
                '1d': df_1d
            }
            
        except Exception as e:
            print(f"❌ 获取数据失败: {e}")
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
        df['macd_histogram'] = df['macd'] - df['macd_signal']
        
        # 布林带
        df['bb_middle'] = df['close'].rolling(window=20).mean()
        bb_std = df['close'].rolling(window=20).std()
        df['bb_upper'] = df['bb_middle'] + (bb_std * 2)
        df['bb_lower'] = df['bb_middle'] - (bb_std * 2)
        
        # ATR (真实波动范围)
        df['high_low'] = df['high'] - df['low']
        df['high_close'] = np.abs(df['high'] - df['close'].shift())
        df['low_close'] = np.abs(df['low'] - df['close'].shift())
        df['tr'] = df[['high_low', 'high_close', 'low_close']].max(axis=1)
        df['atr'] = df['tr'].rolling(window=14).mean()
        
        # 成交量指标
        df['volume_ma'] = df['volume'].rolling(window=20).mean()
        df['volume_ratio'] = df['volume'] / df['volume_ma']
        
        return df
    
    def analyze_trend(self, df_1h, df_4h, df_1d):
        """分析多时间框架趋势"""
        current_price = df_1h['close'].iloc[-1]
        
        analysis = {
            'current_price': current_price,
            'timestamp': datetime.now(),
            'timeframes': {}
        }
        
        # 日线趋势（主趋势）
        df_1d_latest = df_1d.iloc[-1]
        daily_trend = 'neutral'
        if current_price > df_1d_latest['ma20']:
            daily_trend = 'bullish'
        elif current_price < df_1d_latest['ma20']:
            daily_trend = 'bearish'
        
        analysis['timeframes']['1d'] = {
            'trend': daily_trend,
            'price_vs_ma20': (current_price - df_1d_latest['ma20']) / df_1d_latest['ma20'] * 100,
            'rsi': df_1d_latest['rsi'],
            'volume_ratio': df_1d_latest['volume_ratio']
        }
        
        # 4小时趋势（中期趋势）
        df_4h_latest = df_4h.iloc[-1]
        h4_trend = 'neutral'
        if df_4h_latest['ma5'] > df_4h_latest['ma20']:
            h4_trend = 'bullish'
        elif df_4h_latest['ma5'] < df_4h_latest['ma20']:
            h4_trend = 'bearish'
        
        analysis['timeframes']['4h'] = {
            'trend': h4_trend,
            'ma5_vs_ma20': (df_4h_latest['ma5'] - df_4h_latest['ma20']) / df_4h_latest['ma20'] * 100,
            'rsi': df_4h_latest['rsi'],
            'macd': df_4h_latest['macd'],
            'macd_signal': df_4h_latest['macd_signal']
        }
        
        # 1小时趋势（短期趋势）
        df_1h_latest = df_1h.iloc[-1]
        h1_trend = 'neutral'
        if df_1h_latest['close'] > df_1h_latest['ma10'] and df_1h_latest['rsi'] > 50:
            h1_trend = 'bullish'
        elif df_1h_latest['close'] < df_1h_latest['ma10'] and df_1h_latest['rsi'] < 50:
            h1_trend = 'bearish'
        
        analysis['timeframes']['1h'] = {
            'trend': h1_trend,
            'rsi': df_1h_latest['rsi'],
            'bb_position': self._get_bb_position(df_1h_latest),
            'volume_surge': df_1h_latest['volume_ratio'] > 1.5
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
    
    def calculate_stop_loss_take_profit(self, df_1h, entry_price, signal_type):
        """计算止损止盈点位"""
        current_atr = df_1h['atr'].iloc[-1]
        
        if signal_type == 'long':
            # 做多止损点
            stop_loss = entry_price - (current_atr * self.atr_multiplier)
            take_profit = entry_price + (current_atr * self.atr_multiplier * self.profit_target_ratio)
            
            # 支撑位参考
            recent_lows = df_1h['low'].rolling(window=20).min().iloc[-1]
            support_stop = max(stop_loss, recent_lows * 0.995)  # 0.5%缓冲
            
        else:  # short
            # 做空止损点
            stop_loss = entry_price + (current_atr * self.atr_multiplier)
            take_profit = entry_price - (current_atr * self.atr_multiplier * self.profit_target_ratio)
            
            # 阻力位参考
            recent_highs = df_1h['high'].rolling(window=20).max().iloc[-1]
            resistance_stop = min(stop_loss, recent_highs * 1.005)  # 0.5%缓冲
            stop_loss = resistance_stop
        
        risk_amount = abs(entry_price - stop_loss)
        reward_amount = abs(take_profit - entry_price)
        risk_reward_ratio = reward_amount / risk_amount if risk_amount > 0 else 0
        
        return {
            'entry_price': entry_price,
            'stop_loss': stop_loss,
            'take_profit': take_profit,
            'risk_amount': risk_amount,
            'reward_amount': reward_amount,
            'risk_reward_ratio': risk_reward_ratio,
            'risk_percentage': (risk_amount / entry_price) * 100,
            'reward_percentage': (reward_amount / entry_price) * 100
        }
    
    def generate_trading_signals(self, analysis, df_1h, df_4h, df_1d):
        """生成交易信号"""
        signals = {
            'timestamp': datetime.now(),
            'overall_signal': 'hold',
            'confidence': 0,
            'signals': [],
            'risk_level': 'medium',
            'entry_suggestions': []
        }
        
        daily_trend = analysis['timeframes']['1d']['trend']
        h4_trend = analysis['timeframes']['4h']['trend']
        h1_trend = analysis['timeframes']['1h']['trend']
        
        current_price = analysis['current_price']
        h1_rsi = analysis['timeframes']['1h']['rsi']
        h4_rsi = analysis['timeframes']['4h']['rsi']
        
        # 信号判断逻辑
        signal_strength = 0
        
        # 趋势一致性检查
        if daily_trend == 'bullish' and h4_trend == 'bullish':
            if h1_rsi < 70 and h1_trend != 'bearish':
                signals['signals'].append("日线+4H多头趋势确立")
                signal_strength += 3
                signals['overall_signal'] = 'buy'
        
        elif daily_trend == 'bearish' and h4_trend == 'bearish':
            if h1_rsi > 30 and h1_trend != 'bullish':
                signals['signals'].append("日线+4H空头趋势确立")
                signal_strength += 3
                signals['overall_signal'] = 'sell'
        
        # RSI信号
        if h1_rsi < 30:
            signals['signals'].append(f"1H RSI超卖({h1_rsi:.1f})")
            signal_strength += 1
            if signals['overall_signal'] == 'hold':
                signals['overall_signal'] = 'buy_oversold'
        
        elif h1_rsi > 70:
            signals['signals'].append(f"1H RSI超买({h1_rsi:.1f})")
            signal_strength += 1
            if signals['overall_signal'] == 'hold':
                signals['overall_signal'] = 'sell_overbought'
        
        # 布林带信号
        bb_pos = analysis['timeframes']['1h']['bb_position']
        if bb_pos == 'below_lower':
            signals['signals'].append("价格触及布林下轨")
            signal_strength += 1
        elif bb_pos == 'above_upper':
            signals['signals'].append("价格触及布林上轨")
            signal_strength += 1
        
        # 成交量确认
        if analysis['timeframes']['1h']['volume_surge']:
            signals['signals'].append("成交量放大")
            signal_strength += 1
        
        # 设置信心度
        signals['confidence'] = min(signal_strength * 20, 100)
        
        # 风险等级
        volatility = df_1h['atr'].iloc[-1] / current_price * 100
        if volatility > 5:
            signals['risk_level'] = 'high'
        elif volatility < 2:
            signals['risk_level'] = 'low'
        
        # 入场建议
        if signals['overall_signal'] in ['buy', 'buy_oversold']:
            risk_reward = self.calculate_stop_loss_take_profit(df_1h, current_price, 'long')
            signals['entry_suggestions'].append({
                'type': 'long',
                'entry_zone': [current_price * 0.998, current_price * 1.002],
                'stop_loss': risk_reward['stop_loss'],
                'take_profit': risk_reward['take_profit'],
                'risk_reward': risk_reward['risk_reward_ratio']
            })
        
        elif signals['overall_signal'] in ['sell', 'sell_overbought']:
            risk_reward = self.calculate_stop_loss_take_profit(df_1h, current_price, 'short')
            signals['entry_suggestions'].append({
                'type': 'short',
                'entry_zone': [current_price * 0.998, current_price * 1.002],
                'stop_loss': risk_reward['stop_loss'],
                'take_profit': risk_reward['take_profit'],
                'risk_reward': risk_reward['risk_reward_ratio']
            })
        
        return signals
    
    def run_analysis(self):
        """运行完整分析"""
        print(f"\n🔍 {datetime.now().strftime('%H:%M:%S')} XMRUSDT 分析")
        print("-" * 50)
        
        # 获取数据
        data = self.fetch_market_data()
        if not data:
            return None
        
        # 计算技术指标
        for timeframe in ['1h', '4h', '1d']:
            data[timeframe] = self.calculate_technical_indicators(data[timeframe])
        
        # 分析趋势
        analysis = self.analyze_trend(data['1h'], data['4h'], data['1d'])
        
        # 生成信号
        signals = self.generate_trading_signals(analysis, data['1h'], data['4h'], data['1d'])
        
        # 保存分析结果
        self.current_data = {
            'analysis': analysis,
            'signals': signals,
            'ticker': data['ticker'],
            'technical_data': {
                '1h': data['1h'].iloc[-1].to_dict(),
                '4h': data['4h'].iloc[-1].to_dict(),
                '1d': data['1d'].iloc[-1].to_dict()
            }
        }
        
        # 打印结果
        self.print_analysis_report()
        
        return self.current_data
    
    def print_analysis_report(self):
        """打印分析报告"""
        if not self.current_data:
            return
        
        analysis = self.current_data['analysis']
        signals = self.current_data['signals']
        ticker = self.current_data['ticker']
        
        # 基本信息
        print(f"💰 当前价格: ${analysis['current_price']:.4f}")
        print(f"📈 24H变化: {ticker['percentage']:.2f}%")
        print(f"💎 24H成交量: {ticker['quoteVolume']:,.0f} USDT")
        
        # 多时间框架趋势
        print(f"\n📊 多时间框架趋势:")
        for tf, tf_data in analysis['timeframes'].items():
            trend_emoji = "🟢" if tf_data['trend'] == 'bullish' else "🔴" if tf_data['trend'] == 'bearish' else "🟡"
            print(f"   {tf}: {trend_emoji} {tf_data['trend']} (RSI: {tf_data['rsi']:.1f})")
        
        # 交易信号
        signal_color = "🟢" if 'buy' in signals['overall_signal'] else "🔴" if 'sell' in signals['overall_signal'] else "🟡"
        print(f"\n🎯 交易信号: {signal_color} {signals['overall_signal'].upper()}")
        print(f"🎯 信心度: {signals['confidence']:.0f}%")
        print(f"⚠️ 风险等级: {signals['risk_level']}")
        
        if signals['signals']:
            print(f"📋 信号详情:")
            for signal in signals['signals']:
                print(f"   • {signal}")
        
        # 入场建议
        if signals['entry_suggestions']:
            print(f"\n📍 入场建议:")
            for suggestion in signals['entry_suggestions']:
                print(f"   {suggestion['type'].upper()}:")
                print(f"   💰 入场区间: ${suggestion['entry_zone'][0]:.4f} - ${suggestion['entry_zone'][1]:.4f}")
                print(f"   🛡️ 止损: ${suggestion['stop_loss']:.4f}")
                print(f"   🎯 止盈: ${suggestion['take_profit']:.4f}")
                print(f"   ⚖️ 风险收益比: 1:{suggestion['risk_reward']:.2f}")
    
    def start_continuous_monitoring(self, interval=300):
        """开始连续监控 - 网络断开时自动重连"""
        print(f"\n🚀 开始连续监控 XMRUSDT")
        print(f"⏰ 更新间隔: {interval//60}分钟")
        print("🌐 网络断开时将自动重试")
        print("按 Ctrl+C 停止监控")
        
        consecutive_errors = 0
        max_consecutive_errors = 5
        
        try:
            while True:
                try:
                    # 运行分析
                    result = self.run_analysis()
                    
                    if result:
                        # 成功获取数据，重置错误计数
                        consecutive_errors = 0
                        
                        # 保存历史记录
                        self.analysis_history.append({
                            'timestamp': datetime.now().isoformat(),
                            'price': self.current_data['analysis']['current_price'],
                            'signal': self.current_data['signals']['overall_signal'],
                            'confidence': self.current_data['signals']['confidence']
                        })
                        
                        print(f"\n⏰ 下次更新: {(datetime.now() + timedelta(seconds=interval)).strftime('%H:%M:%S')}")
                    else:
                        consecutive_errors += 1
                        print(f"⚠️ 获取数据失败 ({consecutive_errors}/{max_consecutive_errors})")
                    
                    # 正常等待间隔
                    time.sleep(interval)
                    
                except Exception as e:
                    consecutive_errors += 1
                    print(f"❌ 监控出错: {e}")
                    print(f"🔄 错误次数: {consecutive_errors}/{max_consecutive_errors}")
                    
                    if consecutive_errors >= max_consecutive_errors:
                        print(f"⚠️ 连续{max_consecutive_errors}次错误，增加重试间隔")
                        # 逐渐增加重试间隔，最多等待5分钟
                        wait_time = min(300, 30 * consecutive_errors)
                        print(f"⏳ 等待{wait_time}秒后重试...")
                        time.sleep(wait_time)
                        consecutive_errors = 0  # 重置计数器
                    else:
                        # 短暂等待后重试
                        wait_time = 30
                        print(f"⏳ {wait_time}秒后重试...")
                        time.sleep(wait_time)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")
            self.save_analysis_history()
        except Exception as e:
            print(f"\n❌ 监控系统严重错误: {e}")
            self.save_analysis_history()
    
    def save_analysis_history(self):
        """保存分析历史"""
        filename = f"xmr_analysis_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump({
                'symbol': self.symbol,
                'analysis_history': self.analysis_history,
                'final_analysis': self.current_data
            }, f, ensure_ascii=False, indent=2)
        print(f"📁 分析历史已保存: {filename}")
    
    def check_network_connection(self):
        """检查网络连接"""
        try:
            response = requests.get('https://api.binance.com/api/v3/ping', timeout=10)
            return response.status_code == 200
        except:
            return False
    
    def start_daemon_monitoring(self, interval=300):
        """启动守护进程监控 - 永不停止"""
        print(f"\n🔄 启动守护进程监控模式")
        print(f"⏰ 更新间隔: {interval//60}分钟") 
        print(f"🌐 网络断开时自动等待重连")
        print(f"💾 监控数据自动保存")
        print("=" * 50)
        
        # 设置信号处理器
        def signal_handler(sig, frame):
            print(f"\n🛑 收到停止信号，正在保存数据...")
            self.save_analysis_history()
            print("👋 守护进程已停止")
            sys.exit(0)
        
        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)
        
        error_count = 0
        last_success_time = None
        
        while True:
            try:
                # 检查网络连接
                if not self.check_network_connection():
                    print(f"🌐 网络连接检查失败，等待30秒重试...")
                    time.sleep(30)
                    continue
                
                # 运行分析
                result = self.run_analysis()
                
                if result:
                    error_count = 0
                    last_success_time = datetime.now()
                    
                    # 保存历史记录
                    self.analysis_history.append({
                        'timestamp': datetime.now().isoformat(),
                        'price': self.current_data['analysis']['current_price'],
                        'signal': self.current_data['signals']['overall_signal'],
                        'confidence': self.current_data['signals']['confidence']
                    })
                    
                    # 每小时自动保存一次
                    if len(self.analysis_history) % 12 == 0:  # 假设5分钟间隔
                        self.save_analysis_history()
                        print(f"💾 自动保存完成 (记录: {len(self.analysis_history)})")
                    
                    print(f"✅ 监控正常 - 下次更新: {(datetime.now() + timedelta(seconds=interval)).strftime('%H:%M:%S')}")
                else:
                    error_count += 1
                    print(f"⚠️ 数据获取失败 (错误{error_count}次)")
                
            except Exception as e:
                error_count += 1
                print(f"❌ 监控异常: {e} (错误{error_count}次)")
                
                # 如果错误过多，增加等待时间
                if error_count > 10:
                    wait_time = min(600, 60 * (error_count - 10))  # 最多等10分钟
                    print(f"⏳ 错误过多，等待{wait_time//60}分{wait_time%60}秒...")
                    time.sleep(wait_time)
                    error_count = 0
                    continue
            
            # 正常等待
            time.sleep(interval)

# Web Dashboard
app = Flask(__name__)
monitor = XMRMonitor()

@app.route('/')
def dashboard():
    """Web监控面板"""
    return render_template_string('''
<!DOCTYPE html>
<html>
<head>
    <title>XMRUSDT 专用监控</title>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="30">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff; margin: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        .price-card { 
            background: rgba(255,255,255,0.1); 
            padding: 20px; border-radius: 15px; 
            margin-bottom: 20px; backdrop-filter: blur(10px);
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .signal-card { background: rgba(0,255,0,0.1); padding: 15px; border-radius: 10px; }
        .bear-signal { background: rgba(255,0,0,0.1); }
        .neutral-signal { background: rgba(255,255,0,0.1); }
        .indicator { display: flex; justify-content: space-between; margin: 5px 0; }
        .positive { color: #4CAF50; }
        .negative { color: #f44336; }
        .neutral { color: #FFA726; }
        .update-time { text-align: center; color: #888; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 XMRUSDT 专用监控面板</h1>
            <div class="price-card">
                <h2>💰 当前价格: $<span id="current-price">{{ data.price if data else 'Loading...' }}</span></h2>
                <div class="indicator">
                    <span>24H变化:</span>
                    <span class="{{ 'positive' if data and data.change > 0 else 'negative' }}">
                        {{ '{:+.2f}%'.format(data.change) if data else 'N/A' }}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="grid">
            <div class="signal-card {{ 'bear-signal' if data and 'sell' in data.signal else 'neutral-signal' if data and data.signal == 'hold' else '' }}">
                <h3>🎯 交易信号</h3>
                <div class="indicator">
                    <span>信号:</span>
                    <span><strong>{{ data.signal.upper() if data else 'LOADING' }}</strong></span>
                </div>
                <div class="indicator">
                    <span>信心度:</span>
                    <span>{{ data.confidence if data else 0 }}%</span>
                </div>
            </div>
            
            <div class="price-card">
                <h3>📊 技术指标</h3>
                <div class="indicator">
                    <span>RSI (1H):</span>
                    <span class="{{ 'positive' if data and data.rsi_1h > 70 else 'negative' if data and data.rsi_1h < 30 else 'neutral' }}">
                        {{ '{:.1f}'.format(data.rsi_1h) if data else 'N/A' }}
                    </span>
                </div>
                <div class="indicator">
                    <span>趋势 (日线):</span>
                    <span class="{{ 'positive' if data and data.daily_trend == 'bullish' else 'negative' if data and data.daily_trend == 'bearish' else 'neutral' }}">
                        {{ data.daily_trend.upper() if data else 'N/A' }}
                    </span>
                </div>
            </div>
            
            <div class="price-card">
                <h3>🎯 止损止盈建议</h3>
                {% if data and data.entry_suggestions %}
                    {% for suggestion in data.entry_suggestions %}
                    <div style="margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <strong>{{ suggestion.type.upper() }}:</strong><br>
                        🛡️ 止损: ${{ '{:.4f}'.format(suggestion.stop_loss) }}<br>
                        🎯 止盈: ${{ '{:.4f}'.format(suggestion.take_profit) }}<br>
                        ⚖️ 风险收益比: 1:{{ '{:.2f}'.format(suggestion.risk_reward) }}
                    </div>
                    {% endfor %}
                {% else %}
                    <p>暂无明确入场信号</p>
                {% endif %}
            </div>
        </div>
        
        <div class="update-time">
            最后更新: {{ update_time if update_time else 'Never' }} (自动刷新)
        </div>
    </div>
</body>
</html>
    ''', data=get_dashboard_data(), update_time=datetime.now().strftime('%H:%M:%S'))

@app.route('/api/data')
def api_data():
    """API数据接口"""
    return jsonify(get_dashboard_data())

def get_dashboard_data():
    """获取面板数据"""
    try:
        result = monitor.run_analysis()
        if result:
            return {
                'price': result['analysis']['current_price'],
                'change': result['ticker']['percentage'],
                'signal': result['signals']['overall_signal'],
                'confidence': result['signals']['confidence'],
                'rsi_1h': result['analysis']['timeframes']['1h']['rsi'],
                'daily_trend': result['analysis']['timeframes']['1d']['trend'],
                'entry_suggestions': result['signals']['entry_suggestions']
            }
    except Exception as e:
        print(f"❌ 获取数据失败: {e}")
    
    return None

def main():
    """主程序"""
    print("🚀 XMRUSDT 监控系统")
    print("=" * 50)
    print("选择模式:")
    print("1. 单次分析")
    print("2. 连续监控")
    print("3. Web面板 (http://localhost:5010)")
    print("4. 守护进程监控 (永不停止)")
    
    try:
        choice = input("\n请选择 (1-4): ").strip()
        
        if choice == '1':
            monitor.run_analysis()
        elif choice == '2':
            monitor.start_continuous_monitoring(interval=300)  # 5分钟更新
        elif choice == '3':
            print("\n🌐 启动Web监控面板...")
            print("📊 访问 http://localhost:5010 查看实时数据")
            app.run(host='0.0.0.0', port=5010, debug=False)
        elif choice == '4':
            monitor.start_daemon_monitoring(interval=300)  # 守护进程模式
        else:
            print("❌ 无效选择")
    
    except KeyboardInterrupt:
        print("\n👋 程序已退出")
    except Exception as e:
        print(f"❌ 程序出错: {e}")

if __name__ == "__main__":
    main()