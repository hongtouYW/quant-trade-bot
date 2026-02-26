#!/usr/bin/env python3
"""
服务器监控面板 — 端口 5220
轻量级 Flask + psutil，全中文界面
"""
import sys, os, time, json, socket
from collections import deque
from datetime import datetime, timedelta

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from flask import Flask, jsonify, Response

app = Flask(__name__)

# ---------------------------------------------------------------------------
# 历史数据环形缓冲 (最近 60 个点，每 10 秒采样 = 10 分钟)
# ---------------------------------------------------------------------------
MAX_HISTORY = 60
history = {
    "timestamps": deque(maxlen=MAX_HISTORY),
    "cpu": deque(maxlen=MAX_HISTORY),
    "mem": deque(maxlen=MAX_HISTORY),
    "swap": deque(maxlen=MAX_HISTORY),
    "net_sent": deque(maxlen=MAX_HISTORY),
    "net_recv": deque(maxlen=MAX_HISTORY),
    "load1": deque(maxlen=MAX_HISTORY),
}
_last_net = {"sent": 0, "recv": 0, "ts": 0}

# ---------------------------------------------------------------------------
# 已知服务列表
# ---------------------------------------------------------------------------
KNOWN_SERVICES = [
    {"name": "量化交易 (auto_trader)", "keyword": "auto_trader_v41.py", "port": 5001},
    {"name": "交易助手 (5111)", "keyword": "trading_assistant_dashboard", "port": 5111},
    {"name": "Trading SaaS (Gunicorn)", "keyword": "gunicorn", "port": 5200},
    {"name": "BTC 数据采集 (backfill)", "keyword": "resume_backfill.py", "port": None},
    {"name": "清算 WebSocket", "keyword": "start_liquidation_ws.py", "port": None},
    {"name": "数据监控面板", "keyword": "monitor.py", "port": 5210},
    {"name": "服务器监控面板", "keyword": "server_monitor.py", "port": 5220},
    {"name": "Telegram 信号", "keyword": "telegram_signal_dashboard", "port": 5112},
    {"name": "Paper Trader", "keyword": "paper_trader.py", "port": None},
    {"name": "持仓监控", "keyword": "position_monitor.py", "port": None},
    {"name": "MariaDB (MySQL)", "keyword": "mysqld", "port": 3306},
    {"name": "Redis", "keyword": "redis-server", "port": 6379},
    {"name": "Nginx", "keyword": "nginx", "port": 80},
    {"name": "Supervisord", "keyword": "supervisord", "port": None},
]


def _collect_snapshot():
    """采集一次系统快照"""
    import psutil

    now = time.time()
    cpu_percent = psutil.cpu_percent(interval=0)
    mem = psutil.virtual_memory()
    swap = psutil.swap_memory()
    net = psutil.net_io_counters()

    # 计算网络速率 (bytes/s)
    dt = now - _last_net["ts"] if _last_net["ts"] else 10
    if dt < 1:
        dt = 1
    net_sent_rate = (net.bytes_sent - _last_net["sent"]) / dt if _last_net["ts"] else 0
    net_recv_rate = (net.bytes_recv - _last_net["recv"]) / dt if _last_net["ts"] else 0
    _last_net["sent"] = net.bytes_sent
    _last_net["recv"] = net.bytes_recv
    _last_net["ts"] = now

    # 追加历史
    ts_str = datetime.now().strftime("%H:%M:%S")
    history["timestamps"].append(ts_str)
    history["cpu"].append(round(cpu_percent, 1))
    history["mem"].append(round(mem.percent, 1))
    history["swap"].append(round(swap.percent, 1))
    history["net_sent"].append(round(net_sent_rate / 1024, 1))  # KB/s
    history["net_recv"].append(round(net_recv_rate / 1024, 1))
    load1, load5, load15 = psutil.getloadavg()
    history["load1"].append(round(load1, 2))

    return {
        "cpu_percent": round(cpu_percent, 1),
        "cpu_count": psutil.cpu_count(),
        "cpu_freq": round(psutil.cpu_freq().current, 0) if psutil.cpu_freq() else 0,
        "mem_total": mem.total,
        "mem_used": mem.used,
        "mem_available": mem.available,
        "mem_percent": round(mem.percent, 1),
        "mem_cached": getattr(mem, "cached", 0),
        "mem_buffers": getattr(mem, "buffers", 0),
        "swap_total": swap.total,
        "swap_used": swap.used,
        "swap_percent": round(swap.percent, 1),
        "load": [round(load1, 2), round(load5, 2), round(load15, 2)],
        "net_sent_rate": round(net_sent_rate / 1024, 1),
        "net_recv_rate": round(net_recv_rate / 1024, 1),
        "net_total_sent": net.bytes_sent,
        "net_total_recv": net.bytes_recv,
    }


