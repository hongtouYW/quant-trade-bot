import logging
import numpy as np
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.indicators.volatility import atr, bollinger, bollinger_width
from src.indicators.volume import volume_ratio
from .base import BaseStrategy

logger = logging.getLogger(__name__)


class VolatilityBreakoutStrategy(BaseStrategy):
    """
    波动率突破策略 (Phase 2)
    布林带宽度处于最低15% → 突破上/下轨 + 量比>2
    """

    name = "volatility_breakout"

    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:

        if len(klines_15m) < 50:
            return None

        exec_cfg = config.get('execution', {})

        # 计算近50根的布林带宽度分位
        widths = []
        for i in range(50, len(klines_15m) + 1):
            subset = klines_15m[max(0, i - 20):i]
            if len(subset) >= 20:
                widths.append(bollinger_width(subset))

        if len(widths) < 10:
            return None

        current_width = widths[-1]
        percentile = sum(1 for w in widths if w <= current_width) / len(widths)

        if percentile > 0.15:
            return None

        # 突破判断
        upper, mid, lower = bollinger(klines_15m, 20, 2.0)
        curr = klines_15m[-1]

        if direction == Direction.LONG and curr.close <= upper:
            return None
        if direction == Direction.SHORT and curr.close >= lower:
            return None

        # 量比 > 2.0
        vol_r = volume_ratio(klines_15m, recent=1, baseline=20)
        if vol_r < 2.0:
            return None

        entry_price = curr.close
        atr_val = atr(klines_15m, 14)
        stop_mult = exec_cfg.get('stop_atr_multiple', 1.2)
        tp1_r = exec_cfg.get('tp1_r', 1.5)
        tp2_r = exec_cfg.get('tp2_r', 2.5)

        stop_dist = atr_val * stop_mult

        if direction == Direction.LONG:
            stop_loss = entry_price - stop_dist
            tp1 = entry_price + stop_dist * tp1_r
            tp2 = entry_price + stop_dist * tp2_r
        else:
            stop_loss = entry_price + stop_dist
            tp1 = entry_price - stop_dist * tp1_r
            tp2 = entry_price - stop_dist * tp2_r

        risk_reward = tp1_r
        if risk_reward < exec_cfg.get('min_risk_reward', 1.8):
            return None

        return Signal(
            symbol=symbol,
            direction=direction,
            strategy=SignalStrategy.VOLATILITY_BREAKOUT,
            entry_price=entry_price,
            stop_loss=stop_loss,
            take_profits=[(tp1, 0.5), (tp2, 0.5)],
            risk_reward=risk_reward,
            confidence=0.80,
        )
