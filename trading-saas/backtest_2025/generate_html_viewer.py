#!/usr/bin/env python3
"""Generate static HTML viewer for backtest results.
Outputs:
  - index.html: summary + per-symbol table + filters
  - {SYMBOL}.html: per-symbol trade list with chart thumbnails
"""
import os
import sqlite3
from datetime import datetime

BASE_DIR = '/opt/backtest_2025'
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025.db')
REPORT_DIR = os.path.join(BASE_DIR, 'reports')
os.makedirs(REPORT_DIR, exist_ok=True)


def format_ts(ms):
    return datetime.fromtimestamp(ms / 1000).strftime('%Y-%m-%d %H:%M')


INDEX_TMPL = '''<!DOCTYPE html>
<html lang="zh"><head><meta charset="utf-8">
<title>V6 回测 2025 汇总</title>
<style>
body{font-family:-apple-system,"PingFang SC",Arial;margin:20px;background:#0f1419;color:#e6e6e6;}
h1{color:#4caf50;}
table{border-collapse:collapse;width:100%;margin:15px 0;}
th,td{border:1px solid #333;padding:6px 10px;text-align:right;}
th{background:#1a2332;color:#4fc3f7;}
tr:nth-child(even){background:#121820;}
td:first-child,th:first-child{text-align:left;}
.pos{color:#66bb6a;} .neg{color:#ef5350;}
.badge{display:inline-block;padding:2px 8px;border-radius:3px;background:#2c3e50;margin:0 5px;}
a{color:#4fc3f7;text-decoration:none;} a:hover{text-decoration:underline;}
.summary{background:#1a2332;padding:15px;border-radius:5px;margin:15px 0;}
</style></head>
<body>
<h1>V6 策略 2025 全年回测报告</h1>
<div class="summary">
  <span class="badge">回测区间: 2025-01-01 → 2025-12-31</span>
  <span class="badge">策略: V6 (模拟盘线上)</span>
  <span class="badge">本金: 10000U/币 (单币独立)</span>
  <span class="badge">生成时间: __GEN_TIME__</span>
</div>
<h2>总体</h2>
<table>
<tr><td>币种数</td><td>__SYMBOLS__</td></tr>
<tr><td>总交易</td><td>__TRADES__</td></tr>
<tr><td>胜率</td><td>__WINRATE__%</td></tr>
<tr><td>总 PnL (所有币合计)</td><td class="__PNL_CLS__">$__PNL__</td></tr>
<tr><td>平均单币收益率</td><td class="__RET_CLS__">__AVG_RET__%</td></tr>
<tr><td>最佳币种</td><td>__BEST_SYM__ ($__BEST_PNL__)</td></tr>
<tr><td>最差币种</td><td>__WORST_SYM__ ($__WORST_PNL__)</td></tr>
</table>
<h2>按方向拆分</h2>
__DIR_TABLE__
<h2>按退出原因拆分</h2>
__REASON_TABLE__
<h2>币种明细 (点击查看每币交易)</h2>
<table>
<tr><th>币种</th><th>交易数</th><th>胜率</th><th>总PnL</th><th>收益率</th><th>最佳</th><th>最差</th><th>手续费</th></tr>
__SYMBOL_ROWS__
</table>
</body></html>'''


SYMBOL_TMPL = '''<!DOCTYPE html>
<html lang="zh"><head><meta charset="utf-8">
<title>__SYMBOL__ — V6 回测 2025</title>
<style>
body{font-family:-apple-system,"PingFang SC",Arial;margin:20px;background:#0f1419;color:#e6e6e6;}
h1{color:#4caf50;}
table{border-collapse:collapse;width:100%;margin:15px 0;font-size:13px;}
th,td{border:1px solid #333;padding:4px 8px;text-align:right;}
th{background:#1a2332;color:#4fc3f7;}
tr:nth-child(even){background:#121820;}
.pos{color:#66bb6a;} .neg{color:#ef5350;}
.long{color:#26c6da;} .short{color:#ffa726;}
a{color:#4fc3f7;text-decoration:none;}
.nav{background:#1a2332;padding:10px;border-radius:5px;}
.sum{background:#1a2332;padding:12px;border-radius:5px;margin:15px 0;}
img{max-width:100%;}
</style></head>
<body>
<div class="nav"><a href="index.html">← 返回总览</a></div>
<h1>__SYMBOL__ 交易明细</h1>
<div class="sum">__SUMMARY__</div>
<table>
<tr><th>#</th><th>方向</th><th>开仓时间</th><th>开仓价</th><th>平仓时间</th><th>平仓价</th>
<th>仓位</th><th>持仓h</th><th>评分</th><th>ROI</th><th>PnL</th><th>原因</th><th>图表</th></tr>
__ROWS__
</table>
</body></html>'''


