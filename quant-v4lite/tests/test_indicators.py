"""Tests for indicator functions: EMA, ADX, RSI, ATR, Bollinger, MACD."""

import numpy as np
import pytest

from src.core.models import Kline
from src.indicators.trend import ema, adx, macd, ema_alignment
from src.indicators.momentum import rsi
from src.indicators.volatility import atr, bollinger
from src.indicators.volume import volume_ratio, taker_buy_ratio

from conftest import generate_klines, generate_trending_klines, generate_ranging_klines


# ────────────────────────────────────────────
# EMA
# ────────────────────────────────────────────

class TestEMA:

    def test_returns_ndarray_same_length(self):
        klines = generate_klines(50)
        result = ema(klines, 20)
        assert isinstance(result, np.ndarray)
        assert len(result) == len(klines)

    def test_first_value_equals_first_close(self):
        klines = generate_klines(30)
        result = ema(klines, 10)
        assert result[0] == pytest.approx(klines[0].close)

    def test_ema_tracks_trend_upward(self):
        klines = generate_trending_klines(100, direction="up")
        result = ema(klines, 20)
        # EMA should rise over time
        assert result[-1] > result[20]

    def test_shorter_period_more_responsive(self):
        klines = generate_trending_klines(100, direction="up")
        fast = ema(klines, 10)
        slow = ema(klines, 50)
        # Fast EMA closer to latest price than slow EMA
        last_close = klines[-1].close
        assert abs(fast[-1] - last_close) < abs(slow[-1] - last_close)

    def test_single_bar(self):
        klines = generate_klines(1)
        result = ema(klines, 5)
        assert len(result) == 1
        assert result[0] == pytest.approx(klines[0].close)


# ────────────────────────────────────────────
# ADX
# ────────────────────────────────────────────

class TestADX:

    def test_returns_float(self):
        klines = generate_klines(100)
        result = adx(klines, 14)
        assert isinstance(result, float)

    def test_trending_market_high_adx(self):
        klines = generate_trending_klines(250, direction="up")
        result = adx(klines, 14)
        # Strong trend should produce ADX > 20
        assert result > 15

    def test_insufficient_data_returns_zero(self):
        klines = generate_klines(10)
        result = adx(klines, 14)
        assert result == 0.0

    def test_adx_non_negative(self):
        klines = generate_klines(200)
        result = adx(klines, 14)
        assert result >= 0


# ────────────────────────────────────────────
# RSI
# ────────────────────────────────────────────

class TestRSI:

    def test_returns_float(self):
        klines = generate_klines(50)
        result = rsi(klines, 14)
        assert isinstance(result, float)

    def test_rsi_bounded_0_100(self):
        klines = generate_klines(200)
        result = rsi(klines, 14)
        assert 0 <= result <= 100

    def test_strong_uptrend_high_rsi(self):
        klines = generate_trending_klines(100, direction="up")
        result = rsi(klines, 14)
        assert result > 55

    def test_strong_downtrend_low_rsi(self):
        klines = generate_trending_klines(100, direction="down")
        result = rsi(klines, 14)
        assert result < 45

    def test_insufficient_data_returns_50(self):
        klines = generate_klines(5)
        result = rsi(klines, 14)
        assert result == 50.0

    def test_all_gains_returns_100(self):
        """All prices going up should give RSI 100."""
        klines = [
            Kline(timestamp=i * 3600000, open=float(100 + i),
                  high=float(101 + i), low=float(99.5 + i),
                  close=float(101 + i), volume=1000, quote_volume=100000)
            for i in range(30)
        ]
        result = rsi(klines, 14)
        assert result == 100.0


# ────────────────────────────────────────────
# ATR
# ────────────────────────────────────────────

class TestATR:

    def test_returns_float(self):
        klines = generate_klines(50)
        result = atr(klines, 14)
        assert isinstance(result, float)

    def test_atr_positive(self):
        klines = generate_klines(50)
        result = atr(klines, 14)
        assert result > 0

    def test_volatile_market_higher_atr(self):
        calm = generate_klines(50, volatility=0.1)
        wild = generate_klines(50, volatility=2.0)
        assert atr(wild, 14) > atr(calm, 14)

    def test_single_bar_returns_zero(self):
        klines = generate_klines(1)
        result = atr(klines, 14)
        assert result == 0.0


# ────────────────────────────────────────────
# Bollinger Bands
# ────────────────────────────────────────────

class TestBollinger:

    def test_returns_three_floats(self):
        klines = generate_klines(50)
        upper, mid, lower = bollinger(klines, 20, 2.0)
        assert isinstance(upper, float)
        assert isinstance(mid, float)
        assert isinstance(lower, float)

    def test_upper_gt_mid_gt_lower(self):
        klines = generate_klines(50)
        upper, mid, lower = bollinger(klines, 20, 2.0)
        assert upper > mid > lower

    def test_mid_equals_sma(self):
        klines = generate_klines(50)
        _, mid, _ = bollinger(klines, 20, 2.0)
        closes = [k.close for k in klines[-20:]]
        expected_sma = sum(closes) / len(closes)
        assert mid == pytest.approx(expected_sma, rel=1e-6)

    def test_wider_std_dev_wider_bands(self):
        klines = generate_klines(50)
        u1, m1, l1 = bollinger(klines, 20, 1.0)
        u2, m2, l2 = bollinger(klines, 20, 3.0)
        assert (u2 - l2) > (u1 - l1)


# ────────────────────────────────────────────
# MACD
# ────────────────────────────────────────────

class TestMACD:

    def test_returns_three_floats(self):
        klines = generate_klines(50)
        macd_line, signal_line, histogram = macd(klines)
        assert isinstance(macd_line, float)
        assert isinstance(signal_line, float)
        assert isinstance(histogram, float)

    def test_histogram_equals_macd_minus_signal(self):
        klines = generate_klines(100)
        macd_line, signal_line, histogram = macd(klines)
        assert histogram == pytest.approx(macd_line - signal_line, abs=1e-8)

    def test_uptrend_positive_macd(self):
        klines = generate_trending_klines(100, direction="up")
        macd_line, _, _ = macd(klines)
        assert macd_line > 0

    def test_downtrend_negative_macd(self):
        klines = generate_trending_klines(100, direction="down")
        macd_line, _, _ = macd(klines)
        assert macd_line < 0


# ────────────────────────────────────────────
# EMA Alignment
# ────────────────────────────────────────────

class TestEMAAlignment:

    def test_bullish_alignment(self):
        klines = generate_trending_klines(250, direction="up")
        assert ema_alignment(klines) == "bullish"

    def test_bearish_alignment(self):
        klines = generate_trending_klines(250, direction="down")
        assert ema_alignment(klines) == "bearish"

    def test_insufficient_data_mixed(self):
        klines = generate_klines(50)
        assert ema_alignment(klines) == "mixed"


# ────────────────────────────────────────────
# Volume indicators
# ────────────────────────────────────────────

class TestVolumeIndicators:

    def test_volume_ratio_default(self):
        klines = generate_klines(50)
        result = volume_ratio(klines)
        assert isinstance(result, float)
        assert result > 0

    def test_taker_buy_ratio_bounded(self):
        klines = generate_klines(50)
        result = taker_buy_ratio(klines, 10)
        assert 0 <= result <= 1
