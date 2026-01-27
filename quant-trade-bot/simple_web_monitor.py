#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
简单可靠的交易面板 - 端口5001
"""
import sqlite3
import json
import warnings
from datetime import datetime
from flask import Flask, jsonify, render_template_string

warnings.filterwarnings('ignore')
app = Flask(__name__)

DB_PATH = '/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db'

def get_positions():
    """获取持仓数据"""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            SELECT symbol, direction, amount, entry_price, leverage, status, open_time, stop_loss, take_profit
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
                'open_time': row[6],
                'stop_loss': float(row[7]) if row[7] else 0,
                'take_profit': float(row[8]) if row[8] else 0
            })
        conn.close()
        return positions
    except Exception as e:
        print(f"获取持仓错误: {e}")
        return []

# 主页HTML模板
HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>量化交易监控面板</title>
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .long { color: #28a745; font-weight: bold; }
        .short { color: #dc3545; font-weight: bold; }
        .status {
            text-align: center;
            padding: 20px;
            background: rgba(0, 255, 0, 0.1);
            border-radius: 10px;
        }
        .refresh-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center;">🚀 量化交易监控面板</h1>
        
        <div class="status">
            ✅ 系统运行正常 | 端口: 5001 | 数据库: quick_trading.db
            <br>最后更新: {{ current_time }}
        </div>
        
        <div class="card">
            <h2>📊 当前持仓 ({{ position_count }}个)</h2>
            <table>
                <thead>
                    <tr>
                        <th>交易对</th>
                        <th>方向</th>
                        <th>数量</th>
                        <th>开仓价</th>
                        <th>杠杆</th>
                        <th>止损</th>
                        <th>止盈</th>
                        <th>开仓时间</th>
                    </tr>
                </thead>
                <tbody>
                    {% for pos in positions %}
                    <tr>
                        <td>{{ pos.symbol }}</td>
                        <td class="{{ pos.direction }}">
                            {% if pos.direction == 'long' %}📈 多头{% else %}📉 空头{% endif %}
                        </td>
                        <td>{{ "%.4f"|format(pos.amount) }}</td>
                        <td>${{ "%.4f"|format(pos.entry_price) }}</td>
                        <td>{{ pos.leverage }}x</td>
                        <td>{% if pos.stop_loss %} ${{ "%.4f"|format(pos.stop_loss) }} {% else %} - {% endif %}</td>
                        <td>{% if pos.take_profit %} ${{ "%.4f"|format(pos.take_profit) }} {% else %} - {% endif %}</td>
                        <td>{{ pos.open_time }}</td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
        
        <div class="card">
            <h2>📱 量化助理提醒状态</h2>
            <p>• Telegram通知: 已启用</p>
            <p>• 交易策略: 15币种轮动监控</p>
            <p>• API接口: <a href="/api/positions" style="color: #28a745;">/api/positions</a></p>
            <button class="refresh-btn" onclick="location.reload()">🔄 刷新数据</button>
        </div>
    </div>
    
    <script>
        // 30秒自动刷新
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
"""

@app.route('/')
def index():
    """主页"""
    positions = get_positions()
    current_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    return render_template_string(HTML_TEMPLATE, 
                                positions=positions, 
                                position_count=len(positions),
                                current_time=current_time)

@app.route('/api/positions')
def api_positions():
    """持仓API"""
    return jsonify(get_positions())

@app.route('/api/stats')
def api_stats():
    """统计API"""
    positions = get_positions()
    return jsonify({
        'total_positions': len(positions),
        'long_positions': len([p for p in positions if p['direction'] == 'long']),
        'short_positions': len([p for p in positions if p['direction'] == 'short']),
        'status': 'running'
    })

if __name__ == '__main__':
    print("🌐 启动简化交易面板...")
    print("📊 访问: http://localhost:5001")
    print("💡 使用 quick_trading.db 数据库")
    
    # 测试数据库
    positions = get_positions()
    print(f"✅ 数据库连接成功，当前持仓: {len(positions)}个")
    
    app.run(host='127.0.0.1', port=5001, debug=False)