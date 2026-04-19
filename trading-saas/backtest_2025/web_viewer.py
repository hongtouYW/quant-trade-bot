#!/usr/bin/env python3
"""Flask web viewer for V6 backtest 2025 results.
Serves summary, per-symbol trades, and trade detail + chart images.
Port: 5115
"""
import os
import sqlite3
from datetime import datetime
from flask import Flask, jsonify, render_template_string, send_file, request, abort

BASE_DIR = '/opt/backtest_2025'
DB_PATH = os.path.join(BASE_DIR, 'data', 'backtest_2025.db')
CHART_DIR = os.path.join(BASE_DIR, 'charts')

app = Flask(__name__)


def query(sql, args=(), one=False):
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    c = conn.execute(sql, args)
    rv = [dict(r) for r in c.fetchall()]
    conn.close()
    return (rv[0] if rv else None) if one else rv


INDEX_HTML = '''<!DOCTYPE html>
<html lang="zh"><head><meta charset="utf-8">
<title>V6 回测 2025 汇总</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,"PingFang SC",Arial;margin:0;background:#0f1419;color:#e6e6e6}
header{background:linear-gradient(90deg,#0d1f2d,#1a3a52);padding:20px 30px;border-bottom:2px solid #26a69a}
h1{margin:0;color:#4fc3f7;font-size:24px}
.subtitle{color:#b0bec5;margin-top:5px;font-size:13px}
.container{padding:20px 30px;max-width:1600px;margin:0 auto}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:20px 0}
.card{background:#1a2332;border:1px solid #2c3e50;border-radius:6px;padding:15px}
.card .lbl{color:#90a4ae;font-size:12px;margin-bottom:8px}
.card .val{font-size:22px;font-weight:bold;color:#4fc3f7}
.card .sub{color:#78909c;font-size:11px;margin-top:4px}
.pos{color:#66bb6a!important}.neg{color:#ef5350!important}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
th{background:#1a2332;color:#4fc3f7;padding:10px;text-align:right;border-bottom:2px solid #2c3e50;cursor:pointer}
th:hover{background:#243447}
th:first-child,td:first-child{text-align:left}
td{padding:8px 10px;text-align:right;border-bottom:1px solid #1e2a3a}
tr:hover{background:#162028}
a{color:#4fc3f7;text-decoration:none}a:hover{color:#81d4fa}
.long{color:#26c6da}.short{color:#ffa726}
.filters{display:flex;gap:10px;margin:15px 0;flex-wrap:wrap}
input,select{background:#1a2332;border:1px solid #2c3e50;color:#e6e6e6;padding:6px 10px;border-radius:4px;font-family:inherit}
h2{color:#4fc3f7;border-left:4px solid #26a69a;padding-left:10px;margin-top:30px}
.section{background:#121820;padding:15px;border-radius:6px;margin:15px 0}
.flex{display:flex;gap:20px;align-items:flex-start}
.flex>*{flex:1}
</style></head>
<body>
<header>
<h1>V6 策略 2025 全年回测</h1>
<div class="subtitle">回测区间: 2025-01-01 → 2025-12-31 | 策略: V6 (当前模拟盘) | 本金: 10000U/币 独立</div>
</header>
<div class="container">
  <div class="cards" id="cards"></div>

  <div class="flex">
    <div class="section" style="flex:1">
      <h2 style="margin-top:0">按方向拆分</h2>
      <table id="tbl-dir"><thead><tr><th>方向</th><th>笔数</th><th>总PnL</th><th>均PnL</th><th>均ROI</th></tr></thead><tbody></tbody></table>
    </div>
    <div class="section" style="flex:1">
      <h2 style="margin-top:0">按退出原因</h2>
      <table id="tbl-reason"><thead><tr><th>原因</th><th>笔数</th><th>总PnL</th><th>均PnL</th></tr></thead><tbody></tbody></table>
    </div>
  </div>

  <div class="section">
    <h2 style="margin-top:0">币种明细</h2>
    <div class="filters">
      <input type="text" id="filter" placeholder="搜索币种..." oninput="filterSym()">
      <select id="sortSel" onchange="sortSym()">
        <option value="pnl_desc">按PnL (高→低)</option>
        <option value="pnl_asc">按PnL (低→高)</option>
        <option value="wr_desc">按胜率 (高→低)</option>
        <option value="trades_desc">按交易数 (多→少)</option>
        <option value="symbol">按字母</option>
      </select>
    </div>
    <table id="tbl-sym"><thead><tr>
      <th onclick="sortBy('symbol')">币种</th>
      <th onclick="sortBy('total_trades')">交易</th>
      <th onclick="sortBy('win_rate')">胜率</th>
      <th onclick="sortBy('total_pnl')">总PnL</th>
      <th onclick="sortBy('return_pct')">收益率</th>
      <th onclick="sortBy('best_trade')">最佳</th>
      <th onclick="sortBy('worst_trade')">最差</th>
      <th onclick="sortBy('total_fees')">手续费</th>
    </tr></thead><tbody></tbody></table>
  </div>
</div>

<script>
let symbols = [], currentSort = 'pnl_desc';
const fmt = n => n.toLocaleString('en-US', {maximumFractionDigits:2, minimumFractionDigits:2});
const cls = n => n > 0 ? 'pos' : (n < 0 ? 'neg' : '');

async function load() {
  const r = await fetch('/api/summary');
  const d = await r.json();

  // cards
  document.getElementById('cards').innerHTML = [
    ['币种数', d.total.nsym, ''],
    ['总交易', d.total.nt.toLocaleString(), ''],
    ['胜率', d.total.wr.toFixed(2)+'%', `${d.total.wins.toLocaleString()} 盈 / ${(d.total.nt-d.total.wins).toLocaleString()} 亏`],
    ['总PnL (合计)', '$'+fmt(d.total.pnl), d.total.pnl > 0 ? '+' : '', d.total.pnl],
    ['平均收益率', d.total.avg_ret.toFixed(2)+'%', '单币10000U', d.total.avg_ret],
    ['总手续费', '$'+fmt(d.total.fees), ''],
    ['最佳币种', d.best.symbol, '$'+fmt(d.best.total_pnl), d.best.total_pnl],
    ['最差币种', d.worst.symbol, '$'+fmt(d.worst.total_pnl), d.worst.total_pnl],
  ].map(x => `<div class="card"><div class="lbl">${x[0]}</div><div class="val ${cls(x[3]||0)}">${x[1]}</div><div class="sub">${x[2]}</div></div>`).join('');

  // direction table
  document.querySelector('#tbl-dir tbody').innerHTML = d.dirs.map(x =>
    `<tr><td class="${x.direction==='LONG'?'long':'short'}">${x.direction}</td><td>${x.cnt.toLocaleString()}</td>
     <td class="${cls(x.sum_pnl)}">$${fmt(x.sum_pnl)}</td>
     <td class="${cls(x.avg_pnl)}">$${fmt(x.avg_pnl)}</td>
     <td class="${cls(x.avg_roi)}">${fmt(x.avg_roi)}%</td></tr>`).join('');

  // reason table
  document.querySelector('#tbl-reason tbody').innerHTML = d.reasons.map(x =>
    `<tr><td>${x.reason}</td><td>${x.cnt.toLocaleString()}</td>
     <td class="${cls(x.sum_pnl)}">$${fmt(x.sum_pnl)}</td>
     <td class="${cls(x.avg_pnl)}">$${fmt(x.avg_pnl)}</td></tr>`).join('');

  // symbols
  symbols = d.symbols;
  renderSym();
}

function renderSym() {
  const q = document.getElementById('filter').value.toLowerCase();
  const filtered = symbols.filter(s => s.symbol.toLowerCase().includes(q));
  document.querySelector('#tbl-sym tbody').innerHTML = filtered.map(s =>
    `<tr>
      <td><a href="/symbol/${s.symbol}">${s.symbol}</a></td>
      <td>${s.total_trades.toLocaleString()}</td>
      <td>${s.win_rate.toFixed(1)}%</td>
      <td class="${cls(s.total_pnl)}">$${fmt(s.total_pnl)}</td>
      <td class="${cls(s.return_pct)}">${fmt(s.return_pct)}%</td>
      <td>$${fmt(s.best_trade)}</td>
      <td>$${fmt(s.worst_trade)}</td>
      <td>$${fmt(s.total_fees)}</td>
    </tr>`).join('');
}

function filterSym() { renderSym(); }
function sortSym() {
  const s = document.getElementById('sortSel').value;
  const [key, dir] = s.split('_');
  if (s === 'symbol') symbols.sort((a,b) => a.symbol.localeCompare(b.symbol));
  else if (dir === 'asc') symbols.sort((a,b) => a[s.replace('_asc','').replace('_desc','')] - b[s.replace('_asc','').replace('_desc','')]);
  else symbols.sort((a,b) => b[s.replace('_asc','').replace('_desc','')] - a[s.replace('_asc','').replace('_desc','')]);
  renderSym();
}
function sortBy(k) {
  symbols.sort((a,b) => typeof a[k] === 'string' ? a[k].localeCompare(b[k]) : b[k] - a[k]);
  renderSym();
}

load();
setInterval(load, 30000);  // refresh every 30s while backtest running
</script>
</body></html>'''


