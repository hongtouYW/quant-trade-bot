#!/usr/bin/env python3
"""
Funding Extreme 策略回测 — 独立 alpha, 不与 Liquidation Hunter 关联

逻辑: 资金费率极端 = 仓位拥挤, 反方向有 mean-reversion edge + 还能收 funding 费

入场:
  funding > +0.08% (8h 多头拥挤) → 做空, 持仓 8h
  funding < -0.03% (8h 空头拥挤) → 做多, 持仓 8h

出场:
  TP: 价格反向 ±2%
  SL: 价格逆向 ±2%
  超时: 8 小时 (一个 funding 周期, 收一次 funding 费)

杠杆: 20x (因持仓 8h, 比 5min 策略保守)
仓位: 300 U/笔, 最大同持 5 笔 (跨币种)
"""
import json
import time
from datetime import datetime, timezone, timedelta
from collections import defaultdict


# === 策略参数 ===
INITIAL_CAPITAL = 10000
LEVERAGE = 20
MARGIN_PER_TRADE = 300
TAKER_FEE = 0.0005
SLIPPAGE = 0.0005
MAX_POSITIONS = 5
HOLD_HOURS = 8

FUNDING_SHORT_TRIGGER = 0.0008   # +0.08% → 做空 (多头拥挤)
FUNDING_LONG_TRIGGER = -0.0003   # -0.03% → 做多 (空头拥挤)

TP_PCT = 0.02                    # 价格反向 2% 止盈
SL_PCT = 0.02                    # 价格逆向 2% 止损

SYMBOLS = [
    'BTC/USDT','ETH/USDT','SOL/USDT','BNB/USDT','XRP/USDT',
    'DOGE/USDT','ADA/USDT','AVAX/USDT','LINK/USDT','DOT/USDT',
    'NEAR/USDT','APT/USDT','ATOM/USDT','SUI/USDT','TRX/USDT',
    'LTC/USDT','BCH/USDT','ETC/USDT','UNI/USDT','AAVE/USDT',
    'FIL/USDT','INJ/USDT','ARB/USDT','OP/USDT','MKR/USDT',
    'ICP/USDT','HBAR/USDT','FTM/USDT','THETA/USDT','VET/USDT',
]


def fetch_funding(exchange, symbol, start_ms, end_ms):
    """拉取一个币的 funding 历史"""
    all_events = []
    since = start_ms
    while since < end_ms:
        try:
            events = exchange.fetch_funding_rate_history(symbol, since=since, limit=1000)
            if not events:
                break
            all_events.extend([e for e in events if e['timestamp'] < end_ms])
            since = events[-1]['timestamp'] + 1
            if len(events) < 1000:
                break
            time.sleep(0.05)
        except Exception as e:
            print(f"  funding error {symbol}: {e}")
            break
    return all_events


def fetch_klines(exchange, symbol, start_ms, end_ms, tf='1h'):
    """拉取一个币的 1h K 线"""
    all_klines = []
    since = start_ms
    while since < end_ms:
        try:
            batch = exchange.fetch_ohlcv(symbol, tf, since=since, limit=1000)
            if not batch:
                break
            all_klines.extend([k for k in batch if k[0] < end_ms])
            since = batch[-1][0] + 1
            if len(batch) < 1000:
                break
            time.sleep(0.05)
        except Exception as e:
            print(f"  kline error {symbol}: {e}")
            break
    return all_klines


def fetch_all(exchange, symbols, start, end):
    print(f"Fetching {len(symbols)} coins funding + 1h klines...")
    start_ms = int(start.timestamp() * 1000)
    end_ms = int(end.timestamp() * 1000)
    funding_data = {}
    kline_data = {}
    for sym in symbols:
        try:
            print(f"  {sym}...", end=" ", flush=True)
            f = fetch_funding(exchange, sym, start_ms, end_ms)
            k = fetch_klines(exchange, sym, start_ms, end_ms, '1h')
            funding_data[sym] = f
            kline_data[sym] = k
            print(f"funding {len(f)}, kline {len(k)}")
        except Exception as e:
            print(f"SKIP {e}")
    return funding_data, kline_data


def find_kline_at(klines, target_ts):
    """找最接近的 1h K 线 (timestamp <= target_ts < ts + 1h)"""
    for k in klines:
        if k[0] <= target_ts < k[0] + 3600 * 1000:
            return k
    return None


def find_kline_window(klines, start_ts, end_ts):
    """返回 [start_ts, end_ts) 之间的所有 K 线"""
    return [k for k in klines if start_ts <= k[0] < end_ts]


