#!/usr/bin/env python3
"""BTC 数据采集监控面板 - 详细版"""
import sys, os, json, subprocess, time
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from flask import Flask, jsonify
from pathlib import Path
from datetime import datetime

import pandas as pd
from config.settings import Settings

app = Flask(__name__)
settings = Settings.load()
DATA_DIR = Path(settings.data.data_dir)
LOG_DIR = DATA_DIR.parent / "logs"

# Track speed history for estimation
_speed_history = []


def get_dir_info(subdir):
    path = DATA_DIR / subdir
    if not path.exists():
        return {"exists": False, "files": 0, "size_mb": 0, "size_bytes": 0, "file_list": [], "row_count": 0, "date_range": None}
    files = sorted(path.glob("*.parquet"))
    total_size = sum(f.stat().st_size for f in files)
    file_list = []
    for f in files:
        st = f.stat()
        file_list.append({
            "name": f.name,
            "size_mb": round(st.st_size / 1024 / 1024, 2),
            "size_bytes": st.st_size,
            "modified": datetime.fromtimestamp(st.st_mtime).strftime("%Y-%m-%d %H:%M:%S"),
        })
    # Try to get row count and date range from parquet
    row_count = 0
    date_range = None
    try:
        for f in files:
            pf = pd.read_parquet(f)
            row_count += len(pf)
            if "timestamp" in pf.columns and not pf.empty:
                ts = pd.to_datetime(pf["timestamp"])
                fmin, fmax = ts.min(), ts.max()
                if date_range is None:
                    date_range = {"min": str(fmin), "max": str(fmax)}
                else:
                    if str(fmin) < date_range["min"]:
                        date_range["min"] = str(fmin)
                    if str(fmax) > date_range["max"]:
                        date_range["max"] = str(fmax)
    except Exception:
        pass

    return {
        "exists": True,
        "files": len(files),
        "size_mb": round(total_size / 1024 / 1024, 2),
        "size_bytes": total_size,
        "file_list": file_list,
        "row_count": row_count,
        "date_range": date_range,
    }


def get_process_info(name):
    try:
        result = subprocess.run(
            ["pgrep", "-f", name], capture_output=True, text=True, timeout=5
        )
        pids = [p for p in result.stdout.strip().split("\n") if p]
        if not pids:
            return {"running": False, "pids": [], "cpu": 0, "mem_mb": 0, "uptime": ""}
        pid = pids[0]
        ps = subprocess.run(
            ["ps", "-p", pid, "-o", "pid,%cpu,rss,etime", "--no-headers"],
            capture_output=True, text=True, timeout=5
        )
        parts = ps.stdout.strip().split()
        if len(parts) >= 4:
            return {
                "running": True,
                "pids": pids,
                "cpu": float(parts[1]),
                "mem_mb": round(int(parts[2]) / 1024, 1),
                "uptime": parts[3],
            }
        return {"running": True, "pids": pids, "cpu": 0, "mem_mb": 0, "uptime": ""}
    except Exception:
        return {"running": False, "pids": [], "cpu": 0, "mem_mb": 0, "uptime": ""}


def get_server_stats():
    try:
        disk = subprocess.run(["df", "-h", str(DATA_DIR)], capture_output=True, text=True, timeout=5)
        lines = disk.stdout.strip().split("\n")
        if len(lines) >= 2:
            parts = lines[1].split()
            disk_info = {"total": parts[1], "used": parts[2], "avail": parts[3], "pct": parts[4]}
        else:
            disk_info = {}
    except Exception:
        disk_info = {}

    try:
        mem = subprocess.run(["free", "-m"], capture_output=True, text=True, timeout=5)
        lines = mem.stdout.strip().split("\n")
        if len(lines) >= 2:
            parts = lines[1].split()
            mem_info = {"total_mb": int(parts[1]), "used_mb": int(parts[2]), "avail_mb": int(parts[6])}
        else:
            mem_info = {}
    except Exception:
        mem_info = {}

    try:
        load = subprocess.run(["cat", "/proc/loadavg"], capture_output=True, text=True, timeout=5)
        parts = load.stdout.strip().split()
        load_info = {"1m": float(parts[0]), "5m": float(parts[1]), "15m": float(parts[2])}
    except Exception:
        load_info = {}

    return {"disk": disk_info, "memory": mem_info, "load": load_info}


def tail_log(logfile, lines=50):
    path = LOG_DIR / logfile
    if not path.exists():
        return []
    try:
        with open(path, "r") as f:
            all_lines = f.readlines()
            return [l.rstrip() for l in all_lines[-lines:]]
    except Exception:
        return []


