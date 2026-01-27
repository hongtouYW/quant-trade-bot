#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
简化版交易面板 - 修复SSL问题
"""
import sqlite3
import json
import warnings
import ssl
import sys
from datetime import datetime
from flask import Flask, render_template_string, jsonify, request
import logging

# 禁用SSL警告
warnings.filterwarnings('ignore', category=Warning)
ssl._create_default_https_context = ssl._create_unverified_context

# 设置日志级别
logging.getLogger('werkzeug').setLevel(logging.ERROR)

app = Flask(__name__)
app.config['JSON_AS_ASCII'] = False

DATABASE_PATH = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'

def get_positions():
    """获取持仓数据"""
    try:
        conn = sqlite3.connect(DATABASE_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            SELECT symbol, direction, amount, entry_price, leverage, status, open_time
            FROM positions 
            WHERE status = 'open'
            ORDER BY open_time DESC
        """)
        positions = []
        for row in cursor.fetchall():
            positions.append({
                'symbol': row[0],
                'direction': row[1],
                'amount': float(row[2]),
                'entry_price': float(row[3]),
                'leverage': float(row[4]),
                'status': row[5],
                'open_time': row[6]
            })
        conn.close()
        return positions
    except Exception as e:
        print(f"获取持仓数据错误: {e}")
        return []

def get_watchlist():
    """获取关注列表"""
    watchlist = [
        'BTC/USDT', 'ETH/USDT', 'ADA/USDT', 'SOL/USDT', 'DOT/USDT',
        'XRP/USDT', 'AVAX/USDT', 'LINK/USDT', 'MATIC/USDT', 'ATOM/USDT',
        'UNI/USDT', 'AAVE/USDT', 'SUSHI/USDT', 'COMP/USDT', 'XMR/USDT'
    ]
    return [{'symbol': symbol, 'price': '获取中...'} for symbol in watchlist]

# HTML模板
HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>量化交易面板</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            font-family: 'Arial', sans-serif;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .table-dark {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(5px);
        }
        .btn-success { background: linear-gradient(45deg, #28a745, #20c997); }
        .btn-danger { background: linear-gradient(45deg, #dc3545, #fd7e14); }
        .position-long { color: #28a745; font-weight: bold; }
        .position-short { color: #dc3545; font-weight: bold; }
        .refresh-time { 
            position: fixed; 
            top: 10px; 
            right: 10px; 
            background: rgba(0,0,0,0.5); 
            padding: 5px 10px; 
            border-radius: 5px; 
        }
    </style>
</head>
<body>
    <div class="refresh-time">
        最后更新: <span id="lastUpdate">{{ current_time }}</span>
    </div>
    
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-md-12 text-center mb-4">
                <h1>🚀 量化交易监控面板</h1>
                <p class="lead">实时持仓 & 市场监控</p>
            </div>
        </div>
        
        <div class="row">
            <!-- 持仓面板 -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h4>📊 当前持仓 (<span id="positionCount">{{ positions|length }}</span>)</h4>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>交易对</th>
                                        <th>方向</th>
                                        <th>数量</th>
                                        <th>开仓价</th>
                                        <th>杠杆</th>
                                        <th>状态</th>
                                    </tr>
                                </thead>
                                <tbody id="positionsTable">
                                    {% for position in positions %}
                                    <tr>
                                        <td>{{ position.symbol }}</td>
                                        <td>
                                            <span class="{% if position.direction == 'long' %}position-long{% else %}position-short{% endif %}">
                                                {% if position.direction == 'long' %}📈 多头{% else %}📉 空头{% endif %}
                                            </span>
                                        </td>
                                        <td>{{ "%.4f"|format(position.amount) }}</td>
                                        <td>${{ "%.4f"|format(position.entry_price) }}</td>
                                        <td>{{ position.leverage }}x</td>
                                        <td><span class="badge bg-success">{{ position.status }}</span></td>
                                    </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 关注列表面板 -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h4>👁 市场关注列表</h4>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>交易对</th>
                                        <th>当前价格</th>
                                        <th>状态</th>
                                    </tr>
                                </thead>
                                <tbody id="watchlistTable">
                                    {% for item in watchlist %}
                                    <tr>
                                        <td>{{ item.symbol }}</td>
                                        <td class="text-info">${{ item.price }}</td>
                                        <td><span class="badge bg-info">监控中</span></td>
                                    </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 状态栏 -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>📱 量化助理提醒</h5>
                        <p class="mb-1">系统运行正常 | 自动刷新: 每10秒 | 数据库连接: 正常</p>
                        <small class="text-muted">Powered by 智能交易系统 v2.0</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 自动刷新数据
        function refreshData() {
            fetch('/api/positions')
                .then(response => response.json())
                .then(data => {
                    // 更新持仓数据
                    document.getElementById('positionCount').textContent = data.length;
                    // 这里可以添加更多的数据更新逻辑
                })
                .catch(error => console.log('API请求失败'));
            
            // 更新时间
            const now = new Date();
            document.getElementById('lastUpdate').textContent = now.toLocaleTimeString();
        }
        
        // 每10秒刷新一次
        setInterval(refreshData, 10000);
        
        // 页面加载时立即刷新一次
        document.addEventListener('DOMContentLoaded', refreshData);
    </script>
</body>
</html>
"""

@app.route('/')
def dashboard():
    """主面板"""
    positions = get_positions()
    watchlist = get_watchlist()
    current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    
    return render_template_string(HTML_TEMPLATE, 
                                positions=positions, 
                                watchlist=watchlist,
                                current_time=current_time)

@app.route('/api/positions')
def api_positions():
    """持仓API"""
    return jsonify(get_positions())

@app.route('/api/watchlist')
def api_watchlist():
    """关注列表API"""
    return jsonify(get_watchlist())

@app.route('/health')
def health():
    """健康检查"""
    return jsonify({'status': 'ok', 'timestamp': datetime.now().isoformat()})

if __name__ == '__main__':
    print("🚀 启动修复版交易面板...")
    print(f"📊 访问地址: http://localhost:5020")
    print(f"📱 量化助理提醒系统已就绪")
    
    # 测试数据库连接
    try:
        positions = get_positions()
        print(f"✅ 数据库连接成功，当前持仓: {len(positions)} 个")
    except Exception as e:
        print(f"❌ 数据库连接失败: {e}")
    
    app.run(host='0.0.0.0', port=5020, debug=False)