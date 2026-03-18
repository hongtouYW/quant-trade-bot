"""Tests for RiskControl 4-layer checks."""

import pytest
from datetime import datetime, timedelta
from unittest.mock import patch

from src.core.enums import Direction, SignalStrategy
from src.risk.risk_control import RiskControl

from conftest import make_signal


# ────────────────────────────────────────────
# Basic checks
# ────────────────────────────────────────────

class TestRiskControlBasic:

    def test_initial_state_allows_trading(self, default_config):
        rc = RiskControl(default_config, 10000)
        ok, reason = rc.pre_trade_check()
        assert ok is True
        assert reason == "ok"

    def test_on_trade_close_updates_balance(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(100, "BTC/USDT:USDT")
        assert rc._current_balance == 10100

    def test_on_trade_close_tracks_consecutive_losses(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-50, "BTC/USDT:USDT")
        assert rc.consecutive_losses == 1
        rc.on_trade_close(-30, "BTC/USDT:USDT")
        assert rc.consecutive_losses == 2

    def test_win_resets_consecutive_losses(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-50, "BTC/USDT:USDT")
        rc.on_trade_close(-50, "BTC/USDT:USDT")
        assert rc.consecutive_losses == 2
        rc.on_trade_close(100, "BTC/USDT:USDT")
        assert rc.consecutive_losses == 0


# ────────────────────────────────────────────
# Layer 2: Daily limits
# ────────────────────────────────────────────

class TestDailyLimits:

    def test_daily_loss_limit_blocks(self, default_config):
        """Daily loss of -3% should block trading."""
        rc = RiskControl(default_config, 10000)
        # Lose 300 (3% of 10000)
        rc.on_trade_close(-300, "BTC/USDT:USDT")
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "daily_loss_limit" in reason

    def test_daily_loss_just_under_allows(self, default_config):
        """Loss just under -3% should still allow trading."""
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-290, "BTC/USDT:USDT")
        ok, _ = rc.pre_trade_check()
        assert ok is True

    def test_daily_profit_hard_stop(self, default_config):
        """Daily profit of +8% should trigger hard stop."""
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(800, "BTC/USDT:USDT")
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "daily_profit_hard_stop" in reason

    def test_consecutive_loss_streak_triggers_cooldown(self, default_config):
        """5 consecutive losses should trigger cooldown."""
        rc = RiskControl(default_config, 10000)
        for i in range(5):
            rc.on_trade_close(-10, f"SYM{i}/USDT:USDT")

        assert rc.consecutive_losses == 5
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "consecutive_loss_streak" in reason

    def test_cooldown_blocks_subsequent_trades(self, default_config):
        """After cooldown is set, trades should be blocked."""
        rc = RiskControl(default_config, 10000)
        for i in range(5):
            rc.on_trade_close(-10, f"SYM{i}/USDT:USDT")

        # This triggers cooldown
        rc.pre_trade_check()

        # Subsequent check should still be blocked (cooldown)
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "cooldown" in reason


# ────────────────────────────────────────────
# Symbol daily loss
# ────────────────────────────────────────────

class TestSymbolDailyLoss:

    def test_symbol_daily_loss_blocks(self, default_config):
        """Single symbol losing -1% of balance should block that symbol."""
        rc = RiskControl(default_config, 10000)
        # Lose 100 on BTC (1% of 10000)
        rc.on_trade_close(-100, "BTC/USDT:USDT")

        signal = make_signal(symbol="BTC/USDT:USDT")
        ok, reason = rc.pre_trade_check(signal)
        assert ok is False
        assert "symbol_daily_loss" in reason

    def test_symbol_loss_doesnt_block_other_symbol(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-100, "BTC/USDT:USDT")

        signal = make_signal(symbol="ETH/USDT:USDT")
        ok, _ = rc.pre_trade_check(signal)
        assert ok is True


# ────────────────────────────────────────────
# Layer 3: System risk level
# ────────────────────────────────────────────

class TestSystemRisk:

    def test_high_risk_level_blocks(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.set_risk_level(2)
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "system_risk_level" in reason

    def test_normal_risk_level_allows(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.set_risk_level(1)
        ok, _ = rc.pre_trade_check()
        assert ok is True


# ────────────────────────────────────────────
# Layer 4: Account-level
# ────────────────────────────────────────────

class TestAccountLevel:

    def test_total_drawdown_blocks(self, default_config):
        """15% total drawdown should block trading."""
        rc = RiskControl(default_config, 10000)
        rc.update_balance(8500)  # 15% drawdown
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "total_drawdown" in reason

    def test_weekly_loss_blocks(self, default_config):
        """Weekly loss of -8% should block trading."""
        rc = RiskControl(default_config, 10000)
        rc._weekly_pnl = -800
        ok, reason = rc.pre_trade_check()
        assert ok is False
        assert "weekly_loss_limit" in reason


# ────────────────────────────────────────────
# Risk scale
# ────────────────────────────────────────────

class TestRiskScale:

    def test_normal_returns_1(self, default_config):
        rc = RiskControl(default_config, 10000)
        assert rc.get_risk_scale() == 1.0

    def test_daily_loss_2pct_returns_half(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-200, "BTC/USDT:USDT")
        assert rc.get_risk_scale() == 0.3

    def test_daily_loss_1pct_returns_half(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-100, "BTC/USDT:USDT")
        assert rc.get_risk_scale() == 0.5


# ────────────────────────────────────────────
# Reset
# ────────────────────────────────────────────

class TestReset:

    def test_daily_reset_clears_pnl(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc.on_trade_close(-200, "BTC/USDT:USDT")
        rc.daily_reset()
        ok, _ = rc.pre_trade_check()
        assert ok is True

    def test_weekly_reset_clears_weekly_pnl(self, default_config):
        rc = RiskControl(default_config, 10000)
        rc._weekly_pnl = -800
        rc.weekly_reset()
        assert rc._weekly_pnl == 0.0
