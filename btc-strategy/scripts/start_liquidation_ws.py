#!/usr/bin/env python3
"""Start the liquidation WebSocket daemon."""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import asyncio
from config.settings import Settings
from collectors.liquidation_collector import LiquidationCollector


def main():
    settings = Settings.load()
    collector = LiquidationCollector(settings, flush_interval=300)

    print(f"Starting liquidation WebSocket for {settings.data.symbol}...")
    print(f"Flush interval: {collector.flush_interval}s")
    print(f"Data dir: {settings.data.data_dir / 'liquidations'}")

    asyncio.run(collector.start())


if __name__ == "__main__":
    main()
