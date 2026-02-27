#!/usr/bin/env python3
"""Lightweight quality report generator - avoids loading full datasets into memory."""
import sys, os, json, gc
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pyarrow.parquet as pq
import pandas as pd
from pathlib import Path
from datetime import datetime

DATA_DIR = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))) / "data"
REPORT_DIR = DATA_DIR / "reports"


def get_parquet_files(data_type):
    d = DATA_DIR / data_type
    if not d.is_dir():
        return []
    return sorted(d.glob("*.parquet"))


def get_summary(data_type):
    files = get_parquet_files(data_type)
    if not files:
        return {"rows": 0, "from": None, "to": None, "file_count": 0, "size_mb": 0}

    total_rows = 0
    total_size = 0
    for f in files:
        total_size += f.stat().st_size
        meta = pq.read_metadata(f)
        total_rows += meta.num_rows

    # 从首尾文件名推断范围
    min_ts = files[0].stem
    max_ts = files[-1].stem

    # 精确读首尾行
    try:
        pf = pq.ParquetFile(files[0])
        batch = next(pf.iter_batches(batch_size=1, columns=["timestamp"]))
        min_ts = str(batch.column("timestamp")[0].as_py())
    except Exception:
        pass
    try:
        pf = pq.ParquetFile(files[-1])
        last_batch = None
        for batch in pf.iter_batches(batch_size=1000, columns=["timestamp"]):
            last_batch = batch
        if last_batch and len(last_batch) > 0:
            max_ts = str(last_batch.column("timestamp")[-1].as_py())
    except Exception:
        pass

    return {
        "rows": total_rows,
        "from": min_ts,
        "to": max_ts,
        "file_count": len(files),
        "size_mb": round(total_size / 1048576, 2),
    }


def check_file_nulls_dupes(filepath, pk_col="timestamp"):
    """Check nulls and duplicates for a single file."""
    df = pd.read_parquet(filepath)
    rows = len(df)
    nulls = int(df.isnull().sum().sum())
    dupes = int(df.duplicated(subset=[pk_col]).sum()) if pk_col in df.columns else 0
    del df
    gc.collect()
    return rows, nulls, dupes


def validate_data_type(data_type, pk_col="timestamp"):
    """Validate a data type file by file."""
    files = get_parquet_files(data_type)
    if not files:
        return {"status": "empty", "rows": 0, "nulls": 0, "dupes": 0}

    total_rows = 0
    total_nulls = 0
    total_dupes = 0

    for f in files:
        try:
            rows, nulls, dupes = check_file_nulls_dupes(f, pk_col)
            total_rows += rows
            total_nulls += nulls
            total_dupes += dupes
        except Exception as e:
            print(f"  Warning: {f.name}: {e}")

    status = "ok"
    if total_nulls > 0 or total_dupes > 0:
        status = "warning"

    return {
        "status": status,
        "rows": total_rows,
        "nulls": total_nulls,
        "dupes": total_dupes,
        "files": len(files),
    }


def main():
    print("Generating lightweight quality report...")

    report = {
        "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "data_summary": {},
        "validation": {},
    }

    # Data types to check
    data_types = [
        ("klines/1m", "timestamp"),
        ("klines/5m", "timestamp"),
        ("klines/15m", "timestamp"),
        ("klines/1h", "timestamp"),
        ("klines/4h", "timestamp"),
        ("klines/1d", "timestamp"),
        ("funding_rate", "timestamp"),
        ("open_interest", "timestamp"),
        ("agg_trades", "agg_trade_id"),
        ("liquidations", "timestamp"),
        ("whale_tracking", "timestamp"),
        ("trade_flow", "timestamp"),
    ]

    for dt, pk in data_types:
        print(f"  Checking {dt}...")
        report["data_summary"][dt] = get_summary(dt)
        report["validation"][dt] = validate_data_type(dt, pk)
        gc.collect()

    # Overall status
    has_errors = any(v["status"] == "error" for v in report["validation"].values())
    has_warnings = any(v["status"] == "warning" for v in report["validation"].values())
    report["overall_status"] = "error" if has_errors else "warning" if has_warnings else "healthy"

    # Summary counts
    total_rows = sum(v.get("rows", 0) for v in report["validation"].values())
    total_nulls = sum(v.get("nulls", 0) for v in report["validation"].values())
    total_dupes = sum(v.get("dupes", 0) for v in report["validation"].values())
    total_size = sum(v.get("size_mb", 0) for v in report["data_summary"].values())
    report["totals"] = {
        "rows": total_rows,
        "nulls": total_nulls,
        "dupes": total_dupes,
        "size_mb": round(total_size, 2),
        "data_types": len([v for v in report["validation"].values() if v["rows"] > 0]),
    }

    # Save
    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    path = REPORT_DIR / f"quality_report_{ts}.json"
    latest = REPORT_DIR / "latest_report.json"

    for p in [path, latest]:
        with open(p, "w") as f:
            json.dump(report, f, ensure_ascii=False, indent=2, default=str)

    # Print summary
    print(f"\n{'='*50}")
    print(f"  Data Quality Report: {report['overall_status'].upper()}")
    print(f"{'='*50}")
    print(f"  Total: {total_rows:,} rows / {total_size:.1f} MB")
    print(f"  Nulls: {total_nulls:,} | Dupes: {total_dupes:,}")
    print(f"{'='*50}")
    for dt, info in report["data_summary"].items():
        v = report["validation"].get(dt, {})
        if info["rows"] > 0:
            flag = "✓" if v.get("status") == "ok" else "⚠"
            print(f"  {flag} {dt}: {info['rows']:,} rows, {info['size_mb']}MB, {info['from']} ~ {info['to']}")
        else:
            print(f"  - {dt}: empty")
    print(f"{'='*50}")
    print(f"  Report saved: {path}")

    return path


if __name__ == "__main__":
    main()
