#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Trading Assistant Dashboard - 交易助手仪表盘 v1.2
Port: 5111
独立于量化助手(5001)

v1.2 新功能:
- 按需加载图表（点击后才加载）
- 一次只显示一个持仓的图表
- 持仓选择下拉框
- 6种时间周期筛选
"""

from flask import Flask, jsonify, render_template_string, request
import sqlite3
from datetime import datetime, timedelta
import os
import requests

app = Flask(__name__)

DB_PATH = '/opt/trading-bot/quant-trade-bot/data/db/trading_assistant.db'

def get_db():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

@app.route('/')
def index():
    """主页面"""
    return render_template_string(HTML_TEMPLATE)

@app.route('/api/stats')
def get_stats():
    """获取统计数据"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        # 基本统计
        cursor.execute('''
            SELECT
                COUNT(*) as total_trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_trades,
                SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END) as loss_trades,
                SUM(COALESCE(pnl, 0)) as total_pnl,
                SUM(COALESCE(fee, 0)) as total_fees,
                SUM(COALESCE(funding_fee, 0)) as total_funding_fees,
                AVG(CASE WHEN status = 'CLOSED' THEN roi END) as avg_roi,
                MAX(pnl) as best_trade,
                MIN(pnl) as worst_trade
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
        ''')
        
        stats = dict(cursor.fetchone())
        
        # 计算胜率
        total = stats['total_trades'] or 0
        wins = stats['win_trades'] or 0
        stats['win_rate'] = (wins / total * 100) if total > 0 else 0
        
        # 当前资金
        initial_capital = 2000
        current_capital = initial_capital + (stats['total_pnl'] or 0)
        target_profit = 3400

        # 计算持仓占用保证金
        cursor.execute('''
            SELECT
                COUNT(*) as open_positions,
                COALESCE(SUM(amount), 0) as margin_used
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'OPEN'
        ''')

        position_stats = dict(cursor.fetchone())
        margin_used = position_stats['margin_used']
        available_capital = current_capital - margin_used

        stats['initial_capital'] = initial_capital
        stats['current_capital'] = current_capital
        stats['available_capital'] = available_capital
        stats['margin_used'] = margin_used
        stats['target_profit'] = target_profit
        stats['progress'] = ((stats['total_pnl'] or 0) / target_profit * 100) if target_profit > 0 else 0
        stats['open_positions'] = position_stats['open_positions']

        conn.close()
        
        return jsonify(stats)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/positions')
def get_positions():
    """获取当前持仓"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT 
                symbol, direction, entry_price, amount, leverage,
                stop_loss, take_profit, entry_time, reason
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'OPEN'
            ORDER BY entry_time DESC
        ''')
        
        positions = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify(positions)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def get_trades():
    """获取交易历史"""
    try:
        limit = 20
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT
                symbol, direction, entry_price, exit_price,
                amount, leverage, pnl, roi, fee, funding_fee, entry_time, exit_time,
                status, reason, stop_loss, take_profit
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            ORDER BY entry_time DESC
            LIMIT ?
        ''', (limit,))
        
        trades = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify(trades)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_stats')
def get_daily_stats():
    """获取每日统计（最近7天）- 优先从daily_pnl表读取"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        # 先尝试从daily_pnl表读取
        try:
            cursor.execute('''
                SELECT
                    date,
                    trades_count as trades,
                    win_count as wins,
                    total_pnl as daily_pnl,
                    win_rate,
                    cumulative_pnl
                FROM daily_pnl
                WHERE date >= date('now', '-7 days')
                ORDER BY date DESC
            ''')
            daily_stats = [dict(row) for row in cursor.fetchall()]

            # 如果有数据，直接返回
            if daily_stats:
                conn.close()
                return jsonify(daily_stats)
        except:
            # 如果表不存在，继续使用旧方法
            pass

        # 降级方案：实时计算（如果daily_pnl表不存在或无数据）
        cursor.execute('''
            SELECT
                DATE(exit_time) as date,
                COUNT(*) as trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as wins,
                SUM(COALESCE(pnl, 0)) as daily_pnl
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
            AND exit_time >= date('now', '-7 days')
            GROUP BY DATE(exit_time)
            ORDER BY date DESC
        ''')

        daily_stats = [dict(row) for row in cursor.fetchall()]
        conn.close()

        return jsonify(daily_stats)

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_history')
def get_daily_history():
    """获取每日收益历史记录（所有数据）"""
    try:
        days = request.args.get('days', 30, type=int)

        conn = get_db()
        cursor = conn.cursor()

        cursor.execute('''
            SELECT
                date,
                trades_count,
                win_count,
                loss_count,
                total_pnl,
                total_fee,
                total_funding_fee,
                win_rate,
                best_trade,
                worst_trade,
                cumulative_pnl
            FROM daily_pnl
            WHERE date >= date('now', ? || ' days')
            ORDER BY date DESC
        ''', (f'-{days}',))

        history = [dict(row) for row in cursor.fetchall()]
        conn.close()

        return jsonify(history)

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/price/<symbol>')
def get_current_price(symbol):
    """获取币种当前价格（使用Binance期货API）"""
    try:
        # 使用Binance期货API，与交易系统一致
        binance_symbol = f"{symbol}USDT"
        url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={binance_symbol}"
        response = requests.get(url, timeout=10)
        data = response.json()

        if 'price' in data:
            price = float(data['price'])
            return jsonify({'symbol': symbol, 'price': price})
        else:
            return jsonify({'error': f'Price not found for {symbol}'}), 404

    except Exception as e:
        return jsonify({'error': str(e), 'symbol': symbol}), 500

