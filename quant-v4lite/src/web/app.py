"""
V4-Lite 监控面板
运行在 port 5210
"""
import os
import sys
import json
import csv
import io
from datetime import datetime, timedelta
from pathlib import Path

import psycopg2
import psycopg2.extras
from flask import Flask, render_template_string, jsonify, request, Response

app = Flask(__name__)

# 项目根目录
BASE_DIR = Path(__file__).parent.parent.parent
LOG_FILE = BASE_DIR / "logs" / "trading.log"
STATUS_FILE = BASE_DIR / "data" / "status.json"

# DB 连接参数
DB_CONFIG = {
    'host': os.environ.get('DB_HOST', 'localhost'),
    'port': int(os.environ.get('DB_PORT', 5432)),
    'dbname': os.environ.get('DB_NAME', 'quant_trading'),
    'user': os.environ.get('DB_USER', 'quant'),
    'password': os.environ.get('DB_PASSWORD', 'quant2026'),
}


def get_db():
    try:
        return psycopg2.connect(**DB_CONFIG)
    except Exception:
        return None


# ── 共享 CSS ──

SHARED_CSS = """
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, 'Segoe UI', 'PingFang SC', monospace; background: #0d1117; color: #c9d1d9; padding: 20px; }
h1 { color: #58a6ff; margin-bottom: 20px; font-size: 24px; }
.nav { margin-bottom: 20px; }
.nav a { color: #58a6ff; text-decoration: none; margin-right: 20px; font-size: 14px; }
.nav a:hover { text-decoration: underline; }
.nav a.active { color: #c9d1d9; font-weight: 600; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px; }
.card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; }
.card h2 { color: #8b949e; font-size: 14px; margin-bottom: 12px; }
.metric { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #21262d; }
.metric:last-child { border-bottom: none; }
.label { color: #8b949e; }
.value { font-weight: 600; }
.positive { color: #3fb950; }
.negative { color: #f85149; }
.neutral { color: #d29922; }
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
.status-running { background: #0d3117; color: #3fb950; }
.status-stopped { background: #3d1f1f; color: #f85149; }
.log-box { background: #0d1117; border: 1px solid #30363d; border-radius: 8px; padding: 16px; max-height: 400px; overflow-y: auto; font-size: 13px; line-height: 1.6; }
.log-line { white-space: pre-wrap; word-break: break-all; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; color: #8b949e; font-size: 12px; padding: 8px; border-bottom: 1px solid #30363d; }
td { padding: 8px; border-bottom: 1px solid #21262d; font-size: 13px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-long { background: #0d3117; color: #3fb950; }
.badge-short { background: #3d1f1f; color: #f85149; }
.badge-sl { background: #3d1f1f; color: #f85149; }
.badge-tp { background: #0d3117; color: #3fb950; }
.badge-time { background: #2d2200; color: #d29922; }
.badge-trail { background: #0d2d3d; color: #58a6ff; }
"""

NAV_TEMPLATE = """
<div class="nav">
    <a href="/" class="{{ 'active' if page == 'monitor' else '' }}">实时监控</a>
    <a href="/trades" class="{{ 'active' if page == 'trades' else '' }}">交易历史</a>
    <a href="/daily" class="{{ 'active' if page == 'daily' else '' }}">日统计</a>
    <a href="/signals" class="{{ 'active' if page == 'signals' else '' }}">信号日志</a>
</div>
"""

# ── 首页模板 ──

HTML_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>量化交易 V4-Lite 监控</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <style>""" + SHARED_CSS + """
    </style>
