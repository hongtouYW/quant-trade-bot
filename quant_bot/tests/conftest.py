"""Shared test fixtures for quant_bot tests"""
import sys
import os
import pytest
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from unittest.mock import patch, MagicMock

# Ensure app is importable
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))


# ---------- Config mock ----------

TEST_CONFIG = {
    'account': {
        'initial_balance': 2000,
        'leverage': 10,
        'max_margin_usage_pct': 0.90,
    },
    'execution': {
        'risk_per_trade': 0.004,
        'stop_atr_multiple': 1.2,
        'tp1_r_multiple': 1.5,
        'tp2_r_multiple': 2.8,
        'max_positions': 3,
        'max_same_direction_risk': 0.12,
        'max_total_risk': 0.16,
        'entry_timeout_minutes': 3,
        'max_holding_minutes': 75,
    },
    'risk': {
        'daily_loss_limit_pct': 0.03,
        'profit_guard_mode_pct': 0.025,
        'daily_stop_trading_profit_pct': 0.04,
        'cooldown_after_3_losses_minutes': 30,
        'stop_after_5_losses': True,
    },
    'mean_reversion': {'enable': True},
    'funding_arb': {'enable': True},
    'monitoring': {'telegram_alerts': False},
}


@pytest.fixture(autouse=True)
def mock_config():
    """Set config._cfg directly so all get() calls use test config"""
    import app.config as cfg
    old_cfg = cfg._cfg
    old_sym = cfg._symbols_cfg
    cfg._cfg = TEST_CONFIG
    cfg._symbols_cfg = {'blacklist': [], 'whitelist': [], 'overrides': {}}
    yield
    cfg._cfg = old_cfg
    cfg._symbols_cfg = old_sym


@pytest.fixture(autouse=True)
def mock_notifier():
    """Silence telegram notifications in tests"""
    with patch('app.monitoring.notifier.send_telegram'), \
         patch('app.monitoring.notifier.notify_profit_guard'):
        yield


# ---------- Data fixtures ----------

@pytest.fixture
def sample_ohlcv():
    """Generate 100-bar OHLCV DataFrame with realistic price action"""
    np.random.seed(42)
    n = 100
    base_price = 100.0
    returns = np.random.normal(0.001, 0.02, n)
    prices = base_price * np.cumprod(1 + returns)

    df = pd.DataFrame({
        'timestamp': pd.date_range('2026-01-01', periods=n, freq='15min'),
        'open': prices * (1 + np.random.uniform(-0.005, 0.005, n)),
        'high': prices * (1 + np.random.uniform(0.002, 0.015, n)),
        'low': prices * (1 - np.random.uniform(0.002, 0.015, n)),
        'close': prices,
        'volume': np.random.uniform(1000, 5000, n),
    })
    return df


@pytest.fixture
def trending_up_ohlcv():
    """Strong uptrend OHLCV data"""
    n = 100
    prices = np.linspace(100, 130, n) + np.random.normal(0, 0.5, n)

    df = pd.DataFrame({
        'timestamp': pd.date_range('2026-01-01', periods=n, freq='15min'),
        'open': prices - 0.3,
        'high': prices + 1.0,
        'low': prices - 1.0,
        'close': prices,
        'volume': np.random.uniform(2000, 6000, n),
    })
    return df


@pytest.fixture
def ranging_ohlcv():
    """Ranging/sideways OHLCV data"""
    n = 100
    prices = 100 + 3 * np.sin(np.linspace(0, 6 * np.pi, n)) + np.random.normal(0, 0.3, n)

    df = pd.DataFrame({
        'timestamp': pd.date_range('2026-01-01', periods=n, freq='15min'),
        'open': prices - 0.2,
        'high': prices + 0.8,
        'low': prices - 0.8,
        'close': prices,
        'volume': np.random.uniform(1000, 4000, n),
    })
    return df


@pytest.fixture
def make_signal():
    """Factory for creating Signal objects"""
    from app.models.market import Signal

    def _make(symbol='BTC/USDT', direction=1, entry=50000, stop=49000,
              tp1=51500, tp2=52800, score=80, setup_type='pullback',
              grade='B', risk_reward=1.5):
        return Signal(
            symbol=symbol, direction=direction,
            setup_type=setup_type, score=score,
            entry_price=entry, stop_loss=stop,
            tp1=tp1, tp2=tp2, risk_reward=risk_reward,
            grade=grade,
            expires_at=datetime.utcnow() + timedelta(minutes=3),
        )
    return _make


@pytest.fixture
def make_position():
    """Factory for creating Position objects"""
    from app.models.market import Position

    def _make(symbol='BTC/USDT', direction=1, entry=50000, size=0.01,
              margin=50, stop=49000, tp1=51500, tp2=52800):
        return Position(
            symbol=symbol, direction=direction,
            entry_price=entry, size=size, margin=margin,
            stop_loss=stop, tp1=tp1, tp2=tp2,
            opened_at=datetime.utcnow(),
            strategy_tag='pullback',
        )
    return _make
