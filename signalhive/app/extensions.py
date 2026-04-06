from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager
import redis
import os

db = SQLAlchemy()
login_manager = LoginManager()

_redis_client = None


def get_redis():
    global _redis_client
    if _redis_client is None:
        url = os.environ.get('REDIS_URL', 'redis://127.0.0.1:6379/0')
        _redis_client = redis.from_url(url, decode_responses=True)
    return _redis_client
