#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
初始化每日收益记录表
创建 daily_pnl 表用于记录每天的交易收益
"""

import sqlite3
from datetime import datetime

DB_PATH = '/opt/trading-bot/quant-trade-bot/data/db/trading_assistant.db'

def init_daily_pnl_table():
    """创建每日收益记录表"""
    try:
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # 创建每日收益表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS daily_pnl (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL UNIQUE,
                trades_count INTEGER DEFAULT 0,
                win_count INTEGER DEFAULT 0,
                loss_count INTEGER DEFAULT 0,
                total_pnl REAL DEFAULT 0,
                total_fee REAL DEFAULT 0,
                total_funding_fee REAL DEFAULT 0,
                win_rate REAL DEFAULT 0,
                best_trade REAL DEFAULT 0,
                worst_trade REAL DEFAULT 0,
                cumulative_pnl REAL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ''')

        # 创建日期索引
        cursor.execute('''
            CREATE INDEX IF NOT EXISTS idx_daily_pnl_date
            ON daily_pnl(date DESC)
        ''')

        conn.commit()
        conn.close()

        print("=" * 60)
        print("✓ 每日收益表创建成功")
        print(f"数据库: {DB_PATH}")
        print("表名: daily_pnl")
        print("=" * 60)

    except Exception as e:
        print(f"✗ 创建表失败: {str(e)}")
        raise

if __name__ == '__main__':
    init_daily_pnl_table()
