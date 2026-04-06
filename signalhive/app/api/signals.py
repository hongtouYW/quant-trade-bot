"""Signal listing/detail API endpoints."""
from flask import Blueprint, request, jsonify
from flask_login import login_required
from ..models.signal import Signal
from datetime import datetime

signals_bp = Blueprint('signals_api', __name__)


@signals_bp.route('', methods=['GET'])
@login_required
def list_signals():
    coin = request.args.get('coin', '').upper()
    direction = request.args.get('direction', '').upper()
    min_score = request.args.get('min_score', type=float)
    status = request.args.get('status', '')
    limit = min(request.args.get('limit', 50, type=int), 200)

    query = Signal.query
    if coin:
        query = query.filter_by(coin=coin)
    if direction and direction in ('LONG', 'SHORT'):
        query = query.filter_by(direction=direction)
    if min_score:
        query = query.filter(Signal.final_score >= min_score)
    if status:
        query = query.filter_by(status=status)

    signals = query.order_by(Signal.created_at.desc()).limit(limit).all()
    return jsonify([s.to_dict() for s in signals])


@signals_bp.route('/<int:signal_id>', methods=['GET'])
@login_required
def signal_detail(signal_id):
    sig = Signal.query.get_or_404(signal_id)
    data = sig.to_dict()
    # Add TTL remaining
    if sig.expires_at:
        remaining = (sig.expires_at - datetime.utcnow()).total_seconds()
        data['ttl_remaining'] = max(0, int(remaining))
    return jsonify(data)


@signals_bp.route('/digest', methods=['GET'])
@login_required
def daily_digest():
    """Top 5 signals of the day."""
    from datetime import timedelta
    cutoff = datetime.utcnow() - timedelta(hours=24)
    top = Signal.query.filter(
        Signal.created_at >= cutoff,
    ).order_by(Signal.final_score.desc()).limit(5).all()
    return jsonify([s.to_dict() for s in top])
