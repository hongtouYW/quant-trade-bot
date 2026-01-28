#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
实时监控Web面板
"""

from flask import Flask, render_template, jsonify, request, send_from_directory
import sqlite3
import json
from datetime import datetime, date
import os
import sys
import ccxt
import pandas as pd
import numpy as np

# 添加策略目录到Python路径
sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), 'src'))
try:
    import talib
    TALIB_AVAILABLE = True
except ImportError:
    TALIB_AVAILABLE = False
    print("⚠️  TA-Lib未安装，策略筛选功能将受限")

# 使用绝对路径 - 项目根目录
SCRIPT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # 项目根目录
DB_PATH = os.path.join(SCRIPT_DIR, 'data', 'db', 'paper_trading.db')
HTML_DIR = os.path.join(SCRIPT_DIR, 'quant-trade-bot')

# Flask app - 指定模板和静态文件目录
app = Flask(__name__,
            template_folder=os.path.join(SCRIPT_DIR, 'templates'),
            static_folder=os.path.join(SCRIPT_DIR, 'static'))

def get_db():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def get_current_price(symbol):
    """获取当前价格（期货市场）"""
    try:
        exchange = ccxt.binance({
            'enableRateLimit': True,
            'timeout': 10000,
            'options': {'defaultType': 'future'}
        })
        ticker = exchange.fetch_ticker(symbol)
        return ticker['last']
    except:
        return None

@app.route('/')
def index():
    """主页"""
    return send_from_directory(HTML_DIR, 'index.html')

@app.route('/index.html')
def index_html():
    """主页"""
    return send_from_directory(HTML_DIR, 'index.html')

@app.route('/<path:filename>')
def serve_files(filename):
    """服务静态文件"""
    if not filename.startswith('api/'):
        try:
            return send_from_directory(HTML_DIR, filename)
        except:
            pass
    return "Not Found", 404

@app.route('/api/stats')
def get_stats():
    """获取统计数据"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        # 获取最新统计
        cursor.execute('''
            SELECT * FROM stats 
            ORDER BY timestamp DESC 
            LIMIT 1
        ''')
        stats = cursor.fetchone()
        
        if stats:
            result = {
                'balance': stats['balance'],
                'total_pnl': stats['total_pnl'],
                'total_trades': stats['total_trades'],
                'winning_trades': stats['winning_trades'],
                'losing_trades': stats['losing_trades'],
                'win_rate': stats['win_rate'],
                'total_fees': stats['total_fees']
            }
        else:
            # 如果没有统计数据，从trades表计算
            cursor.execute('SELECT balance_after FROM trades ORDER BY timestamp DESC LIMIT 1')
            last_trade = cursor.fetchone()
            balance = last_trade['balance_after'] if last_trade else 1000
            
            result = {
                'balance': balance,
                'total_pnl': 0,
                'total_trades': 0,
                'winning_trades': 0,
                'losing_trades': 0,
                'win_rate': 0,
                'total_fees': 0
            }
        
        conn.close()
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/positions')
def get_positions():
    """获取持仓"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT * FROM positions
            WHERE status = 'open'
            ORDER BY open_time DESC
        ''')
        
        positions = []
        for row in cursor.fetchall():
            current_price = get_current_price(row['symbol'])
            
            if current_price:
                amount = row['amount']
                position_value = amount * current_price
                entry_value = amount * row['entry_price']
                unrealized_pnl = (position_value - entry_value) * row['leverage']
                # cost = entry_value / leverage (approximately)
                cost = entry_value / row['leverage'] if row['leverage'] > 0 else entry_value
                unrealized_pnl_pct = (unrealized_pnl / cost) * 100 if cost > 0 else 0
            else:
                unrealized_pnl = 0
                unrealized_pnl_pct = 0
                current_price = row['entry_price']
            
            positions.append({
                'symbol': row['symbol'],
                'quantity': row['amount'],
                'entry_price': row['entry_price'],
                'current_price': current_price,
                'entry_time': row['open_time'],
                'leverage': row['leverage'],
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'cost': 0,  # cost not in table
                'unrealized_pnl': unrealized_pnl,
                'unrealized_pnl_pct': unrealized_pnl_pct
            })
        
        conn.close()
        return jsonify(positions)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def get_trades():
    """获取交易记录（完整的开仓+平仓记录）- 支持分页"""
    try:
        limit = int(request.args.get('limit', 20))
        offset = int(request.args.get('offset', 0))

        conn = get_db()
        cursor = conn.cursor()

        # 从positions表读取已平仓的完整交易记录（支持分页）
        cursor.execute(f'''
            SELECT * FROM positions
            WHERE status = 'closed'
            ORDER BY close_time DESC
            LIMIT {limit} OFFSET {offset}
        ''')

        trades = []
        for row in cursor.fetchall():
            # 计算交易方向的显示文本
            direction = row['direction'] if 'direction' in row.keys() else 'long'
            side = 'buy' if direction == 'long' else 'sell'

            trades.append({
                'timestamp': row['close_time'],  # 使用平仓时间作为记录时间
                'open_time': row['open_time'],   # 开仓时间
                'symbol': row['symbol'],
                'side': side,
                'direction': direction,
                'entry_price': row['entry_price'],   # 开仓价
                'close_price': row['close_price'],   # 平仓价
                'price': row['close_price'],          # 兼容前端
                'quantity': row['amount'],
                'leverage': row['leverage'],
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'pnl': row['pnl'] if row['pnl'] else 0,
                'pnl_pct': row['pnl_pct'] if row['pnl_pct'] else 0,
            })

        conn.close()
        return jsonify(trades)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_stats')
def get_daily_stats():
    """获取每日统计"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        # 获取最近7天的数据
        cursor.execute('''
            SELECT
                date(timestamp) as date,
                COUNT(*) as trades,
                SUM(CASE WHEN side='buy' THEN 1 ELSE 0 END) as buys,
                SUM(CASE WHEN side='sell' THEN 1 ELSE 0 END) as sells,
                SUM(CASE WHEN pnl IS NOT NULL THEN pnl ELSE 0 END) as pnl,
                SUM(fee) as fees
            FROM trades
            WHERE date(timestamp) >= date('now', '-7 days')
            GROUP BY date(timestamp)
            ORDER BY date(timestamp) DESC
        ''')

        daily_stats = []
        for row in cursor.fetchall():
            daily_stats.append({
                'date': row['date'],
                'trades': row['trades'],
                'buys': row['buys'],
                'sells': row['sells'],
                'pnl': row['pnl'],
                'fees': row['fees']
            })

        conn.close()
        return jsonify(daily_stats)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/kline/<path:symbol>')
