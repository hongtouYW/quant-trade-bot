"""
Executor: Paper trade execution engine.
Simulates fills with realistic slippage.
Monitors TP/SL/TTL for position management.
"""
import random
import logging
from datetime import datetime
from decimal import Decimal
from typing import Optional
from ..models.trade import SignalTrade
from ..models.signal import Signal
from ..models.strategy import Strategy
from ..extensions import db

logger = logging.getLogger(__name__)

# Default capital for paper trading
DEFAULT_CAPITAL = 10000.0


def simulate_fill(price: float, direction: str) -> float:
    """Add realistic slippage to paper trade fills."""
    slippage_pct = random.uniform(0.0005, 0.002)  # 0.05% - 0.2%
    if direction == "LONG":
        return price * (1 + slippage_pct)
    else:
        return price * (1 - slippage_pct)


def get_current_price(coin: str) -> Optional[float]:
    """Fetch current price via ccxt (Binance)."""
    try:
        import ccxt
        exchange = ccxt.binance({'enableRateLimit': True})
        symbol = f"{coin}/USDT"
        ticker = exchange.fetch_ticker(symbol)
        return float(ticker['last'])
    except Exception as e:
        logger.error(f"Failed to fetch price for {coin}: {e}")
        return None


def execute_paper_trade(signal: Signal, strategy: Strategy) -> Optional[SignalTrade]:
    """
    Execute a paper trade based on signal and strategy config.
    Returns the created SignalTrade or None.
    """
    config = strategy.config or {}
    capital = config.get('capital', DEFAULT_CAPITAL)
    position_pct = config.get('position_pct', 0.05)
    leverage = config.get('leverage', 1)

    # Get current price
    price = get_current_price(signal.coin)
    if not price:
        logger.error(f"Cannot execute: no price for {signal.coin}")
        return None

    # Simulate fill
    fill_price = simulate_fill(price, signal.direction)

    # Position sizing: capital * position_pct / leverage gives margin
    # But quantity is in coin units
    position_value = capital * position_pct
    quantity = position_value * leverage / fill_price

    trade = SignalTrade(
        signal_id=signal.id,
        strategy_id=strategy.id,
        user_id=strategy.user_id,
        coin=signal.coin,
        direction=signal.direction,
        entry_price=Decimal(str(round(fill_price, 8))),
        quantity=Decimal(str(round(quantity, 8))),
        leverage=leverage,
        is_paper=strategy.is_paper,
        status='open',
        opened_at=datetime.utcnow(),
    )

    db.session.add(trade)
    db.session.commit()

    logger.info(
        f"Paper trade opened: {signal.coin} {signal.direction} @ {fill_price:.2f} "
        f"qty={quantity:.6f} leverage={leverage}x (strategy={strategy.name})"
    )

    return trade


def check_tp_sl(trade: SignalTrade, signal: Signal, strategy: Strategy) -> bool:
    """
    Check if trade hit TP, SL, or TTL. Close if so.
    Returns True if trade was closed.
    """
    if trade.status != 'open':
        return False

    config = strategy.config or {}
    tp_pct = config.get('tp_pct', 0.08)
    sl_pct = config.get('sl_pct', 0.05)

    price = get_current_price(trade.coin)
    if not price:
        return False

    entry = float(trade.entry_price)
    leverage = int(trade.leverage or 1)

    # Calculate ROI
    if trade.direction == 'LONG':
        roi = (price - entry) / entry * leverage
    else:
        roi = (entry - price) / entry * leverage

    # Check TP
    if roi >= tp_pct:
        return _close_trade(trade, price, roi, 'win')

    # Check SL
    if roi <= -sl_pct:
        return _close_trade(trade, price, roi, 'loss')

    # Check TTL expiry
    if signal.expires_at and datetime.utcnow() > signal.expires_at:
        outcome = 'win' if roi > 0 else 'loss'
        return _close_trade(trade, price, roi, outcome)

    return False


def _close_trade(trade: SignalTrade, exit_price: float, roi: float, outcome: str) -> bool:
    """Close a trade with given outcome."""
    entry = float(trade.entry_price)
    qty = float(trade.quantity)
    leverage = int(trade.leverage or 1)

    if trade.direction == 'LONG':
        pnl = (exit_price - entry) * qty
    else:
        pnl = (entry - exit_price) * qty

    trade.exit_price = Decimal(str(round(exit_price, 8)))
    trade.pnl = Decimal(str(round(pnl, 8)))
    trade.roi = Decimal(str(round(roi, 4)))
    trade.status = 'closed'
    trade.closed_at = datetime.utcnow()

    db.session.commit()

    logger.info(
        f"Trade closed: {trade.coin} {trade.direction} PnL={pnl:.2f} ROI={roi:.2%} ({outcome})"
    )
    return True
