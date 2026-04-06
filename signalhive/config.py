import os


class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'signalhive-secret-key-change-me')

    # MySQL
    SQLALCHEMY_DATABASE_URI = os.environ.get(
        'DATABASE_URL',
        'mysql+pymysql://signalhive:SignalHive2026xK9m@127.0.0.1:3306/signalhive?charset=utf8mb4'
    )
    SQLALCHEMY_TRACK_MODIFICATIONS = False

    # Redis
    REDIS_URL = os.environ.get('REDIS_URL', 'redis://127.0.0.1:6379/0')

    # Anthropic
    ANTHROPIC_API_KEY = os.environ.get('ANTHROPIC_API_KEY', '')

    # Telegram
    TELEGRAM_API_ID = os.environ.get('TELEGRAM_API_ID', '')
    TELEGRAM_API_HASH = os.environ.get('TELEGRAM_API_HASH', '')
    TELEGRAM_SESSION_NAME = os.environ.get('TELEGRAM_SESSION_NAME', 'signalhive_tg')

    # Strategy templates
    STRATEGY_TEMPLATES = {
        "conservative": {
            "entry_delay_seconds": 900,
            "position_pct": 0.03,
            "leverage": 1,
            "tp_pct": 0.05,
            "sl_pct": 0.03,
            "min_signal_score": 75,
            "require_consensus": True,
            "max_positions": 5,
        },
        "balanced": {
            "entry_delay_seconds": 0,
            "position_pct": 0.05,
            "leverage": 3,
            "tp_pct": 0.08,
            "sl_pct": 0.05,
            "min_signal_score": 65,
            "require_consensus": False,
            "max_positions": 10,
        },
        "aggressive": {
            "entry_delay_seconds": 0,
            "position_pct": 0.10,
            "leverage": 10,
            "tp_pct": 0.15,
            "sl_pct": 0.08,
            "min_signal_score": 55,
            "require_consensus": False,
            "max_positions": 20,
        },
    }

    # Default paper trade capital
    DEFAULT_CAPITAL = 10000.0

    # Redis Stream
    STREAM_KEY = 'signalhive:raw_messages'
    CONSUMER_GROUP = 'engine_group'
    STREAM_MAX_LEN = 100000
