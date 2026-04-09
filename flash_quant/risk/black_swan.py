"""
黑天鹅熔断器 - FR-033
监控 Tier A 币种 5min 异常波动
"""
from datetime import datetime, timezone, timedelta
from core.constants import BLACK_SWAN_VOLATILITY_THRESHOLD, BLACK_SWAN_PAUSE_MINUTES
from core.logger import get_logger

logger = get_logger('black_swan')


class BlackSwanMonitor:
    """
    检测 5min 异常波动, 触发全系统熔断
    """

    def __init__(self):
        self._active = False
        self._expires_at = None
        self._trigger_event = None

    def check_volatility(self, symbol: str, high: float, low: float) -> bool:
        """
        检查单根 K线的波幅是否触发黑天鹅

        Args:
            symbol: 币种
            high: K线最高价
            low: K线最低价

        Returns:
            True if triggered
        """
        if low <= 0 or high <= 0:
            return False

        volatility = (high - low) / low

        if volatility >= BLACK_SWAN_VOLATILITY_THRESHOLD:
            self._trigger(symbol, volatility)
            return True

        return False

    def is_active(self) -> bool:
        """检查熔断是否激活"""
        if not self._active:
            return False

        now = datetime.now(timezone.utc)
        if self._expires_at and now >= self._expires_at:
            self._active = False
            self._trigger_event = None
            logger.info("black_swan.expired")
            return False

        return True

    def get_status(self) -> dict:
        """获取当前状态"""
        return {
            'active': self.is_active(),
            'expires_at': self._expires_at.isoformat() if self._expires_at else None,
            'trigger_event': self._trigger_event,
        }

    def force_deactivate(self):
        """手动解除熔断"""
        self._active = False
        self._expires_at = None
        self._trigger_event = None
        logger.info("black_swan.force_deactivated")

    def _trigger(self, symbol: str, volatility: float):
        """触发熔断"""
        now = datetime.now(timezone.utc)
        self._active = True
        self._expires_at = now + timedelta(minutes=BLACK_SWAN_PAUSE_MINUTES)
        self._trigger_event = {
            'symbol': symbol,
            'volatility': round(volatility, 4),
            'triggered_at': now.isoformat(),
        }
        logger.critical("black_swan.triggered",
                       symbol=symbol, volatility=f"{volatility:.2%}",
                       pause_minutes=BLACK_SWAN_PAUSE_MINUTES)


# 全局实例
black_swan_monitor = BlackSwanMonitor()
