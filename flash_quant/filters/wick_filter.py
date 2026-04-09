"""
反插针过滤器 - FR-010
过滤长影线 K线 (插针/抽水盘)
"""
from core.constants import WICK_BODY_RATIO_MIN
from core.exceptions import InvalidKlineError


def wick_filter(open_price: float, high: float,
                low: float, close: float) -> tuple:
    """
    检查 K线是否健康 (实体占比 >= WICK_BODY_RATIO_MIN)

    Args:
        open_price: 开盘价
        high: 最高价
        low: 最低价
        close: 收盘价

    Returns:
        (passed: bool, body_ratio: float)

    Raises:
        InvalidKlineError: 价格数据无效
    """
    prices = [open_price, high, low, close]

    if any(p is None for p in prices):
        raise InvalidKlineError("Price cannot be None")

    if any(not isinstance(p, (int, float)) for p in prices):
        raise InvalidKlineError("Price must be numeric")

    if any(p <= 0 for p in prices):
        raise InvalidKlineError("Price must be positive")

    if high < low:
        raise InvalidKlineError(f"High ({high}) < Low ({low})")

    if high < max(open_price, close) or low > min(open_price, close):
        raise InvalidKlineError("High/Low inconsistent with Open/Close")

    body = abs(close - open_price)
    upper_wick = high - max(open_price, close)
    lower_wick = min(open_price, close) - low
    total = body + upper_wick + lower_wick

    if total == 0:
        return False, 0.0  # 一字线

    body_ratio = body / total
    passed = body_ratio >= WICK_BODY_RATIO_MIN

    return passed, round(body_ratio, 4)
