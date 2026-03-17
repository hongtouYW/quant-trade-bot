import logging
from typing import Dict, List, Optional

from src.core.models import Signal, Position
from src.core.enums import Direction
from src.exchange.base import ExchangeClient

logger = logging.getLogger(__name__)


class OrderManager:
    """订单生命周期管理"""

    def __init__(self, exchange: ExchangeClient):
        self._exchange = exchange
        self._active_orders: Dict[str, dict] = {}  # order_id -> order_info

    async def place_stop_loss(self, position: Position) -> Optional[str]:
        """挂止损单"""
        side = 'sell' if position.direction == Direction.LONG else 'buy'
        try:
            order = await self._exchange.place_stop_order(
                position.symbol, side, position.quantity, position.stop_loss)
            order_id = order['id']
            self._active_orders[order_id] = {
                'type': 'stop_loss',
                'position_id': position.id,
                'symbol': position.symbol,
            }
            return order_id
        except Exception as e:
            logger.error(f"place_stop_loss {position.symbol}: {e}")
            return None

    async def place_take_profit(self, position: Position,
                                 tp_price: float, quantity: float) -> Optional[str]:
        """挂止盈单"""
        side = 'sell' if position.direction == Direction.LONG else 'buy'
        try:
            order = await self._exchange.place_tp_order(
                position.symbol, side, quantity, tp_price)
            order_id = order['id']
            self._active_orders[order_id] = {
                'type': 'take_profit',
                'position_id': position.id,
                'symbol': position.symbol,
            }
            return order_id
        except Exception as e:
            logger.error(f"place_take_profit {position.symbol}: {e}")
            return None

    async def update_stop_loss(self, position: Position,
                                old_order_id: str, new_price: float) -> Optional[str]:
        """修改止损单: 取消旧单 → 挂新单"""
        if old_order_id:
            await self._exchange.cancel_order(position.symbol, old_order_id)
            self._active_orders.pop(old_order_id, None)

        position.stop_loss = new_price
        return await self.place_stop_loss(position)

    async def cancel_all_for_position(self, position: Position):
        """取消某持仓的所有挂单"""
        to_cancel = [oid for oid, info in self._active_orders.items()
                     if info.get('position_id') == position.id]
        for oid in to_cancel:
            info = self._active_orders[oid]
            await self._exchange.cancel_order(info['symbol'], oid)
            self._active_orders.pop(oid, None)

    async def market_close(self, position: Position):
        """市价平仓"""
        side = 'sell' if position.direction == Direction.LONG else 'buy'
        qty = position.quantity * position.remaining_pct
        await self._exchange.place_market_order(position.symbol, side, qty)
        await self.cancel_all_for_position(position)
