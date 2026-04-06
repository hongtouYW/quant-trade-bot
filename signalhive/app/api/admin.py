"""Admin API endpoints."""
from flask import Blueprint, jsonify
from flask_login import login_required, current_user
from functools import wraps
from ..models.channel import Channel
from ..models.signal import Signal, SourceAccuracy
from ..models.trade import SignalTrade
from ..extensions import db
from sqlalchemy import func
from datetime import datetime, timedelta

admin_bp = Blueprint('admin_api', __name__)


def admin_required(f):
    @wraps(f)
    @login_required
    def decorated(*args, **kwargs):
        if not current_user.is_admin:
            return jsonify({'error': 'Admin access required'}), 403
        return f(*args, **kwargs)
    return decorated


@admin_bp.route('/channels/all', methods=['GET'])
@admin_required
def all_channels():
    channels = Channel.query.order_by(Channel.created_at.desc()).all()
    return jsonify([ch.to_dict() for ch in channels])


@admin_bp.route('/trends', methods=['GET'])
@admin_required
def trends():
    """Trend heatmap data: which coins are being signaled most."""
    cutoff = datetime.utcnow() - timedelta(hours=24)
    results = db.session.query(
        Signal.coin,
        Signal.direction,
        func.count(Signal.id).label('count'),
        func.avg(Signal.final_score).label('avg_score'),
    ).filter(
        Signal.created_at >= cutoff
    ).group_by(Signal.coin, Signal.direction).order_by(func.count(Signal.id).desc()).limit(20).all()

    return jsonify([{
        'coin': r.coin,
        'direction': r.direction,
        'count': r.count,
        'avg_score': round(float(r.avg_score), 1) if r.avg_score else 0,
    } for r in results])


@admin_bp.route('/leaderboard', methods=['GET'])
@admin_required
def leaderboard():
    """Influencer accuracy ranking."""
    results = SourceAccuracy.query.filter(
        SourceAccuracy.total_signals >= 5
    ).order_by(SourceAccuracy.accuracy_rate.desc()).limit(20).all()

    return jsonify([sa.to_dict() for sa in results])


@admin_bp.route('/consensus', methods=['GET'])
@admin_required
def consensus():
    """Cross-channel consensus matrix for top coins."""
    cutoff = datetime.utcnow() - timedelta(hours=4)
    signals = Signal.query.filter(
        Signal.created_at >= cutoff,
        Signal.status.in_(['active', 'executed'])
    ).all()

    matrix = {}
    for s in signals:
        if s.coin not in matrix:
            matrix[s.coin] = {'LONG': 0, 'SHORT': 0}
        if s.direction in matrix[s.coin]:
            matrix[s.coin][s.direction] += 1

    return jsonify(matrix)


@admin_bp.route('/anomalies', methods=['GET'])
@admin_required
def anomalies():
    """Recent anomaly alerts (placeholder for now)."""
    return jsonify([])


@admin_bp.route('/health', methods=['GET'])
@admin_required
def system_health():
    """System-wide health dashboard."""
    from ..extensions import get_redis

    r = get_redis()
    # Redis stream info
    try:
        stream_len = r.xlen('signalhive:raw_messages')
    except Exception:
        stream_len = 0

    total_channels = Channel.query.count()
    active_channels = Channel.query.filter_by(status='active').count()
    total_signals = Signal.query.count()
    active_signals = Signal.query.filter_by(status='active').count()
    open_trades = SignalTrade.query.filter_by(status='open').count()

    return jsonify({
        'redis_stream_length': stream_len,
        'total_channels': total_channels,
        'active_channels': active_channels,
        'total_signals': total_signals,
        'active_signals': active_signals,
        'open_trades': open_trades,
        'timestamp': datetime.utcnow().isoformat(),
    })
