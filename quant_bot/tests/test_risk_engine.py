"""Tests for app/risk/risk_engine.py"""
import pytest
import time
from unittest.mock import patch
from app.risk.risk_engine import RiskEngine


class TestCanOpenNewPositions:
    def test_can_open_when_clean(self):
        engine = RiskEngine()
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is True
        assert reason == "ok"

    def test_blocked_by_daily_loss_limit(self):
        engine = RiskEngine()
        engine.daily_pnl = -70  # -3.5% of 2000
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is False
        assert reason == "daily_loss_limit"

    def test_blocked_by_5_consecutive_losses(self):
        engine = RiskEngine()
        engine.consecutive_losses = 5
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is False
        assert reason == "5_consecutive_losses"

    def test_blocked_by_cooldown(self):
        engine = RiskEngine()
        engine.cooldown_until = time.time() + 600  # 10 min from now
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is False
        assert "cooldown" in reason

    def test_blocked_by_max_positions(self, make_position):
        engine = RiskEngine()
        engine.positions = [make_position(), make_position(), make_position()]
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is False
        assert reason == "max_positions"

    def test_blocked_by_profit_stop(self):
        engine = RiskEngine()
        engine.daily_pnl = 100  # +5% of 2000
        ok, reason = engine.can_open_new_positions(2000)
        assert ok is False
        assert reason == "profit_stop"


class TestApproveOrder:
    def test_approve_clean_order(self):
        engine = RiskEngine()
        order = {'direction': 1, 'margin': 50, 'notional': 500}
        ok, reason = engine.approve_order(order, 2000)
        assert ok is True
        assert reason == "approved"

    def test_reject_same_direction_max(self, make_position):
        engine = RiskEngine()
        # 3 long positions already
        engine.positions = [
            make_position(direction=1),
            make_position(direction=1),
            make_position(direction=1),
        ]
        # Need max_positions > 3 to pass that check
        engine._exec_cfg = {**engine._exec_cfg, 'max_positions': 10}
        order = {'direction': 1, 'margin': 50, 'notional': 500}
        ok, reason = engine.approve_order(order, 2000)
        assert ok is False
        assert reason == "max_same_direction_positions"

    def test_approve_opposite_direction(self, make_position):
        engine = RiskEngine()
        # Use small margins to stay within risk limits
        engine.positions = [
            make_position(direction=1, margin=10),
            make_position(direction=1, margin=10),
            make_position(direction=1, margin=10),
        ]
        engine._exec_cfg = {**engine._exec_cfg, 'max_positions': 10}
        order = {'direction': -1, 'margin': 10, 'notional': 100}
        ok, reason = engine.approve_order(order, 2000)
        # Direction -1 has 0 positions, should pass direction check
        assert ok is True

    def test_reject_max_notional(self):
        engine = RiskEngine()
        # notional > 30% of equity = 600
        order = {'direction': 1, 'margin': 10, 'notional': 700}
        ok, reason = engine.approve_order(order, 2000)
        assert ok is False
        assert reason == "max_notional_exceeded"

    def test_reject_total_margin_90pct(self, make_position):
        engine = RiskEngine()
        # Set risk limits high so only 90% margin check triggers
        big_pos = make_position(margin=1700, direction=-1)
        engine.positions = [big_pos]
        engine._exec_cfg = {
            **engine._exec_cfg,
            'max_positions': 10,
            'max_same_direction_risk': 0.99,
            'max_total_risk': 0.99,
        }
        order = {'direction': 1, 'margin': 200, 'notional': 500}
        ok, reason = engine.approve_order(order, 2000)
        assert ok is False
        assert reason == "total_margin_90pct_exceeded"


class TestRecordTradeResult:
    def test_record_win(self):
        engine = RiskEngine()
        engine.record_trade_result(50, 2000)
        assert engine.daily_pnl == 50
        assert engine.today_wins == 1
        assert engine.consecutive_losses == 0

    def test_record_loss(self):
        engine = RiskEngine()
        engine.record_trade_result(-20, 2000)
        assert engine.daily_pnl == -20
        assert engine.today_losses == 1
        assert engine.consecutive_losses == 1

    def test_3_losses_triggers_cooldown(self):
        engine = RiskEngine()
        engine.record_trade_result(-10, 2000)
        engine.record_trade_result(-10, 2000)
        engine.record_trade_result(-10, 2000)
        assert engine.consecutive_losses == 3
        assert engine.cooldown_until > time.time()

    def test_win_resets_consecutive_losses(self):
        engine = RiskEngine()
        engine.record_trade_result(-10, 2000)
        engine.record_trade_result(-10, 2000)
        engine.record_trade_result(30, 2000)
        assert engine.consecutive_losses == 0
        assert engine.today_wins == 1
        assert engine.today_losses == 2


class TestGetAllowedGrade:
    def test_default_all_grades(self):
        engine = RiskEngine()
        assert engine.get_allowed_grade() == 'C'

    def test_profit_guard_only_a(self):
        engine = RiskEngine()
        engine.daily_pnl = 60  # 3% of 2000
        engine.daily_pnl_pct = 0.03
        assert engine.get_allowed_grade() == 'A'


class TestGetStatus:
    def test_status_dict(self):
        engine = RiskEngine()
        status = engine.get_status()
        assert 'daily_pnl' in status
        assert 'consecutive_losses' in status
        assert 'cooldown_active' in status
        assert status['positions_count'] == 0
