#!/usr/bin/env python3
"""
Flash Quant V2 — 回踩做多策略回测
逻辑: 1H趋势向上 + RSI超卖回调 + EMA/BB支撑入场
不追高, 等跌了再买
"""
import sys, json, time
from datetime import datetime, timezone, timedelta
from collections import defaultdict

sys.path.insert(0, '.')
from data.indicators import ema, rsi, bollinger_bands, adx

INITIAL_CAPITAL = 10000
LEVERAGE = 20
TAKER_FEE = 0.0005
SLIPPAGE = 0.0005
MAX_HOLD_HOURS = 8
MAX_POSITIONS = 5
MARGIN_PER_TRADE = 300

# V2 策略参数
RSI_OVERSOLD = 35          # RSI 跌到这里才买
RSI_PERIOD = 14
STOP_LOSS_PCT = 0.025      # 2.5% 价格止损 (比 V1 的 0.5% 大 5 倍)
TAKE_PROFIT_PCT = 0.025    # 2.5% 价格止盈

SYMBOLS = [
    'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT',
    'DOGE/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT',
    'NEAR/USDT', 'APT/USDT', 'ATOM/USDT', 'SUI/USDT', 'TRX/USDT',
    'ARB/USDT', 'OP/USDT', 'LTC/USDT', 'UNI/USDT', 'AAVE/USDT',
]


def fetch_data(exchange, symbols, start):
    print("Fetching %d coins..." % len(symbols))
    data = {}
    for sym in symbols:
        try:
            print("  %s..." % sym, end=" ", flush=True)
            k5 = []; since = int(start.timestamp()*1000)
            while True:
                b = exchange.fetch_ohlcv(sym,'5m',since=since,limit=1000)
                if not b: break
                k5.extend(b); since=b[-1][0]+1
                if len(b)<1000: break
                time.sleep(0.05)
            k1h = []; since = int(start.timestamp()*1000)
            while True:
                b = exchange.fetch_ohlcv(sym,'1h',since=since,limit=1000)
                if not b: break
                k1h.extend(b); since=b[-1][0]+1
                if len(b)<1000: break
                time.sleep(0.05)
            data[sym] = {'5m':k5,'1h':k1h}
            print("OK %d" % len(k5))
        except Exception as e:
            print("SKIP %s" % e)
    return data


def get_trend(k1h, idx):
    """1H 大趋势"""
    if idx < 55: return 'unknown'
    closes = [k[4] for k in k1h[idx-55:idx]]
    e20 = ema(closes, 20)
    e50 = ema(closes, 50)
    if not e20 or not e50: return 'unknown'
    diff = (e20[-1] - e50[-1]) / e50[-1]
    if diff > 0.001: return 'bullish'
    elif diff < -0.001: return 'bearish'
    return 'sideways'


def get_rsi(k5, idx):
    """5min RSI"""
    if idx < RSI_PERIOD + 2: return 50
    closes = [k[4] for k in k5[max(0,idx-100):idx]]
    vals = rsi(closes, RSI_PERIOD)
    return vals[-1] if vals else 50


def near_ema_support(k5, idx):
    """价格是否接近 EMA20 支撑"""
    if idx < 25: return False
    closes = [k[4] for k in k5[idx-25:idx]]
    e20 = ema(closes, 20)
    if not e20: return False
    price = closes[-1]
    ema_val = e20[-1]
    # 价格在 EMA20 附近 (上下 0.5%)
    return abs(price - ema_val) / ema_val < 0.005


def near_bb_lower(k5, idx):
    """价格是否接近布林带下轨"""
    if idx < 25: return False
    closes = [k[4] for k in k5[idx-25:idx]]
    upper, middle, lower = bollinger_bands(closes, 20, 2.0)
    if not lower: return False
    price = closes[-1]
    # 价格在下轨附近或以下
    return price <= lower[-1] * 1.003


def find_1h(k1h, ts):
    for i in range(len(k1h)-1,-1,-1):
        if k1h[i][0] <= ts: return i
    return 0


