"""Billing Service - High Water Mark profit sharing.

Implements the billing model:
- Monthly billing periods
- Commissions only charged on NEW profits above the high water mark
- If agent loses money and recovers, recovery up to HWM is free

Example:
  Month 1: 2000 -> 2500, profit=500, commission=100 (20%), HWM=2500
  Month 2: 2500 -> 2300, profit=0, commission=0, HWM=2500
  Month 3: 2300 -> 2700, profit above HWM=200, commission=40, HWM=2700
"""
from datetime import datetime, date, timezone, timedelta
from decimal import Decimal
from calendar import monthrange

from ..extensions import db
from ..models.billing import BillingPeriod
from ..models.trade import Trade
from ..models.agent import Agent
from ..models.agent_config import AgentTradingConfig


def get_or_create_period(agent_id: int) -> BillingPeriod:
    """Get the current open billing period, or create one if none exists."""
    period = BillingPeriod.query.filter_by(
        agent_id=agent_id, status='open'
    ).first()

    if period:
        return period

    # Create new period for current month
    today = date.today()
    month_start = today.replace(day=1)
    _, last_day = monthrange(today.year, today.month)
    month_end = today.replace(day=last_day)

    # Get agent config for initial capital
    tc = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    initial_capital = float(tc.initial_capital) if tc else 2000

    # Determine starting capital and HWM from previous period
    prev_period = (
        BillingPeriod.query
        .filter_by(agent_id=agent_id)
        .filter(BillingPeriod.status != 'open')
        .order_by(BillingPeriod.id.desc())
        .first()
    )

    if prev_period:
        starting_capital = float(prev_period.ending_capital or initial_capital)
        hwm = float(prev_period.high_water_mark or starting_capital)
    else:
        starting_capital = initial_capital
        hwm = initial_capital

    # Get agent's profit share percentage
    agent = Agent.query.get(agent_id)
    share_pct = float(agent.profit_share_pct) if agent else 20.0

    period = BillingPeriod(
        agent_id=agent_id,
        period_start=month_start,
        period_end=month_end,
        starting_capital=Decimal(str(starting_capital)),
        high_water_mark=Decimal(str(hwm)),
        profit_share_pct=Decimal(str(share_pct)),
        status='open',
    )
    db.session.add(period)
    db.session.commit()
    return period


def calculate_period_pnl(agent_id: int, period_start: date, period_end: date) -> dict:
    """Calculate total PnL for a billing period from closed trades."""
    trades = Trade.query.filter(
        Trade.agent_id == agent_id,
        Trade.status == 'CLOSED',
        Trade.exit_time >= datetime.combine(period_start, datetime.min.time()),
        Trade.exit_time <= datetime.combine(period_end, datetime.max.time()),
    ).all()

    gross_pnl = sum(float(t.pnl) for t in trades if t.pnl)
    total_fees = sum(
        float(t.fee or 0) + float(t.funding_fee or 0) for t in trades
    )
    trade_count = len(trades)
    win_count = sum(1 for t in trades if t.pnl and float(t.pnl) > 0)

    return {
        'gross_pnl': gross_pnl,
        'total_fees': total_fees,
        'trade_count': trade_count,
        'win_count': win_count,
    }


