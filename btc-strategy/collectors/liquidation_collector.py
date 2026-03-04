import asyncio
import json
import signal
import ssl
from datetime import datetime
from pathlib import Path
from typing import Optional

import pandas as pd

from config.settings import Settings
from storage.parquet_store import ParquetStore
from storage.schema import LIQUIDATION_SCHEMA
from utils.time_utils import ms_to_utc8, utc8_now, UTC8
from utils.logger import get_logger

logger = get_logger("LiquidationCollector")


class LiquidationCollector:
    """
    WebSocket liquidation collector.
    Stream: wss://fstream.binance.com/ws/btcusdt@forceOrder
    Runs as a persistent daemon, flushing to parquet periodically.
    """

    def __init__(self, settings: Settings, flush_interval: int = 300):
        self.settings = settings
        self.ws_url = f"{settings.binance.ws_url}/ws/btcusdt@forceOrder"
        self.flush_interval = flush_interval
        self.buffer: list = []
        self.store = ParquetStore(settings.data.data_dir)
        self.running = False
        self.pid_file = settings.data.data_dir / "liquidation_ws.pid"

    async def start(self):
        try:
            import websockets
        except ImportError:
            logger.error("websockets package required. pip install websockets")
            return

        self.running = True
        self._write_pid()

        loop = asyncio.get_event_loop()
        for sig in (signal.SIGTERM, signal.SIGINT):
            loop.add_signal_handler(sig, lambda: asyncio.create_task(self._shutdown()))

        logger.info(f"Starting liquidation WebSocket: {self.ws_url}")
        reconnect_delay = 1.0

        while self.running:
            try:
                ssl_ctx = ssl.create_default_context()
                ssl_ctx.check_hostname = False
                ssl_ctx.verify_mode = ssl.CERT_NONE

                async with websockets.connect(
                    self.ws_url,
                    ssl=ssl_ctx,
                    ping_interval=30,
                    ping_timeout=10,
                    close_timeout=5,
                ) as ws:
                    logger.info("WebSocket connected")
                    reconnect_delay = 1.0

                    flush_task = asyncio.create_task(self._periodic_flush())

                    try:
                        async for msg in ws:
                            if not self.running:
                                break
                            self._handle_message(msg)
                    finally:
                        flush_task.cancel()

            except Exception as e:
                if not self.running:
                    break
                logger.warning(f"WebSocket error: {e}. Reconnecting in {reconnect_delay:.0f}s")
                await asyncio.sleep(reconnect_delay)
                reconnect_delay = min(reconnect_delay * 2, 60.0)

        self._flush_buffer()
        self._remove_pid()
        logger.info("Liquidation collector stopped")

    def _handle_message(self, msg: str):
        try:
            data = json.loads(msg)
            order = data.get("o", {})

            price = float(order.get("p", 0))
            qty = float(order.get("q", 0))
            avg_price = float(order.get("ap", 0))
            filled_qty = float(order.get("z", 0))

            record = {
                "timestamp": ms_to_utc8(order.get("T", int(utc8_now().timestamp() * 1000))),
                "symbol": order.get("s", "BTCUSDT"),
                "side": order.get("S", ""),
                "order_type": order.get("o", ""),
                "original_quantity": qty,
                "price": price,
                "average_price": avg_price,
                "order_status": order.get("X", ""),
                "filled_accumulated_qty": filled_qty,
                "trade_value_usd": avg_price * filled_qty if avg_price and filled_qty else price * qty,
            }
            self.buffer.append(record)

        except Exception as e:
            logger.error(f"Failed to parse liquidation event: {e}")

    async def _periodic_flush(self):
        while self.running:
            await asyncio.sleep(self.flush_interval)
            self._flush_buffer()

    def _flush_buffer(self):
        if not self.buffer:
            return

        records = self.buffer.copy()
        self.buffer.clear()

        df = pd.DataFrame(records)
        pk = LIQUIDATION_SCHEMA["primary_key"]
        written = self.store.write("liquidations", df, primary_key=pk)
        logger.info(f"Flushed {written} liquidation events to parquet")

    async def _shutdown(self):
        logger.info("Shutdown signal received")
        self.running = False

    def _write_pid(self):
        import os
        self.pid_file.parent.mkdir(parents=True, exist_ok=True)
        self.pid_file.write_text(str(os.getpid()))

    def _remove_pid(self):
        if self.pid_file.exists():
            self.pid_file.unlink()

    def get_data_type(self) -> str:
        return "liquidations"
