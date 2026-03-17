import logging
from typing import List

from src.core.models import Kline
from src.core.enums import MarketRegime
from src.indicators.trend import ema, adx, ema_alignment
from src.indicators.volatility import atrp, bollinger

logger = logging.getLogger(__name__)

# 策略路由表
STRATEGY_ROUTING = {
    MarketRegime.STRONG_TREND_UP: {
        "strategies": ["trend_follow", "pullback_breakout"],
        "direction_bias": "long",
        "position_scale": 1.0,
        "max_positions": 4,
    },
    MarketRegime.WEAK_TREND_UP: {
        "strategies": ["trend_follow", "pullback_breakout", "volatility_breakout"],
        "direction_bias": "long",
        "position_scale": 0.7,
        "max_positions": 3,
    },
    MarketRegime.RANGING: {
        "strategies": ["mean_reversion", "volatility_breakout", "funding_arbitrage"],
        "direction_bias": "both",
        "position_scale": 0.5,
        "max_positions": 3,
    },
    MarketRegime.WEAK_TREND_DOWN: {
        "strategies": ["trend_follow", "pullback_breakout", "volatility_breakout"],
        "direction_bias": "short",
        "position_scale": 0.7,
        "max_positions": 3,
    },
    MarketRegime.STRONG_TREND_DOWN: {
        "strategies": ["trend_follow", "pullback_breakout"],
        "direction_bias": "short",
        "position_scale": 1.0,
        "max_positions": 4,
    },
    MarketRegime.EXTREME_VOLATILE: {
        "strategies": ["funding_arbitrage"],
        "direction_bias": "both",
        "position_scale": 0.2,
        "max_positions": 1,
    },
}

# Phase 1 默认路由
DEFAULT_ROUTING = {
    "strategies": ["trend_follow", "pullback_breakout"],
    "direction_bias": "both",
    "position_scale": 1.0,
    "max_positions": 3,
}


class RegimeDetector:
    """
    基于 BTC 1H+4H 数据判断市场状态
    投票机制：ADX方向性 + EMA排列 + 布林带 + 波动率
    """

    def detect(self, btc_1h: List[Kline], btc_4h: List[Kline]) -> MarketRegime:
        if len(btc_1h) < 200 or len(btc_4h) < 50:
            return MarketRegime.RANGING

        up_votes = 0
        down_votes = 0

        # 1. ADX + DI 方向性 (1H)
        adx_val = adx(btc_1h, 14)
        if adx_val > 25:
            align_1h = ema_alignment(btc_1h)
            if align_1h == 'bullish':
                up_votes += 2
            elif align_1h == 'bearish':
                down_votes += 2

        # 2. EMA 排列一致性 (1H + 4H)
        align_1h = ema_alignment(btc_1h)
        align_4h = ema_alignment(btc_4h)
        if align_1h == 'bullish':
            up_votes += 1
        elif align_1h == 'bearish':
            down_votes += 1
        if align_4h == 'bullish':
            up_votes += 1
        elif align_4h == 'bearish':
            down_votes += 1

        # 3. 布林带状态 (1H)
        upper, mid, lower = bollinger(btc_1h)
        close = btc_1h[-1].close
        if close > upper:
            up_votes += 1
        elif close < lower:
            down_votes += 1

        # 4. 波动率极端检测
        vol = atrp(btc_1h, 14)
        is_extreme = vol > 0.03  # 3% ATR 为极端

        # 综合投票
        if is_extreme and abs(up_votes - down_votes) <= 1:
            return MarketRegime.EXTREME_VOLATILE
        if up_votes >= 4:
            return MarketRegime.STRONG_TREND_UP
        if up_votes >= 2 and up_votes > down_votes:
            return MarketRegime.WEAK_TREND_UP
        if down_votes >= 4:
            return MarketRegime.STRONG_TREND_DOWN
        if down_votes >= 2 and down_votes > up_votes:
            return MarketRegime.WEAK_TREND_DOWN
        return MarketRegime.RANGING