def close_billing_period(agent_id: int) -> dict:
    """Close the current billing period and calculate commission.

    Returns:
        Dict with period summary including commission amount.
    """
    period = BillingPeriod.query.filter_by(
        agent_id=agent_id, status='open'
    ).first()

    if not period:
        return {'error': 'No open billing period'}

    # Calculate PnL for this period
    pnl_data = calculate_period_pnl(
        agent_id, period.period_start, period.period_end
    )

    starting = float(period.starting_capital or 0)
    hwm = float(period.high_water_mark or starting)
    gross_pnl = pnl_data['gross_pnl']
    ending = starting + gross_pnl

    # High water mark commission calculation
    commission = 0.0
    new_hwm = hwm

    if ending > hwm:
        # Only charge on profit above HWM
        profit_above_hwm = ending - hwm
        share_pct = float(period.profit_share_pct or 20) / 100
        commission = profit_above_hwm * share_pct
        new_hwm = ending

    # Update period
    period.ending_capital = Decimal(str(round(ending, 2)))
    period.gross_pnl = Decimal(str(round(gross_pnl, 4)))
    period.total_fees = Decimal(str(round(pnl_data['total_fees'], 6)))
    period.high_water_mark = Decimal(str(round(new_hwm, 2)))
    period.commission_amount = Decimal(str(round(commission, 4)))
    period.status = 'pending'  # Awaiting admin approval
    db.session.commit()

    return {
        'period_id': period.id,
        'period_start': period.period_start.isoformat(),
        'period_end': period.period_end.isoformat(),
        'starting_capital': starting,
        'ending_capital': ending,
        'gross_pnl': gross_pnl,
        'high_water_mark': new_hwm,
        'prev_hwm': hwm,
        'profit_above_hwm': max(0, ending - hwm),
        'profit_share_pct': float(period.profit_share_pct),
        'commission': commission,
        'trade_count': pnl_data['trade_count'],
        'win_count': pnl_data['win_count'],
    }


def approve_billing_period(period_id: int) -> dict:
    """Admin approves a pending billing period."""
    period = BillingPeriod.query.get(period_id)
    if not period:
        return {'error': 'Period not found'}
    if period.status != 'pending':
        return {'error': f'Period status is {period.status}, not pending'}

    period.status = 'approved'
    period.approved_at = datetime.now(timezone.utc)
    db.session.commit()

    return {'message': 'Period approved', 'period_id': period_id}


def mark_period_paid(period_id: int) -> dict:
    """Admin marks billing period as paid."""
    period = BillingPeriod.query.get(period_id)
    if not period:
        return {'error': 'Period not found'}
    if period.status not in ('approved', 'pending'):
        return {'error': f'Period status is {period.status}'}

    period.status = 'paid'
    db.session.commit()

    return {'message': 'Period marked as paid', 'period_id': period_id}


def get_admin_revenue_summary(admin_id: int) -> dict:
    """Get revenue summary for an admin across all their agents."""
    from sqlalchemy import func

    # All agents under this admin
    agents = Agent.query.filter_by(admin_id=admin_id).all()
    agent_ids = [a.id for a in agents]

    if not agent_ids:
        return {
            'total_revenue': 0, 'pending_revenue': 0, 'paid_revenue': 0,
            'agents': [],
        }

    # Revenue by status
    total = db.session.query(
        func.sum(BillingPeriod.commission_amount)
    ).filter(
        BillingPeriod.agent_id.in_(agent_ids)
    ).scalar() or 0

    pending = db.session.query(
        func.sum(BillingPeriod.commission_amount)
    ).filter(
        BillingPeriod.agent_id.in_(agent_ids),
        BillingPeriod.status == 'pending',
    ).scalar() or 0

    paid = db.session.query(
        func.sum(BillingPeriod.commission_amount)
    ).filter(
        BillingPeriod.agent_id.in_(agent_ids),
        BillingPeriod.status == 'paid',
    ).scalar() or 0

    # Per-agent breakdown
    agent_data = []
    for agent in agents:
        agent_total = db.session.query(
            func.sum(BillingPeriod.commission_amount)
        ).filter(
            BillingPeriod.agent_id == agent.id,
        ).scalar() or 0

        agent_pnl = db.session.query(
            func.sum(Trade.pnl)
        ).filter(
            Trade.agent_id == agent.id, Trade.status == 'CLOSED',
        ).scalar() or 0

        agent_data.append({
            'agent_id': agent.id,
            'username': agent.username,
            'display_name': agent.display_name,
            'total_pnl': round(float(agent_pnl), 2),
            'total_commission': round(float(agent_total), 2),
            'profit_share_pct': float(agent.profit_share_pct),
        })

    return {
        'total_revenue': round(float(total), 2),
        'pending_revenue': round(float(pending), 2),
        'paid_revenue': round(float(paid), 2),
        'agents': agent_data,
    }
