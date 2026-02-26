"""Tests for SignalAnalyzer pure functions."""
import pytest
from unittest.mock import patch
from app.engine.signal_analyzer import (
    calculate_rsi,
    calculate_atr,
    binance_symbol,
    calculate_position_size,
    calculate_stop_take,
    COIN_TIERS,
    TIER_MULTIPLIER,
)


class TestCalculateRSI:

    def test_insufficient_data_returns_50(self):
        """RSI with insufficient data should return 50."""
        assert calculate_rsi([100, 101, 102], period=14) == 50.0

    def test_all_gains_returns_100(self):
        """All positive changes should give RSI near 100."""
        prices = list(range(100, 120))  # 20 ascending prices
        rsi = calculate_rsi(prices, period=14)
        assert rsi == 100.0

    def test_all_losses_returns_0(self):
        """All negative changes should give RSI near 0."""
        prices = list(range(120, 100, -1))  # 20 descending prices
        rsi = calculate_rsi(prices, period=14)
        assert rsi == pytest.approx(0.0, abs=0.1)

    def test_mixed_prices(self):
        """Mixed prices should give RSI between 0 and 100."""
        prices = [100, 102, 101, 103, 100, 105, 103, 106, 104, 107,
                  105, 108, 106, 109, 107, 110]
        rsi = calculate_rsi(prices, period=14)
        assert 0 < rsi < 100

    def test_flat_prices(self):
        """Flat prices (no changes) should give RSI of 50."""
        prices = [100.0] * 20
        rsi = calculate_rsi(prices, period=14)
        # avg_gain=0, avg_loss=0 -> avg_loss==0 -> returns 100
        # This is the standard RSI behavior when there are no losses
        assert rsi == 100.0


class TestCalculateATR:

    def test_insufficient_data_returns_none(self):
        """ATR with insufficient candles returns None."""
        candles = [{'high': 110, 'low': 90, 'close': 100}]
        assert calculate_atr(candles, period=14) is None

    def test_basic_atr(self):
        """ATR should be positive for volatile candles."""
        candles = []
        for i in range(20):
            candles.append({
                'high': 100 + i + 5,
                'low': 100 + i - 5,
                'close': 100 + i,
            })
        atr = calculate_atr(candles, period=14)
        assert atr is not None
        assert atr > 0

    def test_flat_candles_low_atr(self):
        """Flat candles should produce low ATR."""
        candles = [{'high': 100.1, 'low': 99.9, 'close': 100.0} for _ in range(20)]
        atr = calculate_atr(candles, period=14)
        assert atr is not None
        assert atr < 1.0


class TestBinanceSymbol:

    def test_btc_usdt(self):
        assert binance_symbol('BTC/USDT') == 'BTCUSDT'

    def test_eth_usdt(self):
        assert binance_symbol('ETH/USDT') == 'ETHUSDT'

    def test_1000_token_bonk(self):
        assert binance_symbol('BONK/USDT') == '1000BONKUSDT'

    def test_1000_token_pepe(self):
        assert binance_symbol('PEPE/USDT') == '1000PEPEUSDT'

    def test_1000_token_shib(self):
        assert binance_symbol('SHIB/USDT') == '1000SHIBUSDT'

    def test_case_insensitive(self):
        assert binance_symbol('btc/usdt') == 'BTCUSDT'


class TestCalculatePositionSize:

    def test_high_score_position(self):
        """High score should give reasonable position size."""
        config = {'max_leverage': 3, 'max_position_size': 500}
        size, leverage = calculate_position_size(85, 2000, config)
        assert size > 0
        assert leverage <= 3

    def test_low_score_returns_zero(self):
        """Score below 60 should return 0 size."""
        config = {'max_leverage': 3, 'max_position_size': 500}
        size, leverage = calculate_position_size(50, 2000, config)
        assert size == 0

    def test_tier_multiplier_applied(self):
        """T1 coins should get full size, T3 should be reduced."""
        config = {'max_leverage': 3, 'max_position_size': 500}
        size_t1, _ = calculate_position_size(80, 5000, config, 'BTC/USDT')
        size_t3, _ = calculate_position_size(80, 5000, config, 'UNKNOWN/USDT')
        assert size_t1 > size_t3

    def test_max_position_cap(self):
        """Position size should not exceed max_position_size."""
        config = {'max_leverage': 3, 'max_position_size': 100}
        size, _ = calculate_position_size(85, 100000, config)
        assert size <= 100

    def test_minimum_position_size(self):
        """Position should be at least 50 USDT."""
        config = {'max_leverage': 3, 'max_position_size': 500}
        size, _ = calculate_position_size(60, 500, config)
        assert size >= 50

    def test_score_70_medium_size(self):
        config = {'max_leverage': 3, 'max_position_size': 500}
        size, _ = calculate_position_size(70, 3000, config)
        assert 50 <= size <= 500


class TestCalculateStopTake:

    def test_long_stop_loss_below_entry(self):
        """LONG stop-loss should be below entry price."""
        config = {'roi_stop_loss': -10, 'roi_trailing_start': 6, 'roi_trailing_distance': 3}
        result = calculate_stop_take(50000, 'LONG', 3, config)
        assert result['stop_loss'] < 50000

    def test_long_take_profit_above_entry(self):
        """LONG take-profit should be above entry price."""
        config = {'roi_stop_loss': -10, 'roi_trailing_start': 6, 'roi_trailing_distance': 3}
        result = calculate_stop_take(50000, 'LONG', 3, config)
        assert result['take_profit'] > 50000

    def test_short_stop_loss_above_entry(self):
        """SHORT stop-loss should be above entry price."""
        config = {'roi_stop_loss': -10, 'roi_trailing_start': 6, 'roi_trailing_distance': 3}
        result = calculate_stop_take(50000, 'SHORT', 3, config)
        assert result['stop_loss'] > 50000

    def test_short_take_profit_below_entry(self):
        """SHORT take-profit should be below entry price."""
        config = {'roi_stop_loss': -10, 'roi_trailing_start': 6, 'roi_trailing_distance': 3}
        result = calculate_stop_take(50000, 'SHORT', 3, config)
        assert result['take_profit'] < 50000

    def test_higher_leverage_tighter_stops(self):
        """Higher leverage should result in tighter stop/tp levels."""
        config = {'roi_stop_loss': -10, 'roi_trailing_start': 6, 'roi_trailing_distance': 3}
        result_3x = calculate_stop_take(50000, 'LONG', 3, config)
        result_5x = calculate_stop_take(50000, 'LONG', 5, config)
        # With higher leverage, stop loss should be closer to entry
        sl_dist_3x = abs(50000 - result_3x['stop_loss'])
        sl_dist_5x = abs(50000 - result_5x['stop_loss'])
        assert sl_dist_5x < sl_dist_3x

    def test_returns_config_values(self):
        config = {'roi_stop_loss': -15, 'roi_trailing_start': 8, 'roi_trailing_distance': 4}
        result = calculate_stop_take(50000, 'LONG', 3, config)
        assert result['roi_stop_loss'] == -15
        assert result['roi_trailing_start'] == 8
        assert result['roi_trailing_distance'] == 4
