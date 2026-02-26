from datetime import datetime, timezone, timedelta
import pandas as pd

UTC8 = timezone(timedelta(hours=8))


def ms_to_utc8(timestamp_ms: int) -> datetime:
    return datetime.fromtimestamp(timestamp_ms / 1000, tz=UTC8)


def utc8_to_ms(dt: datetime) -> int:
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=UTC8)
    return int(dt.timestamp() * 1000)


def utc8_now() -> datetime:
    return datetime.now(tz=UTC8)


def parse_date(date_str: str) -> datetime:
    dt = datetime.strptime(date_str, "%Y-%m-%d")
    return dt.replace(tzinfo=UTC8)


def normalize_to_utc8(df: pd.DataFrame, col: str = "timestamp") -> pd.DataFrame:
    if df.empty:
        return df
    df = df.copy()
    if not hasattr(df[col].dtype, 'tz') or df[col].dt.tz is None:
        df[col] = pd.to_datetime(df[col]).dt.tz_localize(UTC8)
    else:
        df[col] = df[col].dt.tz_convert(UTC8)
    return df


def get_partition_key(dt: datetime) -> str:
    return dt.strftime("%Y-%m")


def interval_to_ms(interval: str) -> int:
    units = {"m": 60_000, "h": 3_600_000, "d": 86_400_000, "w": 604_800_000}
    num = int(interval[:-1])
    unit = interval[-1]
    return num * units[unit]
