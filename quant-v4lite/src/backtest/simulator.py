"""
模拟执行器
为回测引擎提供模拟的订单执行，包含滑点和手续费模拟
"""
import logging
from dataclasses import dataclass
from typing import Optional, Tuple

from src.core.enums import Direction, FillType

logger = logging.getLogger(__name__)


@dataclass
class SimulatedFill:
    """模拟成交结果"""
    price: float
    quantity: float
    fee: float
    fill_type: FillType
    slippage: float


class BacktestSimulator:
    """回测模拟执行器"""

    def __init__(self, config: dict):
        self._taker_fee = config.get('execution', {}).get('fee_rate', 0.0004)
        self._maker_fee = config.get('execution', {}).get('maker_fee_rate', 0.0002)
        self._slippage_pct = config.get('execution', {}).get('slippage_pct', 0.00015)

    def simulate_entry(self, direction: Direction, price: float,
                       quantity: float, fill_type: FillType = FillType.MARKET) -> SimulatedFill:
        """模拟开仓成交"""
        slippage = price * self._slippage_pct

        if fill_type == FillType.MAKER:
            # Maker: 无滑点，低手续费
            fill_price = price
            fee = price * quantity * self._maker_fee
            actual_slippage = 0
        else:
            # Taker/Market: 有滑点
            if direction == Direction.LONG:
                fill_price = price + slippage
            else:
                fill_price = price - slippage
            fee = fill_price * quantity * self._taker_fee
            actual_slippage = slippage

        return SimulatedFill(
            price=fill_price,
            quantity=quantity,
            fee=fee,
            fill_type=fill_type,
            slippage=actual_slippage,
        )

    def simulate_exit(self, direction: Direction, price: float,
                      quantity: float, is_stop: bool = False) -> SimulatedFill:
        """模拟平仓成交"""
        slippage = price * self._slippage_pct

        if is_stop:
            # 止损单: 更大的滑点
            slippage *= 2

        if direction == Direction.LONG:
            # 多单平仓 = 卖出，价格向下滑
            fill_price = price - slippage
        else:
            # 空单平仓 = 买入，价格向上滑
            fill_price = price + slippage

        fee = fill_price * quantity * self._taker_fee

        return SimulatedFill(
            price=fill_price,
            quantity=quantity,
            fee=fee,
            fill_type=FillType.MARKET,
            slippage=slippage,
        )

    def calc_pnl(self, direction: Direction, entry_price: float,
                 exit_price: float, quantity: float,
                 entry_fee: float, exit_fee: float) -> float:
        """计算盈亏（扣费后）"""
        if direction == Direction.LONG:
            raw_pnl = (exit_price - entry_price) * quantity
        else:
            raw_pnl = (entry_price - exit_price) * quantity

        return raw_pnl - entry_fee - exit_fee
