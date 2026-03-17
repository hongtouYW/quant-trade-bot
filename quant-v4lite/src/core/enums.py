from enum import Enum


class Direction(Enum):
    LONG = 1
    SHORT = -1


class MarketRegime(Enum):
    STRONG_TREND_UP = "strong_trend_up"
    WEAK_TREND_UP = "weak_trend_up"
    RANGING = "ranging"
    WEAK_TREND_DOWN = "weak_trend_down"
    STRONG_TREND_DOWN = "strong_trend_down"
    EXTREME_VOLATILE = "extreme_volatile"


class OrderType(Enum):
    MARKET = "market"
    LIMIT = "limit"
    STOP_MARKET = "stop_market"
    TAKE_PROFIT_MARKET = "take_profit_market"


class FillType(Enum):
    MAKER = "maker"
    CHASE = "chase"
    MARKET = "market"


class SignalStrategy(Enum):
    TREND_FOLLOW = "trend_follow"
    PULLBACK_BREAKOUT = "pullback_breakout"
    MEAN_REVERSION = "mean_reversion"
    VOLATILITY_BREAKOUT = "volatility_breakout"
    FUNDING_ARBITRAGE = "funding_arbitrage"


class SessionName(Enum):
    ASIA = "asia"
    EUROPE = "europe"
    AMERICA = "america"
