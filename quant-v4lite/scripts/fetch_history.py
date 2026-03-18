"""
拉取历史 K 线数据用于回测
用法: python scripts/fetch_history.py [--days 90] [--symbols 30] [--output data/history]
"""
import asyncio
import json
import sys
import argparse
from pathlib import Path
from datetime import datetime, timedelta

ROOT = Path(__file__).parent.parent
sys.path.insert(0, str(ROOT))

from dotenv import load_dotenv
load_dotenv(ROOT / ".env")

from src.core.config import Config
from src.exchange.binance_client import BinanceClient


async def fetch_history(days: int, num_symbols: int, output_dir: str):
    config = Config()
    cfg = config.data

    exchange = BinanceClient(
        cfg['account']['api_key'],
        cfg['account']['api_secret'],
    )

    print(f"正在加载市场信息...")
    markets = await exchange.load_markets()
    symbols = list(markets.values())[:num_symbols]
    print(f"共 {len(symbols)} 个交易对")

    out_path = ROOT / output_dir
    out_path.mkdir(parents=True, exist_ok=True)

    since = int((datetime.utcnow() - timedelta(days=days)).timestamp() * 1000)
    timeframes = ['1h', '15m']

    for i, sym_info in enumerate(symbols):
        symbol = sym_info.symbol
        sym_dir = out_path / symbol.replace('/', '_').replace(':', '_')
        sym_dir.mkdir(exist_ok=True)

        for tf in timeframes:
            print(f"[{i+1}/{len(symbols)}] {symbol} {tf}...", end=' ')
            try:
                klines = await exchange.fetch_klines(symbol, tf, limit=1500)
                data = []
                for k in klines:
                    data.append({
                        'timestamp': k.timestamp,
                        'open': k.open,
                        'high': k.high,
                        'low': k.low,
                        'close': k.close,
                        'volume': k.volume,
                        'quote_volume': k.quote_volume,
                        'taker_buy_volume': k.taker_buy_volume,
                    })

                filepath = sym_dir / f"{tf}.json"
                with open(filepath, 'w') as f:
                    json.dump(data, f)
                print(f"{len(data)} bars")
            except Exception as e:
                print(f"错误: {e}")

            await asyncio.sleep(0.2)  # 避免限频

    await exchange.close()
    print(f"\n完成! 数据保存在 {out_path}")


def main():
    parser = argparse.ArgumentParser(description='拉取历史K线数据')
    parser.add_argument('--days', type=int, default=90, help='拉取天数 (默认90)')
    parser.add_argument('--symbols', type=int, default=30, help='交易对数量 (默认30)')
    parser.add_argument('--output', type=str, default='data/history', help='输出目录')
    args = parser.parse_args()

    asyncio.run(fetch_history(args.days, args.symbols, args.output))


if __name__ == '__main__':
    main()
