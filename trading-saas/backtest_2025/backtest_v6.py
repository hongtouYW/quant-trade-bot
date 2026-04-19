#!/usr/bin/env python3
"""V6 strategy backtest with detailed per-trade signal snapshot + OHLC path.

Outputs SQLite database with:
  - backtest_runs: per-symbol summary
  - trades: per-trade + entry signal snapshot
  - trade_klines: OHLC during holding period (for charts)
"""
import os
import json
import time
import sqlite3
from datetime import datetime

BASE_DIR = '/opt/backtest_2025'
CACHE_DIR = os.path.join(BASE_DIR, 'data', 'klines_cache')
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025.db')

# === v6 strategy config (current prod) ===
CONFIG = {
    'initial_capital': 10000,
    'fee_rate': 0.0005,
    'min_score': 70,
    'long_min_score': 85,
    'max_positions': 20,
    'max_leverage': 3,
    'cooldown_bars': 1,  # per-symbol in live, but 1-bar global in backtest aligns with report
    'short_bias': 1.05,
    'enable_trend_filter': True,
    'long_ma_slope_threshold': 0.02,
    'ma_slope_threshold': 0.01,
    'roi_stop_loss': -10,
    'roi_trailing_start': 6,
    'roi_trailing_distance': 3,
}


# === Technical indicators ===
def calc_rsi(prices, period=14):
    if len(prices) < period + 1:
        return 50
    deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
    gains = [d if d > 0 else 0 for d in deltas[-period:]]
    losses = [-d if d < 0 else 0 for d in deltas[-period:]]
    avg_g = sum(gains) / period
    avg_l = sum(losses) / period
    if avg_l == 0:
        return 100
    rs = avg_g / avg_l
    return 100 - (100 / (1 + rs))


def calc_macd(closes, fast=12, slow=26, signal=9):
    if len(closes) < slow + signal:
        return None
    def ema(data, period):
        k = 2 / (period + 1)
        result = [data[0]]
        for i in range(1, len(data)):
            result.append(data[i] * k + result[-1] * (1 - k))
        return result
    ema_fast = ema(closes, fast)
    ema_slow = ema(closes, slow)
    macd_line = [f - s for f, s in zip(ema_fast, ema_slow)]
    signal_line = ema(macd_line[slow-1:], signal)
    hist = macd_line[-1] - signal_line[-1]
    prev_hist = macd_line[-2] - signal_line[-2]
    return {
        'macd': macd_line[-1], 'signal': signal_line[-1],
        'histogram': hist, 'prev_histogram': prev_hist,
        'crossover_up': prev_hist <= 0 and hist > 0,
        'crossover_down': prev_hist >= 0 and hist < 0,
    }


def calc_bollinger(closes, period=20, std_dev=2):
    if len(closes) < period:
        return None
    sma = sum(closes[-period:]) / period
    variance = sum((c - sma) ** 2 for c in closes[-period:]) / period
    std = variance ** 0.5
    upper = sma + std_dev * std
    lower = sma - std_dev * std
    pct_b = (closes[-1] - lower) / (upper - lower) if upper > lower else 0.5
    return {'upper': upper, 'middle': sma, 'lower': lower, 'percent_b': pct_b}


def calc_adx(bars, period=14):
    """bars: list of [time, open, high, low, close, volume]"""
    if len(bars) < period * 2 + 1:
        return None
    highs = [b[2] for b in bars]
    lows = [b[3] for b in bars]
    closes = [b[4] for b in bars]
    tr_list, plus_dm, minus_dm = [], [], []
    for i in range(1, len(bars)):
        h, l, pc = highs[i], lows[i], closes[i-1]
        tr_list.append(max(h - l, abs(h - pc), abs(l - pc)))
        up = highs[i] - highs[i-1]
        dn = lows[i-1] - lows[i]
        plus_dm.append(up if up > dn and up > 0 else 0)
        minus_dm.append(dn if dn > up and dn > 0 else 0)

    def smooth(data, p):
        s = sum(data[:p])
        result = [s]
        for i in range(p, len(data)):
            s = s - s / p + data[i]
            result.append(s)
        return result

    atr = smooth(tr_list, period)
    s_plus = smooth(plus_dm, period)
    s_minus = smooth(minus_dm, period)
    dx_list = []
    for i in range(len(atr)):
        if atr[i] == 0:
            dx_list.append(0)
            continue
        di_p = 100 * s_plus[i] / atr[i]
        di_m = 100 * s_minus[i] / atr[i]
        di_sum = di_p + di_m
        dx_list.append(100 * abs(di_p - di_m) / di_sum if di_sum > 0 else 0)
    if len(dx_list) < period:
        return None
    return sum(dx_list[-period:]) / period


