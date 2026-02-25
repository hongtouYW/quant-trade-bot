"""Billing API - View and manage billing periods and commissions"""
from flask import Blueprint, jsonify, request

from ..middleware.auth_middleware import agent_required, admin_required, get_current_user_id
from ..models.billing import BillingPeriod
from ..services.billing_service import (
    get_or_create_period, close_billing_period, calculate_period_pnl,
    approve_billing_period, mark_period_paid, get_admin_revenue_summary,
)

billing_bp = Blueprint('billing', __name__)


# ─── Agent endpoints (/api/agent/billing/*) ──────────────────

@billing_bp.route('/current', methods=['GET'])
@agent_required
def current_period():
    """Get or create current billing period with live PnL."""
    agent_id = get_current_user_id()
    period = get_or_create_period(agent_id)
    result = period.to_dict()

    # Add live PnL data
    pnl_data = calculate_period_pnl(
        agent_id, period.period_start, period.period_end
    )
    result['live_pnl'] = round(pnl_data['gross_pnl'], 2)
    result['live_trade_count'] = pnl_data['trade_count']
    result['live_win_count'] = pnl_data['win_count']

    # Calculate projected commission
    starting = float(period.starting_capital or 0)
    hwm = float(period.high_water_mark or starting)
    ending = starting + pnl_data['gross_pnl']
    if ending > hwm:
        share_pct = float(period.profit_share_pct or 20) / 100
        result['projected_commission'] = round((ending - hwm) * share_pct, 2)
    else:
        result['projected_commission'] = 0

    return jsonify(result)


@billing_bp.route('/history', methods=['GET'])
@agent_required
def billing_history():
    """Get all billing periods for the agent."""
    agent_id = get_current_user_id()
    periods = (
        BillingPeriod.query
        .filter_by(agent_id=agent_id)
        .order_by(BillingPeriod.period_start.desc())
        .all()
    )
    return jsonify({'periods': [p.to_dict() for p in periods]})


# ─── Admin billing endpoints ─────────────────────────────────
# These are registered under /api/admin via the admin blueprint

billing_admin_bp = Blueprint('billing_admin', __name__)


@billing_admin_bp.route('/revenue', methods=['GET'])
@admin_required
def admin_revenue():
    """Admin: revenue dashboard with per-agent breakdown."""
    admin_id = get_current_user_id()
    summary = get_admin_revenue_summary(admin_id)
    return jsonify(summary)


@billing_admin_bp.route('/periods', methods=['GET'])
@admin_required
def admin_all_periods():
    """Admin: view all billing periods across agents."""
    admin_id = get_current_user_id()
    status_filter = request.args.get('status')

    from ..models.agent import Agent
    query = (
        BillingPeriod.query
        .join(Agent)
        .filter(Agent.admin_id == admin_id)
    )
    if status_filter:
        query = query.filter(BillingPeriod.status == status_filter)

    periods = query.order_by(BillingPeriod.period_end.desc()).all()

    result = []
    for p in periods:
        d = p.to_dict()
        d['agent_username'] = p.agent.username if p.agent else None
        result.append(d)

    return jsonify({'periods': result})


@billing_admin_bp.route('/periods/<int:agent_id>/close', methods=['POST'])
@admin_required
def admin_close_period(agent_id):
    """Admin: close current billing period for an agent."""
    result = close_billing_period(agent_id)
    if 'error' in result:
        return jsonify(result), 400
    return jsonify(result)


@billing_admin_bp.route('/periods/<int:period_id>/approve', methods=['POST'])
@admin_required
def admin_approve_period(period_id):
    """Admin: approve a pending billing period."""
    result = approve_billing_period(period_id)
    if 'error' in result:
        return jsonify(result), 400
    return jsonify(result)


@billing_admin_bp.route('/periods/<int:period_id>/paid', methods=['POST'])
@admin_required
def admin_mark_paid(period_id):
    """Admin: mark a billing period as paid."""
    result = mark_period_paid(period_id)
    if 'error' in result:
        return jsonify(result), 400
    return jsonify(result)
