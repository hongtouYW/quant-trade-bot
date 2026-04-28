#!/usr/bin/env python3
"""
Pump-Fade 策略回测 — Liquidation Hunter 的镜像版本

入场: 5min K线 涨幅 ≥ +3% AND 量比 5-10x → 做空 (爆涨过冲交易)
止损: 入场价上方 +1% (跌再涨穿止损)
止盈: 价格 -2% 或 60min 超时
杠杆: 50x
仓位: 300 U/笔, 最大 3 笔同持

注: 这是独立的策略验证, 不与 Liquidation Hunter 合并。
"""
import json
import time
from datetime import datetime, timezone
from collections import defaultdict


# === 策略参数 (与 Liquidation Hunter 镜像) ===
INITIAL_CAPITAL = 10000
LEVERAGE = 50
MARGIN_PER_TRADE = 300
TAKER_FEE = 0.0005
SLIPPAGE = 0.001
MAX_POSITIONS = 3
MAX_HOLD_BARS = 12          # 60 min

PUMP_THRESHOLD = 0.03        # 涨幅 +3%
VOLUME_RATIO_MIN = 5.0
VOLUME_RATIO_MAX = 10.0      # 镜像 A 方案 — 避开 10-30x 真起飞 (相反: 真趋势继续涨)
LOOKBACK_BARS = 20

STOP_LOSS_PCT = 0.01         # 价格涨 1% 止损 (做空)
TAKE_PROFIT_PCT = -0.02      # 价格跌 2% 止盈

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


def backtest(data):
    print("\n" + "=" * 70)
    print("Pump-Fade 回测")
    print(f"  入场: 5min 涨幅≥+{PUMP_THRESHOLD*100:.0f}% AND 量比 {VOLUME_RATIO_MIN}-{VOLUME_RATIO_MAX}x → 做空")
    print(f"  止损: +{STOP_LOSS_PCT*100:.0f}% / 止盈: {TAKE_PROFIT_PCT*100:.0f}% / 超时: {MAX_HOLD_BARS*5}min")
    print(f"  杠杆: {LEVERAGE}x / 单笔: {MARGIN_PER_TRADE}U / 最大持仓: {MAX_POSITIONS}")
    print("=" * 70)

    cap = INITIAL_CAPITAL
    trades = []
    positions = {}
    curve = []
    peak = INITIAL_CAPITAL
    max_dd = 0
    daily_pnl = defaultdict(float)

    all_klines = []
    for sym, klines in data.items():
        for i, k in enumerate(klines):
            all_klines.append((k[0], sym, i, k))
    all_klines.sort(key=lambda x: x[0])

    sym_klines = data

    for ts, sym, i, k in all_klines:
        ts_, o, h, l, c, vol = k
        ds = datetime.fromtimestamp(ts/1000, tz=timezone.utc).strftime('%Y-%m-%d')

        # 1. 检查现有持仓 (做空: low 触及止盈, high 触及止损)
        if sym in positions:
            pos = positions[sym]
            hold_bars = i - pos['entry_idx']

            exit_price = None
            reason = None

            # 做空止盈优先 (low 触及 = 价格跌到止盈)
            if l <= pos['tp_price']:
                exit_price = pos['tp_price'] * (1 + SLIPPAGE)  # 滑点 (做空 buy back)
                reason = 'tp'
            elif h >= pos['sl_price']:
                exit_price = pos['sl_price'] * (1 + SLIPPAGE)
                reason = 'sl'
            elif hold_bars >= MAX_HOLD_BARS:
                exit_price = c * (1 + SLIPPAGE)
                reason = 'timeout'

            if exit_price:
                # 做空盈亏: (entry - exit) / entry
                pnl_pct = (pos['entry'] - exit_price) / pos['entry']
                notional = pos['margin'] * LEVERAGE
                fees = notional * TAKER_FEE * 2
                pnl = pnl_pct * notional - fees
                cap += pnl
                daily_pnl[ds] += pnl

                trades.append({
                    'symbol': sym,
                    'direction': 'short',
                    'leverage': LEVERAGE,
                    'margin': round(pos['margin'], 2),
                    'entry': round(pos['entry'], 6),
                    'exit': round(exit_price, 6),
                    'pnl': round(pnl, 2),
                    'pnl_pct': round(pnl_pct * 100, 2),
                    'reason': reason,
                    'hold_min': hold_bars * 5,
                    'date': ds,
                    'entry_pump_pct': pos['entry_pump_pct'],
                    'entry_vol_ratio': pos['entry_vol_ratio'],
                })
                del positions[sym]

        # 2. 检查新信号 (爆涨)
        if sym in positions:
            continue
        if len(positions) >= MAX_POSITIONS:
            continue
        if i < LOOKBACK_BARS:
            continue

        pump_pct = (c - o) / o if o > 0 else 0
        if pump_pct < PUMP_THRESHOLD:
            continue
        prev_vols = [sym_klines[sym][j][5] for j in range(i - LOOKBACK_BARS, i)]
        avg_vol = sum(prev_vols) / len(prev_vols) if prev_vols else 0
        vol_ratio = vol / avg_vol if avg_vol > 0 else 0
        if vol_ratio < VOLUME_RATIO_MIN or vol_ratio > VOLUME_RATIO_MAX:
            continue

        margin = min(MARGIN_PER_TRADE, cap * 0.10)
        if margin < 50:
            continue

        # 入场 (做空: 在收盘价之下 + 滑点, 因为我们 sell)
        entry = c * (1 - SLIPPAGE)
        positions[sym] = {
            'entry': entry,
            'entry_idx': i,
            'entry_ts': ts,
            'margin': margin,
            'tp_price': entry * (1 + TAKE_PROFIT_PCT),  # 跌 2% 止盈
            'sl_price': entry * (1 + STOP_LOSS_PCT),    # 涨 1% 止损
            'entry_pump_pct': pump_pct * 100,
            'entry_vol_ratio': vol_ratio,
        }

        if not curve or curve[-1]['date'] != ds:
            curve.append({'date': ds, 'balance': round(cap, 2)})
            if cap > peak: peak = cap
            dd = (peak - cap) / peak if peak > 0 else 0
            if dd > max_dd: max_dd = dd

    # 平剩余仓
    for sym, pos in positions.items():
        if data.get(sym):
            c = data[sym][-1][4]
            pnl_pct = (pos['entry'] - c) / pos['entry']
            notional = pos['margin'] * LEVERAGE
            fees = notional * TAKER_FEE * 2
            pnl = pnl_pct * notional - fees
            cap += pnl
            trades.append({
                'symbol': sym, 'direction': 'short',
                'leverage': LEVERAGE, 'margin': round(pos['margin'], 2),
                'entry': round(pos['entry'], 6), 'exit': round(c, 6),
                'pnl': round(pnl, 2), 'pnl_pct': round(pnl_pct * 100, 2),
                'reason': 'end', 'hold_min': 0, 'date': 'end',
                'entry_pump_pct': pos['entry_pump_pct'],
                'entry_vol_ratio': pos['entry_vol_ratio'],
            })

    return cap, trades, curve, max_dd