SYMBOL_HTML = '''<!DOCTYPE html>
<html lang="zh"><head><meta charset="utf-8">
<title>{{symbol}} — V6 回测</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,"PingFang SC",Arial;margin:0;background:#0f1419;color:#e6e6e6}
header{background:linear-gradient(90deg,#0d1f2d,#1a3a52);padding:15px 30px;border-bottom:2px solid #26a69a}
header a{color:#81d4fa;text-decoration:none;font-size:14px}
h1{margin:5px 0 0 0;color:#4fc3f7;font-size:22px}
.container{padding:20px 30px;max-width:1800px;margin:0 auto}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:15px 0}
.card{background:#1a2332;border:1px solid #2c3e50;border-radius:6px;padding:12px}
.card .lbl{color:#90a4ae;font-size:11px}
.card .val{font-size:20px;font-weight:bold;color:#4fc3f7}
.pos{color:#66bb6a!important}.neg{color:#ef5350!important}
.long{color:#26c6da}.short{color:#ffa726}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:#1a2332;color:#4fc3f7;padding:8px;text-align:right;cursor:pointer;border-bottom:2px solid #2c3e50}
th:hover{background:#243447}
th:first-child,td:first-child{text-align:left}
td{padding:6px 8px;text-align:right;border-bottom:1px solid #1e2a3a}
tr{cursor:pointer}
tr:hover{background:#162028}
.filters{display:flex;gap:10px;margin:10px 0;flex-wrap:wrap}
input,select{background:#1a2332;border:1px solid #2c3e50;color:#e6e6e6;padding:6px 10px;border-radius:4px}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:100;align-items:center;justify-content:center}
.modal.show{display:flex}
.modal-content{background:#121820;border-radius:8px;max-width:95vw;max-height:95vh;overflow:auto;padding:20px;border:1px solid #26a69a}
.close-btn{float:right;font-size:24px;cursor:pointer;color:#ef5350;border:none;background:none}
.sig-table{font-size:11px;background:#0f1419;padding:10px;border-radius:4px;margin-top:10px}
.sig-table td{padding:4px 8px;border:none}
</style></head>
<body>
<header>
<a href="/">← 返回总览</a>
<h1>{{symbol}} 交易明细</h1>
</header>
<div class="container">
  <div class="cards" id="cards"></div>
  <div class="filters">
    <input type="text" id="filter" placeholder="按原因/ID筛选..." oninput="render()">
    <select id="dirSel" onchange="render()">
      <option value="">全部方向</option>
      <option value="LONG">仅 LONG</option>
      <option value="SHORT">仅 SHORT</option>
    </select>
    <select id="pnlSel" onchange="render()">
      <option value="">全部盈亏</option>
      <option value="win">仅盈利</option>
      <option value="loss">仅亏损</option>
    </select>
    <select id="sortSel" onchange="render()">
      <option value="id">按编号</option>
      <option value="pnl_desc">按PnL高→低</option>
      <option value="pnl_asc">按PnL低→高</option>
      <option value="hold_desc">按持仓h→最长</option>
    </select>
  </div>
  <table id="tbl"><thead><tr>
    <th>#</th><th>方向</th><th>开仓时间</th><th>开仓价</th>
    <th>平仓时间</th><th>平仓价</th><th>仓位</th>
    <th>持仓h</th><th>评分</th><th>ROI%</th><th>PnL</th><th>原因</th>
  </tr></thead><tbody></tbody></table>
</div>

<div class="modal" id="modal" onclick="if(event.target===this)closeModal()">
  <div class="modal-content">
    <button class="close-btn" onclick="closeModal()">✕</button>
    <div id="modalBody"></div>
  </div>
</div>

<script>
let trades = [];
const fmt = n => n == null ? '-' : n.toLocaleString('en-US', {maximumFractionDigits:4, minimumFractionDigits:2});
const fmt0 = n => n == null ? '-' : n.toLocaleString('en-US', {maximumFractionDigits:0});
const cls = n => n > 0 ? 'pos' : (n < 0 ? 'neg' : '');
const ts = ms => new Date(ms).toISOString().replace('T',' ').substring(0,16);

async function load() {
  const r = await fetch('/api/symbol/{{symbol}}');
  const d = await r.json();
  trades = d.trades;
  document.getElementById('cards').innerHTML = [
    ['总交易', d.summary.total_trades.toLocaleString(), ''],
    ['胜率', d.summary.win_rate.toFixed(1)+'%', d.summary.win_trades + '/' + d.summary.total_trades],
    ['总PnL', '$'+fmt(d.summary.total_pnl), '', d.summary.total_pnl],
    ['收益率', fmt(d.summary.return_pct)+'%', '', d.summary.return_pct],
    ['最佳交易', '$'+fmt(d.summary.best_trade), ''],
    ['最差交易', '$'+fmt(d.summary.worst_trade), ''],
    ['总手续费', '$'+fmt(d.summary.total_fees), ''],
  ].map(x => `<div class="card"><div class="lbl">${x[0]}</div><div class="val ${cls(x[3]||0)}">${x[1]}</div><div class="sub">${x[2]}</div></div>`).join('');
  render();
}

function render() {
  const q = document.getElementById('filter').value.toLowerCase();
  const ds = document.getElementById('dirSel').value;
  const ps = document.getElementById('pnlSel').value;
  const ss = document.getElementById('sortSel').value;
  let list = trades.filter(t => {
    if (ds && t.direction !== ds) return false;
    if (ps === 'win' && t.pnl <= 0) return false;
    if (ps === 'loss' && t.pnl > 0) return false;
    if (q && !(t.reason.toLowerCase().includes(q) || String(t.trade_id).includes(q))) return false;
    return true;
  });
  if (ss === 'pnl_desc') list.sort((a,b) => b.pnl - a.pnl);
  else if (ss === 'pnl_asc') list.sort((a,b) => a.pnl - b.pnl);
  else if (ss === 'hold_desc') list.sort((a,b) => b.hold_hours - a.hold_hours);
  else list.sort((a,b) => a.trade_id - b.trade_id);
  document.querySelector('#tbl tbody').innerHTML = list.map(t =>
    `<tr onclick="showTrade(${t.trade_id})">
      <td>${t.trade_id}</td>
      <td class="${t.direction==='LONG'?'long':'short'}">${t.direction}</td>
      <td>${ts(t.entry_time)}</td><td>${fmt(t.entry_price)}</td>
      <td>${ts(t.exit_time)}</td><td>${fmt(t.exit_price)}</td>
      <td>$${fmt0(t.amount)}</td>
      <td>${t.hold_hours}h</td>
      <td>${t.score}</td>
      <td class="${cls(t.roi_pct)}">${fmt(t.roi_pct)}%</td>
      <td class="${cls(t.pnl)}">$${fmt(t.pnl)}</td>
      <td>${t.reason.substring(0,30)}</td>
    </tr>`).join('');
}

function showTrade(tid) {
  const t = trades.find(x => x.trade_id === tid);
  if (!t) return;
  const sigRows = [
    ['RSI', t.entry_rsi], ['ADX', t.entry_adx],
    ['MACD hist', t.entry_macd_hist], ['BB %B', t.entry_bb_pct_b],
    ['Vol ratio', t.entry_vol_ratio], ['Price pos', t.entry_price_position],
    ['MA7', t.entry_ma7], ['MA20', t.entry_ma20], ['MA50', t.entry_ma50],
    ['Bonus', t.entry_bonus],
  ].map(([k,v]) => `<tr><td>${k}</td><td>${typeof v === 'number' ? v.toFixed(4) : (v||'-')}</td></tr>`).join('');

  const chartUrl = `/chart/{{symbol}}/${tid}`;
  document.getElementById('modalBody').innerHTML = `
    <h2 style="margin:0;color:#4fc3f7">#${tid} ${t.direction}  <span class="${cls(t.pnl)}">$${fmt(t.pnl)}</span> (${fmt(t.roi_pct)}%)</h2>
    <div style="color:#90a4ae;margin:6px 0 15px">持仓 ${t.hold_hours}h | 评分 ${t.score} | 原因: ${t.reason}</div>
    <img src="${chartUrl}" style="max-width:1400px;max-height:700px;border:1px solid #2c3e50">
    <h3 style="color:#4fc3f7">开仓信号快照</h3>
    <table class="sig-table">
      <tr><td>开仓价</td><td>${fmt(t.entry_price)}</td></tr>
      <tr><td>平仓价</td><td>${fmt(t.exit_price)}</td></tr>
      <tr><td>止损价</td><td>${fmt(t.stop_loss_price)}</td></tr>
      <tr><td>移动止盈启动</td><td>${fmt(t.tp_trigger_price)}</td></tr>
      <tr><td>峰值ROI</td><td>${fmt(t.peak_roi)}%</td></tr>
      <tr><td>仓位/杠杆</td><td>$${fmt0(t.amount)} × ${t.leverage}x</td></tr>
      <tr><td>手续费</td><td>$${fmt(t.fee + (t.funding_fee||0))}</td></tr>
      ${sigRows}
    </table>
  `;
  document.getElementById('modal').classList.add('show');
}

function closeModal() { document.getElementById('modal').classList.remove('show'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

load();
</script>
</body></html>'''


