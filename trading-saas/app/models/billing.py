"""Billing Period Model"""
from datetime import datetime, timezone
from ..extensions import db


class BillingPeriod(db.Model):
    __tablename__ = 'billing_periods'

    id = db.Column(db.Integer, primary_key=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id'), nullable=False,
                         index=True)
    period_start = db.Column(db.Date, nullable=False)
    period_end = db.Column(db.Date, nullable=False)
    starting_capital = db.Column(db.Numeric(15, 2))
    ending_capital = db.Column(db.Numeric(15, 2))
    gross_pnl = db.Column(db.Numeric(15, 4))
    total_fees = db.Column(db.Numeric(15, 6))
    high_water_mark = db.Column(db.Numeric(15, 2))
    profit_share_pct = db.Column(db.Numeric(5, 2))
    commission_amount = db.Column(db.Numeric(15, 4))
    status = db.Column(db.Enum('open', 'pending', 'approved', 'paid'),
                       default='open', index=True)
    approved_at = db.Column(db.DateTime, nullable=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    agent = db.relationship('Agent', backref=db.backref('billing_periods', lazy='dynamic'))

    def to_dict(self):
        return {
            'id': self.id,
            'agent_id': self.agent_id,
            'period_start': self.period_start.isoformat() if self.period_start else None,
            'period_end': self.period_end.isoformat() if self.period_end else None,
            'starting_capital': float(self.starting_capital) if self.starting_capital else None,
            'ending_capital': float(self.ending_capital) if self.ending_capital else None,
            'gross_pnl': float(self.gross_pnl) if self.gross_pnl else None,
            'high_water_mark': float(self.high_water_mark) if self.high_water_mark else None,
            'profit_share_pct': float(self.profit_share_pct) if self.profit_share_pct else None,
            'commission_amount': float(self.commission_amount) if self.commission_amount else None,
            'status': self.status,
        }