def report(cap, trades, curve, max_dd):
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    pnl = sum(t['pnl'] for t in trades)
    aw = sum(t['pnl'] for t in wins) / len(wins) if wins else 0
    al = abs(sum(t['pnl'] for t in losses) / len(losses)) if losses else 1
    pf = aw / al if al else 0

    reason_counts = defaultdict(lambda: {'n': 0, 'pnl': 0})
    for t in trades:
        reason_counts[t['reason']]['n'] += 1
        reason_counts[t['reason']]['pnl'] += t['pnl']

    print("\n" + "=" * 70)
    print("Pump-Fade 回测结果")
    print("=" * 70)
    print(f"  {INITIAL_CAPITAL} U → {cap:.2f} U  PnL: {pnl:+.2f} ({pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  Max DD: {max_dd*100:.1f}%  Trades: {len(trades)}")
    if trades:
        print(f"  WR: {len(wins)/len(trades)*100:.1f}% ({len(wins)}W / {len(losses)}L)")
        print(f"  Avg Win: +{aw:.2f}  Avg Loss: -{al:.2f}  PF: {pf:.2f}")
        print(f"  Avg Hold: {sum(t['hold_min'] for t in trades)/len(trades):.0f} min")

    print(f"\n  Reasons:")
    for r in ['tp', 'sl', 'timeout', 'end']:
        if r in reason_counts:
            d = reason_counts[r]
            print(f"    {r:<10} {d['n']:<5} PnL {d['pnl']:+.0f} U")

    print(f"\n  入场涨幅分布:")
    for low, high in [(3, 4), (4, 5), (5, 7), (7, 10), (10, 100)]:
        in_range = [t for t in trades if low <= t['entry_pump_pct'] < high]
        if in_range:
            wins_r = sum(1 for t in in_range if t['pnl'] > 0)
            pnl_r = sum(t['pnl'] for t in in_range)
            print(f"    +{low}~+{high}%: {len(in_range):3} 笔  胜率 {wins_r/len(in_range)*100:5.1f}%  PnL {pnl_r:+.0f}")

    print(f"\n  量比分布:")
    for low, high in [(5, 7), (7, 10), (10, 15), (15, 30), (30, 1000)]:
        in_range = [t for t in trades if low <= t['entry_vol_ratio'] < high]
        if in_range:
            wins_r = sum(1 for t in in_range if t['pnl'] > 0)
            pnl_r = sum(t['pnl'] for t in in_range)
            print(f"    {low}-{high}x: {len(in_range):3} 笔  胜率 {wins_r/len(in_range)*100:5.1f}%  PnL {pnl_r:+.0f}")

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
        print(f"    {m}: {d['n']:3}笔  胜率{wr:5.1f}%  PnL{d['p']:+8.0f}")

    result = {
        'period': f'{datetime.fromtimestamp(curve[0]["balance"], tz=timezone.utc).strftime("%Y-%m") if curve else "?"} backtest' if curve else '?',
        'strategy': f'Pump-Fade (pump +{PUMP_THRESHOLD*100:.0f}% + vol {VOLUME_RATIO_MIN}-{VOLUME_RATIO_MAX}x → short, {LEVERAGE}x lev)',
        'initial_capital': INITIAL_CAPITAL,
        'final_capital': round(cap, 2),
        'total_pnl': round(pnl, 2),
        'total_trades': len(trades),
        'wins': len(wins),
        'losses': len(losses),
        'win_rate': round(len(wins)/len(trades)*100, 1) if trades else 0,
        'profit_factor': round(pf, 2),
        'max_drawdown': round(max_dd*100, 1),
        'equity_curve': curve,
        'trades': trades,
    }
    with open('pump_fade_result.json', 'w') as f:
        json.dump(result, f, indent=2, default=str)
    print("\nSaved to pump_fade_result.json")


if __name__ == "__main__":
    import ccxt
    ex = ccxt.binance({'options': {'defaultType': 'future'}})
    start = datetime(2025, 1, 1, tzinfo=timezone.utc)
    end = datetime(2026, 1, 1, tzinfo=timezone.utc)

    data = fetch_data(ex, SYMBOLS, start, end)
    cap, trades, curve, max_dd = backtest(data)
    report(cap, trades, curve, max_dd)
