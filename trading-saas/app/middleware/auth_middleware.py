"""Authentication Middleware"""
from functools import wraps
from flask import jsonify
from flask_jwt_extended import verify_jwt_in_request, get_jwt_identity, get_jwt


def admin_required(fn):
    """Decorator: require admin JWT."""
    @wraps(fn)
    def wrapper(*args, **kwargs):
        verify_jwt_in_request()
        claims = get_jwt()
        if claims.get('user_type') != 'admin':
            return jsonify({'error': 'Admin access required'}), 403
        return fn(*args, **kwargs)
    return wrapper


def agent_required(fn):
    """Decorator: require agent JWT."""
    @wraps(fn)
    def wrapper(*args, **kwargs):
        verify_jwt_in_request()
        claims = get_jwt()
        if claims.get('user_type') != 'agent':
            return jsonify({'error': 'Agent access required'}), 403
        return fn(*args, **kwargs)
    return wrapper


def any_auth_required(fn):
    """Decorator: require any valid JWT (admin or agent)."""
    @wraps(fn)
    def wrapper(*args, **kwargs):
        verify_jwt_in_request()
        identity = get_jwt_identity()
        if not identity:
            return jsonify({'error': 'Authentication required'}), 401
        return fn(*args, **kwargs)
    return wrapper


def get_current_user_id():
    """Get the current user's ID from JWT."""
    return int(get_jwt_identity())


def get_current_user_type():
    """Get the current user type ('admin' or 'agent') from JWT."""
    claims = get_jwt()
    return claims.get('user_type')
