"""
Binance WebSocket 客户端
订阅 kline + aggTrade 多流
"""
import asyncio
import json
import time
import websockets
from core.logger import get_logger
from data.kline_cache import kline_cache, Kline
from data.cvd_calculator import cvd_calculator
from data.market_data import market_data
from risk.black_swan import black_swan_monitor

logger = get_logger('binance_ws')

BINANCE_WS_FUTURES = "wss://fstream.binance.com/stream?streams="


class BinanceWebSocket:
    """
    管理 Binance Futures WebSocket 连接
    订阅: kline_5m + kline_15m + kline_1h + aggTrade
    """

    def __init__(self, symbols: list, intervals: list = None):
        self.symbols = [s.lower().replace('/usdt', 'usdt').replace(':usdt', '')
                        for s in symbols]
        self.intervals = intervals or ['5m', '15m', '1h']
        self._running = False
        self._msg_count = 0
        self._error_count = 0
        self._connected = False

    def _build_url(self) -> str:
        streams = []
        for sym in self.symbols:
            for interval in self.intervals:
                streams.append(f"{sym}@kline_{interval}")
        # aggTrade: 前 20 个主流币 (CVD 计算)
        for sym in self.symbols[:20]:
            streams.append(f"{sym}@aggTrade")
        # Binance 单连接最多 200 streams
        if len(streams) > 200:
            streams = streams[:200]
            logger.warning("binance_ws.stream_limit", count=len(streams))
        return BINANCE_WS_FUTURES + "/".join(streams)

    async def run(self):
        """主运行循环, 含重连"""
        self._running = True
        retry = 0
        max_retry = 20

        while self._running and retry < max_retry:
            try:
                url = self._build_url()
                logger.info("binance_ws.connecting",
                           symbols=len(self.symbols),
                           streams=len(self.symbols) * len(self.intervals))

                async with websockets.connect(
                    url, ping_interval=20, ping_timeout=10,
                    max_size=10 * 1024 * 1024,  # 10MB
                ) as ws:
                    self._connected = True
                    retry = 0
                    logger.info("binance_ws.connected")

                    async for msg in ws:
                        self._process(msg)

            except websockets.exceptions.ConnectionClosed as e:
                self._connected = False
                retry += 1
                wait = min(2 ** retry, 60)
                logger.warning("binance_ws.disconnected",
                              code=e.code, retry=retry, wait=wait)
                try:
                    await asyncio.sleep(wait)
                except RuntimeError:
                    import time; time.sleep(wait)

            except Exception as e:
                self._connected = False
                retry += 1
                wait = min(2 ** retry, 60)
                logger.error("binance_ws.error",
                            error=str(e), retry=retry, wait=wait)
                try:
                    await asyncio.sleep(wait)
                except RuntimeError:
                    import time; time.sleep(wait)

        logger.error("binance_ws.max_retries_reached", max_retry=max_retry)

    def stop(self):
        self._running = False

    def _process(self, raw: str):
        """处理单条 WebSocket 消息"""
        self._msg_count += 1
        try:
            data = json.loads(raw)
            stream = data.get('stream', '')

            if '@kline_' in stream:
                self._handle_kline(data['data'])
            elif '@aggTrade' in stream:
                self._handle_agg_trade(data['data'])

            # 每 1000 条打印一次状态
            if self._msg_count % 1000 == 0:
                from data.kline_cache import kline_cache
                syms = kline_cache.symbols()
                logger.info("binance_ws.stats",
                           msgs=self._msg_count, errors=self._error_count,
                           cached_symbols=len(syms))

        except Exception as e:
            self._error_count += 1
            if self._error_count % 100 == 0:
                logger.error("binance_ws.process_error",
                            error=str(e), total_errors=self._error_count)

    def _handle_kline(self, data: dict):
        """处理 K线消息"""
        k = data.get('k', {})
        symbol = k.get('s', '')
        interval = k.get('i', '')

        kline = Kline(
            timestamp=k['t'],
            open=float(k['o']),
            high=float(k['h']),
            low=float(k['l']),
            close=float(k['c']),
            volume=float(k['v']),
            close_time=k['T'],
            is_closed=k['x'],
        )

        kline_cache.update(symbol, interval, kline)

        # 更新价格
        market_data.update_price(symbol, kline.close)

        # 调试: K线收盘日志
        if kline.is_closed and interval == '5m':
            logger.info("kline.closed", symbol=symbol, close=kline.close,
                       vol=kline.volume, ts=kline.timestamp)

        # 黑天鹅检测 (每根 5m K线收盘时)
        if interval == '5m' and kline.is_closed:
            black_swan_monitor.check_volatility(symbol, kline.high, kline.low)

    def _handle_agg_trade(self, data: dict):
        """处理 aggTrade 消息 → CVD"""
        cvd_calculator.on_agg_trade(
            symbol=data['s'],
            timestamp_ms=data['T'],
            quantity=float(data['q']),
            is_buyer_maker=data['m'],
        )

    @property
    def stats(self) -> dict:
        return {
            'connected': self._connected,
            'messages': self._msg_count,
            'errors': self._error_count,
            'symbols': len(self.symbols),
        }