def backtest(data):
    print("\nV2 Backtest: Pullback Long Strategy")
    print("  RSI < %d + 1H Trend Up + EMA/BB Support" % RSI_OVERSOLD)
    print("  SL: %.1f%% TP: %.1f%% Leverage: %dx" % (STOP_LOSS_PCT*100, TAKE_PROFIT_PCT*100, LEVERAGE))

    cap = INITIAL_CAPITAL
    trades = []
    pos = []
    curve = []
    sigs = 0
    filtered = 0

    for sym, d in data.items():
        k5 = d['5m']; k1h = d['1h']
        if len(k5) < 100 or len(k1h) < 55: continue

        for i in range(100, len(k5)):
            ts,o,h,l,c,vol = k5[i]
            ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d')

            # 持仓检查: 止盈/止损/超时
            np = []
            for p in pos:
                if p['sym'] != sym: np.append(p); continue

                # 止盈
                hit_tp = False
                if p['dir'] == 'long' and h >= p['tp']:
                    ep = p['tp'] * (1 - SLIPPAGE)
                    reason = 'take_profit'
                    hit_tp = True
                elif p['dir'] == 'short' and l <= p['tp']:
                    ep = p['tp'] * (1 + SLIPPAGE)
                    reason = 'take_profit'
                    hit_tp = True

                # 止损
                hit_sl = False
                if not hit_tp:
                    if p['dir'] == 'long' and l <= p['sl']:
                        ep = p['sl'] * (1 - SLIPPAGE)
                        reason = 'stop_loss'
                        hit_sl = True
                    elif p['dir'] == 'short' and h >= p['sl']:
                        ep = p['sl'] * (1 + SLIPPAGE)
                        reason = 'stop_loss'
                        hit_sl = True

                # 超时
                hit_to = (ts - p['ts']) > MAX_HOLD_HOURS * 3600000

                if hit_tp or hit_sl or hit_to:
                    if hit_to and not hit_tp and not hit_sl:
                        ep = c * (1 - SLIPPAGE if p['dir'] == 'long' else 1 + SLIPPAGE)
                        reason = 'timeout'
                    n = p['m'] * LEVERAGE
                    fee = n * TAKER_FEE
                    if p['dir'] == 'long':
                        pnl = (ep - p['e']) / p['e'] * n - p['f'] - fee
                    else:
                        pnl = (p['e'] - ep) / p['e'] * n - p['f'] - fee
                    cap += pnl
                    trades.append({
                        'symbol': sym, 'direction': p['dir'], 'tier': 'pullback',
                        'entry': round(p['e'], 6), 'exit': round(ep, 6),
                        'pnl': round(pnl, 2), 'reason': reason,
                        'hold_hours': round((ts-p['ts'])/3600000, 1), 'date': ds
                    })
                else:
                    np.append(p)
            pos = np

            if not curve or curve[-1]['date'] != ds:
                curve.append({'date': ds, 'balance': round(cap, 2)})

            # 开仓条件检查
            if len(pos) >= MAX_POSITIONS: continue
            if any(p['sym'] == sym for p in pos): continue
            margin = min(MARGIN_PER_TRADE, cap * 0.15)
            if margin < 50: continue

            # === V2 策略信号 ===

            # 1. 1H 大趋势
            hi = find_1h(k1h, ts)
            trend = get_trend(k1h, hi)

            # 2. RSI 超卖
            current_rsi = get_rsi(k5, i)

            # 3. 支撑确认 (EMA 或 BB 下轨)
            at_support = near_ema_support(k5, i) or near_bb_lower(k5, i)

            # 信号逻辑
            signal = False
            direction = None

            if trend == 'bullish' and current_rsi <= RSI_OVERSOLD and at_support:
                signal = True
                direction = 'long'
                sigs += 1

            elif trend == 'bearish' and current_rsi >= (100 - RSI_OVERSOLD) and not at_support:
                # 做空: 趋势向下 + RSI 超买 + 不在支撑
                signal = True
                direction = 'short'
                sigs += 1

            if not signal:
                continue

            # 开仓
            entry = c * (1 + SLIPPAGE if direction == 'long' else 1 - SLIPPAGE)

            if direction == 'long':
                sl = entry * (1 - STOP_LOSS_PCT)
                tp = entry * (1 + TAKE_PROFIT_PCT)
            else:
                sl = entry * (1 + STOP_LOSS_PCT)
                tp = entry * (1 - TAKE_PROFIT_PCT)

            fee = margin * LEVERAGE * TAKER_FEE
            pos.append({'sym': sym, 'dir': direction, 'e': entry, 'sl': sl, 'tp': tp,
                        'm': margin, 'f': fee, 'ts': ts})

    # 强平
    for p in pos:
        last = data[p['sym']]['5m'][-1]; ep = last[4]
        n = p['m']*LEVERAGE; fee = n*TAKER_FEE
        pnl = ((ep-p['e'])/p['e'] if p['dir']=='long' else (p['e']-ep)/p['e'])*n - p['f'] - fee
        cap += pnl
        trades.append({'symbol':p['sym'],'direction':p['dir'],'tier':'pullback',
            'entry':round(p['e'],6),'exit':round(ep,6),'pnl':round(pnl,2),
            'reason':'end','hold_hours':0,'date':'end'})

    return cap, trades, curve, sigs, filtered


