import asyncio
import logging
from typing import Tuple, Optional

from src.core.enums import FillType
from src.core.exceptions import ExchangeError
from .base import ExchangeClient

logger = logging.getLogger(__name__)


class SmartOrderRouter:
    """
    智能下单引擎 [V4]
    Layer 1: Maker → Layer 2: 追价 → Layer 3: 市价
    """

    def __init__(self, exchange: ExchangeClient, config: dict):
        self._exchange = exchange
        so_cfg = config.get('smart_order', {})
        self._enabled = so_cfg.get('enabled', False)
        self._maker_wait = so_cfg.get('maker_wait_sec', 3)
        self._chase_wait = so_cfg.get('chase_wait_sec', 3)
        self._max_slippage = so_cfg.get('max_slippage_pct', 0.05) / 100
        self._post_only = so_cfg.get('post_only', True)
        self._urgent_types = so_cfg.get('urgent_types', ['stop_loss'])

        # 统计
        self._stats = {'maker': 0, 'chase': 0, 'market': 0}

    async def execute_entry(self, symbol: str, side: str, quantity: float,
                            urgency: str = 'normal') -> Tuple[Optional[dict], FillType]:
        """入场下单"""

        if not self._enabled or urgency in self._urgent_types:
            result = await self._exchange.place_market_order(symbol, side, quantity)
            self._stats['market'] += 1
            return result, FillType.MARKET

        # Layer 1: Maker
        try:
            book = await self._exchange.fetch_orderbook(symbol)
            if side == 'buy':
                maker_price = book.best_bid + book.bids[0].price * 0.00001  # +1 tick
            else:
                maker_price = book.best_ask - book.asks[0].price * 0.00001

            order = await self._exchange.place_limit_order(
                symbol, side, quantity, maker_price, post_only=self._post_only)

            await asyncio.sleep(self._maker_wait)

            status = await self._exchange.get_order_status(symbol, order['id'])
            if status.get('status') == 'closed':
                self._stats['maker'] += 1
                return status, FillType.MAKER

            await self._exchange.cancel_order(symbol, order['id'])
        except Exception as e:
            logger.warning(f"Maker failed {symbol}: {e}")

        # Layer 2: 追价
        try:
            book = await self._exchange.fetch_orderbook(symbol)
            if side == 'buy':
                chase_price = book.best_ask
                slippage = (chase_price - book.best_bid) / book.best_bid
            else:
                chase_price = book.best_bid
                slippage = (book.best_ask - chase_price) / chase_price

            if slippage > self._max_slippage:
                logger.warning(f"Slippage too high {symbol}: {slippage*100:.3f}%")
                return None, FillType.MARKET

            order = await self._exchange.place_limit_order(
                symbol, side, quantity, chase_price)

            await asyncio.sleep(self._chase_wait)

            status = await self._exchange.get_order_status(symbol, order['id'])
            if status.get('status') == 'closed':
                self._stats['chase'] += 1
                return status, FillType.CHASE

            await self._exchange.cancel_order(symbol, order['id'])
        except Exception as e:
            logger.warning(f"Chase failed {symbol}: {e}")

        # Layer 3: 市价
        result = await self._exchange.place_market_order(symbol, side, quantity)
        self._stats['market'] += 1
        return result, FillType.MARKET

    async def execute_exit(self, symbol: str, side: str, quantity: float,
                           reason: str = 'tp') -> Tuple[dict, FillType]:
        """平仓下单"""
        if reason in ('stop_loss', 'liquidation_risk'):
            result = await self._exchange.place_market_order(symbol, side, quantity)
            return result, FillType.MARKET

        # 止盈可以尝试 maker
        if self._enabled:
            try:
                result, fill = await self.execute_entry(symbol, side, quantity, 'normal')
                if result:
                    return result, fill
            except Exception:
                pass

        result = await self._exchange.place_market_order(symbol, side, quantity)
        return result, FillType.MARKET

    @property
    def stats(self) -> dict:
        total = sum(self._stats.values())
        if total == 0:
            return self._stats
        return {
            **self._stats,
            'maker_rate': self._stats['maker'] / total,
        }