def parse_progress(log_lines):
    global _speed_history
    for line in reversed(log_lines):
        if "AggTrades:" in line and "/" in line:
            try:
                part = line.split("AggTrades:")[1]
                frac = part.split("(")[0].strip()
                current, total = frac.split("/")
                pct = part.split("(")[1].split(")")[0]
                written = part.split("written")[1].split("total")[0].strip()
                cur = int(current)
                tot = int(total)
                now = time.time()
                _speed_history.append((now, cur))
                if len(_speed_history) > 20:
                    _speed_history = _speed_history[-20:]
                speed = 0
                eta = ""
                if len(_speed_history) >= 2:
                    dt = _speed_history[-1][0] - _speed_history[0][0]
                    dw = _speed_history[-1][1] - _speed_history[0][1]
                    if dt > 0 and dw > 0:
                        speed = dw / (dt / 60)  # windows per minute
                        remaining = tot - cur
                        if speed > 0:
                            eta_min = remaining / speed
                            if eta_min > 60:
                                eta = f"{eta_min/60:.1f} 小时"
                            else:
                                eta = f"{eta_min:.0f} 分钟"
                return {
                    "current_window": cur,
                    "total_windows": tot,
                    "percent": pct,
                    "records_written": int(written),
                    "speed": round(speed, 1),
                    "eta": eta,
                }
            except Exception:
                pass
    return None


def count_rate_limits(log_lines):
    count = 0
    for line in log_lines:
        if "Rate limited" in line:
            count += 1
    return count


@app.route("/")
def index():
    return HTML_PAGE


@app.route("/api/detail/<path:data_type>")
def api_detail(data_type):
    """二级详情: 每个文件的记录数、字段信息、数据样本、按月分布"""
    # Map display names to paths
    path_map = {
        "klines_1m": "klines/1m", "klines_5m": "klines/5m", "klines_15m": "klines/15m",
        "klines_1h": "klines/1h", "klines_4h": "klines/4h", "klines_1d": "klines/1d",
    }
    subdir = path_map.get(data_type, data_type)
    path = DATA_DIR / subdir
    if not path.exists():
        return jsonify({"error": "数据目录不存在", "data_type": data_type})

    files = sorted(path.glob("*.parquet"))
    file_details = []
    all_columns = []
    sample_head = []
    sample_tail = []
    total_rows = 0
    monthly_dist = []

    for f in files:
        try:
            df = pd.read_parquet(f)
            rows = len(df)
            total_rows += rows
            cols = list(df.columns)
            if not all_columns:
                all_columns = cols
                # Column types
                col_info = []
                for c in cols:
                    col_info.append({"name": c, "dtype": str(df[c].dtype)})

            st = f.stat()
            finfo = {
                "name": f.name,
                "month": f.stem,
                "rows": rows,
                "size_mb": round(st.st_size / 1024 / 1024, 2),
                "modified": datetime.fromtimestamp(st.st_mtime).strftime("%Y-%m-%d %H:%M:%S"),
            }

            # Date range per file
            if "timestamp" in df.columns and not df.empty:
                ts = pd.to_datetime(df["timestamp"])
                finfo["date_min"] = str(ts.min())
                finfo["date_max"] = str(ts.max())

            # Null count per file
            nulls = int(df.isnull().sum().sum())
            finfo["null_count"] = nulls
            finfo["dupes"] = int(df.duplicated().sum())

            file_details.append(finfo)
            monthly_dist.append({"month": f.stem, "rows": rows, "size_mb": finfo["size_mb"]})

        except Exception as e:
            file_details.append({"name": f.name, "error": str(e)})

    # Sample data (first & last 5 rows from first and last file)
    if files:
        try:
            df_first = pd.read_parquet(files[0])
            sample_head = json.loads(df_first.head(5).to_json(orient="records", date_format="iso", default_handler=str))
        except Exception:
            pass
        try:
            df_last = pd.read_parquet(files[-1])
            sample_tail = json.loads(df_last.tail(5).to_json(orient="records", date_format="iso", default_handler=str))
        except Exception:
            pass

    # Column info
    col_info = []
    if files:
        try:
            df0 = pd.read_parquet(files[0])
            for c in df0.columns:
                info = {"name": c, "dtype": str(df0[c].dtype)}
                if df0[c].dtype in ["float64", "int64", "float32", "int32"]:
                    info["min"] = str(df0[c].min())
                    info["max"] = str(df0[c].max())
                    info["mean"] = str(round(df0[c].mean(), 4))
                col_info.append(info)
        except Exception:
            pass

    return jsonify({
        "data_type": data_type,
        "subdir": subdir,
        "total_rows": total_rows,
        "total_files": len(files),
        "columns": col_info,
        "files": file_details,
        "monthly_dist": monthly_dist,
        "sample_head": sample_head,
        "sample_tail": sample_tail,
    })


@app.route("/api/status")
def api_status():
    kline_timeframes = ["1m", "5m", "15m", "1h", "4h", "1d"]
    klines = {}
    total_kline_size = 0
    total_kline_rows = 0
    for tf in kline_timeframes:
        info = get_dir_info(f"klines/{tf}")
        klines[tf] = info
        total_kline_size += info["size_mb"]
        total_kline_rows += info["row_count"]

    data_types = {
        "klines": {"size_mb": round(total_kline_size, 2), "row_count": total_kline_rows, "timeframes": klines},
        "funding_rate": get_dir_info("funding_rate"),
        "open_interest": get_dir_info("open_interest"),
        "agg_trades": get_dir_info("agg_trades"),
        "liquidations": get_dir_info("liquidations"),
        "whale_tracking": get_dir_info("whale_tracking"),
        "trade_flow": get_dir_info("trade_flow"),
    }

    processes = {
        "resume_backfill": get_process_info("resume_backfill"),
        "liquidation_ws": get_process_info("start_liquidation_ws"),
        "monitor": get_process_info("scripts/monitor.py"),
    }

    backfill_log = tail_log("resume_backfill.log", 80)
    agg_progress = parse_progress(backfill_log)
    rate_limit_count = count_rate_limits(backfill_log)
    ws_log = tail_log("liquidation_ws.log", 30)

    server = get_server_stats()
    total_data_mb = sum(d.get("size_mb", 0) for k, d in data_types.items() if k != "klines") + total_kline_size

    return jsonify({
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "data": data_types,
        "processes": processes,
        "agg_progress": agg_progress,
        "rate_limit_count": rate_limit_count,
        "server": server,
        "total_data_mb": round(total_data_mb, 2),
        "logs": {
            "backfill": backfill_log[-30:],
            "liquidation_ws": ws_log[-15:],
        },
    })


HTML_PAGE = r"""<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BTC 数据采集监控</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, 'PingFang SC', 'Microsoft YaHei', sans-serif; background: #0a0e17; color: #e0e6f0; padding: 16px; min-height: 100vh; }
a { color: #3b82f6; text-decoration: none; }

.header { text-align: center; margin-bottom: 24px; padding: 20px 0; border-bottom: 1px solid #1e293b; }
.header h1 { font-size: 26px; color: #00d4aa; letter-spacing: 2px; }
.header .subtitle { color: #7a8ba8; font-size: 13px; margin-top: 6px; }
.header .time { color: #4a5568; font-size: 12px; margin-top: 4px; }

/* Top summary cards */
.summary-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.summary-card { background: #111827; border: 1px solid #1e293b; border-radius: 12px; padding: 16px; text-align: center; }
.summary-card .num { font-size: 28px; font-weight: 700; margin: 6px 0; }
.summary-card .label { font-size: 12px; color: #7a8ba8; }
.num-green { color: #00d4aa; }
.num-blue { color: #3b82f6; }
.num-yellow { color: #f0b429; }
.num-purple { color: #a78bfa; }

.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 16px; margin-bottom: 16px; }
.card { background: #111827; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; }
.card h3 { font-size: 15px; color: #7a8ba8; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.card h3 .icon { font-size: 18px; }

.stat-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #151d2e; }
.stat-row:last-child { border-bottom: none; }
.stat-label { color: #94a3b8; font-size: 13px; }
.stat-value { font-size: 13px; font-weight: 600; }
.stat-sub { font-size: 11px; color: #4a5568; margin-top: 2px; }

.ok { color: #00d4aa; }
.warn { color: #f0b429; }
.err { color: #ef4444; }
.blue { color: #3b82f6; }
.purple { color: #a78bfa; }

/* Progress */
.progress-wrap { margin: 8px 0 14px; }
.progress-bar { width: 100%; height: 28px; background: #1e293b; border-radius: 14px; overflow: hidden; position: relative; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #00d4aa, #3b82f6); border-radius: 14px; transition: width 1s ease; }
.progress-text { position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
.progress-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-top: 10px; }
.progress-stat { background: #0d1117; border-radius: 8px; padding: 10px; text-align: center; }
.progress-stat .val { font-size: 18px; font-weight: 700; color: #00d4aa; }
.progress-stat .lbl { font-size: 11px; color: #7a8ba8; margin-top: 2px; }

/* Data bars */
.data-item { margin-bottom: 12px; }
.data-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.data-name { font-size: 14px; font-weight: 600; color: #e0e6f0; }
.data-meta { font-size: 11px; color: #4a5568; margin-bottom: 6px; }
.data-bar-wrap { height: 8px; background: #1e293b; border-radius: 4px; overflow: hidden; }
.data-bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.badge-ok { background: #064e3b; color: #00d4aa; }
.badge-run { background: #1e3a5f; color: #3b82f6; }
.badge-wait { background: #3b2f1e; color: #f0b429; }
.badge-off { background: #2d1f1f; color: #ef4444; }
.badge-done { background: #1a2e1a; color: #4ade80; }

/* Process cards */
.proc-card { background: #0d1117; border-radius: 8px; padding: 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
.proc-left { display: flex; flex-direction: column; gap: 3px; }
.proc-name { font-size: 14px; font-weight: 600; }
.proc-detail { font-size: 11px; color: #4a5568; }

/* Server gauge */
.gauge-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.gauge { text-align: center; }
.gauge-ring { width: 80px; height: 80px; margin: 0 auto 8px; position: relative; }
.gauge-ring svg { transform: rotate(-90deg); }
.gauge-ring .val { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 16px; font-weight: 700; }
.gauge-label { font-size: 12px; color: #7a8ba8; }

/* Kline visual bars */
.kline-bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.kline-label { width: 30px; font-size: 12px; color: #7a8ba8; text-align: right; }
.kline-bar-bg { flex: 1; height: 20px; background: #1e293b; border-radius: 4px; overflow: hidden; position: relative; }
.kline-bar-fg { height: 100%; background: linear-gradient(90deg, #3b82f6, #a78bfa); border-radius: 4px; }
.kline-bar-text { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 11px; color: #e0e6f0; font-weight: 600; }

/* Timeline */
.timeline { display: flex; align-items: center; height: 30px; background: #1e293b; border-radius: 6px; overflow: hidden; margin: 8px 0; position: relative; }
.timeline-fill { height: 100%; }
.timeline-labels { display: flex; justify-content: space-between; font-size: 10px; color: #4a5568; margin-top: 2px; }

/* Log */
.log-box { background: #0d1117; border: 1px solid #1e293b; border-radius: 8px; padding: 12px; max-height: 350px; overflow-y: auto; font-family: 'Menlo', 'Consolas', monospace; font-size: 11px; line-height: 1.7; }
.log-line { white-space: pre-wrap; word-break: break-all; }
.log-line .info { color: #3b82f6; }
.log-line .warn { color: #f0b429; }
.log-line .err { color: #ef4444; }

/* Tabs */
.tab-bar { display: flex; gap: 2px; margin-bottom: 12px; }
.tab { padding: 6px 16px; background: #1e293b; border-radius: 6px 6px 0 0; font-size: 13px; color: #7a8ba8; cursor: pointer; }
.tab.active { background: #0d1117; color: #00d4aa; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* Clickable data items */
.data-item.clickable { cursor: pointer; padding: 8px; border-radius: 8px; transition: background 0.2s; }
.data-item.clickable:hover { background: #1a2332; }
.kline-bar-row.clickable { cursor: pointer; padding: 4px; border-radius: 6px; transition: background 0.2s; }
.kline-bar-row.clickable:hover { background: #1a2332; }

/* Modal overlay */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.75); z-index: 1000; overflow-y: auto; padding: 30px 16px; }
.modal-overlay.active { display: block; }
.modal { background: #111827; border: 1px solid #1e293b; border-radius: 16px; max-width: 900px; margin: 0 auto; padding: 24px; position: relative; }
.modal-close { position: absolute; top: 16px; right: 20px; background: none; border: none; color: #7a8ba8; font-size: 24px; cursor: pointer; }
.modal-close:hover { color: #ef4444; }
.modal h2 { font-size: 20px; color: #00d4aa; margin-bottom: 6px; }
.modal .modal-sub { font-size: 13px; color: #7a8ba8; margin-bottom: 20px; }
.modal-section { margin-bottom: 20px; }
.modal-section h4 { font-size: 14px; color: #7a8ba8; margin-bottom: 10px; border-bottom: 1px solid #1e293b; padding-bottom: 6px; }

/* Modal table */
.m-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.m-table th { background: #0d1117; color: #7a8ba8; padding: 8px 10px; text-align: left; font-weight: 600; border-bottom: 1px solid #1e293b; }
.m-table td { padding: 7px 10px; border-bottom: 1px solid #151d2e; color: #e0e6f0; }
.m-table tr:hover td { background: #1a2332; }
.m-table .num { text-align: right; font-family: 'Menlo', monospace; }

/* Monthly chart in modal */
.month-chart { display: flex; align-items: flex-end; gap: 4px; height: 120px; padding: 10px 0; }
.month-bar { flex: 1; min-width: 20px; background: linear-gradient(180deg, #3b82f6, #1e3a5f); border-radius: 4px 4px 0 0; position: relative; transition: height 0.3s; }
.month-bar:hover { opacity: 0.8; }
.month-bar .tip { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #0d1117; border: 1px solid #1e293b; border-radius: 6px; padding: 4px 8px; font-size: 10px; white-space: nowrap; display: none; color: #e0e6f0; z-index: 10; }
.month-bar:hover .tip { display: block; }
.month-labels { display: flex; gap: 4px; }
.month-labels span { flex: 1; min-width: 20px; text-align: center; font-size: 9px; color: #4a5568; }

/* Sample data */
.sample-box { background: #0d1117; border: 1px solid #1e293b; border-radius: 8px; overflow-x: auto; padding: 10px; }
.sample-box table { font-size: 11px; white-space: nowrap; }
.sample-box th { color: #00d4aa; }

.refresh-btn { position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #00d4aa, #3b82f6); color: #0a0e17; border: none; padding: 12px 24px; border-radius: 24px; cursor: pointer; font-weight: 700; font-size: 14px; box-shadow: 0 4px 15px rgba(0,212,170,0.3); z-index: 100; }

@media (max-width: 768px) {
  .summary-row { grid-template-columns: repeat(2, 1fr); }
  .grid { grid-template-columns: 1fr; }
  .gauge-row { grid-template-columns: 1fr 1fr 1fr; }
  .progress-stats { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<div class="header">
  <h1>BTC 数据采集监控</h1>
  <div class="subtitle">Stage 1: 历史数据回填 & 实时采集 | BTCUSDT 永续合约</div>
  <div class="time" id="updateTime">加载中...</div>
</div>

<!-- Summary cards -->
<div class="summary-row" id="summaryCards"></div>

<!-- Row 1: Progress + Processes -->
<div class="grid">
  <div class="card">
    <h3>成交明细采集进度</h3>
    <div id="aggProgress"></div>
  </div>
  <div class="card">
    <h3>进程状态</h3>
    <div id="processes"></div>
  </div>
</div>

<!-- Row 2: Data overview + Kline bars -->
<div class="grid">
  <div class="card">
    <h3>数据总览</h3>
    <div id="dataOverview"></div>
  </div>
  <div class="card">
    <h3>K线数据详情</h3>
    <div id="klineDetail"></div>
  </div>
</div>

<!-- Row 3: Server + File detail -->
<div class="grid">
  <div class="card">
    <h3>服务器资源</h3>
    <div id="serverStats"></div>
  </div>
  <div class="card">
    <h3>文件分布 (成交明细)</h3>
    <div id="fileDetail"></div>
  </div>
</div>

<!-- Row 4: Logs -->
<div class="grid" style="grid-template-columns: 1fr;">
  <div class="card">
    <h3>采集日志</h3>
    <div class="tab-bar">
      <div class="tab active" onclick="switchTab('backfill')">回填日志</div>
      <div class="tab" onclick="switchTab('ws')">清算日志</div>
    </div>
    <div class="tab-content active" id="tab-backfill">
      <div class="log-box" id="backfillLog"></div>
    </div>
    <div class="tab-content" id="tab-ws">
      <div class="log-box" id="wsLog"></div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <button class="modal-close" onclick="closeModal()">&times;</button>
    <div id="modalContent">加载中...</div>
  </div>
</div>

<button class="refresh-btn" onclick="loadData()">刷新</button>

<script>
var DATA_NAMES = {
  'agg_trades': '成交明细', 'funding_rate': '资金费率', 'open_interest': '持仓量',
  'liquidations': '清算数据', 'whale_tracking': '巨鲸追踪', 'trade_flow': '资金流向'
};
var DATA_COLORS = {
  'agg_trades': '#3b82f6', 'funding_rate': '#00d4aa', 'open_interest': '#f0b429',
  'liquidations': '#ef4444', 'whale_tracking': '#a78bfa', 'trade_flow': '#f472b6'
};
var DATA_DESC = {
  'agg_trades': '逐笔成交聚合, 最近1年', 'funding_rate': '每8小时一条, 完整历史',
  'open_interest': '5分钟粒度, 仅最近30天', 'liquidations': 'WebSocket 实时推送',
  'whale_tracking': '大额交易 (>$100K)', 'trade_flow': '5分钟买卖流统计'
};

function loadData() {
  fetch('/api/status').then(r => r.json()).then(d => render(d)).catch(e => console.error(e));
}

function render(d) {
  document.getElementById('updateTime').textContent = '上次更新: ' + d.timestamp + ' (每15秒自动刷新)';
  renderSummary(d);
  renderProgress(d);
  renderProcesses(d);
  renderDataOverview(d);
  renderKlines(d);
  renderServer(d);
  renderFiles(d);
  renderLogs(d);
}

function renderSummary(d) {
  let total = d.total_data_mb;
  let types_done = 0;
  let types_total = 7;
  ['funding_rate','open_interest'].forEach(t => { if (d.data[t].row_count > 0) types_done++; });
  if (d.data.klines.row_count > 0) types_done++;
  ['agg_trades','liquidations','whale_tracking','trade_flow'].forEach(t => { if (d.data[t].row_count > 0) types_done++; });
  let totalRows = d.data.klines.row_count;
  ['agg_trades','funding_rate','open_interest','liquidations','whale_tracking','trade_flow'].forEach(t => { totalRows += d.data[t].row_count; });

  let pct = d.agg_progress ? d.agg_progress.percent : '0%';

  let h = '';
  h += '<div class="summary-card"><div class="label">数据总量</div><div class="num num-green">' + total.toFixed(1) + ' MB</div><div class="label">所有 Parquet 文件</div></div>';
  h += '<div class="summary-card"><div class="label">总记录数</div><div class="num num-blue">' + totalRows.toLocaleString() + '</div><div class="label">全部数据类型</div></div>';
  h += '<div class="summary-card"><div class="label">采集完成度</div><div class="num num-yellow">' + types_done + ' / ' + types_total + '</div><div class="label">数据类型已就绪</div></div>';
  h += '<div class="summary-card"><div class="label">成交明细进度</div><div class="num num-purple">' + pct + '</div><div class="label">1年历史数据</div></div>';
  document.getElementById('summaryCards').innerHTML = h;
}

function renderProgress(d) {
  let ap = d.agg_progress;
  let h = '';
  if (ap) {
    let pct = parseFloat(ap.percent);
    h += '<div class="progress-wrap">';
    h += '<div class="progress-bar"><div class="progress-fill" style="width:' + Math.max(pct, 1) + '%"></div>';
    h += '<div class="progress-text">' + ap.percent + ' (' + ap.current_window + '/' + ap.total_windows + ' 窗口)</div></div></div>';
    h += '<div class="progress-stats">';
    h += '<div class="progress-stat"><div class="val">' + ap.records_written.toLocaleString() + '</div><div class="lbl">已写入记录</div></div>';
    h += '<div class="progress-stat"><div class="val">' + (ap.speed > 0 ? ap.speed + '/分钟' : '计算中...') + '</div><div class="lbl">采集速度 (窗口)</div></div>';
    h += '<div class="progress-stat"><div class="val">' + (ap.eta || '计算中...') + '</div><div class="lbl">预计剩余时间</div></div>';
    h += '</div>';
    if (d.rate_limit_count > 0) {
      h += '<div style="margin-top:10px; font-size:12px; color:#f0b429">API限速触发: ' + d.rate_limit_count + ' 次 (最近日志中)</div>';
    }
  } else {
    h = '<div style="text-align:center; padding:20px; color:#7a8ba8">回填进程未运行或暂无进度数据</div>';
  }
  document.getElementById('aggProgress').innerHTML = h;
}

function renderProcesses(d) {
  let procs = [
    {key:'resume_backfill', name:'历史数据回填', desc:'采集成交明细 → 巨鲸追踪 → 资金流向'},
    {key:'liquidation_ws', name:'清算监听', desc:'WebSocket 实时接收清算事件'},
    {key:'monitor', name:'监控面板', desc:'本页面的 Flask 服务'},
  ];
  let h = '';
  for (let p of procs) {
    let info = d.processes[p.key] || {running:false};
    let badge = info.running ? '<span class="badge badge-run">运行中</span>' : '<span class="badge badge-off">已停止</span>';
    let detail = '';
    if (info.running) {
      detail = 'CPU: ' + info.cpu + '% | 内存: ' + info.mem_mb + ' MB | 运行: ' + info.uptime;
    }
    h += '<div class="proc-card"><div class="proc-left"><div class="proc-name">' + p.name + ' ' + badge + '</div>';
    h += '<div class="proc-detail">' + p.desc + '</div>';
    if (detail) h += '<div class="proc-detail" style="color:#94a3b8">' + detail + '</div>';
    h += '</div></div>';
  }
  document.getElementById('processes').innerHTML = h;
}

function renderDataOverview(d) {
  let maxSize = Math.max(d.data.klines.size_mb, 1);
  let types = ['agg_trades','funding_rate','open_interest','liquidations','whale_tracking','trade_flow'];
  for (let t of types) { maxSize = Math.max(maxSize, d.data[t].size_mb); }

  let h = '';
  // Klines first (no click - has its own section)
  let kinfo = d.data.klines;
  h += buildDataItem('K线数据', '6个周期, 2年+历史', '#00d4aa', kinfo.size_mb, kinfo.row_count, null, maxSize, true, null);

  for (let t of types) {
    let info = d.data[t];
    let name = DATA_NAMES[t];
    let desc = DATA_DESC[t];
    let color = DATA_COLORS[t];
    let done = info.row_count > 0;
    h += buildDataItem(name, desc, color, info.size_mb, info.row_count, info.date_range, maxSize, done, t);
  }
  document.getElementById('dataOverview').innerHTML = h;
}

function buildDataItem(name, desc, color, sizeMb, rows, dateRange, maxSize, done, dataKey) {
  let pct = maxSize > 0 ? Math.max((sizeMb / maxSize) * 100, 1) : 1;
  let statusBadge = done ? '<span class="badge badge-done">已完成</span>' : '<span class="badge badge-wait">等待中</span>';
  if (name === '成交明细' && rows > 0) statusBadge = '<span class="badge badge-run">采集中</span>';
  if (name === '清算数据') statusBadge = rows > 0 ? '<span class="badge badge-done">监听中</span>' : '<span class="badge badge-run">监听中</span>';

  let click = dataKey ? ' clickable" onclick="openDetail(\'' + dataKey + '\',\'' + name + '\')"' : '"';
  let h = '<div class="data-item' + click + '>';
  h += '<div class="data-header"><span class="data-name">' + name + ' ' + statusBadge;
  if (dataKey) h += ' <span style="font-size:11px;color:#4a5568">点击查看详情 &rarr;</span>';
  h += '</span>';
  h += '<span class="stat-value" style="color:' + color + '">' + sizeMb + ' MB | ' + rows.toLocaleString() + ' 条</span></div>';
  h += '<div class="data-meta">' + desc;
  if (dateRange) h += ' | 范围: ' + dateRange.min.substring(0,10) + ' ~ ' + dateRange.max.substring(0,10);
  h += '</div>';
  h += '<div class="data-bar-wrap"><div class="data-bar-fill" style="width:' + pct + '%; background:' + color + '"></div></div>';
  h += '</div>';
  return h;
}

function renderKlines(d) {
  let tfs = d.data.klines.timeframes;
  let maxMb = 0;
  for (let tf in tfs) { maxMb = Math.max(maxMb, tfs[tf].size_mb); }
  let h = '';
  for (let tf in tfs) {
    let info = tfs[tf];
    let pct = maxMb > 0 ? (info.size_mb / maxMb) * 100 : 0;
    let dr = info.date_range ? info.date_range.min.substring(0,10) + ' ~ ' + info.date_range.max.substring(0,10) : '';
    h += '<div class="kline-bar-row clickable" onclick="openDetail(\'klines_' + tf + '\',\'K线 ' + tf + '\')">';
    h += '<span class="kline-label">' + tf + '</span>';
    h += '<div class="kline-bar-bg"><div class="kline-bar-fg" style="width:' + Math.max(pct, 2) + '%"></div>';
    h += '<span class="kline-bar-text">' + info.size_mb + ' MB | ' + info.row_count.toLocaleString() + ' 条 | ' + info.files + ' 文件</span></div>';
    h += '</div>';
    if (dr) h += '<div style="margin-left:38px; font-size:10px; color:#4a5568; margin-bottom:4px">时间范围: ' + dr + '</div>';
  }
  document.getElementById('klineDetail').innerHTML = h;
}

function renderServer(d) {
  let s = d.server;
  let h = '<div class="gauge-row">';

  // Disk
  if (s.disk && s.disk.pct) {
    let diskPct = parseInt(s.disk.pct);
    h += buildGauge('磁盘', diskPct, s.disk.used + '/' + s.disk.total, diskPct > 80 ? '#ef4444' : '#00d4aa');
  }
  // Memory
  if (s.memory && s.memory.total_mb) {
    let memPct = Math.round(s.memory.used_mb / s.memory.total_mb * 100);
    h += buildGauge('内存', memPct, s.memory.used_mb + '/' + s.memory.total_mb + 'M', memPct > 80 ? '#f0b429' : '#3b82f6');
  }
  // Load
  if (s.load) {
    let loadPct = Math.min(Math.round(s.load['1m'] * 50), 100);
    h += buildGauge('负载', loadPct, s.load['1m'].toFixed(2), loadPct > 80 ? '#ef4444' : '#a78bfa');
  }
  h += '</div>';
  document.getElementById('serverStats').innerHTML = h;
}

function buildGauge(label, pct, text, color) {
  let r = 34, c = 2 * Math.PI * r, offset = c * (1 - pct / 100);
  let h = '<div class="gauge"><div class="gauge-ring">';
  h += '<svg width="80" height="80"><circle cx="40" cy="40" r="' + r + '" fill="none" stroke="#1e293b" stroke-width="8"/>';
  h += '<circle cx="40" cy="40" r="' + r + '" fill="none" stroke="' + color + '" stroke-width="8" stroke-dasharray="' + c + '" stroke-dashoffset="' + offset + '" stroke-linecap="round"/></svg>';
  h += '<div class="val" style="color:' + color + '">' + pct + '%</div></div>';
  h += '<div class="gauge-label">' + label + '</div>';
  h += '<div style="font-size:11px; color:#4a5568">' + text + '</div></div>';
  return h;
}

function renderFiles(d) {
  let files = d.data.agg_trades.file_list || [];
  if (files.length === 0) {
    document.getElementById('fileDetail').innerHTML = '<div style="text-align:center; color:#7a8ba8; padding:20px">暂无文件</div>';
    return;
  }
  let maxSize = Math.max(...files.map(f => f.size_bytes || f.size_mb * 1024 * 1024));
  let h = '';
  for (let f of files) {
    let sz = f.size_bytes || f.size_mb * 1024 * 1024;
    let pct = maxSize > 0 ? (sz / maxSize) * 100 : 0;
    let month = f.name.replace('.parquet', '');
    h += '<div style="margin-bottom:8px">';
    h += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:2px"><span style="color:#94a3b8">' + month + '</span><span class="ok">' + f.size_mb + ' MB</span></div>';
    h += '<div class="data-bar-wrap"><div class="data-bar-fill" style="width:' + pct + '%; background: linear-gradient(90deg, #3b82f6, #a78bfa)"></div></div>';
    h += '<div style="font-size:10px; color:#4a5568">最后更新: ' + f.modified + '</div>';
    h += '</div>';
  }
  document.getElementById('fileDetail').innerHTML = h;
}

function renderLogs(d) {
  renderLogBox('backfillLog', d.logs.backfill || []);
  renderLogBox('wsLog', d.logs.liquidation_ws || []);
}

function renderLogBox(id, lines) {
  let h = '';
  for (let line of lines) {
    let cls = '';
    if (line.includes('WARNING')) cls = 'warn';
    else if (line.includes('ERROR')) cls = 'err';
    else if (line.includes('INFO')) cls = 'info';
    h += '<div class="log-line"><span class="' + cls + '">' + escHtml(line) + '</span></div>';
  }
  let el = document.getElementById(id);
  el.innerHTML = h;
  el.scrollTop = el.scrollHeight;
}

function switchTab(name) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  event.target.classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// === 二级详情弹窗 ===
function openDetail(dataKey, displayName) {
  document.getElementById('modalOverlay').classList.add('active');
  document.getElementById('modalContent').innerHTML = '<div style="text-align:center;padding:40px;color:#7a8ba8">加载 ' + displayName + ' 详情中...</div>';
  fetch('/api/detail/' + dataKey)
    .then(r => r.json())
    .then(d => renderDetail(d, displayName))
    .catch(e => {
      document.getElementById('modalContent').innerHTML = '<div style="color:#ef4444">加载失败: ' + e + '</div>';
    });
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('active');
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

function renderDetail(d, displayName) {
  let h = '';
  h += '<h2>' + displayName + ' 详情</h2>';
  h += '<div class="modal-sub">' + d.subdir + ' | 共 ' + d.total_files + ' 个文件 | ' + d.total_rows.toLocaleString() + ' 条记录</div>';

  // === 按月分布图 ===
  if (d.monthly_dist && d.monthly_dist.length > 0) {
    h += '<div class="modal-section"><h4>按月记录分布</h4>';
    let maxRows = Math.max(...d.monthly_dist.map(m => m.rows));
    h += '<div class="month-chart">';
    for (let m of d.monthly_dist) {
      let pct = maxRows > 0 ? (m.rows / maxRows) * 100 : 0;
      h += '<div class="month-bar" style="height:' + Math.max(pct, 3) + '%">';
      h += '<div class="tip">' + m.month + '<br>' + m.rows.toLocaleString() + ' 条<br>' + m.size_mb + ' MB</div>';
      h += '</div>';
    }
    h += '</div>';
    h += '<div class="month-labels">';
    for (let m of d.monthly_dist) {
      h += '<span>' + m.month.substring(5) + '</span>';
    }
    h += '</div></div>';
  }

  // === 文件列表 ===
  h += '<div class="modal-section"><h4>文件列表</h4>';
  h += '<table class="m-table"><thead><tr>';
  h += '<th>文件</th><th class="num">记录数</th><th class="num">大小</th><th>时间范围</th><th class="num">空值</th><th class="num">重复</th><th>更新时间</th>';
  h += '</tr></thead><tbody>';
  for (let f of d.files) {
    if (f.error) {
      h += '<tr><td>' + f.name + '</td><td colspan="6" style="color:#ef4444">' + f.error + '</td></tr>';
      continue;
    }
    let dr = (f.date_min ? f.date_min.substring(0,10) + ' ~ ' + f.date_max.substring(0,10) : '-');
    let nullCls = f.null_count > 0 ? 'warn' : 'ok';
    let dupeCls = f.dupes > 0 ? 'warn' : 'ok';
    h += '<tr>';
    h += '<td>' + f.month + '</td>';
    h += '<td class="num">' + f.rows.toLocaleString() + '</td>';
    h += '<td class="num">' + f.size_mb + ' MB</td>';
    h += '<td style="font-size:11px">' + dr + '</td>';
    h += '<td class="num ' + nullCls + '">' + f.null_count + '</td>';
    h += '<td class="num ' + dupeCls + '">' + f.dupes + '</td>';
    h += '<td style="font-size:11px;color:#4a5568">' + f.modified + '</td>';
    h += '</tr>';
  }
  h += '</tbody></table></div>';

  // === 字段信息 ===
  if (d.columns && d.columns.length > 0) {
    h += '<div class="modal-section"><h4>字段结构</h4>';
    h += '<table class="m-table"><thead><tr><th>字段名</th><th>类型</th><th class="num">最小值</th><th class="num">最大值</th><th class="num">平均值</th></tr></thead><tbody>';
    for (let c of d.columns) {
      h += '<tr><td style="color:#00d4aa;font-weight:600">' + c.name + '</td>';
      h += '<td style="color:#7a8ba8">' + c.dtype + '</td>';
      h += '<td class="num">' + (c.min !== undefined ? truncVal(c.min) : '-') + '</td>';
      h += '<td class="num">' + (c.max !== undefined ? truncVal(c.max) : '-') + '</td>';
      h += '<td class="num">' + (c.mean !== undefined ? truncVal(c.mean) : '-') + '</td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';
  }

  // === 数据样本 ===
  if (d.sample_head && d.sample_head.length > 0) {
    h += '<div class="modal-section"><h4>最早5条记录</h4>';
    h += buildSampleTable(d.sample_head, d.columns);
    h += '</div>';
  }
  if (d.sample_tail && d.sample_tail.length > 0) {
    h += '<div class="modal-section"><h4>最新5条记录</h4>';
    h += buildSampleTable(d.sample_tail, d.columns);
    h += '</div>';
  }

  document.getElementById('modalContent').innerHTML = h;
}

function buildSampleTable(rows, columns) {
  if (!rows || rows.length === 0) return '<div style="color:#7a8ba8">无数据</div>';
  let keys = columns ? columns.map(c => c.name) : Object.keys(rows[0]);
  let h = '<div class="sample-box"><table class="m-table"><thead><tr>';
  for (let k of keys) h += '<th>' + k + '</th>';
  h += '</tr></thead><tbody>';
  for (let row of rows) {
    h += '<tr>';
    for (let k of keys) {
      let v = row[k];
      if (v === null || v === undefined) v = '<span style="color:#4a5568">null</span>';
      else v = truncVal(String(v));
      h += '<td style="font-size:11px">' + v + '</td>';
    }
    h += '</tr>';
  }
  h += '</tbody></table></div>';
  return h;
}

function truncVal(s) {
  if (!s) return '-';
  s = String(s);
  return s.length > 24 ? s.substring(0, 24) + '...' : s;
}

loadData();
setInterval(loadData, 15000);
</script>
</body>
</html>
"""

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5210, debug=False)