</head>
<body>
    <h1>量化交易 V4-Lite <span class="status-badge {{ 'status-running' if status.running else 'status-stopped' }}">{{ '运行中' if status.running else '已停止' }}</span></h1>

    """ + NAV_TEMPLATE + """

    <div class="grid">
        <div class="card">
            <h2>账户信息</h2>
            <div class="metric"><span class="label">当前余额</span><span class="value">{{ '%.2f'|format(status.balance) }} USDT</span></div>
            <div class="metric"><span class="label">初始本金</span><span class="value">{{ '%.2f'|format(status.initial_balance) }} USDT</span></div>
            <div class="metric"><span class="label">总盈亏</span><span class="value {{ 'positive' if status.total_pnl > 0 else 'negative' }}">{{ '%+.2f'|format(status.total_pnl) }} USDT</span></div>
            <div class="metric"><span class="label">杠杆</span><span class="value">{{ status.leverage }}x 逐仓</span></div>
        </div>

        <div class="card">
            <h2>今日统计</h2>
            <div class="metric"><span class="label">今日盈亏</span><span class="value {{ 'positive' if status.daily_pnl > 0 else 'negative' }}">{{ '%+.2f'|format(status.daily_pnl) }} USDT</span></div>
            <div class="metric"><span class="label">交易次数</span><span class="value">{{ status.daily_trades }} 笔</span></div>
            <div class="metric"><span class="label">胜率</span><span class="value">{{ '%.0f'|format(status.win_rate * 100) }}%</span></div>
            <div class="metric"><span class="label">当前时段</span><span class="value neutral">{{ status.session }}</span></div>
        </div>

        <div class="card">
            <h2>风控状态</h2>
            <div class="metric"><span class="label">持仓数量</span><span class="value">{{ status.open_positions }} / {{ status.max_positions }}</span></div>
            <div class="metric"><span class="label">连续亏损</span><span class="value {{ 'negative' if status.consec_losses >= 3 else '' }}">{{ status.consec_losses }} 笔</span></div>
            <div class="metric"><span class="label">仓位缩放</span><span class="value">{{ '%.0f'|format(status.risk_scale * 100) }}%</span></div>
            <div class="metric"><span class="label">总保证金</span><span class="value">{{ '%.2f'|format(status.total_margin) }} USDT</span></div>
        </div>
    </div>

    {% if status.positions %}
    <div class="card" style="margin-bottom: 20px;">
        <h2>当前持仓</h2>
        <table>
            <tr><th>交易对</th><th>方向</th><th>策略</th><th>入场价</th><th>浮动盈亏</th><th>R值</th><th>持仓时间</th></tr>
            {% for p in status.positions %}
            <tr>
                <td>{{ p.symbol }}</td>
                <td class="{{ 'positive' if p.direction == 'LONG' else 'negative' }}">{{ '做多' if p.direction == 'LONG' else '做空' }}</td>
                <td>{{ p.strategy }}</td>
                <td>{{ p.entry }}</td>
                <td class="{{ 'positive' if p.pnl > 0 else 'negative' }}">{{ '%+.2f'|format(p.pnl) }}U</td>
                <td>{{ '%.1f'|format(p.r_value) }}R</td>
                <td>{{ p.hold_min }}分钟</td>
            </tr>
            {% endfor %}
        </table>
    </div>
    {% endif %}

    <div class="card">
        <h2>最近日志</h2>
        <div class="log-box">
            {% for line in logs %}
            <div class="log-line">{{ line }}</div>
            {% endfor %}
        </div>
    </div>

    <p style="margin-top: 16px; color: #484f58; font-size: 12px;">
        更新时间: {{ now }} | 自动刷新: 30秒
    </p>
