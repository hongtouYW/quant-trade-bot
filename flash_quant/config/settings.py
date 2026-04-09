"""
Flash Quant - 配置加载
从 .env 文件读取可变配置 (非 BR 规则)
"""
import os
from pathlib import Path

# 尝试加载 .env
_env_path = Path(__file__).parent.parent / '.env'
if _env_path.exists():
    with open(_env_path) as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                key, _, value = line.partition('=')
                os.environ.setdefault(key.strip(), value.strip())


class Settings:
    """配置对象 - 从环境变量读取"""

    # Database
    MYSQL_HOST = os.getenv('MYSQL_HOST', '127.0.0.1')
    MYSQL_PORT = int(os.getenv('MYSQL_PORT', '3306'))
    MYSQL_USER = os.getenv('MYSQL_USER', 'flash_quant_user')
    MYSQL_PASSWORD = os.getenv('MYSQL_PASSWORD', '')
    MYSQL_DATABASE = os.getenv('MYSQL_DATABASE', 'flash_quant_db')

    @property
    def DB_URL(self):
        if not self.MYSQL_HOST:
            # 本地开发用 SQLite
            db_path = Path(__file__).parent.parent / 'flash_quant.db'
            return f"sqlite:///{db_path}"
        return (
            f"mysql+pymysql://{self.MYSQL_USER}:{self.MYSQL_PASSWORD}"
            f"@{self.MYSQL_HOST}:{self.MYSQL_PORT}/{self.MYSQL_DATABASE}"
            f"?charset=utf8mb4"
        )

    # Redis
    REDIS_HOST = os.getenv('REDIS_HOST', '127.0.0.1')
    REDIS_PORT = int(os.getenv('REDIS_PORT', '6379'))
    REDIS_DB = int(os.getenv('REDIS_DB', '0'))

    # Binance
    BINANCE_API_KEY = os.getenv('BINANCE_API_KEY', '')
    BINANCE_API_SECRET = os.getenv('BINANCE_API_SECRET', '')
    BINANCE_TESTNET = os.getenv('BINANCE_TESTNET', 'false').lower() == 'true'

    # System
    TRADING_MODE = os.getenv('TRADING_MODE', 'paper')  # paper | live
    PHASE = int(os.getenv('PHASE', '1'))
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FORMAT = os.getenv('LOG_FORMAT', 'json')

    # Web
    WEB_PORT = int(os.getenv('WEB_PORT', '5114'))
    WEB_SECRET_KEY = os.getenv('WEB_SECRET_KEY', 'change-me-in-production')
    WEB_AUTH_USER = os.getenv('WEB_AUTH_USER', 'hongtou')
    WEB_AUTH_PASS_HASH = os.getenv('WEB_AUTH_PASS_HASH', '')

    # Encryption
    ENCRYPTION_MASTER_KEY = os.getenv('ENCRYPTION_MASTER_KEY', '')

    # Telegram (Phase 2+)
    TG_BOT_TOKEN = os.getenv('TG_BOT_TOKEN', '')
    TG_CHAT_ID = os.getenv('TG_CHAT_ID', '')

    def validate(self):
        """验证关键配置"""
        errors = []
        if self.TRADING_MODE == 'live' and not self.BINANCE_API_KEY:
            errors.append("BINANCE_API_KEY required for live trading")
        if self.TRADING_MODE == 'live' and not self.BINANCE_API_SECRET:
            errors.append("BINANCE_API_SECRET required for live trading")
        if self.PHASE < 1 or self.PHASE > 4:
            errors.append(f"Invalid PHASE: {self.PHASE}")
        return errors


settings = Settings()
