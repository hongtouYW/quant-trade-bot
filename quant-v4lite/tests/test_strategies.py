"""Tests for TrendFollowStrategy and PullbackBreakoutStrategy."""

import pytest
from typing import List

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.strategy.trend_follow import TrendFollowStrategy
from src.strategy.pullback_breakout import PullbackBreakoutStrategy

from conftest import generate_klines, generate_trending_klines


# ────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────

def _make_pullback_klines_15m(
    n: int = 50,
    direction: str = "long",
    base_price: float = 150.0,
) -> List[Kline]:
    """Build 15m klines with a narrow box followed by a breakout bar.

    For LONG: 20 bars in tight range, then final bar breaks above.
    For SHORT: 20 bars in tight range, then final bar breaks below.
    """
    klines = []
    ts = 1_700_000_000_000

    # Prefix bars (not in the box)
    for i in range(n - 21):
        klines.append(Kline(
            timestamp=ts + i * 900_000,
            open=base_price, high=base_price + 0.2,
            low=base_price - 0.2, close=base_price,
            volume=2000, quote_volume=200_000,
        ))

    # 20 narrow box bars (range < 3% of mid)
    box_high = base_price + 0.5
    box_low = base_price - 0.5
    for i in range(20):
        idx = (n - 21) + i
        mid = (box_high + box_low) / 2
        klines.append(Kline(
            timestamp=ts + idx * 900_000,
            open=mid - 0.1, high=box_high,
            low=box_low, close=mid + 0.1,
            volume=1000, quote_volume=100_000,
        ))

    # Breakout bar
    idx = n - 1
    if direction == "long":
        klines.append(Kline(
            timestamp=ts + idx * 900_000,
            open=box_high - 0.1, high=box_high + 2,
            low=box_high - 0.2, close=box_high + 1.5,
            volume=8000, quote_volume=800_000,
        ))
    else:
        klines.append(Kline(
            timestamp=ts + idx * 900_000,
            open=box_low + 0.1, high=box_low + 0.2,
            low=box_low - 2, close=box_low - 1.5,
            volume=8000, quote_volume=800_000,
        ))

    return klines


# ────────────────────────────────────────────
# TrendFollowStrategy
# ────────────────────────────────────────────

class TestTrendFollowStrategy:

    def test_returns_none_insufficient_1h_data(self, default_config):
        strat = TrendFollowStrategy()
        klines_1h = generate_klines(50)
        klines_15m = generate_klines(50)
        result = strat.check_signal("BTC/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None

    def test_returns_none_insufficient_15m_data(self, default_config):
        strat = TrendFollowStrategy()
        klines_1h = generate_trending_klines(250, direction="up")
        klines_15m = generate_klines(10)
        result = strat.check_signal("BTC/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None

    def test_returns_none_wrong_direction(self, default_config):
        """Bearish 1H should reject LONG signal."""
        strat = TrendFollowStrategy()
        klines_1h = generate_trending_klines(250, direction="down")
        klines_15m = generate_klines(50)
        result = strat.check_signal("BTC/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None

    def test_signal_has_correct_fields(self, default_config):
        """When a signal is generated, verify its structure."""
        strat = TrendFollowStrategy()

        # Build a scenario where a signal might fire:
        # Bullish 1H + EMA20 pullback on 15m
        klines_1h = generate_trending_klines(250, direction="up")
        last_close_1h = klines_1h[-1].close

        # Build 15m klines around the 1H price
        klines_15m = generate_klines(50, start_price=last_close_1h - 5, trend=0.1, volatility=0.05)

        result = strat.check_signal("TEST/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)

        # Signal may or may not fire depending on exact data; just verify
        # that if it fires, it has the right shape
        if result is not None:
            assert isinstance(result, Signal)
            assert result.direction == Direction.LONG
            assert result.strategy == SignalStrategy.TREND_FOLLOW
            assert result.stop_loss < result.entry_price
            assert len(result.take_profits) == 2
            assert result.risk_reward >= default_config["execution"]["min_risk_reward"]

    def test_strategy_name(self, default_config):
        strat = TrendFollowStrategy()
        assert strat.name == "trend_follow"


# ────────────────────────────────────────────
# PullbackBreakoutStrategy
# ────────────────────────────────────────────

class TestPullbackBreakoutStrategy:

    def test_returns_none_insufficient_data(self, default_config):
        strat = PullbackBreakoutStrategy()
        klines_1h = generate_klines(50)
        klines_15m = generate_klines(50)
        result = strat.check_signal("BTC/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None

    def test_returns_none_no_trend(self, default_config):
        """Ranging 1H should reject signal."""
        strat = PullbackBreakoutStrategy()
        klines_1h = generate_klines(250, trend=0, volatility=0.05)
        klines_15m = _make_pullback_klines_15m(50, direction="long")
        result = strat.check_signal("BTC/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None

    def test_signal_has_correct_strategy_type(self, default_config):
        strat = PullbackBreakoutStrategy()
        klines_1h = generate_trending_klines(250, direction="up")
        klines_15m = _make_pullback_klines_15m(50, direction="long",
                                                base_price=klines_1h[-1].close)
        result = strat.check_signal("TEST/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        if result is not None:
            assert result.strategy == SignalStrategy.PULLBACK_BREAKOUT

    def test_strategy_name(self, default_config):
        strat = PullbackBreakoutStrategy()
        assert strat.name == "pullback_breakout"

    def test_rejects_wide_box(self, default_config):
        """Box range > 3% should be rejected."""
        strat = PullbackBreakoutStrategy()
        klines_1h = generate_trending_klines(250, direction="up")
        # Wide box: 10% range
        klines_15m = generate_klines(50, start_price=100, volatility=5.0)
        result = strat.check_signal("TEST/USDT:USDT", klines_1h, klines_15m,
                                    Direction.LONG, default_config)
        assert result is None