def get_kline(symbol):
    """获取K线数据"""
    try:
        timeframe = request.args.get('timeframe', '15m')
        limit = int(request.args.get('limit', 100))

        # 映射时间周期
        timeframe_map = {
            '5m': '5m',
            '10m': '5m',  # Binance没有10m，用5m数据
            '15m': '15m',
            '1h': '1h',
            '4h': '4h',
            '8h': '4h',  # Binance没有8h，用4h数据
            '1d': '1d'
        }

        binance_timeframe = timeframe_map.get(timeframe, '15m')

        # 从Binance获取K线数据
        exchange = ccxt.binance({'enableRateLimit': True, 'timeout': 10000})
        ohlcv = exchange.fetch_ohlcv(symbol, binance_timeframe, limit=limit)

        # 格式化数据
        klines = []
        for candle in ohlcv:
            klines.append({
                'timestamp': candle[0],
                'open': candle[1],
                'high': candle[2],
                'low': candle[3],
                'close': candle[4],
                'volume': candle[5]
            })

        return jsonify(klines)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/recommendations')
def get_recommendations():
    """获取策略推荐的货币对"""
    try:
        # 监控的货币对列表（期货市场）
        symbols = [
            'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT',
            'AVAX/USDT', 'DOT/USDT', 'ATOM/USDT',
            'DOGE/USDT', 'LINK/USDT', 'ADA/USDT', 'LTC/USDT',
            'UNI/USDT', 'AAVE/USDT', 'FIL/USDT'
        ]
        # 注意：MATIC已下架，改为POL

        recommendations = []
        # 使用期货市场，与auto_trader保持一致
        exchange = ccxt.binance({
            'enableRateLimit': True,
            'timeout': 10000,
            'options': {'defaultType': 'future'}
        })

        for symbol in symbols:
            try:
                result = analyze_symbol_simple(exchange, symbol)
                if result and result['signal'] != 'neutral':
                    recommendations.append(result)
            except Exception as e:
                print(f"❌ 分析 {symbol} 失败: {e}")
                continue

        # 按信号强度排序
        recommendations.sort(key=lambda x: x['score'], reverse=True)

        return jsonify(recommendations)
    except Exception as e:
        print(f"❌ 获取推荐失败: {e}")
        return jsonify({'error': str(e)}), 500

