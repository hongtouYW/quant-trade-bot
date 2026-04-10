"""
Flash Quant - 扫描器引擎主入口
由 Supervisord 管理, 独立进程

启动流程:
1. 加载配置
2. 初始化 WebSocket 数据采集
3. Warmup K线缓存
4. 启动三层扫描器
5. 启动持仓监控循环
"""
import asyncio
import signal as sig
from config.settings import settings
from core.logger import setup_logging, get_logger
from ws.binance_ws import BinanceWebSocket
from data.market_data import market_data
from scanner.tier1_scalper import Tier1Scalper
from executor.paper_executor import PaperExecutor
from risk.risk_manager import risk_manager
from models.db_ops import count_open_trades, get_open_symbols

logger = get_logger('engine')

# Phase 1 默认监控的 Tier A + B 币种
DEFAULT_SYMBOLS = [
    'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
    'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'DOTUSDT',
    'NEARUSDT', 'APTUSDT', 'ATOMUSDT', 'TRXUSDT', 'SUIUSDT',
    'PEPEUSDT', 'ARBUSDT', 'OPUSDT', 'WIFUSDT', 'SEIUSDT',
    'MATICUSDT', 'FILUSDT', 'INJUSDT', 'TIAUSDT', 'STXUSDT',
    'IMXUSDT', 'MKRUSDT', 'LTCUSDT', 'BCHUSDT', 'ETCUSDT',
    'AAVEUSDT', 'GRTUSDT', 'RENUSDT', 'SNXUSDT', 'RUNEUSDT',
    'FETUSDT', 'WLDUSDT', 'JUPUSDT', 'ENAUSDT', 'TONUSDT',
    'ORDIUSDT', 'ONDOUSDT', 'PENUSDT', 'JUPUSDT', 'PYTHUSDT',
    'RNDRUSDT', 'AGIXUSDT', 'BLURUSDT', 'CELOUSDT', 'CFXUSDT',
]


async def position_monitor(executor: PaperExecutor, interval: int = 30):
    """持仓监控循环: 止损/止盈/超时检查"""
    logger.info("position_monitor.started", interval=interval)
    while True:
        try:
            await executor.check_positions()

            # 更新 risk_manager 的持仓状态 (从 DB 读)
            risk_manager.update_positions(
                count_open_trades(),
                get_open_symbols(),
            )

        except Exception as e:
            logger.error("position_monitor.error", error=str(e))

        await asyncio.sleep(interval)


async def main():
    setup_logging(level=settings.LOG_LEVEL,
                  json_format=settings.LOG_FORMAT == 'json')

    logger.info("engine.starting",
                mode=settings.TRADING_MODE,
                phase=settings.PHASE,
                symbols=len(DEFAULT_SYMBOLS))

    # 1. 初始化执行器
    if settings.TRADING_MODE == 'paper':
        executor = PaperExecutor()
    else:
        # Phase 2+: Binance 实盘
        raise NotImplementedError("Live executor not implemented yet")

    # 2. Warmup: 用 ccxt REST 拉取历史 K线
    try:
        import ccxt
        exchange = ccxt.binance({'options': {'defaultType': 'future'}})
        for sym in DEFAULT_SYMBOLS[:20]:  # 先 warmup 前 20 个
            try:
                pair = sym.replace('USDT', '/USDT')
                ohlcv = exchange.fetch_ohlcv(pair, '5m', limit=25)
                from data.kline_cache import kline_cache, Kline
                for candle in ohlcv[:-1]:  # 最后一根未收盘,不算
                    kline_cache.update(sym, '5m', Kline(
                        timestamp=candle[0],
                        open=candle[1], high=candle[2],
                        low=candle[3], close=candle[4],
                        volume=candle[5], is_closed=True,
                    ))
                logger.info("warmup.done", symbol=sym,
                           klines=len(ohlcv) - 1)
            except Exception as e:
                logger.warning("warmup.skip", symbol=sym, error=str(e))
        logger.info("warmup.complete", symbols=20)
    except Exception as e:
        logger.error("warmup.failed", error=str(e))

    # 3. 初始化 WebSocket
    ws = BinanceWebSocket(DEFAULT_SYMBOLS)

    # 4. 初始化扫描器
    tier1 = Tier1Scalper(DEFAULT_SYMBOLS, executor=executor)

    # 5. 启动所有任务
    logger.info("engine.started")
    await asyncio.gather(
        ws.run(),                              # WebSocket 数据采集
        tier1.run(),                           # Tier 1 扫描
        position_monitor(executor, interval=30),  # 持仓监控
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
