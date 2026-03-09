"""Admin API - Manage Agents"""
import json as _json
import logging
from flask import Blueprint, request, jsonify
from ..middleware.auth_middleware import admin_required, get_current_user_id
from ..services.auth_service import hash_password
from ..services.audit_service import log_action
from ..services.encryption_service import EncryptionService
from ..models.agent import Agent
from ..models.agent_config import AgentApiKey, AgentTradingConfig
from ..models.bot_state import BotState
from ..models.trade import Trade
from ..models.audit import AuditLog
from ..extensions import db

logger = logging.getLogger(__name__)

admin_bp = Blueprint('admin', __name__)


def _fetch_agent_balance(agent_id):
    """Fetch wallet balance for an agent via exchange API."""
    from ..api.agent import _create_exchange
    record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if not record or not record.permissions_verified:
        return None
    try:
        combined_json = EncryptionService.decrypt(record.binance_api_key_enc, record.encryption_iv)
        keys = _json.loads(combined_json)
        exchange_name = record.exchange or 'binance'
        exchange = _create_exchange(exchange_name, keys['k'], keys['s'],
                                    passphrase=keys.get('p'),
                                    is_testnet=record.is_testnet)
        balance = exchange.fetch_balance()
        usdt = balance.get('USDT', {})
        return {
            'total': float(usdt.get('total', 0)),
            'free': float(usdt.get('free', 0)),
            'used': float(usdt.get('used', 0)),
        }
    except Exception as e:
        logger.warning(f"Balance fetch failed for agent {agent_id}: {e}")
        return None


@admin_bp.route('/dashboard', methods=['GET'])
@admin_required
def dashboard():
    admin_id = get_current_user_id()
    total_agents = Agent.query.filter_by(admin_id=admin_id).count()
    active_agents = Agent.query.filter_by(admin_id=admin_id, is_active=True).count()
    trading_enabled = Agent.query.filter_by(
        admin_id=admin_id, is_trading_enabled=True
    ).count()

    # Running bots
    running_bots = (
        db.session.query(BotState)
        .join(Agent)
        .filter(Agent.admin_id == admin_id, BotState.status == 'running')
        .count()
    )

    # Total PnL across all agents
    from sqlalchemy import func
    total_pnl = (
        db.session.query(func.sum(Trade.pnl))
        .join(Agent)
        .filter(Agent.admin_id == admin_id, Trade.status == 'CLOSED')
        .scalar()
    ) or 0

    return jsonify({
        'total_agents': total_agents,
        'active_agents': active_agents,
        'trading_enabled': trading_enabled,
        'running_bots': running_bots,
        'total_pnl': float(total_pnl),
    })


@admin_bp.route('/agents', methods=['GET'])
@admin_required
def list_agents():
    admin_id = get_current_user_id()
    agents = Agent.query.filter_by(admin_id=admin_id).order_by(Agent.created_at.desc()).all()
    return jsonify({'agents': [a.to_admin_dict() for a in agents]})


@admin_bp.route('/agents', methods=['POST'])
@admin_required
def create_agent():
    admin_id = get_current_user_id()
    data = request.get_json()

    required = ['username', 'email', 'password']
    for field in required:
        if not data.get(field):
            return jsonify({'error': f'{field} is required'}), 400

    # Check uniqueness
    if Agent.query.filter_by(username=data['username']).first():
        return jsonify({'error': 'Username already exists'}), 409
    if Agent.query.filter_by(email=data['email']).first():
        return jsonify({'error': 'Email already exists'}), 409

    try:
        agent = Agent(
            admin_id=admin_id,
            username=data['username'],
            email=data['email'],
            password_hash=hash_password(data['password']),
            display_name=data.get('display_name', data['username']),
            phone=data.get('phone'),
            profit_share_pct=data.get('profit_share_pct', 20.0),
            notes=data.get('notes'),
        )
        db.session.add(agent)
        db.session.flush()  # Get agent.id

        # Create default trading config
        config = AgentTradingConfig(agent_id=agent.id)
        db.session.add(config)

        # Create bot state
        bot_state = BotState(agent_id=agent.id)
        db.session.add(bot_state)

        db.session.commit()
    except Exception:
        db.session.rollback()
        return jsonify({'error': 'Failed to create agent'}), 500

    log_action('admin', admin_id, 'create_agent',
               resource=f'agent:{agent.id}',
               details={'username': agent.username})

    return jsonify({'agent': agent.to_admin_dict()}), 201


