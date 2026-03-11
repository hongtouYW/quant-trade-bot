"""Application Configuration"""
import os
import sys
from datetime import timedelta
from dotenv import load_dotenv

load_dotenv()


def _require_env(key: str, hint: str = '') -> str:
    """Require env var in production, allow default in dev."""
    val = os.environ.get(key, '')
    if not val and os.environ.get('FLASK_ENV') == 'production':
        print(f"FATAL: {key} must be set in production. {hint}")
        sys.exit(1)
    return val


class Config:
    # Flask
    SECRET_KEY = os.environ.get('FLASK_SECRET_KEY', 'dev-secret-change-me')

    # Database - Use MySQL if MYSQL_HOST is set, otherwise SQLite for local dev
    MYSQL_HOST = os.environ.get('MYSQL_HOST', '')
    MYSQL_PORT = os.environ.get('MYSQL_PORT', '3306')
    MYSQL_DB = os.environ.get('MYSQL_DATABASE', 'trading_saas')
    MYSQL_USER = os.environ.get('MYSQL_USER', 'trading_app')
    MYSQL_PASS = os.environ.get('MYSQL_PASSWORD', '')

    if MYSQL_HOST:
        SQLALCHEMY_DATABASE_URI = (
            f"mysql+pymysql://{MYSQL_USER}:{MYSQL_PASS}"
            f"@{MYSQL_HOST}:{MYSQL_PORT}/{MYSQL_DB}?charset=utf8mb4"
        )
        SQLALCHEMY_ENGINE_OPTIONS = {
            'pool_size': 10,
            'pool_recycle': 3600,
            'pool_pre_ping': True,
        }
    else:
        _db_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'trading_saas.db')
        SQLALCHEMY_DATABASE_URI = f"sqlite:///{_db_path}"
        SQLALCHEMY_ENGINE_OPTIONS = {}

    SQLALCHEMY_TRACK_MODIFICATIONS = False

    # JWT
    JWT_SECRET_KEY = os.environ.get('JWT_SECRET_KEY', 'jwt-dev-secret')
    JWT_ACCESS_TOKEN_EXPIRES = timedelta(hours=1)
    JWT_REFRESH_TOKEN_EXPIRES = timedelta(days=2)
    JWT_TOKEN_LOCATION = ['headers']

    # Redis
    REDIS_URL = os.environ.get('REDIS_URL', 'redis://127.0.0.1:6379/0')

    # Encryption
    ENCRYPTION_MASTER_KEY = os.environ.get('ENCRYPTION_MASTER_KEY', '')

    # CORS
    CORS_ORIGINS = os.environ.get('CORS_ORIGINS', '*').split(',')

    # Trading
    BOT_SCAN_INTERVAL = int(os.environ.get('BOT_SCAN_INTERVAL', '60'))

    # Admin Telegram notifications (circuit breaker alerts)
    ADMIN_TELEGRAM_BOT_TOKEN = os.environ.get('ADMIN_TELEGRAM_BOT_TOKEN', '')
    ADMIN_TELEGRAM_CHAT_ID = os.environ.get('ADMIN_TELEGRAM_CHAT_ID', '')


# --- Production safety checks ---
if os.environ.get('FLASK_ENV') == 'production':
    _errors = []
    if Config.SECRET_KEY in ('dev-secret-change-me', ''):
        _errors.append('FLASK_SECRET_KEY must be set (not default)')
    if Config.JWT_SECRET_KEY in ('jwt-dev-secret', ''):
        _errors.append('JWT_SECRET_KEY must be set (not default)')
    _emk = Config.ENCRYPTION_MASTER_KEY
    if not _emk or len(_emk) != 64 or not all(c in '0123456789abcdefABCDEF' for c in _emk):
        _errors.append('ENCRYPTION_MASTER_KEY must be 64-char hex')
    if '*' in Config.CORS_ORIGINS:
        _errors.append('CORS_ORIGINS must not be * in production')
    if _errors:
        for e in _errors:
            print(f"FATAL CONFIG: {e}")
        sys.exit(1)
