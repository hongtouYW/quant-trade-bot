"""
Telegram Collector runner - entry point for supervisord.
Loads channel config from DB, starts collector, and refreshes periodically.
"""
import asyncio
import logging
import sys
import os
import time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from config import Config

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(name)s] %(levelname)s: %(message)s'
)
logger = logging.getLogger('signalhive.collector.tg_runner')


def load_channels(flask_app):
    """Load active Telegram channels from DB."""
    from app.models.channel import Channel
    with flask_app.app_context():
        channels = Channel.query.filter_by(
            platform='telegram', status='active'
        ).all()
        return [(ch.id, ch.source_url, ch.source_name) for ch in channels]


async def run_collector():
    """Main async entry point."""
    from app import create_app
    from app.extensions import get_redis
    from app.collectors.telegram_collector import TelegramCollector

    flask_app = create_app()
    redis_client = get_redis()

    api_id = flask_app.config.get('TELEGRAM_API_ID', '')
    api_hash = flask_app.config.get('TELEGRAM_API_HASH', '')
    session = flask_app.config.get('TELEGRAM_SESSION_NAME', 'signalhive_tg')

    if not api_id or not api_hash:
        logger.warning("Telegram API credentials not configured. Collector will idle.")
        while True:
            await asyncio.sleep(60)
            # Re-check config
            api_id = os.environ.get('TELEGRAM_API_ID', '')
            api_hash = os.environ.get('TELEGRAM_API_HASH', '')
            if api_id and api_hash:
                break

    collector = TelegramCollector(api_id, api_hash, session, redis_client)

    connected = await collector.connect()
    if not connected:
        logger.error("Failed to connect to Telegram. Retrying in 30s...")
        await asyncio.sleep(30)
        return

    # Load channels from DB
    channels = load_channels(flask_app)
    for ch_id, url, name in channels:
        await collector.add_channel(ch_id, url, name)

    logger.info(f"Loaded {len(channels)} channels")

    # Start listening with periodic channel refresh
    async def refresh_channels():
        while True:
            await asyncio.sleep(300)  # Every 5 minutes
            try:
                new_channels = load_channels(flask_app)
                current_ids = set(collector.channels.keys())
                new_ids = {ch[0] for ch in new_channels}

                # Add new channels
                for ch_id, url, name in new_channels:
                    if ch_id not in current_ids:
                        await collector.add_channel(ch_id, url, name)

                # Remove deleted channels
                for ch_id in current_ids - new_ids:
                    await collector.remove_channel(ch_id)

                # Health check
                health = await collector.health_check()
                logger.info(f"Health check: {health.value}")
            except Exception as e:
                logger.error(f"Channel refresh error: {e}")

    asyncio.create_task(refresh_channels())
    await collector.listen()


def main():
    logger.info("SignalHive Telegram Collector starting...")
    while True:
        try:
            asyncio.run(run_collector())
        except KeyboardInterrupt:
            logger.info("Collector shutting down...")
            break
        except Exception as e:
            logger.error(f"Collector crashed: {e}")
            time.sleep(10)


if __name__ == '__main__':
    main()
