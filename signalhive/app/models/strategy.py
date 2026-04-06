from datetime import datetime
from ..extensions import db
import json


class Strategy(db.Model):
    __tablename__ = 'strategies'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    user_id = db.Column(db.Integer, nullable=False, index=True)
    name = db.Column(db.String(100))
    template_type = db.Column(db.Enum('conservative', 'balanced', 'aggressive', 'custom'), default='balanced')
    config = db.Column(db.JSON)
    bound_channels = db.Column(db.JSON)
    min_signal_score = db.Column(db.Numeric(5, 2), default=60)
    is_paper = db.Column(db.Boolean, default=True)
    status = db.Column(db.Enum('active', 'paused'), default='active')
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'user_id': self.user_id,
            'name': self.name,
            'template_type': self.template_type,
            'config': self.config,
            'bound_channels': self.bound_channels,
            'min_signal_score': float(self.min_signal_score) if self.min_signal_score else None,
            'is_paper': self.is_paper,
            'status': self.status,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }

    def get_config_value(self, key, default=None):
        if self.config and key in self.config:
            return self.config[key]
        return default
