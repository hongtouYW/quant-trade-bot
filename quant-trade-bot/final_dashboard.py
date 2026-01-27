#!/usr/bin/env python3
import sqlite3
import json
from flask import Flask, jsonify

app = Flask(__name__)

@app.route('/')
def home():
    return """
    <h1>🚀 交易面板运行正常</h1>
    <p>API测试:</p>
    <ul>
        <li><a href='/api/positions'>持仓数据 /api/positions</a></li>
        <li><a href='/test'>数据库测试 /test</a></li>
    </ul>
    """

@app.route('/test')
def test():
    try:
        conn = sqlite3.connect('/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db')
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM positions")
        count = cursor.fetchone()[0]
        conn.close()
        return f"数据库连接成功！持仓记录数: {count}"
    except Exception as e:
        return f"数据库错误: {e}"

@app.route('/api/positions')
def positions():
    try:
        conn = sqlite3.connect('/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db')
        cursor = conn.cursor()
        cursor.execute("""
            SELECT symbol, direction, amount, entry_price, leverage, status, open_time
            FROM positions 
            WHERE status = 'open'
            ORDER BY open_time DESC
        """)
        data = []
        for row in cursor.fetchall():
            data.append({
                'symbol': row[0],
                'direction': row[1],
                'amount': float(row[2]),
                'entry_price': float(row[3]),
                'leverage': float(row[4]),
                'status': row[5],
                'open_time': row[6]
            })
        conn.close()
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)})

if __name__ == '__main__':
    print("🚀 启动交易面板 - 端口5021")
    print("📊 访问: http://localhost:5021")
    app.run(host='127.0.0.1', port=5021, debug=False)