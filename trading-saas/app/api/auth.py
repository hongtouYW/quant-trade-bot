"""Auth API - Login / Refresh / Change Password"""
from flask import Blueprint, request, jsonify
from flask_jwt_extended import (
    jwt_required, get_jwt_identity, get_jwt,
    create_access_token, create_refresh_token,
)

from ..services.auth_service import login_admin, login_agent, hash_password, verify_password
from ..middleware.rate_limiter import rate_limit
from ..models.admin import Admin
from ..models.agent import Agent
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
