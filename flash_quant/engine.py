"""
Flash Quant - 扫描器引擎主入口
三层扫描 + REST 数据补充 + 持仓监控
"""
import asyncio
import signal as sig
from config.settings import settings
from core.logger import setup_logging, get_logger
from ws.binance_ws import BinanceWebSocket
from data.rest_poller import RestPoller
from data.market_data import market_data
from scanner.tier1_scalper import Tier1Scalper
from scanner.tier2_trend import Tier2TrendScanner
from scanner.tier3_direction import Tier3DirectionScanner
from executor.paper_executor import PaperExecutor
from risk.risk_manager import risk_manager
from models.db_ops import count_open_trades, get_open_symbols
from data.daily_stats_updater import daily_stats_updater

logger = get_logger('engine')

# 监控的 50 个币
DEFAULT_SYMBOLS = [
    'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
    'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'DOTUSDT',
    'MATICUSDT', 'NEARUSDT', 'APTUSDT', 'ATOMUSDT', 'TRXUSDT',
    'SUIUSDT', 'WIFUSDT', 'PEPEUSDT', 'ARBUSDT', 'OPUSDT',
    'SEIUSDT', 'FILUSDT', 'INJUSDT', 'TIAUSDT', 'STXUSDT',
    'IMXUSDT', 'MKRUSDT', 'LTCUSDT', 'BCHUSDT', 'ETCUSDT',
    'AAVEUSDT', 'GRTUSDT', 'SNXUSDT', 'RUNEUSDT',
    'FETUSDT', 'WLDUSDT', 'JUPUSDT', 'ENAUSDT', 'TONUSDT',
    'ORDIUSDT', 'ONDOUSDT', 'PYTHUSDT',
    'RNDRUSDT', 'BLURUSDT', 'CELOUSDT', 'CFXUSDT',
    'PENUSDT', 'AGIXUSDT', 'FLOKIUSDT', 'WOOUSDT',
]


async def position_monitor(executor, interval: int = 30):
    """持仓监控: 止损/止盈/超时检查"""
    logger.info("position_monitor.started", interval=interval)
    while True:
        try:
            await executor.check_positions()
            risk_manager.update_positions(
                count_open_trades(),
                get_open_symbols(),
            )
        except Exception as e:
            logger.error("position_monitor.error", error=str(e))
        await asyncio.sleep(interval)


async def warmup_klines(symbols):
    """启动时用 REST 拉取历史 K线"""
    try:
        import ccxt
        exchange = ccxt.binance({'options': {'defaultType': 'future'}})
        from data.kline_cache import kline_cache, Kline

        for sym in symbols:
            try:
                pair = sym.replace('USDT', '/USDT')
                # 拉 5min K线
                ohlcv_5m = exchange.fetch_ohlcv(pair, '5m', limit=25)
                for c in ohlcv_5m[:-1]:
                    kline_cache.update(sym, '5m', Kline(
                        timestamp=c[0], open=c[1], high=c[2],
                        low=c[3], close=c[4], volume=c[5], is_closed=True,
                    ))

                # 拉 15min K线 (Tier 2 需要)
                ohlcv_15m = exchange.fetch_ohlcv(pair, '15m', limit=50)
                for c in ohlcv_15m[:-1]:
                    kline_cache.update(sym, '15m', Kline(
                        timestamp=c[0], open=c[1], high=c[2],
                        low=c[3], close=c[4], volume=c[5], is_closed=True,
                    ))

                # 拉 1H K线 (Tier 3 需要)
                ohlcv_1h = exchange.fetch_ohlcv(pair, '1h', limit=50)
                for c in ohlcv_1h[:-1]:
                    kline_cache.update(sym, '1h', Kline(
                        timestamp=c[0], open=c[1], high=c[2],
                        low=c[3], close=c[4], volume=c[5], is_closed=True,
                    ))

                logger.info("warmup.done", symbol=sym)
            except Exception as e:
                logger.warning("warmup.skip", symbol=sym, error=str(e))

            await asyncio.sleep(0.2)  # 限速保护

        logger.info("warmup.complete", symbols=len(symbols))
    except Exception as e:
        logger.error("warmup.failed", error=str(e))


async def main():
    setup_logging(level=settings.LOG_LEVEL,
                  json_format=settings.LOG_FORMAT == 'json')

    logger.info("engine.starting",
                mode=settings.TRADING_MODE,
                phase=settings.PHASE,
                symbols=len(DEFAULT_SYMBOLS))

    # 1. 执行器
    if settings.TRADING_MODE == 'paper':
        executor = PaperExecutor()
    elif settings.TRADING_MODE == 'live':
        from executor.binance_executor import BinanceExecutor
        executor = BinanceExecutor()
        logger.info("engine.live_mode", warning="REAL MONEY!")
    else:
        raise ValueError(f"Unknown TRADING_MODE: {settings.TRADING_MODE}")

    # 2. Warmup K线 (5m + 15m + 1h)
    await warmup_klines(DEFAULT_SYMBOLS)

    # 3. WebSocket (kline 5m + 15m + 1h)
    ws = BinanceWebSocket(DEFAULT_SYMBOLS, intervals=['5m', '15m', '1h'])

    # 4. REST 数据补充 (funding/OI/volume)
    rest = RestPoller(DEFAULT_SYMBOLS)

    # 5. 扫描器 (Tier 1 爆发 + Tier 2 次级爆发, Tier 3 暂停)
    tier1 = Tier1Scalper(DEFAULT_SYMBOLS, executor=executor)
    tier2 = Tier2TrendScanner(DEFAULT_SYMBOLS, executor=executor)
    # Tier 3 暂停: 评分系统在震荡市方向不准, 检讨后决定暂停

    # 6. 启动
    logger.info("engine.started", tiers="1+2 (tier3 suspended)")
    await asyncio.gather(
        ws.run(),
        rest.run(),
        tier1.run(),
        tier2.run(),
        position_monitor(executor, interval=30),
        daily_stats_updater(interval=300),
    )


if __name__ == "__main__":
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)

    for s in (sig.SIGINT, sig.SIGTERM):
        loop.add_signal_handler(s, loop.stop)

    try:
        loop.run_until_complete(main())
    except KeyboardInterrupt:
        logger.info("engine.stopped")
    finally:
        loop.close()
