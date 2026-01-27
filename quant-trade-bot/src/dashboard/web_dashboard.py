#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
实盘模拟交易系统 - Web监控面板
实时显示交易数据
"""

from flask import Flask, render_template, jsonify
import sqlite3
from datetime import datetime, date, timedelta
import os
import sys
import json

sys.path.append(os.path.dirname(os.path.abspath(__file__)))

app = Flask(__name__)

DB_PATH = 'paper_trading.db'

def get_db_connection():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

@app.route('/')
def index():
    """主页"""
    return render_template('dashboard.html')

@app.route('/api/account')
def api_account():
    """获取账户信息"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # 获取最新统计
        cursor.execute('''
            SELECT * FROM stats 
            ORDER BY timestamp DESC 
            LIMIT 1
        ''')
        stats = cursor.fetchone()
        
        # 获取最新余额
        cursor.execute('''
            SELECT balance_after FROM trades 
            ORDER BY timestamp DESC 
            LIMIT 1
        ''')
        balance_row = cursor.fetchone()
        
        conn.close()
        
        if stats:
            return jsonify({
                'balance': stats['balance'],
                'total_pnl': stats['total_pnl'],
                'total_trades': stats['total_trades'],
                'winning_trades': stats['winning_trades'],
                'losing_trades': stats['losing_trades'],
                'win_rate': stats['win_rate'],
                'total_fees': stats['total_fees'],
                'timestamp': stats['timestamp']
            })
        elif balance_row:
            return jsonify({
                'balance': balance_row['balance_after'],
                'total_pnl': 0,
                'total_trades': 0,
                'winning_trades': 0,
                'losing_trades': 0,
                'win_rate': 0,
                'total_fees': 0,
                'timestamp': datetime.now().isoformat()
            })
        else:
            return jsonify({
                'balance': 1000,
                'total_pnl': 0,
                'total_trades': 0,
                'winning_trades': 0,
                'losing_trades': 0,
                'win_rate': 0,
                'total_fees': 0,
                'timestamp': datetime.now().isoformat()
            })
            
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/positions')
def api_positions():
    """获取当前持仓"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT * FROM positions 
            WHERE status = 'open'
            ORDER BY entry_time DESC
        ''')
        
        positions = []
        for row in cursor.fetchall():
            positions.append({
                'symbol': row['symbol'],
                'quantity': row['quantity'],
                'entry_price': row['entry_price'],
                'entry_time': row['entry_time'],
                'leverage': row['leverage'],
                'stop_loss': row['stop_loss'],
                'take_profit': row['take_profit'],
                'cost': row['cost']
            })
        
        conn.close()
        return jsonify(positions)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def api_trades():
    """获取交易记录"""
    try:
        limit = int(request.args.get('limit', 20))
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute(f'''
            SELECT * FROM trades 
            ORDER BY timestamp DESC 
            LIMIT {limit}
        ''')
        
        trades = []
        for row in cursor.fetchall():
            trades.append({
                'id': row['id'],
                'timestamp': row['timestamp'],
                'symbol': row['symbol'],
                'side': row['side'],
                'price': row['price'],
                'quantity': row['quantity'],
                'leverage': row['leverage'],
                'cost': row['cost'],
                'fee': row['fee'],
                'pnl': row['pnl'],
                'pnl_pct': row['pnl_pct'],
                'reason': row['reason'],
                'balance_after': row['balance_after']
            })
        
        conn.close()
        return jsonify(trades)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_stats')
def api_daily_stats():
    """获取每日统计"""
    try:
        days = int(request.args.get('days', 7))
        
        conn = get_db_connection()
        cursor = conn.cursor()
        
        stats = []
        for i in range(days - 1, -1, -1):
            target_date = date.today() - timedelta(days=i)
            target_date_str = target_date.isoformat()
            
            cursor.execute('''
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN pnl IS NOT NULL THEN pnl ELSE 0 END) as pnl,
                    SUM(fee) as fees
                FROM trades 
                WHERE date(timestamp) = ?
            ''', (target_date_str,))
            
            result = cursor.fetchone()
            
            stats.append({
                'date': target_date_str,
                'total_trades': result['total'],
                'pnl': result['pnl'] if result['pnl'] else 0,
                'fees': result['fees'] if result['fees'] else 0
            })
        
        conn.close()
        return jsonify(stats)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/today')
def api_today():
    """获取今日统计"""
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        today_str = date.today().isoformat()
        
        cursor.execute('''
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN side='buy' THEN 1 ELSE 0 END) as buys,
                SUM(CASE WHEN side='sell' THEN 1 ELSE 0 END) as sells,
                SUM(CASE WHEN pnl IS NOT NULL THEN pnl ELSE 0 END) as pnl,
                SUM(fee) as fees
            FROM trades 
            WHERE date(timestamp) = ?
        ''', (today_str,))
        
        result = cursor.fetchone()
        
        conn.close()
        
        return jsonify({
            'date': today_str,
            'total_trades': result['total'],
            'buy_count': result['buys'],
            'sell_count': result['sells'],
            'pnl': result['pnl'] if result['pnl'] else 0,
            'fees': result['fees'] if result['fees'] else 0
        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    from flask import request
    print("🌐 启动Web监控面板...")
    print("📊 访问: http://localhost:5000")
    print("💡 按 Ctrl+C 停止服务")
    app.run(host='0.0.0.0', port=5000, debug=True)
