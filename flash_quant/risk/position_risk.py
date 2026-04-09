"""
单笔风控 + 仓位计算 - FR-030, BR-005, BR-008
"""
from datetime import datetime, timezone
from core.constants import (
    MAX_MARGIN_PER_TRADE, MAX_CONCURRENT_POSITIONS,
    WEEKEND_POSITION_MULTIPLIER, CIRCUIT_CONSECUTIVE_HALF,
    get_leverage_tier, MAX_LEVERAGE,
)


def calculate_position_size(base_margin: float = MAX_MARGIN_PER_TRADE,
                            is_weekend: bool = None,
                            consecutive_losses: int = 0) -> float:
    """
    计算实际仓位大小
    考虑: BR-005 上限 + BR-008 周末减仓 + FR-031 连亏减半

    Returns:
        margin: 实际保证金 (USDT)
    """
    margin = base_margin

    # BR-005: 硬性上限
    margin = min(margin, MAX_MARGIN_PER_TRADE)

    # BR-008: 周末减仓
    if is_weekend is None:
        is_weekend = datetime.now(timezone.utc).weekday() in (5, 6)
    if is_weekend:
        margin *= WEEKEND_POSITION_MULTIPLIER

    # FR-031: 连亏 3 笔减半
    if consecutive_losses >= CIRCUIT_CONSECUTIVE_HALF:
        margin *= 0.5

    return round(margin, 2)


def validate_leverage(symbol: str, requested_leverage: int) -> tuple:
    """
    验证杠杆是否符合 BR-001 分级规则

    Returns:
        (valid: bool, max_allowed: int, tier_name: str)
    """
    tier = get_leverage_tier(symbol)
    max_allowed = tier['max_leverage']

    if requested_leverage > max_allowed:
        return False, max_allowed, tier['tier_name']
    if requested_leverage > MAX_LEVERAGE:
        return False, MAX_LEVERAGE, 'absolute_max'
    if requested_leverage <= 0:
        return False, max_allowed, tier['tier_name']

    return True, max_allowed, tier['tier_name']


def get_stop_loss_price(entry_price: float, direction: str,
                        symbol: str) -> float:
    """
    根据分级杠杆计算止损价格
    BR-001: 不同 tier 有不同止损 ROI
    """
    tier = get_leverage_tier(symbol)
    stop_roi = abs(tier['stop_loss_roi'])
    leverage = tier['max_leverage']

    # 止损价格距离 = |stop_roi| / leverage
    price_distance = stop_roi / leverage

    if direction == 'long':
        return round(entry_price * (1 - price_distance), 8)
    else:
        return round(entry_price * (1 + price_distance), 8)


def get_max_hold_hours(symbol: str, tier_strategy: str) -> float:
    """获取最大持仓时间"""
    lever_tier = get_leverage_tier(symbol)
    return lever_tier.get('max_hold_hours', 20)
