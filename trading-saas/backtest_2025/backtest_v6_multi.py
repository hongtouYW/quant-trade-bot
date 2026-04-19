#!/usr/bin/env python3
"""Multi-symbol V6 backtest — SHARED capital pool across all coins.
Matches live bot: 10000U total, 20 positions max globally, per-symbol cooldown.
"""
import os
import json
import time
import sqlite3
from datetime import datetime

# Reuse indicators and analyze_signal_v6 from backtest_v6.py
import sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from backtest_v6 import analyze_signal_v6, calc_position_size

BASE_DIR = '/opt/backtest_2025'
CACHE_DIR = os.path.join(BASE_DIR, 'data', 'klines_cache')
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025_multi.db')

CONFIG = {
    'initial_capital': 10000,   # TOTAL, shared across all coins
    'fee_rate': 0.0005,
    'min_score': 70,
    'long_min_score': 85,
    'max_positions': 20,        # GLOBAL across all coins
    'max_leverage': 3,
    'cooldown_bars': 1,         # per-symbol (live logic)
    'enable_trend_filter': True,
    'long_ma_slope_threshold': 0.02,
    'ma_slope_threshold': 0.01,
    'roi_stop_loss': -10,
    'roi_trailing_start': 6,
    'roi_trailing_distance': 3,
}


def init_db(db_path):
    if os.path.exists(db_path):
        os.remove(db_path)
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    c.execute('''CREATE TABLE run_summary (
        id INTEGER PRIMARY KEY,
        start_date TEXT, end_date TEXT,
        initial_capital REAL, final_capital REAL, total_pnl REAL, return_pct REAL,
        total_trades INTEGER, win_trades INTEGER, loss_trades INTEGER, win_rate REAL,
        total_fees REAL, max_drawdown_pct REAL, peak_capital REAL,
        total_symbols_eligible INTEGER, symbols_traded INTEGER,
        run_time TEXT
    )''')
    c.execute('''CREATE TABLE trades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trade_id INTEGER, symbol TEXT,
        direction TEXT, entry_price REAL, exit_price REAL,
        amount REAL, leverage INTEGER, pnl REAL, roi_pct REAL,
        fee REAL, funding_fee REAL,
        entry_time INTEGER, exit_time INTEGER, hold_hours INTEGER,
        score INTEGER, reason TEXT, peak_roi REAL,
        stop_loss_price REAL, tp_trigger_price REAL,
        capital_before REAL, capital_after REAL,
        entry_rsi REAL, entry_ma7 REAL, entry_ma20 REAL, entry_ma50 REAL,
        entry_vol_ratio REAL, entry_adx REAL, entry_macd_hist REAL,
        entry_bb_pct_b REAL, entry_price_position REAL, entry_bonus INTEGER
    )''')
    c.execute('''CREATE TABLE trade_klines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trade_db_id INTEGER,
        time INTEGER, open REAL, high REAL, low REAL, close REAL
    )''')
    c.execute('''CREATE TABLE equity_curve (
        time INTEGER, capital REAL, positions_count INTEGER, floating_pnl REAL
    )''')
    c.execute('CREATE INDEX idx_trade_sym ON trades(symbol)')
    c.execute('CREATE INDEX idx_klines_trade ON trade_klines(trade_db_id)')
    conn.commit()
    return conn


def load_all_klines():
    """Load all cached klines, returning {symbol: bars} aligned by time."""
    all_data = {}
    files = sorted([f for f in os.listdir(CACHE_DIR) if f.endswith('.json')])
    for f in files:
        sym = f.replace('.json', '')
        with open(os.path.join(CACHE_DIR, f)) as fp:
            bars = json.load(fp)
        if len(bars) < 500:
            continue
        all_data[sym] = bars
    return all_data


def build_time_index(all_data):
    """Build mapping: timestamp_ms → {symbol: bar}.
    Use the most common timeline (from BTC or ETH) as reference.
    """
    # Use the symbol with most bars as reference timeline
    ref_sym = max(all_data.keys(), key=lambda s: len(all_data[s]))
    ref_times = [b[0] for b in all_data[ref_sym]]
    time_to_idx = {t: i for i, t in enumerate(ref_times)}

    # For each symbol, build parallel array aligned to ref_times (None if missing)
    aligned = {}
    for sym, bars in all_data.items():
        sym_map = {b[0]: b for b in bars}
        aligned[sym] = [sym_map.get(t) for t in ref_times]

    return ref_times, aligned


