"""Authentication Service"""
from datetime import datetime, timezone
import bcrypt
from flask_jwt_extended import create_access_token, create_refresh_token

from ..extensions import db
from ..models.admin import Admin
from ..models.agent import Agent
from ..models.audit import AuditLog


def hash_password(password: str) -> str:
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')


def verify_password(password: str, password_hash: str) -> bool:
    return bcrypt.checkpw(password.encode('utf-8'), password_hash.encode('utf-8'))


def login_admin(username: str, password: str, ip_address: str = None):
    """Authenticate admin and return JWT tokens."""
    admin = Admin.query.filter_by(username=username).first()
    if not admin or not admin.is_active:
        return None, "Invalid credentials"
    if not verify_password(password, admin.password_hash):
        return None, "Invalid credentials"

    admin.last_login_at = datetime.now(timezone.utc)
    db.session.add(AuditLog(
        user_type='admin', user_id=admin.id,
        action='login', ip_address=ip_address
    ))
    db.session.commit()

    identity = str(admin.id)
    claims = {'user_type': 'admin'}
    return {
        'access_token': create_access_token(identity=identity, additional_claims=claims),
        'refresh_token': create_refresh_token(identity=identity, additional_claims=claims),
        'user': admin.to_dict(),
    }, None


def login_agent(username: str, password: str, ip_address: str = None):
    """Authenticate agent and return JWT tokens."""
    agent = Agent.query.filter_by(username=username).first()
    if not agent or not agent.is_active:
        return None, "Invalid credentials"
    if not verify_password(password, agent.password_hash):
        return None, "Invalid credentials"

    agent.last_login_at = datetime.now(timezone.utc)
    db.session.add(AuditLog(
        user_type='agent', user_id=agent.id,
        action='login', ip_address=ip_address
    ))
    db.session.commit()

    identity = str(agent.id)
    claims = {'user_type': 'agent'}
    return {
        'access_token': create_access_token(identity=identity, additional_claims=claims),
        'refresh_token': create_refresh_token(identity=identity, additional_claims=claims),
        'user': agent.to_dict(),
    }, None
