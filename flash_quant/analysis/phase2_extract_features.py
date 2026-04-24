#!/usr/bin/env python3
"""
Phase 2: 给每笔交易提取开仓前的市场特征
在服务器上运行 (需要连 Binance)
"""
import json
import time
import ccxt
from datetime import datetime, timezone, timedelta
from collections import defaultdict

IN_FILE = '/opt/flash_quant/analysis/trades_raw.json'
OUT_FILE = '/opt/flash_quant/analysis/trades_with_features.json'


def ema(data, period):
    """简单 EMA"""
    if len(data) < period:
        return None
    k = 2 / (period + 1)
    val = sum(data[:period]) / period
    for p in data[period:]:
        val = p * k + val * (1 - k)
    return val


def rsi(closes, period=14):
    """RSI"""
    if len(closes) < period + 1:
        return None
    gains, losses = [], []
    for i in range(1, len(closes)):
        d = closes[i] - closes[i-1]
        gains.append(d if d > 0 else 0)
        losses.append(-d if d < 0 else 0)

    avg_g = sum(gains[:period]) / period
    avg_l = sum(losses[:period]) / period

    for i in range(period, len(gains)):
        avg_g = (avg_g * (period - 1) + gains[i]) / period
        avg_l = (avg_l * (period - 1) + losses[i]) / period

    if avg_l == 0:
        return 100
    rs = avg_g / avg_l
    return 100 - 100 / (1 + rs)


def atr(highs, lows, closes, period=14):
    """ATR (波动率)"""
    if len(closes) < period + 1:
        return None
    trs = []
    for i in range(1, len(closes)):
        tr = max(
            highs[i] - lows[i],
            abs(highs[i] - closes[i-1]),
            abs(lows[i] - closes[i-1])
        )
        trs.append(tr)
    return sum(trs[-period:]) / period if len(trs) >= period else None


def fetch_klines(exchange, symbol, before_ts_ms, n_hours=50):
    """拉开仓前 n_hours 的 1H K 线"""
    pair = symbol.replace('USDT', '/USDT')
    try:
        since = before_ts_ms - (n_hours * 60 * 60 * 1000)
        klines = exchange.fetch_ohlcv(pair, '1h', since=since, limit=n_hours + 2)
        # 过滤掉 opened_at 之后的 (只要之前的)
        return [k for k in klines if k[0] < before_ts_ms]
    except Exception as e:
        return None


def fetch_btc_context(exchange, before_ts_ms, cache):
    """拉 BTC 大盘上下文 (复用缓存)"""
    key = before_ts_ms // (60 * 60 * 1000)
    if key in cache:
        return cache[key]
    k = fetch_klines(exchange, 'BTCUSDT', before_ts_ms, 50)
    cache[key] = k
    return k


def extract_features(klines, btc_klines):
    """从 K 线提取特征"""
    if not klines or len(klines) < 20:
        return None

    closes = [k[4] for k in klines]
    highs = [k[2] for k in klines]
    lows = [k[3] for k in klines]
    volumes = [k[5] for k in klines]

    last_c = closes[-1]

    features = {}

    # 1. RSI
    features['rsi_14'] = rsi(closes, 14)

    # 2. EMA 趋势
    ema20 = ema(closes, 20)
    ema50 = ema(closes, 50) if len(closes) >= 50 else None
    features['price_vs_ema20_pct'] = ((last_c - ema20) / ema20 * 100) if ema20 else None
    features['price_vs_ema50_pct'] = ((last_c - ema50) / ema50 * 100) if ema50 else None
    features['ema20_above_ema50'] = (ema20 > ema50) if (ema20 and ema50) else None

    # 3. ATR (波动率)
    atr_val = atr(highs, lows, closes, 14)
    features['atr_pct'] = (atr_val / last_c * 100) if atr_val else None

    # 4. 量比 (最近 1h vs 前 10h 均量)
    if len(volumes) >= 11:
        recent_v = volumes[-1]
        prev_avg = sum(volumes[-11:-1]) / 10
        features['volume_ratio'] = recent_v / prev_avg if prev_avg > 0 else None
    else:
        features['volume_ratio'] = None

    # 5. 近 24h 涨跌
    if len(closes) >= 24:
        features['change_24h_pct'] = (last_c - closes[-24]) / closes[-24] * 100
    else:
        features['change_24h_pct'] = None

    # 6. 近 1h 涨跌
    if len(closes) >= 2:
        features['change_1h_pct'] = (last_c - closes[-2]) / closes[-2] * 100

    # 7. 近期波动 (24h 最高/最低)
    if len(closes) >= 24:
        h24 = max(highs[-24:])
        l24 = min(lows[-24:])
        features['range_24h_pct'] = (h24 - l24) / l24 * 100

    # 8. 价格在 24h 区间位置 (0 = 底, 1 = 顶)
    if len(closes) >= 24:
        h24 = max(highs[-24:])
        l24 = min(lows[-24:])
        if h24 > l24:
            features['position_in_range_24h'] = (last_c - l24) / (h24 - l24)

    # 9. BTC 大盘 (作为市场情绪)
    if btc_klines and len(btc_klines) >= 24:
        btc_closes = [k[4] for k in btc_klines]
        btc_last = btc_closes[-1]
        features['btc_change_1h_pct'] = (btc_last - btc_closes[-2]) / btc_closes[-2] * 100 if len(btc_closes) >= 2 else None
        features['btc_change_24h_pct'] = (btc_last - btc_closes[-24]) / btc_closes[-24] * 100
        btc_ema20 = ema(btc_closes, 20)
        features['btc_above_ema20'] = (btc_last > btc_ema20) if btc_ema20 else None

    return features


def main():
    print("Loading trades...")
    with open(IN_FILE) as f:
        data = json.load(f)

    all_trades = data['tp'] + data['sl']
    print(f"Total: {len(all_trades)} trades")

    exchange = ccxt.binance({'options': {'defaultType': 'future'}})
    btc_cache = {}
    results = []

    for i, t in enumerate(all_trades):
        # 解析时间
        ot = t['opened_at']
        try:
            if isinstance(ot, str):
                dt = datetime.fromisoformat(ot.replace('Z', '+00:00'))
            else:
                dt = datetime.fromtimestamp(ot / 1000, tz=timezone.utc)
            opened_ms = int(dt.timestamp() * 1000)
        except Exception as e:
            print(f"  #{t['id']} {t['symbol']}: time parse error {e}")
            continue

        # 拉 K 线
        klines = fetch_klines(exchange, t['symbol'], opened_ms, 50)
        if not klines:
            print(f"  #{t['id']} {t['symbol']}: no klines")
            continue

        # 拉 BTC
        btc = fetch_btc_context(exchange, opened_ms, btc_cache)

        # 提取特征
        feats = extract_features(klines, btc)
        if not feats:
            continue

        results.append({
            'id': t['id'],
            'symbol': t['symbol'],
            'direction': t['direction'],
            'leverage': t['leverage'],
            'close_reason': t['close_reason'],
            'pnl': t['pnl'],
            'pnl_pct': t['pnl_pct'],
            'opened_at': t['opened_at'],
            'features': feats,
        })

        if (i + 1) % 10 == 0:
            print(f"  Progress: {i+1}/{len(all_trades)}")
        time.sleep(0.05)  # rate limit

    print(f"\nExtracted features for {len(results)}/{len(all_trades)} trades")

    with open(OUT_FILE, 'w') as f:
        json.dump({'trades': results}, f, default=str, indent=2)
    print(f"Saved to {OUT_FILE}")


if __name__ == '__main__':
    main()
