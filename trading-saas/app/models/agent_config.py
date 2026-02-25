"""Agent Configuration Models"""
import json
from datetime import datetime, timezone
from ..extensions import db


class AgentApiKey(db.Model):
    __tablename__ = 'agent_api_keys'

    id = db.Column(db.Integer, primary_key=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id', ondelete='CASCADE'),
                         unique=True, nullable=False)
    binance_api_key_enc = db.Column(db.LargeBinary(512), nullable=False)
    binance_api_secret_enc = db.Column(db.LargeBinary(512), nullable=False)
    encryption_iv = db.Column(db.LargeBinary(16), nullable=False)
    is_testnet = db.Column(db.Boolean, default=False)
    permissions_verified = db.Column(db.Boolean, default=False)
    last_verified_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    updated_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           onupdate=lambda: datetime.now(timezone.utc))

    def to_dict(self):
        return {
            'has_api_key': True,
            'is_testnet': self.is_testnet,
            'permissions_verified': self.permissions_verified,
            'last_verified_at': self.last_verified_at.isoformat() if self.last_verified_at else None,
            'updated_at': self.updated_at.isoformat() if self.updated_at else None,
        }


class AgentTelegramConfig(db.Model):
    __tablename__ = 'agent_telegram_config'

    id = db.Column(db.Integer, primary_key=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id', ondelete='CASCADE'),
                         unique=True, nullable=False)
    bot_token_enc = db.Column(db.LargeBinary(512), nullable=False)
    chat_id = db.Column(db.String(50), nullable=False)
    encryption_iv = db.Column(db.LargeBinary(16), nullable=False)
    is_enabled = db.Column(db.Boolean, default=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    def to_dict(self):
        return {
            'has_telegram': True,
            'chat_id': self.chat_id,
            'is_enabled': self.is_enabled,
        }


class AgentTradingConfig(db.Model):
    __tablename__ = 'agent_trading_config'

    id = db.Column(db.Integer, primary_key=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id', ondelete='CASCADE'),
                         unique=True, nullable=False)
    strategy_version = db.Column(db.String(20), default='v4.2')
    initial_capital = db.Column(db.Numeric(15, 2), default=2000)
    max_positions = db.Column(db.Integer, default=15)
    max_position_size = db.Column(db.Numeric(15, 2), default=500)
    max_leverage = db.Column(db.Integer, default=3)
    min_score = db.Column(db.Integer, default=60)
    long_min_score = db.Column(db.Integer, default=70)
    fee_rate = db.Column(db.Numeric(8, 6), default=0.000500)
    cooldown_minutes = db.Column(db.Integer, default=30)
    roi_stop_loss = db.Column(db.Numeric(5, 2), default=-10.00)
    roi_trailing_start = db.Column(db.Numeric(5, 2), default=6.00)
    roi_trailing_distance = db.Column(db.Numeric(5, 2), default=3.00)
    daily_loss_limit = db.Column(db.Numeric(15, 2), default=200)
    max_drawdown_pct = db.Column(db.Numeric(5, 2), default=20.00)
    enable_trend_filter = db.Column(db.Boolean, default=True)
    enable_btc_filter = db.Column(db.Boolean, default=True)
    short_bias = db.Column(db.Numeric(5, 3), default=1.050)
    custom_params = db.Column(db.Text, nullable=True)  # JSON as text for MariaDB 5.5
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    updated_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           onupdate=lambda: datetime.now(timezone.utc))

    def to_dict(self):
        return {
            'strategy_version': self.strategy_version,
            'initial_capital': float(self.initial_capital) if self.initial_capital else 2000,
            'max_positions': self.max_positions,
            'max_position_size': float(self.max_position_size) if self.max_position_size else 500,
            'max_leverage': self.max_leverage,
            'min_score': self.min_score,
            'long_min_score': self.long_min_score,
            'fee_rate': float(self.fee_rate) if self.fee_rate else 0.0005,
            'cooldown_minutes': self.cooldown_minutes,
            'roi_stop_loss': float(self.roi_stop_loss) if self.roi_stop_loss else -10,
            'roi_trailing_start': float(self.roi_trailing_start) if self.roi_trailing_start else 6,
            'roi_trailing_distance': float(self.roi_trailing_distance) if self.roi_trailing_distance else 3,
            'daily_loss_limit': float(self.daily_loss_limit) if self.daily_loss_limit else 200,
            'max_drawdown_pct': float(self.max_drawdown_pct) if self.max_drawdown_pct else 20,
            'enable_trend_filter': self.enable_trend_filter,
            'enable_btc_filter': self.enable_btc_filter,
            'short_bias': float(self.short_bias) if self.short_bias else 1.05,
            'custom_params': json.loads(self.custom_params) if self.custom_params else None,
        }
