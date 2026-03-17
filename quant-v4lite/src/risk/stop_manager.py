import logging
from typing import Dict

from src.core.models import Signal, Position
from src.core.enums import Direction

logger = logging.getLogger(__name__)


class StopManager:
    """三阶段止损 + 分批止盈"""

    def __init__(self, config: dict):
        self._exec = config.get('execution', {})

    def initial_orders(self, signal: Signal) -> dict:
        """返回开仓时需要挂的止损和止盈订单参数"""
        orders = {
            'stop_loss': {
                'price': signal.stop_loss,
                'side': 'sell' if signal.direction == Direction.LONG else 'buy',
            },
            'take_profits': [],
        }
        for tp_price, tp_pct in signal.take_profits:
            orders['take_profits'].append({
                'price': tp_price,
                'close_pct': tp_pct,
                'side': 'sell' if signal.direction == Direction.LONG else 'buy',
            })
        return orders

    def update(self, position: Position, current_price: float) -> float:
        """
        根据当前价格更新止损价
        返回新的止损价
        """
        r = position.current_r(current_price)
        current_stop = position.stop_loss
        entry = position.entry_price

        # 更新最优价
        if position.direction == Direction.LONG:
            position.best_price = max(position.best_price, current_price)
        else:
            position.best_price = min(position.best_price, current_price)

        breakeven_r = self._exec.get('breakeven_after_r', 1.0)
        trailing_r = self._exec.get('trailing_activate_r', 1.5)
        trailing_lock = self._exec.get('trailing_lock_pct', 0.60)

        # 阶段1: 盈利 < 1R → 不动
        if r < breakeven_r:
            return current_stop

        # 阶段2: 盈利 >= 1R → 保本
        fee_buffer = entry * 0.001  # 0.1% 手续费缓冲
        if position.direction == Direction.LONG:
            breakeven_stop = entry + fee_buffer
        else:
            breakeven_stop = entry - fee_buffer

        # 阶段3: 盈利 >= 1.5R → 移动止盈
        if r >= trailing_r and self._exec.get('trailing_enabled', True):
            if position.direction == Direction.LONG:
                trail_stop = entry + (position.best_price - entry) * trailing_lock
                new_stop = max(current_stop, trail_stop, breakeven_stop)
            else:
                trail_stop = entry - (entry - position.best_price) * trailing_lock
                new_stop = min(current_stop, trail_stop, breakeven_stop)
            return new_stop

        # 仅保本
        if position.direction == Direction.LONG:
            return max(current_stop, breakeven_stop)
        else:
            return min(current_stop, breakeven_stop)

    def check_time_stop(self, position: Position, current_price: float) -> bool:
        """检查时间止损: 持仓 > 60min 且盈利 < 0.3R"""
        max_minutes = self._exec.get('time_stop_minutes', 60)
        min_r = self._exec.get('time_stop_min_r', 0.3)

        if position.holding_minutes > max_minutes:
            r = position.current_r(current_price)
            if r < min_r:
                return True
        return False

    def check_tp1(self, position: Position, current_price: float) -> bool:
        """检查 TP1 是否命中"""
        if position.tp1_hit or not position.take_profits:
            return False

        tp1_price = position.take_profits[0][0]
        if position.direction == Direction.LONG:
            return current_price >= tp1_price
        else:
            return current_price <= tp1_price