def run_multi_backtest(all_data, config):
    ref_times, aligned = build_time_index(all_data)
    N = len(ref_times)
    print(f'Loaded {len(all_data)} symbols, {N} time bars')

    cap = config['initial_capital']
    positions = {}      # symbol -> position dict
    cooldowns = {}      # symbol -> bar_index_when_closed (per-symbol)
    trades = []
    equity_curve = []
    trade_id_counter = 0
    peak_cap = cap
    max_dd = 0

    for i in range(100, N):
        t = ref_times[i]
        if cap <= 50:
            print(f'  bar={i} BANKRUPT at ${cap:.2f}')
            break

        # === Step 1: check all existing positions for SL/TP ===
        to_close = []
        for sym, pos in positions.items():
            bar = aligned[sym][i]
            if bar is None:
                continue  # no data this bar, use last available

            ep = pos['entry_price']
            lev = pos['leverage']
            high = bar[2]; low = bar[3]
            if pos['direction'] == 'LONG':
                worst_roi = ((low - ep) / ep) * lev * 100
                best_roi = ((high - ep) / ep) * lev * 100
            else:
                worst_roi = ((ep - high) / ep) * lev * 100
                best_roi = ((ep - low) / ep) * lev * 100

            if best_roi > pos['peak_roi']:
                pos['peak_roi'] = best_roi

            pos['ohlc_path'].append([bar[0], bar[1], bar[2], bar[3], bar[4]])

            # Stop loss
            if worst_roi <= config['roi_stop_loss']:
                exit_pct = config['roi_stop_loss'] / (lev * 100)
                exit_price = ep * (1 + exit_pct) if pos['direction'] == 'LONG' else ep * (1 - exit_pct)
                to_close.append((sym, exit_price, f"SL hit ROI={config['roi_stop_loss']}%"))
                continue

            # Trailing TP
            if pos['peak_roi'] >= config['roi_trailing_start']:
                trail_exit_roi = pos['peak_roi'] - config['roi_trailing_distance']
                if worst_roi <= trail_exit_roi:
                    exit_pct = trail_exit_roi / (lev * 100)
                    exit_price = ep * (1 + exit_pct) if pos['direction'] == 'LONG' else ep * (1 - exit_pct)
                    reason = f"trail TP peak={pos['peak_roi']:.1f}% exit={trail_exit_roi:.1f}%"
                    to_close.append((sym, exit_price, reason))

        for sym, exit_price, reason in to_close:
            pos = positions[sym]
            bars_held = i - pos['bar_index']
            ep = pos['entry_price']
            amount = pos['amount']
            lev = pos['leverage']
            pct = (exit_price - ep) / ep if pos['direction'] == 'LONG' else (ep - exit_price) / ep
            pnl_raw = amount * pct * lev
            fee = amount * lev * config['fee_rate'] * 2
            funding = amount * lev * 0.0001 * (bars_held / 8)
            pnl = pnl_raw - fee - funding
            cap_before = cap
            cap += pnl

            trade_id_counter += 1
            trades.append({
                'trade_id': trade_id_counter,
                'symbol': sym,
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
                'exit_time': t,
                'hold_hours': bars_held,
                'score': pos['score'],
                'reason': reason,
                'peak_roi': round(pos['peak_roi'], 2),
                'stop_loss_price': pos['stop_loss_price'],
                'tp_trigger_price': pos['tp_trigger_price'],
                'capital_before': round(cap_before, 2),
                'capital_after': round(cap, 2),
                'entry_signal': pos['entry_signal'],
                'ohlc_path': pos['ohlc_path'],
            })
            del positions[sym]
            cooldowns[sym] = i + config['cooldown_bars']

        peak_cap = max(peak_cap, cap)
        dd = (peak_cap - cap) / peak_cap * 100 if peak_cap > 0 else 0
        max_dd = max(max_dd, dd)

        # Record equity curve every 50 bars
        if i % 50 == 0:
            float_pnl = 0
            for sym, pos in positions.items():
                bar = aligned[sym][i]
                if bar is None: continue
                ep = pos['entry_price']
                lev = pos['leverage']
                cp = bar[4]
                if pos['direction'] == 'LONG':
                    pct = (cp - ep) / ep
                else:
                    pct = (ep - cp) / ep
                float_pnl += pos['amount'] * pct * lev
            equity_curve.append([t, round(cap, 2), len(positions), round(float_pnl, 2)])

        # === Step 2: scan all coins for new position candidates ===
        if len(positions) >= config['max_positions']:
            continue

        used = sum(p['amount'] for p in positions.values())
        available = cap - used
        if available < 100:
            continue

        candidates = []  # (score, symbol, analysis, bar)
        for sym, sym_bars in aligned.items():
            if sym in positions:
                continue
            if sym in cooldowns and i <= cooldowns[sym]:
                continue
            if sym_bars[i] is None:
                continue

            # build lookback from this symbol's aligned bars (skip Nones)
            lookback = []
            j = i
            while j >= 0 and len(lookback) < 100:
                b = sym_bars[j]
                if b is not None:
                    lookback.insert(0, b)
                j -= 1
            if len(lookback) < 50:
                continue

            score, analysis = analyze_signal_v6(lookback)
            if score < config['min_score'] or analysis is None:
                continue
            direction = analysis['direction']
            if direction == 'LONG' and score < config['long_min_score']:
                continue

            if config['enable_trend_filter'] and len(lookback) >= 25:
                ma20_now = sum(c[4] for c in lookback[-20:]) / 20
                ma20_prev = sum(c[4] for c in lookback[-25:-5]) / 20
                ma20_slope = (ma20_now - ma20_prev) / ma20_prev if ma20_prev > 0 else 0
                if direction == 'LONG' and ma20_slope < -config['long_ma_slope_threshold']:
                    continue
                if direction == 'SHORT' and ma20_slope > config['ma_slope_threshold']:
                    continue

            candidates.append((score, sym, analysis, sym_bars[i]))

        # Sort by score desc, open top-N until we fill positions or run out of money
        candidates.sort(key=lambda x: -x[0])
        for score, sym, analysis, bar in candidates:
            if len(positions) >= config['max_positions']:
                break
            used = sum(p['amount'] for p in positions.values())
            avail = cap - used
            if avail < 100:
                break

            amount, leverage = calc_position_size(score, avail, config['max_leverage'])
            if amount < 100:
                continue

            entry_price = analysis['price']
            stop_pct = config['roi_stop_loss'] / (leverage * 100)
            tp_pct = config['roi_trailing_start'] / (leverage * 100)
            if analysis['direction'] == 'LONG':
                stop_loss_price = entry_price * (1 + stop_pct)
                tp_trigger_price = entry_price * (1 + tp_pct)
            else:
                stop_loss_price = entry_price * (1 - stop_pct)
                tp_trigger_price = entry_price * (1 - tp_pct)

            positions[sym] = {
                'symbol': sym,
                'direction': analysis['direction'],
                'entry_price': entry_price,
                'amount': amount,
                'leverage': leverage,
                'stop_loss_price': stop_loss_price,
                'tp_trigger_price': tp_trigger_price,
                'peak_roi': 0,
                'entry_time': t,
                'bar_index': i,
                'score': score,
                'entry_signal': analysis,
                'ohlc_path': [[bar[0], bar[1], bar[2], bar[3], bar[4]]],
            }

        # Progress
        if i % 500 == 0:
            print(f'  bar={i}/{N} cap=${cap:.2f} pos={len(positions)} trades={trade_id_counter} dd={max_dd:.2f}%')

    # Force-close remaining at last bar
    if positions:
        i = N - 1
        last_t = ref_times[i]
        for sym, pos in list(positions.items()):
            bar = aligned[sym][i] or aligned[sym][i-1] or aligned[sym][i-2]
            if bar is None:
                continue
            bars_held = i - pos['bar_index']
            ep = pos['entry_price']
            exit_price = bar[4]
            amount = pos['amount']
            lev = pos['leverage']
            pct = (exit_price - ep) / ep if pos['direction'] == 'LONG' else (ep - exit_price) / ep
            pnl_raw = amount * pct * lev
            fee = amount * lev * config['fee_rate'] * 2
            funding = amount * lev * 0.0001 * (bars_held / 8)
            pnl = pnl_raw - fee - funding
            cap_before = cap
            cap += pnl
            trade_id_counter += 1
            trades.append({
                'trade_id': trade_id_counter,
                'symbol': sym,
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
                'exit_time': last_t,
                'hold_hours': bars_held,
                'score': pos['score'],
                'reason': '回测结束强制平仓',
                'peak_roi': round(pos['peak_roi'], 2),
                'stop_loss_price': pos['stop_loss_price'],
                'tp_trigger_price': pos['tp_trigger_price'],
                'capital_before': round(cap_before, 2),
                'capital_after': round(cap, 2),
                'entry_signal': pos['entry_signal'],
                'ohlc_path': pos['ohlc_path'],
            })

    # Final stats
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    summary = {
        'initial_capital': config['initial_capital'],
        'final_capital': round(cap, 2),
        'total_pnl': round(cap - config['initial_capital'], 2),
        'return_pct': round((cap - config['initial_capital']) / config['initial_capital'] * 100, 2),
        'total_trades': len(trades),
        'win_trades': len(wins),
        'loss_trades': len(losses),
        'win_rate': round(len(wins) / len(trades) * 100, 2) if trades else 0,
        'total_fees': round(sum(t['fee'] + t['funding_fee'] for t in trades), 2),
        'max_drawdown_pct': round(max_dd, 2),
        'peak_capital': round(peak_cap, 2),
        'symbols_eligible': len(all_data),
        'symbols_traded': len(set(t['symbol'] for t in trades)),
    }
    return trades, summary, equity_curve


