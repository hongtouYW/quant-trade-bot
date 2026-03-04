#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
记录每日收益
每天运行一次，汇总当天的交易数据并记录到daily_pnl表
"""

import sqlite3
from datetime import datetime, timedelta

DB_PATH = '/opt/trading-bot/quant-trade-bot/data/db/trading_assistant.db'

def record_daily_pnl(date=None):
    """
    记录指定日期的收益数据
    如果不指定日期，则记录昨天的数据
    """
    if date is None:
        # 默认记录昨天的数据
        yesterday = datetime.now() - timedelta(days=1)
        date = yesterday.strftime('%Y-%m-%d')

    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()

        # 获取指定日期的所有已平仓交易
        cursor.execute('''
            SELECT
                COUNT(*) as trades_count,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_count,
                SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END) as loss_count,
                SUM(COALESCE(pnl, 0)) as total_pnl,
                SUM(COALESCE(fee, 0)) as total_fee,
                SUM(COALESCE(funding_fee, 0)) as total_funding_fee,
                MAX(pnl) as best_trade,
                MIN(pnl) as worst_trade
            FROM real_trades
            WHERE mode = 'paper'
            AND assistant = '交易助手'
            AND status = 'CLOSED'
            AND DATE(exit_time) = ?
        ''', (date,))

        stats = dict(cursor.fetchone())

        # 如果当天没有交易，不记录
        if stats['trades_count'] == 0:
            print(f"{date}: 无交易数据")
            conn.close()
            return

        # 计算胜率
        win_rate = (stats['win_count'] / stats['trades_count'] * 100) if stats['trades_count'] > 0 else 0

        # 获取截止到这一天的累计盈亏
        cursor.execute('''
            SELECT COALESCE(SUM(pnl), 0) as cumulative_pnl
            FROM real_trades
            WHERE mode = 'paper'
            AND assistant = '交易助手'
            AND status = 'CLOSED'
            AND DATE(exit_time) <= ?
        ''', (date,))

        cumulative_result = cursor.fetchone()
        cumulative_pnl = cumulative_result['cumulative_pnl']

        # 先尝试删除已有记录，然后插入新记录
        cursor.execute('DELETE FROM daily_pnl WHERE date = ?', (date,))

        # 插入每日记录
        cursor.execute('''
            INSERT INTO daily_pnl (
                date, trades_count, win_count, loss_count,
                total_pnl, total_fee, total_funding_fee,
                win_rate, best_trade, worst_trade, cumulative_pnl,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ''', (
            date,
            stats['trades_count'],
            stats['win_count'],
            stats['loss_count'],
            stats['total_pnl'],
            stats['total_fee'],
            stats['total_funding_fee'],
            win_rate,
            stats['best_trade'] or 0,
            stats['worst_trade'] or 0,
            cumulative_pnl
        ))

        conn.commit()
        conn.close()

        print(f"✓ {date}: 记录成功")
        print(f"  交易: {stats['trades_count']}笔, 胜率: {win_rate:.1f}%")
        print(f"  盈亏: {stats['total_pnl']:.2f}U, 累计: {cumulative_pnl:.2f}U")

    except Exception as e:
        print(f"✗ 记录失败: {str(e)}")
        raise

def backfill_daily_pnl(days=30):
    """
    回填历史数据
    回填最近N天的数据
    """
    print(f"开始回填最近{days}天的历史数据...")

    for i in range(days, -1, -1):
        date = (datetime.now() - timedelta(days=i)).strftime('%Y-%m-%d')
        try:
            record_daily_pnl(date)
        except Exception as e:
            print(f"  {date}: 跳过 - {str(e)}")
            continue

    print("=" * 60)
    print("✓ 历史数据回填完成")

if __name__ == '__main__':
    import sys

    if len(sys.argv) > 1 and sys.argv[1] == 'backfill':
        # 回填历史数据
        days = int(sys.argv[2]) if len(sys.argv) > 2 else 30
        backfill_daily_pnl(days)
    else:
        # 记录昨天的数据
        record_daily_pnl()
