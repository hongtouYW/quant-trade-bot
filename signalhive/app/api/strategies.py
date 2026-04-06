"""Strategy CRUD API endpoints."""
from flask import Blueprint, request, jsonify, current_app
from flask_login import login_required, current_user
from ..models.strategy import Strategy
from ..extensions import db

strategies_bp = Blueprint('strategies_api', __name__)


@strategies_bp.route('', methods=['POST'])
@login_required
def create_strategy():
    data = request.json or {}
    name = data.get('name', 'My Strategy')
    template = data.get('template_type', 'balanced')

    templates = current_app.config.get('STRATEGY_TEMPLATES', {})
    config = templates.get(template, templates.get('balanced', {})).copy()

    # Override with user-provided config
    if 'config' in data:
        config.update(data['config'])

    strat = Strategy(
        user_id=current_user.id,
        name=name,
        template_type=template,
        config=config,
        bound_channels=data.get('bound_channels', []),
        min_signal_score=data.get('min_signal_score', config.get('min_signal_score', 60)),
        is_paper=data.get('is_paper', True),
        status='active',
    )
    db.session.add(strat)
    db.session.commit()

    return jsonify(strat.to_dict()), 201


@strategies_bp.route('', methods=['GET'])
@login_required
def list_strategies():
    strats = Strategy.query.filter_by(user_id=current_user.id).order_by(Strategy.created_at.desc()).all()
    return jsonify([s.to_dict() for s in strats])


@strategies_bp.route('/<int:strategy_id>', methods=['PUT'])
@login_required
def update_strategy(strategy_id):
    strat = Strategy.query.filter_by(id=strategy_id, user_id=current_user.id).first()
    if not strat:
        return jsonify({'error': 'Strategy not found'}), 404

    data = request.json or {}
    if 'name' in data:
        strat.name = data['name']
    if 'config' in data:
        current_config = strat.config or {}
        current_config.update(data['config'])
        strat.config = current_config
    if 'bound_channels' in data:
        strat.bound_channels = data['bound_channels']
    if 'min_signal_score' in data:
        strat.min_signal_score = data['min_signal_score']
    if 'status' in data:
        strat.status = data['status']
    if 'is_paper' in data:
        strat.is_paper = data['is_paper']

    db.session.commit()
    return jsonify(strat.to_dict())


@strategies_bp.route('/<int:strategy_id>', methods=['DELETE'])
@login_required
def delete_strategy(strategy_id):
    strat = Strategy.query.filter_by(id=strategy_id, user_id=current_user.id).first()
    if not strat:
        return jsonify({'error': 'Strategy not found'}), 404
    db.session.delete(strat)
    db.session.commit()
    return jsonify({'ok': True})
