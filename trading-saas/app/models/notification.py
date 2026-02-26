"""Notification Model - In-app notifications for agents."""
from datetime import datetime, timezone
from ..extensions import db


class Notification(db.Model):
    __tablename__ = 'notifications'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    agent_id = db.Column(db.Integer, db.ForeignKey('agents.id'), nullable=False)
    type = db.Column(db.String(30), nullable=False)  # trade, risk, bot, billing, system
    title = db.Column(db.String(200), nullable=False)
    message = db.Column(db.Text)
    is_read = db.Column(db.Boolean, default=False)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           index=True)
    read_at = db.Column(db.DateTime)

    __table_args__ = (
        db.Index('idx_agent_read', 'agent_id', 'is_read'),
        db.Index('idx_agent_created', 'agent_id', 'created_at'),
    )

    def to_dict(self):
        return {
            'id': self.id,
            'type': self.type,
            'title': self.title,
            'message': self.message,
            'is_read': self.is_read,
            'created_at': self.created_at.isoformat() if self.created_at else None,
            'read_at': self.read_at.isoformat() if self.read_at else None,
        }
