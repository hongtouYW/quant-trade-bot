"""
风控模块测试 - FR-030 ~ FR-034, BR-001, BR-005, BR-008
"""
import pytest
from datetime import datetime, timezone, timedelta
from risk.position_risk import (
    calculate_position_size, validate_leverage, get_stop_loss_price,
)
from risk.circuit_breaker import CircuitBreakerEngine
from risk.black_swan import BlackSwanMonitor
from risk.risk_manager import RiskManager


class TestPositionSize:

    def test_default_300(self):
        """默认仓位 300U"""
        assert calculate_position_size(is_weekend=False) == 300

    def test_br005_cap(self):
        """BR-005: 不超过 300U"""
        assert calculate_position_size(base_margin=500, is_weekend=False) == 300

    def test_br008_weekend_half(self):
        """BR-008: 周末减半"""
        size = calculate_position_size(is_weekend=True)
        assert size == 150

    def test_consecutive_3_half(self):
        """FR-031: 连亏 3 笔减半"""
        size = calculate_position_size(is_weekend=False, consecutive_losses=3)
        assert size == 150

    def test_weekend_plus_consecutive(self):
        """周末 + 连亏 3 = 双重减半"""
        size = calculate_position_size(is_weekend=True, consecutive_losses=3)
        assert size == 75

    def test_consecutive_2_no_reduction(self):
        """连亏 2 笔不减半"""
        size = calculate_position_size(is_weekend=False, consecutive_losses=2)
        assert size == 300


class TestValidateLeverage:

    def test_btc_50x_pass(self):
        valid, max_lev, _ = validate_leverage("BTCUSDT", 50)
        assert valid is True
        assert max_lev == 50

    def test_btc_51x_fail(self):
        valid, max_lev, _ = validate_leverage("BTCUSDT", 51)
        assert valid is False

    def test_sol_30x_pass(self):
        valid, max_lev, _ = validate_leverage("SOLUSDT", 30)
        assert valid is True

    def test_sol_31x_fail(self):
        valid, max_lev, _ = validate_leverage("SOLUSDT", 31)
        assert valid is False

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
        """BTC 50x, -5% ROI → 价格 -0.1%"""
        sl = get_stop_loss_price(60000, 'long', 'BTCUSDT')
        assert sl < 60000
        assert sl == pytest.approx(60000 * (1 - 0.05/50), rel=1e-6)

    def test_btc_short_stop(self):
        """BTC 50x, -5% ROI → 价格 +0.1%"""
        sl = get_stop_loss_price(60000, 'short', 'BTCUSDT')
        assert sl > 60000

    def test_sol_long_stop(self):
        """SOL 30x, -8% ROI"""
        sl = get_stop_loss_price(150, 'long', 'SOLUSDT')
        expected = 150 * (1 - 0.08/30)
        assert sl == pytest.approx(expected, rel=1e-6)


class TestCircuitBreaker:

    def setup_method(self):
        self.cb = CircuitBreakerEngine()

    def test_no_breaker_initially(self):
        active, _ = self.cb.is_active()
        assert active is False

    def test_consecutive_5_pauses(self):
        """连亏 5 笔 → 暂停 4h"""
        now = datetime.now(timezone.utc)
        for i in range(5):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=30-i))
        active, reason = self.cb.is_active(['consecutive_loss'])
        assert active is True
        assert "consecutive_loss" in reason

    def test_consecutive_3_no_pause(self):
        """连亏 3 笔 → 不暂停 (只减仓)"""
        now = datetime.now(timezone.utc)
        for i in range(3):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=10-i))
        active, _ = self.cb.is_active(['consecutive_loss'])
        assert active is False  # 3 笔只减仓不暂停

    def test_consecutive_reset_on_win(self):
        """连亏后 1 胜清零"""
        now = datetime.now(timezone.utc)
        for i in range(4):
            self.cb.record_trade(-30, f"SYM{i}", now - timedelta(minutes=20-i))
        self.cb.record_trade(50, "WINX", now)  # 1 胜
        losses = self.cb.get_consecutive_losses()
        assert losses == 0

    def test_symbol_cooldown(self):
        """FR-034: 同币冷却 2h"""
        self.cb.record_trade(-30, "BTCUSDT", datetime.now(timezone.utc))
        cooled, remaining = self.cb.is_symbol_cooled("BTCUSDT")
        assert cooled is True
        assert remaining > 0

    def test_different_symbol_not_cooled(self):
        """不同币种不受冷却影响"""
        self.cb.record_trade(-30, "BTCUSDT", datetime.now(timezone.utc))
        cooled, _ = self.cb.is_symbol_cooled("ETHUSDT")
        assert cooled is False

    def test_cooldown_expired(self):
        """冷却期过后可交易"""
        old_time = datetime.now(timezone.utc) - timedelta(hours=3)
        self.cb.record_trade(-30, "BTCUSDT", old_time)
        cooled, _ = self.cb.is_symbol_cooled("BTCUSDT")
        assert cooled is False

    def test_manual_activate(self):
        """手动激活"""
        self.cb.activate('manual', 2, 'test')
        active, _ = self.cb.is_active(['manual'])
        assert active is True

    def test_manual_deactivate(self):
        """手动停用"""
        self.cb.activate('manual', 2, 'test')
        self.cb.deactivate('manual')
        active, _ = self.cb.is_active(['manual'])
        assert active is False


class TestBlackSwan:

    def setup_method(self):
        self.bs = BlackSwanMonitor()

    def test_normal_volatility(self):
        """正常波幅 → 不触发"""
        triggered = self.bs.check_volatility("BTCUSDT", 60500, 60000)
        assert triggered is False
        assert self.bs.is_active() is False

    def test_49_pct_no_trigger(self):
        """4.9% 波幅 → 不触发"""
        triggered = self.bs.check_volatility("BTCUSDT", 60000 * 1.049, 60000)
        assert triggered is False

    def test_5_pct_triggers(self):
        """5% 波幅 → 触发"""
        triggered = self.bs.check_volatility("BTCUSDT", 63000, 60000)
        assert triggered is True
        assert self.bs.is_active() is True

    def test_active_then_expires(self):
        """触发后检查状态"""
        self.bs.check_volatility("BTCUSDT", 63000, 60000)
        status = self.bs.get_status()
        assert status['active'] is True
        assert status['trigger_event']['symbol'] == 'BTCUSDT'

    def test_force_deactivate(self):
        """手动解除"""
        self.bs.check_volatility("BTCUSDT", 63000, 60000)
        self.bs.force_deactivate()
        assert self.bs.is_active() is False

    def test_zero_price_no_crash(self):
        """价格为 0 不崩溃"""
        triggered = self.bs.check_volatility("BTCUSDT", 0, 0)
        assert triggered is False


class TestRiskManager:

    def setup_method(self):
        self.rm = RiskManager()
        self.rm.update_positions(0, set())

    def test_normal_signal_approved(self):
        """正常信号 → 通过"""
        result = self.rm.check({'symbol': 'BTCUSDT', 'direction': 'long'})
        assert result.approved is True
        assert result.leverage == 50
        assert result.position_size == 300

    def test_max_positions_reached(self):
        """持仓满 5 笔 → 拒绝"""
        self.rm.update_positions(5, {'A', 'B', 'C', 'D', 'E'})
        result = self.rm.check({'symbol': 'BTCUSDT'})
        assert result.approved is False
        assert "max_positions" in result.reason

    def test_symbol_already_open(self):
        """同币已持仓 → 拒绝"""
        self.rm.update_positions(1, {'BTCUSDT'})
        result = self.rm.check({'symbol': 'BTCUSDT'})
        assert result.approved is False
        assert "already_open" in result.reason

    def test_sol_leverage_30(self):
        """SOL → 30x"""
        result = self.rm.check({'symbol': 'SOLUSDT'})
        assert result.leverage == 30
        assert result.stop_loss_roi == -0.08
