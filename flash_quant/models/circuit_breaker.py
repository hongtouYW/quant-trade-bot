"""
CircuitBreaker 模型 - 断路器状态表
"""
from sqlalchemy import (
    Table, Column, BigInteger, String, DateTime, Enum, JSON, Index, func
)
from models.base import metadata

circuit_breakers = Table(
    'circuit_breakers', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('type', Enum(
        'consecutive_loss', 'daily_loss', 'weekly_loss',
        'monthly_loss', 'black_swan', 'manual'
    ), nullable=False),
    Column('status', Enum('active', 'expired'), nullable=False),
    Column('triggered_at', DateTime, nullable=False),
    Column('expires_at', DateTime, nullable=False),
    Column('reason', String(255)),
    Column('metadata_json', JSON),

    Index('idx_cb_status', 'status'),
    Index('idx_cb_expires', 'expires_at'),
)