</body>
</html>
"""

# ── 交易历史页面模板 ──

TRADES_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>交易历史 - V4-Lite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <style>""" + SHARED_CSS + """
        .card .big { font-size: 28px; font-weight: 700; }
        .card .sub { color: #8b949e; font-size: 12px; margin-top: 4px; }
        .filters { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filters label { color: #8b949e; font-size: 12px; }
        .filters select, .filters input { background: #0d1117; color: #c9d1d9; border: 1px solid #30363d; border-radius: 4px; padding: 6px 10px; font-size: 13px; }
        .filters button { background: #238636; color: #fff; border: none; border-radius: 4px; padding: 6px 16px; cursor: pointer; font-size: 13px; }
        .filters button:hover { background: #2ea043; }
        .btn-csv { background: #1f6feb; color: #fff; border: none; border-radius: 4px; padding: 6px 16px; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-csv:hover { background: #388bfd; }
        tr:hover { background: #1c2128; }
        .empty { text-align: center; padding: 40px; color: #484f58; }
        .strategy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .strat-card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 12px; }
        .strat-card .name { color: #58a6ff; font-size: 13px; font-weight: 600; margin-bottom: 8px; }
        .strat-card .row { display: flex; justify-content: space-between; font-size: 12px; padding: 2px 0; }
        .strat-card .row .label { color: #8b949e; }
        .chart-box { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .chart-box h2 { color: #8b949e; font-size: 14px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>交易历史</h1>

    """ + NAV_TEMPLATE + """

    <!-- 总览统计 -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="card">
            <h2>总交易</h2>
            <div class="big">{{ summary.total }}</div>
            <div class="sub">胜率 {{ '%.0f'|format(summary.win_rate) }}% ({{ summary.wins }}W / {{ summary.losses }}L)</div>
        </div>
        <div class="card">
            <h2>净盈亏</h2>
            <div class="big {{ 'positive' if summary.net_pnl > 0 else 'negative' }}">{{ '%+.2f'|format(summary.net_pnl) }}U</div>
            <div class="sub">毛利 {{ '%+.2f'|format(summary.gross_pnl) }}U | 手续费 {{ '%.2f'|format(summary.total_fee) }}U</div>
        </div>
        <div class="card">
            <h2>平均盈亏</h2>
            <div class="big {{ 'positive' if summary.avg_pnl > 0 else 'negative' }}">{{ '%+.2f'|format(summary.avg_pnl) }}U</div>
            <div class="sub">盈利均值 {{ '%+.2f'|format(summary.avg_win) }}U | 亏损均值 {{ '%+.2f'|format(summary.avg_loss) }}U</div>
        </div>
        <div class="card">
            <h2>盈亏比</h2>
            <div class="big neutral">{{ '%.2f'|format(summary.profit_factor) }}</div>
            <div class="sub">资金费 {{ '%+.4f'|format(summary.total_funding) }}U</div>
        </div>
    </div>

    <!-- 权益曲线 -->
    {% if trades %}
    <div class="chart-box">
        <h2>权益曲线 (累计 PnL)</h2>
        <canvas id="equityChart" height="200"></canvas>
    </div>
    {% endif %}

    <!-- 策略分析 -->
    {% if strategies %}
    <div class="strategy-grid">
        {% for s in strategies %}
        <div class="strat-card">
            <div class="name">{{ s.strategy }}</div>
            <div class="row"><span class="label">交易</span><span>{{ s.count }} 笔</span></div>
            <div class="row"><span class="label">胜率</span><span>{{ '%.0f'|format(s.win_rate) }}%</span></div>
            <div class="row"><span class="label">盈亏</span><span class="{{ 'positive' if s.pnl > 0 else 'negative' }}">{{ '%+.2f'|format(s.pnl) }}U</span></div>
            <div class="row"><span class="label">均值</span><span class="{{ 'positive' if s.avg > 0 else 'negative' }}">{{ '%+.2f'|format(s.avg) }}U</span></div>
        </div>
        {% endfor %}
    </div>
    {% endif %}

    <!-- 平仓原因分布 -->
    <div class="grid" style="margin-bottom: 20px; grid-template-columns: 1fr;">
        <div class="card">
            <h2>平仓原因分布</h2>
            {% for r in reasons %}
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                <span>{{ r.reason }}</span>
                <span>{{ r.count }} 笔 ({{ '%+.2f'|format(r.pnl) }}U)</span>
            </div>
            {% endfor %}
        </div>
    </div>

    <!-- 筛选 -->
    <form class="filters" method="get" action="/trades">
        <div>
            <label>策略</label><br>
            <select name="strategy">
                <option value="">全部</option>
                {% for s in all_strategies %}
                <option value="{{ s }}" {{ 'selected' if s == filter_strategy else '' }}>{{ s }}</option>
                {% endfor %}
            </select>
        </div>
        <div>
            <label>方向</label><br>
            <select name="direction">
                <option value="">全部</option>
                <option value="LONG" {{ 'selected' if filter_direction == 'LONG' else '' }}>LONG</option>
                <option value="SHORT" {{ 'selected' if filter_direction == 'SHORT' else '' }}>SHORT</option>
            </select>
        </div>
        <div>
            <label>平仓原因</label><br>
            <select name="close_reason">
                <option value="">全部</option>
                {% for r in all_reasons %}
                <option value="{{ r }}" {{ 'selected' if r == filter_reason else '' }}>{{ r }}</option>
                {% endfor %}
            </select>
        </div>
        <div>
            <label>交易对</label><br>
            <input type="text" name="symbol" value="{{ filter_symbol }}" placeholder="如 BTC/USDT">
        </div>
        <div style="align-self: flex-end;">
            <button type="submit">筛选</button>
            <a class="btn-csv" href="/trades/csv?strategy={{ filter_strategy }}&direction={{ filter_direction }}&close_reason={{ filter_reason }}&symbol={{ filter_symbol }}">导出CSV</a>
        </div>
    </form>

    <!-- 交易列表 -->
    {% if trades %}
    <div style="overflow-x: auto;">
    <table>
        <tr>
            <th>时间</th>
            <th>交易对</th>
            <th>方向</th>
            <th>策略</th>
            <th>入场价</th>
            <th>出场价</th>
            <th>数量</th>
            <th>保证金</th>
            <th>PnL</th>
            <th>手续费</th>
            <th>资金费</th>
            <th>净PnL</th>
            <th>持仓时间</th>
            <th>平仓原因</th>
        </tr>
        {% for t in trades %}
        <tr>
            <td style="white-space:nowrap;">{{ t.close_time.strftime('%m-%d %H:%M') if t.close_time else '-' }}</td>
            <td>{{ t.symbol }}</td>
            <td><span class="badge {{ 'badge-long' if t.direction == 'LONG' else 'badge-short' }}">{{ t.direction }}</span></td>
            <td>{{ t.strategy }}</td>
            <td>{{ '%.4f'|format(t.entry_price) if t.entry_price else '-' }}</td>
            <td>{{ '%.4f'|format(t.exit_price) if t.exit_price else '-' }}</td>
            <td>{{ '%.4f'|format(t.quantity) if t.quantity else '-' }}</td>
            <td>{{ '%.1f'|format(t.margin) if t.margin else '-' }}U</td>
            <td class="{{ 'positive' if (t.pnl or 0) > 0 else 'negative' }}">{{ '%+.2f'|format(t.pnl or 0) }}U</td>
            <td>{{ '%.4f'|format(t.fee or 0) }}U</td>
            <td>{{ '%+.4f'|format(t.funding_fee or 0) }}U</td>
            <td class="{{ 'positive' if ((t.pnl or 0) - (t.fee or 0) - (t.funding_fee or 0)) > 0 else 'negative' }}">{{ '%+.2f'|format((t.pnl or 0) - (t.fee or 0) - (t.funding_fee or 0)) }}U</td>
            <td>{{ t.hold_min }}分</td>
            <td><span class="badge {{ 'badge-sl' if t.close_reason == 'stop_loss' else ('badge-tp' if t.close_reason in ('tp1','tp2') else ('badge-trail' if t.close_reason == 'trailing' else 'badge-time')) }}">{{ t.close_reason }}</span></td>
        </tr>
        {% endfor %}
    </table>
    </div>
    {% else %}
    <div class="empty">暂无交易记录</div>
    {% endif %}

    <p style="margin-top: 16px; color: #484f58; font-size: 12px;">
        共 {{ trades|length }} 笔交易 | 更新时间: {{ now }} | 自动刷新: 60秒
    </p>

    <!-- 权益曲线 JS -->
    {% if equity_data %}
    <script>
    (function(){
        var canvas = document.getElementById('equityChart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var W = canvas.width = canvas.parentElement.clientWidth - 32;
        var H = canvas.height = 200;
        var data = {{ equity_data|tojson }};
        if (data.length < 2) return;

        var values = data.map(function(d){return d.cum_pnl;});
        var minV = Math.min.apply(null, values);
        var maxV = Math.max.apply(null, values);
        var range = maxV - minV || 1;
        var padY = 20;

        function x(i){ return (i / (data.length - 1)) * (W - 60) + 40; }
        function y(v){ return H - padY - ((v - minV) / range) * (H - padY * 2); }

        // 零线
        ctx.strokeStyle = '#30363d';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(40, y(0));
        ctx.lineTo(W, y(0));
        ctx.stroke();
        ctx.setLineDash([]);

        // 曲线
        ctx.strokeStyle = values[values.length-1] >= 0 ? '#3fb950' : '#f85149';
        ctx.lineWidth = 2;
        ctx.beginPath();
        for(var i = 0; i < data.length; i++){
            if(i === 0) ctx.moveTo(x(i), y(values[i]));
            else ctx.lineTo(x(i), y(values[i]));
        }
        ctx.stroke();

        // 填充
        ctx.globalAlpha = 0.1;
        ctx.fillStyle = values[values.length-1] >= 0 ? '#3fb950' : '#f85149';
        ctx.lineTo(x(data.length-1), y(0));
        ctx.lineTo(x(0), y(0));
        ctx.closePath();
        ctx.fill();
        ctx.globalAlpha = 1;

        // 圆点
        for(var i = 0; i < data.length; i++){
            ctx.fillStyle = values[i] >= 0 ? '#3fb950' : '#f85149';
            ctx.beginPath();
            ctx.arc(x(i), y(values[i]), 3, 0, Math.PI*2);
            ctx.fill();
        }

        // Y轴标签
        ctx.fillStyle = '#8b949e';
        ctx.font = '11px monospace';
        ctx.textAlign = 'right';
        ctx.fillText(maxV.toFixed(1) + 'U', 36, y(maxV) + 4);
        ctx.fillText(minV.toFixed(1) + 'U', 36, y(minV) + 4);
        if(minV < 0 && maxV > 0) ctx.fillText('0', 36, y(0) + 4);

        // X轴标签 (首尾日期)
        ctx.textAlign = 'center';
        ctx.fillText(data[0].date, x(0), H - 4);
        ctx.fillText(data[data.length-1].date, x(data.length-1), H - 4);
    })();
    </script>
    {% endif %}
</body>
</html>
"""

