"""
资金费率过滤器 - FR-012
过滤极端费率币种 (容易被反向收割)
"""
from core.constants import FUNDING_RATE_MAX


def funding_filter(funding_rate: float) -> tuple:
    """
    检查资金费率是否在安全范围内

    Args:
        funding_rate: 当前资金费率 (如 0.0005 = 0.05%)

    Returns:
        (passed: bool, reason: str)
    """
    if funding_rate is None:
        return False, "funding_rate_none"

    if not isinstance(funding_rate, (int, float)):
        return False, "funding_rate_invalid_type"

    passed = abs(funding_rate) < FUNDING_RATE_MAX

    if not passed:
        if funding_rate > 0:
            return False, f"funding_too_high_{funding_rate:.4f}"
        else:
            return False, f"funding_too_low_{funding_rate:.4f}"

    return True, "ok"