# === V6 signal analysis ===
def analyze_signal_v6(lookback):
    """lookback: list of last 100 bars [time, open, high, low, close, volume]
    Returns: (score, analysis_dict)
    """
    if len(lookback) < 50:
        return 0, None

    closes = [b[4] for b in lookback]
    volumes = [b[5] for b in lookback]
    highs = [b[2] for b in lookback]
    lows = [b[3] for b in lookback]
    price = closes[-1]

    votes = {'LONG': 0, 'SHORT': 0}

    rsi = calc_rsi(closes)
    if rsi < 30:
        rsi_sc = 30; votes['LONG'] += 1
    elif rsi > 70:
        rsi_sc = 30; votes['SHORT'] += 1
    elif rsi < 45:
        rsi_sc = 15; votes['LONG'] += 1
    elif rsi > 55:
        rsi_sc = 15; votes['SHORT'] += 1
    else:
        rsi_sc = 5

    ma7 = sum(closes[-7:]) / 7
    ma20 = sum(closes[-20:]) / 20
    ma50 = sum(closes[-50:]) / 50
    if price > ma7 > ma20 > ma50:
        trend_sc = 30; votes['LONG'] += 2
    elif price < ma7 < ma20 < ma50:
        trend_sc = 30; votes['SHORT'] += 2
    elif price > ma7 > ma20:
        trend_sc = 15; votes['LONG'] += 1
    elif price < ma7 < ma20:
        trend_sc = 15; votes['SHORT'] += 1
    else:
        trend_sc = 5

    avg_vol = sum(volumes[-20:]) / 20
    vol_ratio = volumes[-1] / avg_vol if avg_vol > 0 else 1
    if vol_ratio > 1.5:
        vol_sc = 20
    elif vol_ratio > 1.2:
        vol_sc = 15
    elif vol_ratio > 1:
        vol_sc = 10
    else:
        vol_sc = 5

    h50 = max(highs[-50:])
    l50 = min(lows[-50:])
    pos = (price - l50) / (h50 - l50) if h50 > l50 else 0.5
    if pos < 0.2:
        pos_sc = 20; votes['LONG'] += 1
    elif pos > 0.8:
        pos_sc = 20; votes['SHORT'] += 1
    elif pos < 0.35:
        pos_sc = 10; votes['LONG'] += 1
    elif pos > 0.65:
        pos_sc = 10; votes['SHORT'] += 1
    else:
        pos_sc = 5

    if votes['LONG'] > votes['SHORT']:
        direction = 'LONG'
    elif votes['SHORT'] > votes['LONG']:
        direction = 'SHORT'
    else:
        direction = 'LONG' if rsi < 50 else 'SHORT'

    total = rsi_sc + trend_sc + vol_sc + pos_sc

    rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
    trend_dir = 'LONG' if price > ma20 else 'SHORT'
    if rsi_dir != trend_dir:
        total = int(total * 0.85)

    bonus = 0
    macd = calc_macd(closes)
    bb = calc_bollinger(closes)
    adx = calc_adx(lookback)

    macd_hist = None
    macd_prev = None
    if macd:
        hist = macd['histogram']
        prev_hist = macd['prev_histogram']
        macd_hist = hist
        macd_prev = prev_hist
        macd_dir = 'LONG' if hist > 0 else 'SHORT'
        if macd_dir == direction:
            if macd['crossover_up'] or macd['crossover_down']:
                bonus += 10
            elif (hist > 0 and hist > prev_hist) or (hist < 0 and hist < prev_hist):
                bonus += 6
            else:
                bonus += 3

    if adx is not None:
        if adx >= 35:
            bonus += 8
        elif adx >= 25:
            bonus += 5
        elif adx >= 20:
            bonus += 2

    bb_pct = bb['percent_b'] if bb else None
    if bb:
        pct_b = bb['percent_b']
        if direction == 'LONG':
            if pct_b < 0.1:
                bonus += 7
            elif pct_b < 0.25:
                bonus += 4
        else:
            if pct_b > 0.9:
                bonus += 7
            elif pct_b > 0.75:
                bonus += 4

    total += bonus

    if direction == 'SHORT':
        total = int(total * 1.05)

    return total, {
        'price': price, 'rsi': rsi, 'ma7': ma7, 'ma20': ma20, 'ma50': ma50,
        'volume_ratio': vol_ratio, 'direction': direction, 'score': total,
        'bonus': bonus, 'adx': adx,
        'macd_hist': macd_hist, 'macd_prev': macd_prev,
        'bb_pct_b': bb_pct,
        'rsi_sc': rsi_sc, 'trend_sc': trend_sc, 'vol_sc': vol_sc, 'pos_sc': pos_sc,
        'price_position': pos,
    }