# ── 日统计页面 ──

DAILY_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>日统计 - V4-Lite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="300">
    <style>""" + SHARED_CSS + """
        .card .big { font-size: 28px; font-weight: 700; }
        .card .sub { color: #8b949e; font-size: 12px; margin-top: 4px; }
        tr:hover { background: #1c2128; }
        .chart-box { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .chart-box h2 { color: #8b949e; font-size: 14px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <h1>日统计</h1>

    """ + NAV_TEMPLATE + """

    <!-- 日盈亏柱状图 -->
    {% if daily_data %}
    <div class="chart-box">
        <h2>每日盈亏</h2>
        <canvas id="dailyChart" height="200"></canvas>
    </div>
    {% endif %}

    <!-- 统计表 -->
    {% if daily_data %}
    <div style="overflow-x: auto;">
    <table>
        <tr><th>日期</th><th>交易笔数</th><th>胜/负</th><th>胜率</th><th>毛利PnL</th><th>手续费</th><th>净PnL</th><th>余额</th></tr>
        {% for d in daily_data %}
        <tr>
            <td>{{ d.date }}</td>
            <td>{{ d.count }}</td>
            <td>{{ d.wins }}W / {{ d.losses }}L</td>
            <td>{{ '%.0f'|format(d.win_rate) }}%</td>
            <td class="{{ 'positive' if d.pnl > 0 else 'negative' }}">{{ '%+.2f'|format(d.pnl) }}U</td>
            <td>{{ '%.2f'|format(d.fee) }}U</td>
            <td class="{{ 'positive' if d.net > 0 else 'negative' }}">{{ '%+.2f'|format(d.net) }}U</td>
            <td>{{ '%.2f'|format(d.balance) }}U</td>
        </tr>
        {% endfor %}
    </table>
    </div>
    {% else %}
    <div style="text-align:center;padding:40px;color:#484f58;">暂无日统计数据</div>
    {% endif %}

    <p style="margin-top: 16px; color: #484f58; font-size: 12px;">更新时间: {{ now }}</p>

    {% if daily_data %}
    <script>
    (function(){
        var canvas = document.getElementById('dailyChart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var W = canvas.width = canvas.parentElement.clientWidth - 32;
        var H = canvas.height = 200;
        var data = {{ daily_chart|tojson }};
        if (!data.length) return;

        var values = data.map(function(d){return d.net;});
        var maxV = Math.max.apply(null, values.map(Math.abs)) || 1;
        var padY = 20, padX = 50;
        var barW = Math.min(40, (W - padX * 2) / data.length * 0.7);
        var gap = (W - padX * 2) / data.length;

        // 零线
        var zeroY = H / 2;
        ctx.strokeStyle = '#30363d';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(padX, zeroY);
        ctx.lineTo(W - 10, zeroY);
        ctx.stroke();

        for(var i = 0; i < data.length; i++){
            var val = values[i];
            var barH = (Math.abs(val) / maxV) * (H/2 - padY);
            var bx = padX + i * gap + (gap - barW) / 2;
            var by = val >= 0 ? zeroY - barH : zeroY;

            ctx.fillStyle = val >= 0 ? '#3fb950' : '#f85149';
            ctx.fillRect(bx, by, barW, barH);

            // 值标签
            ctx.fillStyle = '#8b949e';
            ctx.font = '10px monospace';
            ctx.textAlign = 'center';
            ctx.fillText(val.toFixed(1), bx + barW/2, val >= 0 ? by - 4 : by + barH + 12);

            // 日期标签
            ctx.fillText(data[i].date.slice(5), bx + barW/2, H - 4);
        }
    })();
    </script>
    {% endif %}
</body>
</html>
"""

# ── 信号日志页面 ──

SIGNALS_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>信号日志 - V4-Lite</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <style>""" + SHARED_CSS + """
        tr:hover { background: #1c2128; }
        .badge-pass { background: #0d3117; color: #3fb950; }
        .badge-reject { background: #2d2200; color: #d29922; }
        .badge-near { background: #1c1d3d; color: #a371f7; }
        .filters { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 16px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .filters label { color: #8b949e; font-size: 12px; }
        .filters select { background: #0d1117; color: #c9d1d9; border: 1px solid #30363d; border-radius: 4px; padding: 6px 10px; font-size: 13px; }
        .filters button { background: #238636; color: #fff; border: none; border-radius: 4px; padding: 6px 16px; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body>
    <h1>信号日志</h1>

    """ + NAV_TEMPLATE + """

    <!-- 筛选 -->
    <form class="filters" method="get" action="/signals">
        <div>
            <label>策略</label><br>
            <select name="strategy">
                <option value="">全部</option>
                {% for s in all_strategies %}
                <option value="{{ s }}" {{ 'selected' if s == filter_strategy else '' }}>{{ s }}</option>
                {% endfor %}
            </select>
        </div>
        <div>
            <label>状态</label><br>
            <select name="status">
                <option value="">全部</option>
                <option value="triggered" {{ 'selected' if filter_status == 'triggered' else '' }}>已触发</option>
                <option value="near_miss" {{ 'selected' if filter_status == 'near_miss' else '' }}>差一点</option>
                <option value="rejected" {{ 'selected' if filter_status == 'rejected' else '' }}>被拒绝</option>
            </select>
        </div>
        <div style="align-self: flex-end;">
            <button type="submit">筛选</button>
        </div>
    </form>

    {% if signals %}
    <div style="overflow-x: auto;">
    <table>
        <tr>
            <th>时间</th>
            <th>交易对</th>
            <th>方向</th>
            <th>策略</th>
            <th>状态</th>
            <th>拒绝原因</th>
            <th>关键指标</th>
        </tr>
        {% for s in signals %}
        <tr>
            <td style="white-space:nowrap;">{{ s.ts.strftime('%m-%d %H:%M') if s.ts else '-' }}</td>
            <td>{{ s.symbol }}</td>
            <td><span class="badge {{ 'badge-long' if s.direction == 'LONG' else 'badge-short' }}">{{ s.direction }}</span></td>
            <td>{{ s.strategy }}</td>
            <td><span class="badge {{ 'badge-pass' if s.status == 'triggered' else ('badge-near' if s.status == 'near_miss' else 'badge-reject') }}">{{ s.status }}</span></td>
            <td style="max-width:200px;word-break:break-all;">{{ s.reason or '-' }}</td>
            <td style="max-width:250px;word-break:break-all;">{{ s.details or '-' }}</td>
        </tr>
        {% endfor %}
    </table>
    </div>
    {% else %}
    <div style="text-align:center;padding:40px;color:#484f58;">暂无信号日志</div>
    {% endif %}

    <p style="margin-top: 16px; color: #484f58; font-size: 12px;">
        共 {{ signals|length }} 条 | 更新时间: {{ now }} | 自动刷新: 60秒
    </p>
</body>
</html>
"""


# ── 数据加载函数 ──

def load_status():
    default = {
        'running': False, 'balance': 2000, 'initial_balance': 2000,
        'total_pnl': 0, 'daily_pnl': 0, 'daily_trades': 0,
        'win_rate': 0, 'leverage': 10, 'session': 'N/A',
        'open_positions': 0, 'max_positions': 4,
        'consec_losses': 0, 'risk_scale': 1.0,
        'total_margin': 0, 'positions': [],
    }
    if STATUS_FILE.exists():
        try:
            with open(STATUS_FILE, 'r') as f:
                data = json.load(f)
            default.update(data)
        except Exception:
            pass

    class Status:
        pass
    s = Status()
    for k, v in default.items():
        setattr(s, k, v)
    return s


def load_logs(n=50):
    if not LOG_FILE.exists():
        return ["暂无日志"]
    try:
        with open(LOG_FILE, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
        return [l.rstrip() for l in lines[-n:]]
    except Exception as e:
        return [f"日志读取失败: {e}"]


def load_trades(strategy=None, direction=None, close_reason=None, symbol=None):
    conn = get_db()
    if not conn:
        return []
    try:
        cur = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        sql = 'SELECT * FROM trades WHERE 1=1'
        params = []
        if strategy:
            sql += ' AND strategy = %s'
            params.append(strategy)
        if direction:
            sql += ' AND direction = %s'
            params.append(direction)
        if close_reason:
            sql += ' AND close_reason = %s'
            params.append(close_reason)
        if symbol:
            sql += ' AND symbol ILIKE %s'
            params.append(f'%{symbol}%')
        sql += ' ORDER BY close_time DESC LIMIT 500'
        cur.execute(sql, params)
        rows = cur.fetchall()
        for r in rows:
            if r.get('open_time') and r.get('close_time'):
                delta = r['close_time'] - r['open_time']
                r['hold_min'] = int(delta.total_seconds() / 60)
            else:
                r['hold_min'] = 0
        return rows
    except Exception:
        return []
    finally:
        conn.close()


def load_signals(strategy=None, status=None, limit=200):
    conn = get_db()
    if not conn:
        return []
    try:
        cur = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        sql = 'SELECT * FROM signal_log WHERE 1=1'
        params = []
        if strategy:
            sql += ' AND strategy = %s'
            params.append(strategy)
        if status:
            sql += ' AND status = %s'
            params.append(status)
        sql += ' ORDER BY ts DESC LIMIT %s'
        params.append(limit)
        cur.execute(sql, params)
        return cur.fetchall()
    except Exception:
        return []
    finally:
        conn.close()


def calc_summary(trades):
    total = len(trades)
    if total == 0:
        return {
            'total': 0, 'wins': 0, 'losses': 0, 'win_rate': 0,
            'gross_pnl': 0, 'net_pnl': 0, 'total_fee': 0,
            'total_funding': 0, 'avg_pnl': 0, 'avg_win': 0,
            'avg_loss': 0, 'profit_factor': 0,
        }
    wins = [t for t in trades if (t.get('pnl') or 0) > 0]
    losses = [t for t in trades if (t.get('pnl') or 0) <= 0]
    gross_pnl = sum(t.get('pnl') or 0 for t in trades)
    total_fee = sum(t.get('fee') or 0 for t in trades)
    total_funding = sum(t.get('funding_fee') or 0 for t in trades)
    net_pnl = gross_pnl - total_fee - total_funding
    total_win = sum(t.get('pnl') or 0 for t in wins) if wins else 0
    total_loss = abs(sum(t.get('pnl') or 0 for t in losses)) if losses else 0

    return {
        'total': total, 'wins': len(wins), 'losses': len(losses),
        'win_rate': len(wins) / total * 100 if total else 0,
        'gross_pnl': gross_pnl, 'net_pnl': net_pnl,
        'total_fee': total_fee, 'total_funding': total_funding,
        'avg_pnl': gross_pnl / total if total else 0,
        'avg_win': total_win / len(wins) if wins else 0,
        'avg_loss': -(total_loss / len(losses)) if losses else 0,
        'profit_factor': total_win / total_loss if total_loss > 0 else 999,
    }


def calc_strategy_stats(trades):
    groups = {}
    for t in trades:
        s = t.get('strategy', 'unknown')
        if s not in groups:
            groups[s] = {'strategy': s, 'count': 0, 'wins': 0, 'pnl': 0}
        groups[s]['count'] += 1
        pnl = t.get('pnl') or 0
        groups[s]['pnl'] += pnl
        if pnl > 0:
            groups[s]['wins'] += 1
    result = []
    for g in groups.values():
        g['win_rate'] = g['wins'] / g['count'] * 100 if g['count'] else 0
        g['avg'] = g['pnl'] / g['count'] if g['count'] else 0
        result.append(g)
    return sorted(result, key=lambda x: x['count'], reverse=True)


def calc_reason_stats(trades):
    groups = {}
    for t in trades:
        r = t.get('close_reason', 'unknown')
        if r not in groups:
            groups[r] = {'reason': r, 'count': 0, 'pnl': 0}
        groups[r]['count'] += 1
        groups[r]['pnl'] += t.get('pnl') or 0
    return sorted(groups.values(), key=lambda x: x['count'], reverse=True)


def calc_equity_curve(trades):
    """按时间排序计算累计 PnL"""
    sorted_trades = sorted(trades, key=lambda t: t.get('close_time') or datetime.min)
    cum_pnl = 0
    data = []
    for t in sorted_trades:
        pnl = (t.get('pnl') or 0) - (t.get('fee') or 0) - (t.get('funding_fee') or 0)
        cum_pnl += pnl
        ct = t.get('close_time')
        data.append({
            'date': ct.strftime('%m-%d %H:%M') if ct else '',
            'cum_pnl': round(cum_pnl, 2),
            'pnl': round(pnl, 2),
        })
    return data


def calc_daily_stats(trades):
    """按天汇总"""
    days = {}
    sorted_trades = sorted(trades, key=lambda t: t.get('close_time') or datetime.min)
    cum_pnl = 0
    initial_balance = 2000

    for t in sorted_trades:
        ct = t.get('close_time')
        if not ct:
            continue
        day = ct.strftime('%Y-%m-%d')
        if day not in days:
            days[day] = {'date': day, 'count': 0, 'wins': 0, 'losses': 0,
                         'pnl': 0, 'fee': 0, 'funding': 0}
        pnl = t.get('pnl') or 0
        fee = t.get('fee') or 0
        funding = t.get('funding_fee') or 0
        days[day]['count'] += 1
        days[day]['pnl'] += pnl
        days[day]['fee'] += fee
        days[day]['funding'] += funding
        if pnl > 0:
            days[day]['wins'] += 1
        else:
            days[day]['losses'] += 1

    result = []
    cum_pnl = 0
    for day in sorted(days.keys()):
        d = days[day]
        net = d['pnl'] - d['fee'] - d['funding']
        cum_pnl += net
        d['net'] = net
        d['balance'] = initial_balance + cum_pnl
        d['win_rate'] = d['wins'] / d['count'] * 100 if d['count'] else 0
        result.append(d)
    return result


# ── 路由 ──

@app.route('/')
def index():
    status = load_status()
    logs = load_logs(50)
    now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S UTC')
    return render_template_string(HTML_TEMPLATE, status=status, logs=logs, now=now, page='monitor')


@app.route('/trades')
def trades_page():
    f_strategy = request.args.get('strategy', '')
    f_direction = request.args.get('direction', '')
    f_reason = request.args.get('close_reason', '')
    f_symbol = request.args.get('symbol', '')

    trades = load_trades(
        strategy=f_strategy or None,
        direction=f_direction or None,
        close_reason=f_reason or None,
        symbol=f_symbol or None,
    )
    all_trades = load_trades() if (f_strategy or f_direction or f_reason or f_symbol) else trades

    summary = calc_summary(all_trades)
    strategies = calc_strategy_stats(all_trades)
    reasons = calc_reason_stats(all_trades)
    equity_data = calc_equity_curve(all_trades)

    all_strategies = sorted(set(t.get('strategy', '') for t in all_trades))
    all_reasons = sorted(set(t.get('close_reason', '') for t in all_trades))

    now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S UTC')
    return render_template_string(
        TRADES_TEMPLATE, trades=trades, summary=summary,
        strategies=strategies, reasons=reasons,
        equity_data=equity_data,
        all_strategies=all_strategies, all_reasons=all_reasons,
        filter_strategy=f_strategy, filter_direction=f_direction,
        filter_reason=f_reason, filter_symbol=f_symbol,
        now=now, page='trades',
    )


@app.route('/trades/csv')
def trades_csv():
    """导出 CSV"""
    trades = load_trades(
        strategy=request.args.get('strategy') or None,
        direction=request.args.get('direction') or None,
        close_reason=request.args.get('close_reason') or None,
        symbol=request.args.get('symbol') or None,
    )
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(['close_time', 'symbol', 'direction', 'strategy',
                     'entry_price', 'exit_price', 'quantity', 'margin',
                     'pnl', 'fee', 'funding_fee', 'net_pnl',
                     'hold_min', 'close_reason', 'regime', 'session'])
    for t in trades:
        net = (t.get('pnl') or 0) - (t.get('fee') or 0) - (t.get('funding_fee') or 0)
        writer.writerow([
            t.get('close_time', ''),
            t.get('symbol', ''),
            t.get('direction', ''),
            t.get('strategy', ''),
            t.get('entry_price', ''),
            t.get('exit_price', ''),
            t.get('quantity', ''),
            t.get('margin', ''),
            t.get('pnl', ''),
            t.get('fee', ''),
            t.get('funding_fee', ''),
            round(net, 4),
            t.get('hold_min', ''),
            t.get('close_reason', ''),
            t.get('regime', ''),
            t.get('session', ''),
        ])

    return Response(
        output.getvalue(),
        mimetype='text/csv',
        headers={'Content-Disposition': f'attachment; filename=trades_{datetime.utcnow().strftime("%Y%m%d")}.csv'},
    )


@app.route('/daily')
def daily_page():
    all_trades = load_trades()
    daily_data = calc_daily_stats(all_trades)
    daily_chart = [{'date': d['date'], 'net': round(d['net'], 2)} for d in daily_data]
    now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S UTC')
    return render_template_string(
        DAILY_TEMPLATE, daily_data=daily_data, daily_chart=daily_chart,
        now=now, page='daily',
    )


@app.route('/signals')
def signals_page():
    f_strategy = request.args.get('strategy', '')
    f_status = request.args.get('status', '')
    signals = load_signals(
        strategy=f_strategy or None,
        status=f_status or None,
    )
    all_strategies = set()
    conn = get_db()
    if conn:
        try:
            cur = conn.cursor()
            cur.execute('SELECT DISTINCT strategy FROM signal_log ORDER BY strategy')
            all_strategies = [r[0] for r in cur.fetchall()]
        except Exception:
            all_strategies = []
        finally:
            conn.close()

    now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S UTC')
    return render_template_string(
        SIGNALS_TEMPLATE, signals=signals,
        all_strategies=all_strategies,
        filter_strategy=f_strategy, filter_status=f_status,
        now=now, page='signals',
    )


@app.route('/api/trades')
def api_trades():
    trades = load_trades(
        strategy=request.args.get('strategy'),
        direction=request.args.get('direction'),
        close_reason=request.args.get('close_reason'),
        symbol=request.args.get('symbol'),
    )
    for t in trades:
        for k in ('open_time', 'close_time'):
            if t.get(k):
                t[k] = t[k].isoformat()
    return jsonify({'trades': trades, 'summary': calc_summary(trades)})


@app.route('/api/status')
def api_status():
    if STATUS_FILE.exists():
        with open(STATUS_FILE, 'r') as f:
            return jsonify(json.load(f))
    return jsonify({'running': False})


@app.route('/health')
def health():
    return jsonify({'status': 'ok', 'time': datetime.utcnow().isoformat()})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5210, debug=False)