def backtest(funding_data, kline_data):
    print("\n" + "=" * 70)
    print("Funding Extreme 回测")
    print(f"  入场: funding > +{FUNDING_SHORT_TRIGGER*100:.3f}% → 做空")
    print(f"        funding < {FUNDING_LONG_TRIGGER*100:.3f}% → 做多")
    print(f"  TP: ±{TP_PCT*100:.0f}% / SL: ±{SL_PCT*100:.0f}% / 超时: {HOLD_HOURS}h")
    print(f"  杠杆: {LEVERAGE}x / 单笔: {MARGIN_PER_TRADE}U / 最大持仓: {MAX_POSITIONS}")
    print("=" * 70)

    # 收集所有 funding 极端事件 (按时间排序)
    events = []
    for sym, fr_list in funding_data.items():
        for fr in fr_list:
            rate = fr['fundingRate']
            ts = fr['timestamp']
            if rate >= FUNDING_SHORT_TRIGGER:
                events.append((ts, sym, 'short', rate))
            elif rate <= FUNDING_LONG_TRIGGER:
                events.append((ts, sym, 'long', rate))
    events.sort(key=lambda x: x[0])
    print(f"  极端事件: {len(events)} 个")

    cap = INITIAL_CAPITAL
    trades = []
    positions = {}  # sym -> pos
    peak = INITIAL_CAPITAL
    max_dd = 0
    curve = []

    # 同时按时间推进所有事件 + 检查持仓
    # 用 1h 时间格清扫
    if not events:
        return cap, trades, curve, max_dd

    all_ts = sorted(set([e[0] for e in events] +
                         [k[0] for ks in kline_data.values() for k in ks]))

    # 简化: 只在 funding 时间点处理 — 入场/出场都在 1h 内做
    open_pos = {}  # sym -> {entry_ts, direction, entry_price, margin, exit_ts, sl_price, tp_price, funding_rate}

    for ev in events:
        ev_ts, sym, direction, rate = ev
        if sym not in kline_data or not kline_data[sym]:
            continue

        # 1. 先平掉所有到期/止损的持仓 (检查到 ev_ts 之前)
        to_close = []
        for s, p in open_pos.items():
            window = find_kline_window(kline_data[s], p['entry_ts'], ev_ts + 3600 * 1000)
            for k in window:
                ts_k, o, h, l, c, v = k
                if ts_k < p['entry_ts']:
                    continue
                # 检查 SL/TP/超时
                exit_p, reason = None, None
                if direction_of(p) == 'short':
                    if l <= p['tp_price']:
                        exit_p, reason = p['tp_price'] * (1 + SLIPPAGE), 'tp'
                    elif h >= p['sl_price']:
                        exit_p, reason = p['sl_price'] * (1 + SLIPPAGE), 'sl'
                else:  # long
                    if h >= p['tp_price']:
                        exit_p, reason = p['tp_price'] * (1 - SLIPPAGE), 'tp'
                    elif l <= p['sl_price']:
                        exit_p, reason = p['sl_price'] * (1 - SLIPPAGE), 'sl'

                if exit_p is None and ts_k >= p['exit_ts']:
                    exit_p, reason = c * (1 + (SLIPPAGE if direction_of(p) == 'short' else -SLIPPAGE)), 'timeout'

                if exit_p:
                    pnl = compute_pnl(p, exit_p, ts_k)
                    cap += pnl
                    trades.append({
                        'symbol': s, 'direction': direction_of(p),
                        'leverage': LEVERAGE, 'margin': round(p['margin'], 2),
                        'entry': round(p['entry_price'], 6),
                        'exit': round(exit_p, 6),
                        'pnl': round(pnl, 2),
                        'pnl_pct': round((exit_p - p['entry_price'])/p['entry_price']*100 *
                                          (-1 if direction_of(p) == 'short' else 1), 2),
                        'reason': reason,
                        'hold_hours': round((ts_k - p['entry_ts']) / 3600 / 1000, 1),
                        'date': datetime.fromtimestamp(p['entry_ts']/1000, tz=timezone.utc).strftime('%Y-%m-%d'),
                        'funding_rate': round(p['funding_rate']*100, 4),
                    })
                    if cap > peak: peak = cap
                    dd = (peak - cap) / peak if peak > 0 else 0
                    if dd > max_dd: max_dd = dd
                    to_close.append(s)
                    break

        for s in to_close:
            del open_pos[s]

        # 2. 入场: 此事件
        if sym in open_pos:
            continue
        if len(open_pos) >= MAX_POSITIONS:
            continue
        # 入场价 = ev_ts 时的 1h 收盘 (作为 funding settlement 后的实际价)
        kl = find_kline_at(kline_data[sym], ev_ts)
        if not kl:
            continue
        entry_price = kl[4]  # 收盘
        margin = min(MARGIN_PER_TRADE, cap * 0.05)  # 单笔最多 5% 本金
        if margin < 50:
            continue

        if direction == 'short':
            entry_price = entry_price * (1 - SLIPPAGE)
            sl_price = entry_price * (1 + SL_PCT)
            tp_price = entry_price * (1 - TP_PCT)
        else:
            entry_price = entry_price * (1 + SLIPPAGE)
            sl_price = entry_price * (1 - SL_PCT)
            tp_price = entry_price * (1 + TP_PCT)

        open_pos[sym] = {
            'entry_ts': ev_ts,
            'entry_price': entry_price,
            'margin': margin,
            'exit_ts': ev_ts + HOLD_HOURS * 3600 * 1000,
            'sl_price': sl_price,
            'tp_price': tp_price,
            'funding_rate': rate,
            'direction': direction,
        }

    # 平剩余仓
    final_ts = max([k[0] for ks in kline_data.values() for k in ks if ks])
    for s, p in list(open_pos.items()):
        kl = find_kline_at(kline_data[s], p['exit_ts']) or kline_data[s][-1]
        exit_p = kl[4]
        pnl = compute_pnl(p, exit_p, p['exit_ts'])
        cap += pnl
        trades.append({
            'symbol': s, 'direction': p['direction'],
            'leverage': LEVERAGE, 'margin': round(p['margin'], 2),
            'entry': round(p['entry_price'], 6),
            'exit': round(exit_p, 6),
            'pnl': round(pnl, 2),
            'pnl_pct': round((exit_p - p['entry_price'])/p['entry_price']*100 *
                              (-1 if p['direction'] == 'short' else 1), 2),
            'reason': 'end',
            'hold_hours': HOLD_HOURS,
            'date': datetime.fromtimestamp(p['entry_ts']/1000, tz=timezone.utc).strftime('%Y-%m-%d'),
            'funding_rate': round(p['funding_rate']*100, 4),
        })

    return cap, trades, curve, max_dd


