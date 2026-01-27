#!/usr/bin/env python3
"""
简单的API服务器，为前端提供交易数据
"""
import sqlite3
import json
from http.server import HTTPServer, BaseHTTPRequestHandler
import urllib.parse
import os

class APIHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        # 解析URL
        parsed_path = urllib.parse.urlparse(self.path)
        path = parsed_path.path
        
        # 处理CORS
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-type', 'application/json')
        self.end_headers()
        
        try:
            if path == '/api/trades':
                # 获取交易历史
                trades = self.get_trade_history()
                self.wfile.write(json.dumps(trades, ensure_ascii=False).encode('utf-8'))
            
            elif path == '/api/positions':
                # 获取持仓信息
                positions = self.get_positions()
                self.wfile.write(json.dumps(positions, ensure_ascii=False).encode('utf-8'))
            
            elif path == '/api/stats':
                # 获取统计信息
                stats = self.get_stats()
                self.wfile.write(json.dumps(stats, ensure_ascii=False).encode('utf-8'))
            
            else:
                self.wfile.write(json.dumps({'error': 'Not found'}).encode('utf-8'))
                
        except Exception as e:
            print(f"API错误: {e}")
            self.wfile.write(json.dumps({'error': str(e)}).encode('utf-8'))
    
    def get_trade_history(self):
        """获取交易历史"""
        db_path = 'data/db/quick_trading.db'
        if not os.path.exists(db_path):
            return []
            
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            # 获取交易记录
            cursor.execute("""
                SELECT id, timestamp, symbol, side, price, amount, pnl, reason, balance_after 
                FROM quick_trades 
                ORDER BY timestamp DESC 
                LIMIT 20
            """)
            
            trades = []
            for row in cursor.fetchall():
                trades.append({
                    'id': row[0],
                    'timestamp': row[1],
                    'symbol': row[2],
                    'side': row[3],
                    'price': float(row[4]),
                    'amount': float(row[5]),
                    'pnl': float(row[6]) if row[6] else 0,
                    'reason': row[7],
                    'balance_after': float(row[8]) if row[8] else 0
                })
            
            conn.close()
            return trades
            
        except Exception as e:
            print(f"数据库查询错误: {e}")
            return []
    
    def get_positions(self):
        """获取持仓信息"""
        db_path = 'data/db/quick_trading.db'
        if not os.path.exists(db_path):
            return []
            
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            cursor.execute("""
                SELECT id, symbol, direction, entry_price, amount, leverage, 
                       stop_loss, take_profit, open_time, status 
                FROM positions 
                WHERE status = 'open'
                ORDER BY open_time DESC
            """)
            
            positions = []
            for row in cursor.fetchall():
                positions.append({
                    'id': row[0],
                    'symbol': row[1],
                    'direction': row[2],
                    'entry_price': float(row[3]),
                    'amount': float(row[4]),
                    'leverage': float(row[5]),
                    'stop_loss': float(row[6]) if row[6] else 0,
                    'take_profit': float(row[7]) if row[7] else 0,
                    'open_time': row[8],
                    'status': row[9]
                })
            
            conn.close()
            return positions
            
        except Exception as e:
            print(f"持仓查询错误: {e}")
            return []
    
    def get_stats(self):
        """获取统计信息"""
        db_path = 'data/db/quick_trading.db'
        
        try:
            conn = sqlite3.connect(db_path)
            cursor = conn.cursor()
            
            # 获取交易次数
            cursor.execute("SELECT COUNT(*) FROM quick_trades")
            trade_count = cursor.fetchone()[0]
            
            # 获取总盈亏
            cursor.execute("SELECT SUM(pnl), AVG(balance_after) FROM quick_trades")
            result = cursor.fetchone()
            total_pnl = result[0] if result[0] else 0
            avg_balance = result[1] if result[1] else 1000
            
            # 获取当前余额（最新记录）
            cursor.execute("SELECT balance_after FROM quick_trades ORDER BY timestamp DESC LIMIT 1")
            latest_balance = cursor.fetchone()
            current_balance = latest_balance[0] if latest_balance else 1000
            
            conn.close()
            
            return {
                'total_balance': current_balance,
                'trade_count': trade_count,
                'total_pnl': total_pnl,
                'pnl_percentage': (total_pnl / 1000) * 100 if total_pnl != 0 else 0
            }
            
        except Exception as e:
            print(f"统计查询错误: {e}")
            return {
                'total_balance': 1000,
                'trade_count': 0,
                'total_pnl': 0,
                'pnl_percentage': 0
            }
    
    def do_OPTIONS(self):
        # 处理预检请求
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

def run_api_server(port=5002):
    """启动API服务器"""
    server_address = ('', port)
    httpd = HTTPServer(server_address, APIHandler)
    print(f"API服务器启动在端口 {port}")
    print(f"可用端点:")
    print(f"  http://localhost:{port}/api/trades")
    print(f"  http://localhost:{port}/api/positions")
    print(f"  http://localhost:{port}/api/stats")
    
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\n停止API服务器")
        httpd.shutdown()

if __name__ == '__main__':
    run_api_server()