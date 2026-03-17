import asyncio
import logging
from typing import Dict

from src.core.models import Position
from src.core.enums import Direction
from src.exchange.base import ExchangeClient
from src.risk.stop_manager import StopManager
from src.risk.portfolio import PortfolioManager
from .order_manager import OrderManager

logger = logging.getLogger(__name__)


class PositionMonitor:
    """
    每 15 秒扫描所有持仓:
    更新止损、检查 TP1、时间止损、趋势骤降
    """

    def __init__(self, exchange: ExchangeClient,
                 stop_manager: StopManager,
                 portfolio: PortfolioManager,
                 order_manager: OrderManager):
        self._exchange = exchange
        self._stop_mgr = stop_manager
        self._portfolio = portfolio
        self._order_mgr = order_manager
        self._sl_orders: Dict[str, str] = {}  # position_id -> sl_order_id
        self._running = False

    async def monitor_once(self):
        """单次扫描"""
        positions = list(self._portfolio.positions.values())

        for pos in positions:
            try:
                ticker = await self._exchange.fetch_ticker(pos.symbol)
                current_price = ticker.get('last', 0)
                if current_price <= 0:
                    continue

                # 更新最优价
                if pos.direction == Direction.LONG:
                    pos.best_price = max(pos.best_price, current_price)
                else:
                    pos.best_price = min(pos.best_price, current_price)

                # 更新止损
                new_stop = self._stop_mgr.update(pos, current_price)
                if new_stop != pos.stop_loss:
                    old_oid = self._sl_orders.get(pos.id)
                    new_oid = await self._order_mgr.update_stop_loss(
                        pos, old_oid, new_stop)
                    if new_oid:
                        self._sl_orders[pos.id] = new_oid
                    logger.info(f"SL updated {pos.symbol}: {pos.stop_loss:.4f} → {new_stop:.4f}")

                # TP1 检查
                if self._stop_mgr.check_tp1(pos, current_price):
                    tp_pct = pos.take_profits[0][1]
                    qty = pos.quantity * tp_pct
                    side = 'sell' if pos.direction == Direction.LONG else 'buy'
                    await self._exchange.place_market_order(pos.symbol, side, qty)
                    pos.tp1_hit = True
                    self._portfolio.partial_close(pos.id, tp_pct)
                    logger.info(f"TP1 hit {pos.symbol}, closed {tp_pct*100:.0f}%")

                # 时间止损
                if self._stop_mgr.check_time_stop(pos, current_price):
                    await self._order_mgr.market_close(pos)
                    self._portfolio.close_position(pos.id)
                    self._sl_orders.pop(pos.id, None)
                    logger.info(f"Time stop {pos.symbol}")

            except Exception as e:
                logger.error(f"Monitor error {pos.symbol}: {e}")

    async def monitor_loop(self):
        """持续监控循环"""
        self._running = True
        while self._running:
            await self.monitor_once()
            await asyncio.sleep(15)

    def stop(self):
        self._running = False
