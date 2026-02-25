"""Application Configuration"""
import os
from datetime import timedelta
from dotenv import load_dotenv

load_dotenv()


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
    JWT_REFRESH_TOKEN_EXPIRES = timedelta(days=7)
    JWT_TOKEN_LOCATION = ['headers']

    # Redis
    REDIS_URL = os.environ.get('REDIS_URL', 'redis://127.0.0.1:6379/0')

    # Encryption
    ENCRYPTION_MASTER_KEY = os.environ.get('ENCRYPTION_MASTER_KEY', '')

    # CORS
    CORS_ORIGINS = os.environ.get('CORS_ORIGINS', '*').split(',')

    # Trading
    BOT_SCAN_INTERVAL = int(os.environ.get('BOT_SCAN_INTERVAL', '60'))
