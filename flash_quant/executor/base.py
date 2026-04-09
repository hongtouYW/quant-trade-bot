"""
执行器基类
"""
from abc import ABC, abstractmethod


class ExecutorBase(ABC):

    @abstractmethod
    async def open_position(self, symbol: str, direction: str,
                            tier: str, margin: float, leverage: int,
                            stop_loss_roi: float, signal_id: int = None) -> dict:
        """开仓"""
        pass

    @abstractmethod
    async def close_position(self, position_id: int, reason: str) -> dict:
        """平仓"""
        pass

    @abstractmethod
    async def check_positions(self):
        """检查持仓 (止损/止盈/超时)"""
        pass
