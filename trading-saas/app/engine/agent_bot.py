"""Agent Bot - Single-agent trading bot.

Refactored from PaperTradingAssistant in paper_trader.py.
Each agent gets its own AgentBot running in a thread, using:
- SignalAnalyzer for market analysis
- OrderExecutor for real Binance trades
- RiskManager for risk control
- SQLAlchemy models for persistence
"""
import json
import time
import threading
import traceback
import requests
from collections import deque
from datetime import datetime, timezone
from decimal import Decimal

from ..extensions import db
from ..models.agent import Agent
from ..models.agent_config import AgentApiKey, AgentTradingConfig, AgentTelegramConfig
from ..models.trade import Trade, DailyStat
from ..models.bot_state import BotState
from ..models.audit import AuditLog
from ..services.encryption_service import EncryptionService

from .signal_analyzer import (
    analyze_signal, calculate_position_size, calculate_stop_take,
    fetch_price, DEFAULT_WATCHLIST, COIN_TIERS, SKIP_COINS,
)
from .order_executor import OrderExecutor
from .risk_manager import RiskManager


class AgentBot:
    """Trading bot for a single agent. Designed to run in its own thread."""

    def __init__(self, agent_id: int, app):
        """
        Args:
            agent_id: Agent database ID
            app: Flask app instance (for app context in threads)
        """
        self.agent_id = agent_id
        self.app = app
        self._stop_event = threading.Event()
        self._paused = False

        # In-memory position tracking (symbol -> position dict)
        self.positions = {}
        # Cooldown tracking (symbol -> datetime)
        self.cooldowns = {}
        # Min hold time protection (3 hours)
        self.min_hold_minutes = 180
        # Max hold time forced close (48 hours)
        self.max_hold_minutes = 2880

        # Lock to serialize position opens (prevent race conditions)
        self._open_lock = threading.Lock()
        # Consecutive error counter for backoff
        self._consecutive_errors = 0

        self.executor = None
        self.risk_manager = None
        self.config = {}
        self.scan_count = 0
        # Activity log ring buffer (last 100 entries)
        self.activity_log = deque(maxlen=100)
        # Last scan result for signal panel
        self.last_scan_result = {
            'last_scan_time': None,
            'scan_interval': 60,
            'signals_analyzed': 0,
            'signals_passed': 0,
            'signals_filtered': [],
            'positions_opened': 0,
        }

    def _log(self, level: str, message: str):
        """Log a message to both stdout and the in-memory activity log."""
        ts = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
        print(f"[AgentBot-{self.agent_id}] {message}")
        self.activity_log.append({
            'time': ts,
            'level': level,
            'message': message,
        })

    def _load_config(self):
        """Load agent config from database."""
        agent = Agent.query.get(self.agent_id)
        if not agent or not agent.is_active or not agent.is_trading_enabled:
            raise RuntimeError(f"Agent {self.agent_id} not active or trading not enabled")

        # Load API keys
        api_key_record = AgentApiKey.query.filter_by(agent_id=self.agent_id).first()
        if not api_key_record or not api_key_record.permissions_verified:
            raise RuntimeError(f"Agent {self.agent_id} has no verified API keys")

        # Decrypt API keys (stored as single encrypted JSON blob)
        import json as _json
        combined_json = EncryptionService.decrypt(
            api_key_record.binance_api_key_enc,
            api_key_record.encryption_iv,
        )
        keys = _json.loads(combined_json)
        api_key = keys['k']
        api_secret = keys['s']

        # Initialize executor
        self.executor = OrderExecutor(
            api_key=api_key,
            api_secret=api_secret,
            is_testnet=api_key_record.is_testnet,
        )

        # Load trading config
        trading_config = AgentTradingConfig.query.filter_by(
            agent_id=self.agent_id
        ).first()
        if trading_config:
            self.config = trading_config.to_dict()
        else:
            self.config = {
                'strategy_version': 'v4.2',
                'initial_capital': 2000,
                'max_positions': 15,
                'max_position_size': 500,
                'max_leverage': 3,
                'min_score': 60,
                'long_min_score': 70,
                'fee_rate': 0.0005,
                'cooldown_minutes': 30,
                'roi_stop_loss': -10,
                'roi_trailing_start': 6,
                'roi_trailing_distance': 3,
                'daily_loss_limit': 200,
                'max_drawdown_pct': 20,
                'enable_trend_filter': True,
                'enable_btc_filter': True,
                'short_bias': 1.05,
            }

        # Initialize risk manager
        self.risk_manager = RiskManager(self.agent_id, self.config)

        self._config_reload_counter = 0

        # Load existing open trades into memory
        open_trades = Trade.query.filter_by(
            agent_id=self.agent_id, status='OPEN'
        ).all()
        for trade in open_trades:
            self.positions[trade.symbol] = {
                'trade_id': trade.id,
                'direction': trade.direction,
                'entry_price': float(trade.entry_price),
                'amount': float(trade.amount),
                'leverage': trade.leverage,
                'stop_loss': float(trade.stop_loss) if trade.stop_loss else 0,
                'take_profit': float(trade.take_profit) if trade.take_profit else 0,
                'peak_roi': float(trade.peak_roi) if trade.peak_roi else 0,
                'entry_time': trade.entry_time.strftime('%Y-%m-%d %H:%M:%S'),
                'score': trade.score or 0,
                'open_fee': float(trade.fee) if trade.fee else 0,
                'roi_stop_loss': float(self.config.get('roi_stop_loss', -10)),
                'roi_trailing_start': float(self.config.get('roi_trailing_start', 6)),
                'roi_trailing_distance': float(self.config.get('roi_trailing_distance', 3)),
            }

        self._log('info', f"Config loaded. Strategy: {self.config.get('strategy_version')}, "
                  f"Open positions: {len(self.positions)}")

        # Reconcile: detect ghost positions on Binance not tracked in DB
        self._reconcile_binance_positions()

    def _reload_trading_config(self):
        """Hot-reload trading config from DB (lightweight, no API key reload)."""
        try:
            trading_config = AgentTradingConfig.query.filter_by(
                agent_id=self.agent_id
            ).first()
            if trading_config:
                new_config = trading_config.to_dict()
                if new_config != self.config:
                    self._log('info', f"Config updated: "
                              f"max_positions={new_config.get('max_positions')}, "
                              f"min_score={new_config.get('min_score')}, "
                              f"roi_stop_loss={new_config.get('roi_stop_loss')}")
                    self.config = new_config
                    self.risk_manager = RiskManager(self.agent_id, self.config)
        except Exception as e:
            self._log('error', f"Config reload failed: {e}")

    def _fetch_funding_fee(self, symbol: str, entry_time_str: str) -> float:
        """Fetch actual funding fee from Binance for a position."""
        try:
            bn_symbol = symbol.replace('/', '')
            since_ms = 0
            if entry_time_str:
                entry_dt = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
                since_ms = int(entry_dt.timestamp() * 1000)

            incomes = self.executor.exchange.fapiPrivateGetIncome({
                'symbol': bn_symbol,
                'incomeType': 'FUNDING_FEE',
                'startTime': since_ms,
                'limit': 100,
            })
            # Positive = received, negative = paid
            # Return as cost (positive = we paid)
            total = sum(float(inc['income']) for inc in incomes)
            return -total  # flip: positive income = negative cost
        except Exception as e:
            self._log('warn', f"Funding fee fetch failed for {symbol}: {e}")
            # Fallback to estimate
            position_value = 0
            pos = self.positions.get(symbol)
            if pos:
                position_value = pos['amount'] * pos['leverage']
            holding_hours = 0
            if entry_time_str:
                try:
                    entry_dt = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
                    holding_hours = (datetime.now() - entry_dt).total_seconds() / 3600
                except Exception:
                    pass
            return position_value * 0.0001 * (holding_hours / 8)

    def _reconcile_binance_positions(self):
        """Detect positions on Binance that the DB doesn't know about.

        This catches 'ghost' positions caused by:
        - DB marked CLOSED but Binance close order failed
        - Duplicate opens where only one was tracked in memory
        - Bot crash after Binance order but before DB write
        """
        try:
            binance_positions = self.executor.get_open_positions()
            if not binance_positions:
                return

            db_symbols = set(self.positions.keys())
            for bpos in binance_positions:
                symbol = bpos['symbol']
                if symbol not in db_symbols:
                    self._log('warn',
                              f"GHOST POSITION detected: {symbol} "
                              f"({bpos['side']}, {bpos['contracts']} contracts, "
                              f"entry ${bpos['entry_price']:.6f}) exists on Binance "
                              f"but NOT in DB. Sending alert.")
                    self._send_telegram(
                        f"⚠️ <b>Ghost Position Detected</b>\n"
                        f"Symbol: {symbol}\n"
                        f"Side: {bpos['side']}\n"
                        f"Contracts: {bpos['contracts']}\n"
                        f"Entry: ${bpos['entry_price']:.6f}\n"
                        f"Unrealized PnL: {bpos['unrealized_pnl']:.2f}U\n\n"
                        f"This position is NOT tracked by the bot.\n"
                        f"Please close it manually on Binance."
                    )
        except Exception as e:
            self._log('error', f"Binance reconciliation failed: {e}")

    def _update_state(self, status: str, error: str = None):
        """Update bot state in database."""
        state = BotState.query.filter_by(agent_id=self.agent_id).first()
        if not state:
            state = BotState(agent_id=self.agent_id)
            db.session.add(state)

        state.status = status
        state.last_scan_at = datetime.now(timezone.utc)
        state.scan_count = self.scan_count

        if error:
            state.last_error = error
            state.error_count = (state.error_count or 0) + 1
        elif status == 'running':
            state.last_error = None

        if status == 'running' and not state.started_at:
            state.started_at = datetime.now(timezone.utc)
        elif status == 'stopped':
            state.started_at = None
            state.pid = None

        db.session.commit()

    def _send_telegram(self, message: str):
        """Send Telegram notification for this agent."""
        try:
            tg_config = AgentTelegramConfig.query.filter_by(
                agent_id=self.agent_id
            ).first()
            if not tg_config or not tg_config.is_enabled:
                return

            bot_token = EncryptionService.decrypt(
                tg_config.bot_token_enc,
                tg_config.encryption_iv,
            )
            chat_id = tg_config.chat_id

            url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
            requests.post(url, json={
                'chat_id': chat_id,
                'text': message,
                'parse_mode': 'HTML',
            }, timeout=10)
        except Exception as e:
            print(f"[AgentBot-{self.agent_id}] Telegram send failed: {e}")

    # ─── Open Position ───────────────────────────────────────

    def _open_position(self, symbol: str, analysis: dict):
        """Open a new position."""
        # Serialize concurrent opens — only one at a time per bot instance
        if not self._open_lock.acquire(blocking=False):
            self._log('warn', f"Skip {symbol}: another position open in progress")
            return

        try:
            # ── DB-level deduplication guard ────────────────────────────────
            # Check DB directly, not just in-memory dict.
            # This prevents duplicates when bot restarts after a partial write
            # (order sent to Binance but DB commit failed before tracking).
            existing = Trade.query.filter_by(
                agent_id=self.agent_id, symbol=symbol, status='OPEN'
            ).first()
            if existing:
                self._log('warn', f"Skip {symbol}: open trade #{existing.id} already in DB")
                # Re-sync to in-memory if somehow missing
                if symbol not in self.positions:
                    self.positions[symbol] = {
                        'trade_id': existing.id,
                        'direction': existing.direction,
                        'entry_price': float(existing.entry_price),
                        'amount': float(existing.amount),
                        'leverage': existing.leverage,
                        'stop_loss': float(existing.stop_loss) if existing.stop_loss else 0,
                        'take_profit': float(existing.take_profit) if existing.take_profit else 0,
                        'peak_roi': float(existing.peak_roi) if existing.peak_roi else 0,
                        'entry_time': existing.entry_time.strftime('%Y-%m-%d %H:%M:%S'),
                        'score': existing.score or 0,
                        'roi_stop_loss': float(self.config.get('roi_stop_loss', -10)),
                        'roi_trailing_start': float(self.config.get('roi_trailing_start', 6)),
                        'roi_trailing_distance': float(self.config.get('roi_trailing_distance', 3)),
                    }
                return
            # ────────────────────────────────────────────────────────────────

            score = analysis['score']
            direction = analysis['direction']
            entry_price = analysis['price']

            # Calculate position size
            positions_list = list(self.positions.values())
            used_margin = sum(p['amount'] for p in positions_list)
            available = float(self.config.get('initial_capital', 2000)) - used_margin

            # Get risk-adjusted multiplier
            risk_multiplier = self.risk_manager.get_position_multiplier(positions_list)

            amount, leverage = calculate_position_size(
                score, available, self.config, symbol
            )

            # Apply risk multiplier
            if risk_multiplier < 1.0:
                amount = int(amount * risk_multiplier)

            if amount < 50:
                return

            # Calculate stop/take
            stp = calculate_stop_take(entry_price, direction, leverage, self.config)

            # Execute real order on Binance
            order = self.executor.open_position(symbol, direction, amount, leverage)
            if not order:
                self._log('error', f"Order failed for {symbol}")
                return

            # Use actual fill price and fee from exchange
            fill_price = order['price']
            open_fee = order.get('fee', 0)

            # Recalculate stop/take with actual fill price
            stp = calculate_stop_take(fill_price, direction, leverage, self.config)

            # Record trade in database
            trade = Trade(
                agent_id=self.agent_id,
                symbol=symbol,
                direction=direction,
                entry_price=Decimal(str(fill_price)),
                amount=Decimal(str(amount)),
                leverage=leverage,
                stop_loss=Decimal(str(stp['stop_loss'])),
                take_profit=Decimal(str(stp['take_profit'])),
                entry_time=datetime.now(timezone.utc),
                status='OPEN',
                fee=Decimal(str(round(open_fee, 6))),
                score=score,
                binance_order_id=order.get('order_id'),
                strategy_version=self.config.get('strategy_version', 'v4.2'),
            )
            db.session.add(trade)
            db.session.commit()

            # Track in memory
            self.positions[symbol] = {
                'trade_id': trade.id,
                'direction': direction,
                'entry_price': fill_price,
                'amount': amount,
                'leverage': leverage,
                'stop_loss': stp['stop_loss'],
                'take_profit': stp['take_profit'],
                'peak_roi': 0,
                'entry_time': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),
                'score': score,
                'open_fee': open_fee,
                'roi_stop_loss': stp['roi_stop_loss'],
                'roi_trailing_start': stp['roi_trailing_start'],
                'roi_trailing_distance': stp['roi_trailing_distance'],
            }

            # Audit log
            db.session.add(AuditLog(
                user_type='agent', user_id=self.agent_id,
                action='open_position',
                details=json.dumps({'symbol': symbol, 'direction': direction,
                         'amount': amount, 'leverage': leverage,
                         'score': score, 'price': fill_price}),
            ))
            db.session.commit()

            # Telegram notification
            tier = COIN_TIERS.get(symbol, 'T3')
            self._send_telegram(
                f"<b>Open {direction}</b> {symbol}\n"
                f"Price: ${fill_price:.6f}\n"
                f"Amount: {amount}U x{leverage}\n"
                f"Score: {score} | Tier: {tier}\n"
                f"SL: ${stp['stop_loss']:.4f} | TP: ${stp['take_profit']:.4f}\n"
                f"Positions: {len(self.positions)}"
            )

            self._log('trade', f"OPEN {direction} {symbol} {amount}U x{leverage} "
                      f"@ ${fill_price:.6f} (score: {score})")

            # WebSocket push
            try:
                from ..api.ws_events import emit_trade_event
                emit_trade_event(self.agent_id, 'open', {
                    'symbol': symbol, 'direction': direction,
                    'amount': amount, 'leverage': leverage,
                    'price': fill_price, 'score': score,
                    'positions': len(self.positions),
                })
            except Exception:
                pass

        except Exception as e:
            self._log('error', f"Open position failed: {e}")
            traceback.print_exc()
            try:
                db.session.rollback()
            except Exception:
                pass
        finally:
            self._open_lock.release()

    # ─── Check Position ──────────────────────────────────────

    def _check_position(self, symbol: str, position: dict):
        """Check if a position should be closed."""
        try:
            current_price = self.executor.get_price(symbol)
            if not current_price:
                # Fallback to direct API
                current_price = fetch_price(symbol)
            if not current_price:
                return

            direction = position['direction']
            entry_price = position['entry_price']
            leverage = position.get('leverage', 1)

            roi_stop = position.get('roi_stop_loss', -10)
            roi_trail_start = position.get('roi_trailing_start', 6)
            roi_trail_dist = position.get('roi_trailing_distance', 3)

            # Calculate current ROI
            if direction == 'LONG':
                current_roi = ((current_price - entry_price) / entry_price) * leverage * 100
            else:
                current_roi = ((entry_price - current_price) / entry_price) * leverage * 100

            # Update peak ROI
            peak_roi = position.get('peak_roi', 0)
            if current_roi > peak_roi:
                position['peak_roi'] = current_roi
                peak_roi = current_roi

            should_close = False
            reason = ""

            # Hold time
            hold_minutes = 0
            entry_time_str = position.get('entry_time', '')
            if entry_time_str:
                try:
                    entry_dt = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
                    hold_minutes = (datetime.now() - entry_dt).total_seconds() / 60
                except Exception:
                    pass

            # Min hold protection (3h), unless severe loss > -15% ROI
            min_hold_protect = (hold_minutes < self.min_hold_minutes
                                and current_roi > -15)

            # Max hold forced close (48h)
            if hold_minutes > self.max_hold_minutes:
                should_close = True
                reason = f"Max hold time ({hold_minutes / 60:.0f}h, ROI {current_roi:+.1f}%)"

            # Fixed stop-loss / take-profit price check
            stop_loss = position.get('stop_loss', 0)
            take_profit = position.get('take_profit', 0)

            if take_profit > 0:
                if direction == 'LONG' and current_price >= take_profit:
                    should_close = True
                    reason = f"Take profit (ROI +{current_roi:.1f}%)"
                elif direction == 'SHORT' and current_price <= take_profit:
                    should_close = True
                    reason = f"Take profit (ROI +{current_roi:.1f}%)"

            if not should_close and stop_loss > 0:
                hit_sl = False
                if direction == 'LONG' and current_price <= stop_loss:
                    hit_sl = True
                elif direction == 'SHORT' and current_price >= stop_loss:
                    hit_sl = True
                if hit_sl and not min_hold_protect:
                    should_close = True
                    reason = f"Stop loss (ROI {current_roi:.1f}%)"

            # ROI stop loss
            if not should_close and current_roi <= roi_stop and not min_hold_protect:
                should_close = True
                reason = f"ROI stop loss ({current_roi:.1f}%)"

            # Trailing stop: peak above start, drawdown exceeds distance
            if not should_close and peak_roi >= roi_trail_start:
                drawdown = peak_roi - current_roi
                if drawdown >= roi_trail_dist:
                    trail_exit_roi = peak_roi - roi_trail_dist
                    should_close = True
                    if trail_exit_roi > 0:
                        reason = f"Trailing stop (+{trail_exit_roi:.1f}%, peak +{peak_roi:.1f}%)"
                    else:
                        reason = f"Trailing stop ({trail_exit_roi:.1f}%)"

            if should_close:
                self._close_position(symbol, current_price, reason)

        except Exception as e:
            print(f"[AgentBot-{self.agent_id}] Check {symbol} failed: {e}")

    # ─── Close Position ──────────────────────────────────────

    def _close_position(self, symbol: str, exit_price: float, reason: str):
        """Close a position."""
        try:
            position = self.positions.get(symbol)
            if not position:
                return

            direction = position['direction']
            entry_price = position['entry_price']
            amount = position['amount']
            leverage = position['leverage']

            # Close on Binance — MUST succeed before marking DB as CLOSED
            close_result = self.executor.close_position(symbol, direction)
            if not close_result:
                self._log('error', f"Binance close FAILED for {symbol} — "
                          f"keeping position OPEN in DB to prevent ghost position")
                return

            # Use actual close price from Binance
            actual_price = close_result.get('price')
            if actual_price and actual_price > 0:
                exit_price = actual_price

            # Calculate PnL with actual price
            if direction == 'LONG':
                price_change_pct = (exit_price - entry_price) / entry_price
            else:
                price_change_pct = (entry_price - exit_price) / entry_price

            roi = price_change_pct * leverage * 100
            pnl_before_fee = amount * price_change_pct * leverage

            # Use actual fees from Binance order responses
            open_fee = position.get('open_fee', 0)
            close_fee = close_result.get('fee', 0)
            total_fee = open_fee + close_fee

            # Funding fee: fetch from Binance income API
            funding_fee = self._fetch_funding_fee(symbol, position.get('entry_time', ''))

            pnl = pnl_before_fee - total_fee - funding_fee

            # Update database (only after Binance close confirmed)
            trade = Trade.query.get(position.get('trade_id'))
            if trade:
                trade.exit_price = Decimal(str(exit_price))
                trade.exit_time = datetime.now(timezone.utc)
                trade.status = 'CLOSED'
                trade.pnl = Decimal(str(round(pnl, 4)))
                trade.roi = Decimal(str(round(roi, 4)))
                trade.fee = Decimal(str(round(total_fee, 6)))
                trade.funding_fee = Decimal(str(round(funding_fee, 6)))
                trade.close_reason = reason
                trade.peak_roi = Decimal(str(round(position.get('peak_roi', 0), 4)))
                db.session.commit()

            # Update daily stats
            self._update_daily_stats(pnl, total_fee + funding_fee)

            # Remove from memory
            del self.positions[symbol]

            # Set cooldown
            cooldown_minutes = int(self.config.get('cooldown_minutes', 30))
            self.cooldowns[symbol] = datetime.now()

            # Audit log
            db.session.add(AuditLog(
                user_type='agent', user_id=self.agent_id,
                action='close_position',
                details=json.dumps({'symbol': symbol, 'direction': direction,
                         'pnl': round(pnl, 4), 'roi': round(roi, 4),
                         'reason': reason}),
            ))
            db.session.commit()

            # Telegram notification
            emoji = '+' if pnl > 0 else ''
            self._send_telegram(
                f"<b>Close {direction}</b> {symbol}\n"
                f"Entry: ${entry_price:.6f} -> Exit: ${exit_price:.6f}\n"
                f"PnL: {emoji}{pnl:.2f}U ({emoji}{roi:.2f}%)\n"
                f"Fee: {total_fee:.2f}U | Reason: {reason}\n"
                f"Positions: {len(self.positions)}"
            )

            self._log('trade', f"CLOSE {direction} {symbol} PnL: {pnl:+.2f}U "
                      f"({roi:+.2f}%) - {reason}")

            # WebSocket push
            try:
                from ..api.ws_events import emit_trade_event
                emit_trade_event(self.agent_id, 'close', {
                    'symbol': symbol, 'direction': direction,
                    'pnl': round(pnl, 2), 'roi': round(roi, 2),
                    'reason': reason, 'positions': len(self.positions),
                })
            except Exception:
                pass

        except Exception as e:
            self._log('error', f"Close position failed: {e}")
            traceback.print_exc()
            try:
                db.session.rollback()
            except Exception:
                pass

    def _update_daily_stats(self, pnl: float, fees: float):
        """Update or create daily stats record."""
        today = datetime.now(timezone.utc).date()
        stat = DailyStat.query.filter_by(
            agent_id=self.agent_id, date=today
        ).first()
        if not stat:
            stat = DailyStat(agent_id=self.agent_id, date=today)
            db.session.add(stat)

        stat.trades_closed = (stat.trades_closed or 0) + 1
        if pnl > 0:
            stat.win_trades = (stat.win_trades or 0) + 1
        stat.total_pnl = Decimal(str(float(stat.total_pnl or 0) + pnl))
        stat.total_fees = Decimal(str(float(stat.total_fees or 0) + fees))
        db.session.commit()

    # ─── Main Scan Loop ──────────────────────────────────────

    def _scan_once(self):
        """Run one complete scan cycle."""
        self.scan_count += 1
        self._log('info', f"Scan #{self.scan_count} | Positions: {len(self.positions)}")

        # Hot-reload config every 5 scans (~100s at 20s interval)
        self._config_reload_counter += 1
        if self._config_reload_counter >= 5:
            self._config_reload_counter = 0
            self._reload_trading_config()

        # Signal panel tracking
        scan_filtered = []
        scan_analyzed = 0
        scan_passed = 0
        scan_opened = 0

        # 1. Check existing positions
        for symbol in list(self.positions.keys()):
            self._check_position(symbol, self.positions[symbol])

        # 2. Risk check
        positions_list = list(self.positions.values())
        risk_metrics = self.risk_manager.calculate_risk_metrics(positions_list)
        self.risk_manager.update_bot_state(risk_metrics)

        risk_level, _, should_pause = self.risk_manager.get_risk_level(
            risk_metrics['risk_score']
        )

        # Circuit breaker: alert admin on HIGH/CRITICAL risk
        self.risk_manager.check_circuit_breaker(risk_metrics)

        if risk_level in ('CRITICAL', 'HIGH'):
            self._log('warn', f"Risk: {risk_level} (score: {risk_metrics['risk_score']}/10) - "
                       f"{'Pausing' if should_pause else 'Reducing positions'}")

        # 3. Scan for new signals (only if not paused)
        if not should_pause and not self._paused:
            min_score = int(self.config.get('min_score', 60))
            long_min_score = int(self.config.get('long_min_score', 70))
            cooldown_minutes = int(self.config.get('cooldown_minutes', 30))
            opened_this_scan = 0

            for symbol in DEFAULT_WATCHLIST:
                # v4: 跳过持续亏损币 (回测验证)
                if symbol in SKIP_COINS:
                    continue

                # Skip if already in position
                if symbol in self.positions:
                    continue

                # Max 3 new positions per scan (from paper_trader)
                if opened_this_scan >= 3:
                    break

                # Skip if in cooldown
                if symbol in self.cooldowns:
                    cd_time = self.cooldowns[symbol]
                    elapsed = (datetime.now() - cd_time).total_seconds() / 60
                    if elapsed < cooldown_minutes:
                        continue
                    else:
                        del self.cooldowns[symbol]

                # Pre-trade risk check
                can_open, risk_reason = self.risk_manager.check_can_open(
                    positions_list
                )
                if not can_open:
                    self._log('warn', f"Risk blocked: {risk_reason}")
                    break

                # Analyze signal
                score, analysis = analyze_signal(symbol, self.config)
                scan_analyzed += 1
                if not analysis or score < min_score:
                    continue

                direction = analysis['direction']
                scan_passed += 1

                # Stricter min score for LONG (from backtest data)
                if direction == 'LONG' and score < long_min_score:
                    scan_filtered.append({
                        'symbol': symbol, 'score': score,
                        'direction': direction, 'reason': f'LONG score < {long_min_score}',
                    })
                    scan_passed -= 1
                    continue

                # v4核心: 85+分LONG完全跳过 (回测亏钱, 极端做多=抄底接刀)
                if score >= 85 and direction == 'LONG':
                    self._log('info', f"Skip {symbol}: {score}pt LONG (85+ LONG loses money)")
                    scan_filtered.append({
                        'symbol': symbol, 'score': score,
                        'direction': direction, 'reason': '85+ LONG skip',
                    })
                    scan_passed -= 1
                    continue

                # v4: MA slope趋势过滤 — MA20与MA50斜率与方向冲突时跳过
                ma20 = analysis.get('ma20', 0)
                ma50 = analysis.get('ma50', 0)
                if ma20 > 0 and ma50 > 0:
                    ma_slope = (ma20 - ma50) / ma50
                    if direction == 'LONG' and ma_slope < -0.01:
                        self._log('info', f"Skip {symbol}: trend filter (MA slope {ma_slope:.3f})")
                        scan_filtered.append({
                            'symbol': symbol, 'score': score,
                            'direction': direction,
                            'reason': f'MA slope {ma_slope:.3f}',
                        })
                        scan_passed -= 1
                        continue
                    if direction == 'SHORT' and ma_slope > 0.01:
                        self._log('info', f"Skip {symbol}: trend filter (MA slope {ma_slope:.3f})")
                        scan_filtered.append({
                            'symbol': symbol, 'score': score,
                            'direction': direction,
                            'reason': f'MA slope {ma_slope:.3f}',
                        })
                        scan_passed -= 1
                        continue

                self._log('signal', f"Signal: {symbol} {direction} score={score}")

                # Open position
                self._open_position(symbol, analysis)
                opened_this_scan += 1
                scan_opened += 1

                # Update positions list for next risk check
                positions_list = list(self.positions.values())

                # Small delay between orders
                time.sleep(1)

        # Update scan result for signal panel
        self.last_scan_result = {
            'last_scan_time': datetime.now(timezone.utc).isoformat(),
            'scan_interval': self.config.get('scan_interval', 60),
            'signals_analyzed': scan_analyzed,
            'signals_passed': scan_passed,
            'signals_filtered': scan_filtered[-10:],  # Keep last 10
            'positions_opened': scan_opened,
            'total_positions': len(self.positions),
            'risk_score': risk_metrics.get('risk_score', 0),
            'risk_level': risk_level,
        }

        # WebSocket push scan result
        try:
            from ..api.ws_events import emit_signal_update
            emit_signal_update(self.agent_id, self.last_scan_result)
        except Exception:
            pass

        # Update bot state
        self._update_state('running')

    # ─── Lifecycle ───────────────────────────────────────────

    def run(self, scan_interval: int = 60):
        """Main bot loop. Call this from a thread.

        Args:
            scan_interval: Seconds between market scans
        """
        with self.app.app_context():
            try:
                self._load_config()
                self._update_state('running')

                self._log('info', f"Bot started. Scan interval: {scan_interval}s")

                while not self._stop_event.is_set():
                    try:
                        if not self._paused:
                            self._scan_once()
                        # Reset error counter on successful scan
                        self._consecutive_errors = 0
                    except Exception as e:
                        self._consecutive_errors += 1
                        self._log('error', f"Scan error (#{self._consecutive_errors}): {e}")
                        traceback.print_exc()
                        self._update_state('error', str(e))

                        # Exponential backoff on repeated errors:
                        # 1 error → 60s, 2 → 120s, 3 → 240s, 4+ → 300s (5 min max)
                        backoff = min(scan_interval * (2 ** (self._consecutive_errors - 1)), 300)
                        if self._consecutive_errors > 1:
                            self._log('warn', f"Backoff {backoff}s due to "
                                      f"{self._consecutive_errors} consecutive errors")
                            self._stop_event.wait(timeout=backoff)
                            continue

                    # Wait with periodic stop-event checking
                    self._stop_event.wait(timeout=scan_interval)

                # Clean shutdown
                self._update_state('stopped')
                self._log('info', "Bot stopped gracefully.")

            except Exception as e:
                self._log('error', f"Fatal error: {e}")
                traceback.print_exc()
                try:
                    self._update_state('error', str(e))
                except Exception:
                    pass

    def stop(self):
        """Signal the bot to stop."""
        self._stop_event.set()

    def pause(self):
        """Pause scanning (existing positions still monitored)."""
        self._paused = True

    def resume(self):
        """Resume scanning."""
        self._paused = False

    @property
    def is_running(self) -> bool:
        return not self._stop_event.is_set()
