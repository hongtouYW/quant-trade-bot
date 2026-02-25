"""Bot State Model"""
from datetime import datetime, timezone
from ..extensions import db


class BotState(db.Model):
    __tablename__ = 'bot_state'

    id = db.Column(db.Integer, primary_key=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id'),
                         unique=True, nullable=False)
    status = db.Column(db.Enum('stopped', 'running', 'paused', 'error'),
                       default='stopped', index=True)
    last_scan_at = db.Column(db.DateTime, nullable=True)
    last_error = db.Column(db.Text, nullable=True)
    error_count = db.Column(db.Integer, default=0)
    risk_score = db.Column(db.Integer, default=0)
    risk_position_multiplier = db.Column(db.Numeric(3, 2), default=1.00)
    peak_capital = db.Column(db.Numeric(15, 2))
    scan_count = db.Column(db.Integer, default=0)
    pid = db.Column(db.Integer, nullable=True)
    started_at = db.Column(db.DateTime, nullable=True)
    updated_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           onupdate=lambda: datetime.now(timezone.utc))

    def to_dict(self):
        return {
            'status': self.status,
            'last_scan_at': self.last_scan_at.isoformat() if self.last_scan_at else None,
            'last_error': self.last_error,
            'error_count': self.error_count,
            'risk_score': self.risk_score,
            'scan_count': self.scan_count,
            'started_at': self.started_at.isoformat() if self.started_at else None,
        }