def _get_disk_info():
    import psutil
    parts = []
    for p in psutil.disk_partitions():
        try:
            usage = psutil.disk_usage(p.mountpoint)
            parts.append({
                "device": p.device,
                "mount": p.mountpoint,
                "fstype": p.fstype,
                "total": usage.total,
                "used": usage.used,
                "free": usage.free,
                "percent": round(usage.percent, 1),
            })
        except Exception:
            pass
    return parts


def _get_processes(top_n=25):
    import psutil
    procs = []
    for p in psutil.process_iter(["pid", "name", "username", "cpu_percent",
                                   "memory_info", "memory_percent", "status",
                                   "create_time", "cmdline"]):
        try:
            info = p.info
            cmdline = " ".join(info.get("cmdline") or [])[:120]
            mem_info = info.get("memory_info")
            rss = mem_info.rss if mem_info else 0
            procs.append({
                "pid": info["pid"],
                "name": info["name"],
                "user": info.get("username", ""),
                "cpu": round(info.get("cpu_percent", 0), 1),
                "mem_mb": round(rss / 1048576, 1),
                "mem_pct": round(info.get("memory_percent", 0), 1),
                "status": info.get("status", ""),
                "started": datetime.fromtimestamp(info["create_time"]).strftime("%m-%d %H:%M") if info.get("create_time") else "",
                "cmd": cmdline,
            })
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            pass
    procs.sort(key=lambda x: x["mem_mb"], reverse=True)
    return procs[:top_n]


def _get_services():
    import psutil
    results = []
    all_procs = {p.pid: p for p in psutil.process_iter(["pid", "name", "cmdline", "cpu_percent", "memory_info", "status", "create_time"])}

    for svc in KNOWN_SERVICES:
        found = None
        for pid, p in all_procs.items():
            try:
                cmdline = " ".join(p.info.get("cmdline") or [])
                if svc["keyword"] in cmdline:
                    mem = p.info.get("memory_info")
                    found = {
                        "name": svc["name"],
                        "port": svc["port"],
                        "status": "running",
                        "pid": pid,
                        "cpu": round(p.info.get("cpu_percent", 0), 1),
                        "mem_mb": round(mem.rss / 1048576, 1) if mem else 0,
                        "uptime": _fmt_uptime(time.time() - p.info["create_time"]) if p.info.get("create_time") else "",
                    }
                    break
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass
        if not found:
            found = {
                "name": svc["name"],
                "port": svc["port"],
                "status": "stopped",
                "pid": None,
                "cpu": 0,
                "mem_mb": 0,
                "uptime": "",
            }
        results.append(found)
    return results


def _get_system_info():
    import psutil
    import platform
    boot = datetime.fromtimestamp(psutil.boot_time())
    uptime = datetime.now() - boot
    return {
        "hostname": socket.gethostname(),
        "platform": platform.platform(),
        "kernel": platform.release(),
        "arch": platform.machine(),
        "python": platform.python_version(),
        "boot_time": boot.strftime("%Y-%m-%d %H:%M:%S"),
        "uptime": _fmt_uptime(uptime.total_seconds()),
        "uptime_days": uptime.days,
    }