# === Position sizing (v6 aligned) ===
def calc_position_size(score, available, max_leverage=3):
    leverage = min(3, max_leverage)
    if score >= 85:
        size = min(400, available * 0.25)
    elif score >= 75:
        size = min(300, available * 0.20)
    elif score >= 70:
        size = min(200, available * 0.15)
    elif score >= 60:
        size = min(150, available * 0.10)
    elif score >= 55:
        size = min(100, available * 0.08)
    else:
        return 0, 0
    if size < 100:
        return 0, 0
    return int(size), leverage


# === Run single-symbol backtest ===
def run_symbol_backtest(symbol, bars, config):
    """Returns: (trades, summary, equity_curve)"""
    cap = config['initial_capital']
    positions = {}  # pos_id -> pos
    trades = []
    equity_curve = []
    cooldown_until = -1
    trade_id_counter = 0

    for i in range(100, len(bars)):
        candle = bars[i]
        if cap <= 0:
            break

        # Check existing positions
        to_close = []
        for pid, pos in positions.items():
            ep = pos['entry_price']
            lev = pos['leverage']
            high, low = candle[2], candle[3]
            if pos['direction'] == 'LONG':
                worst_roi = ((low - ep) / ep) * lev * 100
                best_roi = ((high - ep) / ep) * lev * 100
            else:
                worst_roi = ((ep - high) / ep) * lev * 100
                best_roi = ((ep - low) / ep) * lev * 100

            if best_roi > pos['peak_roi']:
                pos['peak_roi'] = best_roi

            # Log OHLC during hold
            pos['ohlc_path'].append([candle[0], candle[1], candle[2], candle[3], candle[4]])

            # Stop loss
            if worst_roi <= config['roi_stop_loss']:
                exit_pct = config['roi_stop_loss'] / (lev * 100)
                exit_price = ep * (1 + exit_pct) if pos['direction'] == 'LONG' else ep * (1 - exit_pct)
                to_close.append((pid, exit_price, f"SL hit ROI={config['roi_stop_loss']}%"))
                continue

            # Trailing TP
            if pos['peak_roi'] >= config['roi_trailing_start']:
                trail_exit_roi = pos['peak_roi'] - config['roi_trailing_distance']
                if worst_roi <= trail_exit_roi:
                    exit_pct = trail_exit_roi / (lev * 100)
                    exit_price = ep * (1 + exit_pct) if pos['direction'] == 'LONG' else ep * (1 - exit_pct)
                    reason = f"trail TP peak={pos['peak_roi']:.1f}% exit={trail_exit_roi:.1f}%"
                    to_close.append((pid, exit_price, reason))

        for pid, exit_price, reason in to_close:
            pos = positions[pid]
            bars_held = i - pos['bar_index']
            ep = pos['entry_price']
            amount = pos['amount']
            lev = pos['leverage']
            if pos['direction'] == 'LONG':
                pct = (exit_price - ep) / ep
            else:
                pct = (ep - exit_price) / ep
            pnl_raw = amount * pct * lev
            fee = amount * lev * config['fee_rate'] * 2
            funding = amount * lev * 0.0001 * (bars_held / 8)
            pnl = pnl_raw - fee - funding
            cap += pnl

            trade_id_counter += 1
            trades.append({
                'trade_id': trade_id_counter,
                'symbol': symbol,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': exit_price,
                'amount': amount,
                'leverage': lev,
                'pnl': round(pnl, 4),
                'roi_pct': round(pct * lev * 100, 2),
                'fee': round(fee, 4),
                'funding_fee': round(funding, 4),
                'entry_time': pos['entry_time'],
                'exit_time': candle[0],
                'hold_bars': bars_held,
                'hold_hours': bars_held,
                'score': pos['score'],
                'reason': reason,
                'peak_roi': round(pos['peak_roi'], 2),
                'stop_loss_price': pos['stop_loss_price'],
                'tp_trigger_price': pos['tp_trigger_price'],
                'entry_signal': pos['entry_signal'],
                'ohlc_path': pos['ohlc_path'],
            })
            del positions[pid]
            cooldown_until = i + config['cooldown_bars']

        if i % 50 == 0:
            equity_curve.append([candle[0], round(cap, 2)])

        # === Open new position ===
        if i <= cooldown_until:
            continue
        if len(positions) >= config['max_positions']:
            continue
        if cap <= 50:
            continue

        lookback = bars[max(0, i-99):i+1]
        score, analysis = analyze_signal_v6(lookback)
        if score < config['min_score'] or analysis is None:
            continue

        direction = analysis['direction']
        if direction == 'LONG' and score < config['long_min_score']:
            continue

        # MA20 slope filter
        if config['enable_trend_filter'] and len(lookback) >= 25:
            ma20_now = sum(c[4] for c in lookback[-20:]) / 20
            ma20_prev = sum(c[4] for c in lookback[-25:-5]) / 20
            ma20_slope = (ma20_now - ma20_prev) / ma20_prev if ma20_prev > 0 else 0
            if direction == 'LONG' and ma20_slope < -config['long_ma_slope_threshold']:
                continue
            if direction == 'SHORT' and ma20_slope > config['ma_slope_threshold']:
                continue

        used = sum(p['amount'] for p in positions.values())
        available = cap - used
        if available < 100:
            continue

        amount, leverage = calc_position_size(score, available, config['max_leverage'])
        if amount < 100:
            continue

        entry_price = analysis['price']
        stop_pct = config['roi_stop_loss'] / (leverage * 100)
        tp_pct = config['roi_trailing_start'] / (leverage * 100)
        if direction == 'LONG':
            stop_loss_price = entry_price * (1 + stop_pct)
            tp_trigger_price = entry_price * (1 + tp_pct)
        else:
            stop_loss_price = entry_price * (1 - stop_pct)
            tp_trigger_price = entry_price * (1 - tp_pct)

        pos_id = f'{symbol}_{i}'
        positions[pos_id] = {
            'symbol': symbol,
            'direction': direction,
            'entry_price': entry_price,
            'amount': amount,
            'leverage': leverage,
            'stop_loss_price': stop_loss_price,
            'tp_trigger_price': tp_trigger_price,
            'peak_roi': 0,
            'entry_time': candle[0],
            'bar_index': i,
            'score': score,
            'entry_signal': analysis,
            'ohlc_path': [[candle[0], candle[1], candle[2], candle[3], candle[4]]],
        }

    # Force-close remaining at last bar
    if bars and positions:
        last = bars[-1]
        for pid, pos in list(positions.items()):
            bars_held = len(bars) - 1 - pos['bar_index']
            ep = pos['entry_price']
            exit_price = last[4]
            amount = pos['amount']
            lev = pos['leverage']
            if pos['direction'] == 'LONG':
                pct = (exit_price - ep) / ep
            else:
                pct = (ep - exit_price) / ep
            pnl_raw = amount * pct * lev
            fee = amount * lev * config['fee_rate'] * 2
            funding = amount * lev * 0.0001 * (bars_held / 8)
            pnl = pnl_raw - fee - funding
            cap += pnl
            trade_id_counter += 1
            trades.append({
                'trade_id': trade_id_counter,
                'symbol': symbol,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': exit_price,
                'amount': amount,
                'leverage': lev,
                'pnl': round(pnl, 4),
                'roi_pct': round(pct * lev * 100, 2),
                'fee': round(fee, 4),
                'funding_fee': round(funding, 4),
                'entry_time': pos['entry_time'],
                'exit_time': last[0],
                'hold_bars': bars_held,
                'hold_hours': bars_held,
                'score': pos['score'],
                'reason': '回测结束强制平仓',
                'peak_roi': round(pos['peak_roi'], 2),
                'stop_loss_price': pos['stop_loss_price'],
                'tp_trigger_price': pos['tp_trigger_price'],
                'entry_signal': pos['entry_signal'],
                'ohlc_path': pos['ohlc_path'],
            })

    # Summary
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    summary = {
        'symbol': symbol,
        'total_trades': len(trades),
        'win_trades': len(wins),
        'loss_trades': len(losses),
        'win_rate': round(len(wins) / len(trades) * 100, 2) if trades else 0,
        'total_pnl': round(sum(t['pnl'] for t in trades), 2),
        'total_fees': round(sum(t['fee'] + t['funding_fee'] for t in trades), 2),
        'final_capital': round(cap, 2),
        'return_pct': round((cap - config['initial_capital']) / config['initial_capital'] * 100, 2),
        'avg_win': round(sum(t['pnl'] for t in wins) / len(wins), 2) if wins else 0,
        'avg_loss': round(sum(t['pnl'] for t in losses) / len(losses), 2) if losses else 0,
        'best_trade': round(max((t['pnl'] for t in trades), default=0), 2),
        'worst_trade': round(min((t['pnl'] for t in trades), default=0), 2),
    }
    return trades, summary, equity_curve


