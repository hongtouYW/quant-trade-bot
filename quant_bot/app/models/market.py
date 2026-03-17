"""Core data structures"""
from dataclasses import dataclass, field
from datetime import datetime
from typing import List, Optional, Tuple


@dataclass
class Candle:
    ts: datetime
    open: float
    high: float
    low: float
    close: float
    volume: float


@dataclass
class SymbolSnapshot:
    symbol: str
    volume_24h: float = 0.0
    open_interest: float = 0.0
    spread_pct: float = 0.0
    funding_rate: float = 0.0
    listing_days: int = 999
    momentum_score: float = 0.0
    quality_score: float = 0.0
    final_score: float = 0.0
    direction: int = 0      # 1=long, -1=short, 0=neutral
    regime: str = 'UNKNOWN'  # TRENDING / RANGING / EXTREME
    atrp_1h: float = 0.0
    adx_1h: float = 0.0
    last_price: float = 0.0


@dataclass
class Signal:
    symbol: str
    direction: int           # 1=long, -1=short
    setup_type: str          # pullback / compression_breakout
    score: float
    entry_price: float
    stop_loss: float
    tp1: float = 0.0
    tp2: float = 0.0
    risk_reward: float = 0.0
    created_at: datetime = field(default_factory=datetime.utcnow)
    expires_at: Optional[datetime] = None
    grade: str = 'C'         # A / B / C


@dataclass
class Position:
    symbol: str
    direction: int
    entry_price: float
    size: float
    margin: float
    stop_loss: float
    tp1: float
    tp2: float
    opened_at: datetime
    strategy_tag: str
    tp1_done: bool = False
    original_size: float = 0.0
    current_pnl: float = 0.0
    current_pnl_pct: float = 0.0
    order_id: str = ''


@dataclass
class TradeRecord:
    symbol: str
    direction: int
    entry_price: float
    exit_price: float
    size: float
    pnl: float
    pnl_pct: float
    setup_type: str
    close_reason: str
    opened_at: datetime
    closed_at: datetime
    fees: float = 0.0
