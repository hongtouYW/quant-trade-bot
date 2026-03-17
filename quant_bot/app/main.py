"""量化交易机器人 - 主循环"""
import os
import sys
import time
import logging
import threading
import json
from datetime import datetime, date

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.config import load_config, get
from app.data.exchange_client import ExchangeClient
from app.data.ohlcv_cache import OHLCVCache
from app.universe.candidate_pool import CandidatePool
from app.universe.trend_scoring import TrendScoring
from app.strategy.signal_engine import SignalEngine, EntryRefiner, FakeBreakoutFilter
from app.risk.risk_engine import RiskEngine
from app.risk.position_sizer import PositionSizer
from app.risk.cooldown_engine import CooldownEngine
from app.risk.correlation_guard import CorrelationGuard
from app.execution.position_manager import PositionManager
from app.execution.slippage_guard import SlippageGuard
from app.execution.stop_manager import StopManager
from app.monitoring import notifier

log = logging.getLogger(__name__)


class QuantBot:
    """量化交易主引擎"""

    def __init__(self, config_path=None, sandbox=True, paper_mode=True):
        self.config = load_config(config_path)
        self.sandbox = sandbox
        self.paper_mode = paper_mode
        self.running = False
        self.active_pool = []
        self.trend_scores = {}
        self.snapshots = {}
        self.last_pool_refresh = 0
        self.pool_refresh_interval = 300  # 5分钟刷新候选池
        self.last_heartbeat = 0
        self.heartbeat_interval = get('monitoring', 'heartbeat_seconds', 60)
        self.cycle_count = 0
        self.start_time = None
        self.logs = []
        self._max_logs = 500

        # 初始化组件
        api_key = os.environ.get('BINANCE_API_KEY', '')
        api_secret = os.environ.get('BINANCE_API_SECRET', '')

        self.exchange = ExchangeClient(api_key, api_secret, sandbox=sandbox)
        self.cache = OHLCVCache(self.exchange)
        self.candidate_pool = CandidatePool(self.exchange, self.cache)
        self.trend_scoring = TrendScoring(self.cache)
        self.signal_engine = SignalEngine(self.cache)
        self.entry_refiner = EntryRefiner(self.cache)
        self.fake_filter = FakeBreakoutFilter(self.cache)
        self.risk_engine = RiskEngine()
        self.position_sizer = PositionSizer()
        self.cooldown = CooldownEngine()
        self.correlation = CorrelationGuard(self.cache)
        self.slippage_guard = SlippageGuard(self.exchange)
        self.stop_manager = StopManager(self.exchange)
        self.position_manager = PositionManager(
            self.exchange, self.cache, self.risk_engine, self.cooldown
        )

        # Telegram
        tg_token = os.environ.get('TELEGRAM_BOT_TOKEN', '')
        if tg_token:
            notifier.init_telegram(tg_token)

    def _log(self, level, msg):
        ts = datetime.utcnow().strftime('%H:%M:%S')
        entry = {'ts': ts, 'level': level, 'msg': msg}
        self.logs.append(entry)
        if len(self.logs) > self._max_logs:
            self.logs = self.logs[-self._max_logs:]
        getattr(log, level, log.info)(msg)

    def start(self):
        if self.running:
            return
        self.running = True
        self.start_time = datetime.utcnow()
        mode = '模拟' if self.paper_mode else '实盘'
        self._log('info', f"QuantBot 启动 ({mode}模式)")
        notifier.send_telegram(f"🚀 QuantBot 已启动 ({mode}模式)")
        thread = threading.Thread(target=self._run_loop, daemon=True)
        thread.start()

    def stop(self):
        self.running = False
        self._log('info', "QuantBot 已停止")
        notifier.send_telegram("🛑 QuantBot 已停止")

    def _run_loop(self):
        while self.running:
            try:
                self.cycle_count += 1
                self._cycle()
                time.sleep(15)
            except Exception as e:
                self._log('error', f"主循环异常: {e}")
                notifier.notify_risk_event("主循环异常", str(e))
                time.sleep(30)

    def _cycle(self):
        now = time.time()

        # 定时刷新候选池
        if now - self.last_pool_refresh > self.pool_refresh_interval:
            self._refresh_pool()
            self.last_pool_refresh = now

        # 心跳通知
        if now - self.last_heartbeat > self.heartbeat_interval:
            self._send_heartbeat()
            self.last_heartbeat = now

        # 时间白名单检查
        hour = datetime.utcnow().hour
        whitelist = get('schedule', 'whitelist_hours', list(range(24)))
        in_schedule = hour in whitelist

        # 获取账户余额
        balance = self.exchange.fetch_balance()
        equity = balance.get('equity', 0)
        if equity <= 0 and not self.paper_mode:
            self._log('warning', "未检测到账户余额")
            notifier.notify_risk_event("数据异常", "未检测到账户余额")
            return

        if self.paper_mode and equity <= 0:
            equity = get('account', 'initial_balance', 2000)

        # 管理现有持仓
        self.position_manager.sync_positions()
        self.position_manager.manage_all(self.trend_scores)

        # 检查止损移动 - 同步到交易所
        if not self.paper_mode:
            self._sync_exchange_stops()

        # 检查是否可以开新仓
        can_open, reason = self.risk_engine.can_open_new_positions(equity)
        if not can_open:
            if self.cycle_count % 20 == 0:
                self._log('info', f"禁止开仓: {reason}")
            if 'daily_loss' in reason:
                notifier.notify_risk_event("日亏损限制触发", f"原因: {reason}")
            return

        if not in_schedule:
            return

        if not self.active_pool:
            return

        # 扫描信号
        allowed_grade = self.risk_engine.get_allowed_grade()

        for item in self.active_pool:
            symbol = item['symbol']
            snap = self.snapshots.get(symbol, item)
            regime = snap.get('regime', 'UNKNOWN')
            direction = snap.get('direction', 0)
            score = snap.get('final_score', 0)
            grade = self.trend_scoring.grade(score)
            setup_type = None

            # 冷却检查（含策略级）
            if self.cooldown.block(symbol, strategy=setup_type):
                continue

            # 相关性检查
            if self.correlation.block(symbol, direction, self.position_manager.positions):
                continue

            # 极端市场禁止
            if regime == 'EXTREME':
                continue

            # 最低分数检查
            min_score = get('trend_filter', 'score_min_trade', 62)
            if score < min_score:
                continue

            # 等级检查
            if allowed_grade == 'A' and grade != 'A':
                continue

            # 震荡市只允许A级
            if regime == 'RANGING' and grade != 'A':
                continue

            # 方向检查
            if direction == 0:
                continue

            # 已持有该币种
            if any(p.symbol == symbol for p in self.position_manager.positions):
                continue

            # 寻找入场信号
            setup = self.signal_engine.find_setup(symbol, direction, snap)
            if not setup:
                continue
            setup_type = setup.setup_type

            # 假突破过滤
            if self.fake_filter.reject(setup):
                self.cooldown.record_fake_breakout(symbol, direction)
                self._log('info', f"假突破过滤: {symbol}")
                continue

            # 1m精细确认
            refined = self.entry_refiner.confirm(setup)
            if not refined:
                continue

            # 仓位计算
            order_plan = self.position_sizer.build_plan(refined, equity, regime)
            if not order_plan:
                continue

            # 风控审批
            approved, reason = self.risk_engine.approve_order(order_plan, equity)
            if not approved:
                self._log('info', f"风控拒绝 {symbol}: {reason}")
                continue

            # 滑点检查
            if not self.paper_mode:
                slip_ok, slip_pct = self.slippage_guard.check(
                    symbol, order_plan['side'], order_plan['size'], order_plan['entry']
                )
                if not slip_ok:
                    self._log('warning', f"滑点过高 {symbol}: {slip_pct:.3f}%")
                    continue

            # 执行入场
            self._execute_entry(order_plan)
            break  # 每个周期最多开一笔

    def _refresh_pool(self):
        try:
            self._log('info', "刷新候选池...")
            candidates = self.candidate_pool.build()

            scored = []
            for c in candidates:
                symbol = c['symbol']
                m_score, q_score, final, direction, regime = self.trend_scoring.score_symbol(symbol)
                c['momentum_score'] = m_score
                c['quality_score'] = q_score
                c['final_score'] = final
                c['direction'] = direction
                c['regime'] = regime
                c['grade'] = self.trend_scoring.grade(final)
                self.snapshots[symbol] = c
                self.trend_scores[symbol] = final
                scored.append(c)

            scored.sort(key=lambda x: x['final_score'], reverse=True)
            pool_size = get('market', 'active_pool_size', 12)
            self.active_pool = scored[:pool_size]

            symbols = [f"{s['symbol']}({s.get('grade','?')})" for s in self.active_pool[:8]]
            self._log('info', f"活跃池 ({len(self.active_pool)}): {', '.join(symbols)}")
            self.correlation.clear_cache()

        except Exception as e:
            self._log('error', f"候选池刷新失败: {e}")
            notifier.notify_risk_event("数据异常", f"候选池刷新失败: {e}")

    def _execute_entry(self, order_plan):
        symbol = order_plan['symbol']
        dir_str = '做多' if order_plan['direction'] == 1 else '做空'
        self._log('info', f"开仓: {dir_str} {symbol} @ {order_plan['entry']:.4f} 仓位={order_plan['size']:.4f}")

        if self.paper_mode:
            self.position_manager.add_position(order_plan, {'id': f'paper_{int(time.time())}'})
            notifier.notify_trade_open(order_plan)
            return

        # 实盘执行 - 带重试
        try:
            self.exchange.set_leverage(symbol, get('account', 'leverage', 10))

            order = None
            for attempt in range(3):  # 最多重试2次(共3次)
                order = self.exchange.create_order(
                    symbol, order_plan['side'], order_plan['size'],
                    order_type='market',
                )
                if order:
                    break
                self._log('warning', f"下单失败 {symbol}, 重试 {attempt+1}/2")
                time.sleep(1)

            if order:
                pos = self.position_manager.add_position(order_plan, order)
                notifier.notify_trade_open(order_plan)

                # 挂交易所止损单
                stop_side = 'sell' if order_plan['direction'] == 1 else 'buy'
                stop_result = self.stop_manager.place_stop(
                    symbol, stop_side, order_plan['size'], order_plan['stop']
                )
                if not stop_result:
                    notifier.notify_risk_event("止损挂单失败", f"{symbol} 止损 {order_plan['stop']:.4f}")
            else:
                self._log('error', f"下单最终失败: {symbol}")
                notifier.notify_risk_event("下单失败", f"{symbol} {dir_str}")
        except Exception as e:
            self._log('error', f"执行异常 {symbol}: {e}")
            notifier.notify_risk_event("交易所接口异常", f"{symbol}: {e}")

    def _sync_exchange_stops(self):
        """将内存止损同步到交易所"""
        for pos in self.position_manager.positions:
            current_stop = self.stop_manager._stop_orders.get(pos.symbol)
            if current_stop:
                # 如果止损价变化了(移动止损)，更新交易所
                stop_side = 'sell' if pos.direction == 1 else 'buy'
                self.stop_manager.update_stop(pos.symbol, stop_side, pos.size, pos.stop_loss)

    def _send_heartbeat(self):
        """发送心跳通知"""
        status = {
            'positions': len(self.position_manager.positions),
            'daily_pnl': self.risk_engine.daily_pnl,
            'daily_pnl_pct': round(self.risk_engine.daily_pnl_pct * 100, 2),
            'trades': self.risk_engine.today_trades,
            'wins': self.risk_engine.today_wins,
            'losses': self.risk_engine.today_losses,
            'pool_size': len(self.active_pool),
        }
        notifier.notify_heartbeat(status)

    def get_status(self):
        balance = {'equity': get('account', 'initial_balance', 2000), 'available': 0}
        try:
            if not self.paper_mode:
                balance = self.exchange.fetch_balance()
        except Exception:
            pass

        risk_status = self.risk_engine.get_status()
        positions = self.position_manager.get_positions_summary()

        return {
            'running': self.running,
            'paper_mode': self.paper_mode,
            'sandbox': self.sandbox,
            'uptime': str(datetime.utcnow() - self.start_time).split('.')[0] if self.start_time else '0:00:00',
            'cycle_count': self.cycle_count,
            'equity': balance.get('equity', 0),
            'available': balance.get('available', 0),
            'active_pool_size': len(self.active_pool),
            'active_pool': [
                {
                    'symbol': s['symbol'],
                    'score': round(s.get('final_score', 0), 1),
                    'direction': '🟢' if s.get('direction') == 1 else ('🔴' if s.get('direction') == -1 else '⚪'),
                    'regime': s.get('regime', '?'),
                    'grade': s.get('grade', '?'),
                    'momentum': round(s.get('momentum_score', 0), 1),
                    'quality': round(s.get('quality_score', 0), 1),
                }
                for s in self.active_pool[:15]
            ],
            'positions': positions,
            'risk': risk_status,
            'cooldowns': self.cooldown.get_status(),
            'trade_history': [
                {
                    'symbol': t.symbol,
                    'direction': '做多' if t.direction == 1 else '做空',
                    'pnl': round(t.pnl, 2),
                    'pnl_pct': round(t.pnl_pct * 100, 2),
                    'reason': t.close_reason,
                    'setup': t.setup_type,
                    'closed_at': t.closed_at.strftime('%H:%M:%S'),
                }
                for t in self.position_manager.trade_history[-50:]
            ],
            'logs': self.logs[-100:],
        }


# 全局实例
bot = None

def get_bot():
    global bot
    return bot

def create_bot(**kwargs):
    global bot
    bot = QuantBot(**kwargs)
    return bot
