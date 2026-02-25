"""Risk Manager - Per-agent risk monitoring and control.

Extracted from paper_trader.py's check_risk_level() and calculate_risk_metrics().
Uses SQLAlchemy models to read trade data instead of raw SQLite.
"""
from datetime import datetime, timezone
from typing import Optional

from ..extensions import db
from ..models.trade import Trade
from ..models.bot_state import BotState


class RiskManager:
    """Calculates risk metrics and enforces risk limits for a single agent."""

    def __init__(self, agent_id: int, config: dict):
        """
        Args:
            agent_id: The agent this risk manager monitors
            config: Dict with risk parameters:
                - initial_capital (float)
                - daily_loss_limit (float)
                - max_drawdown_pct (float)
                - max_positions (int)
                - max_leverage (int)
        """
        self.agent_id = agent_id
        self.config = config
        self.initial_capital = float(config.get('initial_capital', 2000))
        self.daily_loss_limit = float(config.get('daily_loss_limit', 200))
        self.max_drawdown_pct = float(config.get('max_drawdown_pct', 20))
        self.max_positions = int(config.get('max_positions', 15))
        self.max_leverage = int(config.get('max_leverage', 3))

    def calculate_risk_metrics(self, positions: list) -> dict:
        """Calculate comprehensive risk metrics.

        Args:
            positions: List of current open position dicts from the bot

        Returns:
            Dict with all risk metrics
        """
        # 1. Calculate max & current drawdown from closed trades
        closed_trades = (
            Trade.query
            .filter_by(agent_id=self.agent_id, status='CLOSED')
            .order_by(Trade.exit_time)
            .all()
        )

        peak_capital = self.initial_capital
        cumulative_capital = self.initial_capital
        max_drawdown = 0.0

        for trade in closed_trades:
            pnl = float(trade.pnl) if trade.pnl else 0
            cumulative_capital += pnl
            if cumulative_capital > peak_capital:
                peak_capital = cumulative_capital
            dd_pct = ((peak_capital - cumulative_capital) / peak_capital * 100
                      if peak_capital > 0 else 0)
            max_drawdown = max(max_drawdown, dd_pct)

        current_capital = cumulative_capital
        current_drawdown = ((peak_capital - current_capital) / peak_capital * 100
                            if current_capital < peak_capital and peak_capital > 0 else 0)

        # 2. Consecutive losses
        recent = (
            Trade.query
            .filter_by(agent_id=self.agent_id, status='CLOSED')
            .order_by(Trade.exit_time.desc())
            .limit(10)
            .all()
        )
        consecutive_losses = 0
        for trade in recent:
            if trade.pnl and float(trade.pnl) < 0:
                consecutive_losses += 1
            else:
                break

        # 3. Position concentration
        position_count = len(positions)
        max_position_pct = 0.0
        if position_count > 0:
            amounts = [p.get('amount', 0) for p in positions]
            total_pos = sum(amounts)
            if total_pos > 0:
                max_position_pct = max(amounts) / total_pos * 100

        # 4. Long/short ratio
        long_count = sum(1 for p in positions if p.get('direction') == 'LONG')
        short_count = sum(1 for p in positions if p.get('direction') == 'SHORT')
        long_ratio = (long_count / position_count * 100) if position_count > 0 else 0
        short_ratio = (short_count / position_count * 100) if position_count > 0 else 0

        # 5. Average leverage
        avg_leverage = 0.0
        if position_count > 0:
            leverages = [p.get('leverage', 1) for p in positions]
            avg_leverage = sum(leverages) / len(leverages)

        # 6. Daily PnL
        today = datetime.now(timezone.utc).date()
        daily_trades = (
            Trade.query
            .filter_by(agent_id=self.agent_id, status='CLOSED')
            .filter(db.func.date(Trade.exit_time) == today)
            .all()
        )
        daily_pnl = sum(float(t.pnl) for t in daily_trades if t.pnl)

        # 7. Risk score (0-10)
        risk_score = 0

        # Drawdown risk (0-3)
        if current_drawdown > 15:
            risk_score += 3
        elif current_drawdown > 10:
            risk_score += 2
        elif current_drawdown > 5:
            risk_score += 1

        # Consecutive losses (0-2)
        if consecutive_losses >= 3:
            risk_score += 2
        elif consecutive_losses >= 2:
            risk_score += 1

        # Position concentration (0-2) - only when >= 3 positions
        if position_count >= 3:
            if max_position_pct > 40:
                risk_score += 2
            elif max_position_pct > 30:
                risk_score += 1

        # Direction imbalance (0-2) - only when >= 3 positions
        if position_count >= 3:
            if max(long_ratio, short_ratio) > 85:
                risk_score += 2
            elif max(long_ratio, short_ratio) > 70:
                risk_score += 1

        # Leverage risk (0-1)
        if avg_leverage > 3:
            risk_score += 1

        # Daily loss limit check
        daily_loss_breach = abs(daily_pnl) > self.daily_loss_limit if daily_pnl < 0 else False
        if daily_loss_breach:
            risk_score = max(risk_score, 7)

        # Max drawdown check
        drawdown_breach = current_drawdown > self.max_drawdown_pct
        if drawdown_breach:
            risk_score = max(risk_score, 8)

        return {
            'risk_score': risk_score,
            'current_capital': current_capital,
            'peak_capital': peak_capital,
            'max_drawdown': max_drawdown,
            'current_drawdown': current_drawdown,
            'consecutive_losses': consecutive_losses,
            'max_position_pct': max_position_pct,
            'long_ratio': long_ratio,
            'short_ratio': short_ratio,
            'avg_leverage': avg_leverage,
            'daily_pnl': daily_pnl,
            'daily_loss_breach': daily_loss_breach,
            'drawdown_breach': drawdown_breach,
            'position_count': position_count,
        }

    def get_risk_level(self, risk_score: int) -> tuple:
        """Determine risk level and position multiplier.

        Returns:
            (level_name: str, position_multiplier: float, should_pause: bool)
        """
        if risk_score >= 9:
            return 'CRITICAL', 0.0, True       # Stop all trading
        elif risk_score >= 7:
            return 'HIGH', 0.3, True            # Reduce + pause new positions
        elif risk_score >= 4:
            return 'MEDIUM', 0.5, False         # Reduce position sizes
        else:
            return 'LOW', 1.0, False            # Normal trading

    def check_can_open(self, positions: list, direction: str = None) -> tuple:
        """Pre-trade risk check: can we open a new position?

        Args:
            positions: Current open positions
            direction: 'LONG' or 'SHORT'

        Returns:
            (allowed: bool, reason: str)
        """
        # Position count limit
        if len(positions) >= self.max_positions:
            return False, f"Max positions reached ({self.max_positions})"

        # Calculate risk
        metrics = self.calculate_risk_metrics(positions)
        level, multiplier, should_pause = self.get_risk_level(metrics['risk_score'])

        if should_pause:
            return False, f"Trading paused: risk level {level} (score {metrics['risk_score']}/10)"

        if metrics['daily_loss_breach']:
            return False, f"Daily loss limit exceeded ({metrics['daily_pnl']:.2f})"

        if metrics['drawdown_breach']:
            return False, f"Max drawdown exceeded ({metrics['current_drawdown']:.1f}%)"

        return True, f"OK (risk: {level}, multiplier: {multiplier})"

    def get_position_multiplier(self, positions: list) -> float:
        """Get position size multiplier based on current risk."""
        metrics = self.calculate_risk_metrics(positions)
        _, multiplier, _ = self.get_risk_level(metrics['risk_score'])
        return multiplier

    def update_bot_state(self, risk_metrics: dict):
        """Persist risk metrics to BotState table."""
        state = BotState.query.filter_by(agent_id=self.agent_id).first()
        if state:
            state.risk_score = risk_metrics['risk_score']
            _, multiplier, _ = self.get_risk_level(risk_metrics['risk_score'])
            state.risk_position_multiplier = multiplier
            state.peak_capital = risk_metrics['peak_capital']
            db.session.commit()
