"""
Telegram Collector: Uses Telethon to listen to Telegram channels/groups.
Pushes RawMessage to Redis Stream for engine consumption.
"""
import asyncio
import logging
import json
import os
from datetime import datetime, timedelta
from typing import Dict, Optional

from telethon import TelegramClient, events
from .base import BaseCollector, HealthStatus, RawMessage

logger = logging.getLogger('signalhive.collector.telegram')


class TelegramCollector(BaseCollector):
    def __init__(self, api_id: str, api_hash: str, session_name: str, redis_client=None):
        self.api_id = int(api_id) if api_id else 0
        self.api_hash = api_hash or ''
        self.session_name = session_name
        self.client: Optional[TelegramClient] = None
        self.redis = redis_client
        self.channels: Dict[int, dict] = {}  # channel_id -> config
        self.last_message_times: Dict[int, datetime] = {}
        self._running = False
        self.stream_key = os.environ.get('STREAM_KEY', 'signalhive:raw_messages')
        self.stream_max_len = 100000

    async def connect(self) -> bool:
        """Connect to Telegram."""
        if not self.api_id or not self.api_hash:
            logger.error("Telegram API credentials not configured")
            return False
        try:
            self.client = TelegramClient(self.session_name, self.api_id, self.api_hash)
            await self.client.start()
            me = await self.client.get_me()
            logger.info(f"Connected to Telegram as {me.username or me.id}")
            return True
        except Exception as e:
            logger.error(f"Failed to connect to Telegram: {e}")
            return False

    async def add_channel(self, channel_id: int, source_url: str, source_name: str = None):
        """Dynamically add a channel to monitor."""
        self.channels[channel_id] = {
            'source_url': source_url,
            'source_name': source_name or source_url,
        }
        self.last_message_times[channel_id] = datetime.utcnow()
        logger.info(f"Added channel: {source_name} ({source_url})")

    async def remove_channel(self, channel_id: int):
        """Stop monitoring a channel."""
        self.channels.pop(channel_id, None)
        self.last_message_times.pop(channel_id, None)
        logger.info(f"Removed channel: {channel_id}")

    async def listen(self):
        """Listen for messages from monitored channels."""
        if not self.client:
            logger.error("Client not connected")
            return

        @self.client.on(events.NewMessage)
        async def handler(event):
            try:
                chat = await event.get_chat()
                chat_id = event.chat_id

                # Find matching channel
                matched_channel_id = None
                for ch_id, ch_config in self.channels.items():
                    # Match by chat ID or URL
                    if str(chat_id) in ch_config.get('source_url', ''):
                        matched_channel_id = ch_id
                        break

                if not matched_channel_id:
                    # Try matching by username
                    chat_username = getattr(chat, 'username', None)
                    if chat_username:
                        for ch_id, ch_config in self.channels.items():
                            if chat_username.lower() in ch_config.get('source_url', '').lower():
                                matched_channel_id = ch_id
                                break

                if not matched_channel_id:
                    return  # Not a monitored channel

                sender = await event.get_sender()
                author = getattr(sender, 'username', None) or str(getattr(sender, 'id', 'unknown'))
                text = event.message.text or ''

                if not text.strip():
                    return

                # Update last message time
                self.last_message_times[matched_channel_id] = datetime.utcnow()

                # Build message URL
                msg_url = None
                if chat_username:
                    msg_url = f"https://t.me/{chat_username}/{event.message.id}"

                # Push to Redis Stream
                if self.redis:
                    self.redis.xadd(
                        self.stream_key,
                        {
                            'channel_id': str(matched_channel_id),
                            'platform': 'telegram',
                            'author': author,
                            'text': text,
                            'url': msg_url or '',
                            'ts': datetime.utcnow().isoformat() + 'Z',
                        },
                        maxlen=self.stream_max_len,
                    )
                    logger.debug(f"Pushed message to Redis Stream (channel={matched_channel_id})")

            except Exception as e:
                logger.error(f"Error handling message: {e}")

        self._running = True
        logger.info("Telegram collector listening...")

        # Keep running until stopped
        while self._running:
            await asyncio.sleep(1)

    async def health_check(self) -> HealthStatus:
        """Check health of all monitored channels."""
        if not self.client or not self.client.is_connected():
            return HealthStatus.DOWN

        now = datetime.utcnow()
        stale_threshold = timedelta(minutes=10)

        stale_count = 0
        for ch_id, last_time in self.last_message_times.items():
            if now - last_time > stale_threshold:
                stale_count += 1
                logger.warning(f"Channel {ch_id} stale: no messages in 10+ minutes")

        if stale_count == 0:
            return HealthStatus.HEALTHY
        elif stale_count < len(self.channels):
            return HealthStatus.DEGRADED
        else:
            return HealthStatus.DOWN

    async def disconnect(self) -> None:
        """Graceful shutdown."""
        self._running = False
        if self.client:
            await self.client.disconnect()
        logger.info("Telegram collector disconnected")
