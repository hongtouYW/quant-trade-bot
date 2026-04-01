import asyncio
import json
import logging
from datetime import datetime
from pathlib import Path

from src.core.config import Config
from src.core.enums import Direction
from src.data.db import Database
from src.exchange.binance_client import BinanceClient
from src.exchange.smart_router import SmartOrderRouter
from src.data.market_data import MarketDataManager
from src.data.cache import RedisCache
from src.analysis.regime import RegimeDetector, STRATEGY_ROUTING, DEFAULT_ROUTING
from src.analysis.screener import SymbolScreener
from src.analysis.orderbook_filter import OrderBookFilter
from src.analysis.multi_exchange import MultiExchangeConsensus
from src.analysis.economic_calendar import EconomicCalendar
from src.strategy.aggregator import SignalAggregator
from src.risk.position_sizer import PositionSizer
from src.risk.stop_manager import StopManager
from src.risk.risk_control import RiskControl
from src.risk.portfolio import PortfolioManager
from src.risk.session import SessionManager
from src.execution.order_manager import OrderManager
from src.execution.position_monitor import PositionMonitor
from src.execution.paper_monitor import PaperPositionMonitor
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
        self._paper = self._cfg.get('account', {}).get('paper_trading', False)
        self._last_regime = None  # 用于 regime 切换通知

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

        # 风控 (模拟模式用 config 本金，实盘用交易所余额)
        if self._paper:
            balance = cfg.get('account', {}).get('balance', 2000)
        else:
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

        # 数据库
        self._db = None
        db_cfg = cfg.get('database', {}).get('postgres', {})
        if db_cfg.get('host'):
            try:
                self._db = Database(
                    db_cfg.get('host', 'localhost'),
                    db_cfg.get('port', 5432),
                    db_cfg.get('db', 'quant_trading'),
                    db_cfg.get('user', 'quant'),
                    db_cfg.get('password', ''),
                )
                await self._db.connect()
                logger.info("Database connected")
            except Exception as e:
                logger.warning(f"Database connection failed: {e}, continuing without DB")
                self._db = None

        # 模拟持仓监控
        if self._paper:
            self._paper_monitor = PaperPositionMonitor(
                self._exchange, self._stop_mgr, self._portfolio,
                self._risk, None, cfg)  # telegram 稍后设置
            self._paper_monitor._db = self._db

        # 其他
        self._adaptive = AdaptiveEngine(cfg)
        self._telegram = TelegramNotifier(cfg)
        self._calendar_enabled = cfg.get('economic_calendar', {}).get('enabled', True)
        self._calendar = EconomicCalendar() if self._calendar_enabled else None

        # 回填 telegram 到 paper_monitor
        if self._paper:
            self._paper_monitor._telegram = self._telegram

        mode = "模拟交易" if self._paper else "实盘交易"
        logger.info(f"Modules initialized, balance={balance:.2f}U, mode={mode}")

    async def _restore_balance(self):
        """从 DB 恢复累计 PnL（模拟模式）"""
        if not self._db or not self._paper:
            return
        try:
            summary = await self._db.get_trade_summary()
            total_pnl = summary.get('total_pnl', 0) or 0
            total_fee = summary.get('total_fee', 0) or 0
            total_funding = summary.get('total_funding_fee', 0) or 0
            net_pnl = total_pnl - total_fee - total_funding
            if abs(net_pnl) > 0.01:
                initial = self._cfg.get('account', {}).get('balance', 2000)
                restored = initial + net_pnl
                self._risk._current_balance = restored
                self._risk._daily_pnl = 0  # 日内 PnL 从 0 开始
                logger.info(f"Balance restored from DB: {initial:.2f} + {net_pnl:+.2f} = {restored:.2f}U "
                            f"(trades PnL={total_pnl:+.2f}, fee={total_fee:.2f}, funding={total_funding:.4f})")
        except Exception as e:
            logger.warning(f"Failed to restore balance: {e}")

    async def _restore_positions(self):
        """从 DB 恢复持仓"""
        if not self._db:
            return
        try:
            rows = await self._db.get_open_positions()
            if not rows:
                return
            for row in rows:
                from src.core.models import Position
                direction = Direction.LONG if row['direction'] == 'LONG' else Direction.SHORT
                from src.core.enums import SignalStrategy, FillType
                strategy = SignalStrategy(row['strategy'])
                fill_type = FillType(row['fill_type']) if row['fill_type'] else FillType.MARKET
                pos = Position(
                    id=row['id'],
                    symbol=row['symbol'],
                    direction=direction,
                    strategy=strategy,
                    entry_price=row['entry_price'],
                    margin=row['margin'],
                    notional=row['notional'],
                    quantity=row['quantity'],
                    stop_loss=row['stop_loss'],
                    initial_stop=row['initial_stop'],
                    take_profits=[
                        (row['tp1_price'], row['tp1_pct']),
                        (row['tp2_price'], row['tp2_pct']),
                    ],
                    best_price=row['best_price'],
                    open_time=row['open_time'].replace(tzinfo=None) if hasattr(row['open_time'], 'tzinfo') and row['open_time'].tzinfo else row['open_time'],
                    remaining_pct=row.get('remaining_pct', 1.0),
                    tp1_hit=row.get('tp1_hit', False),
                    fill_type=fill_type,
                )
                self._portfolio._positions[pos.id] = pos
            logger.info(f"Restored {len(rows)} positions from DB")
        except Exception as e:
            logger.warning(f"Failed to restore positions: {e}")

    async def run(self):
        """主循环"""
        setup_logger(self._cfg)
        await self._init_modules()
        await self._restore_balance()
        await self._restore_positions()
        await self._market_data.start()

        # 启动时重置日统计（防止跨天累积）
        self._risk.daily_reset()
        if datetime.utcnow().weekday() == 0:
            self._risk.weekly_reset()
        logger.info("Risk counters reset on startup")

        # 后台任务
        if self._paper:
            asyncio.create_task(self._paper_monitor.monitor_loop())
        else:
            asyncio.create_task(self._monitor.monitor_loop())
        asyncio.create_task(self._multi_exchange_loop())
        asyncio.create_task(self._status_loop())
        asyncio.create_task(self._adaptive_loop())
        asyncio.create_task(self._daily_report_loop())
        asyncio.create_task(self._telegram_command_loop())
        asyncio.create_task(self._signal_log_cleanup_loop())

        self._running = True
        scan_interval = self._cfg.get('symbols', {}).get('scan_interval_sec', 300)

        mode = "模拟" if self._paper else "实盘"
        logger.info(f"Trading engine started [{mode}模式]")
        await self._telegram.notify('system', f'🟢 V4-Lite 交易引擎已启动 [{mode}模式]')

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
            logger.info(f"Market regime: {regime.value} → strategies={routing['strategies']} bias={routing['direction_bias']}")

            # Regime 切换通知
            if self._last_regime is not None and regime != self._last_regime:
                old_r = STRATEGY_ROUTING.get(self._last_regime, DEFAULT_ROUTING)
                msg = (f"🔀 Regime 切换: {self._last_regime.value} → {regime.value}\n"
                       f"策略: {old_r['strategies']} → {routing['strategies']}\n"
                       f"方向偏好: {old_r['direction_bias']} → {routing['direction_bias']}")
                await self._telegram.notify('system', msg)
                logger.info(f"Regime changed: {self._last_regime.value} → {regime.value}")
            self._last_regime = regime

        # 经济日历检查
        if self._calendar:
            cal_event = self._calendar.check_upcoming()
            if cal_event:
                if cal_event['action'] == 'pause':
                    logger.info(f"📅 经济事件 {cal_event['name']} 在 {cal_event['hours_until']:.1f}h 后，暂停开新仓")
                    return
                elif cal_event['action'] == 'reduce':
                    logger.info(f"📅 经济事件 {cal_event['name']} 附近，风险缩至50%")

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

        # 当 regime = ranging 时，追加低 ADX 候选给 mean_reversion
        mr_extra_syms = []
        if 'mean_reversion' in routing.get('strategies', []):
            from src.indicators.trend import adx as calc_adx
            pool_syms = {ts.symbol for ts in pool}
            level1_syms = self._screener.level1_filter(symbols[:150], tickers, klines_dict)
            for sym in level1_syms:
                if sym in pool_syms:
                    continue
                kl = klines_dict.get(sym, [])
                if len(kl) >= 50:
                    adx_val = calc_adx(kl, 14)
                    if adx_val < 30:
                        mr_extra_syms.append(sym)
            if mr_extra_syms:
                logger.info(f"MR extra candidates (ADX<25): {len(mr_extra_syms)} symbols")

        # 遍历候选
        logger.info(f"Scanning {len(pool)} candidates: {[ts.symbol.split(':')[0] for ts in pool[:8]]}...")
        for ts in pool:
            if self._portfolio.count >= max_pos:
                break
            if self._portfolio.has_symbol(ts.symbol):
                continue

            # 拉取 15m K线
            klines_15m = await self._market_data.get_klines(ts.symbol, '15m', 100)
            klines_1h = klines_dict.get(ts.symbol, [])

            # 获取资金费率 (funding_arbitrage 需要)
            funding_rate = 0.0
            hours_to_funding = 99
            if 'funding_arbitrage' in routing.get('strategies', []):
                try:
                    fr = await self._exchange.fetch_funding_rate(ts.symbol)
                    funding_rate = fr['rate']
                    hours_to_funding = fr['hours_to_funding']
                except Exception:
                    pass

            # 信号检测
            signal = self._aggregator.scan(
                ts.symbol, klines_1h, klines_15m,
                routing['strategies'], routing['direction_bias'], cfg,
                funding_rate=funding_rate,
                hours_to_funding=hours_to_funding)

            if not signal:
                continue

            rr_val = signal.risk_reward or 0
            logger.info(f"Signal: {signal.direction.name} {signal.symbol} "
                        f"@{signal.entry_price:.4f} conf={signal.confidence:.2f} "
                        f"RR={rr_val:.1f} strategy={signal.strategy.value}")

            # 记录信号到 DB
            if self._db:
                try:
                    await self._db.log_signal(
                        signal.symbol, signal.direction.name, signal.strategy.value,
                        'triggered', '',
                        f"price={signal.entry_price:.4f} RR={rr_val:.1f} conf={signal.confidence:.2f}")
                except Exception:
                    pass

            # V4 增强检查
            if cfg.get('orderbook', {}).get('enabled', False):
                book = await self._market_data.get_orderbook(signal.symbol)
                ob_analysis = self._ob_filter.analyze(
                    signal.symbol, signal.direction, signal.entry_price, book)
                signal, reason = self._aggregator.enhanced_check(
                    signal, orderbook_analysis=ob_analysis)
                if not signal:
                    logger.info(f"Signal rejected {ts.symbol}: {reason}")
                    if self._db:
                        try:
                            await self._db.log_signal(
                                ts.symbol, '', '', 'rejected', reason, '')
                        except Exception:
                            pass
                    continue

            if cfg.get('multi_exchange', {}).get('enabled', False):
                consensus = self._multi_exchange.check(signal.symbol, signal.direction)
                signal, reason = self._aggregator.enhanced_check(
                    signal, consensus_result=consensus)
                if not signal:
                    logger.info(f"Consensus rejected {ts.symbol}: {reason}")
                    if self._db:
                        try:
                            await self._db.log_signal(
                                ts.symbol, '', '', 'rejected', reason, '')
                        except Exception:
                            pass
                    continue

            # 再次风控
            can_trade, reason = self._risk.pre_trade_check(signal)
            if not can_trade:
                continue

            # 仓位计算
            if self._paper:
                balance = self._risk._current_balance
            else:
                balance = await self._exchange.get_balance()
            cal_scale = self._calendar.get_risk_scale() if self._calendar else 1.0
            risk_scale = self._risk.get_risk_scale() * cal_scale
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

            # 下单 (模拟模式不实际下单)
            from src.core.enums import FillType
            side = 'buy' if signal.direction == Direction.LONG else 'sell'

            if self._paper:
                # 模拟模式: 直接记录，不下单
                fill_type = FillType.MARKET
                logger.info(f"[模拟] 信号触发 {signal.direction.name} {signal.symbol} "
                            f"@{signal.entry_price} qty={pos_size.quantity:.4f} "
                            f"margin={pos_size.margin:.2f}U notional={pos_size.notional:.2f}U "
                            f"risk={pos_size.risk_amount:.2f}U")
            else:
                await self._exchange.set_leverage(signal.symbol, cfg['account']['leverage'])
                order_result, fill_type = await self._smart_router.execute_entry(
                    signal.symbol, side, pos_size.quantity)
                if not order_result:
                    continue

            # 创建持仓 (模拟和实盘都记录)
            pos = self._portfolio.open_position(
                signal, pos_size.margin, pos_size.notional,
                pos_size.quantity, fill_type)

            # 保存到 DB
            if self._db:
                try:
                    tp1 = signal.take_profits[0] if signal.take_profits else (0, 0)
                    tp2 = signal.take_profits[1] if len(signal.take_profits) > 1 else (0, 0)
                    await self._db.save_open_position({
                        'id': pos.id,
                        'symbol': pos.symbol,
                        'direction': pos.direction.name,
                        'strategy': pos.strategy.value,
                        'entry_price': pos.entry_price,
                        'quantity': pos.quantity,
                        'margin': pos.margin,
                        'notional': pos.notional,
                        'stop_loss': pos.stop_loss,
                        'initial_stop': pos.initial_stop,
                        'tp1_price': tp1[0], 'tp1_pct': tp1[1],
                        'tp2_price': tp2[0], 'tp2_pct': tp2[1],
                        'best_price': pos.best_price,
                        'remaining_pct': pos.remaining_pct,
                        'tp1_hit': pos.tp1_hit,
                        'fill_type': pos.fill_type.value,
                        'open_time': pos.open_time,
                        'confidence': signal.confidence,
                        'trend_score': signal.trend_score,
                    })
                    logger.info(f"Position saved to DB: {pos.id}")
                except Exception as e:
                    logger.warning(f"Failed to save position to DB: {e}")

            if not self._paper:
                # 实盘: 挂止损止盈
                await self._order_mgr.place_stop_loss(pos)
                for tp_price, tp_pct in signal.take_profits:
                    qty = pos_size.quantity * tp_pct
                    await self._order_mgr.place_take_profit(pos, tp_price, qty)

            # 通知
            mode_tag = "[模拟] " if self._paper else ""
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

            logger.info(f"{mode_tag}Opened {signal.direction.name} {signal.symbol} "
                        f"@{signal.entry_price} qty={pos_size.quantity:.4f} "
                        f"margin={pos_size.margin:.2f}U notional={pos_size.notional:.2f}U")

        # 第二轮扫描: 低 ADX 候选 (mean_reversion + volatility_breakout)
        ranging_strategies = [s for s in routing.get('strategies', [])
                              if s in ('mean_reversion', 'volatility_breakout')]
        if mr_extra_syms and ranging_strategies and self._portfolio.count < max_pos:
            logger.info(f"Ranging scan: checking {len(mr_extra_syms)} low-ADX symbols with {ranging_strategies}...")
            for sym in mr_extra_syms[:30]:
                if self._portfolio.count >= max_pos:
                    break
                if self._portfolio.has_symbol(sym):
                    continue

                klines_15m = await self._market_data.get_klines(sym, '15m', 100)
                klines_1h = klines_dict.get(sym, [])

                # 获取资金费率 (funding_arbitrage 需要)
                fr_rate = 0.0
                fr_hours = 99
                if 'funding_arbitrage' in ranging_strategies:
                    try:
                        fr = await self._exchange.fetch_funding_rate(sym)
                        fr_rate = fr['rate']
                        fr_hours = fr['hours_to_funding']
                    except Exception:
                        pass

                signal = self._aggregator.scan(
                    sym, klines_1h, klines_15m,
                    ranging_strategies, routing['direction_bias'], cfg,
                    funding_rate=fr_rate,
                    hours_to_funding=fr_hours)

                if not signal:
                    continue

                rr_val = signal.risk_reward or 0
                logger.info(f"MR Signal: {signal.direction.name} {signal.symbol} "
                            f"@{signal.entry_price:.4f} conf={signal.confidence:.2f} "
                            f"RR={rr_val:.1f}")

                if self._db:
                    try:
                        await self._db.log_signal(
                            signal.symbol, signal.direction.name, signal.strategy.value,
                            'triggered', '',
                            f"price={signal.entry_price:.4f} RR={rr_val:.1f} conf={signal.confidence:.2f}")
                    except Exception:
                        pass

                # 风控
                can_trade, reason = self._risk.pre_trade_check(signal)
                if not can_trade:
                    continue

                # 仓位计算
                if self._paper:
                    balance = self._risk._current_balance
                else:
                    balance = await self._exchange.get_balance()
                cal_scale = self._calendar.get_risk_scale() if self._calendar else 1.0
                risk_scale = self._risk.get_risk_scale() * cal_scale
                pos_size = self._sizer.calculate(
                    balance, signal, routing, risk_scale, cfg,
                    self._risk.consecutive_losses)

                if not pos_size:
                    continue

                total_margin = self._portfolio.total_margin() + pos_size.margin
                max_total = cfg.get('risk', {}).get('max_total_margin_pct', 40) / 100
                if total_margin > balance * max_total:
                    continue

                from src.core.enums import FillType
                if self._paper:
                    fill_type = FillType.MARKET
                    logger.info(f"[模拟] MR信号触发 {signal.direction.name} {signal.symbol} "
                                f"@{signal.entry_price} qty={pos_size.quantity:.4f} "
                                f"margin={pos_size.margin:.2f}U notional={pos_size.notional:.2f}U "
                                f"risk={pos_size.risk_amount:.2f}U")
                else:
                    await self._exchange.set_leverage(signal.symbol, cfg['account']['leverage'])
                    order_result, fill_type = await self._smart_router.execute_entry(
                        signal.symbol, 'buy' if signal.direction == Direction.LONG else 'sell',
                        pos_size.quantity)
                    if not order_result:
                        continue

                pos = self._portfolio.open_position(
                    signal, pos_size.margin, pos_size.notional,
                    pos_size.quantity, fill_type)

                if self._db:
                    try:
                        tp1 = signal.take_profits[0] if signal.take_profits else (0, 0)
                        tp2 = signal.take_profits[1] if len(signal.take_profits) > 1 else (0, 0)
                        await self._db.save_open_position({
                            'id': pos.id, 'symbol': pos.symbol,
                            'direction': pos.direction.name,
                            'strategy': pos.strategy.value,
                            'entry_price': pos.entry_price,
                            'quantity': pos.quantity, 'margin': pos.margin,
                            'notional': pos.notional, 'stop_loss': pos.stop_loss,
                            'initial_stop': pos.initial_stop,
                            'tp1_price': tp1[0], 'tp1_pct': tp1[1],
                            'tp2_price': tp2[0], 'tp2_pct': tp2[1],
                            'best_price': pos.best_price,
                            'remaining_pct': pos.remaining_pct,
                            'tp1_hit': pos.tp1_hit,
                            'fill_type': pos.fill_type.value,
                            'open_time': pos.open_time,
                            'confidence': signal.confidence,
                            'trend_score': signal.trend_score,
                        })
                        logger.info(f"Position saved to DB: {pos.id}")
                    except Exception as e:
                        logger.warning(f"Failed to save position to DB: {e}")

                if not self._paper:
                    await self._order_mgr.place_stop_loss(pos)
                    for tp_price, tp_pct in signal.take_profits:
                        await self._order_mgr.place_take_profit(pos, tp_price, pos_size.quantity * tp_pct)

                mode_tag = "[模拟] " if self._paper else ""
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
                logger.info(f"{mode_tag}MR Opened {signal.direction.name} {signal.symbol} "
                            f"@{signal.entry_price} qty={pos_size.quantity:.4f} "
                            f"margin={pos_size.margin:.2f}U notional={pos_size.notional:.2f}U")

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

    async def _adaptive_loop(self):
        """每小时运行自适应参数调整"""
        while self._running:
            await asyncio.sleep(3600)  # 每小时一次
            try:
                if not self._db or not self._adaptive._enabled:
                    continue
                # 从 DB 获取近 7 天交易
                from datetime import timedelta
                since = datetime.utcnow() - timedelta(days=self._adaptive._lookback)
                rows = await self._db.get_trades_since(since)
                if not rows:
                    continue
                from src.core.models import TradeRecord
                from src.core.enums import SignalStrategy, FillType
                trades = []
                for r in rows:
                    try:
                        direction = Direction.LONG if r['direction'] == 'LONG' else Direction.SHORT
                        trades.append(TradeRecord(
                            id=r.get('id', ''),
                            symbol=r['symbol'],
                            direction=direction,
                            strategy=SignalStrategy(r['strategy']),
                            entry_price=r['entry_price'],
                            exit_price=r['exit_price'],
                            quantity=r.get('quantity', 0),
                            margin=r.get('margin', 0),
                            pnl=r['pnl'],
                            fee=r.get('fee', 0),
                            fill_type=FillType.MARKET,
                            open_time=r['open_time'],
                            close_time=r['close_time'],
                            close_reason=r.get('close_reason', ''),
                        ))
                    except Exception:
                        continue
                adjustments = self._adaptive.review(trades, self._cfg)
                if adjustments:
                    logger.info(f"Adaptive adjustments: {adjustments}")
                    # 应用调整到运行时 config (不修改文件)
                    for key, val in adjustments.items():
                        if key in ('confidence_adjustments', 'hour_analysis'):
                            continue  # 仅供分析
                        if isinstance(val, dict) and key in self._cfg:
                            self._cfg[key].update(val)
                            logger.info(f"  Applied: {key} → {val}")
                    await self._telegram.notify('system',
                        f'🔄 自适应调整: {len(adjustments)} 项参数更新')
            except Exception as e:
                logger.warning(f"Adaptive review error: {e}")

    async def _daily_report_loop(self):
        """每日 UTC 00:00 发送日报"""
        while self._running:
            try:
                # 计算到下一个 UTC 00:05 的等待时间
                now = datetime.utcnow()
                next_report = now.replace(hour=0, minute=5, second=0, microsecond=0)
                if now >= next_report:
                    from datetime import timedelta
                    next_report += timedelta(days=1)
                wait_sec = (next_report - now).total_seconds()
                logger.info(f"Daily report scheduled in {wait_sec/3600:.1f}h")
                await asyncio.sleep(wait_sec)

                if not self._db:
                    continue

                # 获取昨天的交易
                from datetime import timedelta
                yesterday = (datetime.utcnow() - timedelta(days=1)).replace(
                    hour=0, minute=0, second=0, microsecond=0)
                today = datetime.utcnow().replace(
                    hour=0, minute=0, second=0, microsecond=0)
                rows = await self._db.get_trades_since(yesterday)
                day_trades = [dict(r) for r in rows
                              if r['close_time'] < today]

                total_trades = len(day_trades)
                wins = sum(1 for t in day_trades if t['pnl'] > 0)
                total_pnl = sum(t['pnl'] for t in day_trades)
                total_fee = sum(t.get('fee', 0) for t in day_trades)
                balance = self._risk._current_balance

                # 按策略统计
                strat_stats = {}
                for t in day_trades:
                    s = t.get('strategy', 'unknown')
                    if s not in strat_stats:
                        strat_stats[s] = {'count': 0, 'pnl': 0, 'wins': 0}
                    strat_stats[s]['count'] += 1
                    strat_stats[s]['pnl'] += t['pnl']
                    if t['pnl'] > 0:
                        strat_stats[s]['wins'] += 1

                # 按平仓原因统计
                reason_stats = {}
                for t in day_trades:
                    r = t.get('close_reason', 'unknown')
                    reason_stats[r] = reason_stats.get(r, 0) + 1

                date_str = yesterday.strftime('%Y-%m-%d')
                win_rate = wins / total_trades if total_trades > 0 else 0
                pnl_pct = total_pnl / balance * 100 if balance > 0 else 0

                # 基础日报
                await self._telegram.daily_report({
                    'date': date_str,
                    'trades': total_trades,
                    'win_rate': win_rate,
                    'pnl': total_pnl,
                    'pnl_pct': pnl_pct,
                    'fee': total_fee,
                })

                # 详细策略分析
                if strat_stats:
                    detail = f"📈 策略分析 {date_str}\n"
                    for s, st in strat_stats.items():
                        wr = st['wins'] / st['count'] if st['count'] > 0 else 0
                        detail += f"  {s}: {st['count']}笔 胜率{wr*100:.0f}% PnL={st['pnl']:+.2f}U\n"
                    detail += f"\n平仓原因: {reason_stats}\n"
                    detail += f"余额: {balance:.2f}U"
                    await self._telegram.notify('daily_report', detail)

                logger.info(f"Daily report sent: {date_str} trades={total_trades} pnl={total_pnl:+.2f}U")

                # 日统计重置
                self._risk.daily_reset()
                logger.info("Daily risk counters reset (UTC 00:05)")

                # 周一重置周统计
                if datetime.utcnow().weekday() == 0:
                    self._risk.weekly_reset()
                    logger.info("Weekly risk counters reset (Monday)")

            except Exception as e:
                logger.warning(f"Daily report error: {e}")
                await asyncio.sleep(60)

    async def _telegram_command_loop(self):
        """监听 Telegram 命令: /status /trades /pnl"""
        if not self._telegram._enabled or not self._telegram._token:
            return

        import ssl
        import aiohttp

        base_url = f"https://api.telegram.org/bot{self._telegram._token}"
        offset = 0
        ssl_ctx = ssl.create_default_context()
        ssl_ctx.check_hostname = False
        ssl_ctx.verify_mode = ssl.CERT_NONE

        while self._running:
            try:
                connector = aiohttp.TCPConnector(ssl=ssl_ctx)
                async with aiohttp.ClientSession(connector=connector) as session:
                    url = f"{base_url}/getUpdates?offset={offset}&timeout=30"
                    async with session.get(url, timeout=aiohttp.ClientTimeout(total=35)) as resp:
                        if resp.status != 200:
                            await asyncio.sleep(5)
                            continue
                        data = await resp.json()

                if not data.get('ok') or not data.get('result'):
                    await asyncio.sleep(2)
                    continue

                for update in data['result']:
                    offset = update['update_id'] + 1
                    msg = update.get('message', {})
                    text = msg.get('text', '').strip()
                    chat_id = str(msg.get('chat', {}).get('id', ''))

                    # 只响应配置的 chat_id
                    if chat_id != str(self._telegram._chat_id):
                        continue

                    if text == '/status':
                        await self._cmd_status()
                    elif text == '/trades':
                        await self._cmd_trades()
                    elif text == '/pnl':
                        await self._cmd_pnl()
                    elif text == '/calendar':
                        await self._cmd_calendar()
                    elif text == '/help':
                        await self._telegram.notify('system',
                            "📋 可用命令:\n/status — 当前状态\n/trades — 最近交易\n"
                            "/pnl — 盈亏统计\n/calendar — 经济日历")

            except asyncio.TimeoutError:
                pass
            except Exception as e:
                logger.debug(f"Telegram poll error: {e}")
                await asyncio.sleep(10)

    async def _cmd_status(self):
        """处理 /status 命令"""
        balance = self._risk._current_balance
        initial = self._risk._initial_balance
        pnl = balance - initial
        pnl_pct = pnl / initial * 100 if initial > 0 else 0
        positions = list(self._portfolio.positions.values())

        regime_str = self._last_regime.value if self._last_regime else 'unknown'
        session_name, _ = self._session.get_current_session()

        msg = (f"📊 <b>V4-Lite 状态</b>\n"
               f"余额: {balance:.2f}U | PnL: {pnl:+.2f}U ({pnl_pct:+.1f}%)\n"
               f"持仓: {len(positions)}/{self._cfg.get('risk', {}).get('max_open_positions', 4)}\n"
               f"Regime: {regime_str} | 时段: {session_name.value}\n"
               f"连亏: {self._risk._consecutive_losses} | 缩放: {self._risk.get_risk_scale():.0%}")

        if positions:
            msg += "\n\n<b>持仓:</b>"
            for p in positions:
                try:
                    ticker = await self._exchange.fetch_ticker(p.symbol)
                    price = ticker.get('last', p.entry_price)
                    upnl = p.unrealized_pnl(price)
                    r_val = p.current_r(price)
                except Exception:
                    upnl = 0
                    r_val = 0
                msg += (f"\n  {p.symbol.split(':')[0]} {p.direction.name} "
                        f"@{p.entry_price:.4f} PnL={upnl:+.2f}U R={r_val:.1f} "
                        f"{int(p.holding_minutes)}分")
        await self._telegram.notify('system', msg)

    async def _cmd_trades(self):
        """处理 /trades 命令"""
        if not self._db:
            await self._telegram.notify('system', '⚠️ 数据库未连接')
            return
        trades = await self._db.get_all_trades()
        recent = trades[:5]
        if not recent:
            await self._telegram.notify('system', '暂无交易记录')
            return

        msg = f"📋 <b>最近 {len(recent)} 笔交易</b>\n"
        for t in recent:
            pnl = t.get('pnl', 0)
            emoji = "🟢" if pnl > 0 else "🔴"
            sym = t.get('symbol', '').split(':')[0]
            ct = t.get('close_time')
            time_str = ct.strftime('%m-%d %H:%M') if ct else ''
            msg += (f"\n{emoji} {sym} {t.get('direction','')} | {t.get('strategy','')}\n"
                    f"   PnL: {pnl:+.2f}U | {t.get('close_reason','')} | {time_str}")
        await self._telegram.notify('system', msg)

    async def _cmd_pnl(self):
        """处理 /pnl 命令"""
        if not self._db:
            await self._telegram.notify('system', '⚠️ 数据库未连接')
            return
        summary = await self._db.get_trade_summary()
        total = summary.get('total_trades', 0)
        wins = summary.get('wins', 0)
        total_pnl = summary.get('total_pnl', 0) or 0
        total_fee = summary.get('total_fee', 0) or 0
        total_funding = summary.get('total_funding_fee', 0) or 0
        net = total_pnl - total_fee - total_funding
        wr = wins / total * 100 if total > 0 else 0

        msg = (f"💰 <b>盈亏统计</b>\n"
               f"总交易: {total} 笔 | 胜率: {wr:.0f}%\n"
               f"毛利: {total_pnl:+.2f}U\n"
               f"手续费: {total_fee:.2f}U | 资金费: {total_funding:.4f}U\n"
               f"净盈亏: {net:+.2f}U\n\n"
               f"<b>平仓原因:</b>\n"
               f"  止损: {summary.get('stop_loss_count', 0)}\n"
               f"  TP1: {summary.get('tp1_count', 0)} | TP2: {summary.get('tp2_count', 0)}\n"
               f"  移动止盈: {summary.get('trailing_count', 0)}\n"
               f"  时间止损: {summary.get('time_stop_count', 0)}")
        await self._telegram.notify('system', msg)

    async def _cmd_calendar(self):
        """处理 /calendar 命令"""
        if not self._calendar:
            await self._telegram.notify('system', '📅 经济日历已禁用，全天交易模式')
            return
        now = datetime.utcnow()
        upcoming = []
        for event in self._calendar._events:
            diff = (event['time'] - now).total_seconds() / 3600
            if -1 <= diff <= 72:
                upcoming.append(event)
        if not upcoming:
            await self._telegram.notify('system', '📅 72小时内无重大经济事件')
            return

        msg = "📅 <b>经济日历 (72h)</b>\n"
        for e in upcoming[:10]:
            diff = (e['time'] - now).total_seconds() / 3600
            if diff < 0:
                time_str = f"{abs(diff):.1f}h 前"
            else:
                time_str = f"{diff:.1f}h 后"
            icon = "🔴" if e['impact'] == 'high' else "🟡"
            msg += f"\n{icon} {e['name']} — {e['time'].strftime('%m-%d %H:%M')} UTC ({time_str})"

        scale = self._calendar.get_risk_scale()
        if scale < 1.0:
            msg += f"\n\n⚠️ 当前风险缩放: {scale:.0%}"
        await self._telegram.notify('system', msg)

    async def _signal_log_cleanup_loop(self):
        """每天清理 7 天前的信号日志"""
        while self._running:
            await asyncio.sleep(86400)  # 24小时
            if self._db:
                try:
                    await self._db.cleanup_signal_log(7)
                    logger.info("Signal log cleanup done")
                except Exception as e:
                    logger.debug(f"Signal log cleanup error: {e}")

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
                        'strategy': p.strategy.value,
                        'entry': f"{p.entry_price:.4f}",
                        'quantity': round(p.quantity, 4),
                        'margin': round(p.margin, 2),
                        'notional': round(p.notional, 2),
                        'stop_loss': f"{p.stop_loss:.4f}",
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
        if self._paper:
            self._paper_monitor.stop()
        else:
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
        if self._db:
            await self._db.close()
        await self._telegram.notify('system', '🔴 Trading engine stopped')
        logger.info("Trading engine stopped")
