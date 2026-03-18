"""Tests for BacktestEngine."""

import pytest
from datetime import datetime
from unittest.mock import patch, MagicMock
from typing import List, Dict

from src.core.models import Kline, Signal, TradeRecord
from src.core.enums import Direction, SignalStrategy, FillType
from src.backtest.engine import BacktestEngine, BacktestResult, BacktestPosition

from conftest import generate_klines, generate_trending_klines


# ────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────

def _make_backtest_data(
    symbols: List[str],
    n_1h: int = 300,
    n_15m: int = 1200,
    trend: float = 0.1,
) -> Dict[str, Dict[str, List[Kline]]]:
    """Build data dict for backtest engine."""
    data = {}
    for sym in symbols:
        data[sym] = {
            "1h": generate_klines(
                n=n_1h, start_price=100, trend=trend, volatility=0.3,
                interval_ms=3_600_000,
                start_ts=1_700_000_000_000,
            ),
            "15m": generate_klines(
                n=n_15m, start_price=100, trend=trend * 0.25, volatility=0.15,
                interval_ms=900_000,
                start_ts=1_700_000_000_000,
            ),
        }
    return data


# ────────────────────────────────────────────
# BacktestResult dataclass
# ────────────────────────────────────────────

class TestBacktestResult:

    def test_default_values(self):
        result = BacktestResult()
        assert result.total_return_pct == 0
        assert result.max_drawdown_pct == 0
        assert result.win_rate == 0
        assert result.total_trades == 0
        assert result.trades == []
        assert result.equity_curve == []

    def test_fields_writable(self):
        result = BacktestResult()
        result.total_return_pct = 15.5
        result.win_rate = 0.65
        assert result.total_return_pct == 15.5
        assert result.win_rate == 0.65


# ────────────────────────────────────────────
# BacktestPosition
# ────────────────────────────────────────────

class TestBacktestPosition:

    def test_creation(self):
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.LONG,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=1.0,
            margin=10.0,
            stop_loss=95.0,
            initial_stop=95.0,
            take_profits=[(110.0, 0.5), (120.0, 0.5)],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        assert pos.remaining_pct == 1.0
        assert pos.tp1_hit is False


# ────────────────────────────────────────────
# Engine initialization
# ────────────────────────────────────────────

class TestBacktestEngineInit:

    def test_creates_engine(self, default_config):
        engine = BacktestEngine(default_config)
        assert engine is not None
        assert engine.TAKER_FEE == 0.0004
        assert engine.MAKER_FEE == 0.0002
        assert engine.SLIPPAGE == 0.00015


# ────────────────────────────────────────────
# PNL calculation
# ────────────────────────────────────────────

class TestPnlCalculation:

    def test_long_profit(self, default_config):
        engine = BacktestEngine(default_config)
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.LONG,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=10.0,
            margin=100.0,
            stop_loss=95.0,
            initial_stop=95.0,
            take_profits=[(110.0, 0.5)],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        pnl = engine._calc_pnl(pos, 110.0, "tp1")
        # raw: (110 - 100) * 10 = 100
        # fee: (100*10 + 110*10) * 0.0004 = 0.84
        expected = 100 - (100 * 10 + 110 * 10) * 0.0004
        assert pnl == pytest.approx(expected, rel=1e-6)

    def test_long_loss(self, default_config):
        engine = BacktestEngine(default_config)
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.LONG,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=10.0,
            margin=100.0,
            stop_loss=95.0,
            initial_stop=95.0,
            take_profits=[],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        pnl = engine._calc_pnl(pos, 95.0, "stop_loss")
        assert pnl < 0

    def test_short_profit(self, default_config):
        engine = BacktestEngine(default_config)
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.SHORT,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=10.0,
            margin=100.0,
            stop_loss=105.0,
            initial_stop=105.0,
            take_profits=[(90.0, 0.5)],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        pnl = engine._calc_pnl(pos, 90.0, "tp1")
        assert pnl > 0

    def test_partial_close(self, default_config):
        engine = BacktestEngine(default_config)
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.LONG,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=10.0,
            margin=100.0,
            stop_loss=95.0,
            initial_stop=95.0,
            take_profits=[(110.0, 0.5)],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        pnl_full = engine._calc_pnl(pos, 110.0, "tp1", close_pct=1.0)
        pnl_half = engine._calc_pnl(pos, 110.0, "tp1", close_pct=0.5)
        assert pnl_half == pytest.approx(pnl_full * 0.5, rel=1e-6)


# ────────────────────────────────────────────
# Trade record generation
# ────────────────────────────────────────────

class TestMakeRecord:

    def test_creates_trade_record(self, default_config):
        engine = BacktestEngine(default_config)
        pos = BacktestPosition(
            symbol="BTC/USDT:USDT",
            direction=Direction.LONG,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=100.0,
            quantity=10.0,
            margin=100.0,
            stop_loss=95.0,
            initial_stop=95.0,
            take_profits=[],
            best_price=100.0,
            open_time=datetime(2024, 1, 1),
        )
        ts = datetime(2024, 1, 2)
        record = engine._make_record(pos, 95.0, -50.0, "stop_loss", ts)

        assert isinstance(record, TradeRecord)
        assert record.symbol == "BTC/USDT:USDT"
        assert record.direction == Direction.LONG
        assert record.entry_price == 100.0
        assert record.exit_price == 95.0
        assert record.close_reason == "stop_loss"
        assert record.fill_type == FillType.MARKET


# ────────────────────────────────────────────
# Full run
# ────────────────────────────────────────────

class TestBacktestRun:

    def test_empty_data_returns_default_result(self, default_config):
        engine = BacktestEngine(default_config)
        result = engine.run({}, "2024-01-01", "2024-02-01")
        assert isinstance(result, BacktestResult)
        assert result.total_trades == 0

    def test_run_returns_backtest_result(self, default_config):
        engine = BacktestEngine(default_config)
        data = _make_backtest_data(["BTC/USDT:USDT"], n_1h=300, n_15m=1200)
        result = engine.run(data, "2023-11-15", "2023-12-25")

        assert isinstance(result, BacktestResult)
        assert isinstance(result.equity_curve, list)
        assert len(result.equity_curve) > 0

    def test_drawdown_non_negative(self, default_config):
        engine = BacktestEngine(default_config)
        data = _make_backtest_data(["BTC/USDT:USDT"])
        result = engine.run(data, "2023-11-15", "2023-12-25")
        assert result.max_drawdown_pct >= 0

    def test_win_rate_bounded(self, default_config):
        engine = BacktestEngine(default_config)
        data = _make_backtest_data(["BTC/USDT:USDT"])
        result = engine.run(data, "2023-11-15", "2023-12-25")
        assert 0 <= result.win_rate <= 1
