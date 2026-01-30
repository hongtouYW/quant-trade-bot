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
DB_PATH = os.path.join(SCRIPT_DIR, 'data', 'db', 'paper_trading.db')  # 量化交易独立数据库
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
    """获取统计数据（从 real_trades 表计算）"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        # 从数据库读取账户配置
        cursor.execute("SELECT key, value FROM account_config WHERE key IN ('initial_capital', 'target_profit')")
        config = {row['key']: float(row['value']) for row in cursor.fetchall()}
        initial_capital = config.get('initial_capital', 2000)
        target_profit = config.get('target_profit', 3400)

        # 从 real_trades 表计算统计
        cursor.execute('''
            SELECT
                COUNT(*) as total_trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_trades,
                SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END) as loss_trades,
                SUM(pnl) as total_pnl,
                SUM(fee) as total_fees,
                SUM(funding_fee) as total_funding_fees,
                MAX(pnl) as best_trade,
                MIN(pnl) as worst_trade,
                AVG(roi) as avg_roi
            FROM real_trades
            WHERE status = 'CLOSED'
        ''')
        closed_stats = cursor.fetchone()

        # 获取未平仓持仓
        cursor.execute('''
            SELECT COUNT(*) as open_count, SUM(amount) as margin_used
            FROM real_trades
            WHERE status = 'OPEN'
        ''')
        open_stats = cursor.fetchone()

        total_pnl = closed_stats['total_pnl'] or 0
        total_fees = closed_stats['total_fees'] or 0
        total_trades = closed_stats['total_trades'] or 0
        win_trades = closed_stats['win_trades'] or 0
        loss_trades = closed_stats['loss_trades'] or 0

        current_capital = initial_capital + total_pnl
        win_rate = (win_trades / total_trades * 100) if total_trades > 0 else 0
        progress = (total_pnl / target_profit * 100) if target_profit > 0 else 0

        available = current_capital - (open_stats['margin_used'] or 0)
        result = {
            'initial_capital': initial_capital,
            'current_capital': current_capital,
            'target_profit': target_profit,
            'total_pnl': total_pnl,
            'total_fees': total_fees,
            'total_funding_fees': closed_stats['total_funding_fees'] or 0,
            'total_trades': total_trades,
            'win_trades': win_trades,
            'loss_trades': loss_trades,
            'win_rate': win_rate,
            'progress': progress,
            'best_trade': closed_stats['best_trade'],
            'worst_trade': closed_stats['worst_trade'],
            'avg_roi': closed_stats['avg_roi'],
            'open_positions': open_stats['open_count'] or 0,
            'margin_used': open_stats['margin_used'] or 0,
            'available_capital': available,
            'balance': available  # 前端使用的字段名
        }

        conn.close()
        return jsonify(result)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/positions')
def get_positions():
    """获取持仓（从 real_trades 表读取 status=OPEN）"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        cursor.execute('''
            SELECT * FROM real_trades
            WHERE status = 'OPEN'
            ORDER BY entry_time DESC
        ''')

        positions = []
        for row in cursor.fetchall():
            symbol = row['symbol']
            direction = row['direction'].upper() if row['direction'] else 'LONG'

            current_price = get_current_price(symbol)

            if current_price:
                margin = row['amount']
                leverage = row['leverage'] or 1
                entry_price = row['entry_price']

                position_value_usdt = margin * leverage
                coin_quantity = position_value_usdt / entry_price
                current_value_usdt = coin_quantity * current_price

                if direction == 'LONG':
                    unrealized_pnl = current_value_usdt - position_value_usdt
                else:
                    unrealized_pnl = position_value_usdt - current_value_usdt

                unrealized_pnl_pct = (unrealized_pnl / margin) * 100 if margin > 0 else 0
            else:
                unrealized_pnl = 0
                unrealized_pnl_pct = 0
                current_price = row['entry_price']

            positions.append({
                'id': row['id'],
                'symbol': symbol,
                'quantity': row['amount'],
                'entry_price': row['entry_price'],
                'current_price': current_price,
                'entry_time': row['entry_time'],
                'leverage': row['leverage'] or 1,
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'direction': direction.lower(),
                'cost': 0,
                'unrealized_pnl': unrealized_pnl,
                'unrealized_pnl_pct': unrealized_pnl_pct,
                'score': row['score'] if 'score' in row.keys() else 0,
                'close_reason': row['close_reason'] if 'close_reason' in row.keys() else '',
                'original_stop_loss': row['original_stop_loss'] if 'original_stop_loss' in row.keys() else None,
                'original_take_profit': row['original_take_profit'] if 'original_take_profit' in row.keys() else None
            })

        conn.close()
        return jsonify(positions)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def get_trades():
    """获取交易记录（从 real_trades 表读取 status=CLOSED）- 支持分页"""
    try:
        limit = int(request.args.get('limit', 20))
        offset = int(request.args.get('offset', 0))

        conn = get_db()
        cursor = conn.cursor()

        cursor.execute(f'''
            SELECT * FROM real_trades
            WHERE status = 'CLOSED'
            ORDER BY exit_time DESC
            LIMIT {limit} OFFSET {offset}
        ''')

        trades = []
        for row in cursor.fetchall():
            direction = row['direction'].upper() if row['direction'] else 'LONG'
            trades.append({
                'id': row['id'],
                'timestamp': row['exit_time'],
                'open_time': row['entry_time'],
                'symbol': row['symbol'],
                'side': 'buy' if direction == 'LONG' else 'sell',
                'direction': direction.lower(),
                'entry_price': row['entry_price'],
                'close_price': row['exit_price'],
                'price': row['exit_price'],
                'quantity': row['amount'],
                'leverage': row['leverage'] or 1,
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'pnl': row['pnl'] if row['pnl'] else 0,
                'pnl_pct': row['roi'] if row['roi'] else 0,
                'fee': row['fee'] if row['fee'] else 0,
                'score': row['score'] if 'score' in row.keys() else 0,
                'close_reason': row['close_reason'] if 'close_reason' in row.keys() else '',
                'original_stop_loss': row['original_stop_loss'] if 'original_stop_loss' in row.keys() else None,
                'original_take_profit': row['original_take_profit'] if 'original_take_profit' in row.keys() else None
            })

        conn.close()
        return jsonify(trades)
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/api/daily_stats')
def get_daily_stats():
    """获取每日统计（从 real_trades 表）"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        # 从 real_trades 获取每日统计
        cursor.execute('''
            SELECT
                DATE(exit_time) as date,
                COUNT(*) as trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN pnl <= 0 THEN 1 ELSE 0 END) as losses,
                ROUND(SUM(pnl), 2) as pnl,
                ROUND(SUM(fee), 2) as fees,
                ROUND(SUM(pnl) - SUM(fee), 2) as net_pnl
            FROM real_trades
            WHERE status = 'CLOSED'
              AND DATE(exit_time) >= DATE('now', '-30 days')
            GROUP BY DATE(exit_time)
            ORDER BY DATE(exit_time) DESC
        ''')

        daily_stats = []
        for row in cursor.fetchall():
            daily_stats.append({
                'date': row['date'],
                'trades': row['trades'],
                'wins': row['wins'],
                'losses': row['losses'],
                'pnl': row['pnl'],
                'fees': row['fees'],
                'net_pnl': row['net_pnl']
            })

        conn.close()
        return jsonify(daily_stats)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/kline/<path:symbol>')
def get_kline(symbol):
    """获取K线数据（支持复盘模式指定时间范围）"""
    try:
        timeframe = request.args.get('timeframe', '15m')
        limit = int(request.args.get('limit', 100))
        since = request.args.get('since')  # 开始时间戳（毫秒）

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

        # 如果指定了since参数，从指定时间开始获取
        if since:
            since_ts = int(since)
            ohlcv = exchange.fetch_ohlcv(symbol, binance_timeframe, since=since_ts, limit=limit)
        else:
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
    """策略分析 - 投票制评分，方向冲突时降分"""
    try:
        # 获取1小时K线数据
        ohlcv = exchange.fetch_ohlcv(symbol, '1h', limit=100)

        if not ohlcv or len(ohlcv) < 50:
            return None

        # 提取数据
        closes = [float(k[4]) for k in ohlcv]
        volumes = [float(k[5]) for k in ohlcv]
        highs = [float(k[2]) for k in ohlcv]
        lows = [float(k[3]) for k in ohlcv]

        current_price = closes[-1]

        # 计算RSI
        rsi = calculate_rsi_simple(np.array(closes), 14)[-1]
        if np.isnan(rsi):
            rsi = 50

        # 用投票制：每个指标投 buy/sell
        buy_votes = 0
        sell_votes = 0
        reasons = []

        # 1. RSI分析
        if rsi < 20:
            rsi_score = 30
            buy_votes += 2
            reasons.append("RSI极度超卖(%.0f)" % rsi)
        elif rsi < 30:
            rsi_score = 25
            buy_votes += 1
            reasons.append("RSI超卖")
        elif rsi > 80:
            rsi_score = 30
            sell_votes += 2
            reasons.append("RSI极度超买(%.0f)" % rsi)
        elif rsi > 70:
            rsi_score = 25
            sell_votes += 1
            reasons.append("RSI超买")
        elif 40 <= rsi <= 60:
            rsi_score = 10
            reasons.append("RSI中性")
        else:
            rsi_score = 15
            if rsi < 50:
                buy_votes += 1
            else:
                sell_votes += 1
            reasons.append("RSI偏移")

        # 2. 趋势分析（最重要，权重最大）
        ma7 = sum(closes[-7:]) / 7
        ma20 = sum(closes[-20:]) / 20
        ma50 = sum(closes[-50:]) / 50

        if current_price > ma7 > ma20 > ma50:
            trend_score = 30
            buy_votes += 3
            trend_label = 'bullish'
            reasons.append("多头排列")
        elif current_price < ma7 < ma20 < ma50:
            trend_score = 30
            sell_votes += 3
            trend_label = 'bearish'
            reasons.append("空头排列")
        elif current_price > ma7 > ma20:
            trend_score = 15
            buy_votes += 1
            trend_label = 'bullish'
            reasons.append("短期多头")
        elif current_price < ma7 < ma20:
            trend_score = 15
            sell_votes += 1
            trend_label = 'bearish'
            reasons.append("短期空头")
        else:
            trend_score = 5
            trend_label = 'neutral'
            reasons.append("趋势不明")

        # 3. 成交量分析
        avg_volume = sum(volumes[-20:]) / 20
        recent_volume = volumes[-1]
        volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1

        if volume_ratio > 1.5:
            volume_score = 15
            reasons.append("成交量放大")
        elif volume_ratio > 1.2:
            volume_score = 10
        elif volume_ratio > 1:
            volume_score = 5
        else:
            volume_score = 0

        # 4. 价格位置
        high_50 = max(highs[-50:])
        low_50 = min(lows[-50:])
        price_position = (current_price - low_50) / (high_50 - low_50) if high_50 > low_50 else 0.5

        if price_position < 0.2:
            position_score = 15
            buy_votes += 1
            reasons.append("接近底部")
        elif price_position < 0.3:
            position_score = 10
            buy_votes += 1
            reasons.append("偏底部")
        elif price_position > 0.8:
            position_score = 15
            sell_votes += 1
            reasons.append("接近顶部")
        elif price_position > 0.7:
            position_score = 10
            sell_votes += 1
            reasons.append("偏顶部")
        else:
            position_score = 5

        # 5. 动量检查：最近3根K线方向
        recent_change = (closes[-1] - closes[-4]) / closes[-4] * 100
        if recent_change < -3:
            sell_votes += 2
            reasons.append("急跌%.1f%%" % recent_change)
        elif recent_change < -1:
            sell_votes += 1
        elif recent_change > 3:
            buy_votes += 2
            reasons.append("急涨+%.1f%%" % recent_change)
        elif recent_change > 1:
            buy_votes += 1

        # 决定方向：投票制
        if buy_votes > sell_votes:
            signal = 'buy'
        elif sell_votes > buy_votes:
            signal = 'sell'
        else:
            signal = 'buy' if trend_label == 'bullish' else 'sell'

        # 计算基础分
        base_score = rsi_score + trend_score + volume_score + position_score

        # 方向冲突时扣分
        vote_diff = abs(buy_votes - sell_votes)
        if vote_diff == 0:
            total_score = int(base_score * 0.4)
            reasons.append("信号矛盾")
        elif vote_diff == 1:
            total_score = int(base_score * 0.6)
        elif vote_diff == 2:
            total_score = int(base_score * 0.8)
        else:
            total_score = base_score

        # 最低分门槛
        if total_score < 70:
            return None

        # 趋势标签用实际趋势
        trend = trend_label

        # 计算止损止盈
        if signal == 'buy':
            stop_loss = current_price * 0.97
            take_profit = current_price * 1.05
        else:
            stop_loss = current_price * 1.03
            take_profit = current_price * 0.95

        return {
            'symbol': symbol,
            'signal': signal,  # 'buy' or 'sell'
            'price': float(current_price),
            'score': int(total_score),  # 使用100分制的总分
            'rsi': float(rsi),
            'trend': trend,
            'reasons': reasons,
            'stop_loss': float(stop_loss),
            'take_profit': float(take_profit),
            'volume_surge': volume_ratio > 1.5
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

@app.route('/api/real_trades')
def get_real_trades():
    """获取真实的Binance交易历史"""
    try:
        from dotenv import load_dotenv
        load_dotenv(os.path.join(SCRIPT_DIR, 'quant-trade-bot', '.env'))

        api_key = os.getenv('BINANCE_API_KEY')
        api_secret = os.getenv('BINANCE_API_SECRET')

        if not api_key or not api_secret:
            return jsonify({'error': 'API未配置', 'trades': []})

        exchange = ccxt.binance({
            'apiKey': api_key,
            'secret': api_secret,
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })

        # 获取最近的交易记录
        symbols = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'DOGE/USDT']
        all_trades = []

        for symbol in symbols:
            try:
                trades = exchange.fetch_my_trades(symbol, limit=20)
                for t in trades:
                    all_trades.append({
                        'id': t['id'],
                        'symbol': t['symbol'],
                        'side': t['side'],
                        'price': t['price'],
                        'amount': t['amount'],
                        'cost': t['cost'],
                        'fee': t['fee']['cost'] if t['fee'] else 0,
                        'timestamp': t['datetime'],
                        'pnl': t.get('info', {}).get('realizedPnl', 0)
                    })
            except:
                continue

        # 按时间排序
        all_trades.sort(key=lambda x: x['timestamp'], reverse=True)

        return jsonify({
            'trades': all_trades[:50],
            'count': len(all_trades)
        })
    except Exception as e:
        return jsonify({'error': str(e), 'trades': []})

@app.route('/api/real_balance')
def get_real_balance():
    """获取真实的Binance账户余额"""
    try:
        from dotenv import load_dotenv
        load_dotenv(os.path.join(SCRIPT_DIR, 'quant-trade-bot', '.env'))

        api_key = os.getenv('BINANCE_API_KEY')
        api_secret = os.getenv('BINANCE_API_SECRET')

        if not api_key or not api_secret:
            return jsonify({'error': 'API未配置'})

        exchange = ccxt.binance({
            'apiKey': api_key,
            'secret': api_secret,
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })

        balance = exchange.fetch_balance()

        return jsonify({
            'USDT': {
                'total': balance.get('USDT', {}).get('total', 0),
                'free': balance.get('USDT', {}).get('free', 0),
                'used': balance.get('USDT', {}).get('used', 0)
            },
            'timestamp': datetime.now().isoformat()
        })
    except Exception as e:
        return jsonify({'error': str(e)})

if __name__ == '__main__':
    print("🌐 启动Web监控面板...")
    print("📊 访问地址: http://localhost:5001")
    print("💡 按 Ctrl+C 停止服务器")
    app.run(debug=False, host='0.0.0.0', port=5001)