def analyze_symbol_simple(exchange, symbol):
    """简化版策略分析（不依赖TA-Lib）"""
    try:
        # 获取15分钟K线数据
        ohlcv_15m = exchange.fetch_ohlcv(symbol, '15m', limit=100)
        # 获取1小时K线数据
        ohlcv_1h = exchange.fetch_ohlcv(symbol, '1h', limit=50)

        if not ohlcv_15m or not ohlcv_1h:
            return None

        # 转换为DataFrame
        df_15m = pd.DataFrame(ohlcv_15m, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        df_1h = pd.DataFrame(ohlcv_1h, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])

        # 计算简单指标
        close_15m = df_15m['close'].values
        close_1h = df_1h['close'].values
        volume_15m = df_15m['volume'].values

        # 使用TA-Lib或手动计算
        if TALIB_AVAILABLE:
            # RSI
            rsi_15m = talib.RSI(close_15m, timeperiod=14)
            rsi_1h = talib.RSI(close_1h, timeperiod=14)

            # MACD
            macd_15m, signal_15m, hist_15m = talib.MACD(close_15m, fastperiod=12, slowperiod=26, signalperiod=9)

            # EMA
            ema_fast_1h = talib.EMA(close_1h, timeperiod=20)
            ema_slow_1h = talib.EMA(close_1h, timeperiod=50)

            # 布林带
            upper, middle, lower = talib.BBANDS(close_15m, timeperiod=20, nbdevup=2, nbdevdn=2)
        else:
            # 手动计算简化指标
            rsi_15m = calculate_rsi_simple(close_15m, 14)
            rsi_1h = calculate_rsi_simple(close_1h, 14)
            ema_fast_1h = calculate_ema_simple(close_1h, 20)
            ema_slow_1h = calculate_ema_simple(close_1h, 50)
            macd_15m = np.array([0] * len(close_15m))
            signal_15m = np.array([0] * len(close_15m))
            middle = calculate_sma_simple(close_15m, 20)

        # 当前值
        current_price = close_15m[-1]
        current_rsi_15m = rsi_15m[-1] if not np.isnan(rsi_15m[-1]) else 50
        current_rsi_1h = rsi_1h[-1] if not np.isnan(rsi_1h[-1]) else 50

        # 判断趋势（1小时）
        trend = 'neutral'
        if ema_fast_1h[-1] > ema_slow_1h[-1]:
            trend = 'bullish'
        elif ema_fast_1h[-1] < ema_slow_1h[-1]:
            trend = 'bearish'

        # 成交量分析
        avg_volume = np.mean(volume_15m[-20:])
        current_volume = volume_15m[-1]
        volume_surge = current_volume > avg_volume * 1.5

        # 生成信号
        signal = 'neutral'
        score = 0
        reasons = []

        # 多头信号
        if trend == 'bullish':
            if current_rsi_15m < 35:
                score += 3
                reasons.append("RSI超卖")
            elif current_rsi_15m < 45:
                score += 2
                reasons.append("RSI偏低")
            elif current_rsi_15m < 55:
                score += 1
                reasons.append("趋势上涨")

            if TALIB_AVAILABLE and len(macd_15m) > 1:
                if macd_15m[-1] > signal_15m[-1] and macd_15m[-2] <= signal_15m[-2]:
                    score += 3
                    reasons.append("MACD金叉")

            if volume_surge:
                score += 2
                reasons.append("成交量放大")

            # 降低门槛：只需要2分即可
            if score >= 2:
                signal = 'buy'

        # 空头信号
        elif trend == 'bearish':
            if current_rsi_15m > 65:
                score += 3
                reasons.append("RSI超买")
            elif current_rsi_15m > 55:
                score += 2
                reasons.append("RSI偏高")
            elif current_rsi_15m > 45:
                score += 1
                reasons.append("趋势下跌")

            if TALIB_AVAILABLE and len(macd_15m) > 1:
                if macd_15m[-1] < signal_15m[-1] and macd_15m[-2] >= signal_15m[-2]:
                    score += 3
                    reasons.append("MACD死叉")

            if volume_surge:
                score += 2
                reasons.append("成交量放大")

            # 降低门槛：只需要2分即可
            if score >= 2:
                signal = 'sell'

        if signal == 'neutral':
            return None

        # 计算止损止盈
        if TALIB_AVAILABLE:
            stop_loss = lower[-1] if signal == 'buy' else upper[-1]
            take_profit = upper[-1] if signal == 'buy' else lower[-1]
        else:
            atr = np.std(close_15m[-20:])
            stop_loss = current_price - (2 * atr) if signal == 'buy' else current_price + (2 * atr)
            take_profit = current_price + (3 * atr) if signal == 'buy' else current_price - (3 * atr)

        return {
            'symbol': symbol,
            'signal': signal,  # 'buy' or 'sell'
            'price': float(current_price),
            'score': int(score),
            'rsi': float(current_rsi_15m),
            'trend': trend,
            'reasons': reasons,
            'stop_loss': float(stop_loss) if not np.isnan(stop_loss) else float(current_price * 0.97),
            'take_profit': float(take_profit) if not np.isnan(take_profit) else float(current_price * 1.05),
            'volume_surge': bool(volume_surge)
        }

    except Exception as e:
        print(f"❌ 分析{symbol}出错: {e}")
        return None

