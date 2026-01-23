# -*- coding: utf-8 -*-
"""
量化交易模拟器 - Web Dashboard
"""

from flask import Flask, render_template, jsonify, request
import sqlite3
import pandas as pd
import json
from datetime import datetime, timedelta
import threading
import logging

from realtime_trader import RealTimeTrader

app = Flask(__name__)
logger = logging.getLogger(__name__)

# 全局交易器实例
trader = None
trader_lock = threading.Lock()

def get_trader():
    """获取交易器实例"""
    global trader
    with trader_lock:
        if trader is None:
            trader = RealTimeTrader(initial_balance=1000.0)
            # 启动后台监控
            trader.start_monitoring()
            logger.info("🚀 交易模拟器已启动")
        return trader

@app.route('/')
def dashboard():
    """主页面"""
    return render_template('trading_dashboard.html')

@app.route('/api/account_summary')
def api_account_summary():
    """获取账户摘要API"""
    try:
        trader = get_trader()
        summary = trader.get_trading_summary()
        
        return jsonify({
            'success': True,
            'data': summary
        })
    except Exception as e:
        logger.error(f"❌ 获取账户摘要失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/recent_trades')
def api_recent_trades():
    """获取最近交易记录"""
    try:
        trader = get_trader()
        
        conn = sqlite3.connect(trader.simulator.db_path)
        query = '''
        SELECT 
            timestamp, symbol, side, direction, type, 
            amount, price, leverage, fee_amount, 
            pnl_percent, pnl_amount, status
        FROM trades 
        ORDER BY timestamp DESC 
        LIMIT 50
        '''
        
        df = pd.read_sql_query(query, conn)
        conn.close()
        
        # 转换为字典列表
        trades = df.to_dict('records')
        
        # 格式化时间
        for trade in trades:
            if trade['timestamp']:
                trade['timestamp'] = datetime.fromisoformat(trade['timestamp']).strftime('%m-%d %H:%M')
            
            # 添加颜色标识
            if trade['pnl_amount'] is not None:
                trade['pnl_color'] = 'success' if trade['pnl_amount'] >= 0 else 'danger'
            else:
                trade['pnl_color'] = 'secondary'
        
        return jsonify({
            'success': True,
            'data': trades
        })
        
    except Exception as e:
        logger.error(f"❌ 获取交易记录失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/current_positions')
def api_current_positions():
    """获取当前持仓"""
    try:
        trader = get_trader()
        
        conn = sqlite3.connect(trader.simulator.db_path)
        query = '''
        SELECT 
            id, symbol, direction, type, amount, 
            entry_price, current_price, leverage, 
            unrealized_pnl, unrealized_pnl_percent, 
            open_time, status
        FROM positions 
        WHERE status = 'open'
        ORDER BY open_time DESC
        '''
        
        df = pd.read_sql_query(query, conn)
        conn.close()
        
        positions = df.to_dict('records')
        
        # 更新当前价格和盈亏
        for pos in positions:
            symbol = pos['symbol']
            current_price = trader.simulator.get_current_price(symbol)
            pos['current_price'] = current_price
            
            # 重新计算盈亏
            if current_price > 0:
                entry_price = pos['entry_price']
                leverage = pos['leverage']
                direction = pos['direction']
                
                if direction == 'long':
                    pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                else:
                    pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
                
                pos['unrealized_pnl_percent'] = pnl_percent
                pos['unrealized_pnl'] = (pnl_percent / 100) * (pos['amount'] * entry_price)
                pos['pnl_color'] = 'success' if pnl_percent >= 0 else 'danger'
            
            # 格式化时间
            if pos['open_time']:
                pos['open_time'] = datetime.fromisoformat(pos['open_time']).strftime('%m-%d %H:%M')
        
        return jsonify({
            'success': True,
            'data': positions
        })
        
    except Exception as e:
        logger.error(f"❌ 获取持仓失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/strategy_signals')
def api_strategy_signals():
    """获取策略信号"""
    try:
        trader = get_trader()
        
        conn = sqlite3.connect(trader.simulator.db_path)
        query = '''
        SELECT 
            timestamp, symbol, signal, confidence, 
            reason, price, executed
        FROM strategy_signals 
        ORDER BY timestamp DESC 
        LIMIT 30
        '''
        
        df = pd.read_sql_query(query, conn)
        conn.close()
        
        signals = df.to_dict('records')
        
        # 格式化数据
        for signal in signals:
            if signal['timestamp']:
                signal['timestamp'] = datetime.fromisoformat(signal['timestamp']).strftime('%m-%d %H:%M')
            
            # 添加信号颜色
            signal_colors = {
                'buy': 'success',
                'sell': 'danger', 
                'hold': 'secondary'
            }
            signal['signal_color'] = signal_colors.get(signal['signal'], 'secondary')
            
            # 格式化置信度
            if signal['confidence']:
                signal['confidence_percent'] = f"{signal['confidence']*100:.1f}%"
            else:
                signal['confidence_percent'] = "0%"
        
        return jsonify({
            'success': True,
            'data': signals
        })
        
    except Exception as e:
        logger.error(f"❌ 获取策略信号失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/balance_history')
def api_balance_history():
    """获取余额历史"""
    try:
        trader = get_trader()
        
        conn = sqlite3.connect(trader.simulator.db_path)
        query = '''
        SELECT 
            timestamp, balance, total_pnl, 
            total_pnl_percent, open_positions
        FROM balance_history 
        ORDER BY timestamp ASC
        LIMIT 100
        '''
        
        df = pd.read_sql_query(query, conn)
        conn.close()
        
        if len(df) == 0:
            # 如果没有历史数据，返回当前状态
            summary = trader.simulator.get_account_summary()
            history = [{
                'timestamp': datetime.now().isoformat(),
                'balance': summary['current_balance'],
                'total_pnl_percent': summary['total_pnl_percent'],
                'open_positions': summary['open_positions']
            }]
        else:
            history = df.to_dict('records')
            
            # 格式化时间戳
            for item in history:
                if item['timestamp']:
                    item['timestamp'] = datetime.fromisoformat(item['timestamp']).strftime('%m-%d %H:%M')
        
        return jsonify({
            'success': True,
            'data': history
        })
        
    except Exception as e:
        logger.error(f"❌ 获取余额历史失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/current_prices')
def api_current_prices():
    """获取当前价格"""
    try:
        trader = get_trader()
        prices = {}
        
        for symbol in trader.trading_pairs:
            price = trader.simulator.get_current_price(symbol)
            prices[symbol] = price
            
            # 添加信号信息
            if symbol in trader.last_signals:
                signal_data = trader.last_signals[symbol]['final_signal']
                prices[f"{symbol}_signal"] = signal_data['signal']
                prices[f"{symbol}_confidence"] = signal_data['confidence']
        
        return jsonify({
            'success': True,
            'data': prices
        })
        
    except Exception as e:
        logger.error(f"❌ 获取价格失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

@app.route('/api/close_position', methods=['POST'])
def api_close_position():
    """手动平仓"""
    try:
        data = request.json
        position_id = data.get('position_id')
        
        if not position_id:
            return jsonify({
                'success': False,
                'error': '缺少持仓ID'
            })
        
        trader = get_trader()
        success = trader.simulator.close_position(int(position_id), "manual")
        
        return jsonify({
            'success': success,
            'message': '平仓成功' if success else '平仓失败'
        })
        
    except Exception as e:
        logger.error(f"❌ 手动平仓失败: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        })

if __name__ == '__main__':
    # 设置日志
    logging.basicConfig(level=logging.INFO)
    
    print("🚀 启动量化交易模拟器 Dashboard")
    print("📊 访问地址: http://localhost:5010")
    
    app.run(host='0.0.0.0', port=5010, debug=False, threaded=True)