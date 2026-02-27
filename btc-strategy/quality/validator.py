import os
import pandas as pd
import pyarrow.parquet as pq
from datetime import datetime, timedelta
from dataclasses import dataclass, field
from typing import List, Optional

from storage.parquet_store import ParquetStore
from config.settings import Settings
from utils.time_utils import utc8_now, interval_to_ms, UTC8
from utils.logger import get_logger

logger = get_logger("DataValidator")


@dataclass
class ValidationResult:
    data_type: str
    check_name: str
    passed: bool
    message: str
    severity: str  # "critical", "error", "warning", "info"
    details: dict = field(default_factory=dict)


class DataValidator:
    def __init__(self, store: ParquetStore, settings: Settings):
        self.store = store
        self.settings = settings

    def validate_all(self) -> List[ValidationResult]:
        results = []

        for tf in self.settings.data.kline_timeframes:
            results.extend(self.check_kline_gaps(tf))
            results.extend(self.check_kline_ohlc(tf))

        for dt in ["funding_rate", "open_interest", "agg_trades", "liquidations", "whale_tracking"]:
            results.append(self.check_null_values(dt))
            results.append(self.check_duplicates(dt))

        results.append(self.check_oi_freshness())
        results.append(self.check_liquidation_gaps())

        return results

    def check_kline_gaps(self, timeframe: str) -> List[ValidationResult]:
        results = []
        data_type = f"klines/{timeframe}"
        df = self.store.read(data_type)

        if df.empty:
            results.append(ValidationResult(
                data_type=data_type, check_name="gap_detection",
                passed=False, message=f"No data for {data_type}",
                severity="error"
            ))
            return results

        df["timestamp"] = pd.to_datetime(df["timestamp"])
        df = df.sort_values("timestamp")
        diffs = df["timestamp"].diff().dropna()

        expected = pd.Timedelta(milliseconds=interval_to_ms(timeframe))
        gaps = diffs[diffs > expected * 3]

        if gaps.empty:
            results.append(ValidationResult(
                data_type=data_type, check_name="gap_detection",
                passed=True, message=f"No gaps detected in {len(df)} candles",
                severity="info"
            ))
        else:
            gap_details = []
            for idx in gaps.index:
                prev_ts = df.loc[idx - 1, "timestamp"] if idx > 0 else None
                curr_ts = df.loc[idx, "timestamp"]
                gap_details.append({
                    "from": str(prev_ts),
                    "to": str(curr_ts),
                    "gap": str(gaps[idx]),
                })

            results.append(ValidationResult(
                data_type=data_type, check_name="gap_detection",
                passed=False,
                message=f"Found {len(gaps)} gaps in {len(df)} candles",
                severity="error",
                details={"gaps": gap_details[:20]},
            ))

        return results

    def check_kline_ohlc(self, timeframe: str) -> List[ValidationResult]:
        results = []
        data_type = f"klines/{timeframe}"
        df = self.store.read(data_type)

        if df.empty:
            return results

        bad_high = df[df["high"] < df[["open", "close"]].max(axis=1)]
        bad_low = df[df["low"] > df[["open", "close"]].min(axis=1)]
        bad_count = len(bad_high) + len(bad_low)

        results.append(ValidationResult(
            data_type=data_type, check_name="ohlc_consistency",
            passed=bad_count == 0,
            message=f"OHLC consistency: {bad_count} violations in {len(df)} candles",
            severity="error" if bad_count > 0 else "info",
            details={"bad_high": len(bad_high), "bad_low": len(bad_low)},
        ))

        return results

    def _get_parquet_files(self, data_type: str) -> list:
        """获取数据类型对应的所有 parquet 文件路径"""
        data_dir = os.path.join(str(self.store.base_dir), data_type)
        if not os.path.isdir(data_dir):
            return []
        return sorted([
            os.path.join(data_dir, f)
            for f in os.listdir(data_dir) if f.endswith(".parquet")
        ])

    def check_null_values(self, data_type: str) -> ValidationResult:
        pq_files = self._get_parquet_files(data_type)
        if not pq_files:
            return ValidationResult(
                data_type=data_type, check_name="null_check",
                passed=True, message=f"No data (skipped)",
                severity="info"
            )

        total_rows = 0
        null_totals = {}
        for f in pq_files:
            df = pd.read_parquet(f)
            total_rows += len(df)
            nulls = df.isnull().sum()
            for col, cnt in nulls.items():
                if cnt > 0:
                    null_totals[col] = null_totals.get(col, 0) + cnt
            del df

        total_nulls = sum(null_totals.values())
        return ValidationResult(
            data_type=data_type, check_name="null_check",
            passed=total_nulls == 0,
            message=f"Null values: {total_nulls} across {total_rows} rows",
            severity="warning" if total_nulls > 0 else "info",
            details=null_totals if total_nulls > 0 else {},
        )

    def check_duplicates(self, data_type: str) -> ValidationResult:
        pq_files = self._get_parquet_files(data_type)
        if not pq_files:
            return ValidationResult(
                data_type=data_type, check_name="duplicate_check",
                passed=True, message="No data (skipped)",
                severity="info"
            )

        total_rows = 0
        total_dupes = 0
        for f in pq_files:
            df = pd.read_parquet(f)
            total_rows += len(df)
            if "timestamp" in df.columns:
                total_dupes += df.duplicated(subset=["timestamp"]).sum()
            else:
                total_dupes += df.duplicated().sum()
            del df

        return ValidationResult(
            data_type=data_type, check_name="duplicate_check",
            passed=total_dupes == 0,
            message=f"Duplicates: {total_dupes} in {total_rows} rows",
            severity="warning" if total_dupes > 0 else "info",
        )

    def check_oi_freshness(self) -> ValidationResult:
        last_ts = self.store.get_last_timestamp("open_interest")

        if last_ts is None:
            return ValidationResult(
                data_type="open_interest", check_name="oi_freshness",
                passed=False, message="No OI data collected yet",
                severity="critical",
            )

        now = utc8_now()
        if hasattr(last_ts, 'tzinfo') and last_ts.tzinfo is None:
            last_ts = last_ts.replace(tzinfo=UTC8)
        age_days = (now - last_ts).total_seconds() / 86400

        if age_days > 28:
            severity = "critical"
            passed = False
            msg = f"OI data is {age_days:.1f} days old - approaching 30-day expiry!"
        elif age_days > 2:
            severity = "warning"
            passed = False
            msg = f"OI data is {age_days:.1f} days old"
        else:
            severity = "info"
            passed = True
            msg = f"OI data is fresh ({age_days:.1f} days old)"

        return ValidationResult(
            data_type="open_interest", check_name="oi_freshness",
            passed=passed, message=msg, severity=severity,
            details={"last_timestamp": str(last_ts), "age_days": round(age_days, 2)},
        )

    def check_liquidation_gaps(self) -> ValidationResult:
        df = self.store.read("liquidations")

        if df.empty:
            return ValidationResult(
                data_type="liquidations", check_name="ws_uptime",
                passed=True, message="No liquidation data yet (WebSocket not started)",
                severity="info",
            )

        df["timestamp"] = pd.to_datetime(df["timestamp"])
        df = df.sort_values("timestamp")
        diffs = df["timestamp"].diff().dropna()

        large_gaps = diffs[diffs > pd.Timedelta(minutes=30)]

        return ValidationResult(
            data_type="liquidations", check_name="ws_uptime",
            passed=len(large_gaps) == 0,
            message=f"Liquidation WS gaps >30min: {len(large_gaps)}",
            severity="warning" if len(large_gaps) > 0 else "info",
            details={"gap_count": len(large_gaps)},
        )
