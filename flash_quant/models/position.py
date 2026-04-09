"""
Position 模型 - 当前持仓表
"""
from sqlalchemy import (
    Table, Column, BigInteger, String, Float, Integer, DateTime, Enum, JSON,
    Index, func
)
from models.base import metadata

positions = Table(
    'positions', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('trade_id', BigInteger, nullable=False),
    Column('symbol', String(20), nullable=False),
    Column('direction', Enum('long', 'short'), nullable=False),
    Column('leverage', Integer, nullable=False),
    Column('margin', Float, nullable=False),
    Column('entry_price', Float, nullable=False),
    Column('quantity', Float, nullable=False),
    Column('current_price', Float),
    Column('unrealized_pnl', Float),
    Column('stop_loss_price', Float, nullable=False),
    Column('take_profit_levels', JSON),
    Column('open_time', DateTime, nullable=False),
    Column('max_hold_until', DateTime, nullable=False),
    Column('last_update', DateTime, server_default=func.now(), onupdate=func.now()),

    Index('idx_positions_open_time', 'open_time'),
)