@app.route('/api/kline/<symbol>')
def get_kline(symbol):
    """获取K线数据（支持多时间周期）"""
    try:
        # 获取时间周期参数，默认5m
        interval = request.args.get('interval', '5m')
        
        # Binance不支持10m，改用15m
        if interval == '10m':
            interval = '15m'

        # 时间周期对应的数据量（保持图表信息量一致）
        interval_limits = {
            '5m': 288,   # 24小时 = 288个5分钟K线
            '15m': 96,   # 24小时 = 96个15分钟K线
            '30m': 48,   # 24小时 = 48个30分钟K线
            '1h': 168,   # 7天 = 168个1小时K线
            '4h': 168,   # 28天 = 168个4小时K线
            '1d': 90     # 90天 = 90个1日K线
        }
        
        limit = interval_limits.get(interval, 288)
        
        symbol_map = {
            # 原有币种
            'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
            'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
            # 新增币种
            'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
            'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT'
        }
        binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

        # 使用期货API获取K线数据
        url = f"https://fapi.binance.com/fapi/v1/klines?symbol={binance_symbol}&interval={interval}&limit={limit}"
        response = requests.get(url, timeout=10)
        klines = response.json()
        
        # 转换为简化格式
        data = []
        for k in klines:
            data.append({
                'time': int(k[0]),
                'open': float(k[1]),
                'high': float(k[2]),
                'low': float(k[3]),
                'close': float(k[4]),
                'volume': float(k[5])
            })
        
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/watchlist')
def get_watchlist():
    """获取监控币种列表"""
    try:
        # 监控币种 (25个 - 激进策略：增加交易机会)
        watch_symbols = [
            # 原有监控 (7个)
            'XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK',
            # 高分币种 (6个)
            'VET',   # 得分100 - VeChain
            'BNB',   # 得分80 - Binance Coin
            'INJ',   # 得分80 - Injective
            'LINK',  # 得分70 - Chainlink
            'OP',    # 得分70 - Optimism
            'FIL',   # 得分70 - Filecoin
            # 高流动性币种 (6个)
            'ETH',   # 以太坊
            'AVAX',  # Avalanche
            'DOT',   # Polkadot
            'ATOM',  # Cosmos
            'MATIC', # Polygon
            'ARB',   # Arbitrum
            # 高波动性币种 (6个)
            'APT',   # Aptos
            'SUI',   # Sui
            'SEI',   # Sei
            'TIA',   # Celestia
            'WLD',   # Worldcoin
            'NEAR'   # Near Protocol
        ]

        conn = get_db()
        cursor = conn.cursor()

        # 获取当前持仓（包含方向、杠杆、止盈止损信息）
        cursor.execute('''
            SELECT symbol, direction, leverage, take_profit, stop_loss FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手' AND status = 'OPEN'
        ''')
        positions_dict = {row['symbol']: {
            'direction': row['direction'],
            'leverage': row['leverage'],
            'take_profit': row['take_profit'],
            'stop_loss': row['stop_loss']
        } for row in cursor.fetchall()}
        conn.close()

        # 获取每个币种的当前价格和建议
        watchlist = []
        for symbol in watch_symbols:
            try:
                price_data = get_price_value(symbol)
                has_position = symbol in positions_dict

                # 获取信号建议（包括持仓和非持仓币种）
                suggestion_data = None
                confidence = 0
                suggested_direction = None
                stop_loss = None
                take_profit = None
                leverage = None
                profit_pct = None
                loss_pct = None

                # 获取信号数据（总是返回数据，包括信心度）
                suggestion_data = get_signal_suggestion(symbol)
                if suggestion_data:
                    confidence = suggestion_data['confidence']

                if has_position:
                    # 持仓币种：使用实际的杠杆、止盈、止损
                    pos_info = positions_dict[symbol]
                    leverage = pos_info['leverage']
                    take_profit = pos_info['take_profit']
                    stop_loss = pos_info['stop_loss']
                else:
                    # 非持仓币种：显示建议的止盈止损，计算预估盈亏%
                    leverage = 10  # 默认10倍杠杆
                    if suggestion_data and suggestion_data.get('tradeable', False):
                        # 只有可交易的信号才显示建议方向和止盈止损
                        stop_loss = suggestion_data['stop_loss']
                        take_profit = suggestion_data['take_profit']
                        suggested_direction = suggestion_data['direction']

                        # 计算预估盈利%和亏损%（考虑杠杆）
                        if suggested_direction == 'LONG' and take_profit and stop_loss:
                            profit_pct = ((take_profit - price_data) / price_data) * leverage * 100
                            loss_pct = ((price_data - stop_loss) / price_data) * leverage * 100
                        elif suggested_direction == 'SHORT' and take_profit and stop_loss:
                            profit_pct = ((price_data - take_profit) / price_data) * leverage * 100
                            loss_pct = ((stop_loss - price_data) / price_data) * leverage * 100

                watchlist.append({
                    'symbol': symbol,
                    'price': price_data,
                    'has_position': has_position,
                    'direction': positions_dict[symbol]['direction'] if has_position else None,  # 当前持仓方向
                    'suggested_direction': suggested_direction,  # 建议方向（仅非持仓）
                    'confidence': confidence,  # 信心度分数
                    'stop_loss': stop_loss,  # 止损价位
                    'take_profit': take_profit,  # 止盈价位
                    'leverage': leverage,  # 杠杆倍数
                    'profit_pct': profit_pct,  # 预估盈利%（仅非持仓）
                    'loss_pct': loss_pct  # 预估亏损%（仅非持仓）
                })
            except Exception as e:
                has_position = symbol in positions_dict
                watchlist.append({
                    'symbol': symbol,
                    'price': 0,
                    'has_position': has_position,
                    'direction': positions_dict[symbol]['direction'] if has_position else None,
                    'suggested_direction': None,
                    'confidence': 0,
                    'stop_loss': None,
                    'take_profit': None,
                    'leverage': positions_dict[symbol]['leverage'] if has_position else 10,
                    'profit_pct': None,
                    'loss_pct': None,
                    'error': str(e)
                })

        # 排序：1. 有持仓的在最前 2. 按信心度降序
        watchlist.sort(key=lambda x: (not x['has_position'], -x['confidence']))

        return jsonify(watchlist)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def get_price_value(symbol):
    """获取币种当前价格（期货价格）"""
    symbol_map = {
        # 原有币种
        'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
        'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
        'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
        'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
        # 新增币种
        'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
        'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
        'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
        'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
    }
    binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

    # 使用Binance期货API，与交易系统保持一致
    url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={binance_symbol}"
    response = requests.get(url, timeout=5)
    data = response.json()
    return float(data['price'])

def get_signal_suggestion(symbol):
    """获取币种信号建议（做多/做空）+ 信心度 + 止盈止损"""
    try:
        symbol_map = {
            'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
            'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
            'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
            'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
            'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
            'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
            'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
            'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
        }
        binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

        # 获取K线数据（使用期货API）
        url = f"https://fapi.binance.com/fapi/v1/klines"
        params = {
            'symbol': binance_symbol,
            'interval': '5m',
            'limit': 50
        }
        response = requests.get(url, params=params, timeout=5)
        klines = response.json()

        if not klines or len(klines) < 30:
            return None

        # 计算简单RSI
        closes = [float(k[4]) for k in klines]
        changes = [closes[i] - closes[i-1] for i in range(1, len(closes))]
        gains = [c if c > 0 else 0 for c in changes]
        losses = [abs(c) if c < 0 else 0 for c in changes]

        avg_gain = sum(gains[-14:]) / 14 if len(gains) >= 14 else 0
        avg_loss = sum(losses[-14:]) / 14 if len(losses) >= 14 else 0

        if avg_loss == 0:
            rsi = 100
        else:
            rs = avg_gain / avg_loss
            rsi = 100 - (100 / (1 + rs))

        # 计算MA趋势
        ma7 = sum(closes[-7:]) / 7
        ma25 = sum(closes[-25:]) / 25
        current_price = closes[-1]

        # 计算信心度分数 (0-100)
        confidence = 0
        direction = None

        # RSI 分数 (40分)
        if rsi < 30:
            confidence += 40
            direction = 'LONG'
        elif rsi > 70:
            confidence += 40
            direction = 'SHORT'
        elif rsi < 40:
            confidence += 20
            direction = 'LONG'
        elif rsi > 60:
            confidence += 20
            direction = 'SHORT'

        # MA 趋势分数 (30分)
        if ma7 > ma25:
            confidence += 30
            if direction != 'SHORT':
                direction = 'LONG'
        elif ma7 < ma25:
            confidence += 30
            if direction != 'LONG':
                direction = 'SHORT'

        # 价格位置分数 (30分)
        if direction == 'LONG' and current_price > ma7:
            confidence += 30
        elif direction == 'SHORT' and current_price < ma7:
            confidence += 30
        elif direction == 'LONG' and current_price < ma7:
            confidence -= 10
        elif direction == 'SHORT' and current_price > ma7:
            confidence -= 10

        # 计算止盈止损 (基于当前价格或方向)
        if direction == 'LONG':
            stop_loss = current_price * 0.95  # -5%
            take_profit = current_price * 1.10  # +10%
        elif direction == 'SHORT':
            stop_loss = current_price * 1.05  # +5%
            take_profit = current_price * 0.90  # -10%
        else:
            # 无明确方向，返回基本信息
            stop_loss = None
            take_profit = None

        # 最低50分才标记为可交易（激进策略：增加交易频率）
        tradeable = confidence >= 50 and direction is not None

        return {
            'direction': direction,
            'confidence': max(0, min(confidence, 100)),  # 0-100分
            'stop_loss': stop_loss,
            'take_profit': take_profit,
            'current_price': current_price,
            'rsi': rsi,
            'tradeable': tradeable  # 是否可交易
        }

    except Exception as e:
        return None

