import logging
from typing import List, Dict, Optional

import ccxt.async_support as ccxt

from src.core.models import Kline, OrderBook, SymbolInfo
from src.core.exceptions import ExchangeError
from .base import ExchangeClient

logger = logging.getLogger(__name__)


class OKXClient(ExchangeClient):
    """OKX 客户端 — 仅公开数据 (ticker), 用于多交易所共识"""

    def __init__(self):
        self.exchange = ccxt.okx({
            'options': {'defaultType': 'swap'},
            'enableRateLimit': True,
        })

    async def close(self):
        await self.exchange.close()

    # ── 已实现: 市场数据 (ticker) ──

    async def fetch_ticker(self, symbol: str) -> dict:
        try:
            return await self.exchange.fetch_ticker(symbol)
        except Exception as e:
            raise ExchangeError(f"OKX fetch_ticker {symbol}: {e}")

    async def fetch_tickers(self, symbols: List[str]) -> Dict[str, dict]:
        try:
            all_tickers = await self.exchange.fetch_tickers(symbols)
            return {s: all_tickers[s] for s in symbols if s in all_tickers}
        except Exception as e:
            raise ExchangeError(f"OKX fetch_tickers: {e}")

    # ── 未实现: 其余接口 ──

    async def load_markets(self) -> Dict[str, SymbolInfo]:
        raise NotImplementedError("OKXClient: load_markets not implemented")

    async def fetch_klines(self, symbol: str, timeframe: str,
                           limit: int = 100) -> List[Kline]:
        raise NotImplementedError("OKXClient: fetch_klines not implemented")

    async def fetch_orderbook(self, symbol: str, depth: int = 20) -> OrderBook:
        raise NotImplementedError("OKXClient: fetch_orderbook not implemented")

    async def fetch_funding_rate(self, symbol: str) -> float:
        raise NotImplementedError("OKXClient: fetch_funding_rate not implemented")

    async def fetch_open_interest(self, symbol: str) -> float:
        raise NotImplementedError("OKXClient: fetch_open_interest not implemented")

    async def set_leverage(self, symbol: str, leverage: int):
        raise NotImplementedError("OKXClient: set_leverage not implemented")

    async def place_market_order(self, symbol: str, side: str,
                                 quantity: float) -> dict:
        raise NotImplementedError("OKXClient: place_market_order not implemented")

    async def place_limit_order(self, symbol: str, side: str,
                                quantity: float, price: float,
                                post_only: bool = False) -> dict:
        raise NotImplementedError("OKXClient: place_limit_order not implemented")

    async def place_stop_order(self, symbol: str, side: str,
                               quantity: float, stop_price: float) -> dict:
        raise NotImplementedError("OKXClient: place_stop_order not implemented")

    async def place_tp_order(self, symbol: str, side: str,
                             quantity: float, tp_price: float) -> dict:
        raise NotImplementedError("OKXClient: place_tp_order not implemented")

    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        raise NotImplementedError("OKXClient: cancel_order not implemented")

    async def get_order_status(self, symbol: str, order_id: str) -> dict:
        raise NotImplementedError("OKXClient: get_order_status not implemented")

    async def get_position(self, symbol: str) -> Optional[dict]:
        raise NotImplementedError("OKXClient: get_position not implemented")

    async def get_balance(self) -> float:
        raise NotImplementedError("OKXClient: get_balance not implemented")

    async def subscribe_klines(self, symbols: List[str],
                               timeframes: List[str], callback):
        raise NotImplementedError("OKXClient: subscribe_klines not implemented")

    async def subscribe_orderbook(self, symbols: List[str], callback):
        raise NotImplementedError("OKXClient: subscribe_orderbook not implemented")
