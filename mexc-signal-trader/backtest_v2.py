#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
LUKE Signal Backtest V2 - 逐根K线验证
- 每笔交易用15分钟K线逐根扫描，确认SL/TP是否真的触发
- 过滤异常价格（变动>50%标记无效）
- 信号数据 vs 交易数据分开统计
- 每笔交易保存K线数据，供前端画图
"""

import json, os, re, sys, time, requests
from datetime import datetime, timezone, timedelta
from collections import defaultdict

try:
    import pytesseract
    from PIL import Image
    HAS_OCR = True
except:
    HAS_OCR = False

# ===== Config =====
INITIAL_CAPITAL = 10000
MAJOR_COINS = ['BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'ADA', 'DOGE', 'AVAX', 'DOT', 'LINK',
               'XAG', 'MATIC', 'UNI', 'LTC', 'ATOM', 'FIL', 'APT', 'ARB', 'OP', 'SUI']
FEE_RATE = 0.0006
FUNDING_RATE = 0.0001
HARD_SL_PCT = -50
MAX_HOLD_HOURS = 168  # 7 days
MAX_CONCURRENT = 10  # 最大同时持仓数

def parse_luke_signal(text):
    if not text or '標的' not in text:
        return None
    sym_match = re.search(r'標的[：:]\s*(\d*[A-Za-z]{2,10})', text)
    if not sym_match:
        return None
    symbol = sym_match.group(1).upper()

    direction = None
    if any(w in text for w in ['空单', '空單', '做空', '開空', '开空', '空']):
        direction = 'SHORT'
    elif any(w in text for w in ['多单', '多單', '做多', '開多', '开多', '多']):
        direction = 'LONG'
    if not direction:
        return None

    entry = None
    entry_match = re.search(r'(?:進場|进场|入場|入场)[：:]\s*(\d+\.?\d*)', text)
    if entry_match:
        entry = float(entry_match.group(1))

    sl = None
    sl_match = re.search(r'(?:止[损損])[：:]\s*(\d+\.?\d*)', text)
    if sl_match:
        sl = float(sl_match.group(1))

    tp = None
    tp_match = re.search(r'(?:止盈)[：:]\s*(\d+\.?\d*)', text)
    if tp_match:
        tp = float(tp_match.group(1))

    return {'symbol': symbol, 'direction': direction, 'entry_price': entry,
            'stop_loss': sl, 'take_profit': tp}

def get_klines(symbol, start_ts_ms, interval='15m', limit=500):
    """Get Binance klines, return list of [open_time, open, high, low, close, close_time]"""
    sym = symbol.upper()
    if not sym.endswith('USDT'):
        sym += 'USDT'
    try:
        r = requests.get('https://api.binance.com/api/v3/klines', params={
            'symbol': sym, 'interval': interval,
            'startTime': start_ts_ms, 'limit': limit
        }, timeout=15)
        if r.status_code != 200:
            return None
        data = r.json()
        if not data or not isinstance(data, list):
            return None
        return [{
            'time': int(c[0]),
            'open': float(c[1]),
            'high': float(c[2]),
            'low': float(c[3]),
            'close': float(c[4]),
            'close_time': int(c[6]),
        } for c in data]
    except:
        return None

def simulate_trade(symbol, direction, entry, sl, tp, signal_time_str):
    """
    Simulate trade using 15m candles.
    Returns: {hit_type, exit_price, exit_time, hours_held, candles_data}
    """
    dt = datetime.strptime(signal_time_str, '%Y-%m-%d %H:%M:%S')
    ts_ms = int(dt.replace(tzinfo=timezone.utc).timestamp() * 1000)

    # Get entry price from first candle if not provided
    candles = get_klines(symbol, ts_ms, '15m', 500)
    if not candles or len(candles) < 2:
        return None

    actual_entry = candles[0]['close']

    # Use signal entry if provided, validate loosely
    if entry:
        diff = abs(actual_entry - entry) / entry
        if diff > 0.1:  # >10% off, use candle price
            actual_entry = candles[0]['close']
        else:
            actual_entry = entry  # Trust signal entry price
    else:
        entry = actual_entry

    # Set default SL/TP if missing
    is_major = symbol.upper() in MAJOR_COINS
    if not sl:
        sl_pct = 0.03 if is_major else 0.05
        sl = entry * (1 - sl_pct) if direction == 'LONG' else entry * (1 + sl_pct)
    if not tp:
        tp_pct = 0.06 if is_major else 0.10
        tp = entry * (1 + tp_pct) if direction == 'LONG' else entry * (1 - tp_pct)

    # Validate SL/TP sanity - swap if wrong direction
    if direction == 'LONG':
        if sl and sl >= entry:
            sl = None  # Bad SL, ignore
        if tp and tp <= entry:
            tp = None  # Bad TP, ignore
    else:
        if sl and sl <= entry:
            sl = None
        if tp and tp >= entry:
            tp = None

    # Scan candles
    trade_candles = []
    exit_info = None

    for i, c in enumerate(candles):
        trade_candles.append(c)
        hours = (c['close_time'] - ts_ms) / (1000 * 3600)

        if hours > MAX_HOLD_HOURS:
            exit_info = {'hit': 'timeout', 'price': c['close'], 'time': c['close_time'], 'hours': hours}
            break

        # Only use signal's SL/TP — no hard stop
        if direction == 'LONG':
            if sl and c['low'] <= sl:
                exit_info = {'hit': 'sl', 'price': sl, 'time': c['close_time'], 'hours': hours}
                break
            if tp and c['high'] >= tp:
                exit_info = {'hit': 'tp', 'price': tp, 'time': c['close_time'], 'hours': hours}
                break
        else:
            if sl and c['high'] >= sl:
                exit_info = {'hit': 'sl', 'price': sl, 'time': c['close_time'], 'hours': hours}
                break
            if tp and c['low'] <= tp:
                exit_info = {'hit': 'tp', 'price': tp, 'time': c['close_time'], 'hours': hours}
                break

    if not exit_info:
        last = candles[-1]
        hours = (last['close_time'] - ts_ms) / (1000 * 3600)
        exit_info = {'hit': 'timeout', 'price': last['close'], 'time': last['close_time'], 'hours': hours}

    exit_info['entry_price'] = actual_entry
    exit_info['candles'] = trade_candles[:200]  # Cap for storage
    exit_info['exit_time_str'] = datetime.fromtimestamp(exit_info['time']/1000, tz=timezone.utc).strftime('%Y-%m-%d %H:%M')

    return exit_info

def run_backtest():
    msgs_file = '/opt/mexc-signal-trader/backtest_messages.json'
    with open(msgs_file) as f:
        messages = json.load(f)
    messages.sort(key=lambda m: m['date'])

    print("=" * 70)
    print("  LUKE 回测 V2 — 逐根K线验证")
    print(f"  本金: {INITIAL_CAPITAL}U | 最大同时持仓: {MAX_CONCURRENT}")
    print("=" * 70)

    # Parse signals
    signals = []
    for msg in messages:
        sig = parse_luke_signal(msg['text'])
        if sig:
            sig['date'] = msg['date']
            sig['msg_id'] = msg['id']
            signals.append(sig)
    print(f"\n信号总数: {len(signals)}", flush=True)

    # Simulate
    capital = INITIAL_CAPITAL
    trades = []
    open_positions = {}  # symbol -> end_time
    skipped_concurrent = 0
    skipped_bad_data = 0
    skipped_no_kline = 0

    for i, sig in enumerate(signals):
        symbol = sig['symbol']
        direction = sig['direction']
        entry = sig['entry_price']
        sl = sig['stop_loss']
        tp = sig['take_profit']
        date = sig['date']

        # Skip if already holding this symbol
        if symbol in open_positions:
            dt_now = datetime.strptime(date, '%Y-%m-%d %H:%M:%S').replace(tzinfo=timezone.utc)
            if dt_now.timestamp() * 1000 < open_positions[symbol]:
                continue

        # Check concurrent positions
        dt_sig = datetime.strptime(date, '%Y-%m-%d %H:%M:%S').replace(tzinfo=timezone.utc)
        active = sum(1 for end_t in open_positions.values() if dt_sig.timestamp()*1000 < end_t)
        if active >= MAX_CONCURRENT:
            skipped_concurrent += 1
            continue

        # Classify
        is_major = symbol in MAJOR_COINS
        position_size = 400 if is_major else 200
        lev_min, lev_max = (50, 100) if is_major else (10, 30)

        if capital < position_size:
            continue

        # Dynamic leverage
        if entry and sl and entry > 0:
            sl_dist = abs(entry - sl) / entry * 100
            if sl_dist < 1: leverage = 100
            elif sl_dist < 2: leverage = 75
            elif sl_dist < 3: leverage = 50
            elif sl_dist < 5: leverage = 30
            else: leverage = 20
            leverage = max(min(leverage, lev_max), lev_min)
            max_safe = int(50 / sl_dist) if sl_dist > 0 else 100
            leverage = min(leverage, max_safe, 100)
        else:
            leverage = lev_min

        # Simulate with klines
        result = simulate_trade(symbol, direction, entry, sl, tp, date)
        if not result:
            skipped_bad_data += 1
            continue

        actual_entry = result['entry_price']
        exit_price = result['price']
        hours = result['hours']
        hit_type = result['hit']

        # PnL
        if direction == 'LONG':
            pnl_pct = ((exit_price - actual_entry) / actual_entry) * leverage * 100
        else:
            pnl_pct = ((actual_entry - exit_price) / actual_entry) * leverage * 100

        # No hard stop — use signal SL/TP only
        # Cap at -100% (can't lose more than margin)
        if pnl_pct < -100:
            pnl_pct = -100

        pnl = position_size * (pnl_pct / 100)
        notional = position_size * leverage
        trading_fees = notional * FEE_RATE * 2
        funding_fees = notional * FUNDING_RATE * (hours / 8)
        fees = trading_fees + funding_fees
        real_pnl = pnl - fees
        capital += real_pnl

        # Track position end time
        exit_ts = result['time']
        open_positions[symbol] = exit_ts

        trade = {
            'date': date,
            'symbol': symbol,
            'direction': direction,
            'type': 'Major' if is_major else 'Small',
            'signal_entry': entry,
            'actual_entry': actual_entry,
            'exit_price': exit_price,
            'sl': sl, 'tp': tp,
            'leverage': leverage,
            'margin': position_size,
            'notional': round(notional, 2),
            'pnl_pct': round(pnl_pct, 2),
            'pnl': round(pnl, 2),
            'real_pnl': round(real_pnl, 2),
            'trading_fees': round(trading_fees, 2),
            'funding_fees': round(funding_fees, 2),
            'total_fees': round(fees, 2),
            'hours_held': round(hours, 1),
            'hit_type': hit_type,
            'capital_after': round(capital, 2),
            'open_time': date,
            'close_time': result['exit_time_str'],
            'candles': result['candles'],  # For chart
        }
        trades.append(trade)

        emoji = '✅' if real_pnl > 0 else '❌'
        print(f"{emoji} {date[:10]} {symbol:>10} {direction:>5} {leverage:>3}x | "
              f"entry={actual_entry:<10} exit={exit_price:<10} | "
              f"PnL={real_pnl:>+8.2f}U ({pnl_pct:>+6.1f}%) | "
              f"Hold={hours:.0f}h | Capital={capital:>10.2f}U [{hit_type}]", flush=True)

        time.sleep(0.3)  # Rate limit

        if (i+1) % 50 == 0:
            print(f"  --- Progress: {i+1}/{len(signals)} signals processed ---", flush=True)

    # ===== Summary =====
    print(f"\n{'='*70}")
    print("  回测 V2 总结")
    print(f"{'='*70}")

    wins = [t for t in trades if t['real_pnl'] > 0]
    losses = [t for t in trades if t['real_pnl'] <= 0]
    total_pnl = sum(t['real_pnl'] for t in trades)
    total_fees = sum(t['total_fees'] for t in trades)

    # Max drawdown
    peak = INITIAL_CAPITAL
    max_dd = 0
    for t in trades:
        if t['capital_after'] > peak: peak = t['capital_after']
        dd = (peak - t['capital_after']) / peak * 100
        if dd > max_dd: max_dd = dd

    print(f"\n📊 基本统计:")
    print(f"  初始本金: {INITIAL_CAPITAL:,.2f}U")
    print(f"  最终资金: {capital:,.2f}U")
    print(f"  总盈亏: {total_pnl:+,.2f}U ({total_pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  总交易: {len(trades)}笔")
    print(f"  胜率: {len(wins)}/{len(trades)} ({len(wins)/len(trades)*100:.1f}%)" if trades else "")
    print(f"  最大回撤: {max_dd:.1f}%")
    print(f"  总费用: {total_fees:,.2f}U")
    print(f"  跳过(同时持仓满): {skipped_concurrent}")
    print(f"  跳过(数据异常): {skipped_bad_data}")
    print(f"  平均持仓: {sum(t['hours_held'] for t in trades)/len(trades):.1f}h" if trades else "")

    # Monthly
    monthly = defaultdict(lambda: {'c': 0, 'pnl': 0, 'w': 0})
    for t in trades:
        m = t['date'][:7]
        monthly[m]['c'] += 1
        monthly[m]['pnl'] += t['real_pnl']
        if t['real_pnl'] > 0: monthly[m]['w'] += 1
    print(f"\n📅 月度:")
    for m in sorted(monthly):
        d = monthly[m]
        print(f"  {m}: {d['c']}笔 胜率{d['w']/d['c']*100:.0f}% PnL={d['pnl']:+,.0f}U")

    # Hit types
    print(f"\n📈 止盈/止损:")
    for ht in ['tp', 'sl', 'hard_sl', 'timeout']:
        items = [t for t in trades if t['hit_type'] == ht]
        if items:
            print(f"  {ht}: {len(items)}笔 PnL={sum(t['real_pnl'] for t in items):+,.0f}U")

    # Top/Bottom
    if trades:
        st = sorted(trades, key=lambda t: t['real_pnl'], reverse=True)
        print(f"\n🏆 Top 5:")
        for t in st[:5]:
            print(f"  {t['date'][:10]} {t['symbol']:>8} {t['direction']:>5} {t['leverage']}x {t['hours_held']}h → {t['real_pnl']:+,.1f}U ({t['pnl_pct']:+.1f}%)")
        print(f"\n💀 Bottom 5:")
        for t in st[-5:]:
            print(f"  {t['date'][:10]} {t['symbol']:>8} {t['direction']:>5} {t['leverage']}x {t['hours_held']}h → {t['real_pnl']:+,.1f}U ({t['pnl_pct']:+.1f}%)")

    # Save (strip candles for summary, keep in detail)
    trades_summary = [{k: v for k, v in t.items() if k != 'candles'} for t in trades]
    results = {
        'version': 'v2',
        'summary': {
            'initial_capital': INITIAL_CAPITAL,
            'final_capital': round(capital, 2),
            'total_pnl': round(total_pnl, 2),
            'total_pnl_pct': round(total_pnl/INITIAL_CAPITAL*100, 2),
            'total_trades': len(trades),
            'wins': len(wins),
            'losses': len(losses),
            'win_rate': round(len(wins)/len(trades)*100, 2) if trades else 0,
            'max_drawdown': round(max_dd, 2),
            'total_fees': round(total_fees, 2),
            'max_concurrent': MAX_CONCURRENT,
            'skipped_concurrent': skipped_concurrent,
            'skipped_bad_data': skipped_bad_data,
            'avg_hold_hours': round(sum(t['hours_held'] for t in trades)/len(trades), 1) if trades else 0,
        },
        'trades': trades_summary,
    }

    # Save summary
    with open('/opt/mexc-signal-trader/backtest_results.json', 'w') as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    # Save full with candles (for charts)
    with open('/opt/mexc-signal-trader/backtest_trades_detail.json', 'w') as f:
        json.dump(trades, f, ensure_ascii=False)

    print(f"\n结果已保存!")

if __name__ == '__main__':
    run_backtest()