def main():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()

    # Aggregate
    c.execute('''SELECT COUNT(*) as nsym, SUM(total_trades) as nt,
                        SUM(win_trades) as wins, SUM(total_pnl) as pnl,
                        AVG(return_pct) as ret FROM runs''')
    nsym, nt, wins, total_pnl, avg_ret = c.fetchone()

    c.execute('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl DESC LIMIT 1')
    best = c.fetchone()
    c.execute('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl ASC LIMIT 1')
    worst = c.fetchone()

    c.execute('''SELECT direction, COUNT(*), ROUND(SUM(pnl),2), ROUND(AVG(pnl),2), ROUND(AVG(roi_pct),2)
                 FROM trades GROUP BY direction ORDER BY direction''')
    dirs = c.fetchall()

    c.execute('''SELECT SUBSTR(reason,1,10), COUNT(*), ROUND(SUM(pnl),2), ROUND(AVG(pnl),2)
                 FROM trades GROUP BY SUBSTR(reason,1,10) ORDER BY COUNT(*) DESC''')
    reasons = c.fetchall()

    c.execute('''SELECT symbol, total_trades, win_rate, total_pnl, return_pct,
                        best_trade, worst_trade, total_fees
                 FROM runs ORDER BY total_pnl DESC''')
    sym_rows = c.fetchall()

    # Build index.html
    dir_rows = '<table><tr><th>方向</th><th>笔数</th><th>总PnL</th><th>均PnL</th><th>均ROI</th></tr>'
    for d, cnt, sp, ap, ar in dirs:
        cls_sp = 'pos' if sp > 0 else 'neg'
        cls_ap = 'pos' if ap > 0 else 'neg'
        cls_ar = 'pos' if ar > 0 else 'neg'
        dir_rows += f'<tr><td>{d}</td><td>{cnt:,}</td><td class="{cls_sp}">${sp:+,.2f}</td><td class="{cls_ap}">${ap:+.2f}</td><td class="{cls_ar}">{ar:+.2f}%</td></tr>'
    dir_rows += '</table>'

    rsn_rows = '<table><tr><th>原因</th><th>笔数</th><th>总PnL</th><th>均PnL</th></tr>'
    for r, cnt, sp, ap in reasons:
        cls_sp = 'pos' if sp > 0 else 'neg'
        cls_ap = 'pos' if ap > 0 else 'neg'
        rsn_rows += f'<tr><td>{r}</td><td>{cnt:,}</td><td class="{cls_sp}">${sp:+,.2f}</td><td class="{cls_ap}">${ap:+.2f}</td></tr>'
    rsn_rows += '</table>'

    sym_html_rows = ''
    for sym, nt2, wr, pnl, ret, best_t, worst_t, fees in sym_rows:
        cls_p = 'pos' if pnl > 0 else 'neg'
        cls_r = 'pos' if ret > 0 else 'neg'
        sym_html_rows += (f'<tr><td><a href="{sym}.html">{sym}</a></td><td>{nt2:,}</td>'
                         f'<td>{wr:.1f}%</td><td class="{cls_p}">${pnl:+,.2f}</td>'
                         f'<td class="{cls_r}">{ret:+.2f}%</td>'
                         f'<td>${best_t:+.2f}</td><td>${worst_t:+.2f}</td>'
                         f'<td>${fees:,.2f}</td></tr>')

    wr_all = (wins / nt * 100) if nt else 0
    html = INDEX_TMPL.replace('__GEN_TIME__', datetime.now().strftime('%Y-%m-%d %H:%M'))
    html = html.replace('__SYMBOLS__', str(nsym))
    html = html.replace('__TRADES__', f'{nt:,}')
    html = html.replace('__WINRATE__', f'{wr_all:.2f}')
    html = html.replace('__PNL_CLS__', 'pos' if total_pnl > 0 else 'neg')
    html = html.replace('__PNL__', f'{total_pnl:+,.2f}')
    html = html.replace('__RET_CLS__', 'pos' if avg_ret > 0 else 'neg')
    html = html.replace('__AVG_RET__', f'{avg_ret:+.2f}')
    html = html.replace('__BEST_SYM__', best[0] if best else '-')
    html = html.replace('__BEST_PNL__', f'{best[1]:+,.2f}' if best else '-')
    html = html.replace('__WORST_SYM__', worst[0] if worst else '-')
    html = html.replace('__WORST_PNL__', f'{worst[1]:+,.2f}' if worst else '-')
    html = html.replace('__DIR_TABLE__', dir_rows)
    html = html.replace('__REASON_TABLE__', rsn_rows)
    html = html.replace('__SYMBOL_ROWS__', sym_html_rows)

    with open(os.path.join(REPORT_DIR, 'index.html'), 'w') as f:
        f.write(html)
    print('Wrote index.html')

    # Per-symbol pages
    c.execute('SELECT DISTINCT symbol FROM trades ORDER BY symbol')
    symbols = [r[0] for r in c.fetchall()]
    for sym in symbols:
        c.execute('SELECT total_trades, win_rate, total_pnl, return_pct, total_fees FROM runs WHERE symbol=?', (sym,))
        sum_row = c.fetchone()
        if not sum_row:
            continue
        nt, wr, pnl, ret, fees = sum_row
        cls_p = 'pos' if pnl > 0 else 'neg'
        summary = (f'<b>{sym}</b> | 交易 {nt:,} | 胜率 {wr:.1f}% | '
                   f'PnL <span class="{cls_p}">${pnl:+,.2f}</span> | 收益率 {ret:+.2f}% | 费用 ${fees:,.2f}')

        c.execute('''SELECT trade_id, direction, entry_time, entry_price,
                            exit_time, exit_price, amount, hold_hours,
                            score, roi_pct, pnl, reason
                     FROM trades WHERE symbol=? ORDER BY trade_id''', (sym,))
        rows = ''
        for trade_id, d, et, ep, xt, xp, amt, hh, sc, roi, p, rsn in c.fetchall():
            cls_p = 'pos' if p > 0 else 'neg'
            cls_d = 'long' if d == 'LONG' else 'short'
            chart_rel = f'../charts/{sym}/{sym}_{trade_id:04d}.png'
            rows += (f'<tr><td>{trade_id}</td><td class="{cls_d}">{d}</td>'
                    f'<td>{format_ts(et)}</td><td>{ep:.6f}</td>'
                    f'<td>{format_ts(xt)}</td><td>{xp:.6f}</td>'
                    f'<td>${amt:.0f}</td><td>{hh}h</td>'
                    f'<td>{sc}</td>'
                    f'<td class="{cls_p}">{roi:+.2f}%</td>'
                    f'<td class="{cls_p}">${p:+.2f}</td>'
                    f'<td>{rsn[:28]}</td>'
                    f'<td><a href="{chart_rel}" target="_blank">查看</a></td></tr>')

        html = SYMBOL_TMPL.replace('__SYMBOL__', sym).replace('__SUMMARY__', summary).replace('__ROWS__', rows)
        with open(os.path.join(REPORT_DIR, f'{sym}.html'), 'w') as f:
            f.write(html)

    print(f'Wrote {len(symbols)} per-symbol pages')
    conn.close()


if __name__ == '__main__':
    main()
