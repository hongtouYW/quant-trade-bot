"""
DailyStat 模型 - 日统计表
"""
from sqlalchemy import (
    Table, Column, BigInteger, Date, Float, Integer, DateTime, func
)
from models.base import metadata

daily_stats = Table(
    'daily_stats', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('date', Date, nullable=False, unique=True),
    Column('starting_balance', Float, nullable=False),
    Column('ending_balance', Float, nullable=False),
    Column('total_trades', Integer, default=0),
    Column('winning_trades', Integer, default=0),
    Column('losing_trades', Integer, default=0),
    Column('total_pnl', Float, default=0),
    Column('total_fee', Float, default=0),
    Column('max_drawdown_pct', Float),
    Column('win_rate', Float),
    Column('profit_factor', Float),
    Column('tier1_trades', Integer, default=0),
    Column('tier2_trades', Integer, default=0),
    Column('tier3_trades', Integer, default=0),
    Column('created_at', DateTime, server_default=func.now()),
)
