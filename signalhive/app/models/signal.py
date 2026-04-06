from datetime import datetime
from ..extensions import db


class RawMessage(db.Model):
    __tablename__ = 'raw_messages'

    id = db.Column(db.BigInteger, primary_key=True, autoincrement=True)
    channel_id = db.Column(db.Integer, nullable=False, index=True)
    message_text = db.Column(db.Text)
    message_url = db.Column(db.String(500))
    author_name = db.Column(db.String(200))
    passed_prefilter = db.Column(db.Boolean, default=False)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'channel_id': self.channel_id,
            'message_text': self.message_text,
            'message_url': self.message_url,
            'author_name': self.author_name,
            'passed_prefilter': self.passed_prefilter,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }


class Signal(db.Model):
    __tablename__ = 'signals'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    message_id = db.Column(db.BigInteger, nullable=False)
    channel_id = db.Column(db.Integer, nullable=False)
    coin = db.Column(db.String(20), nullable=False)
    direction = db.Column(db.Enum('LONG', 'SHORT'))
    llm_confidence = db.Column(db.Numeric(4, 3))
    final_score = db.Column(db.Numeric(5, 2))
    entry_hint = db.Column(db.Numeric(20, 8))
    tp_hint = db.Column(db.Numeric(20, 8))
    sl_hint = db.Column(db.Numeric(20, 8))
    reasoning = db.Column(db.Text)
    source_text = db.Column(db.Text)
    ttl_seconds = db.Column(db.Integer, default=3600)
    expires_at = db.Column(db.DateTime)
    status = db.Column(db.Enum('active', 'expired', 'executed', 'invalidated'), default='active')
    actual_result = db.Column(db.Enum('win', 'loss', 'pending', 'expired'), default='pending')
    signal_type = db.Column(db.Enum('action', 'trend'), default='action')
    entry_zone = db.Column(db.String(50))
    target_zone = db.Column(db.String(50))
    invalidation = db.Column(db.Numeric(20, 8))
    timeframe = db.Column(db.String(50))
    content_summary = db.Column(db.Text)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'message_id': self.message_id,
            'channel_id': self.channel_id,
            'coin': self.coin,
            'direction': self.direction,
            'llm_confidence': float(self.llm_confidence) if self.llm_confidence else None,
            'final_score': float(self.final_score) if self.final_score else None,
            'entry_hint': float(self.entry_hint) if self.entry_hint else None,
            'tp_hint': float(self.tp_hint) if self.tp_hint else None,
            'sl_hint': float(self.sl_hint) if self.sl_hint else None,
            'reasoning': self.reasoning,
            'source_text': self.source_text,
            'ttl_seconds': self.ttl_seconds,
            'expires_at': self.expires_at.isoformat() if self.expires_at else None,
            'status': self.status,
            'actual_result': self.actual_result,
            'signal_type': self.signal_type,
            'created_at': self.created_at.isoformat() if self.created_at else None,
        }

    @property
    def is_expired(self):
        if self.expires_at and datetime.utcnow() > self.expires_at:
            return True
        return False


class SourceAccuracy(db.Model):
    __tablename__ = 'source_accuracy'

    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    channel_id = db.Column(db.Integer, nullable=False)
    author_name = db.Column(db.String(200))
    total_signals = db.Column(db.Integer, default=0)
    win_count = db.Column(db.Integer, default=0)
    loss_count = db.Column(db.Integer, default=0)
    accuracy_rate = db.Column(db.Numeric(5, 4))
    avg_roi = db.Column(db.Numeric(8, 4))
    last_updated = db.Column(db.DateTime)

    __table_args__ = (
        db.UniqueConstraint('channel_id', 'author_name', name='uk_channel_author'),
    )

    def to_dict(self):
        return {
            'id': self.id,
            'channel_id': self.channel_id,
            'author_name': self.author_name,
            'total_signals': self.total_signals,
            'win_count': self.win_count,
            'loss_count': self.loss_count,
            'accuracy_rate': float(self.accuracy_rate) if self.accuracy_rate else None,
            'avg_roi': float(self.avg_roi) if self.avg_roi else None,
            'last_updated': self.last_updated.isoformat() if self.last_updated else None,
        }
