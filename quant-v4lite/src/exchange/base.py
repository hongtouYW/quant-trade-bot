from abc import ABC, abstractmethod
from typing import List, Dict, Optional

from src.core.models import Kline, OrderBook, SymbolInfo


class ExchangeClient(ABC):
    """交易所客户端抽象基类"""

    # ── 市场数据 ──

    @abstractmethod
    async def load_markets(self) -> Dict[str, SymbolInfo]:
        pass

    @abstractmethod
    async def fetch_klines(self, symbol: str, timeframe: str,
                           limit: int = 100) -> List[Kline]:
        pass

    @abstractmethod
    async def fetch_ticker(self, symbol: str) -> dict:
        pass

    @abstractmethod
    async def fetch_tickers(self, symbols: List[str]) -> Dict[str, dict]:
        pass

    @abstractmethod
    async def fetch_orderbook(self, symbol: str, depth: int = 20) -> OrderBook:
        pass

    @abstractmethod
    async def fetch_funding_rate(self, symbol: str) -> float:
        pass

    @abstractmethod
    async def fetch_open_interest(self, symbol: str) -> float:
        pass

    # ── 交易操作 ──

    @abstractmethod
    async def set_leverage(self, symbol: str, leverage: int):
        pass

    @abstractmethod
    async def place_market_order(self, symbol: str, side: str,
                                 quantity: float) -> dict:
        pass

    @abstractmethod
    async def place_limit_order(self, symbol: str, side: str,
                                quantity: float, price: float,
                                post_only: bool = False) -> dict:
        pass

    @abstractmethod
    async def place_stop_order(self, symbol: str, side: str,
                               quantity: float, stop_price: float) -> dict:
        pass

    @abstractmethod
    async def place_tp_order(self, symbol: str, side: str,
                             quantity: float, tp_price: float) -> dict:
        pass

    @abstractmethod
    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        pass

    @abstractmethod
    async def get_order_status(self, symbol: str, order_id: str) -> dict:
        pass

    @abstractmethod
    async def get_position(self, symbol: str) -> Optional[dict]:
        pass

    @abstractmethod
    async def get_balance(self) -> float:
        pass

    # ── WebSocket ──

    @abstractmethod
    async def subscribe_klines(self, symbols: List[str],
                               timeframes: List[str], callback):
        pass

    @abstractmethod
    async def subscribe_orderbook(self, symbols: List[str], callback):
        pass
