"""Trade & DailyStat Models"""
from datetime import datetime, timezone
from ..extensions import db


class Trade(db.Model):
    __tablename__ = 'trades'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id'), nullable=False,
                         index=True)
    symbol = db.Column(db.String(20), nullable=False)
    direction = db.Column(db.Enum('LONG', 'SHORT'), nullable=False)
    entry_price = db.Column(db.Numeric(20, 8), nullable=False)
    exit_price = db.Column(db.Numeric(20, 8))
    amount = db.Column(db.Numeric(15, 2), nullable=False)
    leverage = db.Column(db.Integer, nullable=False)
    stop_loss = db.Column(db.Numeric(20, 8))
    take_profit = db.Column(db.Numeric(20, 8))
    entry_time = db.Column(db.DateTime, nullable=False)
    exit_time = db.Column(db.DateTime, nullable=True)
    status = db.Column(db.Enum('OPEN', 'CLOSED', 'CANCELLED'), default='OPEN',
                       index=True)
    pnl = db.Column(db.Numeric(15, 4))
    roi = db.Column(db.Numeric(10, 4))
    fee = db.Column(db.Numeric(15, 6), default=0)
    funding_fee = db.Column(db.Numeric(15, 6), default=0)
    score = db.Column(db.Integer)
    close_reason = db.Column(db.String(200))
    peak_roi = db.Column(db.Numeric(10, 4), default=0)
    binance_order_id = db.Column(db.String(50))
    strategy_version = db.Column(db.String(20))
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    updated_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           onupdate=lambda: datetime.now(timezone.utc))

    __table_args__ = (
        db.Index('idx_agent_status', 'agent_id', 'status'),
        db.Index('idx_agent_time', 'agent_id', 'entry_time'),
    )

    def to_dict(self):
        return {
            'id': self.id,
            'symbol': self.symbol,
            'direction': self.direction,
            'entry_price': float(self.entry_price) if self.entry_price else None,
            'exit_price': float(self.exit_price) if self.exit_price else None,
            'amount': float(self.amount) if self.amount else None,
            'leverage': self.leverage,
            'stop_loss': float(self.stop_loss) if self.stop_loss else None,
            'take_profit': float(self.take_profit) if self.take_profit else None,
            'entry_time': self.entry_time.isoformat() if self.entry_time else None,
            'exit_time': self.exit_time.isoformat() if self.exit_time else None,
            'status': self.status,
            'pnl': float(self.pnl) if self.pnl else None,
            'roi': float(self.roi) if self.roi else None,
            'fee': float(self.fee) if self.fee else 0,
            'funding_fee': float(self.funding_fee) if self.funding_fee else 0,
            'score': self.score,
            'close_reason': self.close_reason,
            'peak_roi': float(self.peak_roi) if self.peak_roi else 0,
            'strategy_version': self.strategy_version,
        }


class DailyStat(db.Model):
    __tablename__ = 'daily_stats'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id'), nullable=False)
    date = db.Column(db.Date, nullable=False)
    trades_closed = db.Column(db.Integer, default=0)
    win_trades = db.Column(db.Integer, default=0)
    total_pnl = db.Column(db.Numeric(15, 4), default=0)
    total_fees = db.Column(db.Numeric(15, 6), default=0)

    __table_args__ = (
        db.UniqueConstraint('agent_id', 'date', name='uk_agent_date'),
    )

    def to_dict(self):
        return {
            'date': self.date.isoformat() if self.date else None,
            'trades_closed': self.trades_closed,
            'win_trades': self.win_trades,
            'total_pnl': float(self.total_pnl) if self.total_pnl else 0,
            'total_fees': float(self.total_fees) if self.total_fees else 0,
            'win_rate': (self.win_trades / self.trades_closed * 100
                         if self.trades_closed else 0),
        }
