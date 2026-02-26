"""Auth API - Login / Refresh / Change Password / Register"""
import re
from flask import Blueprint, request, jsonify
from flask_jwt_extended import (
    jwt_required, get_jwt_identity, get_jwt,
    create_access_token, create_refresh_token,
)

from ..services.auth_service import login_admin, login_agent, hash_password, verify_password
from ..services.audit_service import log_action
from ..middleware.rate_limiter import rate_limit
from ..models.admin import Admin
from ..models.agent import Agent
from ..models.agent_config import AgentTradingConfig
from ..models.bot_state import BotState
from ..extensions import db

auth_bp = Blueprint('auth', __name__)


@auth_bp.route('/admin/login', methods=['POST'])
@rate_limit(max_requests=5, window_seconds=60)
def admin_login():
    data = request.get_json()
    if not data or not data.get('username') or not data.get('password'):
        return jsonify({'error': 'Username and password required'}), 400

    result, error = login_admin(
        data['username'], data['password'],
        ip_address=request.remote_addr
    )
    if error:
        return jsonify({'error': error}), 401
    return jsonify(result)


@auth_bp.route('/agent/login', methods=['POST'])
@rate_limit(max_requests=5, window_seconds=60)
def agent_login():
    data = request.get_json()
    if not data or not data.get('username') or not data.get('password'):
        return jsonify({'error': 'Username and password required'}), 400

    result, error = login_agent(
        data['username'], data['password'],
        ip_address=request.remote_addr
    )
    if error:
        return jsonify({'error': error}), 401
    return jsonify(result)


@auth_bp.route('/agent/register', methods=['POST'])
@rate_limit(max_requests=3, window_seconds=300)
def agent_register():
    """Self-service agent registration. Requires admin approval to trade."""
    data = request.get_json()
    if not data:
        return jsonify({'error': 'Request body required'}), 400

    username = (data.get('username') or '').strip()
    email = (data.get('email') or '').strip().lower()
    password = data.get('password') or ''
    display_name = (data.get('display_name') or username).strip()

    # Validation
    if not username or not email or not password:
        return jsonify({'error': 'Username, email, and password are required'}), 400
    if len(username) < 3 or len(username) > 50:
        return jsonify({'error': 'Username must be 3-50 characters'}), 400
    if not re.match(r'^[a-zA-Z0-9_]+$', username):
        return jsonify({'error': 'Username can only contain letters, numbers, underscores'}), 400
    if not re.match(r'^[^@]+@[^@]+\.[^@]+$', email):
        return jsonify({'error': 'Invalid email format'}), 400
    if len(password) < 8:
        return jsonify({'error': 'Password must be at least 8 characters'}), 400

    # Check uniqueness
    if Agent.query.filter_by(username=username).first():
        return jsonify({'error': 'Username already taken'}), 409
    if Agent.query.filter_by(email=email).first():
        return jsonify({'error': 'Email already registered'}), 409

    # Assign to default admin (first admin)
    default_admin = Admin.query.filter_by(is_active=True).order_by(Admin.id).first()
    if not default_admin:
        return jsonify({'error': 'System not configured. Contact administrator.'}), 500

    agent = Agent(
        admin_id=default_admin.id,
        username=username,
        email=email,
        password_hash=hash_password(password),
        display_name=display_name,
        is_active=True,
        is_trading_enabled=False,  # Requires admin approval
    )
    db.session.add(agent)
    db.session.flush()

    # Create default trading config and bot state
    db.session.add(AgentTradingConfig(agent_id=agent.id))
    db.session.add(BotState(agent_id=agent.id))
    db.session.commit()

    log_action('agent', agent.id, 'self_register',
               details={'username': username, 'email': email})

    return jsonify({
        'message': 'Registration successful. Admin approval needed before trading.',
        'username': agent.username,
    }), 201


@auth_bp.route('/refresh', methods=['POST'])
@jwt_required(refresh=True)
def refresh_token():
    identity = get_jwt_identity()
    claims = get_jwt()
    new_token = create_access_token(
        identity=identity,
        additional_claims={'user_type': claims.get('user_type')}
    )
    return jsonify({'access_token': new_token})


@auth_bp.route('/change-password', methods=['POST'])
@jwt_required()
def change_password():
    user_id = int(get_jwt_identity())
    claims = get_jwt()
    data = request.get_json()

    if not data or not data.get('old_password') or not data.get('new_password'):
        return jsonify({'error': 'old_password and new_password required'}), 400

    if len(data['new_password']) < 8:
        return jsonify({'error': 'Password must be at least 8 characters'}), 400

    user_type = claims.get('user_type')

    if user_type == 'admin':
        user = Admin.query.get(user_id)
    else:
        user = Agent.query.get(user_id)

    if not user or not verify_password(data['old_password'], user.password_hash):
        return jsonify({'error': 'Invalid current password'}), 401

    user.password_hash = hash_password(data['new_password'])
    db.session.commit()
    return jsonify({'message': 'Password changed successfully'})
