from datetime import datetime
from ..extensions import db
import json


class Channel(db.Model):
    __tablename__ = 'channels'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    user_id = db.Column(db.Integer, nullable=False, index=True)
    platform = db.Column(db.Enum('telegram', 'twitter', 'weibo', 'youtube', 'facebook'), nullable=False)
    source_url = db.Column(db.String(500), nullable=False)
    source_name = db.Column(db.String(200))
    status = db.Column(db.Enum('active', 'paused', 'error', 'connecting'), default='connecting')
    last_message_at = db.Column(db.DateTime)
    health_status = db.Column(db.JSON)
    module = db.Column(db.Enum('realtime', 'content'), default='realtime')
    content_type = db.Column(db.Enum('text', 'video', 'mixed'), default='text')
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'user_id': self.user_id,
            'platform': self.platform,
            'source_url': self.source_url,
            'source_name': self.source_name,
            'status': self.status,
            'last_message_at': self.last_message_at.isoformat() if self.last_message_at else None,
            'health_status': self.health_status,
            'module': self.module,
            'content_type': self.content_type,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }
