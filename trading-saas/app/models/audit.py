"""Audit Log Model"""
import json
from datetime import datetime, timezone
from ..extensions import db


class AuditLog(db.Model):
    __tablename__ = 'audit_log'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    user_type = db.Column(db.Enum('admin', 'agent'), nullable=False)
    user_id = db.Column(db.Integer, nullable=False)
    action = db.Column(db.String(100), nullable=False, index=True)
    resource = db.Column(db.String(100))
    details = db.Column(db.Text)  # JSON stored as text for MariaDB 5.5 compat
    ip_address = db.Column(db.String(45))
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc),
                           index=True)

    __table_args__ = (
        db.Index('idx_user', 'user_type', 'user_id'),
    )

    def to_dict(self):
        return {
            'id': self.id,
            'user_type': self.user_type,
            'user_id': self.user_id,
            'action': self.action,
            'resource': self.resource,
            'details': json.loads(self.details) if self.details else None,
            'ip_address': self.ip_address,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }
