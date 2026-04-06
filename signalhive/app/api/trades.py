"""Trade listing and stats API endpoints."""
from flask import Blueprint, request, jsonify
from flask_login import login_required, current_user
from ..models.trade import SignalTrade
from ..extensions import db
from sqlalchemy import func

trades_bp = Blueprint('trades_api', __name__)


@trades_bp.route('', methods=['GET'])
@login_required
def list_trades():
    status = request.args.get('status', '')
    coin = request.args.get('coin', '').upper()
    is_paper = request.args.get('is_paper', '')
    limit = min(request.args.get('limit', 50, type=int), 200)

    query = SignalTrade.query.filter_by(user_id=current_user.id)
    if status:
        query = query.filter_by(status=status)
    if coin:
        query = query.filter_by(coin=coin)
    if is_paper != '':
        query = query.filter_by(is_paper=is_paper.lower() == 'true')

    trades = query.order_by(SignalTrade.opened_at.desc()).limit(limit).all()
    return jsonify([t.to_dict() for t in trades])


@trades_bp.route('/stats', methods=['GET'])
@login_required
def trade_stats():
    """Performance stats: PnL, winrate, drawdown."""
    closed = SignalTrade.query.filter_by(
        user_id=current_user.id, status='closed'
    ).all()

    total = len(closed)
    if total == 0:
        return jsonify({
            'total_trades': 0, 'wins': 0, 'losses': 0,
            'winrate': 0, 'total_pnl': 0, 'avg_roi': 0,
            'max_drawdown': 0, 'best_trade': None, 'worst_trade': None,
        })

    wins = sum(1 for t in closed if t.pnl and float(t.pnl) > 0)
    losses = sum(1 for t in closed if t.pnl and float(t.pnl) < 0)
    total_pnl = sum(float(t.pnl) for t in closed if t.pnl)
    avg_roi = sum(float(t.roi) for t in closed if t.roi) / total

    # Max drawdown
    running_pnl = 0
    peak = 0
    max_dd = 0
    for t in sorted(closed, key=lambda x: x.closed_at or x.opened_at):
        running_pnl += float(t.pnl or 0)
        peak = max(peak, running_pnl)
        dd = peak - running_pnl
        max_dd = max(max_dd, dd)

    best = max(closed, key=lambda t: float(t.pnl or 0))
    worst = min(closed, key=lambda t: float(t.pnl or 0))

    return jsonify({
        'total_trades': total,
        'wins': wins,
        'losses': losses,
        'winrate': round(wins / total * 100, 1),
        'total_pnl': round(total_pnl, 2),
        'avg_roi': round(avg_roi * 100, 2),
        'max_drawdown': round(max_dd, 2),
        'best_trade': best.to_dict(),
        'worst_trade': worst.to_dict(),
    })
