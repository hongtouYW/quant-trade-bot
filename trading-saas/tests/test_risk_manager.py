"""Tests for RiskManager."""
import pytest
from datetime import datetime, timezone
from decimal import Decimal
from app.engine.risk_manager import RiskManager
from app.models.trade import Trade
from app.extensions import db


class TestRiskLevel:
    """Test get_risk_level() pure function."""

    def test_low_risk(self):
        rm = RiskManager(1, {'initial_capital': 2000})
        level, mult, pause = rm.get_risk_level(2)
        assert level == 'LOW'
        assert mult == 1.0
        assert pause is False

    def test_medium_risk(self):
        rm = RiskManager(1, {'initial_capital': 2000})
        level, mult, pause = rm.get_risk_level(5)
        assert level == 'MEDIUM'
        assert mult == 0.5
        assert pause is False

    def test_high_risk(self):
        rm = RiskManager(1, {'initial_capital': 2000})
        level, mult, pause = rm.get_risk_level(7)
        assert level == 'HIGH'
        assert mult == 0.3
        assert pause is True

    def test_critical_risk(self):
        rm = RiskManager(1, {'initial_capital': 2000})
        level, mult, pause = rm.get_risk_level(9)
        assert level == 'CRITICAL'
        assert mult == 0.0
        assert pause is True

    def test_zero_risk(self):
        rm = RiskManager(1, {'initial_capital': 2000})
        level, mult, pause = rm.get_risk_level(0)
        assert level == 'LOW'
        assert mult == 1.0


class TestRiskMetrics:
    """Test calculate_risk_metrics() with DB trades."""

    def test_no_trades_no_positions(self, app_ctx, agent):
        """With no trades and no positions, risk should be 0."""
        rm = RiskManager(agent.id, {'initial_capital': 2000})
        metrics = rm.calculate_risk_metrics([])
        assert metrics['risk_score'] == 0
        assert metrics['current_capital'] == 2000
        assert metrics['position_count'] == 0
        assert metrics['consecutive_losses'] == 0

    def test_with_positions(self, app_ctx, agent):
        """Positions should be counted."""
        rm = RiskManager(agent.id, {'initial_capital': 2000})
        positions = [
            {'direction': 'LONG', 'amount': 100, 'leverage': 3},
            {'direction': 'SHORT', 'amount': 150, 'leverage': 2},
        ]
        metrics = rm.calculate_risk_metrics(positions)
        assert metrics['position_count'] == 2
        assert metrics['long_ratio'] == 50.0
        assert metrics['short_ratio'] == 50.0

    def test_consecutive_losses(self, app_ctx, agent, db):
        """Consecutive losses should be tracked."""
        # Add 4 losing trades
        for i in range(4):
            t = Trade(
                agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
                entry_price=Decimal('50000'), exit_price=Decimal('49000'),
                amount=Decimal('100'), leverage=3,
                entry_time=datetime(2026, 2, 1, i, tzinfo=timezone.utc),
                exit_time=datetime(2026, 2, 1, i + 1, tzinfo=timezone.utc),
                status='CLOSED', pnl=Decimal('-50'),
            )
            db.session.add(t)
        db.session.commit()

        rm = RiskManager(agent.id, {'initial_capital': 2000})
        metrics = rm.calculate_risk_metrics([])
        assert metrics['consecutive_losses'] == 4
        # 4 losses >= 3 -> +2 risk from consecutive losses
        assert metrics['risk_score'] >= 2

    def test_drawdown_calculation(self, app_ctx, agent, db):
        """Should calculate drawdown correctly."""
        trades = [
            (100,),   # capital: 2100, peak: 2100
            (100,),   # capital: 2200, peak: 2200
            (-300,),  # capital: 1900, peak: 2200 -> drawdown = 13.6%
        ]
        for i, (pnl,) in enumerate(trades):
            t = Trade(
                agent_id=agent.id, symbol='ETH/USDT', direction='LONG',
                entry_price=Decimal('3000'), exit_price=Decimal('3100'),
                amount=Decimal('100'), leverage=3,
                entry_time=datetime(2026, 2, 1, i, tzinfo=timezone.utc),
                exit_time=datetime(2026, 2, 1, i + 1, tzinfo=timezone.utc),
                status='CLOSED', pnl=Decimal(str(pnl)),
            )
            db.session.add(t)
        db.session.commit()

        rm = RiskManager(agent.id, {'initial_capital': 2000})
        metrics = rm.calculate_risk_metrics([])
        assert metrics['peak_capital'] == 2200
        assert metrics['current_capital'] == 1900
        assert metrics['current_drawdown'] == pytest.approx(13.6, abs=0.5)

    def test_all_long_positions_imbalance(self, app_ctx, agent):
        """100% long positions with 3+ positions should raise risk."""
        positions = [
            {'direction': 'LONG', 'amount': 100, 'leverage': 3},
            {'direction': 'LONG', 'amount': 100, 'leverage': 3},
            {'direction': 'LONG', 'amount': 100, 'leverage': 3},
        ]
        rm = RiskManager(agent.id, {'initial_capital': 2000})
        metrics = rm.calculate_risk_metrics(positions)
        assert metrics['long_ratio'] == 100.0
        # 100% > 85% -> +2 from direction imbalance
        assert metrics['risk_score'] >= 2

    def test_high_leverage_risk(self, app_ctx, agent):
        """Average leverage > 3 should add risk."""
        positions = [
            {'direction': 'LONG', 'amount': 100, 'leverage': 5},
            {'direction': 'SHORT', 'amount': 100, 'leverage': 4},
        ]
        rm = RiskManager(agent.id, {'initial_capital': 2000})
        metrics = rm.calculate_risk_metrics(positions)
        assert metrics['avg_leverage'] == 4.5
        # avg_leverage > 3 -> +1 risk
        assert metrics['risk_score'] >= 1


class TestCheckCanOpen:

    def test_max_positions_reached(self, app_ctx, agent):
        """Should reject when max positions reached."""
        positions = [{'direction': 'LONG', 'amount': 100, 'leverage': 3}] * 15
        rm = RiskManager(agent.id, {'initial_capital': 2000, 'max_positions': 15})
        allowed, reason = rm.check_can_open(positions)
        assert allowed is False
        assert 'Max positions' in reason

    def test_normal_conditions_allowed(self, app_ctx, agent):
        """Should allow when conditions are normal."""
        rm = RiskManager(agent.id, {'initial_capital': 2000, 'max_positions': 15})
        allowed, reason = rm.check_can_open([])
        assert allowed is True
        assert 'OK' in reason

    def test_high_risk_blocks(self, app_ctx, agent, db):
        """Should block when risk is HIGH (score >= 7)."""
        # Add enough losing trades to trigger drawdown breach
        for i in range(10):
            t = Trade(
                agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
                entry_price=Decimal('50000'), exit_price=Decimal('49000'),
                amount=Decimal('200'), leverage=3,
                entry_time=datetime(2026, 2, 1, i, tzinfo=timezone.utc),
                exit_time=datetime(2026, 2, 1, i + 1, tzinfo=timezone.utc),
                status='CLOSED', pnl=Decimal('-100'),
            )
            db.session.add(t)
        db.session.commit()

        rm = RiskManager(agent.id, {
            'initial_capital': 2000, 'max_positions': 15,
            'max_drawdown_pct': 20, 'daily_loss_limit': 200,
        })
        metrics = rm.calculate_risk_metrics([])
        # 1000 loss on 2000 capital = 50% drawdown, well above 20% max
        assert metrics['drawdown_breach'] is True
