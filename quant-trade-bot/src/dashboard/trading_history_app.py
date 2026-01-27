from flask import Flask, render_template, jsonify
import json
import os
from datetime import datetime

app = Flask(__name__)

@app.route('/')
def index():
    """主页 - 策略分析概览"""
    return render_template('strategy_overview.html')

@app.route('/trades/<strategy>')
def trades_detail(strategy):
    """策略交易明细页面"""
    return render_template('trades_detail.html', strategy=strategy)

@app.route('/api/strategies')
def get_strategies():
    """获取所有策略的汇总数据"""
    try:
        with open('latest_analysis.json', 'r', encoding='utf-8') as f:
            strategies = json.load(f)
        return jsonify({'success': True, 'data': strategies})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无策略分析数据，请先运行回测'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/trades')
def get_all_trades():
    """获取所有策略的交易历史"""
    try:
        with open('latest_trades.json', 'r', encoding='utf-8') as f:
            trades = json.load(f)
        return jsonify({'success': True, 'data': trades})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无交易历史数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/trades/<strategy>')
def get_strategy_trades(strategy):
    """获取特定策略的交易历史"""
    try:
        with open('latest_trades.json', 'r', encoding='utf-8') as f:
            all_trades = json.load(f)
        
        strategy_trades = all_trades.get(strategy, [])
        return jsonify({'success': True, 'data': strategy_trades})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无交易历史数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/performance/<strategy>')
def get_strategy_performance(strategy):
    """获取策略绩效分析"""
    try:
        with open('latest_analysis.json', 'r', encoding='utf-8') as f:
            strategies = json.load(f)
        
        strategy_data = None
        for s in strategies:
            if s.get('strategy') == strategy:
                strategy_data = s
                break
        
        if not strategy_data:
            return jsonify({'success': False, 'error': '策略未找到'})
        
        return jsonify({'success': True, 'data': strategy_data})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    # 创建templates目录
    os.makedirs('templates', exist_ok=True)
    
    print("🚀 启动交易历史分析服务...")
    print("📊 访问 http://localhost:5002 查看策略分析")
    app.run(host='0.0.0.0', port=5002, debug=True)