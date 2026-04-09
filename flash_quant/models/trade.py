"""
Trade 模型 - 交易记录表
"""
from sqlalchemy import (
    Table, Column, BigInteger, String, Float, Integer, DateTime, Enum, JSON,
    Index, func
)
from models.base import metadata

trades = Table(
    'trades', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('signal_id', BigInteger),
    Column('mode', Enum('paper', 'live'), nullable=False),
    Column('tier', Enum('tier1', 'tier2', 'tier3'), nullable=False),
    Column('symbol', String(20), nullable=False),
    Column('direction', Enum('long', 'short'), nullable=False),
    Column('leverage', Integer, nullable=False),
    Column('margin', Float, nullable=False),
    Column('entry_price', Float, nullable=False),
    Column('exit_price', Float),
    Column('quantity', Float, nullable=False),
    Column('status', Enum('open', 'closed', 'cancelled'), nullable=False),
    Column('open_time', DateTime, nullable=False),
    Column('close_time', DateTime),
    Column('pnl', Float),
    Column('pnl_pct', Float),
    Column('fee', Float),
    Column('close_reason', String(50)),
    Column('binance_order_id', String(50)),
    Column('stop_loss_order_id', String(50)),
    Column('take_profit_data', JSON),
    Column('created_at', DateTime, server_default=func.now()),
    Column('updated_at', DateTime, server_default=func.now(), onupdate=func.now()),

    Index('idx_trades_status', 'status'),
    Index('idx_trades_open_time', 'open_time'),
    Index('idx_trades_symbol', 'symbol'),
    Index('idx_trades_mode_tier', 'mode', 'tier'),
)