# HTML模板
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交易助理 - Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card .label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card .value.positive {
            color: #10b981;
        }
        
        .stat-card .value.negative {
            color: #ef4444;
        }
        
        .stat-card .subtext {
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .badge.long {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge.short {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge.open {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge.closed {
            background: #e5e7eb;
            color: #374151;
        }

        .watchlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .watch-card {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .watch-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .watch-card.has-position {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .watch-symbol {
            font-size: 1.1em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .watch-card.has-position .watch-symbol {
            color: #10b981;
        }

        .watch-price {
            font-size: 0.95em;
            color: #999;
        }

        .watch-status {
            font-size: 0.75em;
            margin-top: 8px;
            padding: 3px 8px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            display: inline-block;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: width 0.5s;
        }
        
        .refresh-time {
            text-align: center;
            color: white;
            margin-top: 20px;
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* 持仓卡片样式 */
        .position-cards {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .position-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 8px;
            padding: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }

        /* 做多持仓 - 绿色边框 */
        .position-card.long {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
            border: 2px solid rgba(16, 185, 129, 0.4);
        }

        .position-card.long:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.6);
        }

        /* 做空持仓 - 红色边框 */
        .position-card.short {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(220, 38, 38, 0.05) 100%);
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        .position-card.short:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.6);
        }

        .position-card:hover {
            transform: translateY(-2px);
        }

        .position-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .position-card-title {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .position-card-symbol {
            font-size: 0.9em;
            font-weight: bold;
            color: #333;
        }

        .position-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 6px;
        }

        .position-card-main {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 6px;
        }

        .position-card-pnl {
            font-size: 1.1em;
            font-weight: bold;
        }

        .position-card-info {
            font-size: 0.75em;
            color: #666;
        }

        .position-card-label {
            color: #999;
            margin-right: 3px;
        }

        .position-card-value {
            font-weight: 600;
            color: #333;
        }

        .position-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7em;
            color: #666;
            padding-top: 6px;
            border-top: 1px dashed rgba(102, 126, 234, 0.2);
        }

        /* 交易历史卡片样式 */
        .trade-cards {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .trade-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.9) 100%);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.2s;
        }

        .trade-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .trade-card.closed {
            border-left: 3px solid #10b981;
        }

        .trade-card.closed.loss {
            border-left: 3px solid #ef4444;
        }

        .trade-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .trade-card-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .trade-card-symbol {
            font-size: 1em;
            font-weight: bold;
            color: #333;
        }

        .trade-card-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .trade-card-pnl {
            font-size: 1.2em;
            font-weight: bold;
        }

        .trade-card-roi {
            font-size: 0.95em;
            font-weight: 600;
        }

        .trade-card-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.75em;
            color: #666;
        }

        .trade-card-detail {
            display: flex;
            flex-direction: column;
        }

        .trade-card-detail-label {
            color: #999;
            margin-bottom: 2px;
        }

        .trade-card-detail-value {
            color: #333;
            font-weight: 500;
        }

        /* 小标签样式 */
        .badge-sm {
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 500;
        }

        .mini-btn {
            padding: 4px 10px;
            font-size: 0.75em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            background: #667eea;
            color: white;
        }

        .mini-btn:hover {
            background: #5568d3;
            transform: scale(1.05);
        }
        
        .loading::after {
            content: '...';
            animation: pulse 1.5s infinite;
        }
        
        /* 图表控制区域 */
        .chart-controls-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
        }

        .position-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .position-selector-wrapper label {
            color: #666;
            font-size: 0.85em;
            font-weight: 500;
        }

        .position-selector-wrapper select {
            padding: 6px 12px;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 0.85em;
            cursor: pointer;
            background: white;
        }

        /* 时间周期按钮组 */
        .timeframe-selector {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }

        .timeframe-btn {
            padding: 5px 10px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75em;
            font-weight: 500;
            color: #666;
            transition: all 0.2s;
            min-width: 40px;
        }

        .timeframe-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .timeframe-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }
        
        /* 查看图表按钮 */
        .view-chart-btn {
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s;
        }
        
        .view-chart-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* 图表容器样式 */
        .chart-wrapper {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .chart-title {
            font-size: 1em;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 10px;
            padding: 8px 10px;
            background: #f9fafb;
            border-radius: 6px;
        }

        .chart-info-grid.two-rows {
            grid-template-rows: auto auto;
        }

        .info-item {
            font-size: 0.8em;
        }

        .info-label {
            color: #666;
            font-weight: 500;
            margin-right: 3px;
        }

        .info-value {
            font-weight: bold;
            color: #333;
        }

        #charts-container {
            height: 100%;
            width: 100%;
        }

        .placeholder {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .placeholder-icon {
            font-size: 3em;
            margin-bottom: 20px;
        }

        /* 三栏布局 */
        .main-layout {
            display: grid;
            grid-template-columns: 200px 1fr 280px;
            gap: 20px;
            margin-bottom: 20px;
            height: 700px;
        }

        /* 底部区域 */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .bottom-panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .bottom-panel h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .bottom-panel-content {
            max-height: 400px;
            overflow-y: auto;
        }

        /* 目标进度面板 */
        .progress-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .progress-stat-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .progress-stat-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
        }

        .progress-stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
        }

        .progress-stat-value.positive {
            color: #10b981;
        }

        .progress-stat-value.negative {
            color: #ef4444;
        }

        .progress-bar-large {
            width: 100%;
            height: 50px;
            background: #e5e7eb;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-fill-large {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2em;
            transition: width 0.5s;
        }

        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.95em;
            color: #666;
            margin-top: 10px;
        }

        .daily-breakdown {
            margin-top: 15px;
        }

        .daily-breakdown-title {
            font-size: 1em;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
        }

        .daily-item {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .daily-item:last-child {
            border-bottom: none;
        }

        .daily-date {
            color: #666;
        }

        .daily-value {
            font-weight: 600;
        }

        .daily-value.positive {
            color: #10b981;
        }

        .daily-value.negative {
            color: #ef4444;
        }

        .left-panel {
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .left-panel h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
        }

        .left-panel-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .center-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .center-panel h2 {
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #333;
        }

        .center-panel-content {
            height: 600px;
            overflow-y: auto;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
        }

        .right-panel-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .right-panel-section h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
        }

        /* 监控列表优化 - 垂直排列 */
        .watchlist-vertical {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .watch-card-vertical {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 12px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .watch-card-vertical:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .watch-card-vertical.has-position {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .watch-card-vertical .watch-info {
            flex: 1;
        }

        .watch-card-vertical .watch-symbol {
            font-size: 1.1em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .watch-card-vertical.has-position .watch-symbol {
            color: #10b981;
            font-size: 1.15em;
        }

        .watch-card-vertical .watch-price {
            font-size: 0.95em;
            color: #333;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .watch-card-vertical .watch-confidence {
            font-size: 0.8em;
            color: #667eea;
            font-weight: 700;
            margin-top: 3px;
        }

        .watch-card-vertical .watch-icon {
            font-size: 1.5em;
        }

        /* 顶部按钮栏 */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .panel-header h2 {
            margin: 0;
        }

        .header-btn {
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.3s;
        }

        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* 筛选按钮组 */
        .filter-buttons {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            padding: 5px 12px;
            background: #2d3748;
            color: #a0aec0;
            border: 1px solid #4a5568;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #374151;
            border-color: #667eea;
            color: white;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        /* 滚动条样式 */
        .left-panel-content::-webkit-scrollbar,
        .center-panel-content::-webkit-scrollbar,
        .right-panel::-webkit-scrollbar,
        .bottom-panel-content::-webkit-scrollbar {
            width: 6px;
        }

        .left-panel-content::-webkit-scrollbar-track,
        .center-panel-content::-webkit-scrollbar-track,
        .right-panel::-webkit-scrollbar-track,
        .bottom-panel-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb,
        .center-panel-content::-webkit-scrollbar-thumb,
        .right-panel::-webkit-scrollbar-thumb,
        .bottom-panel-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb:hover,
        .center-panel-content::-webkit-scrollbar-thumb:hover,
        .right-panel::-webkit-scrollbar-thumb:hover,
        .bottom-panel-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* 响应式：小屏幕时恢复垂直布局 */
        @media (max-width: 1200px) {
            .main-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                height: auto;
            }

            .bottom-section {
                grid-template-columns: 1fr;
            }

            .watchlist-vertical {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .watch-card-vertical {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 交易助手仪表盘 v1.2</h1>
            <div class="subtitle">Paper Trading System - 按需加载 - Port 5111</div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">资金统计</div>
                <div class="value" id="current-capital" style="font-size: 1.5em;">-</div>
                <div class="subtext" style="display: flex; flex-direction: column; gap: 4px; margin-top: 8px;">
                    <span>💰 初始: <span id="initial-capital">2000U</span></span>
                    <span>💵 可用: <span id="available-capital" style="color: #10b981; font-weight: bold;">-</span></span>
                    <span>🔒 占用: <span id="margin-used" style="color: #999;">-</span></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">总盈亏 (已扣费)</div>
                <div class="value" id="total-pnl">-</div>
                <div class="subtext" style="display: flex; flex-direction: column; gap: 4px; margin-top: 8px;">
                    <span>🎯 目标: <span id="target-profit">3400U</span></span>
                    <span>💳 交易费: <span id="total-fees" style="color: #ef4444;">-</span></span>
                    <span>⚡ 资金费: <span id="total-funding-fees" style="color: #ef4444;">-</span></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">胜率</div>
                <div class="value" id="win-rate">-</div>
                <div class="subtext"><span id="win-count">0</span> 胜 / <span id="total-count">0</span> 笔</div>
            </div>
            
            <div class="stat-card">
                <div class="label">持仓数</div>
                <div class="value" id="open-positions">-</div>
                <div class="subtext">最多同时3个</div>
            </div>
        </div>

        <!-- 三栏布局 -->
        <div class="main-layout">
            <!-- 左侧：监控列表 -->
            <div class="left-panel">
                <div class="panel-header">
                    <h2 style="font-size: 1.2em;">👁️ 监控列表</h2>
                    <div style="font-size: 0.75em; color: #999; margin-top: 4px;">
                        <span id="watchlist-countdown">刷新: --</span>
                    </div>
                </div>
                <div class="left-panel-content">
                    <div id="watchlist-container">
                        <div class="loading">加载中</div>
                    </div>
                </div>
            </div>

            <!-- 中间：持仓实时图表 -->
            <div class="center-panel">
                <h2>📈 持仓实时图表</h2>
                <div id="chart-controls" style="display: none;">
                    <div class="chart-controls-wrapper">
                        <!-- 中间：时间周期 -->
                        <div class="timeframe-selector">
                            <button class="timeframe-btn active" data-interval="5m" onclick="changeTimeframe('5m', this)">5m</button>
                            <button class="timeframe-btn" data-interval="10m" onclick="changeTimeframe('10m', this)">10m</button>
                            <button class="timeframe-btn" data-interval="30m" onclick="changeTimeframe('30m', this)">30m</button>
                            <button class="timeframe-btn" data-interval="1h" onclick="changeTimeframe('1h', this)">1h</button>
                            <button class="timeframe-btn" data-interval="4h" onclick="changeTimeframe('4h', this)">4h</button>
                            <button class="timeframe-btn" data-interval="1d" onclick="changeTimeframe('1d', this)">1d</button>
                        </div>
                        <!-- 右上：持仓选择 -->
                        <div class="position-selector-wrapper">
                            <label>持仓:</label>
                            <select id="position-selector" onchange="loadSelectedChart()">
                                <option value="">-- 请选择 --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="center-panel-content">
                    <div id="charts-container">
                        <div class="placeholder">
                            <div class="placeholder-icon">📊</div>
                            <div style="font-size: 1.1em; margin-bottom: 8px;">请从右侧"当前持仓"点击查看</div>
                            <div style="font-size: 0.9em; color: #999;">或使用上方下拉框选择</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧：当前持仓 -->
            <div class="right-panel">
                <div class="right-panel-section">
                    <div class="panel-header">
                        <h2>📦 当前持仓</h2>
                    </div>
                    <div class="filter-buttons" style="margin-bottom: 12px;">
                        <button class="filter-btn active" onclick="filterPositions('all')">全部</button>
                        <button class="filter-btn" onclick="filterPositions('long')">📈 做多</button>
                        <button class="filter-btn" onclick="filterPositions('short')">📉 做空</button>
                    </div>
                    <div id="positions-table">
                        <div class="loading">加载中</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 底部区域：交易历史 + 目标进度 -->
        <div class="bottom-section">
            <!-- 交易历史 -->
            <div class="bottom-panel">
                <h2>📊 交易历史</h2>
                <div class="bottom-panel-content" id="trades-table">
                    <div class="loading">加载中</div>
                </div>
            </div>

            <!-- 目标进度 -->
            <div class="bottom-panel">
                <h2>🎯 目标进度追踪</h2>
                <div class="progress-panel">
                    <!-- 进度条 -->
                    <div>
                        <div class="progress-bar-large">
                            <div class="progress-fill-large" id="progress-bar-large" style="width: 0%">0%</div>
                        </div>
                        <div class="progress-details">
                            <span>已完成: <strong id="progress-earned">0U</strong></span>
                            <span>还需: <strong id="progress-remaining">3400U</strong></span>
                        </div>
                    </div>

                    <!-- 统计数据 -->
                    <div class="progress-stats">
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">目标金额</div>
                            <div class="progress-stat-value" id="progress-target">3400U</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">当前盈亏</div>
                            <div class="progress-stat-value" id="progress-current">-</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">平均日收益</div>
                            <div class="progress-stat-value" id="progress-daily-avg">-</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">预计完成天数</div>
                            <div class="progress-stat-value" id="progress-days">-</div>
                        </div>
                    </div>

                    <!-- 每日盈亏明细 -->
                    <div class="daily-breakdown">
                        <div class="daily-breakdown-title">📅 最近7天盈亏</div>
                        <div id="daily-pnl-list">
                            <div class="loading">加载中</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="refresh-time">
            最后更新: <span id="last-update">-</span> | 每60秒自动刷新
        </div>
    </div>
    
    <script>
        // 全局变量
        let currentInterval = '5m';
        let currentPositions = [];
        let allPositions = []; // 存储所有持仓（未筛选）
        let positionFilter = 'all'; // 持仓筛选状态: all, long, short
        let selectedPositionIndex = -1;
        let currentTrades = [];

        // 筛选持仓
        function filterPositions(filter) {
            positionFilter = filter;

            // 更新按钮样式
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // 重新渲染持仓
            renderPositions();
        }

        // 查看指定持仓的图表
        function viewChart(symbol, index) {
            selectedPositionIndex = index;

            // 显示图表控制区域
            document.getElementById('chart-controls').style.display = 'block';

            // 更新持仓选择器
            const selector = document.getElementById('position-selector');
            selector.value = index;

            // 滚动到图表区域
            document.getElementById('charts-container').scrollIntoView({ behavior: 'smooth', block: 'start' });

            // 加载该持仓的图表
            if (currentPositions.length > 0 && index >= 0 && index < currentPositions.length) {
                loadSingleChart(currentPositions[index]);
            }
        }

        // 查看交易复盘图表
        async function viewTradeChart(index) {
            if (!currentTrades || index < 0 || index >= currentTrades.length) {
                alert('无法加载交易数据');
                return;
            }

            const trade = currentTrades[index];

            // 隐藏常规图表控制
            document.getElementById('chart-controls').style.display = 'none';

            // 显示加载状态
            const container = document.getElementById('charts-container');
            container.innerHTML = '<div class="loading">加载复盘图表中...</div>';

            // 滚动到图表区域
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                // 获取K线数据（使用5分钟周期）
                const klineResp = await fetch(`/api/kline/${trade.symbol}?interval=5m`);
                const klineData = await klineResp.json();

                // 创建图表容器
                const chartDiv = document.createElement('div');
                chartDiv.className = 'chart-wrapper';

                const directionEmoji = trade.direction === 'LONG' ? '📈' : '📉';
                const directionText = trade.direction === 'LONG' ? '做多' : '做空';
                const directionColor = trade.direction === 'LONG' ? '#10b981' : '#ef4444';
                const pnlColor = trade.pnl >= 0 ? '#10b981' : '#ef4444';
                const roiColor = trade.roi >= 0 ? '#10b981' : '#ef4444';

                const title = document.createElement('div');
                title.className = 'chart-title';
                title.innerHTML = `
                    <span>📊</span>
                    <span>${trade.symbol}/USDT 复盘</span>
                    <span style="color: ${directionColor}; font-size: 0.9em;">${directionText}</span>
                    <span style="color: #667eea; font-size: 0.85em;">${trade.leverage}x杠杆</span>
                    <span style="color: #999; font-size: 0.75em; margin-left: auto;">已平仓</span>
                `;

                const info = document.createElement('div');
                info.className = 'chart-info-grid';
                info.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">📍 入场价:</span>
                        <span class="info-value" style="color: #3b82f6;">$${formatNumber(trade.entry_price, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🚪 出场价:</span>
                        <span class="info-value" style="color: #f59e0b;">$${formatNumber(trade.exit_price, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💼 仓位:</span>
                        <span class="info-value">${formatNumber(trade.amount, 0)}U × ${trade.leverage}x</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💵 盈亏:</span>
                        <span class="info-value" style="color: ${pnlColor}; font-size: 1.15em;">${formatCurrency(trade.pnl)}U</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📊 ROI:</span>
                        <span class="info-value" style="color: ${roiColor}; font-size: 1.15em;">${formatCurrency(trade.roi)}%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💳 交易手续费:</span>
                        <span class="info-value" style="color: #999;">$${formatNumber(trade.fee, 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⚡ 资金费率:</span>
                        <span class="info-value" style="color: #999;">$${formatNumber(trade.funding_fee || 0, 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 总费用:</span>
                        <span class="info-value" style="color: #ef4444;">$${formatNumber((trade.fee || 0) + (trade.funding_fee || 0), 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 入场时间:</span>
                        <span class="info-value" style="font-size: 0.9em;">${formatTime(trade.entry_time)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 出场时间:</span>
                        <span class="info-value" style="font-size: 0.9em;">${formatTime(trade.exit_time)}</span>
                    </div>
                    ${trade.reason ? `
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">📝 平仓原因:</span>
                        <span class="info-value" style="color: #667eea;">${trade.reason}</span>
                    </div>
                    ` : ''}
                `;

                const canvasWrapper = document.createElement('div');
                canvasWrapper.style.flex = '1';
                canvasWrapper.style.minHeight = '0';
                canvasWrapper.style.position = 'relative';

                const canvas = document.createElement('canvas');
                canvas.id = `trade-chart-${index}`;

                canvasWrapper.appendChild(canvas);
                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvasWrapper);
                container.innerHTML = '';
                container.appendChild(chartDiv);

                // 准备图表数据
                const timeFormat = {hour: '2-digit', minute: '2-digit'};
                const labels = klineData.map(k => new Date(k.time).toLocaleString('zh-CN', timeFormat));
                const prices = klineData.map(k => k.close);

                // 找到入场和出场时间对应的索引
                const entryTime = new Date(trade.entry_time).getTime();
                const exitTime = new Date(trade.exit_time).getTime();

                let entryIndex = 0;
                let exitIndex = klineData.length - 1;

                for (let i = 0; i < klineData.length; i++) {
                    if (klineData[i].time >= entryTime && entryIndex === 0) {
                        entryIndex = i;
                    }
                    if (klineData[i].time >= exitTime) {
                        exitIndex = i;
                        break;
                    }
                }

                // 创建图表
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '价格走势',
                            data: prices,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.1,
                            pointRadius: 0,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            annotation: {
                                annotations: {
                                    // 入场价格线（蓝色虚线）
                                    entryLine: {
                                        type: 'line',
                                        yMin: trade.entry_price,
                                        yMax: trade.entry_price,
                                        borderColor: '#3b82f6',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `📍 入场 $${formatNumber(trade.entry_price, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#3b82f6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 入场点标记
                                    entryPoint: {
                                        type: 'point',
                                        xValue: entryIndex,
                                        yValue: trade.entry_price,
                                        backgroundColor: '#3b82f6',
                                        borderColor: '#ffffff',
                                        borderWidth: 3,
                                        radius: 8
                                    },
                                    // 出场价格线（橙色虚线）
                                    exitLine: {
                                        type: 'line',
                                        yMin: trade.exit_price,
                                        yMax: trade.exit_price,
                                        borderColor: '#f59e0b',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🚪 出场 $${formatNumber(trade.exit_price, 6)}`,
                                            enabled: true,
                                            position: 'end',
                                            backgroundColor: '#f59e0b',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 出场点标记
                                    exitPoint: {
                                        type: 'point',
                                        xValue: exitIndex,
                                        yValue: trade.exit_price,
                                        backgroundColor: '#f59e0b',
                                        borderColor: '#ffffff',
                                        borderWidth: 3,
                                        radius: 8
                                    },
                                    // 止盈线（绿色虚线）
                                    takeProfitLine: {
                                        type: 'line',
                                        yMin: trade.take_profit,
                                        yMax: trade.take_profit,
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🎯 止盈 $${formatNumber(trade.take_profit, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#10b981',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 止损线（红色虚线）
                                    stopLossLine: {
                                        type: 'line',
                                        yMin: trade.stop_loss,
                                        yMax: trade.stop_loss,
                                        borderColor: '#ef4444',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🛑 止损 $${formatNumber(trade.stop_loss, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#ef4444',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#999',
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                display: true,
                                position: 'right',
                                grid: {
                                    display: true,
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#999',
                                    callback: function(value) {
                                        return '$' + value.toFixed(6);
                                    }
                                }
                            }
                        }
                    }
                });

            } catch (error) {
                console.error('加载复盘图表失败:', error);
                container.innerHTML = '<p style="color: #ef4444;">加载图表失败</p>';
            }
        }
        
        // 从下拉框选择持仓
        function loadSelectedChart() {
            const selector = document.getElementById('position-selector');
            const index = parseInt(selector.value);
            
            if (!isNaN(index) && index >= 0 && index < currentPositions.length) {
                selectedPositionIndex = index;
                loadSingleChart(currentPositions[index]);
            } else {
                // 清空图表
                document.getElementById('charts-container').innerHTML = `
                    <div class="placeholder">
                        <div class="placeholder-icon">📊</div>
                        <div style="font-size: 1.2em;">请选择要查看的持仓</div>
                    </div>
                `;
            }
        }
        
        // 切换时间周期
        function changeTimeframe(interval, btn) {
            currentInterval = interval;
            
            // 更新按钮状态
            document.querySelectorAll('.timeframe-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // 重新加载当前选中的图表
            if (selectedPositionIndex >= 0 && currentPositions.length > 0) {
                loadSingleChart(currentPositions[selectedPositionIndex]);
            }
        }
        
        function formatNumber(num, decimals = 2) {
            if (num === null || num === undefined) return '-';
            return Number(num).toFixed(decimals);
        }
        
        function formatCurrency(num) {
            if (num === null || num === undefined) return '-';
            const formatted = formatNumber(num, 2);
            return num >= 0 ? '+' + formatted : formatted;
        }
        
        function formatTime(timeStr) {
            if (!timeStr) return '-';
            const date = new Date(timeStr);
            return date.toLocaleString('zh-CN', { 
                month: '2-digit', 
                day: '2-digit', 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        async function loadStats() {
            try {
                const response = await fetch('/api/stats');
                const stats = await response.json();
                
                document.getElementById('current-capital').textContent = formatNumber(stats.current_capital, 2) + 'U';
                document.getElementById('current-capital').className = 'value ' + (stats.current_capital >= stats.initial_capital ? 'positive' : 'negative');

                document.getElementById('initial-capital').textContent = formatNumber(stats.initial_capital, 2) + 'U';
                document.getElementById('available-capital').textContent = formatNumber(stats.available_capital, 2) + 'U';
                document.getElementById('margin-used').textContent = formatNumber(stats.margin_used, 2) + 'U';
                
                document.getElementById('total-pnl').textContent = formatCurrency(stats.total_pnl) + 'U';
                document.getElementById('total-pnl').className = 'value ' + (stats.total_pnl >= 0 ? 'positive' : 'negative');

                document.getElementById('target-profit').textContent = formatNumber(stats.target_profit, 2) + 'U';
                document.getElementById('total-fees').textContent = formatNumber(stats.total_fees || 0, 2) + 'U';
                document.getElementById('total-funding-fees').textContent = formatNumber(stats.total_funding_fees || 0, 2) + 'U';
                
                document.getElementById('win-rate').textContent = formatNumber(stats.win_rate, 1) + '%';
                document.getElementById('win-count').textContent = stats.win_trades || 0;
                document.getElementById('total-count').textContent = stats.total_trades || 0;
                
                document.getElementById('open-positions').textContent = stats.open_positions || 0;
                
                const progress = Math.min(100, Math.max(0, stats.progress || 0));
                const progressBar = document.getElementById('progress-bar');
                progressBar.style.width = progress + '%';
                progressBar.textContent = formatNumber(progress, 1) + '%';
                
                const earned = stats.total_pnl || 0;
                const remaining = Math.max(0, stats.target_profit - earned);
                document.getElementById('earned').textContent = formatNumber(earned, 2) + 'U';
                document.getElementById('remaining').textContent = formatNumber(remaining, 2) + 'U';
                
            } catch (error) {
                console.error('加载统计失败:', error);
            }
        }
        
        async function loadPositions() {
            try {
                const response = await fetch('/api/positions');
                const positions = await response.json();

                const container = document.getElementById('positions-table');

                if (positions.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无持仓</p>';
                    allPositions = [];
                    currentPositions = [];
                    document.getElementById('position-selector').innerHTML = '<option value="">-- 暂无持仓 --</option>';
                    return;
                }

                // 获取所有持仓的当前价格
                const pricePromises = positions.map(pos =>
                    fetch(`/api/price/${pos.symbol}`).then(r => r.json())
                );
                const prices = await Promise.all(pricePromises);

                // 为每个持仓添加当前价格
                positions.forEach((pos, i) => {
                    pos.currentPrice = prices[i].price || 0;
                });

                // 保存到全局变量
                allPositions = positions;

                // 渲染持仓（应用筛选）
                renderPositions();

            } catch (error) {
                console.error('加载持仓失败:', error);
                document.getElementById('positions-table').innerHTML = '<p style="color: #ef4444;">加载失败</p>';
            }
        }

        function renderPositions() {
            const container = document.getElementById('positions-table');

            if (!allPositions || allPositions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无持仓</p>';
                return;
            }

            // 根据筛选器过滤持仓
            let filteredPositions = allPositions;
            if (positionFilter === 'long') {
                filteredPositions = allPositions.filter(pos => pos.direction === 'LONG');
            } else if (positionFilter === 'short') {
                filteredPositions = allPositions.filter(pos => pos.direction === 'SHORT');
            }

            if (filteredPositions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">无匹配持仓</p>';
                return;
            }

            let html = '<div class="position-cards">';

            filteredPositions.forEach((pos, i) => {
                const currentPrice = pos.currentPrice || 0;

                let pricePct = 0;
                if (pos.direction === 'LONG') {
                    pricePct = (currentPrice - pos.entry_price) / pos.entry_price;
                } else {
                    pricePct = (pos.entry_price - currentPrice) / pos.entry_price;
                }

                const roi = pricePct * pos.leverage * 100;
                const pnl = pos.amount * pricePct * pos.leverage;

                const directionText = pos.direction === 'LONG' ? '做多' : '做空';
                const directionClass = pos.direction.toLowerCase();
                const directionEmoji = pos.direction === 'LONG' ? '📈' : '📉';
                const pnlColor = pnl >= 0 ? '#10b981' : '#ef4444';

                // 找到在allPositions中的原始索引（用于viewChart）
                const originalIndex = allPositions.indexOf(pos);

                html += `
                    <div class="position-card ${directionClass}" onclick="viewChart('${pos.symbol}', ${originalIndex})">
                        <div class="position-card-header">
                            <div class="position-card-title">
                                <span class="position-card-symbol">${directionEmoji} ${pos.symbol}</span>
                                <span class="badge-sm ${directionClass}">${directionText}</span>
                                <span class="badge-sm" style="background: #667eea; color: white;">${pos.leverage}x</span>
                            </div>
                            <button class="mini-btn" onclick="event.stopPropagation(); viewChart('${pos.symbol}', ${originalIndex})">📊 图表</button>
                        </div>

                        <div class="position-card-body">
                            <div class="position-card-main">
                                <div>
                                    <div style="font-size: 0.75em; color: #999;">当前价</div>
                                    <div style="font-size: 1.1em; font-weight: bold; color: #667eea;">$${formatNumber(currentPrice, 4)}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.75em; color: #999;">盈亏</div>
                                    <div class="position-card-pnl" style="color: ${pnlColor};">
                                        ${formatCurrency(pnl)}U
                                    </div>
                                    <div style="font-size: 0.85em; color: ${pnlColor};">
                                        ${formatCurrency(roi)}%
                                    </div>
                                </div>
                            </div>

                            <div class="position-card-info">
                                <span class="position-card-label">入场:</span>
                                <span class="position-card-value">$${formatNumber(pos.entry_price, 4)}</span>
                            </div>

                            <div class="position-card-info">
                                <span class="position-card-label">金额:</span>
                                <span class="position-card-value">${formatNumber(pos.amount, 0)}U</span>
                            </div>
                        </div>

                        <div class="position-card-footer">
                            <span>🎯 ${formatNumber(pos.take_profit, 4)}</span>
                            <span>🛑 ${formatNumber(pos.stop_loss, 4)}</span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            // 更新currentPositions为所有持仓（用于其他功能）
            currentPositions = allPositions;

            // 填充持仓选择器
            const selector = document.getElementById('position-selector');
            selector.innerHTML = '<option value="">-- 请选择 --</option>';
            allPositions.forEach((pos, idx) => {
                const direction = pos.direction === 'LONG' ? '做多' : '做空';
                selector.innerHTML += `<option value="${idx}">${pos.symbol} ${direction} ${pos.leverage}x</option>`;
            });

            // 如果之前有选中的持仓，保持显示
            if (selectedPositionIndex >= 0 && selectedPositionIndex < allPositions.length) {
                selector.value = selectedPositionIndex;
                loadSingleChart(allPositions[selectedPositionIndex]);
            }
        }

        async function loadTrades() {
            try {
                const response = await fetch('/api/trades');
                const trades = await response.json();

                const container = document.getElementById('trades-table');

                if (trades.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无交易记录</p>';
                    return;
                }

                let html = '<div class="trade-cards">';

                trades.forEach((trade, index) => {
                    const pnl = trade.pnl || 0;
                    const roi = trade.roi || 0;

                    const directionText = trade.direction === 'LONG' ? '做多' : '做空';
                    const directionClass = trade.direction.toLowerCase();
                    const statusText = trade.status === 'OPEN' ? '持仓中' : '已平仓';
                    const statusClass = trade.status.toLowerCase();
                    const pnlColor = pnl >= 0 ? '#10b981' : '#ef4444';
                    const lossClass = (trade.status === 'CLOSED' && pnl < 0) ? 'loss' : '';

                    html += `
                        <div class="trade-card ${statusClass} ${lossClass}">
                            <div class="trade-card-header">
                                <div class="trade-card-title">
                                    <span class="trade-card-symbol">${trade.symbol}</span>
                                    <span class="badge-sm ${directionClass}">${directionText}</span>
                                    <span class="badge-sm ${statusClass}">${statusText}</span>
                                </div>
                                ${trade.status === 'CLOSED' ?
                                    `<button class="mini-btn" onclick="viewTradeChart(${index})">📊</button>` :
                                    '<span style="color: #999; font-size: 0.75em;">-</span>'
                                }
                            </div>

                            <div class="trade-card-main">
                                <div>
                                    <div class="trade-card-pnl" style="color: ${pnlColor};">
                                        ${formatCurrency(pnl)}U
                                    </div>
                                </div>
                                <div class="trade-card-roi" style="color: ${pnlColor};">
                                    ${formatCurrency(roi)}%
                                </div>
                            </div>

                            <div class="trade-card-details">
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">入场</span>
                                    <span class="trade-card-detail-value">$${formatNumber(trade.entry_price, 4)}</span>
                                </div>
                                ${trade.exit_price ? `
                                    <div class="trade-card-detail">
                                        <span class="trade-card-detail-label">出场</span>
                                        <span class="trade-card-detail-value">$${formatNumber(trade.exit_price, 4)}</span>
                                    </div>
                                ` : '<div class="trade-card-detail"></div>'}
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">仓位</span>
                                    <span class="trade-card-detail-value">${formatNumber(trade.amount, 0)}U</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">杠杆</span>
                                    <span class="trade-card-detail-value">${trade.leverage}x</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;

                // 保存到全局变量
                currentTrades = trades;

            } catch (error) {
                console.error('加载交易历史失败:', error);
                document.getElementById('trades-table').innerHTML = '<p style="color: #ef4444; font-size: 0.9em;">加载失败</p>';
            }
        }

        async function loadWatchlist() {
            try {
                const response = await fetch('/api/watchlist');
                const watchlist = await response.json();

                const container = document.getElementById('watchlist-container');

                if (watchlist.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">无监控币种</p>';
                    return;
                }

                let html = '<div class="watchlist-vertical">';

                watchlist.forEach(coin => {
                    const hasPosition = coin.has_position ? 'has-position' : '';
                    // 使用真实信心度
                    const confidence = coin.confidence || 0;

                    // 方向/建议标签
                    let directionBadge = '';
                    if (coin.has_position && coin.direction) {
                        // 有持仓：显示当前方向
                        const isLong = coin.direction === 'LONG';
                        const directionText = isLong ? '做多' : '做空';
                        const directionEmoji = isLong ? '📈' : '📉';
                        const directionColor = isLong ? '#10b981' : '#ef4444';
                        directionBadge = `<span style="font-size: 0.75em; background: ${directionColor}; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">${directionEmoji} ${directionText}</span>`;
                    } else if (!coin.has_position && coin.suggested_direction) {
                        // 无持仓：显示建议
                        const isLong = coin.suggested_direction === 'LONG';
                        const suggestionText = isLong ? '建议做多' : '建议做空';
                        const suggestionEmoji = isLong ? '📈' : '📉';
                        const suggestionColor = isLong ? '#10b981' : '#f59e0b';
                        directionBadge = `<span style="font-size: 0.7em; background: ${suggestionColor}; color: white; padding: 2px 5px; border-radius: 4px; margin-left: 6px; opacity: 0.8;">${suggestionEmoji} ${suggestionText}</span>`;
                    }

                    // 止盈止损和杠杆信息
                    let detailsInfo = '';

                    if (coin.has_position) {
                        // 持仓币种：显示信心度、杠杆、止盈、止损
                        detailsInfo = `
                            <div style="font-size: 0.7em; color: #666; margin-top: 4px; line-height: 1.4;">
                                ${confidence > 0 ? `<div>💪 信心度: ${confidence}%</div>` : ''}
                                ${coin.leverage ? `<div>⚡ 杠杆: ${coin.leverage}x</div>` : ''}
                                ${coin.take_profit ? `<div>🎯 止盈: $${formatNumber(coin.take_profit, 4)}</div>` : ''}
                                ${coin.stop_loss ? `<div>🛑 止损: $${formatNumber(coin.stop_loss, 4)}</div>` : ''}
                            </div>
                        `;
                    } else {
                        // 非持仓币种：显示信心度、杠杆、止盈止损价位、预估盈利%、预估亏损%
                        detailsInfo = `
                            <div style="font-size: 0.7em; color: #666; margin-top: 4px; line-height: 1.4;">
                                ${confidence >= 0 ? `<div>💪 信心度: ${confidence}%</div>` : ''}
                                ${coin.leverage ? `<div>⚡ 杠杆: ${coin.leverage}x</div>` : ''}
                                ${coin.take_profit ? `<div>🎯 止盈: $${formatNumber(coin.take_profit, 4)}</div>` : ''}
                                ${coin.stop_loss ? `<div>🛑 止损: $${formatNumber(coin.stop_loss, 4)}</div>` : ''}
                                ${coin.profit_pct !== null ? `<div style="color: #10b981;">📈 预估盈利: ${formatNumber(coin.profit_pct, 2)}%</div>` : ''}
                                ${coin.loss_pct !== null ? `<div style="color: #ef4444;">📉 预估亏损: ${formatNumber(coin.loss_pct, 2)}%</div>` : ''}
                            </div>
                        `;
                    }

                    html += `
                        <div class="watch-card-vertical ${hasPosition}">
                            <div class="watch-info">
                                <div class="watch-symbol">${coin.symbol} ${directionBadge}</div>
                                <div class="watch-price">$${formatNumber(coin.price, 4)}</div>
                                ${detailsInfo}
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;

            } catch (error) {
                console.error('加载监控列表失败:', error);
                document.getElementById('watchlist-container').innerHTML = '<p style="color: #ef4444; font-size: 0.9em;">加载失败</p>';
            }
        }

        async function loadSingleChart(pos) {
            const container = document.getElementById('charts-container');
            container.innerHTML = '<div class="loading">加载图表中</div>';
            
            // 显示图表控制区域
            document.getElementById('chart-controls').style.display = 'block';
            
            try {
                // 获取K线数据（使用当前选中的时间周期）
                const klineResp = await fetch(`/api/kline/${pos.symbol}?interval=${currentInterval}`);
                const klineData = await klineResp.json();
                
                // 获取当前价格
                const priceResp = await fetch(`/api/price/${pos.symbol}`);
                const priceData = await priceResp.json();
                const currentPrice = priceData.price || 0;
                
                // 计算盈亏
                let pricePct = 0;
                if (pos.direction === 'LONG') {
                    pricePct = (currentPrice - pos.entry_price) / pos.entry_price;
                } else {
                    pricePct = (pos.entry_price - currentPrice) / pos.entry_price;
                }
                const roi = pricePct * pos.leverage * 100;
                const pnl = pos.amount * pricePct * pos.leverage;
                
                // 创建图表容器
                const chartDiv = document.createElement('div');
                chartDiv.className = 'chart-wrapper';
                
                const directionEmoji = pos.direction === 'LONG' ? '📈' : '📉';
                const directionText = pos.direction === 'LONG' ? '做多' : '做空';
                const directionColor = pos.direction === 'LONG' ? '#10b981' : '#ef4444';
                
                const title = document.createElement('div');
                title.className = 'chart-title';
                title.innerHTML = `
                    <span>${directionEmoji}</span>
                    <span>${pos.symbol}/USDT</span>
                    <span style="color: ${directionColor}; font-size: 0.9em;">${directionText}</span>
                    <span style="color: #667eea; font-size: 0.85em;">${pos.leverage}x杠杆</span>
                    <span style="color: #999; font-size: 0.75em; margin-left: auto;">${currentInterval}</span>
                `;
                
                const info = document.createElement('div');
                info.className = 'chart-info-grid two-rows';
                info.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">📍 入场:</span>
                        <span class="info-value" style="color: #3b82f6;">$${formatNumber(pos.entry_price, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 当前:</span>
                        <span class="info-value" style="color: #8b5cf6; font-weight: bold;">$${formatNumber(currentPrice, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🎯 止盈:</span>
                        <span class="info-value" style="color: #10b981;">$${formatNumber(pos.take_profit, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🛑 止损:</span>
                        <span class="info-value" style="color: #ef4444;">$${formatNumber(pos.stop_loss, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💼 仓位:</span>
                        <span class="info-value">${formatNumber(pos.amount, 0)}U × ${pos.leverage}x</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💵 盈亏:</span>
                        <span class="info-value" style="color: ${pnl >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">${formatCurrency(pnl)}U</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📊 ROI:</span>
                        <span class="info-value" style="color: ${roi >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">${formatCurrency(roi)}%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 开仓:</span>
                        <span class="info-value" style="font-size: 0.85em;">${formatTime(pos.entry_time)}</span>
                    </div>
                `;

                const canvasWrapper = document.createElement('div');
                canvasWrapper.style.flex = '1';
                canvasWrapper.style.minHeight = '0';
                canvasWrapper.style.position = 'relative';

                const canvas = document.createElement('canvas');
                canvas.id = `chart-${pos.symbol}`;

                canvasWrapper.appendChild(canvas);
                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvasWrapper);
                container.innerHTML = '';
                container.appendChild(chartDiv);
                
                // 准备图表数据（根据时间周期调整时间格式）
                let timeFormat = {};
                if (currentInterval === '1d') {
                    timeFormat = {month: 'short', day: 'numeric'};
                } else if (currentInterval === '4h' || currentInterval === '1h') {
                    timeFormat = {month: 'numeric', day: 'numeric', hour: '2-digit'};
                } else {
                    timeFormat = {hour: '2-digit', minute: '2-digit'};
                }
                
                const labels = klineData.map(k => new Date(k.time).toLocaleString('zh-CN', timeFormat));
                const prices = klineData.map(k => k.close);
                
                // 找到开仓时间对应的索引
                const entryTime = new Date(pos.entry_time).getTime();
                let entryIndex = 0;
                for (let i = 0; i < klineData.length; i++) {
                    if (klineData[i].time >= entryTime) {
                        entryIndex = i;
                        break;
                    }
                }
                
                // 创建图表
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '价格走势',
                            data: prices,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.1,
                            pointRadius: 0,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            annotation: {
                                annotations: {
                                    // 1. 购买价格线（蓝色虚线）
                                    entryLine: {
                                        type: 'line',
                                        yMin: pos.entry_price,
                                        yMax: pos.entry_price,
                                        borderColor: '#3b82f6',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `📍 入场 $${formatNumber(pos.entry_price, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#3b82f6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 购买点标记
                                    entryPoint: {
                                        type: 'point',
                                        xValue: entryIndex,
                                        yValue: pos.entry_price,
                                        backgroundColor: '#3b82f6',
                                        borderColor: '#ffffff',
                                        borderWidth: 2,
                                        radius: 6
                                    },
                                    // 2. 当前价格线（紫色粗线）
                                    currentLine: {
                                        type: 'line',
                                        yMin: currentPrice,
                                        yMax: currentPrice,
                                        borderColor: '#8b5cf6',
                                        borderWidth: 3,
                                        label: {
                                            content: `💰 当前 $${formatNumber(currentPrice, 6)}`,
                                            enabled: true,
                                            position: 'end',
                                            backgroundColor: '#8b5cf6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 3. 止盈线（绿色虚线）
                                    takeProfitLine: {
                                        type: 'line',
                                        yMin: pos.take_profit,
                                        yMax: pos.take_profit,
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🎯 止盈 $${formatNumber(pos.take_profit, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#10b981',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 4. 止损线（红色虚线）
                                    stopLossLine: {
                                        type: 'line',
                                        yMin: pos.stop_loss,
                                        yMax: pos.stop_loss,
                                        borderColor: '#ef4444',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🛑 止损 $${formatNumber(pos.stop_loss, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#ef4444',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true
                                },
                                ticks: {
                                    maxTicksLimit: 12,
                                    font: {
                                        size: 11,
                                        weight: '500'
                                    },
                                    color: '#666'
                                }
                            },
                            y: {
                                display: true,
                                position: 'right',
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.08)',
                                    drawBorder: true
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toFixed(6);
                                    },
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    },
                                    color: '#333'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        elements: {
                            point: {
                                radius: 0,
                                hitRadius: 10,
                                hoverRadius: 5
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error(`加载${pos.symbol}图表失败:`, error);
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444;">加载图表失败: ${error.message}</div>`;
            }
        }
        
        async function loadProgressTracking() {
            try {
                // 获取统计数据
                const statsResp = await fetch('/api/stats');
                const stats = await statsResp.json();

                // 获取每日数据
                const dailyResp = await fetch('/api/daily_stats');
                const dailyStats = await dailyResp.json();

                // 更新大进度条
                const progress = Math.min(100, Math.max(0, stats.progress || 0));
                const progressBar = document.getElementById('progress-bar-large');
                progressBar.style.width = progress + '%';
                progressBar.textContent = formatNumber(progress, 1) + '%';

                // 更新进度详情
                const earned = stats.total_pnl || 0;
                const remaining = Math.max(0, stats.target_profit - earned);
                document.getElementById('progress-earned').textContent = formatNumber(earned, 2) + 'U';
                document.getElementById('progress-remaining').textContent = formatNumber(remaining, 2) + 'U';

                // 更新统计卡片
                document.getElementById('progress-target').textContent = formatNumber(stats.target_profit, 2) + 'U';

                const currentPnl = document.getElementById('progress-current');
                currentPnl.textContent = formatCurrency(earned) + 'U';
                currentPnl.className = 'progress-stat-value ' + (earned >= 0 ? 'positive' : 'negative');

                // 计算平均日收益（基于最近7天）
                let totalDailyPnl = 0;
                let daysWithTrades = 0;
                dailyStats.forEach(day => {
                    if (day.trades > 0) {
                        totalDailyPnl += day.daily_pnl || 0;
                        daysWithTrades++;
                    }
                });

                const avgDailyPnl = daysWithTrades > 0 ? totalDailyPnl / daysWithTrades : 0;
                const avgDaily = document.getElementById('progress-daily-avg');
                avgDaily.textContent = formatCurrency(avgDailyPnl) + 'U/天';
                avgDaily.className = 'progress-stat-value ' + (avgDailyPnl >= 0 ? 'positive' : 'negative');

                // 计算预计完成天数
                const daysElement = document.getElementById('progress-days');
                if (avgDailyPnl > 0 && remaining > 0) {
                    const estimatedDays = Math.ceil(remaining / avgDailyPnl);
                    daysElement.textContent = estimatedDays + '天';
                    daysElement.className = 'progress-stat-value';
                } else if (remaining <= 0) {
                    daysElement.textContent = '已完成!';
                    daysElement.className = 'progress-stat-value positive';
                } else {
                    daysElement.textContent = '-';
                    daysElement.className = 'progress-stat-value';
                }

                // 渲染每日盈亏列表
                const dailyListContainer = document.getElementById('daily-pnl-list');
                if (dailyStats.length === 0) {
                    dailyListContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 10px;">暂无数据</p>';
                } else {
                    let html = '';
                    dailyStats.forEach(day => {
                        const pnlColor = day.daily_pnl >= 0 ? 'positive' : 'negative';
                        const winRate = day.trades > 0 ? (day.wins / day.trades * 100) : 0;
                        html += `
                            <div class="daily-item">
                                <span class="daily-date">${day.date} (${day.trades}笔, 胜率${formatNumber(winRate, 0)}%)</span>
                                <span class="daily-value ${pnlColor}">${formatCurrency(day.daily_pnl || 0)}U</span>
                            </div>
                        `;
                    });
                    dailyListContainer.innerHTML = html;
                }

            } catch (error) {
                console.error('加载目标进度失败:', error);
            }
        }

        function updateAll() {
            loadStats();
            loadWatchlist();
            loadPositions();
            loadTrades();
            loadProgressTracking();
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('zh-CN');
        }

        // 初始加载
        updateAll();

        // 每60秒刷新统计、持仓、交易历史
        setInterval(() => {
            loadStats();
            loadPositions();
            loadTrades();
            loadProgressTracking();
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('zh-CN');
        }, 60000);

        // 每10分钟刷新监控列表（包含信号分析，比较耗时）
        let watchlistCountdown = 600;  // 10分钟 = 600秒

        // 更新倒计时显示
        function updateWatchlistCountdown() {
            const minutes = Math.floor(watchlistCountdown / 60);
            const seconds = watchlistCountdown % 60;
            const countdownText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            document.getElementById('watchlist-countdown').textContent = `刷新: ${countdownText}`;

            watchlistCountdown--;

            if (watchlistCountdown < 0) {
                watchlistCountdown = 600;
                console.log('🔄 刷新监控列表（10分钟定时）');
                loadWatchlist();
            }
        }

        // 每秒更新倒计时
        setInterval(updateWatchlistCountdown, 1000);
        updateWatchlistCountdown();  // 立即显示初始倒计时
    </script>
</body>
</html>
'''

if __name__ == '__main__':
    print("=" * 60)
    print("🧪 交易助手仪表盘 v1.2 启动")
    print("=" * 60)
    print(f"📊 端口: 5111")
    print(f"💾 数据库: {DB_PATH}")
    print(f"🌐 访问地址: http://localhost:5111")
    print("=" * 60)
    print("✨ v1.2 新功能:")
    print("  - 按需加载图表（点击后才加载）")
    print("  - 一次只显示一个持仓图表")
    print("  - 持仓选择下拉框")
    print("  - 6种时间周期筛选")
    print("=" * 60)
    print()
    
    app.run(host='0.0.0.0', port=5111, debug=False)
