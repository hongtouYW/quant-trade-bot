class TradingError(Exception):
    """Base exception for trading system"""
    pass


class ExchangeError(TradingError):
    """Exchange API related errors"""
    pass


class RiskLimitError(TradingError):
    """Risk control limit reached"""
    pass


class InsufficientBalanceError(TradingError):
    """Not enough balance for trade"""
    pass


class OrderError(TradingError):
    """Order placement/management error"""
    pass


class ConfigError(TradingError):
    """Configuration error"""
    pass


class DataError(TradingError):
    """Market data error"""
    pass
