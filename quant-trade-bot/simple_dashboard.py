#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
简化版交易面板 - 确保能正常运行
"""

from flask import Flask, jsonify, render_template_string
import sqlite3
import requests
import json
import os
from datetime import datetime

app = Flask(__name__)

# 简单的HTML模板
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>量化交易监控</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: white; }
        .card { background: rgba(255,255,255,0.1); border: none; }
        .profit { color: #00ff88; }
        .loss { color: #ff4757; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h1 class="text-center mb-4">🚀 量化交易监控面板</h1>
        
        <div class="row">
            <!-- 左侧：持仓 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>📊 当前持仓</h3>
                    </div>
                    <div class="card-body" id="positions">
                        <div class="text-center"><i>加载中...</i></div>
                    </div>
                </div>
            </div>
            
            <!-- 右侧：关注 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>👀 关注币种</h3>
                    </div>
                    <div class="card-body" id="watchlist">
                        <div class="text-center"><i>加载中...</i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function loadData() {
            try {
                // 加载持仓
                const posRes = await fetch('/api/positions');
                const posData = await posRes.json();
                
                let posHtml = `<h5>总持仓: ${posData.total_positions}</h5>`;
                
                if (posData.positions.length === 0) {
                    posHtml += '<p class="text-muted">暂无持仓</p>';
                } else {
                    posData.positions.forEach(pos => {
                        const pnlClass = pos.pnl_percent >= 0 ? 'profit' : 'loss';
                        posHtml += `
                            <div class="mb-3 p-3" style="background: rgba(255,255,255,0.05);">
                                <div class="d-flex justify-content-between">
                                    <strong>${pos.symbol}</strong>
                                    <span class="badge bg-${pos.direction === 'LONG' ? 'success' : 'danger'}">${pos.direction}</span>
                                </div>
                                <div class="mt-2">
                                    入场: $${pos.entry_price.toFixed(4)} | 
                                    现价: $${pos.current_price.toFixed(4)} | 
                                    <span class="${pnlClass}">${pos.pnl_percent >= 0 ? '+' : ''}${pos.pnl_percent.toFixed(2)}%</span>
                                </div>
                            </div>
                        `;
                    });
                }
                
                document.getElementById('positions').innerHTML = posHtml;
                
                // 加载关注列表
                const watchRes = await fetch('/api/watchlist');
                const watchData = await watchRes.json();
                
                let watchHtml = `<h5>关注币种: ${watchData.total_watching}</h5>`;
                
                watchData.watchlist.forEach(coin => {
                    const distClass = coin.distance_percent >= 0 ? 'profit' : 'loss';
                    watchHtml += `
                        <div class="mb-2 p-2" style="background: rgba(255,255,255,0.05);">
                            <div class="d-flex justify-content-between">
                                <strong>${coin.symbol}</strong>
                                <span class="badge bg-info">${coin.status}</span>
                            </div>
                            <div class="mt-1">
                                现价: $${coin.current_price.toFixed(4)} | 
                                目标: $${coin.entry_target.toFixed(4)} | 
                                <span class="${distClass}">${coin.distance_percent >= 0 ? '+' : ''}${coin.distance_percent.toFixed(2)}%</span>
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('watchlist').innerHTML = watchHtml;
                
            } catch (error) {
                console.error('加载失败:', error);
            }
        }
        
        // 初始加载和定时刷新
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            setInterval(loadData, 10000); // 10秒刷新
        });
    </script>
</body>
</html>
'''

def get_current_price(symbol):
    """获取当前价格"""
    try:
        binance_symbol = symbol.replace('/', '')
        url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            return float(response.json()['price'])
    except:
        pass
    return 100.0  # 默认价格

def get_positions():
    """获取持仓数据"""
    positions = []
    
    # 查询数据库
    db_path = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'
    if os.path.exists(db_path):
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            cursor.execute('SELECT symbol, direction, entry_price, amount, leverage FROM positions WHERE status="open"')
            rows = cursor.fetchall()
            
            for row in rows:
                symbol, direction, entry_price, amount, leverage = row
                current_price = get_current_price(symbol)
                
                if direction.lower() == 'long':
                    pnl_percent = ((current_price - entry_price) / entry_price) * 100 * leverage
                else:
                    pnl_percent = ((entry_price - current_price) / entry_price) * 100 * leverage
                
                positions.append({
                    'symbol': symbol,
                    'direction': direction.upper(),
                    'entry_price': entry_price,
                    'current_price': current_price,
                    'amount': amount,
                    'leverage': leverage,
                    'pnl_percent': pnl_percent
                })
            
            conn.close()
        except Exception as e:
            print(f"数据库查询错误: {e}")
    
    return positions

@app.route('/')
def index():
    """主页"""
    return render_template_string(HTML_TEMPLATE)

@app.route('/api/positions')
def api_positions():
    """持仓API"""
    positions = get_positions()
    return jsonify({
        'positions': positions,
        'total_positions': len(positions),
        'update_time': datetime.now().strftime('%H:%M:%S')
    })

@app.route('/api/watchlist')
def api_watchlist():
    """关注列表API"""
    watchlist = [
        {'symbol': 'BTC/USDT', 'entry_target': 88000, 'status': 'MONITORING'},
        {'symbol': 'ETH/USDT', 'entry_target': 3200, 'status': 'WATCHING'},
        {'symbol': 'SOL/USDT', 'entry_target': 120, 'status': 'READY'},
        {'symbol': 'DOT/USDT', 'entry_target': 1.85, 'status': 'MONITORING'},
    ]
    
    # 添加实时价格
    for coin in watchlist:
        current_price = get_current_price(coin['symbol'])
        coin['current_price'] = current_price
        coin['distance_percent'] = ((current_price - coin['entry_target']) / coin['entry_target']) * 100
    
    return jsonify({
        'watchlist': watchlist,
        'total_watching': len(watchlist),
        'update_time': datetime.now().strftime('%H:%M:%S')
    })

if __name__ == '__main__':
    print("🚀 启动简化版交易面板...")
    print("📊 访问地址: http://localhost:5020")
    app.run(host='0.0.0.0', port=5020, debug=False)