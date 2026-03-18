"""Tests for app/models/market.py"""
import pytest
from datetime import datetime, timedelta
from app.models.market import Signal, Position, TradeRecord, Candle, SymbolSnapshot


class TestSignal:
    def test_create_long_signal(self):
        sig = Signal(
            symbol='BTC/USDT', direction=1,
            setup_type='pullback', score=80,
            entry_price=50000, stop_loss=49000,
            tp1=51500, tp2=52800, risk_reward=1.5,
        )
        assert sig.symbol == 'BTC/USDT'
        assert sig.direction == 1
        assert sig.grade == 'C'  # default
        assert sig.created_at is not None

    def test_signal_with_expiry(self):
        exp = datetime.utcnow() + timedelta(minutes=3)
        sig = Signal(
            symbol='ETH/USDT', direction=-1,
            setup_type='compression_breakout', score=75,
            entry_price=3000, stop_loss=3100,
            expires_at=exp,
        )
        assert sig.expires_at == exp
        assert sig.direction == -1


class TestPosition:
    def test_create_position(self):
        pos = Position(
            symbol='BTC/USDT', direction=1,
            entry_price=50000, size=0.01, margin=50,
            stop_loss=49000, tp1=51500, tp2=52800,
            opened_at=datetime.utcnow(),
            strategy_tag='pullback',
        )
        assert pos.tp1_done is False
        assert pos.current_pnl == 0.0
        assert pos.funding_fees == 0.0
        assert pos.original_size == 0.0

    def test_position_defaults(self):
        pos = Position(
            symbol='ETH/USDT', direction=-1,
            entry_price=3000, size=1.0, margin=300,
            stop_loss=3100, tp1=2800, tp2=2600,
            opened_at=datetime.utcnow(),
            strategy_tag='mean_reversion',
        )
        assert pos.order_id == ''
        assert pos.entry_fee == 0.0


class TestTradeRecord:
    def test_create_trade_record(self):
        now = datetime.utcnow()
        tr = TradeRecord(
            symbol='BTC/USDT', direction=1,
            entry_price=50000, exit_price=51000,
            size=0.01, pnl=10, pnl_pct=2.0,
            setup_type='pullback', close_reason='tp1',
            opened_at=now - timedelta(minutes=30),
            closed_at=now,
            fees=0.5, funding_fees=0.1, net_pnl=9.4,
        )
        assert tr.net_pnl == 9.4
        assert tr.close_reason == 'tp1'


class TestCandle:
    def test_candle_fields(self):
        c = Candle(
            ts=datetime.utcnow(),
            open=100, high=105, low=98, close=103, volume=5000,
        )
        assert c.high > c.low


class TestSymbolSnapshot:
    def test_defaults(self):
        snap = SymbolSnapshot(symbol='BTC/USDT')
        assert snap.regime == 'UNKNOWN'
        assert snap.direction == 0
        assert snap.final_score == 0.0
        assert snap.funding_rate == 0.0
