"""Tests for BillingService - High Water Mark profit sharing."""
import pytest
from datetime import datetime, timezone, date
from decimal import Decimal
from app.services.billing_service import (
    get_or_create_period,
    close_billing_period,
    approve_billing_period,
    mark_period_paid,
)
from app.models.billing import BillingPeriod
from app.models.trade import Trade
from app.extensions import db


class TestGetOrCreatePeriod:

    def test_creates_new_period(self, app_ctx, agent):
        """Should create a new billing period when none exists."""
        period = get_or_create_period(agent.id)
        assert period is not None
        assert period.agent_id == agent.id
        assert period.status == 'open'
        assert period.starting_capital == Decimal('2000')
        assert period.high_water_mark == Decimal('2000')

    def test_returns_existing_open_period(self, app_ctx, agent):
        """Should return existing open period, not create a new one."""
        p1 = get_or_create_period(agent.id)
        p2 = get_or_create_period(agent.id)
        assert p1.id == p2.id

    def test_inherits_from_previous_period(self, app_ctx, agent):
        """New period should inherit HWM and ending capital from previous."""
        # Create and close a period manually
        period = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            starting_capital=Decimal('2000'),
            ending_capital=Decimal('2500'),
            high_water_mark=Decimal('2500'),
            profit_share_pct=Decimal('20'),
            status='paid',
        )
        db.session.add(period)
        db.session.commit()

        new_period = get_or_create_period(agent.id)
        assert float(new_period.starting_capital) == 2500.0
        assert float(new_period.high_water_mark) == 2500.0

    def test_uses_agent_profit_share_pct(self, app_ctx, agent):
        """Period should use agent's profit share percentage."""
        period = get_or_create_period(agent.id)
        assert float(period.profit_share_pct) == 20.0


class TestCloseBillingPeriod:

    def test_close_with_no_open_period(self, app_ctx, agent):
        """Should return error when no open period exists."""
        result = close_billing_period(agent.id)
        assert 'error' in result

    def test_close_with_profit_above_hwm(self, app_ctx, agent):
        """Profit above HWM should generate commission."""
        period = get_or_create_period(agent.id)

        # Add a winning trade within the period
        t = Trade(
            agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
            entry_price=Decimal('50000'), exit_price=Decimal('52000'),
            amount=Decimal('200'), leverage=3,
            entry_time=datetime(2026, 2, 15, tzinfo=timezone.utc),
            exit_time=datetime(2026, 2, 16, tzinfo=timezone.utc),
            status='CLOSED', pnl=Decimal('500'),
        )
        db.session.add(t)
        db.session.commit()

        result = close_billing_period(agent.id)
        assert 'error' not in result
        assert result['gross_pnl'] == 500.0
        assert result['ending_capital'] == 2500.0
        # Profit above HWM (2000) = 500, 20% = 100
        assert result['commission'] == 100.0
        assert result['high_water_mark'] == 2500.0

    def test_close_with_loss_no_commission(self, app_ctx, agent):
        """Losses should not generate commission."""
        period = get_or_create_period(agent.id)

        t = Trade(
            agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
            entry_price=Decimal('50000'), exit_price=Decimal('49000'),
            amount=Decimal('200'), leverage=3,
            entry_time=datetime(2026, 2, 15, tzinfo=timezone.utc),
            exit_time=datetime(2026, 2, 16, tzinfo=timezone.utc),
            status='CLOSED', pnl=Decimal('-200'),
        )
        db.session.add(t)
        db.session.commit()

        result = close_billing_period(agent.id)
        assert result['commission'] == 0.0
        assert result['ending_capital'] == 1800.0
        # HWM should stay at 2000 (original)
        assert result['high_water_mark'] == 2000.0

    def test_hwm_recovery_no_commission(self, app_ctx, agent):
        """Recovery up to HWM should not charge commission."""
        # Set up previous period with HWM of 2500
        prev = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            starting_capital=Decimal('2000'),
            ending_capital=Decimal('2200'),  # Lost from HWM of 2500
            high_water_mark=Decimal('2500'),
            profit_share_pct=Decimal('20'),
            status='paid',
        )
        db.session.add(prev)
        db.session.commit()

        # New period starts at 2200, HWM=2500
        period = get_or_create_period(agent.id)
        assert float(period.starting_capital) == 2200.0
        assert float(period.high_water_mark) == 2500.0

        # Agent makes 200 profit (recovers to 2400, still below HWM of 2500)
        t = Trade(
            agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
            entry_price=Decimal('50000'), exit_price=Decimal('51000'),
            amount=Decimal('200'), leverage=3,
            entry_time=datetime(2026, 2, 15, tzinfo=timezone.utc),
            exit_time=datetime(2026, 2, 16, tzinfo=timezone.utc),
            status='CLOSED', pnl=Decimal('200'),
        )
        db.session.add(t)
        db.session.commit()

        result = close_billing_period(agent.id)
        # Ending = 2200 + 200 = 2400, still below HWM of 2500
        assert result['ending_capital'] == 2400.0
        assert result['commission'] == 0.0
        assert result['high_water_mark'] == 2500.0  # Unchanged

    def test_partial_above_hwm_commission(self, app_ctx, agent):
        """Only profit above HWM should be charged."""
        prev = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            starting_capital=Decimal('2000'),
            ending_capital=Decimal('2200'),
            high_water_mark=Decimal('2500'),
            profit_share_pct=Decimal('20'),
            status='paid',
        )
        db.session.add(prev)
        db.session.commit()

        period = get_or_create_period(agent.id)

        # Agent makes 500 profit: 2200 -> 2700, only 200 above HWM of 2500
        t = Trade(
            agent_id=agent.id, symbol='BTC/USDT', direction='LONG',
            entry_price=Decimal('50000'), exit_price=Decimal('52000'),
            amount=Decimal('200'), leverage=3,
            entry_time=datetime(2026, 2, 15, tzinfo=timezone.utc),
            exit_time=datetime(2026, 2, 16, tzinfo=timezone.utc),
            status='CLOSED', pnl=Decimal('500'),
        )
        db.session.add(t)
        db.session.commit()

        result = close_billing_period(agent.id)
        assert result['ending_capital'] == 2700.0
        assert result['profit_above_hwm'] == 200.0  # 2700 - 2500
        assert result['commission'] == 40.0  # 200 * 20%
        assert result['high_water_mark'] == 2700.0


