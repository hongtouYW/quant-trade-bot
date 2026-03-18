"""Tests for app/indicators/calc.py"""
import pytest
import numpy as np
import pandas as pd
from app.indicators import calc


class TestEMA:
    def test_ema_length(self, sample_ohlcv):
        result = calc.ema(sample_ohlcv['close'], 20)
        assert len(result) == len(sample_ohlcv)

    def test_ema_converges_to_price(self):
        """Constant series → EMA equals the constant"""
        s = pd.Series([50.0] * 30)
        result = calc.ema(s, 10)
        assert result.iloc[-1] == pytest.approx(50.0)

    def test_ema_shorter_reacts_faster(self):
        """Short EMA reacts faster to price jump"""
        s = pd.Series([100.0] * 20 + [120.0] * 5)
        ema5 = calc.ema(s, 5)
        ema20 = calc.ema(s, 20)
        assert ema5.iloc[-1] > ema20.iloc[-1]


class TestSMA:
    def test_sma_simple(self):
        s = pd.Series([1.0, 2.0, 3.0, 4.0, 5.0])
        result = calc.sma(s, 3)
        assert result.iloc[-1] == pytest.approx(4.0)
        assert result.iloc[2] == pytest.approx(2.0)

    def test_sma_nan_before_period(self):
        s = pd.Series([1.0, 2.0, 3.0])
        result = calc.sma(s, 3)
        assert np.isnan(result.iloc[0])
        assert np.isnan(result.iloc[1])


class TestATR:
    def test_atr_positive(self, sample_ohlcv):
        result = calc.atr(sample_ohlcv, 14)
        valid = result.dropna()
        assert len(valid) > 0
        assert (valid > 0).all()

    def test_atr_flat_market(self):
        """Zero volatility → ATR = 0"""
        df = pd.DataFrame({
            'high': [100.0] * 20,
            'low': [100.0] * 20,
            'close': [100.0] * 20,
        })
        result = calc.atr(df, 14)
        assert result.iloc[-1] == pytest.approx(0.0)


class TestATRP:
    def test_atrp_percentage(self, sample_ohlcv):
        result = calc.atrp(sample_ohlcv, 14)
        valid = result.dropna()
        assert (valid > 0).all()
        assert (valid < 50).all()  # Sanity: should be a small percentage


class TestRSI:
    def test_rsi_range(self, sample_ohlcv):
        result = calc.rsi(sample_ohlcv['close'], 14)
        valid = result.dropna()
        assert (valid >= 0).all()
        assert (valid <= 100).all()

    def test_rsi_uptrend(self):
        """Uptrend with dips → RSI elevated"""
        np.random.seed(42)
        # Biased random walk: positive drift with real dips
        s = pd.Series(np.cumsum(np.random.normal(0.5, 1.0, 100)))
        result = calc.rsi(s, 14)
        valid = result.dropna()
        assert len(valid) > 0
        assert valid.iloc[-1] > 50  # Biased up → RSI above neutral

    def test_rsi_all_down(self):
        """Monotonically decreasing → RSI near 0"""
        s = pd.Series(range(50, 1, -1), dtype=float)
        result = calc.rsi(s, 14)
        assert result.iloc[-1] < 10


class TestADX:
    def test_adx_returns_triple(self, sample_ohlcv):
        adx_val, plus_di, minus_di = calc.adx(sample_ohlcv, 14)
        assert len(adx_val) == len(sample_ohlcv)
        assert len(plus_di) == len(sample_ohlcv)
        assert len(minus_di) == len(sample_ohlcv)

    def test_adx_trending_high(self, trending_up_ohlcv):
        """Strong trend → ADX should be elevated"""
        adx_val, _, _ = calc.adx(trending_up_ohlcv, 14)
        assert adx_val.iloc[-1] > 20

    def test_adx_ranging_lower(self, ranging_ohlcv):
        """Ranging market → ADX should be lower"""
        adx_val, _, _ = calc.adx(ranging_ohlcv, 14)
        # Ranging should have lower ADX than strong trend
        adx_trend, _, _ = calc.adx(
            pd.DataFrame({
                'timestamp': pd.date_range('2026-01-01', periods=100, freq='15min'),
                'open': np.linspace(100, 150, 100) - 0.3,
                'high': np.linspace(100, 150, 100) + 1.0,
                'low': np.linspace(100, 150, 100) - 1.0,
                'close': np.linspace(100, 150, 100),
                'volume': np.ones(100) * 3000,
            }), 14)
        assert adx_val.iloc[-1] < adx_trend.iloc[-1]


