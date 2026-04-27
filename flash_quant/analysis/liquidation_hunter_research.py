#!/usr/bin/env python3
"""
爆仓猎手研究 — 大跌后反弹规律
用价格代理爆仓事件: 5min K线 -3% AND 量比 5x

关键问题:
  1. 这种事件多频繁? (够不够交易)
  2. 之后 30min/1h/2h 反弹概率多大?
  3. 平均反弹幅度多少?
  4. 风险回报比如何?
"""
import json
import time
from datetime import datetime, timezone, timedelta
from collections import defaultdict


DROP_THRESHOLD = -0.03         # 5min 跌幅 >= 3%
VOLUME_RATIO_MIN = 5.0          # 量比 >= 5x
LOOKBACK_BARS = 20              # 量比基准: 前 20 根 5min 平均量
OBSERVATION_WINDOWS = [6, 12, 24, 48]  # 30min, 1h, 2h, 4h (5min K线数)

SYMBOLS = [
    'BTC/USDT','ETH/USDT','SOL/USDT','BNB/USDT','XRP/USDT',
    'DOGE/USDT','ADA/USDT','AVAX/USDT','LINK/USDT','DOT/USDT',
    'NEAR/USDT','APT/USDT','ATOM/USDT','SUI/USDT','TRX/USDT',
    'LTC/USDT','BCH/USDT','ETC/USDT','UNI/USDT','AAVE/USDT',
    'FIL/USDT','INJ/USDT','ARB/USDT','OP/USDT','MKR/USDT',
    'ICP/USDT','HBAR/USDT','FTM/USDT','THETA/USDT','VET/USDT',
]


def fetch_data(exchange, symbols, start, end):
    print(f"Fetching {len(symbols)} coins 5m...")
    data = {}
    for sym in symbols:
        try:
            print(f"  {sym}...", end=" ", flush=True)
            klines = []
            since = int(start.timestamp() * 1000)
            end_ms = int(end.timestamp() * 1000)
            while since < end_ms:
                batch = exchange.fetch_ohlcv(sym, '5m', since=since, limit=1000)
                if not batch: break
                klines.extend([b for b in batch if b[0] < end_ms])
                since = batch[-1][0] + 1
                if len(batch) < 1000: break
                time.sleep(0.05)
            data[sym] = klines
            print(f"OK {len(klines)}")
        except Exception as e:
            print(f"SKIP {e}")
    return data


def detect_events(klines):
    """检测大跌事件 (代理爆仓)"""
    events = []
    for i in range(LOOKBACK_BARS, len(klines)):
        ts, o, h, l, c, vol = klines[i]
        # 跌幅
        drop_pct = (c - o) / o if o > 0 else 0
        if drop_pct > DROP_THRESHOLD:
            continue
        # 量比
        prev_vols = [klines[j][5] for j in range(i - LOOKBACK_BARS, i)]
        avg_vol = sum(prev_vols) / len(prev_vols) if prev_vols else 0
        vol_ratio = vol / avg_vol if avg_vol > 0 else 0
        if vol_ratio < VOLUME_RATIO_MIN:
            continue

        events.append({
            'idx': i,
            'ts': ts,
            'open': o,
            'high': h,
            'low': l,
            'close': c,
            'volume': vol,
            'drop_pct': drop_pct * 100,
            'vol_ratio': vol_ratio,
        })
    return events


def analyze_outcomes(klines, events):
    """分析每个事件后的价格变化"""
    results = []
    for ev in events:
        i = ev['idx']
        entry = ev['close']

        outcomes = {}
        for window in OBSERVATION_WINDOWS:
            end_idx = i + window
            if end_idx >= len(klines):
                continue
            future_klines = klines[i + 1:end_idx + 1]
            if not future_klines:
                continue

            # 最高价 / 最低价 / 收盘价
            future_high = max(k[2] for k in future_klines)
            future_low = min(k[3] for k in future_klines)
            future_close = future_klines[-1][4]

            outcomes[window] = {
                'max_up_pct': (future_high - entry) / entry * 100,
                'max_down_pct': (future_low - entry) / entry * 100,
                'close_pct': (future_close - entry) / entry * 100,
            }

        if outcomes:
            results.append({**ev, 'outcomes': outcomes})

    return results


def summarize(symbol, results):
    if not results:
        return None

    summary = {
        'symbol': symbol,
        'event_count': len(results),
    }

    for window in OBSERVATION_WINDOWS:
        ws = [r for r in results if window in r['outcomes']]
        if not ws:
            continue

        max_ups = [r['outcomes'][window]['max_up_pct'] for r in ws]
        max_downs = [r['outcomes'][window]['max_down_pct'] for r in ws]
        closes = [r['outcomes'][window]['close_pct'] for r in ws]

        # 反弹标准: 最高反弹 > 1%
        bounced_1 = sum(1 for u in max_ups if u >= 1.0)
        bounced_2 = sum(1 for u in max_ups if u >= 2.0)
        # 收盘是否高于触发价
        closed_up = sum(1 for c in closes if c > 0)

        summary[f'window_{window}'] = {
            'n': len(ws),
            'avg_max_up': sum(max_ups) / len(ws),
            'avg_max_down': sum(max_downs) / len(ws),
            'avg_close': sum(closes) / len(ws),
            'bounce_1pct_rate': bounced_1 / len(ws),
            'bounce_2pct_rate': bounced_2 / len(ws),
            'closed_up_rate': closed_up / len(ws),
        }

    return summary


