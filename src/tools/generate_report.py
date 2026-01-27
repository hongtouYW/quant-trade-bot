#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成每日交易报表
"""

import sqlite3
from datetime import datetime, date, timedelta
import sys
import os

sys.path.append(os.path.dirname(os.path.abspath(__file__)))

def generate_daily_report(db_path='paper_trading.db', target_date=None):
    """生成指定日期的报表"""
    
    if target_date is None:
        target_date = date.today()
    
    print(f"\n{'='*80}")
    print(f"📊 每日交易报表 - {target_date}")
    print(f"{'='*80}\n")
    
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # 获取指定日期的交易
        target_date_str = target_date.isoformat()
        cursor.execute('''
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
                reason
            FROM trades 
            WHERE date(timestamp) = ? 
            ORDER BY timestamp ASC
        ''', (target_date_str,))
        
        trades = cursor.fetchall()
        
        if len(trades) == 0:
            print(f"⚠️  {target_date} 无交易记录\n")
            return
        
        # 统计数据
        total_trades = len(trades)
        buy_count = sum(1 for t in trades if t[2] == 'buy')
        sell_count = sum(1 for t in trades if t[2] == 'sell')
        total_pnl = sum(t[8] for t in trades if t[8])
        total_fees = sum(t[7] for t in trades)
        
        winning_trades = sum(1 for t in trades if t[8] and t[8] > 0)
        losing_trades = sum(1 for t in trades if t[8] and t[8] < 0)
        
        # 打印报表头
        print(f"📈 交易概览")
        print(f"{'─'*80}")
        print(f"  总交易: {total_trades} 笔")
        print(f"  买入: {buy_count} 笔 | 卖出: {sell_count} 笔")
        print(f"  盈利: {winning_trades} 笔 | 亏损: {losing_trades} 笔")
        
        if sell_count > 0:
            win_rate = (winning_trades / sell_count) * 100
            print(f"  胜率: {win_rate:.1f}%")
        
        pnl_emoji = "🟢" if total_pnl > 0 else "🔴"
        print(f"  {pnl_emoji} 今日盈亏: ${total_pnl:+,.2f}")
        print(f"  💸 今日手续费: ${total_fees:.2f}")
        print()
        
        # 打印详细交易
        print(f"📋 交易明细")
        print(f"{'─'*80}")
        
        for i, trade in enumerate(trades, 1):
            timestamp = datetime.fromisoformat(trade[0]).strftime('%H:%M:%S')
            symbol = trade[1]
            side = trade[2]
            price = trade[3]
            quantity = trade[4]
            leverage = trade[5]
            cost = trade[6]
            fee = trade[7]
            pnl = trade[8]
            pnl_pct = trade[9]
            reason = trade[10]
            
            # 图标
            if side == 'buy':
                side_icon = "📈 做多"
                side_color = "买入"
            else:
                side_icon = "📉 平仓"
                side_color = "卖出"
            
            print(f"\n{i}. {side_icon} {symbol}")
            print(f"   时间: {timestamp}")
            print(f"   价格: ${price:,.2f}")
            print(f"   数量: {quantity:.6f}")
            print(f"   杠杆: {leverage}x")
            print(f"   成本: ${cost:,.2f}")
            print(f"   手续费: ${fee:.2f}")
            
            if side == 'sell' and pnl is not None:
                pnl_emoji = "🟢" if pnl > 0 else "🔴"
                print(f"   {pnl_emoji} 盈亏: ${pnl:+,.2f} ({pnl_pct:+.2f}%)")
                if reason:
                    print(f"   原因: {reason}")
        
        # 获取当日结束时的账户状态
        cursor.execute('''
            SELECT balance_after FROM trades 
            WHERE date(timestamp) = ? 
            ORDER BY timestamp DESC 
            LIMIT 1
        ''', (target_date_str,))
        
        result = cursor.fetchone()
        if result:
            final_balance = result[0]
            print(f"\n{'─'*80}")
            print(f"💰 日终余额: ${final_balance:,.2f}")
        
        conn.close()
        
        print(f"\n{'='*80}\n")
        
    except Exception as e:
        print(f"❌ 错误: {e}")
        import traceback
        traceback.print_exc()


def show_summary(db_path='paper_trading.db', days=7):
    """显示最近N天的汇总"""
    
    print(f"\n{'='*80}")
    print(f"📊 最近{days}天交易汇总")
    print(f"{'='*80}\n")
    
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # 获取最近N天的每日统计
        for i in range(days - 1, -1, -1):
            target_date = date.today() - timedelta(days=i)
            target_date_str = target_date.isoformat()
            
            # 获取当天交易
            cursor.execute('''
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN side='buy' THEN 1 ELSE 0 END) as buys,
                    SUM(CASE WHEN side='sell' THEN 1 ELSE 0 END) as sells,
                    SUM(CASE WHEN pnl IS NOT NULL THEN pnl ELSE 0 END) as pnl,
                    SUM(fee) as fees
                FROM trades 
                WHERE date(timestamp) = ?
            ''', (target_date_str,))
            
            result = cursor.fetchone()
            total, buys, sells, pnl, fees = result
            
            if total > 0:
                pnl_emoji = "🟢" if pnl > 0 else "🔴"
                print(f"📅 {target_date} ({target_date.strftime('%A')})")
                print(f"   交易: {total}笔 (买{buys}/卖{sells})")
                print(f"   {pnl_emoji} 盈亏: ${pnl:+,.2f}")
                print(f"   手续费: ${fees:.2f}")
                print()
        
        conn.close()
        
    except Exception as e:
        print(f"❌ 错误: {e}")


def main():
    """主程序"""
    import argparse
    
    parser = argparse.ArgumentParser(description='生成交易报表')
    parser.add_argument('--date', '-d', help='指定日期 (YYYY-MM-DD)')
    parser.add_argument('--summary', '-s', action='store_true', help='显示最近7天汇总')
    parser.add_argument('--days', type=int, default=7, help='汇总天数')
    parser.add_argument('--db', default='paper_trading.db', help='数据库路径')
    
    args = parser.parse_args()
    
    if args.summary:
        show_summary(args.db, args.days)
    else:
        if args.date:
            target_date = datetime.strptime(args.date, '%Y-%m-%d').date()
        else:
            target_date = date.today()
        
        generate_daily_report(args.db, target_date)


if __name__ == "__main__":
    main()
