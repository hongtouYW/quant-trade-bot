from abc import ABC, abstractmethod
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction


class BaseStrategy(ABC):
    """所有策略的统一接口"""

    name: str

    @abstractmethod
    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:
        """检测入场信号，返回 Signal 或 None"""
        pass