def save_results(conn, trades, summary, equity_curve):
    c = conn.cursor()
    c.execute('''INSERT INTO run_summary (start_date, end_date, initial_capital, final_capital, total_pnl, return_pct,
                 total_trades, win_trades, loss_trades, win_rate, total_fees, max_drawdown_pct, peak_capital,
                 total_symbols_eligible, symbols_traded, run_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
              ('2025-01-01', '2025-12-31', summary['initial_capital'], summary['final_capital'],
               summary['total_pnl'], summary['return_pct'],
               summary['total_trades'], summary['win_trades'], summary['loss_trades'], summary['win_rate'],
               summary['total_fees'], summary['max_drawdown_pct'], summary['peak_capital'],
               summary['symbols_eligible'], summary['symbols_traded'],
               datetime.now().isoformat()))

    for t in trades:
        sig = t['entry_signal']
        c.execute('''INSERT INTO trades (trade_id, symbol, direction, entry_price, exit_price,
                     amount, leverage, pnl, roi_pct, fee, funding_fee,
                     entry_time, exit_time, hold_hours, score, reason, peak_roi,
                     stop_loss_price, tp_trigger_price, capital_before, capital_after,
                     entry_rsi, entry_ma7, entry_ma20, entry_ma50,
                     entry_vol_ratio, entry_adx, entry_macd_hist,
                     entry_bb_pct_b, entry_price_position, entry_bonus)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                  (t['trade_id'], t['symbol'], t['direction'], t['entry_price'], t['exit_price'],
                   t['amount'], t['leverage'], t['pnl'], t['roi_pct'], t['fee'], t['funding_fee'],
                   t['entry_time'], t['exit_time'], t['hold_hours'], t['score'], t['reason'], t['peak_roi'],
                   t['stop_loss_price'], t['tp_trigger_price'], t['capital_before'], t['capital_after'],
                   sig.get('rsi'), sig.get('ma7'), sig.get('ma20'), sig.get('ma50'),
                   sig.get('volume_ratio'), sig.get('adx'), sig.get('macd_hist'),
                   sig.get('bb_pct_b'), sig.get('price_position'), sig.get('bonus')))
        trade_db_id = c.lastrowid
        for bar in t['ohlc_path']:
            c.execute('INSERT INTO trade_klines (trade_db_id, time, open, high, low, close) VALUES (?,?,?,?,?,?)',
                      (trade_db_id, bar[0], bar[1], bar[2], bar[3], bar[4]))

    for eq in equity_curve:
        c.execute('INSERT INTO equity_curve (time, capital, positions_count, floating_pnl) VALUES (?,?,?,?)', eq)
    conn.commit()


