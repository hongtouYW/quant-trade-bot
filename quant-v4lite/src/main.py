import asyncio
import signal
import sys
from pathlib import Path

# 确保项目根目录在 Python 路径中
ROOT = Path(__file__).parent.parent
sys.path.insert(0, str(ROOT))

from dotenv import load_dotenv
load_dotenv(ROOT / ".env")

from src.bot.engine import TradingEngine


async def main():
    config_path = sys.argv[1] if len(sys.argv) > 1 else None
    engine = TradingEngine(config_path)

    loop = asyncio.get_event_loop()

    def _shutdown():
        asyncio.ensure_future(engine.shutdown())

    loop.add_signal_handler(signal.SIGINT, _shutdown)
    loop.add_signal_handler(signal.SIGTERM, _shutdown)

    await engine.run()


if __name__ == '__main__':
    asyncio.run(main())
