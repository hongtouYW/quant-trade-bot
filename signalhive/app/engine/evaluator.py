"""
Evaluator: Signal scoring engine.
Score = confidence(30%) + accuracy(30%) + consensus(20%) + freshness(20%)
Scale: 0-100
"""
import logging
from datetime import datetime, timedelta
from ..extensions import db
from ..models.signal import Signal, SourceAccuracy

logger = logging.getLogger(__name__)

W_CONFIDENCE = 0.30
W_ACCURACY = 0.30
W_CONSENSUS = 0.20
W_FRESHNESS = 0.20


def get_source_accuracy(channel_id: int, author_name: str) -> float:
    """Get historical accuracy rate for a source. Default 0.5 for new sources."""
    acc = SourceAccuracy.query.filter_by(
        channel_id=channel_id, author_name=author_name
    ).first()
    if acc and acc.total_signals and acc.total_signals >= 10:
        return float(acc.accuracy_rate) if acc.accuracy_rate else 0.5
    return 0.5  # default for new sources


def get_consensus(coin: str, direction: str, window_minutes: int = 60) -> float:
    """
    Cross-source consensus for a coin+direction in recent window.
    Returns -1.0 to 1.0:
      +1 = all sources agree on this direction
       0 = split
      -1 = all sources disagree
    """
    cutoff = datetime.utcnow() - timedelta(minutes=window_minutes)
    recent = Signal.query.filter(
        Signal.coin == coin,
        Signal.created_at >= cutoff,
        Signal.status.in_(['active', 'executed'])
    ).all()

    if len(recent) <= 1:
        return 0.0  # no consensus data

    same = sum(1 for s in recent if s.direction == direction)
    total = len(recent)
    # Scale from -1 to 1: all same = +1, all opposite = -1
    return (2 * same / total) - 1


def calculate_signal_score(
    confidence: float,
    channel_id: int,
    author_name: str,
    coin: str,
    direction: str,
    created_at: datetime = None,
    ttl_seconds: int = 3600
) -> float:
    """
    Calculate composite signal score (0-100).
    """
    if created_at is None:
        created_at = datetime.utcnow()

    # 1. LLM confidence (0-1)
    conf_score = max(0.0, min(1.0, confidence))

    # 2. Source accuracy (0-1)
    acc_score = get_source_accuracy(channel_id, author_name)

    # 3. Consensus (-1 to 1, normalized to 0-1)
    consensus = get_consensus(coin, direction, window_minutes=60)
    cons_score = (consensus + 1) / 2

    # 4. Freshness (1.0 -> 0.0 over TTL)
    age = (datetime.utcnow() - created_at).total_seconds()
    fresh_score = max(0.0, 1.0 - age / max(ttl_seconds, 1))

    score = (
        conf_score * W_CONFIDENCE +
        acc_score * W_ACCURACY +
        cons_score * W_CONSENSUS +
        fresh_score * W_FRESHNESS
    ) * 100

    return round(score, 2)


def update_source_accuracy(channel_id: int, author_name: str, outcome: str):
    """
    Update accuracy tracking for a source after signal outcome.
    outcome: 'win' or 'loss'
    """
    acc = SourceAccuracy.query.filter_by(
        channel_id=channel_id, author_name=author_name
    ).first()

    if not acc:
        acc = SourceAccuracy(
            channel_id=channel_id,
            author_name=author_name,
            total_signals=0,
            win_count=0,
            loss_count=0,
        )
        db.session.add(acc)

    acc.total_signals = (acc.total_signals or 0) + 1
    if outcome == 'win':
        acc.win_count = (acc.win_count or 0) + 1
    elif outcome == 'loss':
        acc.loss_count = (acc.loss_count or 0) + 1

    if acc.total_signals > 0:
        acc.accuracy_rate = acc.win_count / acc.total_signals
    acc.last_updated = datetime.utcnow()

    db.session.commit()
