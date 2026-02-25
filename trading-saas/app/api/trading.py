"""Trading Data API - Positions, History, Stats, Real-time PnL"""
from datetime import datetime, timezone
from flask import Blueprint, request, jsonify
from sqlalchemy import func

from ..middleware.auth_middleware import agent_required, admin_required, get_current_user_id
from ..models.trade import Trade, DailyStat
from ..models.agent import Agent
from ..extensions import db

trading_bp = Blueprint('trading', __name__)


@trading_bp.route('/positions', methods=['GET'])
@agent_required
def get_positions():
    """Get open positions with real-time PnL from BotManager."""
    agent_id = get_current_user_id()
    trades = Trade.query.filter_by(
        agent_id=agent_id, status='OPEN'
    ).order_by(Trade.entry_time.desc()).all()

    positions = []
    for t in trades:
        d = t.to_dict()
        # Try to get live PnL from the running bot
        d['unrealized_pnl'] = None
        d['current_roi'] = None
        try:
            from ..engine.bot_manager import BotManager
            manager = BotManager.get_instance()
            bot = manager._bots.get(agent_id)
            if bot and t.symbol in bot.positions:
                pos = bot.positions[t.symbol]
                d['peak_roi'] = pos.get('peak_roi', 0)
        except Exception:
            pass
        positions.append(d)

    return jsonify({'positions': positions})


@trading_bp.route('/history', methods=['GET'])
@agent_required
def get_history():
    """Get closed trade history with pagination and filters."""
    agent_id = get_current_user_id()
    page = request.args.get('page', 1, type=int)
    per_page = request.args.get('per_page', 20, type=int)
    per_page = min(per_page, 100)

    # Optional filters
    symbol = request.args.get('symbol')
    direction = request.args.get('direction')
    date_from = request.args.get('from')  # YYYY-MM-DD
    date_to = request.args.get('to')

    query = Trade.query.filter_by(agent_id=agent_id, status='CLOSED')

    if symbol:
        query = query.filter(Trade.symbol == symbol.upper())
    if direction:
        query = query.filter(Trade.direction == direction.upper())
    if date_from:
        query = query.filter(Trade.exit_time >= date_from)
    if date_to:
        query = query.filter(Trade.exit_time <= date_to + ' 23:59:59')

    query = query.order_by(Trade.exit_time.desc())
    pagination = query.paginate(page=page, per_page=per_page, error_out=False)

    return jsonify({
        'trades': [t.to_dict() for t in pagination.items],
        'total': pagination.total,
        'page': pagination.page,
        'pages': pagination.pages,
    })


@trading_bp.route('/<int:trade_id>', methods=['GET'])
@agent_required
def get_trade(trade_id):
    agent_id = get_current_user_id()
    trade = Trade.query.filter_by(id=trade_id, agent_id=agent_id).first()
    if not trade:
        return jsonify({'error': 'Trade not found'}), 404
    return jsonify(trade.to_dict())


@trading_bp.route('/stats', methods=['GET'])
@agent_required
def get_stats():
    """Comprehensive trade statistics."""
    agent_id = get_current_user_id()

    stats = db.session.query(
        func.count(Trade.id),
        func.sum(Trade.pnl),
        func.sum(Trade.fee),
        func.sum(Trade.funding_fee),
        func.max(Trade.pnl),
        func.min(Trade.pnl),
        func.sum(db.case((Trade.pnl > 0, 1), else_=0)),
        func.avg(Trade.pnl),
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED'
    ).first()

    total_trades = stats[0] or 0
    if total_trades == 0:
        return jsonify({
            'total_trades': 0, 'win_trades': 0, 'loss_trades': 0,
            'win_rate': 0, 'total_pnl': 0, 'total_fees': 0,
            'avg_pnl': 0, 'best_trade': 0, 'worst_trade': 0,
            'profit_factor': 0, 'max_drawdown': 0,
        })

    total_pnl = float(stats[1]) if stats[1] else 0
    total_fees = float(stats[2] or 0) + float(stats[3] or 0)
    best = float(stats[4]) if stats[4] else 0
    worst = float(stats[5]) if stats[5] else 0
    wins = int(stats[6]) if stats[6] else 0
    avg_pnl = float(stats[7]) if stats[7] else 0

    # Profit factor
    gross_profit = db.session.query(
        func.sum(Trade.pnl)
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED', Trade.pnl > 0
    ).scalar() or 0
    gross_loss = abs(db.session.query(
        func.sum(Trade.pnl)
    ).filter(
        Trade.agent_id == agent_id, Trade.status == 'CLOSED', Trade.pnl < 0
    ).scalar() or 0)
    profit_factor = float(gross_profit) / gross_loss if gross_loss > 0 else 0

    # Max drawdown from trade history
    closed_trades = Trade.query.filter_by(
        agent_id=agent_id, status='CLOSED'
    ).order_by(Trade.exit_time).all()

    from ..models.agent_config import AgentTradingConfig
    tc = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    initial_capital = float(tc.initial_capital) if tc else 2000

    peak = initial_capital
    max_dd = 0
    cumulative = initial_capital
    for t in closed_trades:
        cumulative += float(t.pnl) if t.pnl else 0
        if cumulative > peak:
            peak = cumulative
        dd = (peak - cumulative) / peak * 100 if peak > 0 else 0
        max_dd = max(max_dd, dd)

    # Open positions count and unrealized
    open_count = Trade.query.filter_by(agent_id=agent_id, status='OPEN').count()

    return jsonify({
        'total_trades': total_trades,
        'win_trades': wins,
        'loss_trades': total_trades - wins,
        'win_rate': round(wins / total_trades * 100, 1),
        'total_pnl': round(total_pnl, 2),
        'total_fees': round(total_fees, 4),
        'avg_pnl': round(avg_pnl, 2),
        'best_trade': round(best, 2),
        'worst_trade': round(worst, 2),
        'profit_factor': round(profit_factor, 2),
        'max_drawdown': round(max_dd, 2),
        'current_capital': round(cumulative, 2),
        'open_positions': open_count,
    })


@trading_bp.route('/daily', methods=['GET'])
@agent_required
def get_daily():
    agent_id = get_current_user_id()
    days = request.args.get('days', 30, type=int)
    days = min(days, 365)

    stats = (
        DailyStat.query
        .filter_by(agent_id=agent_id)
        .order_by(DailyStat.date.desc())
        .limit(days)
        .all()
    )

    # Add cumulative PnL
    result = [s.to_dict() for s in reversed(stats)]
    cumulative = 0
    for d in result:
        cumulative += d['total_pnl']
        d['cumulative_pnl'] = round(cumulative, 2)

    return jsonify({'daily': result})


@trading_bp.route('/symbols', methods=['GET'])
@agent_required
def get_traded_symbols():
    """Get list of all symbols this agent has traded."""
    agent_id = get_current_user_id()
    symbols = db.session.query(
        Trade.symbol
    ).filter(
        Trade.agent_id == agent_id
    ).distinct().all()
    return jsonify({'symbols': [s[0] for s in symbols]})
