"""
V4-Lite 监控面板
运行在 port 5210
"""
import os
import sys
import json
from datetime import datetime
from pathlib import Path

from flask import Flask, render_template_string, jsonify

app = Flask(__name__)

# 项目根目录
BASE_DIR = Path(__file__).parent.parent.parent
LOG_FILE = BASE_DIR / "logs" / "trading.log"
STATUS_FILE = BASE_DIR / "data" / "status.json"

HTML_TEMPLATE = """
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>量化交易 V4-Lite 监控</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, 'Segoe UI', 'PingFang SC', monospace; background: #0d1117; color: #c9d1d9; padding: 20px; }
        h1 { color: #58a6ff; margin-bottom: 20px; font-size: 24px; }
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
    </style>
</head>
<body>
    <h1>量化交易 V4-Lite <span class="status-badge {{ 'status-running' if status.running else 'status-stopped' }}">{{ '运行中' if status.running else '已停止' }}</span></h1>

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
            <tr><th>交易对</th><th>方向</th><th>入场价</th><th>浮动盈亏</th><th>R值</th><th>持仓时间</th></tr>
            {% for p in status.positions %}
            <tr>
                <td>{{ p.symbol }}</td>
                <td class="{{ 'positive' if p.direction == 'LONG' else 'negative' }}">{{ '做多' if p.direction == 'LONG' else '做空' }}</td>
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


def load_status():
    """加载状态文件"""
    default = {
        'running': False,
        'balance': 2000,
        'initial_balance': 2000,
        'total_pnl': 0,
        'daily_pnl': 0,
        'daily_trades': 0,
        'win_rate': 0,
        'leverage': 10,
        'session': 'N/A',
        'open_positions': 0,
        'max_positions': 4,
        'consec_losses': 0,
        'risk_scale': 1.0,
        'total_margin': 0,
        'positions': [],
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
    """加载最近 n 行日志"""
    if not LOG_FILE.exists():
        return ["暂无日志"]
    try:
        with open(LOG_FILE, 'r', encoding='utf-8', errors='ignore') as f:
            lines = f.readlines()
        return [l.rstrip() for l in lines[-n:]]
    except Exception as e:
        return [f"日志读取失败: {e}"]


@app.route('/')
def index():
    status = load_status()
    logs = load_logs(50)
    now = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S UTC')
    return render_template_string(HTML_TEMPLATE, status=status, logs=logs, now=now)


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
