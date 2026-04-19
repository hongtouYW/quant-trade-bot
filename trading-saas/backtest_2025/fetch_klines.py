#!/usr/bin/env python3
"""Fetch 1H klines from Binance Futures for 2025-01-01 to 2025-12-31.
Caches each symbol to JSON for reuse.
"""
import os
import json
import time
import requests
from datetime import datetime

BASE = 'https://fapi.binance.com/fapi/v1/klines'
CACHE_DIR = os.path.join(os.path.dirname(__file__), 'data', 'klines_cache')
os.makedirs(CACHE_DIR, exist_ok=True)

# From auto_trader_v6.py (active 111 coins after skip_coins)
WATCH_SYMBOLS = [
    'BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'DOGE', 'ADA', 'AVAX', 'LINK', 'DOT',
    'NEAR', 'SUI', 'APT', 'ATOM', 'FTM', 'HBAR', 'XLM', 'ETC', 'LTC', 'BCH',
    'ALGO', 'ICP', 'FIL', 'XMR', 'TRX',
    'ARB', 'OP', 'MATIC', 'AAVE', 'UNI', 'CRV', 'DYDX', 'INJ', 'SEI',
    'STX', 'RUNE', 'SNX', 'COMP', 'MKR', 'LDO',
    'TAO', 'RENDER', 'FET', 'WLD', 'AGIX', 'OCEAN', 'ARKM', 'PENGU',
    'AIXBT', 'GRASS', 'CGPT',
    'TIA', 'JUP', 'PYTH', 'JTO', 'ENA', 'STRK', 'ZRO', 'WIF',
    'SHIB', 'FLOKI', 'TRUMP',
    'VET', 'AXS', 'ROSE', 'CHZ', 'ENJ', 'SAND',
    'ONDO', 'PENDLE', 'EIGEN', 'ETHFI', 'TON',
    'MANA', 'GALA', 'IMX', 'ORDI', 'SXP', 'ZEC', 'DASH',
    'WAVES', 'GRT', 'THETA', 'IOTA', 'NEO', 'KAVA', 'ONE', 'CELO',
    'CAKE', 'SUSHI', 'GMX', 'ENS', 'BLUR', 'PEOPLE', 'MASK',
    '1INCH', 'ANKR', 'AR', 'FLOW', 'EGLD', 'KAS', 'JASMY', 'NOT',
    'NEIRO', 'PNUT', 'POPCAT', 'MEME',
    'USUAL', 'ME', 'SONIC',
    'HYPE',
    'YGG', 'PORTAL', 'MANTA', 'ZK', 'W', 'SAGA', 'RSR',
]

SYMBOL_1000 = {
    'BONK': '1000BONKUSDT', 'PEPE': '1000PEPEUSDT',
    'SHIB': '1000SHIBUSDT', 'FLOKI': '1000FLOKIUSDT', 'NEIRO': '1000NEIROUSDT',
}

START_MS = int(datetime(2025, 1, 1).timestamp() * 1000)
END_MS = int(datetime(2025, 12, 31, 23, 59, 59).timestamp() * 1000)


def bsym(symbol):
    return SYMBOL_1000.get(symbol, f'{symbol}USDT')


def fetch_all(symbol):
    """Fetch all 1H klines for 2025 for given symbol."""
    cache_path = os.path.join(CACHE_DIR, f'{symbol}.json')
    if os.path.exists(cache_path):
        with open(cache_path) as f:
            data = json.load(f)
            if len(data) > 8000:  # full year ~8760
                return data, True

    binance_sym = bsym(symbol)
    all_bars = []
    cur = START_MS
    while cur < END_MS:
        url = f'{BASE}?symbol={binance_sym}&interval=1h&startTime={cur}&endTime={END_MS}&limit=1500'
        for attempt in range(3):
            try:
                r = requests.get(url, timeout=20)
                if r.status_code != 200:
                    if r.status_code == 400:
                        return [], False  # symbol not listed
                    time.sleep(2)
                    continue
                data = r.json()
                if not isinstance(data, list) or not data:
                    return all_bars, bool(all_bars)
                all_bars.extend(data)
                cur = int(data[-1][0]) + 3600 * 1000
                time.sleep(0.15)
                break
            except Exception as e:
                print(f'    [{symbol}] retry {attempt+1}: {e}')
                time.sleep(2)
        else:
            return all_bars, False

    # Convert to compact format: [time, open, high, low, close, volume]
    compact = [[int(b[0]), float(b[1]), float(b[2]), float(b[3]), float(b[4]), float(b[5])] for b in all_bars]
    with open(cache_path, 'w') as f:
        json.dump(compact, f)
    return compact, True


def main():
    ok, fail = 0, []
    for i, sym in enumerate(WATCH_SYMBOLS, 1):
        t0 = time.time()
        try:
            bars, success = fetch_all(sym)
            dt = time.time() - t0
            if success and len(bars) > 100:
                print(f'[{i}/{len(WATCH_SYMBOLS)}] {sym}: {len(bars)} bars ({dt:.1f}s)')
                ok += 1
            else:
                print(f'[{i}/{len(WATCH_SYMBOLS)}] {sym}: FAILED ({len(bars)} bars)')
                fail.append(sym)
        except Exception as e:
            print(f'[{i}/{len(WATCH_SYMBOLS)}] {sym}: ERROR {e}')
            fail.append(sym)

    print(f'\nDone: {ok} OK, {len(fail)} failed')
    if fail:
        print('Failed:', fail)


if __name__ == '__main__':
    main()
