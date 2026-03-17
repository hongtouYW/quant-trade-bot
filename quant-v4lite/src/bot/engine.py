import asyncio
import json
import logging
from datetime import datetime
from pathlib import Path

from src.core.config import Config
from src.core.enums import Direction
from src.exchange.binance_client import BinanceClient
from src.exchange.smart_router import SmartOrderRouter
from src.data.market_data import MarketDataManager
from src.data.cache import RedisCache
from src.analysis.regime import RegimeDetector, STRATEGY_ROUTING, DEFAULT_ROUTING
from src.analysis.screener import SymbolScreener
from src.analysis.orderbook_filter import OrderBookFilter
from src.analysis.multi_exchange import MultiExchangeConsensus
from src.strategy.aggregator import SignalAggregator
from src.risk.position_sizer import PositionSizer
from src.risk.stop_manager import StopManager
from src.risk.risk_control import RiskControl
from src.risk.portfolio import PortfolioManager
from src.risk.session import SessionManager
from src.execution.order_manager import OrderManager
from src.execution.position_monitor import PositionMonitor
from src.adaptive.daily_review import AdaptiveEngine
from src.utils.telegram import TelegramNotifier
from src.utils.logger import setup_logger

logger = logging.getLogger(__name__)


class TradingEngine:
    """主控循环"""

    def __init__(self, config_path: str = None):
        self._config = Config(config_path)
        self._cfg = self._config.data
        self._running = False
        self._error_count = 0

    async def _init_modules(self):
        """初始化所有模块"""
        cfg = self._cfg

        # 交易所
        self._exchange = BinanceClient(
            cfg['account']['api_key'],
            cfg['account']['api_secret'],
        )

        # 数据
        self._cache = RedisCache(
            cfg.get('database', {}).get('redis', {}).get('host', 'localhost'),
            cfg.get('database', {}).get('redis', {}).get('port', 6379),
        )
        self._market_data = MarketDataManager(self._exchange, self._cache)

        # 分析
        self._regime = RegimeDetector()
        self._screener = SymbolScreener(cfg)
        self._ob_filter = OrderBookFilter(cfg)
        self._multi_exchange = MultiExchangeConsensus(cfg)

        # 策略
        self._aggregator = SignalAggregator()

        # 风控
        balance = await self._exchange.get_balance()
        self._sizer = PositionSizer()
        self._stop_mgr = StopManager(cfg)
        self._risk = RiskControl(cfg, balance)
        self._portfolio = PortfolioManager()
        self._session = SessionManager(cfg)

        # 执行
        self._smart_router = SmartOrderRouter(self._exchange, cfg)
        self._order_mgr = OrderManager(self._exchange)
        self._monitor = PositionMonitor(
            self._exchange, self._stop_mgr, self._portfolio, self._order_mgr)

        # 其他
        self._adaptive = AdaptiveEngine(cfg)
        self._telegram = TelegramNotifier(cfg)

        logger.info(f"Modules initialized, balance={balance:.2f}U")

    async def run(self):
        """主循环"""
        setup_logger(self._cfg)
        await self._init_modules()
        await self._market_data.start()

        # 后台任务
        asyncio.create_task(self._monitor.monitor_loop())
        asyncio.create_task(self._multi_exchange_loop())
        asyncio.create_task(self._status_loop())

        self._running = True
        scan_interval = self._cfg.get('symbols', {}).get('scan_interval_sec', 300)

        logger.info("Trading engine started")
        await self._telegram.notify('system', '🟢 Trading engine started')

        while self._running:
            try:
                await self._main_cycle()
                self._error_count = 0
            except Exception as e:
                self._error_count += 1
                logger.error(f"Main cycle error ({self._error_count}): {e}")
                if self._error_count >= 10:
                    await self._telegram.error(f"连续异常 {self._error_count} 次: {e}")
                await asyncio.sleep(10)
                continue

            await asyncio.sleep(scan_interval)

    async def _main_cycle(self):
        """单次扫描周期"""
        cfg = self._session.apply_overrides(self._cfg)

        # 风控检查
        can_trade, reason = self._risk.pre_trade_check()
        if not can_trade:
            logger.info(f"Trade blocked: {reason}")
            return

        # 获取路由 (Phase 1 用默认)
        routing = DEFAULT_ROUTING
        if cfg.get('regime', {}).get('enabled', False):
            btc = cfg.get('timeframes', {}).get('regime_reference', 'BTC/USDT:USDT')
            btc_1h = await self._market_data.get_klines(btc, '1h', 220)
            btc_4h = await self._market_data.get_klines(btc, '4h', 55)
            regime = self._regime.detect(btc_1h, btc_4h)
            routing = STRATEGY_ROUTING.get(regime, DEFAULT_ROUTING)

        # 持仓数检查
        max_pos = routing.get('max_positions', cfg.get('risk', {}).get('max_open_positions', 4))
        if self._portfolio.count >= max_pos:
            return

        # 获取候选池
        symbols = await self._market_data.get_all_symbols()
        tickers = await self._exchange.fetch_tickers(
            [s.symbol for s in symbols[:150]])

        # 批量拉取 1H K线
        sym_list = [s.symbol for s in symbols[:150]]
        await self._market_data.batch_fetch_klines(sym_list, '1h', 220)
        klines_dict = {}
        for sym in sym_list:
            klines_dict[sym] = await self._market_data.get_klines(sym, '1h', 220)

        # 筛选
        pool = await self._screener.scan(symbols[:150], tickers, klines_dict)

        # 遍历候选
        for ts in pool:
            if self._portfolio.count >= max_pos:
                break
            if self._portfolio.has_symbol(ts.symbol):
                continue

            # 拉取 15m K线
            klines_15m = await self._market_data.get_klines(ts.symbol, '15m', 100)
            klines_1h = klines_dict.get(ts.symbol, [])

            # 信号检测
            signal = self._aggregator.scan(
                ts.symbol, klines_1h, klines_15m,
                routing['strategies'], routing['direction_bias'], cfg)

            if not signal:
                continue

            # V4 增强检查
            if cfg.get('orderbook', {}).get('enabled', False):
                book = await self._market_data.get_orderbook(signal.symbol)
                ob_analysis = self._ob_filter.analyze(
                    signal.symbol, signal.direction, signal.entry_price, book)
                signal, reason = self._aggregator.enhanced_check(
                    signal, orderbook_analysis=ob_analysis)
                if not signal:
                    logger.info(f"Signal rejected {ts.symbol}: {reason}")
                    continue

            if cfg.get('multi_exchange', {}).get('enabled', False):
                consensus = self._multi_exchange.check(signal.symbol, signal.direction)
                signal, reason = self._aggregator.enhanced_check(
                    signal, consensus_result=consensus)
                if not signal:
                    logger.info(f"Consensus rejected {ts.symbol}: {reason}")
                    continue

            # 再次风控
            can_trade, reason = self._risk.pre_trade_check(signal)
            if not can_trade:
                continue

            # 仓位计算
            balance = await self._exchange.get_balance()
            risk_scale = self._risk.get_risk_scale()
            pos_size = self._sizer.calculate(
                balance, signal, routing, risk_scale, cfg,
                self._risk.consecutive_losses)

            if not pos_size:
                continue

            # 总保证金检查
            total_margin = self._portfolio.total_margin() + pos_size.margin
            max_total = cfg.get('risk', {}).get('max_total_margin_pct', 40) / 100
            if total_margin > balance * max_total:
                continue

            # 下单
            side = 'buy' if signal.direction == Direction.LONG else 'sell'
            await self._exchange.set_leverage(signal.symbol, cfg['account']['leverage'])

            order_result, fill_type = await self._smart_router.execute_entry(
                signal.symbol, side, pos_size.quantity)

            if not order_result:
                continue

            # 创建持仓
            pos = self._portfolio.open_position(
                signal, pos_size.margin, pos_size.notional,
                pos_size.quantity, fill_type)

            # 挂止损止盈
            await self._order_mgr.place_stop_loss(pos)
            for tp_price, tp_pct in signal.take_profits:
                qty = pos_size.quantity * tp_pct
                await self._order_mgr.place_take_profit(pos, tp_price, qty)

            # 通知
            await self._telegram.trade_open({
                'symbol': signal.symbol,
                'direction': signal.direction.name,
                'strategy': signal.strategy.value,
                'confidence': signal.confidence,
                'entry': signal.entry_price,
                'stop': signal.stop_loss,
                'rr': signal.risk_reward or 0,
                'margin': pos_size.margin,
                'risk': pos_size.risk_amount,
            })

            logger.info(f"Opened {signal.direction.name} {signal.symbol} "
                        f"@{signal.entry_price} margin={pos_size.margin:.2f}")

    async def _multi_exchange_loop(self):
        """多所价格更新循环"""
        interval = self._cfg.get('multi_exchange', {}).get('update_interval_sec', 30)
        while self._running:
            try:
                pool_symbols = [p.symbol for p in self._portfolio.positions.values()]
                if pool_symbols:
                    await self._multi_exchange.update(pool_symbols)
            except Exception as e:
                logger.warning(f"Multi-exchange update error: {e}")
            await asyncio.sleep(interval)

    async def _status_loop(self):
        """每 10 秒更新 status.json 给 web 面板"""
        status_file = Path(__file__).parent.parent.parent / "data" / "status.json"
        status_file.parent.mkdir(parents=True, exist_ok=True)
        while self._running:
            try:
                session_name, _ = self._session.get_current_session()
                positions_data = []
                for p in self._portfolio.positions.values():
                    try:
                        ticker = await self._exchange.fetch_ticker(p.symbol)
                        price = ticker.get('last', p.entry_price)
                        pnl = p.unrealized_pnl(price)
                        r_val = p.current_r(price)
                    except Exception:
                        pnl = 0
                        r_val = 0
                    positions_data.append({
                        'symbol': p.symbol.split(':')[0],
                        'direction': p.direction.name,
                        'entry': f"{p.entry_price:.4f}",
                        'pnl': round(pnl, 2),
                        'r_value': round(r_val, 1),
                        'hold_min': int(p.holding_minutes),
                    })

                balance = self._risk._current_balance
                status = {
                    'running': True,
                    'balance': round(balance, 2),
                    'initial_balance': round(self._risk._initial_balance, 2),
                    'total_pnl': round(balance - self._risk._initial_balance, 2),
                    'daily_pnl': round(self._risk._daily_pnl, 2),
                    'daily_trades': self._risk._daily_trade_count,
                    'win_rate': (self._risk._daily_win_count / self._risk._daily_trade_count
                                 if self._risk._daily_trade_count > 0 else 0),
                    'leverage': self._cfg.get('account', {}).get('leverage', 10),
                    'session': session_name.value,
                    'open_positions': self._portfolio.count,
                    'max_positions': self._cfg.get('risk', {}).get('max_open_positions', 4),
                    'consec_losses': self._risk._consecutive_losses,
                    'risk_scale': self._risk.get_risk_scale(),
                    'total_margin': round(self._portfolio.total_margin(), 2),
                    'positions': positions_data,
                    'updated': datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S'),
                }
                with open(status_file, 'w') as f:
                    json.dump(status, f, indent=2)
            except Exception as e:
                logger.debug(f"Status update error: {e}")
            await asyncio.sleep(10)

    async def shutdown(self):
        """关闭"""
        self._running = False
        self._monitor.stop()
        # 更新状态为停止
        status_file = Path(__file__).parent.parent.parent / "data" / "status.json"
        try:
            with open(status_file, 'w') as f:
                json.dump({'running': False, 'balance': self._risk._current_balance,
                           'initial_balance': self._risk._initial_balance,
                           'total_pnl': 0, 'daily_pnl': 0, 'daily_trades': 0,
                           'win_rate': 0, 'leverage': 10, 'session': 'N/A',
                           'open_positions': 0, 'max_positions': 4,
                           'consec_losses': 0, 'risk_scale': 1.0,
                           'total_margin': 0, 'positions': []}, f)
        except Exception:
            pass
        await self._exchange.close()
        await self._multi_exchange.close()
        await self._cache.close()
        await self._telegram.notify('system', '🔴 Trading engine stopped')
        logger.info("Trading engine stopped")