class TestApproveBillingPeriod:

    def test_approve_pending(self, app_ctx, agent):
        """Should approve a pending period."""
        period = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            starting_capital=Decimal('2000'),
            ending_capital=Decimal('2500'),
            high_water_mark=Decimal('2500'),
            commission_amount=Decimal('100'),
            profit_share_pct=Decimal('20'),
            status='pending',
        )
        db.session.add(period)
        db.session.commit()

        result = approve_billing_period(period.id)
        assert 'message' in result
        assert BillingPeriod.query.get(period.id).status == 'approved'

    def test_approve_non_pending_fails(self, app_ctx, agent):
        """Should not approve non-pending period."""
        period = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            status='open',
            profit_share_pct=Decimal('20'),
        )
        db.session.add(period)
        db.session.commit()

        result = approve_billing_period(period.id)
        assert 'error' in result

    def test_approve_nonexistent(self, app_ctx):
        """Should error on nonexistent period."""
        result = approve_billing_period(99999)
        assert 'error' in result


class TestMarkPaid:

    def test_mark_approved_as_paid(self, app_ctx, agent):
        """Should mark approved period as paid."""
        period = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            status='approved',
            profit_share_pct=Decimal('20'),
        )
        db.session.add(period)
        db.session.commit()

        result = mark_period_paid(period.id)
        assert 'message' in result
        assert BillingPeriod.query.get(period.id).status == 'paid'

    def test_cannot_mark_open_as_paid(self, app_ctx, agent):
        """Cannot mark an open period as paid."""
        period = BillingPeriod(
            agent_id=agent.id,
            period_start=date(2026, 1, 1),
            period_end=date(2026, 1, 31),
            status='open',
            profit_share_pct=Decimal('20'),
        )
        db.session.add(period)
        db.session.commit()

        result = mark_period_paid(period.id)
        assert 'error' in result
