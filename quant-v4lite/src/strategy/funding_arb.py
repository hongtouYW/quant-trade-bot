import logging
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.indicators.momentum import rsi
from src.indicators.volatility import atr
from .base import BaseStrategy

logger = logging.getLogger(__name__)


class FundingArbitrageStrategy(BaseStrategy):
    """
    资金费率套利策略 (Phase 3)
    |费率| > 0.1%, 距结算 < 2h
    费率正 + RSI>55 → 做空; 费率负 + RSI<45 → 做多
    """

    name = "funding_arbitrage"

    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict,
                     funding_rate: float = 0.0,
                     hours_to_funding: float = 99) -> Optional[Signal]:

        if hours_to_funding > 2:
            return None

        min_rate = config.get('strategies', {}).get(
            'funding_arbitrage', {}).get('min_funding_rate', 0.001)

        if abs(funding_rate) < min_rate:
            return None

        rsi_val = rsi(klines_15m, 14)

        if funding_rate > 0 and rsi_val > 55:
            sig_direction = Direction.SHORT
        elif funding_rate < 0 and rsi_val < 45:
            sig_direction = Direction.LONG
        else:
            return None

        if sig_direction != direction:
            return None

        entry_price = klines_15m[-1].close
        atr_val = atr(klines_15m, 14)
        stop_loss = (entry_price - atr_val * 0.5 if sig_direction == Direction.LONG
                     else entry_price + atr_val * 0.5)

        return Signal(
            symbol=symbol,
            direction=sig_direction,
            strategy=SignalStrategy.FUNDING_ARBITRAGE,
            entry_price=entry_price,
            stop_loss=stop_loss,
            take_profits=[],  # 结算后平仓
            risk_reward=None,
            confidence=0.60,
            hold_until_funding=True,
        )