@admin_bp.route('/agents/<int:agent_id>', methods=['GET'])
@admin_required
def get_agent(agent_id):
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    # Include trade stats
    from sqlalchemy import func
    stats = (
        db.session.query(
            func.count(Trade.id),
            func.sum(Trade.pnl),
            func.sum(db.case((Trade.pnl > 0, 1), else_=0)),
        )
        .filter(Trade.agent_id == agent_id, Trade.status == 'CLOSED')
        .first()
    )

    bot_pnl = float(stats[1]) if stats[1] else 0

    result = agent.to_admin_dict()
    result['trade_stats'] = {
        'total_trades': stats[0] or 0,
        'total_pnl': bot_pnl,
        'win_trades': int(stats[2]) if stats[2] else 0,
    }

    # Fetch wallet balance and calculate PnL breakdown
    wallet = _fetch_agent_balance(agent_id)
    if wallet:
        # Open positions unrealized PnL (bot only)
        open_pnl = (
            db.session.query(func.sum(Trade.pnl))
            .filter(Trade.agent_id == agent_id, Trade.status == 'OPEN')
            .scalar()
        )
        bot_open_pnl = float(open_pnl) if open_pnl else 0

        result['wallet'] = {
            'total_balance': wallet['total'],
            'free': wallet['free'],
            'used': wallet['used'],
            'bot_realized_pnl': round(bot_pnl, 4),
            'bot_open_positions': Trade.query.filter_by(agent_id=agent_id, status='OPEN').count(),
            'note': 'Balance includes all wallet activity. Bot PnL only counts trades opened by our system.',
        }

    return jsonify(result)


@admin_bp.route('/agents/<int:agent_id>', methods=['PUT'])
@admin_required
def update_agent(agent_id):
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    data = request.get_json()
    updatable = ['display_name', 'phone', 'is_active', 'profit_share_pct', 'notes']
    for field in updatable:
        if field in data:
            setattr(agent, field, data[field])

    try:
        db.session.commit()
    except Exception:
        db.session.rollback()
        return jsonify({'error': 'Failed to update agent'}), 500

    log_action('admin', admin_id, 'update_agent',
               resource=f'agent:{agent_id}',
               details={k: data[k] for k in updatable if k in data})

    return jsonify({'agent': agent.to_admin_dict()})


@admin_bp.route('/agents/<int:agent_id>', methods=['DELETE'])
@admin_required
def deactivate_agent(agent_id):
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    agent.is_active = False
    agent.is_trading_enabled = False
    try:
        db.session.commit()
    except Exception:
        db.session.rollback()
        return jsonify({'error': 'Failed to deactivate agent'}), 500

    log_action('admin', admin_id, 'deactivate_agent',
               resource=f'agent:{agent_id}')

    return jsonify({'message': 'Agent deactivated'})


@admin_bp.route('/agents/<int:agent_id>/toggle-trading', methods=['POST'])
@admin_required
def toggle_trading(agent_id):
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    agent.is_trading_enabled = not agent.is_trading_enabled
    try:
        db.session.commit()
    except Exception:
        db.session.rollback()
        return jsonify({'error': 'Failed to toggle trading'}), 500

    log_action('admin', admin_id, 'toggle_trading',
               resource=f'agent:{agent_id}',
               details={'enabled': agent.is_trading_enabled})

    return jsonify({
        'is_trading_enabled': agent.is_trading_enabled,
        'message': f"Trading {'enabled' if agent.is_trading_enabled else 'disabled'}"
    })


@admin_bp.route('/agents/<int:agent_id>/reset-password', methods=['POST'])
@admin_required
def reset_agent_password(agent_id):
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    data = request.get_json()
    new_password = data.get('new_password', '') if data else ''
    if len(new_password) < 8:
        return jsonify({'error': 'Password must be at least 8 characters'}), 400

    agent.password_hash = hash_password(new_password)
    try:
        db.session.commit()
    except Exception:
        db.session.rollback()
        return jsonify({'error': 'Failed to reset password'}), 500

    log_action('admin', admin_id, 'reset_password',
               resource=f'agent:{agent_id}',
               details={'username': agent.username})

    return jsonify({'message': 'Password reset successfully'})


@admin_bp.route('/bots/status', methods=['GET'])
@admin_required
def all_bots_status():
    admin_id = get_current_user_id()
    agents = (
        Agent.query
        .filter_by(admin_id=admin_id)
        .join(BotState)
        .all()
    )
    result = []
    for agent in agents:
        item = {
            'agent_id': agent.id,
            'username': agent.username,
            'display_name': agent.display_name,
            'is_trading_enabled': agent.is_trading_enabled,
        }
        if agent.bot_state:
            item.update(agent.bot_state.to_dict())
        else:
            item['status'] = 'stopped'
        result.append(item)
    return jsonify({'bots': result})


@admin_bp.route('/audit-log', methods=['GET'])
@admin_required
def get_audit_log():
    """Get audit log with pagination and filters."""
    page = request.args.get('page', 1, type=int)
    per_page = request.args.get('per_page', 30, type=int)
    per_page = min(per_page, 100)

    action_filter = request.args.get('action')
    user_type_filter = request.args.get('user_type')

    query = AuditLog.query

    if action_filter:
        query = query.filter(AuditLog.action == action_filter)
    if user_type_filter:
        query = query.filter(AuditLog.user_type == user_type_filter)

    query = query.order_by(AuditLog.created_at.desc())
    pagination = query.paginate(page=page, per_page=per_page, error_out=False)

    # Resolve usernames
    logs = []
    for log in pagination.items:
        d = log.to_dict()
        if log.user_type == 'agent':
            agent = Agent.query.get(log.user_id)
            d['username'] = agent.username if agent else f'agent#{log.user_id}'
        else:
            from ..models.admin import Admin
            admin = Admin.query.get(log.user_id)
            d['username'] = admin.username if admin else f'admin#{log.user_id}'
        logs.append(d)

    # Get distinct actions for filter dropdown
    actions = db.session.query(AuditLog.action).distinct().all()

    return jsonify({
        'logs': logs,
        'total': pagination.total,
        'page': pagination.page,
        'pages': pagination.pages,
        'actions': [a[0] for a in actions],
    })