def main():
    print('Loading klines...')
    all_data = load_all_klines()
    print(f'Loaded {len(all_data)} symbols')

    print('Running multi-symbol backtest...')
    t0 = time.time()
    trades, summary, equity_curve = run_multi_backtest(all_data, CONFIG)
    elapsed = time.time() - t0

    print(f'\n===== 联合回测完成 ({elapsed:.1f}s) =====')
    print(f'初始本金: ${summary["initial_capital"]:,}')
    print(f'最终资金: ${summary["final_capital"]:,.2f}')
    print(f'总 PnL:   ${summary["total_pnl"]:+,.2f}')
    print(f'收益率:   {summary["return_pct"]:+.2f}%')
    print(f'总交易:   {summary["total_trades"]:,}')
    print(f'胜率:     {summary["win_rate"]:.2f}% ({summary["win_trades"]:,}/{summary["total_trades"]:,})')
    print(f'总费用:   ${summary["total_fees"]:,.2f}')
    print(f'最大回撤: {summary["max_drawdown_pct"]:.2f}%')
    print(f'峰值资金: ${summary["peak_capital"]:,.2f}')
    print(f'参与币种: {summary["symbols_traded"]}/{summary["symbols_eligible"]}')

    print('\nSaving to DB...')
    conn = init_db(DB_PATH)
    save_results(conn, trades, summary, equity_curve)
    conn.close()
    print(f'Saved to {DB_PATH}')


if __name__ == '__main__':
    main()
