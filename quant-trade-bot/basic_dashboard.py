#!/usr/bin/env python3
import sqlite3
import json
from flask import Flask, jsonify

app = Flask(__name__)

@app.route('/')
def home():
    return "<h1>交易面板</h1><p>API: <a href='/api/positions'>/api/positions</a></p>"

@app.route('/api/positions')
def positions():
    try:
        conn = sqlite3.connect('/Users/hongtou/newproject/quant-trade-bot/data/db/quick_trading.db')
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM positions LIMIT 5")
        data = cursor.fetchall()
        conn.close()
        return jsonify({'count': len(data), 'data': str(data)})
    except Exception as e:
        return jsonify({'error': str(e)})

if __name__ == '__main__':
    print("启动基础交易面板...")
    app.run(host='127.0.0.1', port=5020, debug=True)