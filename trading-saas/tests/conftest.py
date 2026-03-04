"""Test fixtures for Trading SaaS."""
import os
import pytest
import bcrypt
from datetime import datetime, timezone, date
from decimal import Decimal

# Set test config before importing app
os.environ['FLASK_SECRET_KEY'] = 'test-secret-key'
os.environ['JWT_SECRET_KEY'] = 'test-jwt-secret'
os.environ['ENCRYPTION_MASTER_KEY'] = 'a' * 64  # 32 bytes in hex


from app import create_app
from app.extensions import db as _db
from app.middleware.rate_limiter import _rate_store
from app.models.admin import Admin
from app.models.agent import Agent
from app.models.agent_config import AgentApiKey, AgentTelegramConfig, AgentTradingConfig
from app.models.trade import Trade, DailyStat
from app.models.billing import BillingPeriod
from app.models.bot_state import BotState
from app.models.audit import AuditLog


class TestConfig:
    SECRET_KEY = 'test-secret-key'
    SQLALCHEMY_DATABASE_URI = 'sqlite:///:memory:'
    SQLALCHEMY_TRACK_MODIFICATIONS = False
    SQLALCHEMY_ENGINE_OPTIONS = {}
    JWT_SECRET_KEY = 'test-jwt-secret'
    JWT_ACCESS_TOKEN_EXPIRES = False  # No expiry in tests
    JWT_REFRESH_TOKEN_EXPIRES = False
    JWT_TOKEN_LOCATION = ['headers']
    TESTING = True
    ENCRYPTION_MASTER_KEY = 'a' * 64
    CORS_ORIGINS = ['*']
    REDIS_URL = 'redis://127.0.0.1:6379/0'
    BOT_SCAN_INTERVAL = 60
    ADMIN_TELEGRAM_BOT_TOKEN = ''
    ADMIN_TELEGRAM_CHAT_ID = ''


@pytest.fixture(scope='session')
def app():
    """Create application for the tests."""
    app = create_app(TestConfig)
    return app


@pytest.fixture(scope='function')
def db(app):
    """Create a fresh database for each test."""
    with app.app_context():
        _db.create_all()
        yield _db
        _db.session.rollback()
        _db.drop_all()


@pytest.fixture(autouse=True)
def clear_rate_limits():
    """Clear rate limiter between tests."""
    _rate_store.clear()


@pytest.fixture
def client(app, db):
    """Flask test client."""
    return app.test_client()


@pytest.fixture
def app_ctx(app, db):
    """Application context."""
    with app.app_context():
        yield app


def _hash(pw):
    return bcrypt.hashpw(pw.encode(), bcrypt.gensalt()).decode()


@pytest.fixture
def admin(db):
    """Create a test admin user."""
    admin = Admin(
        username='testadmin',
        email='admin@test.com',
        password_hash=_hash('password123'),
        is_active=True,
    )
    db.session.add(admin)
    db.session.commit()
    return admin


@pytest.fixture
def agent(db, admin):
    """Create a test agent user."""
    agent = Agent(
        admin_id=admin.id,
        username='testagent',
        email='agent@test.com',
        password_hash=_hash('password123'),
        display_name='Test Agent',
        is_active=True,
        is_trading_enabled=True,
        profit_share_pct=Decimal('20.00'),
    )
    db.session.add(agent)
    db.session.flush()

    # Default trading config
    tc = AgentTradingConfig(agent_id=agent.id)
    db.session.add(tc)

    # Default bot state
    bs = BotState(agent_id=agent.id, status='stopped')
    db.session.add(bs)

    db.session.commit()
    return agent


@pytest.fixture
def admin_token(client, admin):
    """Get admin JWT token."""
    resp = client.post('/api/auth/admin/login', json={
        'username': 'testadmin',
        'password': 'password123',
    })
    return resp.get_json()['access_token']


@pytest.fixture
def agent_token(client, agent):
    """Get agent JWT token."""
    resp = client.post('/api/auth/agent/login', json={
        'username': 'testagent',
        'password': 'password123',
    })
    return resp.get_json()['access_token']


@pytest.fixture
def sample_trades(db, agent):
    """Create sample closed trades for testing."""
    trades = []
    for i in range(5):
        pnl = 50 if i % 2 == 0 else -30
        t = Trade(
            agent_id=agent.id,
            symbol='BTC/USDT',
            direction='LONG' if i % 2 == 0 else 'SHORT',
            entry_price=Decimal('50000'),
            exit_price=Decimal('51000') if pnl > 0 else Decimal('49500'),
            amount=Decimal('100'),
            leverage=3,
            entry_time=datetime(2026, 2, 1, i, 0, 0, tzinfo=timezone.utc),
            exit_time=datetime(2026, 2, 1, i + 1, 0, 0, tzinfo=timezone.utc),
            status='CLOSED',
            pnl=Decimal(str(pnl)),
            roi=Decimal(str(pnl / 100 * 100)),
            fee=Decimal('0.5'),
            score=75,
            close_reason='take_profit' if pnl > 0 else 'stop_loss',
            strategy_version='v4.2',
        )
        db.session.add(t)
        trades.append(t)
    db.session.commit()
    return trades
