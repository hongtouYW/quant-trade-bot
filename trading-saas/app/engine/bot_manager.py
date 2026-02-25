"""Bot Manager - Manages multiple AgentBot threads.

Provides start/stop/status for each agent's trading bot.
Singleton pattern: one BotManager per Flask application.
"""
import threading
from typing import Optional

from ..models.bot_state import BotState
from ..models.agent import Agent
from ..extensions import db
from .agent_bot import AgentBot


class BotManager:
    """Manages all running AgentBot instances."""

    _instance = None
    _lock = threading.Lock()

    def __init__(self, app=None):
        self.app = app
        self._bots = {}       # agent_id -> AgentBot
        self._threads = {}    # agent_id -> Thread
        self._scan_interval = 60  # seconds

    @classmethod
    def get_instance(cls, app=None) -> 'BotManager':
        """Get or create the singleton BotManager."""
        with cls._lock:
            if cls._instance is None:
                cls._instance = cls(app)
            elif app is not None:
                cls._instance.app = app
            return cls._instance

    def start_bot(self, agent_id: int) -> tuple:
        """Start a bot for an agent.

        Returns:
            (success: bool, message: str)
        """
        if not self.app:
            return False, "BotManager not initialized with Flask app"

        with self.app.app_context():
            # Check if already running
            if agent_id in self._bots and self._bots[agent_id].is_running:
                return False, "Bot is already running"

            # Validate agent
            agent = Agent.query.get(agent_id)
            if not agent:
                return False, "Agent not found"
            if not agent.is_active:
                return False, "Agent account is deactivated"
            if not agent.is_trading_enabled:
                return False, "Trading not enabled by admin"
            if not agent.api_key or not agent.api_key.permissions_verified:
                return False, "Binance API keys not configured or not verified"

            # Create and start bot
            bot = AgentBot(agent_id, self.app)
            thread = threading.Thread(
                target=bot.run,
                args=(self._scan_interval,),
                name=f"AgentBot-{agent_id}",
                daemon=True,
            )

            self._bots[agent_id] = bot
            self._threads[agent_id] = thread
            thread.start()

            # Update state
            state = BotState.query.filter_by(agent_id=agent_id).first()
            if not state:
                state = BotState(agent_id=agent_id)
                db.session.add(state)
            state.status = 'running'
            state.pid = thread.ident
            db.session.commit()

            return True, f"Bot started for agent {agent_id}"

    def stop_bot(self, agent_id: int) -> tuple:
        """Stop a running bot.

        Returns:
            (success: bool, message: str)
        """
        bot = self._bots.get(agent_id)
        if not bot:
            return False, "Bot not found or not running"

        bot.stop()

        # Wait for thread to finish (max 10s)
        thread = self._threads.get(agent_id)
        if thread and thread.is_alive():
            thread.join(timeout=10)

        # Clean up
        self._bots.pop(agent_id, None)
        self._threads.pop(agent_id, None)

        # Update state
        with self.app.app_context():
            state = BotState.query.filter_by(agent_id=agent_id).first()
            if state:
                state.status = 'stopped'
                state.pid = None
                state.started_at = None
                db.session.commit()

        return True, f"Bot stopped for agent {agent_id}"

    def pause_bot(self, agent_id: int) -> tuple:
        """Pause a running bot (keeps monitoring positions)."""
        bot = self._bots.get(agent_id)
        if not bot or not bot.is_running:
            return False, "Bot not running"

        bot.pause()

        with self.app.app_context():
            state = BotState.query.filter_by(agent_id=agent_id).first()
            if state:
                state.status = 'paused'
                db.session.commit()

        return True, "Bot paused"

    def resume_bot(self, agent_id: int) -> tuple:
        """Resume a paused bot."""
        bot = self._bots.get(agent_id)
        if not bot or not bot.is_running:
            return False, "Bot not running"

        bot.resume()

        with self.app.app_context():
            state = BotState.query.filter_by(agent_id=agent_id).first()
            if state:
                state.status = 'running'
                db.session.commit()

        return True, "Bot resumed"

    def get_bot_status(self, agent_id: int) -> dict:
        """Get status for a specific bot."""
        bot = self._bots.get(agent_id)
        thread = self._threads.get(agent_id)

        return {
            'agent_id': agent_id,
            'in_memory': bot is not None,
            'thread_alive': thread.is_alive() if thread else False,
            'is_running': bot.is_running if bot else False,
            'positions': len(bot.positions) if bot else 0,
            'scan_count': bot.scan_count if bot else 0,
        }

    def get_all_status(self) -> list:
        """Get status for all bots."""
        return [
            self.get_bot_status(agent_id)
            for agent_id in list(self._bots.keys())
        ]

    def stop_all(self):
        """Stop all running bots. Call on app shutdown."""
        for agent_id in list(self._bots.keys()):
            self.stop_bot(agent_id)

    def restart_bot(self, agent_id: int) -> tuple:
        """Restart a bot (stop then start)."""
        if agent_id in self._bots:
            self.stop_bot(agent_id)
        return self.start_bot(agent_id)

    def auto_restart_crashed(self):
        """Check for crashed bots and restart them.

        Call this periodically from a scheduler.
        """
        if not self.app:
            return

        with self.app.app_context():
            # Find bots that should be running but crashed
            running_states = BotState.query.filter(
                BotState.status.in_(['running', 'error'])
            ).all()

            for state in running_states:
                bot = self._bots.get(state.agent_id)
                thread = self._threads.get(state.agent_id)

                # Bot should be running but thread is dead
                if not thread or not thread.is_alive():
                    if state.error_count and state.error_count > 10:
                        print(f"[BotManager] Agent {state.agent_id} has too many errors, "
                              "not auto-restarting")
                        state.status = 'error'
                        db.session.commit()
                        continue

                    print(f"[BotManager] Auto-restarting crashed bot "
                          f"for agent {state.agent_id}")
                    self._bots.pop(state.agent_id, None)
                    self._threads.pop(state.agent_id, None)
                    self.start_bot(state.agent_id)
