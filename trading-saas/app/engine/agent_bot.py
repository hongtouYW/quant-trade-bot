"""Agent Bot - Single-agent trading bot.

Refactored from PaperTradingAssistant in paper_trader.py.
Each agent gets its own AgentBot running in a thread, using:
- SignalAnalyzer for market analysis
- OrderExecutor for real Binance trades
- RiskManager for risk control
- SQLAlchemy models for persistence
"""
import time
import threading
import traceback
import requests
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
    fetch_price, DEFAULT_WATCHLIST, COIN_TIERS,
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

        self.executor = None
        self.risk_manager = None
        self.config = {}
        self.scan_count = 0

    def _load_config(self):
        """Load agent config from database."""
        agent = Agent.query.get(self.agent_id)
        if not agent or not agent.is_active or not agent.is_trading_enabled:
            raise RuntimeError(f"Agent {self.agent_id} not active or trading not enabled")

        # Load API keys
        api_key_record = AgentApiKey.query.filter_by(agent_id=self.agent_id).first()
        if not api_key_record or not api_key_record.permissions_verified:
            raise RuntimeError(f"Agent {self.agent_id} has no verified API keys")

        # Decrypt API keys
        api_key = EncryptionService.decrypt(
            api_key_record.binance_api_key_enc,
            api_key_record.encryption_iv,
        )
        api_secret = EncryptionService.decrypt(
            api_key_record.binance_api_secret_enc,
            api_key_record.encryption_iv,
        )

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
                'roi_stop_loss': float(self.config.get('roi_stop_loss', -10)),
                'roi_trailing_start': float(self.config.get('roi_trailing_start', 6)),
                'roi_trailing_distance': float(self.config.get('roi_trailing_distance', 3)),
            }

        print(f"[AgentBot-{self.agent_id}] Config loaded. "
              f"Strategy: {self.config.get('strategy_version')}, "
              f"Open positions: {len(self.positions)}")

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
        try:
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
                print(f"[AgentBot-{self.agent_id}] Order failed for {symbol}")
                return

            # Use actual fill price from exchange
            fill_price = order['price']

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
                'roi_stop_loss': stp['roi_stop_loss'],
                'roi_trailing_start': stp['roi_trailing_start'],
                'roi_trailing_distance': stp['roi_trailing_distance'],
            }

            # Audit log
            db.session.add(AuditLog(
                user_type='agent', user_id=self.agent_id,
                action='open_position',
                details={'symbol': symbol, 'direction': direction,
                         'amount': amount, 'leverage': leverage,
                         'score': score, 'price': fill_price},
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

            print(f"[AgentBot-{self.agent_id}] Opened {direction} {symbol} "
                  f"{amount}U x{leverage} @ ${fill_price:.6f} (score: {score})")

        except Exception as e:
            print(f"[AgentBot-{self.agent_id}] Open position failed: {e}")
            traceback.print_exc()

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
            fee_rate = float(self.config.get('fee_rate', 0.0005))

            # Calculate PnL
            if direction == 'LONG':
                price_change_pct = (exit_price - entry_price) / entry_price
            else:
                price_change_pct = (entry_price - exit_price) / entry_price

            roi = price_change_pct * leverage * 100
            pnl_before_fee = amount * price_change_pct * leverage

            # Fees
            position_value = amount * leverage
            total_fee = position_value * fee_rate * 2  # open + close

            # Funding fee estimate
            entry_time_str = position.get('entry_time', '')
            holding_hours = 0
            if entry_time_str:
                try:
                    entry_dt = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
                    holding_hours = (datetime.now() - entry_dt).total_seconds() / 3600
                except Exception:
                    pass
            funding_rate = 0.0001  # 0.01% per 8h
            funding_fee = position_value * funding_rate * (holding_hours / 8)

            pnl = pnl_before_fee - total_fee - funding_fee

            # Close on Binance
            close_result = self.executor.close_position(symbol, direction)
            if close_result:
                # Use actual close price if available
                actual_price = close_result.get('price')
                if actual_price and actual_price > 0:
                    exit_price = actual_price
                    # Recalculate with actual price
                    if direction == 'LONG':
                        price_change_pct = (exit_price - entry_price) / entry_price
                    else:
                        price_change_pct = (entry_price - exit_price) / entry_price
                    roi = price_change_pct * leverage * 100
                    pnl_before_fee = amount * price_change_pct * leverage
                    pnl = pnl_before_fee - total_fee - funding_fee

            # Update database
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
                details={'symbol': symbol, 'direction': direction,
                         'pnl': round(pnl, 4), 'roi': round(roi, 4),
                         'reason': reason},
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

            print(f"[AgentBot-{self.agent_id}] Closed {symbol} {direction} "
                  f"PnL: {pnl:+.2f}U ({roi:+.2f}%) - {reason}")

        except Exception as e:
            print(f"[AgentBot-{self.agent_id}] Close position failed: {e}")
            traceback.print_exc()

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
        print(f"\n[AgentBot-{self.agent_id}] === Scan #{self.scan_count} "
              f"| Positions: {len(self.positions)} ===")

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

        if risk_level in ('CRITICAL', 'HIGH'):
            print(f"[AgentBot-{self.agent_id}] Risk: {risk_level} "
                  f"(score: {risk_metrics['risk_score']}/10) - "
                  f"{'Pausing' if should_pause else 'Reducing positions'}")

        # 3. Scan for new signals (only if not paused)
        if not should_pause and not self._paused:
            min_score = int(self.config.get('min_score', 60))
            long_min_score = int(self.config.get('long_min_score', 70))
            cooldown_minutes = int(self.config.get('cooldown_minutes', 30))

            for symbol in DEFAULT_WATCHLIST:
                # Skip if already in position
                if symbol in self.positions:
                    continue

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
                    print(f"[AgentBot-{self.agent_id}] Risk blocked: {risk_reason}")
                    break

                # Analyze signal
                score, analysis = analyze_signal(symbol, self.config)
                if not analysis or score < min_score:
                    continue

                # Stricter min score for LONG (from backtest data)
                if analysis['direction'] == 'LONG' and score < long_min_score:
                    continue

                print(f"[AgentBot-{self.agent_id}] Signal: {symbol} "
                      f"{analysis['direction']} score={score}")

                # Open position
                self._open_position(symbol, analysis)

                # Update positions list for next risk check
                positions_list = list(self.positions.values())

                # Small delay between orders
                time.sleep(1)

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

                print(f"[AgentBot-{self.agent_id}] Started. "
                      f"Scan interval: {scan_interval}s")

                while not self._stop_event.is_set():
                    try:
                        if not self._paused:
                            self._scan_once()
                    except Exception as e:
                        print(f"[AgentBot-{self.agent_id}] Scan error: {e}")
                        traceback.print_exc()
                        self._update_state('error', str(e))

                    # Wait with periodic stop-event checking
                    self._stop_event.wait(timeout=scan_interval)

                # Clean shutdown
                self._update_state('stopped')
                print(f"[AgentBot-{self.agent_id}] Stopped gracefully.")

            except Exception as e:
                print(f"[AgentBot-{self.agent_id}] Fatal error: {e}")
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