@app.route('/')
def index():
    return INDEX_HTML


@app.route('/symbol/<symbol>')
def symbol_page(symbol):
    return render_template_string(SYMBOL_HTML, symbol=symbol)


@app.route('/api/summary')
def api_summary():
    total = query('''SELECT COUNT(*) as nsym, SUM(total_trades) as nt,
                     SUM(win_trades) as wins, SUM(total_pnl) as pnl,
                     SUM(total_fees) as fees, AVG(return_pct) as avg_ret
                     FROM runs''', one=True)
    if not total or not total['nt']:
        return jsonify({'error': 'no data'})
    total['wr'] = total['wins'] / total['nt'] * 100 if total['nt'] else 0

    best = query('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl DESC LIMIT 1', one=True) or {}
    worst = query('SELECT symbol, total_pnl FROM runs ORDER BY total_pnl ASC LIMIT 1', one=True) or {}
    dirs = query('''SELECT direction, COUNT(*) as cnt, ROUND(SUM(pnl),2) as sum_pnl,
                    ROUND(AVG(pnl),2) as avg_pnl, ROUND(AVG(roi_pct),2) as avg_roi
                    FROM trades GROUP BY direction ORDER BY direction''')
    reasons = query('''SELECT SUBSTR(reason,1,10) as reason, COUNT(*) as cnt,
                       ROUND(SUM(pnl),2) as sum_pnl, ROUND(AVG(pnl),2) as avg_pnl
                       FROM trades GROUP BY SUBSTR(reason,1,10) ORDER BY cnt DESC''')
    symbols = query('''SELECT symbol, total_trades, win_rate, total_pnl, return_pct,
                       total_fees, best_trade, worst_trade
                       FROM runs ORDER BY total_pnl DESC''')
    return jsonify({'total': total, 'best': best, 'worst': worst,
                    'dirs': dirs, 'reasons': reasons, 'symbols': symbols})


