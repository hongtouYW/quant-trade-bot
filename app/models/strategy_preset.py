"""Strategy Preset Model"""
import json
from datetime import datetime, timezone
from ..extensions import db


class StrategyPreset(db.Model):
    __tablename__ = 'strategy_presets'

    id = db.Column(db.Integer, primary_key=True)
    version = db.Column(db.String(20), unique=True, nullable=False)
    label = db.Column(db.String(100), nullable=False)
    description = db.Column(db.Text)
    config = db.Column(db.Text, nullable=False)  # JSON as text for MariaDB 5.5
    is_active = db.Column(db.Boolean, default=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    def to_dict(self):
        return {
            'version': self.version,
            'label': self.label,
            'description': self.description,
            'config': json.loads(self.config) if self.config else {},
            'is_active': self.is_active,
        }
