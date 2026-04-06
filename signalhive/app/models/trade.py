from datetime import datetime
from ..extensions import db


class SignalTrade(db.Model):
    __tablename__ = 'signal_trades'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    signal_id = db.Column(db.Integer, nullable=False, index=True)
    strategy_id = db.Column(db.Integer, nullable=False)
    user_id = db.Column(db.Integer, nullable=False, index=True)
    coin = db.Column(db.String(20))
    direction = db.Column(db.Enum('LONG', 'SHORT'))
    entry_price = db.Column(db.Numeric(20, 8))
    exit_price = db.Column(db.Numeric(20, 8))
    quantity = db.Column(db.Numeric(20, 8))
    leverage = db.Column(db.Integer, default=1)
    pnl = db.Column(db.Numeric(20, 8))
    roi = db.Column(db.Numeric(8, 4))
    is_paper = db.Column(db.Boolean, default=True)
    status = db.Column(db.Enum('open', 'closed', 'cancelled'), default='open')
    opened_at = db.Column(db.DateTime, default=datetime.utcnow)
    closed_at = db.Column(db.DateTime)

    def to_dict(self):
        return {
            'id': self.id,
            'signal_id': self.signal_id,
            'strategy_id': self.strategy_id,
            'user_id': self.user_id,
            'coin': self.coin,
            'direction': self.direction,
            'entry_price': float(self.entry_price) if self.entry_price else None,
            'exit_price': float(self.exit_price) if self.exit_price else None,
            'quantity': float(self.quantity) if self.quantity else None,
            'leverage': self.leverage,
            'pnl': float(self.pnl) if self.pnl else None,
            'roi': float(self.roi) if self.roi else None,
            'is_paper': self.is_paper,
            'status': self.status,
            'opened_at': self.opened_at.isoformat() if self.opened_at else None,
            'closed_at': self.closed_at.isoformat() if self.closed_at else None,
        }
