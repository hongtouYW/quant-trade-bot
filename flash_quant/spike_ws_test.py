#!/usr/bin/env python3
"""
Flash Quant - Spike Test
验证 Binance WebSocket + K线 + 延迟 + 量比
运行: python3 spike_ws_test.py
退出: Ctrl+C
"""

import asyncio
import json
import time
import sys
from collections import deque, defaultdict
from datetime import datetime
import statistics

import websockets

# 配置
SYMBOLS = [
    "btcusdt", "ethusdt", "solusdt", "bnbusdt", "xrpusdt",
    "dogeusdt", "adausdt", "avaxusdt", "linkusdt", "dotusdt",
    "nearusdt", "aptusdt", "atomusdt", "trxusdt", "suiusdt",
    "pepeusdt", "arbusdt", "opusdt", "wifusdt", "seiusdt",
]

WS_URL = "wss://fstream.binance.com/stream?streams=" + "/".join(f"{s}@kline_5m" for s in SYMBOLS)

# 状态
kline_volumes = defaultdict(lambda: deque(maxlen=20))
msg_count = 0
latencies = deque(maxlen=10000)
kline_count = defaultdict(int)
vol_signals = []
errors = 0
start_time = time.time()


def percentile(data, p):
    """简单百分位计算"""
    if not data:
        return 0
    s = sorted(data)
    k = (len(s) - 1) * p / 100
    f = int(k)
    c = f + 1 if f + 1 < len(s) else f
    return s[f] + (k - f) * (s[c] - s[f])


def handle_message(raw):
    global msg_count, errors

    msg_count += 1
    try:
        data = json.loads(raw)
    except Exception:
        errors += 1
        return

    k = data.get("data", {}).get("k")
    if not k:
        return

    symbol = k["s"]
    event_ms = data["data"]["E"]
    now_ms = int(time.time() * 1000)
    lat = now_ms - event_ms
    latencies.append(lat)

    is_closed = k["x"]

    # 每 300 条打印状态
    if msg_count % 300 == 0:
        recent = list(latencies)[-300:]
        avg = statistics.mean(recent) if recent else 0
        p99 = percentile(recent, 99) if len(recent) >= 10 else 0
        elapsed = time.time() - start_time
        print(f"  [{datetime.now().strftime('%H:%M:%S')}] msgs={msg_count} rate={msg_count/elapsed:.0f}/s lat_avg={avg:.0f}ms p99={p99:.0f}ms syms={len(kline_volumes)}")

    if not is_closed:
        return

    # K线收盘
    kline_count[symbol] += 1
    vol = float(k["v"])
    o, h, l, c = float(k["o"]), float(k["h"]), float(k["l"]), float(k["c"])

    kline_volumes[symbol].append(vol)
    vols = list(kline_volumes[symbol])

    if len(vols) < 2:
        return

    avg_vol = statistics.mean(vols[:-1])
    vol_ratio = vols[-1] / avg_vol if avg_vol > 0 else 0

    body = abs(c - o)
    uw = h - max(o, c)
    lw = min(o, c) - l
    total = body + uw + lw
    body_ratio = body / total if total > 0 else 0

    pchg = (c - o) / o * 100 if o > 0 else 0

    tag = ""
    if vol_ratio >= 5:
        tag = " 🔥🔥🔥 TIER1!"
    elif vol_ratio >= 3:
        tag = " 🔥 HIGH"
    elif vol_ratio >= 2:
        tag = " ⚡"

    wick = "✅" if body_ratio >= 0.55 else "❌wick"

    print(f"  📊 {symbol:12s} close={c:>10.2f} chg={pchg:>+.2f}% volR={vol_ratio:.1f}x body={body_ratio:.0%}{wick} lat={lat}ms{tag}")

    if vol_ratio >= 3:
        vol_signals.append((symbol, vol_ratio, body_ratio))


def print_report():
    elapsed = time.time() - start_time
    lat_list = list(latencies)

    print(f"\n{'='*80}")
    print(f"📊 SPIKE REPORT")
    print(f"{'='*80}")
    print(f"  运行: {elapsed:.0f}s | 消息: {msg_count} | 错误: {errors}")

    if lat_list:
        avg = statistics.mean(lat_list)
        p50 = percentile(lat_list, 50)
        p95 = percentile(lat_list, 95)
        p99 = percentile(lat_list, 99)
        mx = max(lat_list)
        print(f"\n  📡 延迟: avg={avg:.0f}ms p50={p50:.0f}ms p95={p95:.0f}ms p99={p99:.0f}ms max={mx:.0f}ms")
        print(f"  {'✅' if p99 < 500 else '❌'} AC-5: P99={p99:.0f}ms {'< 500ms PASS' if p99 < 500 else '>= 500ms FAIL'}")

    if kline_count:
        total_klines = sum(kline_count.values())
        print(f"\n  📊 K线收盘: {total_klines} 根 ({len(kline_count)} 个币)")
        for sym in sorted(kline_count, key=kline_count.get, reverse=True)[:5]:
            print(f"     {sym}: {kline_count[sym]}")

    if vol_signals:
        print(f"\n  🔥 高量比信号 (≥3x): {len(vol_signals)}")
        for s, vr, br in vol_signals:
            print(f"     {s}: {vr:.1f}x body={br:.0%}")

    ws_ok = msg_count > 100
    sym_ok = len(kline_volumes) >= 5
    lat_ok = percentile(lat_list, 99) < 500 if lat_list else False
    vol_ok = sum(kline_count.values()) > 0
    err_ok = errors / max(msg_count, 1) < 0.01

    print(f"\n  {'✅' if ws_ok else '❌'} WebSocket 连接: {'PASS' if ws_ok else 'FAIL'}")
    print(f"  {'✅' if sym_ok else '❌'} 多币种订阅: {'PASS' if sym_ok else 'FAIL'}")
    print(f"  {'✅' if lat_ok else '❌'} 延迟 P99<500ms: {'PASS' if lat_ok else 'FAIL'}")
    print(f"  {'✅' if vol_ok else '⏳'} 量比计算: {'PASS' if vol_ok else 'WAITING (需等K线收盘)'}")
    print(f"  {'✅' if err_ok else '❌'} 错误率<1%: {'PASS' if err_ok else 'FAIL'}")

    if ws_ok and sym_ok and lat_ok and err_ok:
        print(f"\n  🎉 核心检查 PASSED — 可以进入 Sprint 1!")
    print()


async def main():
    print(f"🚀 Flash Quant Spike | {len(SYMBOLS)} symbols | 5min kline")
    print(f"   等待 K线收盘 (最多 5 分钟)...\n")

    retries = 0
    while retries < 10:
        try:
            async with websockets.connect(WS_URL, ping_interval=20, ping_timeout=10) as ws:
                retries = 0
                print(f"  ✅ WebSocket 已连接!\n")
                async for msg in ws:
                    handle_message(msg)
        except Exception as e:
            retries += 1
            wait = min(2 ** retries, 30)
            print(f"  ⚠️ {e} | 重连 {retries}/10 in {wait}s...")
            await asyncio.sleep(wait)


if __name__ == "__main__":
    loop = asyncio.new_event_loop()
    try:
        loop.run_until_complete(main())
    except KeyboardInterrupt:
        print_report()
    except Exception as e:
        print(f"❌ {e}")
        import traceback
        traceback.print_exc()
        print_report()
    finally:
        loop.close()
