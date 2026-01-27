#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
持仓监控和自动止损/止盈服务
独立运行，监控数据库中的持仓并自动执行止损止盈
"""

import sqlite3
import ccxt
import json
import time
from datetime import datetime
import os
import sys

# 项目根目录
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(SCRIPT_DIR, 'data', 'db', 'paper_trading.db')
CONFIG_PATH = os.path.join(SCRIPT_DIR, 'config', 'config.json')

class PositionMonitor:
    def __init__(self):
        # 加载配置
        with open(CONFIG_PATH, 'r') as f:
            config = json.load(f)
        
        # 初始化交易所（模拟模式）
        self.exchange = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })
        
        # Telegram配置
        self.telegram_token = config['telegram']['bot_token']
        self.telegram_chat_id = config['telegram']['chat_id']
        
        print("✅ 持仓监控服务已启动")
        print(f"📊 数据库: {DB_PATH}")
        print(f"⏰ 检查间隔: 30秒")
        print("=" * 60)
    
    def get_positions_from_db(self):
        """从数据库获取当前持仓"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT symbol, quantity, entry_price, stop_loss, take_profit, leverage, cost, entry_time
            FROM positions 
            WHERE status = 'open'
        """)
        
        positions = []
        for row in cursor.fetchall():
            positions.append({
                'symbol': row[0],
                'quantity': row[1],
                'entry_price': row[2],
                'stop_loss': row[3],
                'take_profit': row[4],
                'leverage': row[5],
                'cost': row[6],
                'entry_time': row[7]
            })
        
        conn.close()
        return positions
    
    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取{symbol}价格失败: {e}")
            return None
    
    def close_position(self, symbol, quantity, current_price, reason, pnl):
        """平仓"""
        print(f"\n{'='*60}")
        print(f"🚨 执行{reason}: {symbol}")
        print(f"   数量: {quantity}")
        print(f"   价格: ${current_price:.2f}")
        print(f"   盈亏: ${pnl:.2f}")
        print(f"{'='*60}")
        
        # 更新数据库 - 标记持仓为已平仓
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        
        cursor.execute("""
            UPDATE positions 
            SET status = 'closed'
            WHERE symbol = ? AND status = 'open'
        """, (symbol,))
        
        # 记录交易
        cursor.execute("""
            INSERT INTO trades 
            (timestamp, symbol, side, price, quantity, leverage, cost, pnl, reason)
            VALUES (?, ?, 'sell', ?, ?, ?, ?, ?, ?)
        """, (
            datetime.now().isoformat(),
            symbol,
            current_price,
            quantity,
            0,  # leverage for close
            0,  # cost for close
            pnl,
            reason
        ))
        
        conn.commit()
        conn.close()
        
        print("✅ 平仓完成")
        
        # 发送Telegram通知
        self.send_telegram(f"🚨 {reason}\n"
                          f"交易对: {symbol}\n"
                          f"价格: ${current_price:.2f}\n"
                          f"盈亏: ${pnl:.2f}")
    
    def send_telegram(self, message):
        """发送Telegram通知"""
        try:
            import requests
            url = f"https://api.telegram.org/bot{self.telegram_token}/sendMessage"
            data = {
                'chat_id': self.telegram_chat_id,
                'text': message,
                'parse_mode': 'HTML'
            }
            requests.post(url, data=data, timeout=10)
        except Exception as e:
            print(f"发送Telegram失败: {e}")
    
    def check_positions(self):
        """检查所有持仓的止损止盈"""
        positions = self.get_positions_from_db()
        
        if not positions:
            return
        
        print(f"\n[{datetime.now().strftime('%H:%M:%S')}] 检查 {len(positions)} 个持仓...")
        
        for pos in positions:
            symbol = pos['symbol']
            current_price = self.get_current_price(symbol)
            
            if current_price is None:
                continue
            
            stop_loss = pos['stop_loss']
            take_profit = pos['take_profit']
            entry_price = pos['entry_price']
            quantity = pos['quantity']
            
            # 计算盈亏
            pnl = (current_price - entry_price) * quantity * pos['leverage']
            
            # 检查止损
            if current_price <= stop_loss:
                print(f"🔴 {symbol} 触发止损！")
                print(f"   当前价: ${current_price:.2f} <= 止损价: ${stop_loss:.2f}")
                self.close_position(symbol, quantity, current_price, "止损", pnl)
                continue
            
            # 检查止盈
            if current_price >= take_profit:
                print(f"✅ {symbol} 触发止盈！")
                print(f"   当前价: ${current_price:.2f} >= 止盈价: ${take_profit:.2f}")
                self.close_position(symbol, quantity, current_price, "止盈", pnl)
                continue
            
            # 正常监控
            to_stop = ((current_price - stop_loss) / current_price) * 100
            to_take = ((take_profit - current_price) / current_price) * 100
            
            status = "✅" if to_stop > 3 else "🟠" if to_stop > 1 else "🔴"
            print(f"  {status} {symbol}: ${current_price:.2f} | "
                  f"止损↓{to_stop:.1f}% | 止盈↑{to_take:.1f}% | PnL ${pnl:.2f}")
    
    def run(self):
        """持续运行监控"""
        print("\n🚀 开始监控持仓...\n")
        
        while True:
            try:
                self.check_positions()
                time.sleep(30)  # 每30秒检查一次
                
            except KeyboardInterrupt:
                print("\n\n⏹  监控服务已停止")
                break
            except Exception as e:
                print(f"\n❌ 监控错误: {e}")
                import traceback
                traceback.print_exc()
                time.sleep(30)

if __name__ == '__main__':
    monitor = PositionMonitor()
    monitor.run()
