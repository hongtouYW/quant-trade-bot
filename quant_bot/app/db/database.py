"""SQLite database for trade persistence"""
import os
import sqlite3
import logging

log = logging.getLogger(__name__)

DB_PATH = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), 'data', 'quant_bot.db')

_conn = None


def get_connection():
    global _conn
    if _conn is None:
        os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
        _conn = sqlite3.connect(DB_PATH, check_same_thread=False)
        _conn.row_factory = sqlite3.Row
        _init_tables(_conn)
        log.info(f"数据库已连接: {DB_PATH}")
    return _conn


def _init_tables(conn):
    conn.executescript("""
    CREATE TABLE IF NOT EXISTS trades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        symbol TEXT NOT NULL,
        direction INTEGER NOT NULL,
        setup_type TEXT,
        entry_price REAL NOT NULL,
        exit_price REAL,
        size REAL NOT NULL,
        margin REAL DEFAULT 0,
        pnl REAL DEFAULT 0,
        pnl_pct REAL DEFAULT 0,
        fees REAL DEFAULT 0,
        funding_fees REAL DEFAULT 0,
        net_pnl REAL DEFAULT 0,
        close_reason TEXT,
        opened_at TEXT NOT NULL,
        closed_at TEXT,
        status TEXT DEFAULT 'open'
    );

    CREATE TABLE IF NOT EXISTS daily_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT UNIQUE NOT NULL,
        total_trades INTEGER DEFAULT 0,
        wins INTEGER DEFAULT 0,
        losses INTEGER DEFAULT 0,
        pnl REAL DEFAULT 0,
        pnl_pct REAL DEFAULT 0,
        max_drawdown REAL DEFAULT 0,
        equity_start REAL DEFAULT 0,
        equity_end REAL DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS positions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        symbol TEXT NOT NULL,
        direction INTEGER NOT NULL,
        entry_price REAL NOT NULL,
        size REAL NOT NULL,
        margin REAL DEFAULT 0,
        stop_loss REAL DEFAULT 0,
        tp1 REAL DEFAULT 0,
        tp2 REAL DEFAULT 0,
        tp1_done INTEGER DEFAULT 0,
        original_size REAL DEFAULT 0,
        strategy_tag TEXT,
        opened_at TEXT NOT NULL,
        order_id TEXT,
        entry_fee REAL DEFAULT 0,
        funding_fees REAL DEFAULT 0,
        status TEXT DEFAULT 'open'
    );
    """)
    conn.commit()

    # Migration: add columns if missing
    _migrate(conn)


def _migrate(conn):
    """Add new columns to existing tables if missing"""
    migrations = [
        ("trades", "funding_fees", "REAL DEFAULT 0"),
        ("trades", "net_pnl", "REAL DEFAULT 0"),
        ("positions", "entry_fee", "REAL DEFAULT 0"),
        ("positions", "funding_fees", "REAL DEFAULT 0"),
    ]
    for table, col, col_type in migrations:
        try:
            conn.execute(f"ALTER TABLE {table} ADD COLUMN {col} {col_type}")
            conn.commit()
        except sqlite3.OperationalError:
            pass  # column already exists