def _get_connections():
    """获取监听端口列表"""
    import psutil
    conns = []
    seen = set()
    for c in psutil.net_connections(kind="inet"):
        if c.status == "LISTEN" and c.laddr:
            port = c.laddr.port
            if port in seen:
                continue
            seen.add(port)
            addr = c.laddr.ip
            if addr == "0.0.0.0" or addr == "::":
                addr = "*"
            # 尝试获取进程名
            pname = ""
            if c.pid:
                try:
                    pname = psutil.Process(c.pid).name()
                except Exception:
                    pass
            conns.append({
                "port": port,
                "addr": addr,
                "pid": c.pid or 0,
                "process": pname,
            })
    conns.sort(key=lambda x: x["port"])
    return conns


def _fmt_uptime(seconds):
    d = int(seconds // 86400)
    h = int((seconds % 86400) // 3600)
    m = int((seconds % 3600) // 60)
    if d > 0:
        return f"{d}天{h}时{m}分"
    elif h > 0:
        return f"{h}时{m}分"
    else:
        return f"{m}分"


def _fmt_bytes(b):
    for unit in ["B", "KB", "MB", "GB", "TB"]:
        if b < 1024:
            return f"{b:.1f} {unit}"
        b /= 1024
    return f"{b:.1f} PB"


# ---------------------------------------------------------------------------
# API
# ---------------------------------------------------------------------------
@app.route("/api/status")
def api_status():
    snapshot = _collect_snapshot()
    return jsonify({
        "system": _get_system_info(),
        "snapshot": snapshot,
        "disks": _get_disk_info(),
        "processes": _get_processes(25),
        "services": _get_services(),
        "connections": _get_connections(),
        "history": {k: list(v) for k, v in history.items()},
    })


# ---------------------------------------------------------------------------
# HTML 前端
# ---------------------------------------------------------------------------
@app.route("/")
def index():
    return Response(HTML_PAGE, content_type="text/html; charset=utf-8")


HTML_PAGE = r"""<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>服务器监控</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'PingFang SC','Microsoft YaHei',sans-serif;background:#0f1923;color:#e0e6ed;min-height:100vh}
.header{background:linear-gradient(135deg,#1a2a3a,#0d1b2a);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #1e3a5f;position:sticky;top:0;z-index:100}
.header h1{font-size:20px;font-weight:600;color:#4fc3f7}
.header .info{font-size:12px;color:#7a8a9a;display:flex;gap:16px;align-items:center}
.header .dot{width:8px;height:8px;border-radius:50%;background:#4caf50;display:inline-block;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.container{max-width:1400px;margin:0 auto;padding:16px}

/* 概览卡片 */
.overview{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px}
.card{background:#162736;border-radius:10px;padding:16px;border:1px solid #1e3a5f;transition:border-color .2s}
.card:hover{border-color:#4fc3f7}
.card .label{font-size:12px;color:#7a8a9a;margin-bottom:8px;text-transform:uppercase;letter-spacing:1px}
.card .value{font-size:28px;font-weight:700;line-height:1}
.card .sub{font-size:11px;color:#5a7a9a;margin-top:6px}

/* 环形仪表 */
.gauge-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.gauge-card{background:#162736;border-radius:10px;padding:16px;border:1px solid #1e3a5f;text-align:center}
.gauge-card .gtitle{font-size:13px;color:#7a8a9a;margin-bottom:8px}
.gauge-svg{width:120px;height:120px;margin:0 auto}
.gauge-card .gval{font-size:22px;font-weight:700;margin-top:4px}
.gauge-card .gsub{font-size:11px;color:#5a7a9a;margin-top:2px}

/* 图表区 */
.chart-section{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.chart-box{background:#162736;border-radius:10px;padding:16px;border:1px solid #1e3a5f}
.chart-box h3{font-size:14px;color:#4fc3f7;margin-bottom:10px}
.chart-box canvas{width:100%;height:160px}

/* 服务状态 */
.svc-section{margin-bottom:16px}
.svc-section h2{font-size:16px;color:#4fc3f7;margin-bottom:10px;padding-left:4px}
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}
.svc-card{background:#162736;border-radius:8px;padding:12px 14px;border:1px solid #1e3a5f;display:flex;align-items:center;gap:10px}
.svc-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.svc-dot.running{background:#4caf50;box-shadow:0 0 6px #4caf5088}
.svc-dot.stopped{background:#f44336;box-shadow:0 0 6px #f4433688}
.svc-info{flex:1;min-width:0}
.svc-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.svc-meta{font-size:11px;color:#5a7a9a;display:flex;gap:8px;flex-wrap:wrap;margin-top:2px}

/* 表格 */
.table-section{margin-bottom:16px}
.table-section h2{font-size:16px;color:#4fc3f7;margin-bottom:10px;padding-left:4px}
.tbl-wrap{background:#162736;border-radius:10px;border:1px solid #1e3a5f;overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}
th{background:#1a3350;color:#8ab4f8;padding:8px 10px;text-align:left;font-weight:600;position:sticky;top:0;white-space:nowrap}
td{padding:7px 10px;border-top:1px solid #1e3a5f;white-space:nowrap}
tr:hover td{background:#1a2f42}
.bar-cell{position:relative;min-width:80px}
.bar-inner{position:absolute;left:0;top:0;bottom:0;border-radius:2px;opacity:.2}

/* 端口表 */
.port-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.port-item{background:#162736;border-radius:6px;padding:8px 12px;border:1px solid #1e3a5f;font-size:12px}
.port-num{font-size:16px;font-weight:700;color:#4fc3f7}
.port-info{color:#7a8a9a;margin-top:2px}

/* tabs */
.tabs{display:flex;gap:2px;margin-bottom:0}
.tab{padding:8px 16px;background:#1a2a3a;color:#7a8a9a;border:none;border-radius:6px 6px 0 0;cursor:pointer;font-size:13px;font-family:inherit}
.tab.active{background:#162736;color:#4fc3f7;font-weight:600}
.tab-content{display:none}
.tab-content.active{display:block}

/* 颜色 */
.c-green{color:#4caf50}.c-yellow{color:#ffc107}.c-red{color:#f44336}.c-blue{color:#4fc3f7}.c-gray{color:#7a8a9a}

/* 响应式 */
@media(max-width:768px){
  .chart-section{grid-template-columns:1fr}
  .overview{grid-template-columns:repeat(2,1fr)}
  .gauge-row{grid-template-columns:repeat(2,1fr)}
  .header h1{font-size:16px}
  .card .value{font-size:22px}
}
</style>
</head>
<body>
<div class="header">
  <h1>&#x1f5a5; 服务器监控面板</h1>
  <div class="info">
    <span id="hostname">-</span>
    <span>|</span>
    <span id="uptime">-</span>
    <span>|</span>
    <span><span class="dot"></span> <span id="refresh-info">10s 刷新</span></span>
  </div>
</div>

<div class="container">
  <!-- 概览卡片 -->
  <div class="overview" id="overview-cards"></div>

  <!-- 仪表盘 -->
  <div class="gauge-row" id="gauges"></div>

  <!-- 历史图表 -->
  <div class="chart-section">
    <div class="chart-box">
      <h3>CPU / 内存 / Swap 趋势 (10分钟)</h3>
      <canvas id="chart-cpu-mem" height="160"></canvas>
    </div>
    <div class="chart-box">
      <h3>网络流量 (KB/s)</h3>
      <canvas id="chart-net" height="160"></canvas>
    </div>
  </div>

  <!-- 服务状态 -->
  <div class="svc-section">
    <h2>服务状态</h2>
    <div class="svc-grid" id="svc-grid"></div>
  </div>

  <!-- 选项卡：进程 / 磁盘 / 端口 -->
  <div>
    <div class="tabs">
      <button class="tab active" onclick="switchTab(this,'tab-proc')">进程 TOP 25</button>
      <button class="tab" onclick="switchTab(this,'tab-disk')">磁盘分区</button>
      <button class="tab" onclick="switchTab(this,'tab-port')">监听端口</button>
    </div>
    <div id="tab-proc" class="tab-content active">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>PID</th><th>进程</th><th>用户</th><th>CPU%</th><th>内存(MB)</th><th>内存%</th><th>状态</th><th>启动时间</th><th>命令</th></tr></thead>
          <tbody id="proc-body"></tbody>
        </table>
      </div>
    </div>
    <div id="tab-disk" class="tab-content">
      <div class="tbl-wrap">
        <table>
          <thead><tr><th>设备</th><th>挂载点</th><th>文件系统</th><th>总容量</th><th>已用</th><th>可用</th><th>使用率</th><th>使用条</th></tr></thead>
          <tbody id="disk-body"></tbody>
        </table>
      </div>
    </div>
    <div id="tab-port" class="tab-content">
      <div class="port-grid" id="port-grid"></div>
    </div>
  </div>
</div>

<script>
const REFRESH_MS = 10000;
let data = null;

function fmt(bytes) {
  const units = ['B','KB','MB','GB','TB'];
  let i = 0;
  let b = bytes;
  while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
  return b.toFixed(1) + ' ' + units[i];
}

function pctColor(pct) {
  if (pct >= 90) return '#f44336';
  if (pct >= 70) return '#ffc107';
  return '#4caf50';
}

function gaugeSVG(pct, color, size=120) {
  const r = 48, cx = 60, cy = 60, stroke = 8;
  const circ = 2 * Math.PI * r;
  const dash = circ * pct / 100;
  return `<svg viewBox="0 0 120 120" width="${size}" height="${size}">
    <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#1e3a5f" stroke-width="${stroke}"/>
    <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${color}" stroke-width="${stroke}"
      stroke-dasharray="${dash} ${circ}" stroke-linecap="round"
      transform="rotate(-90 ${cx} ${cy})" style="transition:stroke-dasharray .6s"/>
  </svg>`;
}

function renderOverview(d) {
  const s = d.snapshot, sys = d.system;
  const cards = [
    {label:'CPU 使用率', value: s.cpu_percent+'%', sub: s.cpu_count+'核 / '+s.cpu_freq+'MHz', color: pctColor(s.cpu_percent)},
    {label:'内存使用', value: s.mem_percent+'%', sub: fmt(s.mem_used)+' / '+fmt(s.mem_total), color: pctColor(s.mem_percent)},
    {label:'SWAP 使用', value: s.swap_percent+'%', sub: fmt(s.swap_used)+' / '+fmt(s.swap_total), color: pctColor(s.swap_percent)},
    {label:'系统负载', value: s.load[0], sub: '1m/5m/15m: '+s.load.join(' / '), color: s.load[0]>s.cpu_count?'#f44336':'#4caf50'},
    {label:'运行天数', value: sys.uptime_days+'天', sub: sys.uptime, color:'#4fc3f7'},
    {label:'网络 &#x2191;&#x2193;', value: s.net_sent_rate+' / '+s.net_recv_rate, sub: 'KB/s  总计: '+fmt(s.net_total_sent)+' / '+fmt(s.net_total_recv), color:'#4fc3f7'},
  ];
  document.getElementById('overview-cards').innerHTML = cards.map(c =>
    `<div class="card"><div class="label">${c.label}</div><div class="value" style="color:${c.color}">${c.value}</div><div class="sub">${c.sub}</div></div>`
  ).join('');
}

function renderGauges(d) {
  const s = d.snapshot;
  // 找根磁盘
  const rootDisk = (d.disks||[]).find(x => x.mount === '/') || {percent:0, used:0, total:0};
  const items = [
    {title:'CPU', pct:s.cpu_percent, sub: s.cpu_count+'核'},
    {title:'内存', pct:s.mem_percent, sub: fmt(s.mem_used)+' / '+fmt(s.mem_total)},
    {title:'SWAP', pct:s.swap_percent, sub: fmt(s.swap_used)+' / '+fmt(s.swap_total)},
    {title:'磁盘 /', pct:rootDisk.percent, sub: fmt(rootDisk.used)+' / '+fmt(rootDisk.total)},
  ];
  document.getElementById('gauges').innerHTML = items.map(g => {
    const color = pctColor(g.pct);
    return `<div class="gauge-card">
      <div class="gtitle">${g.title}</div>
      <div class="gauge-svg">${gaugeSVG(g.pct, color)}</div>
      <div class="gval" style="color:${color}">${g.pct}%</div>
      <div class="gsub">${g.sub}</div>
    </div>`;
  }).join('');
}

function renderServices(d) {
  const html = d.services.map(s => {
    const dot = s.status === 'running' ? 'running' : 'stopped';
    const meta = s.status === 'running'
      ? `PID:${s.pid} | CPU:${s.cpu}% | 内存:${s.mem_mb}MB | 运行:${s.uptime}` + (s.port ? ` | 端口:${s.port}` : '')
      : '<span class="c-red">未运行</span>' + (s.port ? ` | 端口:${s.port}` : '');
    return `<div class="svc-card"><div class="svc-dot ${dot}"></div><div class="svc-info"><div class="svc-name">${s.name}</div><div class="svc-meta">${meta}</div></div></div>`;
  }).join('');
  document.getElementById('svc-grid').innerHTML = html;
}

function renderProcesses(d) {
  const html = d.processes.map(p => {
    const barColor = p.mem_pct > 10 ? '#f44336' : p.mem_pct > 5 ? '#ffc107' : '#4fc3f7';
    return `<tr>
      <td>${p.pid}</td><td>${p.name}</td><td>${p.user}</td>
      <td>${p.cpu}</td><td>${p.mem_mb}</td>
      <td class="bar-cell"><div class="bar-inner" style="width:${Math.min(p.mem_pct*5,100)}%;background:${barColor}"></div>${p.mem_pct}</td>
      <td>${p.status}</td><td>${p.started}</td>
      <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis" title="${p.cmd}">${p.cmd}</td>
    </tr>`;
  }).join('');
  document.getElementById('proc-body').innerHTML = html;
}

function renderDisks(d) {
  const html = (d.disks||[]).map(dk => {
    const barColor = pctColor(dk.percent);
    return `<tr>
      <td>${dk.device}</td><td>${dk.mount}</td><td>${dk.fstype}</td>
      <td>${fmt(dk.total)}</td><td>${fmt(dk.used)}</td><td>${fmt(dk.free)}</td>
      <td style="color:${barColor}">${dk.percent}%</td>
      <td class="bar-cell" style="min-width:120px"><div class="bar-inner" style="width:${dk.percent}%;background:${barColor}"></div>
        <div style="position:relative;height:14px;background:#1e3a5f;border-radius:3px;overflow:hidden">
          <div style="height:100%;width:${dk.percent}%;background:${barColor};border-radius:3px;transition:width .6s"></div>
        </div>
      </td>
    </tr>`;
  }).join('');
  document.getElementById('disk-body').innerHTML = html;
}

function renderPorts(d) {
  const html = (d.connections||[]).map(c =>
    `<div class="port-item"><div class="port-num">${c.port}</div><div class="port-info">${c.addr} | ${c.process || 'PID:'+c.pid}</div></div>`
  ).join('');
  document.getElementById('port-grid').innerHTML = html;
}

// ---------- 图表绘制 ----------
function drawChart(canvasId, datasets, labels) {
  const canvas = document.getElementById(canvasId);
  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  canvas.width = rect.width * dpr;
  canvas.height = rect.height * dpr;
  ctx.scale(dpr, dpr);
  const W = rect.width, H = rect.height;
  const pad = {top:10,right:10,bottom:24,left:40};
  const cw = W-pad.left-pad.right, ch = H-pad.top-pad.bottom;

  ctx.clearRect(0,0,W,H);

  // 找最大值
  let maxVal = 1;
  datasets.forEach(ds => { ds.data.forEach(v => { if(v>maxVal) maxVal=v; }); });
  if (maxVal < 10) maxVal = 10;
  maxVal = Math.ceil(maxVal / 10) * 10;

  // 网格
  ctx.strokeStyle = '#1e3a5f';
  ctx.lineWidth = 0.5;
  ctx.font = '10px sans-serif';
  ctx.fillStyle = '#5a7a9a';
  for (let i = 0; i <= 4; i++) {
    const y = pad.top + ch - (ch * i / 4);
    ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W-pad.right, y); ctx.stroke();
    ctx.fillText(Math.round(maxVal * i / 4), 4, y + 3);
  }

  // X轴标签
  if (labels.length > 0) {
    const step = Math.max(1, Math.floor(labels.length / 6));
    ctx.fillStyle = '#5a7a9a';
    for (let i = 0; i < labels.length; i += step) {
      const x = pad.left + (i / (labels.length - 1 || 1)) * cw;
      ctx.fillText(labels[i], x - 14, H - 4);
    }
  }

  // 绘制数据线
  datasets.forEach(ds => {
    if (ds.data.length < 2) return;
    ctx.beginPath();
    ctx.strokeStyle = ds.color;
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ds.data.forEach((v, i) => {
      const x = pad.left + (i / (ds.data.length - 1)) * cw;
      const y = pad.top + ch - (v / maxVal) * ch;
      if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.stroke();

    // 面积填充
    const lastX = pad.left + cw;
    ctx.lineTo(lastX, pad.top + ch);
    ctx.lineTo(pad.left, pad.top + ch);
    ctx.closePath();
    ctx.fillStyle = ds.color + '18';
    ctx.fill();
  });

  // 图例
  let lx = pad.left + 4;
  ctx.font = '10px sans-serif';
  datasets.forEach(ds => {
    ctx.fillStyle = ds.color;
    ctx.fillRect(lx, pad.top, 12, 3);
    lx += 14;
    ctx.fillText(ds.label, lx, pad.top + 5);
    lx += ctx.measureText(ds.label).width + 12;
  });
}

function renderCharts(d) {
  const h = d.history;
  drawChart('chart-cpu-mem', [
    {label:'CPU%', data:h.cpu, color:'#4fc3f7'},
    {label:'内存%', data:h.mem, color:'#4caf50'},
    {label:'Swap%', data:h.swap, color:'#ffc107'},
  ], h.timestamps);

  drawChart('chart-net', [
    {label:'发送 KB/s', data:h.net_sent, color:'#ff7043'},
    {label:'接收 KB/s', data:h.net_recv, color:'#66bb6a'},
  ], h.timestamps);
}

function switchTab(btn, id) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}

async function refresh() {
  try {
    const r = await fetch('/api/status');
    data = await r.json();
    document.getElementById('hostname').textContent = data.system.hostname + ' | ' + data.system.platform;
    document.getElementById('uptime').textContent = '运行: ' + data.system.uptime;
    renderOverview(data);
    renderGauges(data);
    renderCharts(data);
    renderServices(data);
    renderProcesses(data);
    renderDisks(data);
    renderPorts(data);
  } catch(e) {
    console.error('刷新失败', e);
  }
}

refresh();
setInterval(refresh, REFRESH_MS);
window.addEventListener('resize', () => { if(data) renderCharts(data); });
</script>
</body>
</html>
"""

if __name__ == "__main__":
    import psutil  # 预先导入验证
    # 初始采样（避免第一次 cpu_percent 返回 0）
    psutil.cpu_percent(interval=1)
    print(f"服务器监控面板启动: http://0.0.0.0:5220")
    print(f"PID: {os.getpid()}")
    app.run(host="0.0.0.0", port=5220, debug=False)
