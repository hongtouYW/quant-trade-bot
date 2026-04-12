"""
风控模块测试 - 检讨后更新 (统一 20x + -10% ROI)
"""
import pytest
from datetime import datetime, timezone, timedelta
from risk.position_risk import (
    calculate_position_size, validate_leverage, get_stop_loss_price,
)
from risk.circuit_breaker import CircuitBreakerEngine
from risk.black_swan import BlackSwanMonitor


class TestPositionSize:

    def test_default_300(self):
        assert calculate_position_size(is_weekend=False) == 300

    def test_br005_cap(self):
        assert calculate_position_size(base_margin=500, is_weekend=False) == 300

    def test_br008_weekend_half(self):
        assert calculate_position_size(is_weekend=True) == 150

    def test_consecutive_3_half(self):
        assert calculate_position_size(is_weekend=False, consecutive_losses=3) == 150

    def test_weekend_plus_consecutive(self):
        assert calculate_position_size(is_weekend=True, consecutive_losses=3) == 75

    def test_consecutive_2_no_reduction(self):
        assert calculate_position_size(is_weekend=False, consecutive_losses=2) == 300


class TestValidateLeverage:

    def test_btc_20x_pass(self):
        valid, max_lev, _ = validate_leverage("BTCUSDT", 20)
        assert valid is True
        assert max_lev == 20

    def test_btc_21x_fail(self):
        valid, max_lev, _ = validate_leverage("BTCUSDT", 21)
        assert valid is False

    def test_sol_20x_pass(self):
        valid, max_lev, _ = validate_leverage("SOLUSDT", 20)
        assert valid is True

    def test_midcap_20x_pass(self):
        valid, max_lev, _ = validate_leverage("ARBUSDT", 20)
        assert valid is True

    def test_zero_leverage_fail(self):
        valid, _, _ = validate_leverage("BTCUSDT", 0)
        assert valid is False

    def test_negative_leverage_fail(self):
        valid, _, _ = validate_leverage("BTCUSDT", -5)
        assert valid is False


class TestStopLossPrice:

    def test_btc_long_stop(self):
        """BTC 20x, -10% ROI → 价格 -0.5%"""
        sl = get_stop_loss_price(60000, 'long', 'BTCUSDT')
        assert sl < 60000
        assert sl == pytest.approx(60000 * (1 - 0.10/20), rel=1e-6)

    def test_btc_short_stop(self):
        sl = get_stop_loss_price(60000, 'short', 'BTCUSDT')
        assert sl > 60000

    def test_sol_long_stop(self):
        """SOL 20x, -10% ROI"""
        sl = get_stop_loss_price(150, 'long', 'SOLUSDT')
        expected = 150 * (1 - 0.10/20)
        assert sl == pytest.approx(expected, rel=1e-6)


class TestCircuitBreaker:

    def setup_method(self):
        self.cb = CircuitBreakerEngine()

    def test_no_breaker_initially(self):
        active, _ = self.cb.is_active()
        assert active is False

    def test_consecutive_5_pauses(self):
        now = datetime.now(timezone.utc)
        for i in range(5):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=30-i))
        active, reason = self.cb.is_active(['consecutive_loss'])
        assert active is True

    def test_consecutive_3_no_pause(self):
        now = datetime.now(timezone.utc)
        for i in range(3):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=10-i))
        active, _ = self.cb.is_active(['consecutive_loss'])
        assert active is False

    def test_consecutive_reset_on_win(self):
        now = datetime.now(timezone.utc)
        for i in range(4):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=20-i))
        self.cb.record_trade(50, "WINX", now)
        assert self.cb.get_consecutive_losses() == 0

    def test_symbol_cooldown(self):
        self.cb.record_trade(-30, "BTCUSDT", datetime.now(timezone.utc))
        cooled, remaining = self.cb.is_symbol_cooled("BTCUSDT")
        assert cooled is True

    def test_different_symbol_not_cooled(self):
        self.cb.record_trade(-30, "BTCUSDT", datetime.now(timezone.utc))
        cooled, _ = self.cb.is_symbol_cooled("ETHUSDT")
        assert cooled is False

    def test_cooldown_expired(self):
        old = datetime.now(timezone.utc) - timedelta(hours=3)
        self.cb.record_trade(-30, "BTCUSDT", old)
        cooled, _ = self.cb.is_symbol_cooled("BTCUSDT")
        assert cooled is False

    def test_manual_activate(self):
        self.cb.activate('manual', 2, 'test')
        active, _ = self.cb.is_active(['manual'])
        assert active is True

    def test_manual_deactivate(self):
        self.cb.activate('manual', 2, 'test')
        self.cb.deactivate('manual')
        active, _ = self.cb.is_active(['manual'])
        assert active is False


class TestBlackSwan:

    def setup_method(self):
        self.bs = BlackSwanMonitor()

    def test_normal_volatility(self):
        assert self.bs.check_volatility("BTCUSDT", 60500, 60000) is False

    def test_5_pct_triggers(self):
        assert self.bs.check_volatility("BTCUSDT", 63000, 60000) is True
        assert self.bs.is_active() is True

    def test_force_deactivate(self):
        self.bs.check_volatility("BTCUSDT", 63000, 60000)
        self.bs.force_deactivate()
        assert self.bs.is_active() is False

    def test_zero_price_no_crash(self):
        assert self.bs.check_volatility("BTCUSDT", 0, 0) is False
