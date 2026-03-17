import time
from datetime import datetime
from typing import Optional


def ts_to_datetime(timestamp_ms: int) -> datetime:
    """毫秒时间戳转 datetime"""
    return datetime.utcfromtimestamp(timestamp_ms / 1000)


def datetime_to_ts(dt: datetime) -> int:
    """datetime 转毫秒时间戳"""
    return int(dt.timestamp() * 1000)


def now_ts() -> int:
    """当前毫秒时间戳"""
    return int(time.time() * 1000)


def format_price(price: float, precision: int = 4) -> str:
    return f"{price:.{precision}f}"


def format_pct(value: float) -> str:
    return f"{value*100:+.2f}%"


def safe_divide(a: float, b: float, default: float = 0.0) -> float:
    return a / b if b != 0 else default


def clamp(value: float, min_val: float, max_val: float) -> float:
    return max(min_val, min(max_val, value))