@app.route('/api/symbol/<symbol>')
def api_symbol(symbol):
    summary = query('SELECT * FROM runs WHERE symbol=?', (symbol,), one=True)
    if not summary:
        return jsonify({'error': 'not found'}), 404
    trades = query('''SELECT trade_id, direction, entry_price, exit_price, amount, leverage,
                      pnl, roi_pct, fee, funding_fee, entry_time, exit_time, hold_hours,
                      score, reason, peak_roi, stop_loss_price, tp_trigger_price,
                      entry_rsi, entry_ma7, entry_ma20, entry_ma50,
                      entry_vol_ratio, entry_adx, entry_macd_hist,
                      entry_bb_pct_b, entry_price_position, entry_bonus
                      FROM trades WHERE symbol=? ORDER BY trade_id''', (symbol,))
    return jsonify({'summary': summary, 'trades': trades})


@app.route('/chart/<symbol>/<int:trade_id>')
def chart(symbol, trade_id):
    """Serve PNG chart - generate on demand if missing."""
    filename = f'{symbol}_{trade_id:04d}.png'
    path = os.path.join(CHART_DIR, symbol, filename)
    if os.path.exists(path):
        return send_file(path, mimetype='image/png')
    # Generate on demand
    try:
        from generate_charts import draw_trade
        trade = query('''SELECT id as db_id, trade_id, symbol, direction, entry_price, exit_price,
                         entry_time, exit_time, hold_hours, score, reason, peak_roi,
                         pnl, roi_pct, stop_loss_price, tp_trigger_price,
                         entry_rsi, entry_adx, entry_macd_hist, entry_bb_pct_b,
                         entry_vol_ratio, entry_price_position, entry_bonus
                         FROM trades WHERE symbol=? AND trade_id=?''', (symbol, trade_id), one=True)
        if not trade:
            abort(404)
        klines = query('SELECT time, open, high, low, close FROM trade_klines WHERE trade_db_id=? ORDER BY time',
                       (trade['db_id'],))
        klines = [[k['time'], k['open'], k['high'], k['low'], k['close']] for k in klines]
        os.makedirs(os.path.dirname(path), exist_ok=True)
        if draw_trade(trade, klines, path):
            return send_file(path, mimetype='image/png')
    except Exception as e:
        print(f'chart gen err: {e}')
    abort(404)


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5115, debug=False)
