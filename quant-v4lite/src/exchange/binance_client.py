import asyncio
import logging
from typing import List, Dict, Optional

import ccxt.async_support as ccxt

from src.core.models import Kline, OrderBook, OrderBookLevel, SymbolInfo
from src.core.exceptions import ExchangeError
from .base import ExchangeClient

logger = logging.getLogger(__name__)


class BinanceClient(ExchangeClient):

    def __init__(self, api_key: str, api_secret: str):
        self.exchange = ccxt.binance({
            'apiKey': api_key,
            'secret': api_secret,
            'options': {'defaultType': 'future'},
            'enableRateLimit': True,
        })

    async def close(self):
        await self.exchange.close()

    async def load_markets(self) -> Dict[str, SymbolInfo]:
        try:
            markets = await self.exchange.load_markets()
        except Exception as e:
            raise ExchangeError(f"load_markets failed: {e}")
        result = {}
        for symbol, info in markets.items():
            if (info.get('type') == 'swap' and
                    info.get('quote') == 'USDT' and
                    info.get('active')):
                result[symbol] = SymbolInfo(
                    symbol=symbol,
                    base=info['base'],
                    quote=info['quote'],
                    price_precision=info['precision']['price'],
                    qty_precision=info['precision']['amount'],
                    min_qty=info['limits']['amount']['min'] or 0,
                    min_notional=info['limits']['cost']['min'] or 0,
                    tick_size=info['precision']['price'],
                )
        return result

    async def fetch_klines(self, symbol: str, timeframe: str,
                           limit: int = 100) -> List[Kline]:
        try:
            ohlcv = await self.exchange.fetch_ohlcv(symbol, timeframe, limit=limit)
        except Exception as e:
            raise ExchangeError(f"fetch_klines {symbol} {timeframe}: {e}")
        return [
            Kline(
                timestamp=k[0],
                open=k[1], high=k[2], low=k[3], close=k[4],
                volume=k[5],
                quote_volume=k[5] * k[4],
            )
            for k in ohlcv
        ]

    async def fetch_ticker(self, symbol: str) -> dict:
        try:
            return await self.exchange.fetch_ticker(symbol)
        except Exception as e:
            raise ExchangeError(f"fetch_ticker {symbol}: {e}")

    async def fetch_tickers(self, symbols: List[str]) -> Dict[str, dict]:
        try:
            all_tickers = await self.exchange.fetch_tickers(symbols)
            return {s: all_tickers[s] for s in symbols if s in all_tickers}
        except Exception as e:
            raise ExchangeError(f"fetch_tickers: {e}")

    async def fetch_orderbook(self, symbol: str, depth: int = 20) -> OrderBook:
        try:
            book = await self.exchange.fetch_order_book(symbol, limit=depth)
        except Exception as e:
            raise ExchangeError(f"fetch_orderbook {symbol}: {e}")
        return OrderBook(
            symbol=symbol,
            timestamp=book['timestamp'] or 0,
            bids=[OrderBookLevel(b[0], b[1]) for b in book['bids']],
            asks=[OrderBookLevel(a[0], a[1]) for a in book['asks']],
        )

    async def fetch_funding_rate(self, symbol: str) -> float:
        try:
            result = await self.exchange.fetch_funding_rate(symbol)
            return result.get('fundingRate', 0.0)
        except Exception as e:
            logger.warning(f"fetch_funding_rate {symbol}: {e}")
            return 0.0

    async def fetch_open_interest(self, symbol: str) -> float:
        try:
            result = await self.exchange.fetch_open_interest(symbol)
            return result.get('openInterestAmount', 0.0)
        except Exception as e:
            logger.warning(f"fetch_open_interest {symbol}: {e}")
            return 0.0

    async def set_leverage(self, symbol: str, leverage: int):
        try:
            await self.exchange.set_leverage(leverage, symbol,
                                             params={'marginMode': 'isolated'})
        except Exception as e:
            logger.warning(f"set_leverage {symbol} {leverage}x: {e}")

    async def place_market_order(self, symbol: str, side: str,
                                 quantity: float) -> dict:
        try:
            return await self.exchange.create_market_order(symbol, side, quantity)
        except Exception as e:
            raise ExchangeError(f"place_market_order {symbol} {side}: {e}")

    async def place_limit_order(self, symbol: str, side: str,
                                quantity: float, price: float,
                                post_only: bool = False) -> dict:
        params = {}
        if post_only:
            params['timeInForce'] = 'GTX'
        try:
            return await self.exchange.create_limit_order(
                symbol, side, quantity, price, params=params)
        except Exception as e:
            raise ExchangeError(f"place_limit_order {symbol} {side}: {e}")

    async def place_stop_order(self, symbol: str, side: str,
                               quantity: float, stop_price: float) -> dict:
        try:
            return await self.exchange.create_order(
                symbol, 'stop_market', side, quantity,
                params={'stopPrice': stop_price, 'reduceOnly': True})
        except Exception as e:
            raise ExchangeError(f"place_stop_order {symbol}: {e}")

    async def place_tp_order(self, symbol: str, side: str,
                             quantity: float, tp_price: float) -> dict:
        try:
            return await self.exchange.create_order(
                symbol, 'take_profit_market', side, quantity,
                params={'stopPrice': tp_price, 'reduceOnly': True})
        except Exception as e:
            raise ExchangeError(f"place_tp_order {symbol}: {e}")

    async def cancel_order(self, symbol: str, order_id: str) -> bool:
        try:
            await self.exchange.cancel_order(order_id, symbol)
            return True
        except Exception as e:
            logger.warning(f"cancel_order {symbol} {order_id}: {e}")
            return False

    async def get_order_status(self, symbol: str, order_id: str) -> dict:
        try:
            return await self.exchange.fetch_order(order_id, symbol)
        except Exception as e:
            raise ExchangeError(f"get_order_status {symbol} {order_id}: {e}")

    async def get_position(self, symbol: str) -> Optional[dict]:
        try:
            positions = await self.exchange.fetch_positions([symbol])
            for pos in positions:
                if pos['symbol'] == symbol and float(pos.get('contracts', 0)) > 0:
                    return pos
            return None
        except Exception as e:
            raise ExchangeError(f"get_position {symbol}: {e}")

    async def get_balance(self) -> float:
        try:
            balance = await self.exchange.fetch_balance()
            return float(balance.get('USDT', {}).get('free', 0))
        except Exception as e:
            raise ExchangeError(f"get_balance: {e}")

    async def subscribe_klines(self, symbols: List[str],
                               timeframes: List[str], callback):
        # WebSocket 需要用 python-binance 或 websockets 实现
        # Phase 2 实现
        logger.info("WebSocket klines subscription not yet implemented")

    async def subscribe_orderbook(self, symbols: List[str], callback):
        logger.info("WebSocket orderbook subscription not yet implemented")
