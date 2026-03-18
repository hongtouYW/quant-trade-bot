"""Shared fixtures and helpers for quant-v4lite tests."""

import math
import random
import pytest
from typing import List

from src.core.models import (
    Kline, OrderBook, OrderBookLevel, Signal, SymbolInfo,
)
from src.core.enums import Direction, SignalStrategy


# ────────────────────────────────────────────
# Kline generators
# ────────────────────────────────────────────

def generate_klines(
    n: int = 100,
    start_price: float = 100.0,
    trend: float = 0.0,
    volatility: float = 0.5,
    start_ts: int = 1_700_000_000_000,
    interval_ms: int = 3_600_000,
) -> List[Kline]:
    """Generate synthetic klines.

    Args:
        n: number of bars
        start_price: opening price of first bar
        trend: per-bar drift (positive = up, negative = down)
        volatility: random noise amplitude as pct of price
        start_ts: first bar timestamp in ms
        interval_ms: bar interval in ms
    """
    klines = []
    price = start_price
    random.seed(42)

    for i in range(n):
        noise = (random.random() - 0.5) * 2 * volatility
        o = price
        c = price + trend + noise
        h = max(o, c) + abs(noise) * 0.5
        l = min(o, c) - abs(noise) * 0.5
        vol = 1000 + random.random() * 500
        klines.append(Kline(
            timestamp=start_ts + i * interval_ms,
            open=round(o, 4),
            high=round(h, 4),
            low=round(l, 4),
            close=round(c, 4),
            volume=round(vol, 2),
            quote_volume=round(vol * c, 2),
            taker_buy_volume=round(vol * 0.5, 2),
        ))
        price = c
    return klines


def generate_trending_klines(
    n: int = 250,
    direction: str = "up",
    start_price: float = 100.0,
) -> List[Kline]:
    """Generate clearly trending klines (up or down)."""
    trend = 0.3 if direction == "up" else -0.3
    return generate_klines(n=n, start_price=start_price, trend=trend, volatility=0.1)


def generate_ranging_klines(
    n: int = 250,
    center: float = 100.0,
) -> List[Kline]:
    """Generate sideways/ranging klines with no net trend."""
    return generate_klines(n=n, start_price=center, trend=0.0, volatility=0.3)


def generate_high_volume_klines(
    n: int = 50,
    start_price: float = 100.0,
    trend: float = 0.1,
) -> List[Kline]:
    """Generate klines where the last bar has notably higher volume."""
    klines = generate_klines(n=n, start_price=start_price, trend=trend, volatility=0.1)
    # Spike the last bar's volume
    last = klines[-1]
    klines[-1] = Kline(
        timestamp=last.timestamp,
        open=last.open,
        high=last.high,
        low=last.low,
        close=last.close,
        volume=last.volume * 5,
        quote_volume=last.quote_volume * 5,
        taker_buy_volume=last.taker_buy_volume * 5,
    )
    return klines


# ────────────────────────────────────────────
# OrderBook helpers
# ────────────────────────────────────────────

def make_orderbook(
    symbol: str = "BTC/USDT:USDT",
    mid_price: float = 100.0,
    levels: int = 20,
    bid_qty: float = 10.0,
    ask_qty: float = 10.0,
) -> OrderBook:
    """Create a symmetric order book around mid_price."""
    tick = mid_price * 0.0001  # 0.01%
    bids = [
        OrderBookLevel(price=round(mid_price - tick * (i + 1), 4), quantity=bid_qty)
        for i in range(levels)
    ]
    asks = [
        OrderBookLevel(price=round(mid_price + tick * (i + 1), 4), quantity=ask_qty)
        for i in range(levels)
    ]
    return OrderBook(symbol=symbol, timestamp=1_700_000_000_000, bids=bids, asks=asks)


# ────────────────────────────────────────────
# Signal helper
# ────────────────────────────────────────────

def make_signal(
    symbol: str = "BTC/USDT:USDT",
    direction: Direction = Direction.LONG,
    entry: float = 100.0,
    stop_loss: float = 95.0,
) -> Signal:
    if direction == Direction.LONG:
        tps = [(entry * 1.10, 0.5), (entry * 1.20, 0.5)]
    else:
        tps = [(entry * 0.90, 0.5), (entry * 0.80, 0.5)]
    return Signal(
        symbol=symbol,
        direction=direction,
        strategy=SignalStrategy.TREND_FOLLOW,
        entry_price=entry,
        stop_loss=stop_loss,
        take_profits=tps,
        risk_reward=2.0,
        confidence=0.8,
    )


# ────────────────────────────────────────────
# Config fixtures
# ────────────────────────────────────────────

@pytest.fixture
def default_config() -> dict:
    """Minimal config dict used across multiple test modules."""
    return {
        "risk": {
            "daily_loss_limit_pct": 3.0,
            "daily_profit_hard_stop_pct": 8.0,
            "max_consecutive_losses": 5,
            "cooldown_after_streak_min": 30,
            "same_symbol_daily_loss_pct": 1.0,
            "total_drawdown_stop_pct": 15.0,
            "weekly_loss_limit_pct": 8.0,
            "max_correlation": 0.75,
        },
        "execution": {
            "stop_atr_multiple": 1.2,
            "tp1_r": 1.5,
            "tp2_r": 2.5,
            "tp1_close_pct": 0.5,
            "tp2_close_pct": 0.5,
            "min_risk_reward": 1.8,
        },
        "trend_filter": {
            "ema_fast": 20,
            "ema_mid": 50,
            "ema_slow": 200,
            "adx_period": 14,
            "adx_strong": 25,
        },
        "liquidity_filter": {
            "min_24h_quote_volume": 5_000_000,
            "max_spread_pct": 0.15,
            "min_volume_ratio": 1.2,
        },
        "volatility_filter": {
            "atr_period": 14,
            "atrp_min_pct": 0.8,
            "atrp_max_pct": 4.5,
        },
        "ranking": {
            "min_score": 60,
            "top_n": 15,
            "weights": {
                "ema_alignment": 30,
                "adx_strength": 25,
                "momentum": 20,
                "volume_expansion": 15,
                "liquidity": 10,
            },
        },
        "orderbook": {
            "wall_threshold": 3.0,
            "imbalance_min_ratio": 0.65,
            "analysis_zone_pct": 0.5,
        },
        "smart_order": {
            "enabled": True,
            "maker_wait_sec": 0.01,
            "chase_wait_sec": 0.01,
            "max_slippage_pct": 0.05,
            "post_only": True,
            "urgent_types": ["stop_loss"],
        },
        "symbols": {
            "blacklist": ["LUNA"],
            "min_listing_days": 7,
        },
        "account": {
            "balance": 2000,
            "leverage": 10,
        },
    }