# === DB schema ===
def init_db(db_path):
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        symbol TEXT, start_date TEXT, end_date TEXT,
        total_trades INTEGER, win_trades INTEGER, loss_trades INTEGER,
        win_rate REAL, total_pnl REAL, total_fees REAL,
        final_capital REAL, return_pct REAL,
        avg_win REAL, avg_loss REAL, best_trade REAL, worst_trade REAL,
        run_time TEXT
    )''')
    c.execute('''CREATE TABLE IF NOT EXISTS trades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_id INTEGER, trade_id INTEGER, symbol TEXT,
        direction TEXT, entry_price REAL, exit_price REAL,
        amount REAL, leverage INTEGER, pnl REAL, roi_pct REAL,
        fee REAL, funding_fee REAL,
        entry_time INTEGER, exit_time INTEGER, hold_hours INTEGER,
        score INTEGER, reason TEXT, peak_roi REAL,
        stop_loss_price REAL, tp_trigger_price REAL,
        entry_rsi REAL, entry_ma7 REAL, entry_ma20 REAL, entry_ma50 REAL,
        entry_vol_ratio REAL, entry_adx REAL, entry_macd_hist REAL,
        entry_bb_pct_b REAL, entry_price_position REAL,
        entry_bonus INTEGER,
        FOREIGN KEY (run_id) REFERENCES runs(id)
    )''')
    c.execute('''CREATE TABLE IF NOT EXISTS trade_klines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trade_db_id INTEGER,
        time INTEGER, open REAL, high REAL, low REAL, close REAL,
        FOREIGN KEY (trade_db_id) REFERENCES trades(id)
    )''')
    c.execute('CREATE INDEX IF NOT EXISTS idx_trades_symbol ON trades(symbol)')
    c.execute('CREATE INDEX IF NOT EXISTS idx_trades_run ON trades(run_id)')
    c.execute('CREATE INDEX IF NOT EXISTS idx_klines_trade ON trade_klines(trade_db_id)')
    conn.commit()
    return conn


def save_run(conn, symbol, summary, trades):
    c = conn.cursor()
    c.execute('''INSERT INTO runs (symbol, start_date, end_date, total_trades, win_trades, loss_trades,
                 win_rate, total_pnl, total_fees, final_capital, return_pct,
                 avg_win, avg_loss, best_trade, worst_trade, run_time)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)''',
              (symbol, '2025-01-01', '2025-12-31',
               summary['total_trades'], summary['win_trades'], summary['loss_trades'],
               summary['win_rate'], summary['total_pnl'], summary['total_fees'],
               summary['final_capital'], summary['return_pct'],
               summary['avg_win'], summary['avg_loss'],
               summary['best_trade'], summary['worst_trade'],
               datetime.now().isoformat()))
    run_id = c.lastrowid

    for t in trades:
        sig = t['entry_signal']
        c.execute('''INSERT INTO trades (run_id, trade_id, symbol, direction, entry_price, exit_price,
                     amount, leverage, pnl, roi_pct, fee, funding_fee,
                     entry_time, exit_time, hold_hours, score, reason, peak_roi,
                     stop_loss_price, tp_trigger_price,
                     entry_rsi, entry_ma7, entry_ma20, entry_ma50,
                     entry_vol_ratio, entry_adx, entry_macd_hist,
                     entry_bb_pct_b, entry_price_position, entry_bonus)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)''',
                  (run_id, t['trade_id'], t['symbol'], t['direction'], t['entry_price'], t['exit_price'],
                   t['amount'], t['leverage'], t['pnl'], t['roi_pct'], t['fee'], t['funding_fee'],
                   t['entry_time'], t['exit_time'], t['hold_hours'], t['score'], t['reason'], t['peak_roi'],
                   t['stop_loss_price'], t['tp_trigger_price'],
                   sig.get('rsi'), sig.get('ma7'), sig.get('ma20'), sig.get('ma50'),
                   sig.get('volume_ratio'), sig.get('adx'), sig.get('macd_hist'),
                   sig.get('bb_pct_b'), sig.get('price_position'), sig.get('bonus')))
        trade_db_id = c.lastrowid

        for bar in t['ohlc_path']:
            c.execute('''INSERT INTO trade_klines (trade_db_id, time, open, high, low, close)
                         VALUES (?, ?, ?, ?, ?, ?)''',
                      (trade_db_id, bar[0], bar[1], bar[2], bar[3], bar[4]))
    conn.commit()
    return run_id


def main():
    import sys
    conn = init_db(DB_PATH)
    symbols = sys.argv[1:] if len(sys.argv) > 1 else None

    if symbols is None:
        # All cached symbols
        symbols = [f.replace('.json', '') for f in os.listdir(CACHE_DIR) if f.endswith('.json')]
        symbols.sort()

    total_trades = 0
    total_pnl = 0
    success_count = 0

    for i, sym in enumerate(symbols, 1):
        cache_path = os.path.join(CACHE_DIR, f'{sym}.json')
        if not os.path.exists(cache_path):
            print(f'[{i}/{len(symbols)}] {sym}: no cache, skip')
            continue
        try:
            with open(cache_path) as f:
                bars = json.load(f)
            if len(bars) < 500:
                print(f'[{i}/{len(symbols)}] {sym}: only {len(bars)} bars, skip')
                continue
            t0 = time.time()
            trades, summary, equity_curve = run_symbol_backtest(sym, bars, CONFIG)
            save_run(conn, sym, summary, trades)
            dt = time.time() - t0
            total_trades += summary['total_trades']
            total_pnl += summary['total_pnl']
            success_count += 1
            print(f'[{i}/{len(symbols)}] {sym}: trades={summary["total_trades"]} wr={summary["win_rate"]}% pnl=${summary["total_pnl"]:+.2f} ({dt:.1f}s)')
        except Exception as e:
            print(f'[{i}/{len(symbols)}] {sym}: ERROR {e}')
            import traceback; traceback.print_exc()

    conn.close()
    print(f'\n===== 全部完成 =====')
    print(f'成功: {success_count}/{len(symbols)}')
    print(f'总交易: {total_trades}')
    print(f'总PnL: ${total_pnl:+.2f}')
    print(f'数据库: {DB_PATH}')


if __name__ == '__main__':
    main()
