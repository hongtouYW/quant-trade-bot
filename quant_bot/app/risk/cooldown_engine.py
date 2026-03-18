"""Cooldown engine - per-symbol, per-strategy, and global cooldowns"""
import time
import logging
from collections import defaultdict
from app.monitoring import notifier

log = logging.getLogger(__name__)


class CooldownEngine:
    def __init__(self):
        self._symbol_cooldowns = {}       # {symbol: expire_ts}
        self._strategy_cooldowns = {}     # {strategy: expire_ts}
        self._symbol_loss_counts = defaultdict(int)  # {(symbol, direction): count}
        self._fake_breakout_counts = defaultdict(int)

    def block(self, symbol, strategy=None):
        """Check if symbol/strategy is in cooldown"""
        now = time.time()
        if symbol in self._symbol_cooldowns and now < self._symbol_cooldowns[symbol]:
            return True
        if strategy and strategy in self._strategy_cooldowns and now < self._strategy_cooldowns[strategy]:
            return True
        return False

    def record_loss(self, symbol, direction, strategy=None):
        """Record a loss for cooldown tracking"""
        key = (symbol, direction)
        self._symbol_loss_counts[key] += 1

        # Same symbol same direction 2 consecutive stops -> 60min cooldown
        if self._symbol_loss_counts[key] >= 2:
            self._symbol_cooldowns[symbol] = time.time() + 60 * 60
            log.warning(f"Symbol cooldown: {symbol} direction={direction} for 60min")
            notifier.notify_cooldown(symbol, 60, f"同方向连续2次止损")
            self._symbol_loss_counts[key] = 0

        # Strategy cooldown
        if strategy:
            self._strategy_cooldowns[strategy] = time.time() + 30 * 60

    def record_fake_breakout(self, symbol, direction):
        """Record a fake breakout signal"""
        key = (symbol, direction)
        self._fake_breakout_counts[key] += 1
        if self._fake_breakout_counts[key] >= 2:
            self._symbol_cooldowns[symbol] = time.time() + 30 * 60
            log.warning(f"Fake breakout cooldown: {symbol} for 30min")
            notifier.notify_cooldown(symbol, 30, f"假突破连续2次")

    def record_win(self, symbol, direction):
        """Reset loss counts on win"""
        key = (symbol, direction)
        self._symbol_loss_counts[key] = 0
        self._fake_breakout_counts[key] = 0

    def get_status(self):
        now = time.time()
        active = {}
        for sym, ts in self._symbol_cooldowns.items():
            if ts > now:
                active[sym] = int(ts - now)
        return active
