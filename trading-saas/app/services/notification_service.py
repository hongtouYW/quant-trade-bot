"""Notification Service - Telegram + In-app notifications per agent.

Each agent has their own Telegram bot token and chat ID.
Provides structured message templates for trade events.
Also stores notifications in-app for the notification center.
"""
import requests
from typing import Optional

from ..extensions import db
from ..models.agent_config import AgentTelegramConfig
from ..models.notification import Notification
from ..services.encryption_service import EncryptionService


class NotificationService:
    """Send Telegram + in-app notifications for a specific agent."""

    def __init__(self, agent_id: int):
        self.agent_id = agent_id
        self._bot_token = None
        self._chat_id = None
        self._enabled = False
        self._loaded = False

    def _load_config(self):
        """Lazy-load Telegram config from database."""
        if self._loaded:
            return
        self._loaded = True

        config = AgentTelegramConfig.query.filter_by(
            agent_id=self.agent_id
        ).first()
        if not config or not config.is_enabled:
            return

        try:
            self._bot_token = EncryptionService.decrypt(
                config.bot_token_enc, config.encryption_iv
            )
            self._chat_id = config.chat_id
            self._enabled = True
        except Exception as e:
            print(f"[Notification] Failed to load config for agent {self.agent_id}: {e}")

    def send(self, message: str, parse_mode: str = 'HTML') -> bool:
        """Send a Telegram message.

        Returns True if sent successfully.
        """
        self._load_config()
        if not self._enabled:
            return False

        try:
            url = f"https://api.telegram.org/bot{self._bot_token}/sendMessage"
            resp = requests.post(url, json={
                'chat_id': self._chat_id,
                'text': message,
                'parse_mode': parse_mode,
            }, timeout=10)
            return resp.status_code == 200
        except Exception as e:
            print(f"[Notification] Send failed for agent {self.agent_id}: {e}")
            return False

    def _store(self, ntype: str, title: str, message: str = ''):
        """Store an in-app notification."""
        try:
            notif = Notification(
                agent_id=self.agent_id,
                type=ntype,
                title=title,
                message=message,
            )
            db.session.add(notif)
            db.session.commit()
        except Exception as e:
            print(f"[Notification] Store failed for agent {self.agent_id}: {e}")
            db.session.rollback()

    # ─── Message Templates ────────────────────────────────────

    def notify_open_position(self, symbol: str, direction: str, amount: float,
                             leverage: int, price: float, score: int,
                             stop_loss: float, take_profit: float,
                             positions_count: int):
        """Notify on position open."""
        emoji = '🟢' if direction == 'LONG' else '🔴'
        self.send(
            f"{emoji} <b>Open {direction}</b> {symbol}\n"
            f"━━━━━━━━━━━━━━━\n"
            f"💰 Amount: {amount}U x{leverage}\n"
            f"📍 Entry: ${price:.6f}\n"
            f"📊 Score: {score}/100\n"
            f"🛑 SL: ${stop_loss:.4f}\n"
            f"🎯 TP: ${take_profit:.4f}\n"
            f"📦 Positions: {positions_count}"
        )
        self._store(
            'trade',
            f"Open {direction} {symbol}",
            f"{amount}U x{leverage} @ ${price:.6f} | Score: {score}",
        )

    def notify_close_position(self, symbol: str, direction: str,
                              entry_price: float, exit_price: float,
                              pnl: float, roi: float, reason: str,
                              positions_count: int, current_capital: float):
        """Notify on position close."""
        emoji = '🎉' if pnl > 0 else '😢'
        sign = '+' if pnl > 0 else ''
        self.send(
            f"{emoji} <b>Close {direction}</b> {symbol}\n"
            f"━━━━━━━━━━━━━━━\n"
            f"📍 Entry: ${entry_price:.6f}\n"
            f"📍 Exit: ${exit_price:.6f}\n"
            f"💰 PnL: {sign}{pnl:.2f}U ({sign}{roi:.2f}%)\n"
            f"💡 Reason: {reason}\n"
            f"━━━━━━━━━━━━━━━\n"
            f"💼 Capital: {current_capital:.2f}U\n"
            f"📦 Positions: {positions_count}"
        )
        self._store(
            'trade',
            f"Close {direction} {symbol} {sign}{pnl:.2f}U",
            f"{sign}{roi:.2f}% | {reason}",
        )

    def notify_risk_alert(self, level: str, risk_score: int,
                          metrics: dict, actions: list = None):
        """Notify on risk level change."""
        emojis = {
            'CRITICAL': '🚨🚨🚨',
            'HIGH': '⚠️⚠️',
            'MEDIUM': '⚠️',
            'LOW': '✅',
        }
        emoji = emojis.get(level, '⚠️')

        msg = (
            f"{emoji} <b>Risk Alert: {level}</b>\n"
            f"━━━━━━━━━━━━━━━\n"
            f"📊 Score: {risk_score}/10\n"
            f"📉 Drawdown: {metrics.get('current_drawdown', 0):.1f}%\n"
            f"🔴 Consec. Losses: {metrics.get('consecutive_losses', 0)}\n"
            f"📊 Long/Short: {metrics.get('long_ratio', 0):.0f}%/"
            f"{metrics.get('short_ratio', 0):.0f}%\n"
            f"💰 Daily PnL: {metrics.get('daily_pnl', 0):.2f}U"
        )

        if actions:
            msg += "\n\n<b>Actions taken:</b>\n"
            for a in actions:
                msg += f"• {a}\n"

        self.send(msg)
        self._store(
            'risk',
            f"Risk Alert: {level} ({risk_score}/10)",
            f"Drawdown: {metrics.get('current_drawdown', 0):.1f}%",
        )

    def notify_bot_status(self, status: str, details: str = ''):
        """Notify on bot start/stop/error."""
        emojis = {
            'started': '🟢',
            'stopped': '🔴',
            'paused': '⏸️',
            'error': '❌',
            'resumed': '▶️',
        }
        emoji = emojis.get(status, '📌')
        msg = f"{emoji} <b>Bot {status}</b>"
        if details:
            msg += f"\n{details}"
        self.send(msg)
        self._store('bot', f"Bot {status}", details)

    def notify_daily_summary(self, date: str, trades_closed: int,
                             win_trades: int, total_pnl: float,
                             current_capital: float):
        """Send daily trading summary."""
        win_rate = (win_trades / trades_closed * 100) if trades_closed > 0 else 0
        emoji = '📈' if total_pnl > 0 else '📉'
        sign = '+' if total_pnl > 0 else ''
        self.send(
            f"📊 <b>Daily Summary</b> ({date})\n"
            f"━━━━━━━━━━━━━━━\n"
            f"📋 Trades: {trades_closed}\n"
            f"✅ Wins: {win_trades} ({win_rate:.0f}%)\n"
            f"{emoji} PnL: {sign}{total_pnl:.2f}U\n"
            f"💼 Capital: {current_capital:.2f}U"
        )
        self._store(
            'system',
            f"Daily Summary ({date})",
            f"{trades_closed} trades, {win_rate:.0f}% WR, {sign}{total_pnl:.2f}U",
        )

    def notify_billing(self, period_start: str, period_end: str,
                       gross_pnl: float, commission: float,
                       high_water_mark: float):
        """Notify on billing period close."""
        self.send(
            f"💳 <b>Billing Period Closed</b>\n"
            f"━━━━━━━━━━━━━━━\n"
            f"📅 {period_start} ~ {period_end}\n"
            f"💰 Gross PnL: {gross_pnl:+.2f}U\n"
            f"📊 High Water Mark: {high_water_mark:.2f}U\n"
            f"💸 Commission: {commission:.2f}U\n"
        )
        self._store(
            'billing',
            f"Billing Period Closed",
            f"{period_start} ~ {period_end} | PnL: {gross_pnl:+.2f}U | Commission: {commission:.2f}U",
        )
