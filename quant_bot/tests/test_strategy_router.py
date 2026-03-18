"""Tests for app/strategy/strategy_router.py"""
import pytest
from unittest.mock import MagicMock
from app.strategy.strategy_router import StrategyRouter
from app.models.market import Signal
from datetime import datetime, timedelta


def _make_signal(setup_type, score, direction=1, symbol='BTC/USDT'):
    return Signal(
        symbol=symbol, direction=direction,
        setup_type=setup_type, score=score,
        entry_price=50000, stop_loss=49000,
        tp1=51500, tp2=52800, risk_reward=1.5,
        expires_at=datetime.utcnow() + timedelta(minutes=3),
    )


class TestStrategyRouter:
    def test_trend_only_in_trending(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = _make_signal('pullback', 80)

        router = StrategyRouter(sig_engine)
        result = router.find_best_setup('BTC/USDT', 1, {}, 'TRENDING')

        assert result is not None
        assert result.setup_type == 'pullback'
        sig_engine.find_setup.assert_called_once()

    def test_trend_also_in_ranging(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = _make_signal('pullback', 70)

        router = StrategyRouter(sig_engine)
        result = router.find_best_setup('BTC/USDT', 1, {}, 'RANGING')

        assert result is not None
        sig_engine.find_setup.assert_called_once()

    def test_no_trend_in_extreme(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = _make_signal('pullback', 80)

        router = StrategyRouter(sig_engine)
        result = router.find_best_setup('BTC/USDT', 1, {}, 'EXTREME')

        # EXTREME not in ('TRENDING', 'RANGING') → trend not called
        sig_engine.find_setup.assert_not_called()
        assert result is None

    def test_mean_reversion_only_ranging(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = None

        mr_engine = MagicMock()
        mr_engine.find_setup.return_value = _make_signal('mean_reversion', 65)

        router = StrategyRouter(sig_engine, mean_reversion=mr_engine)

        # RANGING → MR should be called
        result = router.find_best_setup('BTC/USDT', 1, {}, 'RANGING')
        assert result is not None
        assert result.setup_type == 'mean_reversion'
        mr_engine.find_setup.assert_called_once()

    def test_mean_reversion_not_in_trending(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = None

        mr_engine = MagicMock()
        mr_engine.find_setup.return_value = _make_signal('mean_reversion', 65)

        router = StrategyRouter(sig_engine, mean_reversion=mr_engine)

        result = router.find_best_setup('BTC/USDT', 1, {}, 'TRENDING')
        mr_engine.find_setup.assert_not_called()
        assert result is None

    def test_funding_arb_any_regime(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = None

        fa_engine = MagicMock()
        fa_engine.find_setup.return_value = _make_signal('funding_arb', 50)

        router = StrategyRouter(sig_engine, funding_arb=fa_engine)

        for regime in ['TRENDING', 'RANGING', 'EXTREME']:
            fa_engine.reset_mock()
            result = router.find_best_setup('BTC/USDT', 1, {}, regime)
            fa_engine.find_setup.assert_called_once()
            if regime == 'EXTREME':
                # Only funding arb available
                assert result is not None
                assert result.setup_type == 'funding_arb'

    def test_best_score_wins(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = _make_signal('pullback', 70)

        mr_engine = MagicMock()
        mr_engine.find_setup.return_value = _make_signal('mean_reversion', 85)

        fa_engine = MagicMock()
        fa_engine.find_setup.return_value = _make_signal('funding_arb', 50)

        router = StrategyRouter(sig_engine, mr_engine, fa_engine)
        result = router.find_best_setup('BTC/USDT', 1, {}, 'RANGING')

        assert result.setup_type == 'mean_reversion'
        assert result.score == 85

    def test_no_signals_returns_none(self):
        sig_engine = MagicMock()
        sig_engine.find_setup.return_value = None

        router = StrategyRouter(sig_engine)
        result = router.find_best_setup('BTC/USDT', 1, {}, 'TRENDING')
        assert result is None
