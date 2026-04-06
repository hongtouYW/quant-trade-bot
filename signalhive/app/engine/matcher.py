"""
Matcher: Match scored signals to user strategies.
Checks: bound channels, min score, direction filter, position limits.
"""
import logging
from typing import List
from ..models.strategy import Strategy
from ..models.trade import SignalTrade
from ..extensions import db

logger = logging.getLogger(__name__)


def find_matching_strategies(signal_data: dict) -> List[Strategy]:
    """
    Find all active strategies that should execute on this signal.

    signal_data keys: channel_id, coin, direction, final_score
    """
    channel_id = signal_data.get('channel_id')
    final_score = signal_data.get('final_score', 0)
    direction = signal_data.get('direction')

    strategies = Strategy.query.filter_by(status='active').all()
    matched = []

    for strat in strategies:
        # Check min score
        min_score = float(strat.min_signal_score or 0)
        if final_score < min_score:
            continue

        # Check bound channels
        bound = strat.bound_channels or []
        if bound and channel_id not in bound:
            continue

        # Check direction filter (if set in config)
        config = strat.config or {}
        allowed_directions = config.get('allowed_directions')
        if allowed_directions and direction not in allowed_directions:
            continue

        # Check max open positions
        max_positions = config.get('max_positions', 20)
        open_count = SignalTrade.query.filter_by(
            strategy_id=strat.id, status='open'
        ).count()
        if open_count >= max_positions:
            logger.info(f"Strategy {strat.id} at max positions ({open_count}/{max_positions})")
            continue

        # Check consensus requirement
        if config.get('require_consensus', False):
            # If consensus is low (signal score already reflects it), skip
            if final_score < (min_score + 10):
                continue

        matched.append(strat)

    return matched
