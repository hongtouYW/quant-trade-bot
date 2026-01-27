#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
查看模拟交易记录
"""

import sqlite3
import pandas as pd
from datetime import datetime
import sys

def view_trades(db_path='paper_trading.db', limit=20):
    """查看交易记录"""
    try:
        conn = sqlite3.connect(db_path)
        
        query = f'''
            SELECT 
                timestamp,
                symbol,
                side,
                price,
                quantity,
                leverage,
                cost,
                fee,
                pnl,
                pnl_pct,
                reason,
                balance_after
            FROM trades
            ORDER BY timestamp DESC
            LIMIT {limit}
        '''
        
        df = pd.read_sql_query(query, conn)
        
        if len(df) == 0:
            print("📊 暂无交易记录")
            return
        
        print("\n" + "="*80)
        print(f"📊 最近 {len(df)} 笔交易记录")
        print("="*80)
        
        for idx, row in df.iterrows():
            timestamp = datetime.fromisoformat(row['timestamp']).strftime('%Y-%m-%d %H:%M:%S')
            side_emoji = "📈" if row['side'] == 'buy' else "📉"
            
            print(f"\n{side_emoji} {row['symbol']} - {row['side'].upper()}")
            print(f"  时间: {timestamp}")
            print(f"  价格: ${row['price']:,.2f}")
            print(f"  数量: {row['quantity']:.6f}")
            print(f"  杠杆: {row['leverage']}x")
            print(f"  成本: ${row['cost']:,.2f}")
            print(f"  手续费: ${row['fee']:.2f}")
            
            if row['side'] == 'sell':
                pnl_emoji = "🟢" if row['pnl'] > 0 else "🔴"
                print(f"  {pnl_emoji} 盈亏: ${row['pnl']:+,.2f} ({row['pnl_pct']:+.2f}%)")
                if row['reason']:
                    print(f"  原因: {row['reason']}")
            
            print(f"  余额: ${row['balance_after']:,.2f}")
        
        conn.close()
        
    except Exception as e:
        print(f"❌ 错误: {e}")

def view_positions(db_path='paper_trading.db'):
    """查看持仓"""
    try:
        conn = sqlite3.connect(db_path)
        
        query = '''
            SELECT 
                symbol,
                quantity,
                entry_price,
                entry_time,
                leverage,
                stop_loss,
                take_profit,
                cost,
                status
            FROM positions
            WHERE status = 'open'
            ORDER BY entry_time DESC
        '''
        
        df = pd.read_sql_query(query, conn)
        
        if len(df) == 0:
            print("\n📊 当前无持仓")
            return
        
        print("\n" + "="*80)
        print(f"📊 当前持仓 ({len(df)})")
        print("="*80)
        
        for idx, row in df.iterrows():
            entry_time = datetime.fromisoformat(row['entry_time']).strftime('%Y-%m-%d %H:%M:%S')
            
            print(f"\n📈 {row['symbol']}")
            print(f"  入场时间: {entry_time}")
            print(f"  入场价格: ${row['entry_price']:,.2f}")
            print(f"  数量: {row['quantity']:.6f}")
            print(f"  杠杆: {row['leverage']}x")
            print(f"  保证金: ${row['cost']:,.2f}")
            print(f"  止损: ${row['stop_loss']:,.2f}")
            print(f"  止盈: ${row['take_profit']:,.2f}")
        
        conn.close()
        
    except Exception as e:
        print(f"❌ 错误: {e}")

def view_stats(db_path='paper_trading.db'):
    """查看统计"""
    try:
        conn = sqlite3.connect(db_path)
        
        # 获取最新统计
        query = '''
            SELECT 
                timestamp,
                balance,
                total_pnl,
                total_trades,
                winning_trades,
                losing_trades,
                win_rate,
                total_fees
            FROM stats
            ORDER BY timestamp DESC
            LIMIT 1
        '''
        
        cursor = conn.cursor()
        cursor.execute(query)
        row = cursor.fetchone()
        
        if not row:
            print("\n📊 暂无统计数据")
            return
        
        print("\n" + "="*80)
        print("📊 交易统计")
        print("="*80)
        
        timestamp = datetime.fromisoformat(row[0]).strftime('%Y-%m-%d %H:%M:%S')
        balance = row[1]
        total_pnl = row[2]
        total_trades = row[3]
        winning_trades = row[4]
        losing_trades = row[5]
        win_rate = row[6]
        total_fees = row[7]
        
        print(f"\n更新时间: {timestamp}")
        print(f"当前余额: ${balance:,.2f}")
        emoji = "🟢" if total_pnl > 0 else "🔴"
        print(f"{emoji} 总盈亏: ${total_pnl:+,.2f}")
        print(f"总交易: {total_trades}")
        print(f"盈利: {winning_trades} | 亏损: {losing_trades}")
        print(f"胜率: {win_rate:.1f}%")
        print(f"总手续费: ${total_fees:,.2f}")
        
        conn.close()
        
    except Exception as e:
        print(f"❌ 错误: {e}")

def main():
    """主程序"""
    import argparse
    
    parser = argparse.ArgumentParser(description='查看模拟交易记录')
    parser.add_argument('--trades', '-t', action='store_true', help='查看交易记录')
    parser.add_argument('--positions', '-p', action='store_true', help='查看持仓')
    parser.add_argument('--stats', '-s', action='store_true', help='查看统计')
    parser.add_argument('--all', '-a', action='store_true', help='查看全部')
    parser.add_argument('--limit', '-l', type=int, default=20, help='记录数量')
    parser.add_argument('--db', '-d', default='paper_trading.db', help='数据库路径')
    
    args = parser.parse_args()
    
    if args.all or (not args.trades and not args.positions and not args.stats):
        view_stats(args.db)
        view_positions(args.db)
        view_trades(args.db, args.limit)
    else:
        if args.stats:
            view_stats(args.db)
        if args.positions:
            view_positions(args.db)
        if args.trades:
            view_trades(args.db, args.limit)
    
    print("\n" + "="*80 + "\n")

if __name__ == "__main__":
    main()
