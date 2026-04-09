"""
Flash Quant - 自定义异常
"""


class FlashQuantError(Exception):
    """基类异常"""
    pass


class InvalidKlineError(FlashQuantError):
    """K线数据无效"""
    pass


class RiskRejectedError(FlashQuantError):
    """风控拒绝"""
    def __init__(self, reason: str):
        self.reason = reason
        super().__init__(f"Risk rejected: {reason}")


class ExecutorError(FlashQuantError):
    """下单执行错误"""
    pass


class CircuitBreakerActiveError(FlashQuantError):
    """断路器激活"""
    def __init__(self, breaker_type: str, expires_at=None):
        self.breaker_type = breaker_type
        self.expires_at = expires_at
        super().__init__(f"Circuit breaker active: {breaker_type}")


class WebSocketError(FlashQuantError):
    """WebSocket 连接错误"""
    pass


class ConfigError(FlashQuantError):
    """配置错误"""
    pass