class TestBollingerBands:
    def test_bb_order(self, sample_ohlcv):
        upper, mid, lower = calc.bollinger_bands(sample_ohlcv['close'], 20, 2)
        valid_idx = ~(np.isnan(upper) | np.isnan(lower))
        assert (upper[valid_idx] >= mid[valid_idx]).all()
        assert (mid[valid_idx] >= lower[valid_idx]).all()

    def test_bb_constant_price(self):
        """Constant price → bands collapse to price"""
        s = pd.Series([100.0] * 30)
        upper, mid, lower = calc.bollinger_bands(s, 20, 2)
        assert upper.iloc[-1] == pytest.approx(100.0)
        assert lower.iloc[-1] == pytest.approx(100.0)


class TestROC:
    def test_roc_calculation(self):
        s = pd.Series([100, 105, 110, 115, 120, 125, 130], dtype=float)
        result = calc.roc(s, 6)
        # ROC(6) for last = (130/100 - 1) * 100 = 30%
        assert result.iloc[-1] == pytest.approx(30.0)


class TestVolumeRatio:
    def test_volume_ratio_above_average(self):
        df = pd.DataFrame({
            'volume': [100.0] * 20 + [300.0],
        })
        result = calc.volume_ratio(df, 20)
        # Last bar volume 300 / avg 100 = 3.0
        # Note: SMA(20) at index 20 includes the 300, so slightly > 100
        assert result.iloc[-1] > 2.0


class TestEMASlope:
    def test_ema_slope_upward(self):
        s = pd.Series([100.0, 101.0, 102.0, 103.0, 104.0])
        slope = calc.ema_slope(s, 3)
        assert slope > 0

    def test_ema_slope_insufficient_data(self):
        s = pd.Series([100.0])
        slope = calc.ema_slope(s, 3)
        assert slope == 0


class TestRecentHighsLows:
    def test_uptrend(self):
        df = pd.DataFrame({
            'high': [100, 101, 102, 103, 104],
            'low': [99, 100, 101, 102, 103],
        })
        assert calc.recent_highs_lows(df, 3) == 1

    def test_downtrend(self):
        df = pd.DataFrame({
            'high': [104, 103, 102, 101, 100],
            'low': [103, 102, 101, 100, 99],
        })
        assert calc.recent_highs_lows(df, 3) == -1

    def test_no_trend(self):
        df = pd.DataFrame({
            'high': [100, 102, 101, 103, 100],
            'low': [99, 101, 100, 102, 99],
        })
        assert calc.recent_highs_lows(df, 3) == 0

    def test_insufficient_data(self):
        df = pd.DataFrame({'high': [100], 'low': [99]})
        assert calc.recent_highs_lows(df, 3) == 0


class TestShadowRatio:
    def test_no_shadow(self):
        """Full body candle → shadow_ratio = 0"""
        row = pd.Series({'open': 100, 'high': 110, 'low': 100, 'close': 110})
        assert calc.shadow_ratio(row) == pytest.approx(0.0)

    def test_all_shadow(self):
        """Doji → shadow_ratio = 1"""
        row = pd.Series({'open': 100, 'high': 110, 'low': 90, 'close': 100})
        assert calc.shadow_ratio(row) == pytest.approx(1.0)

    def test_doji_zero_range(self):
        row = pd.Series({'open': 100, 'high': 100, 'low': 100, 'close': 100})
        assert calc.shadow_ratio(row) == 0


class TestCompressionRange:
    def test_compression_narrow(self):
        df = pd.DataFrame({
            'high': [101.0] * 25,
            'low': [99.0] * 25,
        })
        result = calc.compression_range(df, 20)
        # (101-99) / 100 * 100 = 2%
        assert result == pytest.approx(2.0)

    def test_insufficient_data(self):
        df = pd.DataFrame({'high': [101], 'low': [99]})
        assert calc.compression_range(df, 20) == float('inf')
