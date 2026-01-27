#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ADA模拟交易开仓 - 做空信号执行"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import json
import sqlite3
from datetime import datetime
import requests

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

def send_telegram(message):
    """发送Telegram通知"""
    try:
        bot_token = config['telegram']['bot_token']
        chat_id = config['telegram']['chat_id']
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        requests.post(url, data={'chat_id': chat_id, 'text': message}, timeout=5)
    except:
        pass

def create_ada_position():
    """创建ADA做空模拟持仓"""
    
    # ADA交易参数
    symbol = "ADA/USDT"
    side = "sell"  # 做空
    entry_price = 0.3478
    leverage = 3
    position_value = 100  # $100持仓价值
    quantity = position_value / entry_price  # 计算数量
    
    # 止损止盈（方案B参数）
    stop_loss = entry_price * 1.02  # 做空止损+2%
    take_profit = entry_price * 0.96  # 做空止盈-4%
    
    print("=" * 60)
    print("📉 ADA/USDT 模拟做空交易")
    print("=" * 60)
    print(f"\n交易参数:")
    print(f"  交易对: {symbol}")
    print(f"  方向: 做空 (SELL)")
    print(f"  开仓价: ${entry_price:.4f}")
    print(f"  数量: {quantity:.2f} ADA")
    print(f"  杠杆: {leverage}x")
    print(f"  持仓价值: ${position_value:.2f}")
    print(f"  止损价: ${stop_loss:.4f} (+2%)")
    print(f"  止盈价: ${take_profit:.4f} (-4%)")
    
    # 连接数据库
    db_path = 'data/db/paper_trading.db'
    
    # 确保数据库目录存在
    os.makedirs(os.path.dirname(db_path), exist_ok=True)
    
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    # 创建positions表（如果不存在）
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS positions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            symbol TEXT NOT NULL,
            quantity REAL NOT NULL,
            entry_price REAL NOT NULL,
            entry_time TEXT NOT NULL,
            leverage INTEGER DEFAULT 1,
            stop_loss REAL,
            take_profit REAL,
            cost REAL,
            status TEXT DEFAULT 'open'
        )
    ''')
    
    # 插入持仓
    entry_time = datetime.now().isoformat()
    cursor.execute('''
        INSERT INTO positions (symbol, quantity, entry_price, entry_time, leverage, stop_loss, take_profit, cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
    ''', (symbol, -quantity, entry_price, entry_time, leverage, stop_loss, take_profit, position_value))
    
    position_id = cursor.lastrowid
    
    # 创建trades表（如果不存在）
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS trades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            symbol TEXT NOT NULL,
            side TEXT NOT NULL,
            price REAL NOT NULL,
            quantity REAL NOT NULL,
            leverage INTEGER DEFAULT 1,
            cost REAL,
            fee REAL DEFAULT 0,
            pnl REAL DEFAULT 0,
            pnl_pct REAL DEFAULT 0,
            reason TEXT,
            balance_after REAL
        )
    ''')
    
    # 记录开仓交易
    cursor.execute('''
        INSERT INTO trades (timestamp, symbol, side, price, quantity, leverage, cost, fee, pnl, pnl_pct, reason, balance_after)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ''', (entry_time, symbol, side, entry_price, quantity, leverage, position_value, 0.0, 0.0, 0.0, "市场扫描信号-做空", 1000.0))
    
    conn.commit()
    conn.close()
    
    print(f"\n✅ 持仓已创建 (ID: {position_id})")
    print(f"⏰ 开仓时间: {entry_time}")
    
    # Telegram通知
    telegram_msg = f"""
📉 ADA/USDT 做空开仓

开仓价: ${entry_price:.4f}
数量: {quantity:.2f} ADA
杠杆: {leverage}x
止损: ${stop_loss:.4f} (+2%)
止盈: ${take_profit:.4f} (-4%)

时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
策略: 方案B - 市场扫描信号
"""
    
    print("\n发送Telegram通知...")
    send_telegram(telegram_msg)
    
    print("\n" + "=" * 60)
    print("💡 提示:")
    print("  - 这是模拟交易，不会在真实交易所执行")
    print("  - 持仓已保存到 paper_trading.db")
    print("  - 止损监控会自动检查并执行平仓")
    print("  - 访问 http://localhost:5001 查看持仓")
    print("  - 最长持仓24小时后自动平仓")
    print("=" * 60)
    
    return position_id

if __name__ == "__main__":
    try:
        position_id = create_ada_position()
        print(f"\n✅ ADA做空持仓创建成功！")
        
    except Exception as e:
        print(f"\n❌ 创建持仓失败: {e}")
        import traceback
        traceback.print_exc()