def direction_of(p):
    return p['direction']


def compute_pnl(p, exit_price, exit_ts):
    """计算 pnl: 价格盈亏 + 收到的 funding 费"""
    notional = p['margin'] * LEVERAGE
    if p['direction'] == 'short':
        price_pnl_pct = (p['entry_price'] - exit_price) / p['entry_price']
    else:
        price_pnl_pct = (exit_price - p['entry_price']) / p['entry_price']
    price_pnl = price_pnl_pct * notional
    fees = notional * TAKER_FEE * 2
    # funding 费: 持仓跨过 settlement 时间收一次 (8h 内最多 1 次)
    # 简化: 假设全部都收到一次 funding (因为持仓刚好覆盖一个 8h 周期)
    # 做空 + 正 funding → 收到 funding (rate * notional)
    # 做多 + 负 funding → 收到 funding (|rate| * notional)
    funding_pnl = 0
    settlements = 0
    # 8h 间隔 settlement 在 0/8/16 UTC, 检查持仓窗口内有几次
    win_start = p['entry_ts']
    win_end = exit_ts
    # 找下一个 settlement 时间
    cur = (win_start // (8 * 3600 * 1000) + 1) * (8 * 3600 * 1000)
    while cur < win_end:
        settlements += 1
        cur += 8 * 3600 * 1000
    # 收 funding (反向于 funding 方向)
    if p['direction'] == 'short':
        funding_pnl = p['funding_rate'] * notional * settlements
    else:  # long
        funding_pnl = -p['funding_rate'] * notional * settlements

    return price_pnl + funding_pnl - fees


def report(cap, trades, curve, max_dd):
    if not trades:
        print("\n无任何交易触发 (检查 funding 阈值)")
        return
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    pnl = sum(t['pnl'] for t in trades)
    aw = sum(t['pnl'] for t in wins) / len(wins) if wins else 0
    al = abs(sum(t['pnl'] for t in losses) / len(losses)) if losses else 1
    pf = aw / al if al else 0

    longs = [t for t in trades if t['direction'] == 'long']
    shorts = [t for t in trades if t['direction'] == 'short']

    reason_counts = defaultdict(lambda: {'n': 0, 'pnl': 0})
    for t in trades:
        reason_counts[t['reason']]['n'] += 1
        reason_counts[t['reason']]['pnl'] += t['pnl']

    print("\n" + "=" * 70)
    print("Funding Extreme 回测结果")
    print("=" * 70)
    print(f"  {INITIAL_CAPITAL} U → {cap:.2f} U  PnL: {pnl:+.2f} ({pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  Max DD: {max_dd*100:.1f}%  Trades: {len(trades)}")
    print(f"  WR: {len(wins)/len(trades)*100:.1f}% ({len(wins)}W / {len(losses)}L)")
    print(f"  Avg Win: +{aw:.2f}  Avg Loss: -{al:.2f}  PF: {pf:.2f}")
    print(f"  Long {len(longs)} / Short {len(shorts)}")

    print(f"\n  Reasons:")
    for r in ['tp', 'sl', 'timeout', 'end']:
        if r in reason_counts:
            d = reason_counts[r]
            print(f"    {r:<10} {d['n']:<5} PnL {d['pnl']:+.0f} U")

    if longs:
        long_wr = sum(1 for t in longs if t['pnl'] > 0) / len(longs) * 100
        long_pnl = sum(t['pnl'] for t in longs)
        print(f"\n  LONG: {len(longs)} 笔, WR {long_wr:.1f}%, PnL {long_pnl:+.0f}")
    if shorts:
        short_wr = sum(1 for t in shorts if t['pnl'] > 0) / len(shorts) * 100
        short_pnl = sum(t['pnl'] for t in shorts)
        print(f"  SHORT: {len(shorts)} 笔, WR {short_wr:.1f}%, PnL {short_pnl:+.0f}")

    # funding rate 分布
    print(f"\n  Funding rate 分布:")
    buckets = [(-1, -0.0003), (-0.0003, 0.0008), (0.0008, 0.001), (0.001, 0.002), (0.002, 1)]
    for low, high in buckets:
        in_r = [t for t in trades if low <= t['funding_rate']/100 < high]
        if in_r:
            wins_r = sum(1 for t in in_r if t['pnl'] > 0)
            pnl_r = sum(t['pnl'] for t in in_r)
            print(f"    {low*100:.3f}~{high*100:.3f}%: {len(in_r):3} 笔  WR {wins_r/len(in_r)*100:5.1f}%  PnL {pnl_r:+.0f}")

    monthly = defaultdict(lambda: {'n': 0, 'p': 0, 'w': 0})
    for t in trades:
        m = t['date'][:7]
        if m == 'end': continue
        monthly[m]['n'] += 1
        monthly[m]['p'] += t['pnl']
        if t['pnl'] > 0: monthly[m]['w'] += 1

    print(f"\n  月度:")
    for m in sorted(monthly):
        d = monthly[m]
        wr = d['w']/d['n']*100 if d['n'] else 0
        print(f"    {m}: {d['n']:3}笔  WR{wr:5.1f}%  PnL{d['p']:+8.0f}")

    result = {
        'period': '2025 全年',
        'strategy': f'Funding Extreme (>+{FUNDING_SHORT_TRIGGER*100:.3f}% short / <{FUNDING_LONG_TRIGGER*100:.3f}% long, {LEVERAGE}x lev, {HOLD_HOURS}h hold)',
        'initial_capital': INITIAL_CAPITAL,
        'final_capital': round(cap, 2),
        'total_pnl': round(pnl, 2),
        'total_trades': len(trades),
        'wins': len(wins),
        'losses': len(losses),
        'win_rate': round(len(wins)/len(trades)*100, 1) if trades else 0,
        'profit_factor': round(pf, 2),
        'max_drawdown': round(max_dd*100, 1),
        'trades': trades,
    }
    with open('funding_extreme_result.json', 'w') as f:
        json.dump(result, f, indent=2, default=str)
    print("\nSaved to funding_extreme_result.json")


if __name__ == "__main__":
    import ccxt
    ex = ccxt.binance({'options': {'defaultType': 'future'}})
    start = datetime(2025, 1, 1, tzinfo=timezone.utc)
    end = datetime(2026, 1, 1, tzinfo=timezone.utc)

    funding_data, kline_data = fetch_all(ex, SYMBOLS, start, end)
    cap, trades, curve, max_dd = backtest(funding_data, kline_data)
    report(cap, trades, curve, max_dd)
