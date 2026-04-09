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

            # 更新 risk_manager 的持仓状态
            risk_manager.update_positions(
                executor.get_open_count(),
                executor.get_open_symbols(),
            )

            # 每 10 分钟打印统计
            stats = executor.get_stats()
            if stats['total'] > 0 and stats['total'] % 5 == 0:
                logger.info("stats", **stats)

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

    # 2. 初始化 WebSocket
    ws = BinanceWebSocket(DEFAULT_SYMBOLS)

    # 3. 初始化扫描器
    tier1 = Tier1Scalper(DEFAULT_SYMBOLS, executor=executor)

    # 4. 启动所有任务
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
