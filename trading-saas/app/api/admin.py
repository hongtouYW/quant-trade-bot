"""Admin API - Manage Agents"""
from flask import Blueprint, request, jsonify
from ..middleware.auth_middleware import admin_required, get_current_user_id
from ..services.auth_service import hash_password
from ..services.audit_service import log_action
from ..models.agent import Agent
from ..models.agent_config import AgentTradingConfig
from ..models.bot_state import BotState
from ..models.trade import Trade
from ..extensions import db

admin_bp = Blueprint('admin', __name__)


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

    result = agent.to_admin_dict()
    result['trade_stats'] = {
        'total_trades': stats[0] or 0,
        'total_pnl': float(stats[1]) if stats[1] else 0,
        'win_trades': int(stats[2]) if stats[2] else 0,
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

    db.session.commit()

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
    db.session.commit()

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
    db.session.commit()

    log_action('admin', admin_id, 'toggle_trading',
               resource=f'agent:{agent_id}',
               details={'enabled': agent.is_trading_enabled})

    return jsonify({
        'is_trading_enabled': agent.is_trading_enabled,
        'message': f"Trading {'enabled' if agent.is_trading_enabled else 'disabled'}"
    })


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
