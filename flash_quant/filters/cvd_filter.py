"""
CVD 过滤器 - FR-011
验证价格突破时 CVD (Cumulative Volume Delta) 是否同步
"""
from core.constants import CVD_TOLERANCE, CVD_LOOKBACK


def cvd_filter(price_series: list, cvd_series: list,
               direction: str, lookback: int = CVD_LOOKBACK) -> tuple:
    """
    检查价格创新高/新低时 CVD 是否同步

    Args:
        price_series: 价格序列 (close prices)
        cvd_series: CVD 累积序列
        direction: 'long' | 'short'
        lookback: 回看周期

    Returns:
        (passed: bool, reason: str)
    """
    if not price_series or not cvd_series:
        return False, "empty_data"

    if len(price_series) < lookback or len(cvd_series) < lookback:
        return False, "insufficient_data"

    if direction not in ('long', 'short'):
        return False, f"invalid_direction_{direction}"

    recent_prices = price_series[-lookback:]
    recent_cvd = cvd_series[-lookback:]

    if direction == 'long':
        price_high = max(recent_prices)
        cvd_high = max(recent_cvd)

        if price_high == 0:
            return False, "price_zero"

        price_near_high = price_series[-1] >= price_high * (1 - CVD_TOLERANCE)
        cvd_near_high = cvd_series[-1] >= cvd_high * (1 - CVD_TOLERANCE)

        if not price_near_high:
            return False, "price_not_at_high"
        if not cvd_near_high:
            return False, "cvd_divergence"
        return True, "ok"

    else:  # short
        price_low = min(recent_prices)
        cvd_low = min(recent_cvd)

        if price_low == 0:
            return False, "price_zero"

        # 对于 short, 价格要在低点附近 (允许略高于低点)
        price_near_low = price_series[-1] <= price_low * (1 + CVD_TOLERANCE)
        # CVD 低点可能是负数, 用绝对值比较
        cvd_range = max(recent_cvd) - min(recent_cvd)
        if cvd_range == 0:
            cvd_near_low = True
        else:
            cvd_near_low = (cvd_series[-1] - cvd_low) / cvd_range <= CVD_TOLERANCE

        if not price_near_low:
            return False, "price_not_at_low"
        if not cvd_near_low:
            return False, "cvd_divergence"
        return True, "ok"
