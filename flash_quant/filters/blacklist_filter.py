"""
黑名单过滤器 - BR-002, BR-003
过滤 Tier D 垃圾币 + 上市不足 7 天的新币
"""
from datetime import datetime, timezone
from core.constants import TIER_D_VOLUME_THRESHOLD, NEW_LISTING_DAYS


def is_tradable(symbol: str, volume_24h: float,
                listing_date: datetime = None) -> tuple:
    """
    检查币种是否可交易

    Args:
        symbol: 交易对 (如 'BTCUSDT')
        volume_24h: 24h 成交量 (USD)
        listing_date: 上市时间 (可选)

    Returns:
        (passed: bool, reason: str)
    """
    if not symbol:
        return False, "empty_symbol"

    # BR-002: Tier D 黑名单
    if volume_24h is not None and volume_24h < TIER_D_VOLUME_THRESHOLD:
        return False, f"tier_d_volume_{volume_24h:.0f}"

    # BR-003: 新币黑名单
    if listing_date is not None:
        now = datetime.now(timezone.utc)
        if listing_date.tzinfo is None:
            listing_date = listing_date.replace(tzinfo=timezone.utc)
        days_since = (now - listing_date).days
        if days_since < NEW_LISTING_DAYS:
            return False, f"new_listing_{days_since}d"

    return True, "ok"
