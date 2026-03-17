import logging
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.indicators.trend import adx
from src.indicators.momentum import rsi
from src.indicators.volatility import atr, bollinger
from .base import BaseStrategy

logger = logging.getLogger(__name__)


class MeanReversionStrategy(BaseStrategy):
    """
    均值回归策略 (Phase 2)
    前提: ADX < 25 (震荡市)
    15m: RSI超卖/超买 + 布林带回归
    """

    name = "mean_reversion"

    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:

        if len(klines_1h) < 50 or len(klines_15m) < 30:
            return None

        exec_cfg = config.get('execution', {})

        # 前提: 震荡市 ADX < 25
        adx_val = adx(klines_1h, 14)
        if adx_val >= 25:
            return None

        # 15m 指标
        rsi_val = rsi(klines_15m, 14)
        upper, mid, lower = bollinger(klines_15m, 20, 2.0)
        prev = klines_15m[-2]
        curr = klines_15m[-1]

        if direction == Direction.LONG:
            if rsi_val >= 30:
                return None
            if prev.close >= lower:
                return None
            if curr.close <= lower or not curr.is_bullish:
                return None
            # 止盈: 布林中轨
            entry_price = curr.close
            atr_val = atr(klines_15m, 14)
            stop_loss = entry_price - atr_val * 0.8
            tp_price = mid
        else:
            if rsi_val <= 70:
                return None
            if prev.close <= upper:
                return None
            if curr.close >= upper or curr.is_bullish:
                return None
            entry_price = curr.close
            atr_val = atr(klines_15m, 14)
            stop_loss = entry_price + atr_val * 0.8
            tp_price = mid

        risk_reward = abs(tp_price - entry_price) / abs(entry_price - stop_loss)
        min_rr = exec_cfg.get('min_risk_reward', 1.8)
        if risk_reward < min_rr:
            return None

        return Signal(
            symbol=symbol,
            direction=direction,
            strategy=SignalStrategy.MEAN_REVERSION,
            entry_price=entry_price,
            stop_loss=stop_loss,
            take_profits=[(tp_price, 1.0)],
            risk_reward=risk_reward,
            confidence=0.70,
        )
