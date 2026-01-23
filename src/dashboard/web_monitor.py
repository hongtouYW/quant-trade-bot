#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
实时监控Web面板
"""

from flask import Flask, render_template, jsonify, request
import sqlite3
import json
from datetime import datetime, date
import os
import ccxt

app = Flask(__name__)

# 使用绝对路径 - 数据库在项目根目录的data/db/
import os
SCRIPT_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))  # 项目根目录
DB_PATH = os.path.join(SCRIPT_DIR, 'data', 'db', 'paper_trading.db')

def get_db():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def get_current_price(symbol):
    """获取当前价格"""
    try:
        exchange = ccxt.binance({'enableRateLimit': True, 'timeout': 10000})
        ticker = exchange.fetch_ticker(symbol)
        return ticker['last']
    except:
        return None

@app.route('/')
def index():
    """主页"""
    return render_template('trading_monitor.html')

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
            ORDER BY entry_time DESC
        ''')
        
        positions = []
        for row in cursor.fetchall():
            current_price = get_current_price(row['symbol'])
            
            if current_price:
                position_value = row['quantity'] * current_price
                entry_value = row['quantity'] * row['entry_price']
                unrealized_pnl = (position_value - entry_value) * row['leverage']
                unrealized_pnl_pct = (unrealized_pnl / row['cost']) * 100
            else:
                unrealized_pnl = 0
                unrealized_pnl_pct = 0
                current_price = row['entry_price']
            
            positions.append({
                'symbol': row['symbol'],
                'quantity': row['quantity'],
                'entry_price': row['entry_price'],
                'current_price': current_price,
                'entry_time': row['entry_time'],
                'leverage': row['leverage'],
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'cost': row['cost'],
                'unrealized_pnl': unrealized_pnl,
                'unrealized_pnl_pct': unrealized_pnl_pct
            })
        
        conn.close()
        return jsonify(positions)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def get_trades():
    """获取交易记录"""
    try:
        limit = int(request.args.get('limit', 20))
        
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute(f'''
            SELECT * FROM trades 
            ORDER BY timestamp DESC 
            LIMIT {limit}
        ''')
        
        trades = []
        for row in cursor.fetchall():
            trades.append({
                'timestamp': row['timestamp'],
                'symbol': row['symbol'],
                'side': row['side'],
                'price': row['price'],
                'quantity': row['quantity'],
                'leverage': row['leverage'],
                'cost': row['cost'],
                'fee': row['fee'],
                'pnl': row['pnl'] if row['pnl'] else 0,
                'pnl_pct': row['pnl_pct'] if row['pnl_pct'] else 0,
                'reason': row['reason'] if row['reason'] else '',
                'balance_after': row['balance_after']
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

if __name__ == '__main__':
    print("🌐 启动Web监控面板...")
    print("📊 访问地址: http://localhost:5001")
    print("💡 按 Ctrl+C 停止服务器")
    app.run(debug=False, host='0.0.0.0', port=5001)
