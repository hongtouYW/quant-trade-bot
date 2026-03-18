"""Tests for app/risk/position_sizer.py"""
import pytest
from app.risk.position_sizer import PositionSizer


class TestBuildPlan:
    def test_basic_long_plan(self, make_signal):
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)  # stop_dist = 1000
        plan = sizer.build_plan(signal, 2000)

        assert plan is not None
        assert plan['symbol'] == 'BTC/USDT'
        assert plan['direction'] == 1
        assert plan['side'] == 'buy'
        assert plan['entry'] == 50000
        assert plan['stop'] == 49000
        assert plan['size'] > 0
        assert plan['notional'] > 0
        assert plan['margin'] > 0
        assert plan['risk_amount'] > 0

    def test_basic_short_plan(self, make_signal):
        sizer = PositionSizer()
        signal = make_signal(direction=-1, entry=50000, stop=51000,
                             tp1=48500, tp2=47200)
        plan = sizer.build_plan(signal, 2000)

        assert plan is not None
        assert plan['direction'] == -1
        assert plan['side'] == 'sell'

    def test_risk_amount_correct(self, make_signal):
        """risk_amount = equity * risk_pct (0.4%)"""
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)
        plan = sizer.build_plan(signal, 2000)

        assert plan['risk_amount'] == pytest.approx(2000 * 0.004)

    def test_ranging_halves_risk(self, make_signal):
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)

        plan_trend = sizer.build_plan(signal, 2000, regime='TRENDING')
        plan_range = sizer.build_plan(signal, 2000, regime='RANGING')

        assert plan_range['risk_amount'] == pytest.approx(plan_trend['risk_amount'] / 2)

    def test_zero_stop_distance_returns_none(self, make_signal):
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=50000)
        plan = sizer.build_plan(signal, 2000)
        assert plan is None

    def test_tiny_equity_returns_none(self, make_signal):
        """Very small equity → notional < 5 USDT minimum"""
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)
        plan = sizer.build_plan(signal, 1)  # 1 USDT equity
        assert plan is None

    def test_notional_sizing(self, make_signal):
        """notional = risk_amount / (stop_dist / entry)"""
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)
        plan = sizer.build_plan(signal, 2000)

        risk = 2000 * 0.004  # 8 USDT
        stop_pct = 1000 / 50000  # 2%
        expected_notional = risk / stop_pct  # 400 USDT
        assert plan['notional'] == pytest.approx(expected_notional, rel=0.01)

    def test_margin_uses_leverage(self, make_signal):
        """margin = notional / leverage (10x)"""
        sizer = PositionSizer()
        signal = make_signal(entry=50000, stop=49000)
        plan = sizer.build_plan(signal, 2000)

        assert plan['margin'] == pytest.approx(plan['notional'] / 10, rel=0.01)
