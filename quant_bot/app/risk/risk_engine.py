"""Risk engine - portfolio-level risk control"""
import logging
import time
from datetime import datetime, date
from app.config import get

log = logging.getLogger(__name__)


class RiskEngine:
    def __init__(self):
        self.daily_pnl = 0.0
        self.daily_pnl_pct = 0.0
        self.consecutive_losses = 0
        self.today_trades = 0
        self.today_wins = 0
        self.today_losses = 0
        self.current_date = date.today()
        self.positions = []  # active position list
        self.cooldown_until = 0  # timestamp
        self._risk_cfg = get('risk')
        self._exec_cfg = get('execution')

    def reset_daily(self):
        today = date.today()
        if today != self.current_date:
            self.daily_pnl = 0.0
            self.daily_pnl_pct = 0.0
            self.today_trades = 0
            self.today_wins = 0
            self.today_losses = 0
            self.current_date = today
            log.info("Daily risk counters reset")

    def can_open_new_positions(self, equity):
        """Check all portfolio-level conditions"""
        self.reset_daily()

        # Daily loss limit
        if equity > 0:
            self.daily_pnl_pct = self.daily_pnl / equity
        if self.daily_pnl_pct <= -self._risk_cfg.get('daily_loss_limit_pct', 0.03):
            log.warning(f"Daily loss limit hit: {self.daily_pnl_pct:.2%}")
            return False, "daily_loss_limit"

        # Consecutive loss stop
        if self._risk_cfg.get('stop_after_5_losses') and self.consecutive_losses >= 5:
            log.warning("5 consecutive losses - stopping for today")
            return False, "5_consecutive_losses"

        # Cooldown
        if time.time() < self.cooldown_until:
            remaining = int(self.cooldown_until - time.time())
            return False, f"cooldown_{remaining}s"

        # Max positions
        if len(self.positions) >= self._exec_cfg.get('max_positions', 3):
            return False, "max_positions"

        # Profit guard
        if self.daily_pnl_pct >= self._risk_cfg.get('daily_stop_trading_profit_pct', 0.04):
            return False, "profit_stop"

        return True, "ok"

    def get_allowed_grade(self):
        """What signal grade is allowed based on current state"""
        self.reset_daily()
        if self.daily_pnl_pct >= self._risk_cfg.get('profit_guard_mode_pct', 0.025):
            return 'A'  # Only A grade allowed
        return 'C'  # All grades allowed

    def approve_order(self, order_plan, equity):
        """Final approval before placing order"""
        can, reason = self.can_open_new_positions(equity)
        if not can:
            return False, reason

        # Same direction risk check
        same_dir_risk = sum(
            abs(p.margin) / equity for p in self.positions
            if p.direction == order_plan['direction']
        ) if equity > 0 else 0

        new_risk = order_plan['margin'] / equity if equity > 0 else 1
        if same_dir_risk + new_risk > self._exec_cfg.get('max_same_direction_risk', 0.012) * 10:
            return False, "same_direction_risk_limit"

        # Total risk check
        total_risk = sum(abs(p.margin) / equity for p in self.positions) if equity > 0 else 0
        if total_risk + new_risk > self._exec_cfg.get('max_total_risk', 0.016) * 10:
            return False, "total_risk_limit"

        # Max notional per trade
        max_notional = equity * 0.30
        if order_plan['notional'] > max_notional:
            return False, "max_notional_exceeded"

        return True, "approved"

    def record_trade_result(self, pnl, equity):
        """Record a closed trade"""
        self.daily_pnl += pnl
        self.today_trades += 1

        if pnl >= 0:
            self.today_wins += 1
            self.consecutive_losses = 0
        else:
            self.today_losses += 1
            self.consecutive_losses += 1

            # Cooldown triggers
            if self.consecutive_losses == 3:
                cooldown_min = self._risk_cfg.get('cooldown_after_3_losses_minutes', 30)
                self.cooldown_until = time.time() + cooldown_min * 60
                log.warning(f"3 consecutive losses - cooldown {cooldown_min}min")

        if equity > 0:
            self.daily_pnl_pct = self.daily_pnl / equity

    def get_status(self):
        """Return current risk status for display"""
        return {
            'daily_pnl': round(self.daily_pnl, 2),
            'daily_pnl_pct': round(self.daily_pnl_pct * 100, 2),
            'consecutive_losses': self.consecutive_losses,
            'today_trades': self.today_trades,
            'today_wins': self.today_wins,
            'today_losses': self.today_losses,
            'positions_count': len(self.positions),
            'cooldown_active': time.time() < self.cooldown_until,
            'allowed_grade': self.get_allowed_grade(),
        }
