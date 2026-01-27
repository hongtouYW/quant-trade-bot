#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
最终验证版交易面板
"""
import sqlite3
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

from flask import Flask, jsonify

app = Flask(__name__)

@app.route('/')
def home():
    # 获取实际数据
    try:
        conn = sqlite3.connect('/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db')
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM positions WHERE status = 'open'")
        position_count = cursor.fetchone()[0]
        
        cursor.execute("""
            SELECT symbol, direction, amount, entry_price 
            FROM positions 
            WHERE status = 'open'
            ORDER BY open_time DESC
            LIMIT 5
        """)
        positions = cursor.fetchall()
        conn.close()
        
        html = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <title>量化交易面板</title>
            <meta charset="utf-8">
            <style>
                body {{ background: #1a1a2e; color: white; font-family: Arial; padding: 20px; }}
                .container {{ max-width: 800px; margin: 0 auto; }}
                .card {{ background: rgba(255,255,255,0.1); padding: 20px; margin: 10px 0; border-radius: 10px; }}
                table {{ width: 100%; border-collapse: collapse; }}
                th, td {{ padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }}
                .long {{ color: #28a745; }}
                .short {{ color: #dc3545; }}
                .status {{ text-align: center; color: #28a745; font-weight: bold; }}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🚀 量化交易监控面板</h1>
                <div class="status">✅ 系统正常运行 | 数据库连接正常</div>
                
                <div class="card">
                    <h2>📊 当前持仓 ({position_count} 个)</h2>
                    <table>
                        <tr><th>交易对</th><th>方向</th><th>数量</th><th>开仓价</th></tr>
        """
        
        for pos in positions:
            direction_color = "long" if pos[1] == "long" else "short"
            direction_text = "📈 多头" if pos[1] == "long" else "📉 空头"
            html += f"""
                        <tr>
                            <td>{pos[0]}</td>
                            <td class="{direction_color}">{direction_text}</td>
                            <td>{pos[2]:.4f}</td>
                            <td>${pos[3]:.4f}</td>
                        </tr>
            """
        
        html += f"""
                    </table>
                </div>
                
                <div class="card">
                    <h2>📱 量化助理提醒</h2>
                    <p>最后更新: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}</p>
                    <p>API地址: <a href="/api/positions" style="color:#28a745">/api/positions</a></p>
                </div>
            </div>
        </body>
        </html>
        """
        return html
        
    except Exception as e:
        return f"<h1>错误: {e}</h1>"

@app.route('/api/positions')
def api_positions():
    try:
        conn = sqlite3.connect('/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db')
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
        return jsonify({'count': len(positions), 'data': positions})
    except Exception as e:
        return jsonify({'error': str(e)})

if __name__ == '__main__':
    print("🚀 最终验证版交易面板启动")
    print("📊 访问: http://localhost:5022")
    app.run(host='127.0.0.1', port=5022, debug=False)