def calculate_rsi_simple(prices, period=14):
    """简单RSI计算"""
    deltas = np.diff(prices)
    gains = np.where(deltas > 0, deltas, 0)
    losses = np.where(deltas < 0, -deltas, 0)

    avg_gains = np.convolve(gains, np.ones(period)/period, mode='valid')
    avg_losses = np.convolve(losses, np.ones(period)/period, mode='valid')

    rs = avg_gains / (avg_losses + 1e-10)
    rsi = 100 - (100 / (1 + rs))

    # 填充前面的NaN
    result = np.full(len(prices), 50.0)
    result[period:] = rsi
    return result

def calculate_ema_simple(prices, period):
    """简单EMA计算"""
    ema = np.zeros_like(prices)
    ema[0] = prices[0]
    multiplier = 2 / (period + 1)

    for i in range(1, len(prices)):
        ema[i] = (prices[i] - ema[i-1]) * multiplier + ema[i-1]

    return ema

def calculate_sma_simple(prices, period):
    """简单SMA计算"""
    return np.convolve(prices, np.ones(period)/period, mode='same')

if __name__ == '__main__':
    print("🌐 启动Web监控面板...")
    print("📊 访问地址: http://localhost:5001")
    print("💡 按 Ctrl+C 停止服务器")
    app.run(debug=False, host='0.0.0.0', port=5001)