@admin_bp.route('/leaderboard', methods=['GET'])
@admin_required
def leaderboard():
    """Agent performance leaderboard sorted by total PnL."""
    from sqlalchemy import func

    admin_id = get_current_user_id()
    days = request.args.get('days', 30, type=int)
    sort_by = request.args.get('sort', 'pnl')  # pnl, win_rate, trades

    agents = Agent.query.filter_by(admin_id=admin_id, is_active=True).all()
    result = []

    for agent in agents:
        date_filter = []
        if days < 9999:
            from datetime import datetime, timedelta, timezone
            cutoff = datetime.now(timezone.utc) - timedelta(days=days)
            date_filter = [Trade.exit_time >= cutoff]

        stats = db.session.query(
            func.count(Trade.id),
            func.sum(Trade.pnl),
            func.sum(db.case((Trade.pnl > 0, 1), else_=0)),
            func.max(Trade.pnl),
            func.min(Trade.pnl),
        ).filter(
            Trade.agent_id == agent.id,
            Trade.status == 'CLOSED',
            *date_filter,
        ).first()

        total = stats[0] or 0
        total_pnl = float(stats[1]) if stats[1] else 0
        wins = int(stats[2]) if stats[2] else 0
        best = float(stats[3]) if stats[3] else 0
        worst = float(stats[4]) if stats[4] else 0

        result.append({
            'agent_id': agent.id,
            'username': agent.username,
            'display_name': agent.display_name,
            'total_trades': total,
            'total_pnl': round(total_pnl, 2),
            'win_rate': round(wins / total * 100, 1) if total > 0 else 0,
            'win_trades': wins,
            'loss_trades': total - wins,
            'best_trade': round(best, 2),
            'worst_trade': round(worst, 2),
            'bot_status': agent.bot_state.status if agent.bot_state else 'stopped',
        })

    # Sort
    if sort_by == 'win_rate':
        result.sort(key=lambda x: x['win_rate'], reverse=True)
    elif sort_by == 'trades':
        result.sort(key=lambda x: x['total_trades'], reverse=True)
    else:
        result.sort(key=lambda x: x['total_pnl'], reverse=True)

    return jsonify({'leaderboard': result, 'days': days})


@admin_bp.route('/agents/<int:agent_id>/trades', methods=['GET'])
@admin_required
def get_agent_trades(agent_id):
    """Get trade history for a specific agent (admin view)."""
    admin_id = get_current_user_id()
    agent = Agent.query.filter_by(id=agent_id, admin_id=admin_id).first()
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    page = request.args.get('page', 1, type=int)
    per_page = request.args.get('per_page', 20, type=int)
    per_page = min(per_page, 100)
    status_filter = request.args.get('status', 'CLOSED')  # CLOSED or OPEN

    query = Trade.query.filter_by(agent_id=agent_id, status=status_filter)
    if status_filter == 'CLOSED':
        query = query.order_by(Trade.exit_time.desc())
    else:
        query = query.order_by(Trade.entry_time.desc())

    pagination = query.paginate(page=page, per_page=per_page, error_out=False)

    trades_list = [t.to_dict() for t in pagination.items]

    # For OPEN positions, fetch current prices and calculate unrealized PnL
    if status_filter == 'OPEN' and trades_list:
        from ..engine.signal_analyzer import fetch_price
        # Determine agent's exchange
        api_record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
        ex_name = (api_record.exchange or 'binance') if api_record else 'binance'
        symbols = list({t['symbol'] for t in trades_list})
        prices = {}
        for sym in symbols:
            try:
                p = fetch_price(sym, timeout=5, exchange=ex_name)
                if p:
                    prices[sym] = p
            except Exception:
                pass

        for t in trades_list:
            current_price = prices.get(t['symbol'])
            if current_price and t.get('entry_price'):
                entry = float(t['entry_price'])
                leverage = int(t.get('leverage') or 1)
                amount = float(t.get('amount') or 0)
                if t['direction'] == 'LONG':
                    roi = (current_price - entry) / entry * leverage * 100
                else:
                    roi = (entry - current_price) / entry * leverage * 100
                pnl = amount * (roi / 100)
                t['current_price'] = current_price
                t['unrealized_pnl'] = round(pnl, 4)
                t['current_roi'] = round(roi, 2)

    return jsonify({
        'agent': {'id': agent.id, 'username': agent.username, 'display_name': agent.display_name},
        'trades': trades_list,
        'total': pagination.total,
        'page': pagination.page,
        'pages': pagination.pages,
    })
