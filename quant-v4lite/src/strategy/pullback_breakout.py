import logging
import numpy as np
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.indicators.trend import ema_alignment, adx
from src.indicators.volatility import atr
from src.indicators.volume import volume_ratio
from .base import BaseStrategy

logger = logging.getLogger(__name__)


class PullbackBreakoutStrategy(BaseStrategy):
    """
    箱体突破策略
    1H: EMA 排列 + ADX > 25
    15m: 20根K线窄幅整理(高低差<3%) + 突破 + 放量
    """

    name = "pullback_breakout"

    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:

        if len(klines_1h) < 200 or len(klines_15m) < 30:
            return None

        exec_cfg = config.get('execution', {})
        trend_cfg = config.get('trend_filter', {})

        # 1H 过滤
        align = ema_alignment(klines_1h,
                              trend_cfg.get('ema_fast', 20),
                              trend_cfg.get('ema_mid', 50),
                              trend_cfg.get('ema_slow', 200))

        if direction == Direction.LONG and align != 'bullish':
            return None
        if direction == Direction.SHORT and align != 'bearish':
            return None

        adx_val = adx(klines_1h, trend_cfg.get('adx_period', 14))
        if adx_val < trend_cfg.get('adx_strong', 25):
            return None

        # 15m 箱体检测
        lookback = klines_15m[-21:-1]  # 最近20根（不含当前）
        if len(lookback) < 20:
            return None

        highest = max(k.high for k in lookback)
        lowest = min(k.low for k in lookback)
        mid_price = (highest + lowest) / 2
        if mid_price == 0:
            return None

        box_range = (highest - lowest) / mid_price
        if box_range > 0.03:  # 高低差 > 3% 不算窄幅
            return None

        curr = klines_15m[-1]

        if direction == Direction.LONG:
            if curr.close <= highest:
                return None
        else:
            if curr.close >= lowest:
                return None

        # 成交量确认
        vol_r = volume_ratio(klines_15m, recent=1, baseline=20)
        if vol_r < 1.3:
            return None

        # 止损止盈
        entry_price = curr.close
        atr_val = atr(klines_15m, 14)
        stop_mult = exec_cfg.get('stop_atr_multiple', 1.2)
        tp1_r = exec_cfg.get('tp1_r', 1.5)
        tp2_r = exec_cfg.get('tp2_r', 2.5)
        tp1_pct = exec_cfg.get('tp1_close_pct', 0.5)
        tp2_pct = exec_cfg.get('tp2_close_pct', 0.5)

        stop_dist = atr_val * stop_mult

        if direction == Direction.LONG:
            stop_loss = entry_price - stop_dist
            tp1_price = entry_price + stop_dist * tp1_r
            tp2_price = entry_price + stop_dist * tp2_r
        else:
            stop_loss = entry_price + stop_dist
            tp1_price = entry_price - stop_dist * tp1_r
            tp2_price = entry_price - stop_dist * tp2_r

        risk_reward = tp1_r
        min_rr = exec_cfg.get('min_risk_reward', 1.8)
        if risk_reward < min_rr:
            return None

        return Signal(
            symbol=symbol,
            direction=direction,
            strategy=SignalStrategy.PULLBACK_BREAKOUT,
            entry_price=entry_price,
            stop_loss=stop_loss,
            take_profits=[(tp1_price, tp1_pct), (tp2_price, tp2_pct)],
            risk_reward=risk_reward,
            confidence=0.75,
            trend_score=adx_val,
        )
