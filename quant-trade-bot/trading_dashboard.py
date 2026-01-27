#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
交易监控前端 - 显示持仓和关注币种
"""

from flask import Flask, render_template, jsonify
import sqlite3
import requests
import json
import os
from datetime import datetime

app = Flask(__name__)

class TradingDashboard:
    """交易监控面板"""
    
    def __init__(self):
        # 关注的货币列表
        self.watchlist = [
            {'symbol': 'XMR/USDT', 'entry_target': 464.65, 'status': 'MONITORING', 'leverage': '10x'},
            {'symbol': 'BTC/USDT', 'entry_target': 88000, 'status': 'HOLDING', 'leverage': '1x'},
            {'symbol': 'ETH/USDT', 'entry_target': 3200, 'status': 'WATCHING', 'leverage': '3x'},
            {'symbol': 'SOL/USDT', 'entry_target': 220, 'status': 'WATCHING', 'leverage': '5x'},
            {'symbol': 'ADA/USDT', 'entry_target': 0.35, 'status': 'READY_TO_BUY', 'leverage': '3x'},
            {'symbol': 'DOGE/USDT', 'entry_target': 0.32, 'status': 'WATCHING', 'leverage': '10x'}
        ]
        
    def get_current_positions(self):
        """获取当前持仓"""
        positions = []
        
        # 从主数据库获取持仓
        try:
            db_paths = [
                '/Users/hongtou/newproject/quant-trade-bot/data/db/trading_simulator.db',
                '/Users/hongtou/newproject/quant-trade-bot/data/db/paper_trading.db',
                '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db',
                '/Users/hongtou/newproject/trading_simulator.db',
                '/Users/hongtou/newproject/paper_trading.db'
            ]
            
            for db_path in db_paths:
                if os.path.exists(db_path):
                    conn = sqlite3.connect(db_path)
                    cursor = conn.cursor()
                    
                    # 尝试不同的表结构
                    try:
                        cursor.execute('''
                            SELECT symbol, direction, entry_price, amount, leverage, 
                                   open_time, status, stop_loss, take_profit
                            FROM positions WHERE status='open'
                        ''')
                        rows = cursor.fetchall()
                        
                        for row in rows:
                            symbol, direction, entry_price, amount, leverage, open_time, status, stop_loss, take_profit = row
                            
                            # 获取当前价格计算盈亏
                            current_price = self.get_current_price(symbol)
                            
                            if direction == 'long':
                                pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                            else:
                                pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
                            
                            positions.append({
                                'symbol': symbol,
                                'direction': direction.upper(),
                                'entry_price': entry_price,
                                'current_price': current_price,
                                'amount': amount,
                                'leverage': f"{leverage}x",
                                'pnl_percent': pnl_percent,
                                'stop_loss': stop_loss,
                                'take_profit': take_profit,
                                'open_time': open_time,
                                'pnl_color': 'success' if pnl_percent >= 0 else 'danger'
                            })
                    except Exception as e:
                        print(f"查询{db_path}失败: {e}")
                    finally:
                        conn.close()
        except Exception as e:
            print(f"数据库查询错误: {e}")
        
        # 如果没有找到持仓，添加XMR监控的持仓
        if not positions:
            xmr_position_file = 'my_xmr_position.json'
            if os.path.exists(xmr_position_file):
                try:
                    with open(xmr_position_file, 'r') as f:
                        xmr_data = json.load(f)
                    
                    current_price = self.get_current_price('XMR/USDT')
                    entry_price = xmr_data['entry_price']
                    leverage = xmr_data['leverage']
                    
                    pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                    
                    positions.append({
                        'symbol': 'XMR/USDT',
                        'direction': 'LONG',
                        'entry_price': entry_price,
                        'current_price': current_price,
                        'amount': xmr_data['quantity'],
                        'leverage': f"{leverage}x",
                        'pnl_percent': pnl_percent,
                        'stop_loss': xmr_data['stop_loss'],
                        'take_profit': xmr_data['take_profit'],
                        'open_time': xmr_data.get('entry_time', 'N/A'),
                        'pnl_color': 'success' if pnl_percent >= 0 else 'danger'
                    })
                except Exception as e:
                    print(f"XMR持仓文件读取错误: {e}")
        
        return positions
    
    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            # 转换为Binance格式
            binance_symbol = symbol.replace('/', '')
            url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
            response = requests.get(url, timeout=5)
            if response.status_code == 200:
                return float(response.json()['price'])
        except:
            pass
        return 0.0
    
    def get_watchlist_with_prices(self):
        """获取关注列表及实时价格"""
        watchlist_with_prices = []
        
        for coin in self.watchlist:
            symbol = coin['symbol']
            current_price = self.get_current_price(symbol)
            entry_target = coin['entry_target']
            
            # 计算距离目标价格的百分比
            if current_price > 0:
                distance_percent = ((current_price - entry_target) / entry_target) * 100
                
                # 根据状态确定颜色
                if coin['status'] == 'HOLDING':
                    status_color = 'success'
                elif coin['status'] == 'READY_TO_BUY':
                    status_color = 'warning'
                elif coin['status'] == 'MONITORING':
                    status_color = 'info'
                else:
                    status_color = 'secondary'
                
                watchlist_with_prices.append({
                    'symbol': symbol,
                    'current_price': current_price,
                    'entry_target': entry_target,
                    'distance_percent': distance_percent,
                    'status': coin['status'],
                    'status_color': status_color,
                    'leverage': coin['leverage']
                })
        
        return watchlist_with_prices

dashboard = TradingDashboard()

@app.route('/')
def index():
    """主页面"""
    return render_template('trading_dashboard.html')

@app.route('/api/positions')
def api_positions():
    """API: 获取当前持仓"""
    positions = dashboard.get_current_positions()
    return jsonify({
        'positions': positions,
        'total_positions': len(positions),
        'update_time': datetime.now().strftime('%H:%M:%S')
    })

@app.route('/api/watchlist')
def api_watchlist():
    """API: 获取关注列表"""
    watchlist = dashboard.get_watchlist_with_prices()
    return jsonify({
        'watchlist': watchlist,
        'total_watching': len(watchlist),
        'update_time': datetime.now().strftime('%H:%M:%S')
    })
# 为兼容老页面添加缺失的API端点
@app.route('/api/account_summary')
def api_account_summary():
    """账户概要"""
    positions = dashboard.get_current_positions()
    total_pnl = sum(pos.get('pnl_percent', 0) for pos in positions)
    return jsonify({
        'balance': 1000.00,
        'pnl': total_pnl,
        'pnl_percent': total_pnl / 10 if total_pnl else 0,  # 估算值
        'positions_count': len(positions)
    })

@app.route('/api/recent_trades') 
def api_recent_trades():
    """最近交易"""
    trades = []
    try:
        db_path = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'
        if os.path.exists(db_path):
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            cursor.execute('''
                SELECT timestamp, symbol, side, price, amount, pnl, reason 
                FROM quick_trades ORDER BY timestamp DESC LIMIT 20
            ''')
            rows = cursor.fetchall()
            
            for row in rows:
                timestamp, symbol, side, price, amount, pnl, reason = row
                trades.append({
                    'timestamp': timestamp,
                    'symbol': symbol,
                    'side': side.upper(),
                    'type': 'MARKET',
                    'amount': amount,
                    'price': price,
                    'pnl': pnl or 0,
                    'status': '已成交'
                })
            conn.close()
    except Exception as e:
        print(f"查询交易记录失败: {e}")
    
    return jsonify({'trades': trades})

@app.route('/api/current_positions')
def api_current_positions():
    """当前持仓(兼容老API)"""
    positions = dashboard.get_current_positions()
    return jsonify({'positions': positions})

@app.route('/api/strategy_signals')
def api_strategy_signals():
    """策略信号"""
    signals = [
        {
            'time': datetime.now().strftime('%H:%M:%S'),
            'symbol': 'BTC/USDT',
            'signal': '买入',
            'confidence': 85
        },
        {
            'time': datetime.now().strftime('%H:%M:%S'), 
            'symbol': 'ETH/USDT',
            'signal': '观望',
            'confidence': 65
        }
    ]
    return jsonify({'signals': signals})

@app.route('/api/balance_history')
def api_balance_history():
    """余额历史"""
    history = []
    for i in range(24):
        history.append({
            'time': f'{i:02d}:00',
            'balance': 1000 + (i * 5),  # 模拟数据
            'pnl': i * 2
        })
    return jsonify({'history': history})

@app.route('/api/current_prices')
def api_current_prices():
    """当前价格"""
    watchlist = dashboard.get_watchlist_with_prices()
    prices = {}
    for item in watchlist:
        prices[item['symbol']] = {
            'price': item['current_price'],
            'change': item['distance_percent']
        }
    return jsonify({'prices': prices})
if __name__ == '__main__':
    # 创建模板目录
    os.makedirs('templates', exist_ok=True)
    app.run(host='0.0.0.0', port=5020, debug=True)