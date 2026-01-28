#!/usr/bin/env python3
"""
Upgrade paper_trader.db schema for v0.6
Add tables: account_snapshots, signal_history, daily_stats, config
"""

import sqlite3

DB_PATH = "/opt/trading-bot/quant-trade-bot/data/db/paper_trader.db"

def upgrade_schema():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    # 1. account_snapshots - 账户快照，追踪资金曲线
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS account_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            balance REAL NOT NULL,           -- 账户余额
            equity REAL NOT NULL,            -- 账户权益(含持仓)
            positions_value REAL DEFAULT 0,  -- 持仓价值
            unrealized_pnl REAL DEFAULT 0,   -- 未实现盈亏
            realized_pnl REAL DEFAULT 0,     -- 已实现盈亏
            max_drawdown REAL DEFAULT 0,     -- 最大回撤
            positions_count INTEGER DEFAULT 0 -- 持仓数量
        )
    ''')
    print("✅ account_snapshots 表创建完成")

    # 2. signal_history - 信号历史，记录每次扫描的信号
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS signal_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            symbol TEXT NOT NULL,
            signal_type TEXT,          -- BUY/SELL/HOLD
            score INTEGER DEFAULT 0,    -- 信号评分
            rsi REAL,                   -- RSI值
            trend TEXT,                 -- UP/DOWN/SIDEWAYS
            price REAL,                 -- 当时价格
            atr REAL,                   -- ATR值
            atr_pct REAL,               -- ATR百分比
            executed INTEGER DEFAULT 0, -- 是否执行了交易
            reason TEXT                 -- 未执行原因
        )
    ''')
    print("✅ signal_history 表创建完成")

    # 3. daily_stats - 每日统计
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS daily_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date DATE UNIQUE NOT NULL,
            trades_count INTEGER DEFAULT 0,     -- 当日交易数
            win_count INTEGER DEFAULT 0,        -- 盈利交易数
            loss_count INTEGER DEFAULT 0,       -- 亏损交易数
            total_pnl REAL DEFAULT 0,           -- 当日盈亏
            total_fee REAL DEFAULT 0,           -- 当日手续费
            total_funding REAL DEFAULT 0,       -- 当日资金费
            win_rate REAL DEFAULT 0,            -- 胜率
            avg_win REAL DEFAULT 0,             -- 平均盈利
            avg_loss REAL DEFAULT 0,            -- 平均亏损
            max_win REAL DEFAULT 0,             -- 最大盈利
            max_loss REAL DEFAULT 0,            -- 最大亏损
            start_balance REAL DEFAULT 0,       -- 日初余额
            end_balance REAL DEFAULT 0,         -- 日末余额
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    print("✅ daily_stats 表创建完成")

    # 4. config - 配置中心
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS config (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            description TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    print("✅ config 表创建完成")

    # 插入默认配置
    default_configs = [
        ('initial_capital', '2000', '初始本金'),
        ('target_profit', '3400', '目标利润'),
        ('max_positions', '10', '最大持仓数'),
        ('default_leverage', '10', '默认杠杆'),
        ('stop_loss_pct', '0.015', '止损百分比'),
        ('trailing_pct', '0.015', '移动止盈距离'),
        ('min_score', '60', '最低信号分数'),
        ('scan_interval', '60', '扫描间隔(秒)'),
        ('trading_enabled', '1', '是否启用交易'),
        ('use_atr_stop', '1', '使用ATR动态止损'),
    ]

    for key, value, desc in default_configs:
        cursor.execute('''
            INSERT OR IGNORE INTO config (key, value, description)
            VALUES (?, ?, ?)
        ''', (key, value, desc))
    print("✅ 默认配置已插入")

    # 5. 给real_trades添加新字段 (如果不存在)
    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN entry_rsi REAL")
        print("✅ real_trades 添加 entry_rsi 字段")
    except:
        print("⏭️ entry_rsi 字段已存在")

    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN entry_trend TEXT")
        print("✅ real_trades 添加 entry_trend 字段")
    except:
        print("⏭️ entry_trend 字段已存在")

    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN atr_pct REAL")
        print("✅ real_trades 添加 atr_pct 字段")
    except:
        print("⏭️ atr_pct 字段已存在")

    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN max_profit REAL DEFAULT 0")
        print("✅ real_trades 添加 max_profit 字段")
    except:
        print("⏭️ max_profit 字段已存在")

    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN max_loss REAL DEFAULT 0")
        print("✅ real_trades 添加 max_loss 字段")
    except:
        print("⏭️ max_loss 字段已存在")

    try:
        cursor.execute("ALTER TABLE real_trades ADD COLUMN duration_minutes INTEGER")
        print("✅ real_trades 添加 duration_minutes 字段")
    except:
        print("⏭️ duration_minutes 字段已存在")

    conn.commit()
    conn.close()

    print("\n🎉 数据库升级完成!")
    print("新增表: account_snapshots, signal_history, daily_stats, config")
    print("新增字段: entry_rsi, entry_trend, atr_pct, max_profit, max_loss, duration_minutes")

if __name__ == "__main__":
    upgrade_schema()
