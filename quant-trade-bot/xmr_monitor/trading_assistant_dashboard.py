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
    """获取每日统计（最近7天）"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT 
                DATE(entry_time) as date,
                COUNT(*) as trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as wins,
                SUM(COALESCE(pnl, 0)) as daily_pnl
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
            AND entry_time >= date('now', '-7 days')
            GROUP BY DATE(entry_time)
            ORDER BY date DESC
        ''')
        
        daily_stats = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify(daily_stats)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/price/<symbol>')
def get_current_price(symbol):
    """获取币种当前价格（使用Binance API）"""
    try:
        # 使用Binance API，更快更稳定
        binance_symbol = f"{symbol}USDT"
        url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
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
        
        url = f"https://api.binance.com/api/v3/klines?symbol={binance_symbol}&interval={interval}&limit={limit}"
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
        # 监控币种 (13个)
        watch_symbols = [
            # 原有监控 (7个)
            'XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK',
            # 新增高分币种 (6个)
            'VET',   # 得分100 - VeChain
            'BNB',   # 得分80 - Binance Coin
            'INJ',   # 得分80 - Injective
            'LINK',  # 得分70 - Chainlink
            'OP',    # 得分70 - Optimism
            'FIL'    # 得分70 - Filecoin
        ]

        conn = get_db()
        cursor = conn.cursor()

        # 获取当前持仓
        cursor.execute('''
            SELECT symbol FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手' AND status = 'OPEN'
        ''')
        open_positions = set(row['symbol'] for row in cursor.fetchall())
        conn.close()

        # 获取每个币种的当前价格
        watchlist = []
        for symbol in watch_symbols:
            try:
                price_data = get_current_price(symbol)
                watchlist.append({
                    'symbol': symbol,
                    'price': price_data,
                    'has_position': symbol in open_positions
                })
            except Exception as e:
                watchlist.append({
                    'symbol': symbol,
                    'price': 0,
                    'has_position': symbol in open_positions,
                    'error': str(e)
                })

        return jsonify(watchlist)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def get_current_price(symbol):
    """获取币种当前价格"""
    symbol_map = {
        # 原有币种
        'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
        'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
        # 新增币种
        'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
        'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT'
    }
    binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

    url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
    response = requests.get(url, timeout=5)
    data = response.json()
    return float(data['price'])

# HTML模板
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交易助手仪表盘 v1.2 - Paper Trading</title>
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
        
        .loading::after {
            content: '...';
            animation: pulse 1.5s infinite;
        }
        
        /* 时间周期按钮组 */
        .timeframe-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .timeframe-btn {
            padding: 8px 16px;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
        }
        
        .timeframe-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }
        
        .timeframe-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .chart-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .info-item {
            font-size: 0.95em;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
            margin-right: 5px;
        }
        
        .info-value {
            font-weight: bold;
            color: #333;
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
            grid-template-columns: 280px 1fr 380px;
            gap: 20px;
            margin-bottom: 20px;
            height: calc(100vh - 400px);
            min-height: 600px;
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
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .center-panel h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
        }

        .center-panel-content {
            flex: 1;
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
            font-size: 1em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 4px;
        }

        .watch-card-vertical.has-position .watch-symbol {
            color: #10b981;
        }

        .watch-card-vertical .watch-price {
            font-size: 0.85em;
            color: #666;
        }

        .watch-card-vertical .watch-icon {
            font-size: 1.3em;
        }

        /* 滚动条样式 */
        .left-panel-content::-webkit-scrollbar,
        .center-panel-content::-webkit-scrollbar,
        .right-panel::-webkit-scrollbar {
            width: 6px;
        }

        .left-panel-content::-webkit-scrollbar-track,
        .center-panel-content::-webkit-scrollbar-track,
        .right-panel::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb,
        .center-panel-content::-webkit-scrollbar-thumb,
        .right-panel::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb:hover,
        .center-panel-content::-webkit-scrollbar-thumb:hover,
        .right-panel::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* 响应式：小屏幕时恢复垂直布局 */
        @media (max-width: 1200px) {
            .main-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                height: auto;
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
        
        <div class="stat-card">
            <div class="label">目标进度</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-bar" style="width: 0%">0%</div>
            </div>
            <div class="subtext" style="margin-top: 10px;">
                已赚: <span id="earned">0U</span> / 还需: <span id="remaining">3400U</span>
            </div>
        </div>

        <!-- 三栏布局 -->
        <div class="main-layout">
            <!-- 左侧：监控列表 -->
            <div class="left-panel">
                <h2>👁️ 监控列表</h2>
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
                    <div style="text-align: center; margin-bottom: 15px;">
                        <div style="margin-bottom: 12px;">
                            <label style="color: #666; font-size: 0.95em; margin-right: 10px;">选择持仓:</label>
                            <select id="position-selector" onchange="loadSelectedChart()" style="padding: 8px 16px; border: 2px solid #667eea; border-radius: 8px; font-size: 0.95em; cursor: pointer; background: white;">
                                <option value="">-- 请选择 --</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 8px; color: #666; font-size: 0.9em;">选择时间周期</div>
                        <div class="timeframe-selector">
                            <button class="timeframe-btn active" data-interval="5m" onclick="changeTimeframe('5m', this)">5m</button>
                            <button class="timeframe-btn" data-interval="10m" onclick="changeTimeframe('10m', this)">10m</button>
                            <button class="timeframe-btn" data-interval="30m" onclick="changeTimeframe('30m', this)">30m</button>
                            <button class="timeframe-btn" data-interval="1h" onclick="changeTimeframe('1h', this)">1h</button>
                            <button class="timeframe-btn" data-interval="4h" onclick="changeTimeframe('4h', this)">4h</button>
                            <button class="timeframe-btn" data-interval="1d" onclick="changeTimeframe('1d', this)">1d</button>
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

            <!-- 右侧：当前持仓 + 交易历史 -->
            <div class="right-panel">
                <div class="right-panel-section">
                    <h2>📦 当前持仓</h2>
                    <div id="positions-table">
                        <div class="loading">加载中</div>
                    </div>
                </div>

                <div class="right-panel-section">
                    <h2>📊 交易历史</h2>
                    <div id="trades-table">
                        <div class="loading">加载中</div>
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
        let selectedPositionIndex = -1;
        let currentTrades = [];
        
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

                const canvas = document.createElement('canvas');
                canvas.id = `trade-chart-${index}`;
                canvas.style.maxHeight = '400px';

                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvas);
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
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">暂无持仓</p>';
                    currentPositions = [];
                    document.getElementById('position-selector').innerHTML = '<option value="">-- 暂无持仓 --</option>';
                    return;
                }
                
                // 获取所有持仓的当前价格
                const pricePromises = positions.map(pos => 
                    fetch(`/api/price/${pos.symbol}`).then(r => r.json())
                );
                const prices = await Promise.all(pricePromises);
                
                let html = '<table><thead><tr>';
                html += '<th>币种</th><th>方向</th><th>金额</th><th>杠杆</th>';
                html += '<th>入场价</th><th>当前价</th><th>止盈/止损</th><th>盈亏</th><th>操作</th>';
                html += '</tr></thead><tbody>';
                
                positions.forEach((pos, i) => {
                    const currentPrice = prices[i].price || 0;
                    
                    let pricePct = 0;
                    if (pos.direction === 'LONG') {
                        pricePct = (currentPrice - pos.entry_price) / pos.entry_price;
                    } else {
                        pricePct = (pos.entry_price - currentPrice) / pos.entry_price;
                    }
                    
                    const roi = pricePct * pos.leverage * 100;
                    const pnl = pos.amount * pricePct * pos.leverage;
                    
                    html += '<tr>';
                    html += `<td><strong>${pos.symbol}</strong></td>`;
                    html += `<td><span class="badge ${pos.direction.toLowerCase()}">${pos.direction === 'LONG' ? '做多' : '做空'}</span></td>`;
                    html += `<td>${formatNumber(pos.amount, 0)}U</td>`;
                    html += `<td>${pos.leverage}x</td>`;
                    html += `<td>$${formatNumber(pos.entry_price, 6)}</td>`;
                    html += `<td style="color: #667eea; font-weight: bold;">$${formatNumber(currentPrice, 6)}</td>`;
                    html += `<td><span style="color: #10b981;">$${formatNumber(pos.take_profit, 6)}</span> / <span style="color: #ef4444;">$${formatNumber(pos.stop_loss, 6)}</span></td>`;
                    html += `<td style="color: ${pnl >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">`;
                    html += `${formatCurrency(pnl)}U (${formatCurrency(roi)}%)</td>`;
                    html += `<td><button class="view-chart-btn" onclick="viewChart('${pos.symbol}', ${i})">📊 查看图表</button></td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;
                
                // 保存到全局变量
                currentPositions = positions;
                
                // 填充持仓选择器
                const selector = document.getElementById('position-selector');
                selector.innerHTML = '<option value="">-- 请选择 --</option>';
                positions.forEach((pos, idx) => {
                    const direction = pos.direction === 'LONG' ? '做多' : '做空';
                    selector.innerHTML += `<option value="${idx}">${pos.symbol} ${direction} ${pos.leverage}x</option>`;
                });
                
                // 如果之前有选中的持仓，保持显示
                if (selectedPositionIndex >= 0 && selectedPositionIndex < positions.length) {
                    selector.value = selectedPositionIndex;
                    loadSingleChart(positions[selectedPositionIndex]);
                }
                
            } catch (error) {
                console.error('加载持仓失败:', error);
                document.getElementById('positions-table').innerHTML = '<p style="color: #ef4444;">加载失败</p>';
            }
        }
        
        async function loadTrades() {
            try {
                const response = await fetch('/api/trades');
                const trades = await response.json();
                
                const container = document.getElementById('trades-table');
                
                if (trades.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">暂无交易记录</p>';
                    return;
                }
                
                let html = '<table><thead><tr>';
                html += '<th>币种</th><th>方向</th><th>状态</th><th>金额</th>';
                html += '<th>入场/出场</th><th>盈亏</th><th>ROI</th><th>时间</th><th>操作</th>';
                html += '</tr></thead><tbody>';

                trades.forEach((trade, index) => {
                    const pnl = trade.pnl || 0;
                    const roi = trade.roi || 0;

                    html += '<tr>';
                    html += `<td><strong>${trade.symbol}</strong></td>`;
                    html += `<td><span class="badge ${trade.direction.toLowerCase()}">${trade.direction === 'LONG' ? '做多' : '做空'}</span></td>`;
                    html += `<td><span class="badge ${trade.status.toLowerCase()}">${trade.status === 'OPEN' ? '持仓中' : '已平仓'}</span></td>`;
                    html += `<td>${formatNumber(trade.amount, 0)}U × ${trade.leverage}x</td>`;
                    html += `<td>$${formatNumber(trade.entry_price, 6)}`;
                    if (trade.exit_price) {
                        html += ` → $${formatNumber(trade.exit_price, 6)}`;
                    }
                    html += '</td>';
                    html += `<td style="color: ${pnl >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">${formatCurrency(pnl)}U</td>`;
                    html += `<td style="color: ${roi >= 0 ? '#10b981' : '#ef4444'};">${formatCurrency(roi)}%</td>`;
                    html += `<td>${formatTime(trade.entry_time)}</td>`;
                    // 添加查看图表按钮
                    if (trade.status === 'CLOSED') {
                        html += `<td><button class="btn-chart" onclick="viewTradeChart(${index})">📊 复盘</button></td>`;
                    } else {
                        html += `<td><span style="color: #999;">-</span></td>`;
                    }
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                container.innerHTML = html;

                // 保存到全局变量
                currentTrades = trades;

            } catch (error) {
                console.error('加载交易历史失败:', error);
                document.getElementById('trades-table').innerHTML = '<p style="color: #ef4444;">加载失败</p>';
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
                    const icon = coin.has_position ? '📊' : '👁️';

                    html += `
                        <div class="watch-card-vertical ${hasPosition}">
                            <div class="watch-info">
                                <div class="watch-symbol">${coin.symbol}</div>
                                <div class="watch-price">$${formatNumber(coin.price, 4)}</div>
                            </div>
                            <div class="watch-icon">${icon}</div>
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
                info.className = 'chart-info-grid';
                info.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">📍 入场价:</span>
                        <span class="info-value" style="color: #3b82f6;">$${formatNumber(pos.entry_price, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 当前价:</span>
                        <span class="info-value" style="color: #8b5cf6; font-size: 1.1em;">$${formatNumber(currentPrice, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🎯 止盈:</span>
                        <span class="info-value" style="color: #10b981;">$${formatNumber(pos.take_profit, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🛑 止损:</span>
                        <span class="info-value" style="color: #ef4444;">$${formatNumber(pos.stop_loss, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💼 仓位:</span>
                        <span class="info-value">${formatNumber(pos.amount, 0)}U × ${pos.leverage}x</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💵 盈亏:</span>
                        <span class="info-value" style="color: ${pnl >= 0 ? '#10b981' : '#ef4444'}; font-size: 1.15em;">${formatCurrency(pnl)}U</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📊 ROI:</span>
                        <span class="info-value" style="color: ${roi >= 0 ? '#10b981' : '#ef4444'}; font-size: 1.15em;">${formatCurrency(roi)}%</span>
                    </div>
                `;
                
                const canvas = document.createElement('canvas');
                canvas.id = `chart-${pos.symbol}`;
                canvas.style.maxHeight = '400px';
                
                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvas);
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
        
        function updateAll() {
            loadStats();
            loadWatchlist();
            loadPositions();
            loadTrades();
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('zh-CN');
        }
        
        // 初始加载
        updateAll();
        
        // 每60秒刷新（但不会自动加载图表，除非用户已选择某个持仓）
        setInterval(updateAll, 60000);
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