def report(cap, trades, curve, sigs, filtered):
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    pnl = sum(t['pnl'] for t in trades)
    aw = sum(t['pnl'] for t in wins)/len(wins) if wins else 0
    al = abs(sum(t['pnl'] for t in losses)/len(losses)) if losses else 1
    pf = round(aw/al, 2) if al else 0

    # 止盈/止损/超时 分类
    tp_trades = [t for t in trades if t['reason'] == 'take_profit']
    sl_trades = [t for t in trades if t['reason'] == 'stop_loss']
    to_trades = [t for t in trades if t['reason'] == 'timeout']

    lo = [t for t in trades if t['direction'] == 'long']
    sh = [t for t in trades if t['direction'] == 'short']

    print("\n" + "=" * 60)
    print("V2 Pullback Strategy Backtest Report")
    print("=" * 60)
    print("  Initial: %d U -> Final: %.2f U" % (INITIAL_CAPITAL, cap))
    print("  PnL: %+.2f U (%+.1f%%)" % (pnl, pnl/INITIAL_CAPITAL*100))
    print("  Trades: %d (signals: %d)" % (len(trades), sigs))
    if trades:
        print("  Win rate: %.1f%% (%d W / %d L)" % (len(wins)/len(trades)*100, len(wins), len(losses)))
    print("  Profit factor: %.2f" % pf)
    print("  Avg win: +%.2f  Avg loss: -%.2f" % (aw, al))
    print()
    print("  Take profit: %d (%.0f%%)" % (len(tp_trades), len(tp_trades)/len(trades)*100 if trades else 0))
    print("  Stop loss:   %d (%.0f%%)" % (len(sl_trades), len(sl_trades)/len(trades)*100 if trades else 0))
    print("  Timeout:     %d (%.0f%%)" % (len(to_trades), len(to_trades)/len(trades)*100 if trades else 0))
    if lo: print("  Long:  %d trades, %.0f%% win" % (len(lo), sum(1 for t in lo if t['pnl']>0)/len(lo)*100))
    if sh: print("  Short: %d trades, %.0f%% win" % (len(sh), sum(1 for t in sh if t['pnl']>0)/len(sh)*100))

    monthly = defaultdict(lambda: {'t':0,'p':0,'w':0})
    for t in trades:
        m = t['date'][:7]
        if m == 'end': continue
        monthly[m]['t'] += 1; monthly[m]['p'] += t['pnl']
        if t['pnl'] > 0: monthly[m]['w'] += 1
    print("\n  Monthly:")
    for m in sorted(monthly):
        d = monthly[m]
        print("  %s: %3d trades  WR %5.1f%%  PnL %+9.2f" % (m, d['t'], d['w']/d['t']*100 if d['t'] else 0, d['p']))

    # 最近 10 笔
    print("\n  Last 10:")
    for t in trades[-10:]:
        icon = "WIN " if t['pnl'] > 0 else "LOSS"
        print("  %s %s %-12s %s %+8.2f (%s)" % (icon, t['date'], t['symbol'], t['direction'], t['pnl'], t['reason']))

    # Save
    be = round(al/(aw+al)*100, 1) if (aw+al) else 50
    result = {
        'period': '2026-01 to now',
        'strategy': 'V2: Pullback Long (RSI<%d + 1H Trend + EMA/BB Support)' % RSI_OVERSOLD,
        'initial_capital': INITIAL_CAPITAL, 'final_capital': round(cap, 2),
        'total_pnl': round(pnl, 2), 'total_trades': len(trades),
        'wins': len(wins), 'losses': len(losses),
        'win_rate': round(len(wins)/len(trades)*100, 1) if trades else 0,
        'profit_factor': pf, 'breakeven_winrate': be,
        'equity_curve': curve, 'trades': trades[-50:],
    }
    with open('backtest_result.json', 'w') as f:
        json.dump(result, f, indent=2, default=str)
    print("\nSaved to backtest_result.json")


if __name__ == "__main__":
    import ccxt
    ex = ccxt.binance({'options': {'defaultType': 'future'}})
    start = datetime(2026, 1, 1, tzinfo=timezone.utc)
    data = fetch_data(ex, SYMBOLS, start)
    cap, trades, curve, sigs, filt = backtest(data)
    report(cap, trades, curve, sigs, filt)
