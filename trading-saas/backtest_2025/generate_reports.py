#!/usr/bin/env python3
"""Generate summary reports from backtest SQLite DB."""
import os
import csv
import sqlite3
from datetime import datetime

BASE_DIR = '/opt/backtest_2025'
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025.db')
REPORT_DIR = os.path.join(BASE_DIR, 'reports')
os.makedirs(REPORT_DIR, exist_ok=True)


def main():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()

    # === per_symbol.csv ===
    c.execute('''SELECT symbol, total_trades, win_trades, loss_trades, win_rate,
                        total_pnl, total_fees, final_capital, return_pct,
                        avg_win, avg_loss, best_trade, worst_trade
                 FROM runs ORDER BY total_pnl DESC''')
    rows = c.fetchall()
    per_sym_path = os.path.join(REPORT_DIR, 'per_symbol.csv')
    with open(per_sym_path, 'w', newline='') as f:
        w = csv.writer(f)
        w.writerow(['symbol', 'total_trades', 'win_trades', 'loss_trades', 'win_rate',
                    'total_pnl', 'total_fees', 'final_capital', 'return_pct',
                    'avg_win', 'avg_loss', 'best_trade', 'worst_trade'])
        w.writerows(rows)
    print(f'Saved per_symbol.csv ({len(rows)} symbols)')

    # === trades.csv ===
    c.execute('''SELECT t.symbol, t.trade_id, t.direction, t.entry_price, t.exit_price,
                        t.amount, t.leverage, t.pnl, t.roi_pct, t.fee, t.funding_fee,
                        t.entry_time, t.exit_time, t.hold_hours, t.score, t.reason,
                        t.peak_roi, t.stop_loss_price, t.tp_trigger_price,
                        t.entry_rsi, t.entry_ma7, t.entry_ma20, t.entry_ma50,
                        t.entry_vol_ratio, t.entry_adx, t.entry_macd_hist,
                        t.entry_bb_pct_b, t.entry_price_position, t.entry_bonus
                 FROM trades t ORDER BY t.symbol, t.trade_id''')
    trades = c.fetchall()
    trades_path = os.path.join(REPORT_DIR, 'trades.csv')
    with open(trades_path, 'w', newline='') as f:
        w = csv.writer(f)
        w.writerow(['symbol', 'trade_id', 'direction', 'entry_price', 'exit_price',
                    'amount', 'leverage', 'pnl', 'roi_pct', 'fee', 'funding_fee',
                    'entry_time_ms', 'exit_time_ms', 'hold_hours', 'score', 'reason',
                    'peak_roi', 'stop_loss_price', 'tp_trigger_price',
                    'entry_rsi', 'entry_ma7', 'entry_ma20', 'entry_ma50',
                    'entry_vol_ratio', 'entry_adx', 'entry_macd_hist',
                    'entry_bb_pct_b', 'entry_price_position', 'entry_bonus'])
        for t in trades:
            row = list(t)
            # Convert entry_time and exit_time to ISO too for readability
            w.writerow(row)
    print(f'Saved trades.csv ({len(trades)} trades)')

    # === summary.md ===
    c.execute('''SELECT COUNT(*) as symbols, SUM(total_trades) as trades,
                        SUM(win_trades) as wins, SUM(loss_trades) as losses,
                        SUM(total_pnl) as pnl, SUM(total_fees) as fees,
                        AVG(return_pct) as avg_ret FROM runs''')
    agg = c.fetchone()

    c.execute('''SELECT direction, COUNT(*) as cnt, ROUND(SUM(pnl),2) as sum_pnl,
                        ROUND(AVG(pnl),2) as avg_pnl, ROUND(AVG(roi_pct),2) as avg_roi
                 FROM trades GROUP BY direction''')
    by_dir = c.fetchall()

    c.execute('''SELECT SUBSTR(reason,1,5) as r, COUNT(*) as cnt,
                        ROUND(SUM(pnl),2) as sum_pnl, ROUND(AVG(pnl),2) as avg_pnl
                 FROM trades GROUP BY SUBSTR(reason,1,5) ORDER BY cnt DESC''')
    by_reason = c.fetchall()

    c.execute('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl DESC LIMIT 10')
    top_win = c.fetchall()
    c.execute('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl ASC LIMIT 10')
    top_lose = c.fetchall()

    lines = [
        '# V6 策略 2025 全年回测报告',
        '',
        f'**生成时间**: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}  ',
        f'**回测区间**: 2025-01-01 → 2025-12-31',
        f'**策略版本**: V6 (当前模拟盘线上策略)',
        '',
        '## 策略参数',
        '',
        '| 参数 | 值 |',
        '|---|---|',
        '| 初始本金 | 10000 USDT (单币独立运行) |',
        '| 杠杆 | 3x 固定 |',
        '| 最大持仓 | 20 (单币回测下最多 20 同时) |',
        '| SHORT 门槛 | ≥70 分 |',
        '| LONG 门槛 | ≥85 分 |',
        '| SHORT 偏好 | 评分 ×1.05 |',
        '| 止损 | ROI -10% |',
        '| 移动止盈 | ROI +6% 启动, 3% 回撤 |',
        '| 趋势过滤 | MA20 斜率 (LONG<-2% 拒绝, SHORT>+1% 拒绝) |',
        '| 最小开仓 | 100 USDT |',
        '| 冷却 | 1 bar (1小时) |',
        '',
        '## 整体汇总',
        '',
        '| 指标 | 数值 |',
        '|---|---|',
        f'| 币种数 | {agg[0]} |',
        f'| 总交易数 | {agg[1]:,} |',
        f'| 盈利笔数 | {agg[2]:,} |',
        f'| 亏损笔数 | {agg[3]:,} |',
        f'| 胜率 | {(agg[2]/agg[1]*100) if agg[1] else 0:.2f}% |',
        f'| 总 PnL (每币独立10000U) | ${agg[4]:+,.2f} |',
        f'| 总手续费 | ${agg[5]:,.2f} |',
        f'| 平均单币收益率 | {agg[6]:+.2f}% |',
        '',
        '## 按方向拆分',
        '',
        '| 方向 | 笔数 | 总 PnL | 平均 PnL | 平均 ROI |',
        '|---|---|---|---|---|',
    ]
    for d, cnt, sp, ap, ar in by_dir:
        lines.append(f'| {d} | {cnt:,} | ${sp:+,.2f} | ${ap:+.2f} | {ar:+.2f}% |')

    lines += [
        '',
        '## 按退出原因拆分',
        '',
        '| 原因 | 笔数 | 总 PnL | 平均 PnL |',
        '|---|---|---|---|',
    ]
    for r, cnt, sp, ap in by_reason:
        lines.append(f'| {r} | {cnt:,} | ${sp:+,.2f} | ${ap:+.2f} |')

    lines += [
        '',
        '## 盈利 Top 10 币种',
        '',
        '| 币种 | 总 PnL |',
        '|---|---|',
    ]
    for sym, pnl in top_win:
        lines.append(f'| {sym} | ${pnl:+,.2f} |')

    lines += [
        '',
        '## 亏损 Top 10 币种',
        '',
        '| 币种 | 总 PnL |',
        '|---|---|',
    ]
    for sym, pnl in top_lose:
        lines.append(f'| {sym} | ${pnl:+,.2f} |')

    lines += [
        '',
        '## 数据文件',
        '',
        '- `per_symbol.csv` — 按币种汇总',
        '- `trades.csv` — 所有交易明细（含开仓信号快照）',
        '- `backtest_2025.db` — 完整 SQLite 数据库（含每笔交易持仓期间 OHLC）',
        '- `charts/{SYMBOL}/*.png` — 每笔交易图表',
        '',
    ]

    summary_path = os.path.join(REPORT_DIR, 'summary.md')
    with open(summary_path, 'w') as f:
        f.write('\n'.join(lines))
    print(f'Saved summary.md')

    conn.close()


if __name__ == '__main__':
    main()
