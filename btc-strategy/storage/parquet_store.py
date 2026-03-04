import pandas as pd
import pyarrow as pa
import pyarrow.parquet as pq
from pathlib import Path
from datetime import datetime
from typing import Optional
from utils.logger import get_logger
from utils.time_utils import get_partition_key, UTC8

logger = get_logger("parquet_store")


class ParquetStore:
    def __init__(self, base_dir: Path):
        self.base_dir = Path(base_dir)

    def _get_dir(self, data_type: str) -> Path:
        d = self.base_dir / data_type
        d.mkdir(parents=True, exist_ok=True)
        return d

    def _partition_path(self, data_type: str, partition_key: str) -> Path:
        return self._get_dir(data_type) / f"{partition_key}.parquet"

    def write(self, data_type: str, df: pd.DataFrame,
              partition_col: str = "timestamp",
              primary_key: Optional[list] = None) -> int:
        if df.empty:
            return 0

        df = df.copy()
        if partition_col in df.columns:
            df[partition_col] = pd.to_datetime(df[partition_col])

        total_written = 0
        df["_partition"] = df[partition_col].apply(
            lambda x: x.strftime("%Y-%m") if pd.notna(x) else "unknown"
        )

        for key, group in df.groupby("_partition"):
            group = group.drop(columns=["_partition"])
            path = self._partition_path(data_type, key)

            if path.exists():
                existing = pd.read_parquet(path)
                combined = pd.concat([existing, group], ignore_index=True)
            else:
                combined = group

            if primary_key:
                before = len(combined)
                combined = combined.drop_duplicates(subset=primary_key, keep="last")
                dupes = before - len(combined)
                if dupes > 0:
                    logger.debug(f"{data_type}/{key}: removed {dupes} duplicates")

            if partition_col in combined.columns:
                combined = combined.sort_values(partition_col).reset_index(drop=True)

            table = pa.Table.from_pandas(combined, preserve_index=False)
            pq.write_table(table, path, compression="snappy")
            total_written += len(group)
            logger.info(f"Wrote {len(group)} rows to {data_type}/{key}.parquet (total: {len(combined)})")

        return total_written

    def read(self, data_type: str,
             start_time: Optional[datetime] = None,
             end_time: Optional[datetime] = None) -> pd.DataFrame:
        data_dir = self._get_dir(data_type)
        files = sorted(data_dir.glob("*.parquet"))

        if not files:
            return pd.DataFrame()

        dfs = []
        for f in files:
            try:
                df = pd.read_parquet(f)
                dfs.append(df)
            except Exception as e:
                logger.warning(f"Failed to read {f}: {e}")

        if not dfs:
            return pd.DataFrame()

        result = pd.concat(dfs, ignore_index=True)

        if "timestamp" in result.columns:
            result["timestamp"] = pd.to_datetime(result["timestamp"])
            if start_time:
                start_ts = pd.Timestamp(start_time)
                if start_ts.tzinfo is None:
                    start_ts = start_ts.tz_localize(UTC8)
                result = result[result["timestamp"] >= start_ts]
            if end_time:
                end_ts = pd.Timestamp(end_time)
                if end_ts.tzinfo is None:
                    end_ts = end_ts.tz_localize(UTC8)
                result = result[result["timestamp"] <= end_ts]

        return result.reset_index(drop=True)

    def get_last_timestamp(self, data_type: str) -> Optional[datetime]:
        data_dir = self._get_dir(data_type)
        files = sorted(data_dir.glob("*.parquet"), reverse=True)

        for f in files:
            try:
                df = pd.read_parquet(f, columns=["timestamp"])
                if not df.empty:
                    ts = pd.to_datetime(df["timestamp"]).max()
                    if pd.notna(ts):
                        return ts.to_pydatetime()
            except Exception:
                continue
        return None

    def get_data_summary(self, data_type: str) -> dict:
        data_dir = self._get_dir(data_type)
        files = sorted(data_dir.glob("*.parquet"))

        if not files:
            return {"rows": 0, "from": None, "to": None, "file_count": 0, "size_mb": 0}

        total_rows = 0
        total_size = 0

        for f in files:
            try:
                total_size += f.stat().st_size
                meta = pq.read_metadata(f)
                total_rows += meta.num_rows
            except Exception:
                continue

        # 用文件名推断时间范围（文件名格式: YYYY-MM.parquet）
        first_name = files[0].stem   # e.g. "2025-03"
        last_name = files[-1].stem   # e.g. "2026-02"

        # 尝试从首文件读第一行、尾文件读最后一行获取精确时间
        min_ts = first_name
        max_ts = last_name
        try:
            pf = pq.ParquetFile(files[0])
            batch = next(pf.iter_batches(batch_size=1, columns=["timestamp"]))
            min_ts = str(pd.to_datetime(batch.column("timestamp")[0].as_py()))
        except Exception:
            pass
        try:
            pf = pq.ParquetFile(files[-1])
            # 读最后一批
            last_batch = None
            for batch in pf.iter_batches(batch_size=1000, columns=["timestamp"]):
                last_batch = batch
            if last_batch and len(last_batch) > 0:
                max_ts = str(pd.to_datetime(last_batch.column("timestamp")[-1].as_py()))
        except Exception:
            pass

        return {
            "rows": total_rows,
            "from": min_ts,
            "to": max_ts,
            "file_count": len(files),
            "size_mb": round(total_size / 1024 / 1024, 2),
        }
