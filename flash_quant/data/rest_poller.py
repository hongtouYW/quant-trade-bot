"""
REST 数据定时拉取
补充 WebSocket 没有的数据: funding_rate / OI / taker_ratio / 24h_volume
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
import ccxt
from data.market_data import market_data
from core.logger import get_logger

logger = get_logger('rest_poller')

MYT = timezone(timedelta(hours=8))


class RestPoller:
    """
    定时从 Binance REST API 拉取补充数据
    - 每 30s: 24h volume + funding rate (用 tickers)
    - 每 5min: OI
    """

    def __init__(self, symbols: list):
        self.symbols = symbols
        self._exchange = ccxt.binance({'options': {'defaultType': 'future'}})
        self._last_ticker_time = 0
        self._last_oi_time = 0

    async def run(self):
        logger.info("rest_poller.started", symbols=len(self.symbols))
        while True:
            try:
                now = time.time()

                # 每 30s 拉 tickers (volume + funding + price)
                if now - self._last_ticker_time >= 30:
                    await self._fetch_tickers()
                    self._last_ticker_time = now

                # 每 5min 拉 OI
                if now - self._last_oi_time >= 300:
                    await self._fetch_open_interest()
                    self._last_oi_time = now

            except Exception as e:
                logger.error("rest_poller.error", error=str(e))

            await asyncio.sleep(10)

    async def _fetch_tickers(self):
        """拉取所有 tickers (24h volume + last price + funding)"""
        try:
            loop = asyncio.get_event_loop()
            tickers = await loop.run_in_executor(None, self._exchange.fetch_tickers)

            count = 0
            for sym_raw, ticker in tickers.items():
                # ccxt 格式: BTC/USDT:USDT → BTCUSDT
                sym = sym_raw.split(':')[0].replace('/', '')
                if sym not in self.symbols:
                    continue

                # 24h volume (quote volume = USDT)
                vol = ticker.get('quoteVolume') or 0
                market_data.update_volume_24h(sym, vol)

                # price
                price = ticker.get('last') or 0
                if price > 0:
                    market_data.update_price(sym, price)

                # funding rate (Binance 放在 info 里)
                info = ticker.get('info', {})
                funding = info.get('lastFundingRate')
                if funding is not None:
                    market_data.update_funding_rate(sym, float(funding))

                count += 1

            logger.info("rest_poller.tickers", count=count)

        except Exception as e:
            logger.error("rest_poller.tickers_error", error=str(e))

    async def _fetch_open_interest(self):
        """拉取 Open Interest"""
        try:
            loop = asyncio.get_event_loop()
            count = 0
            for sym in self.symbols[:30]:  # 限制前 30 个避免限速
                try:
                    pair = sym.replace('USDT', '/USDT') + ':USDT'
                    oi_data = await loop.run_in_executor(
                        None, self._exchange.fetch_open_interest, pair
                    )
                    if oi_data and 'openInterestAmount' in oi_data:
                        market_data.update_open_interest(
                            sym, float(oi_data['openInterestAmount'])
                        )
                        count += 1
                except Exception:
                    pass  # 有些币可能没有 OI 接口
                await asyncio.sleep(0.1)  # 限速保护

            logger.info("rest_poller.oi", count=count)

        except Exception as e:
            logger.error("rest_poller.oi_error", error=str(e))

    async def fetch_taker_ratio(self, symbol: str):
        """拉取 Taker Buy/Sell Ratio (单独调用)"""
        try:
            pair = symbol.replace('USDT', '/USDT') + ':USDT'
            loop = asyncio.get_event_loop()
            trades = await loop.run_in_executor(
                None, self._exchange.fetch_trades, pair, None, 100
            )
            if not trades:
                return

            buy_vol = sum(t['amount'] for t in trades if t['side'] == 'buy')
            sell_vol = sum(t['amount'] for t in trades if t['side'] == 'sell')
            ratio = buy_vol / sell_vol if sell_vol > 0 else 1.0
            market_data.update_taker_ratio(symbol, round(ratio, 2))

        except Exception:
            pass