def main():
    import ccxt
    ex = ccxt.binance({'options': {'defaultType': 'future'}})
    start = datetime(2025, 1, 1, tzinfo=timezone.utc)
    end = datetime(2026, 1, 1, tzinfo=timezone.utc)

    data = fetch_data(ex, SYMBOLS, start, end)

    print("\n" + "=" * 70)
    print("爆仓猎手 — 大跌事件统计")
    print("=" * 70)

    all_summaries = []
    total_events = 0

    for sym, klines in data.items():
        if len(klines) < LOOKBACK_BARS:
            continue
        events = detect_events(klines)
        results = analyze_outcomes(klines, events)
        summary = summarize(sym, results)
        if summary:
            all_summaries.append(summary)
            total_events += summary['event_count']

    print(f"\n总事件数: {total_events} (跨 {len(all_summaries)} 币种)")

    if total_events == 0:
        print("没有事件触发, 阈值可能太严")
        return

    # 全市场汇总
    print(f"\n{'窗口':<10} {'事件数':<10} {'平均最大反弹':<15} {'反弹>=1%':<12} {'反弹>=2%':<12} {'平均收盘':<12}")
    for window in OBSERVATION_WINDOWS:
        all_ups = []
        all_downs = []
        all_closes = []
        all_bounced_1 = 0
        all_bounced_2 = 0
        all_closed_up = 0
        n_total = 0
        for s in all_summaries:
            w = s.get(f'window_{window}')
            if not w:
                continue
            n = w['n']
            n_total += n
            all_ups.append(w['avg_max_up'] * n)
            all_downs.append(w['avg_max_down'] * n)
            all_closes.append(w['avg_close'] * n)
            all_bounced_1 += w['bounce_1pct_rate'] * n
            all_bounced_2 += w['bounce_2pct_rate'] * n
            all_closed_up += w['closed_up_rate'] * n

        if n_total == 0:
            continue
        avg_up = sum(all_ups) / n_total
        avg_down = sum(all_downs) / n_total
        avg_close = sum(all_closes) / n_total
        b1 = all_bounced_1 / n_total
        b2 = all_bounced_2 / n_total
        cu = all_closed_up / n_total

        win_label = f"{window * 5}min"
        print(f"{win_label:<10} {n_total:<10} +{avg_up:<14.2f} {b1*100:<11.1f}% {b2*100:<11.1f}% {avg_close:+.2f}%")

    # 按币种
    print(f"\n=== 各币种 1h 窗口表现 ===")
    print(f"{'币种':<14} {'事件':<6} {'平均反弹':<10} {'>1%概率':<10} {'>2%概率':<10}")
    for s in sorted(all_summaries, key=lambda x: -x['event_count']):
        w = s.get('window_12')  # 1h
        if not w:
            continue
        print(f"{s['symbol']:<14} {s['event_count']:<6} +{w['avg_max_up']:<9.2f} {w['bounce_1pct_rate']*100:<9.1f}% {w['bounce_2pct_rate']*100:<9.1f}%")

    # 评估
    print(f"\n=== 评估 ===")
    summary_1h = []
    for s in all_summaries:
        w = s.get('window_12')
        if w and w['n'] >= 3:
            summary_1h.append(w)
    if summary_1h:
        all_b1 = sum(w['bounce_1pct_rate'] * w['n'] for w in summary_1h) / sum(w['n'] for w in summary_1h)
        all_b2 = sum(w['bounce_2pct_rate'] * w['n'] for w in summary_1h) / sum(w['n'] for w in summary_1h)
        print(f"1h 反弹 >=1% 的概率: {all_b1*100:.1f}%")
        print(f"1h 反弹 >=2% 的概率: {all_b2*100:.1f}%")

        if all_b1 >= 0.65 and all_b2 >= 0.40:
            print("✅ 概率显著, 值得设计交易策略 → Phase 4")
        elif all_b1 >= 0.55:
            print("🤔 有一定概率但不够强, 可能需要叠加其他过滤")
        else:
            print("❌ 反弹概率不显著, 这个 idea 不行")

    # 保存
    out = {'total_events': total_events, 'summaries': all_summaries}
    with open('liquidation_research.json', 'w') as f:
        json.dump(out, f, indent=2, default=str)
    print("\nSaved to liquidation_research.json")


if __name__ == '__main__':
    main()
