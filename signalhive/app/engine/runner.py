"""
Engine Runner: Consumes messages from Redis Stream, runs the full pipeline.
Pipeline: prefilter -> extractor -> evaluator -> matcher -> executor
"""
import json
import logging
import time
import sys
import os

# Add parent to path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from datetime import datetime, timedelta
from config import Config

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(name)s] %(levelname)s: %(message)s'
)
logger = logging.getLogger('signalhive.engine')

STREAM_KEY = Config.STREAM_KEY
CONSUMER_GROUP = Config.CONSUMER_GROUP
CONSUMER_NAME = 'engine-worker-1'


def create_flask_app():
    from app import create_app
    return create_app()


def ensure_stream_group(r):
    """Create consumer group if not exists."""
    try:
        r.xgroup_create(STREAM_KEY, CONSUMER_GROUP, id='0', mkstream=True)
        logger.info(f"Created consumer group: {CONSUMER_GROUP}")
    except Exception as e:
        if 'BUSYGROUP' in str(e):
            pass  # Already exists
        else:
            logger.error(f"Error creating consumer group: {e}")


def process_message(msg_data: dict, flask_app):
    """Run full pipeline on a single message."""
    from app.engine.prefilter import prefilter
    from app.engine.extractor import extract_signal
    from app.engine.evaluator import calculate_signal_score
    from app.engine.matcher import find_matching_strategies
    from app.engine.executor import execute_paper_trade
    from app.models.signal import RawMessage, Signal
    from app.extensions import db

    text = msg_data.get('text', '')
    channel_id = int(msg_data.get('channel_id', 0))
    author = msg_data.get('author', 'unknown')
    url = msg_data.get('url', '')
    ts = msg_data.get('ts', '')

    with flask_app.app_context():
        # Save raw message
        raw = RawMessage(
            channel_id=channel_id,
            message_text=text,
            message_url=url,
            author_name=author,
            passed_prefilter=False,
        )
        db.session.add(raw)
        db.session.commit()

        # Layer 1: PreFilter
        if not prefilter(text):
            logger.debug(f"Message filtered out (channel={channel_id})")
            return

        raw.passed_prefilter = True
        db.session.commit()
        logger.info(f"Message passed prefilter (channel={channel_id}, author={author})")

        # Layer 2: LLM Extraction
        signal_data = extract_signal(text, api_key=flask_app.config.get('ANTHROPIC_API_KEY'))
        if not signal_data:
            logger.info("No signal extracted by LLM")
            return

        # Calculate score
        score = calculate_signal_score(
            confidence=signal_data['confidence'],
            channel_id=channel_id,
            author_name=author,
            coin=signal_data['coin'],
            direction=signal_data['direction'],
            ttl_seconds=signal_data.get('ttl_seconds', 3600),
        )

        # Parse price hints
        entry_hint = _parse_price(signal_data.get('entry_hint'))
        tp_hint = _parse_price(signal_data.get('tp_hint'))
        sl_hint = _parse_price(signal_data.get('sl_hint'))

        ttl = signal_data.get('ttl_seconds', 3600)
        now = datetime.utcnow()

        # Save signal
        sig = Signal(
            message_id=raw.id,
            channel_id=channel_id,
            coin=signal_data['coin'],
            direction=signal_data['direction'],
            llm_confidence=signal_data['confidence'],
            final_score=score,
            entry_hint=entry_hint,
            tp_hint=tp_hint,
            sl_hint=sl_hint,
            reasoning=signal_data.get('reasoning', ''),
            source_text=signal_data.get('source_text', text),
            ttl_seconds=ttl,
            expires_at=now + timedelta(seconds=ttl),
            status='active',
            signal_type='action',
        )
        db.session.add(sig)
        db.session.commit()

        logger.info(
            f"Signal created: {sig.coin} {sig.direction} score={score} "
            f"confidence={signal_data['confidence']}"
        )

        # Match to strategies
        match_data = {
            'channel_id': channel_id,
            'coin': sig.coin,
            'direction': sig.direction,
            'final_score': score,
        }
        strategies = find_matching_strategies(match_data)

        for strat in strategies:
            try:
                trade = execute_paper_trade(sig, strat)
                if trade:
                    sig.status = 'executed'
                    db.session.commit()
            except Exception as e:
                logger.error(f"Execution error for strategy {strat.id}: {e}")
                db.session.rollback()


def _parse_price(val):
    """Parse price string to Decimal or None."""
    if not val:
        return None
    try:
        from decimal import Decimal
        clean = str(val).replace(',', '').replace('$', '').strip()
        return Decimal(clean)
    except Exception:
        return None


def run_tp_sl_monitor(flask_app):
    """Check open trades for TP/SL/TTL hits."""
    from app.models.trade import SignalTrade
    from app.models.signal import Signal
    from app.models.strategy import Strategy
    from app.engine.executor import check_tp_sl

    with flask_app.app_context():
        open_trades = SignalTrade.query.filter_by(status='open').all()
        for trade in open_trades:
            try:
                signal = Signal.query.get(trade.signal_id)
                strategy = Strategy.query.get(trade.strategy_id)
                if signal and strategy:
                    check_tp_sl(trade, signal, strategy)
            except Exception as e:
                logger.error(f"TP/SL check error for trade {trade.id}: {e}")


def expire_signals(flask_app):
    """Mark expired signals."""
    from app.models.signal import Signal
    from app.extensions import db

    with flask_app.app_context():
        now = datetime.utcnow()
        expired = Signal.query.filter(
            Signal.status == 'active',
            Signal.expires_at <= now
        ).all()
        for sig in expired:
            sig.status = 'expired'
            if sig.actual_result == 'pending':
                sig.actual_result = 'expired'
        if expired:
            db.session.commit()
            logger.info(f"Expired {len(expired)} signals")


def main():
    """Main engine loop."""
    from app.extensions import get_redis

    flask_app = create_flask_app()
    r = get_redis()
    ensure_stream_group(r)

    logger.info("SignalHive Engine started")

    last_monitor = time.time()
    MONITOR_INTERVAL = 60  # check TP/SL every 60s

    while True:
        try:
            # Read from stream (block for 5 seconds)
            messages = r.xreadgroup(
                CONSUMER_GROUP, CONSUMER_NAME,
                {STREAM_KEY: '>'},
                count=10, block=5000
            )

            if messages:
                for stream, entries in messages:
                    for msg_id, msg_data in entries:
                        try:
                            process_message(msg_data, flask_app)
                            r.xack(STREAM_KEY, CONSUMER_GROUP, msg_id)
                        except Exception as e:
                            logger.error(f"Error processing message {msg_id}: {e}")

            # Periodic TP/SL monitoring
            now = time.time()
            if now - last_monitor >= MONITOR_INTERVAL:
                run_tp_sl_monitor(flask_app)
                expire_signals(flask_app)
                last_monitor = now

        except KeyboardInterrupt:
            logger.info("Engine shutting down...")
            break
        except Exception as e:
            logger.error(f"Engine loop error: {e}")
            time.sleep(5)


if __name__ == '__main__':
    main()
