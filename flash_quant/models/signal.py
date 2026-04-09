"""
Signal 模型 - 信号记录表
"""
from sqlalchemy import (
    Table, Column, BigInteger, String, Float, Boolean, DateTime, Enum, JSON,
    Index, func
)
from models.base import metadata

signals = Table(
    'signals', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('timestamp', DateTime, nullable=False),
    Column('tier', Enum('tier1', 'tier2', 'tier3'), nullable=False),
    Column('symbol', String(20), nullable=False),
    Column('direction', Enum('long', 'short'), nullable=False),
    Column('score', Float),
    Column('price', Float, nullable=False),
    Column('volume_ratio', Float),
    Column('price_change_pct', Float),
    Column('oi_change_pct', Float),
    Column('taker_ratio', Float),
    Column('funding_rate', Float),
    Column('body_ratio', Float),
    Column('cvd_aligned', Boolean),
    Column('funding_passed', Boolean),
    Column('blacklist_passed', Boolean),
    Column('final_decision', Enum('passed', 'filtered', 'blocked', 'executed'), nullable=False),
    Column('filter_reason', String(255)),
    Column('latency_ms', Float),
    Column('raw_data', JSON),
    Column('created_at', DateTime, server_default=func.now()),

    Index('idx_signals_timestamp', 'timestamp'),
    Index('idx_signals_symbol_tier', 'symbol', 'tier'),
    Index('idx_signals_decision', 'final_decision'),
)
