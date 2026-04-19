#!/usr/bin/env python3
"""Generate per-trade chart PNGs.
Each chart shows: price path during hold + entry + exit + SL line + TP trigger line.
"""
import os
import sqlite3
import matplotlib
matplotlib.use('Agg')  # headless
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
from datetime import datetime

# Chinese font support
plt.rcParams['font.sans-serif'] = ['Noto Sans CJK SC', 'Noto Sans CJK JP', 'Arial Unicode MS', 'DejaVu Sans']
plt.rcParams['axes.unicode_minus'] = False

BASE_DIR = '/opt/backtest_2025'
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025.db')
CHART_DIR = os.path.join(BASE_DIR, 'charts')
os.makedirs(CHART_DIR, exist_ok=True)


def draw_trade(trade, klines, out_path):
    """trade: dict with entry/exit/SL/TP/direction/etc
    klines: list of [time_ms, open, high, low, close]
    """
    if not klines:
        return False

    times = [datetime.fromtimestamp(k[0] / 1000) for k in klines]
    opens = [k[1] for k in klines]
    highs = [k[2] for k in klines]
    lows = [k[3] for k in klines]
    closes = [k[4] for k in klines]

    fig, ax = plt.subplots(figsize=(14, 7))

    # Candlestick style: high/low line + open/close bar
    for i, (t, o, h, l, c) in enumerate(zip(times, opens, highs, lows, closes)):
        color = '#26a69a' if c >= o else '#ef5350'
        ax.plot([t, t], [l, h], color=color, linewidth=0.8, alpha=0.7)
        ax.plot([t, t], [o, c], color=color, linewidth=4, alpha=0.9)

    # Entry marker
    entry_time = datetime.fromtimestamp(trade['entry_time'] / 1000)
    entry_price = trade['entry_price']
    marker = '^' if trade['direction'] == 'LONG' else 'v'
    marker_color = '#4caf50' if trade['direction'] == 'LONG' else '#f44336'
    ax.scatter([entry_time], [entry_price], marker=marker, s=250, c=marker_color,
               edgecolors='white', linewidths=2, zorder=5, label=f"开仓 {trade['direction']} @{entry_price:.4f}")

    # Exit marker
    exit_time = datetime.fromtimestamp(trade['exit_time'] / 1000)
    exit_price = trade['exit_price']
    exit_pnl_color = '#4caf50' if trade['pnl'] > 0 else '#f44336'
    ax.scatter([exit_time], [exit_price], marker='X', s=250, c=exit_pnl_color,
               edgecolors='white', linewidths=2, zorder=5,
               label=f"平仓 @{exit_price:.4f} ({trade['reason'][:20]})")

    # Stop loss line
    ax.axhline(y=trade['stop_loss_price'], color='#f44336', linestyle='--', alpha=0.6,
               label=f"止损 @{trade['stop_loss_price']:.4f} (-10% ROI)")

    # TP trigger line
    ax.axhline(y=trade['tp_trigger_price'], color='#2196f3', linestyle='--', alpha=0.6,
               label=f"移动止盈启动 @{trade['tp_trigger_price']:.4f} (+6% ROI)")

    # Entry price line
    ax.axhline(y=entry_price, color='gray', linestyle=':', alpha=0.4)

    # Title + info
    title = (f"{trade['symbol']} #{trade['trade_id']} {trade['direction']}  "
             f"PnL=${trade['pnl']:+.2f} ROI={trade['roi_pct']:+.2f}% "
             f"保留={trade['hold_hours']}h  评分={trade['score']}")
    ax.set_title(title, fontsize=13, fontweight='bold')

    # Bottom info
    adx_v = trade['entry_adx'] if trade['entry_adx'] is not None else 0.0
    macd_v = trade['entry_macd_hist'] if trade['entry_macd_hist'] is not None else 0.0
    bb_v = trade['entry_bb_pct_b'] if trade['entry_bb_pct_b'] is not None else 0.0
    vol_v = trade['entry_vol_ratio'] if trade['entry_vol_ratio'] is not None else 0.0
    rsi_v = trade['entry_rsi'] if trade['entry_rsi'] is not None else 0.0
    sig_info = (f"RSI={rsi_v:.1f}  ADX={adx_v:.1f}  MACD_hist={macd_v:.4f}  "
                f"BB%B={bb_v:.2f}  Vol_ratio={vol_v:.2f}  "
                f"Peak_ROI={trade['peak_roi']:+.1f}%")
    ax.text(0.5, -0.12, sig_info, transform=ax.transAxes, ha='center',
            fontsize=10, color='gray')

    ax.set_xlabel('时间', fontsize=11)
    ax.set_ylabel('价格 (USDT)', fontsize=11)
    ax.legend(loc='best', fontsize=9)
    ax.grid(True, alpha=0.3)
    ax.xaxis.set_major_formatter(mdates.DateFormatter('%m-%d %H:%M'))
    fig.autofmt_xdate()

    fig.tight_layout()
    fig.savefig(out_path, dpi=80, bbox_inches='tight')
    plt.close(fig)
    return True


def main():
    import sys
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()

    # Optional filter: symbol or trade id list
    where = ''
    args = []
    if len(sys.argv) > 1:
        if sys.argv[1] == '--symbol':
            where = 'WHERE t.symbol = ?'
            args = [sys.argv[2]]
        elif sys.argv[1] == '--limit':
            limit = int(sys.argv[2])
            where = f'ORDER BY ABS(t.pnl) DESC LIMIT {limit}'

    query = f'''SELECT t.id as db_id, t.trade_id, t.symbol, t.direction, t.entry_price, t.exit_price,
                   t.entry_time, t.exit_time, t.hold_hours, t.score, t.reason, t.peak_roi,
                   t.pnl, t.roi_pct, t.stop_loss_price, t.tp_trigger_price,
                   t.entry_rsi, t.entry_adx, t.entry_macd_hist, t.entry_bb_pct_b,
                   t.entry_vol_ratio, t.entry_price_position, t.entry_bonus
            FROM trades t {where}'''
    c.execute(query, args)
    trades = c.fetchall()

    print(f'Generating charts for {len(trades)} trades...')
    ok, fail = 0, 0

    for i, trade in enumerate(trades, 1):
        trade_d = dict(trade)
        # Fetch klines
        c.execute('SELECT time, open, high, low, close FROM trade_klines WHERE trade_db_id=? ORDER BY time',
                  (trade_d['db_id'],))
        klines = [list(row) for row in c.fetchall()]

        out_dir = os.path.join(CHART_DIR, trade_d['symbol'])
        os.makedirs(out_dir, exist_ok=True)
        out_path = os.path.join(out_dir, f"{trade_d['symbol']}_{trade_d['trade_id']:04d}.png")

        try:
            if draw_trade(trade_d, klines, out_path):
                ok += 1
            else:
                fail += 1
        except Exception as e:
            print(f'  [{i}] {trade_d["symbol"]}#{trade_d["trade_id"]}: ERROR {e}')
            fail += 1

        if i % 100 == 0:
            print(f'  Progress: {i}/{len(trades)} ok={ok} fail={fail}')

    conn.close()
    print(f'\nDone: {ok} ok, {fail} failed')
    print(f'Charts at: {CHART_DIR}')


if __name__ == '__main__':
    main()
