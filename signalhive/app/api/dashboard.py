"""Dashboard routes (Jinja2 rendered pages)."""
from flask import Blueprint, render_template
from flask_login import login_required, current_user
from ..models.signal import Signal
from ..models.trade import SignalTrade
from ..models.channel import Channel
from ..models.strategy import Strategy
from datetime import datetime

dashboard_bp = Blueprint('dashboard', __name__)


@dashboard_bp.route('/')
@login_required
def index():
    """Main dashboard: top signals, active trades, channel health."""
    # Active signals (top scored, not expired)
    signals = Signal.query.filter(
        Signal.status == 'active',
    ).order_by(Signal.final_score.desc()).limit(20).all()

    # Expire stale signals on the fly
    now = datetime.utcnow()
    for s in signals:
        if s.expires_at and now > s.expires_at:
            s.status = 'expired'

    active_signals = [s for s in signals if s.status == 'active']

    # Active trades for current user
    open_trades = SignalTrade.query.filter_by(
        user_id=current_user.id, status='open'
    ).order_by(SignalTrade.opened_at.desc()).limit(20).all()

    # Recent closed trades
    closed_trades = SignalTrade.query.filter_by(
        user_id=current_user.id, status='closed'
    ).order_by(SignalTrade.closed_at.desc()).limit(10).all()

    # Channels
    channels = Channel.query.filter_by(user_id=current_user.id).all()

    # Stats
    total_trades = SignalTrade.query.filter_by(user_id=current_user.id, status='closed').count()
    winning = SignalTrade.query.filter(
        SignalTrade.user_id == current_user.id,
        SignalTrade.status == 'closed',
        SignalTrade.pnl > 0
    ).count()
    winrate = round(winning / total_trades * 100, 1) if total_trades > 0 else 0

    from sqlalchemy import func
    total_pnl_row = SignalTrade.query.with_entities(
        func.sum(SignalTrade.pnl)
    ).filter_by(user_id=current_user.id, status='closed').first()
    total_pnl = float(total_pnl_row[0]) if total_pnl_row[0] else 0

    return render_template('dashboard.html',
        signals=active_signals,
        open_trades=open_trades,
        closed_trades=closed_trades,
        channels=channels,
        total_trades=total_trades,
        winrate=winrate,
        total_pnl=total_pnl,
        now=now,
    )


@dashboard_bp.route('/channels')
@login_required
def channels_page():
    """Channel management page."""
    channels = Channel.query.filter_by(user_id=current_user.id).order_by(Channel.created_at.desc()).all()
    return render_template('channels.html', channels=channels)


@dashboard_bp.route('/signals')
@login_required
def signals_page():
    """Signals list page."""
    page = int(request_args_get('page', 1))
    status = request_args_get('status', '')
    coin = request_args_get('coin', '')

    query = Signal.query
    if status:
        query = query.filter_by(status=status)
    if coin:
        query = query.filter_by(coin=coin.upper())

    signals = query.order_by(Signal.created_at.desc()).limit(100).all()
    now = datetime.utcnow()
    return render_template('signals.html', signals=signals, now=now)


@dashboard_bp.route('/trades')
@login_required
def trades_page():
    """Trade history page."""
    trades = SignalTrade.query.filter_by(
        user_id=current_user.id
    ).order_by(SignalTrade.opened_at.desc()).limit(100).all()

    # Performance stats
    closed = [t for t in trades if t.status == 'closed']
    total = len(closed)
    wins = sum(1 for t in closed if t.pnl and float(t.pnl) > 0)
    losses = sum(1 for t in closed if t.pnl and float(t.pnl) < 0)
    winrate = round(wins / total * 100, 1) if total > 0 else 0
    total_pnl = sum(float(t.pnl) for t in closed if t.pnl) if closed else 0
    avg_roi = sum(float(t.roi) for t in closed if t.roi) / total if total > 0 else 0

    return render_template('trades.html',
        trades=trades,
        total=total,
        wins=wins,
        losses=losses,
        winrate=winrate,
        total_pnl=total_pnl,
        avg_roi=avg_roi,
    )


@dashboard_bp.route('/strategies')
@login_required
def strategies_page():
    """Strategy management page."""
    strategies = Strategy.query.filter_by(
        user_id=current_user.id
    ).order_by(Strategy.created_at.desc()).all()
    channels = Channel.query.filter_by(user_id=current_user.id).all()
    return render_template('strategies.html', strategies=strategies, channels=channels)


def request_args_get(key, default=''):
    from flask import request
    return request.args.get(key, default)
