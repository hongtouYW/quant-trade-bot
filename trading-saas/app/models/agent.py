"""Agent Model"""
from datetime import datetime, timezone
from ..extensions import db


class Agent(db.Model):
    __tablename__ = 'agents'

    id = db.Column(db.Integer, primary_key=True)
    admin_id = db.Column(db.Integer, db.ForeignKey('admins.id'), nullable=False)
    username = db.Column(db.String(50), unique=True, nullable=False)
    email = db.Column(db.String(191), unique=True, nullable=False)
    password_hash = db.Column(db.String(255), nullable=False)
    display_name = db.Column(db.String(100))
    phone = db.Column(db.String(20))
    is_active = db.Column(db.Boolean, default=True)
    is_trading_enabled = db.Column(db.Boolean, default=False)
    profit_share_pct = db.Column(db.Numeric(5, 2), default=20.00)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    updated_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           onupdate=lambda: datetime.now(timezone.utc))
    last_login_at = db.Column(db.DateTime, nullable=True)
    notes = db.Column(db.Text)

    # Relationships
    api_key = db.relationship('AgentApiKey', backref='agent', uselist=False,
                              cascade='all, delete-orphan')
    telegram_config = db.relationship('AgentTelegramConfig', backref='agent',
                                      uselist=False, cascade='all, delete-orphan')
    trading_config = db.relationship('AgentTradingConfig', backref='agent',
                                     uselist=False, cascade='all, delete-orphan')
    trades = db.relationship('Trade', backref='agent', lazy='dynamic')
    bot_state = db.relationship('BotState', backref='agent', uselist=False,
                                cascade='all, delete-orphan')

    def to_dict(self, include_config=False):
        d = {
            'id': self.id,
            'admin_id': self.admin_id,
            'username': self.username,
            'email': self.email,
            'display_name': self.display_name,
            'is_active': self.is_active,
            'is_trading_enabled': self.is_trading_enabled,
            'profit_share_pct': float(self.profit_share_pct) if self.profit_share_pct else 20.0,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'last_login_at': self.last_login_at.isoformat() if self.last_login_at else None,
            'has_api_key': self.api_key is not None,
            'has_telegram': self.telegram_config is not None,
            'bot_status': self.bot_state.status if self.bot_state else 'stopped',
        }
        if include_config and self.trading_config:
            d['trading_config'] = self.trading_config.to_dict()
        return d

    def to_admin_dict(self):
        """Extended dict for admin view"""
        d = self.to_dict(include_config=True)
        d['notes'] = self.notes
        d['phone'] = self.phone
        d['api_key_verified'] = (
            self.api_key.permissions_verified if self.api_key else False
        )
        return d
