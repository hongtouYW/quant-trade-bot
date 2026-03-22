import logging
from datetime import datetime, timedelta
from typing import Optional, Tuple, List

from src.core.models import Signal
from src.core.exceptions import RiskLimitError

logger = logging.getLogger(__name__)


class RiskControl:
    """四层风控中心"""

    def __init__(self, config: dict, initial_balance: float):
        self._config = config.get('risk', {})
        self._initial_balance = initial_balance
        self._current_balance = initial_balance

        # 日统计
        self._daily_pnl = 0.0
        self._daily_trade_count = 0
        self._daily_win_count = 0
        self._consecutive_losses = 0
        self._cooldown_until: Optional[datetime] = None
        self._symbol_daily_pnl: dict = {}  # {symbol: pnl}
        self._symbol_loss_count: dict = {}  # {symbol: consecutive_losses}
        self._symbol_cooldown: dict = {}  # {symbol: cooldown_until}

        # 周统计
        self._weekly_pnl = 0.0

        # 异常检测
        self._risk_level = 0

    @property
    def consecutive_losses(self) -> int:
        return self._consecutive_losses

    def pre_trade_check(self, signal: Optional[Signal] = None) -> Tuple[bool, str]:
        """返回 (可否交易, 原因)"""

        # 冷却期
        if self._cooldown_until and datetime.utcnow() < self._cooldown_until:
            remaining = (self._cooldown_until - datetime.utcnow()).seconds // 60
            return False, f"cooldown: {remaining}min remaining"

        # Layer 2: 日级别
        daily_loss_limit = self._config.get('daily_loss_limit_pct', 3.0) / 100
        if self._daily_pnl / self._initial_balance <= -daily_loss_limit:
            return False, f"daily_loss_limit: {self._daily_pnl:.2f}U"

        daily_profit_hard = self._config.get('daily_profit_hard_stop_pct', 8.0) / 100
        if self._daily_pnl / self._initial_balance >= daily_profit_hard:
            return False, f"daily_profit_hard_stop: {self._daily_pnl:.2f}U"

        max_consecutive = self._config.get('max_consecutive_losses', 5)
        if self._consecutive_losses >= max_consecutive:
            cooldown_min = self._config.get('cooldown_after_streak_min', 30)
            self._cooldown_until = datetime.utcnow() + timedelta(minutes=cooldown_min)
            self._consecutive_losses = 0
            return False, f"consecutive_loss_streak: cooldown {cooldown_min}min"

        # 单币冷却检查 (止损后冷却 30 分钟)
        if signal:
            sym_cd = self._symbol_cooldown.get(signal.symbol)
            if sym_cd and datetime.utcnow() < sym_cd:
                remaining = (sym_cd - datetime.utcnow()).seconds // 60
                return False, f"symbol_cooldown: {signal.symbol} {remaining}min remaining"

            # 单币连亏上限 (同一币种最多连亏 2 次)
            sym_losses = self._symbol_loss_count.get(signal.symbol, 0)
            if sym_losses >= 2:
                return False, f"symbol_loss_limit: {signal.symbol} {sym_losses} consecutive losses"

            # 单币日亏损检查
            sym_pnl = self._symbol_daily_pnl.get(signal.symbol, 0)
            sym_limit = self._config.get('same_symbol_daily_loss_pct', 1.0) / 100
            if sym_pnl / self._initial_balance <= -sym_limit:
                return False, f"symbol_daily_loss: {signal.symbol} {sym_pnl:.2f}U"

        # Layer 3: 系统级
        if self._risk_level >= 2:
            return False, f"system_risk_level: {self._risk_level}"

        # Layer 4: 账户级
        total_drawdown = self._config.get('total_drawdown_stop_pct', 15.0) / 100
        drawdown = (self._initial_balance - self._current_balance) / self._initial_balance
        if drawdown >= total_drawdown:
            return False, f"total_drawdown: {drawdown*100:.1f}%"

        weekly_limit = self._config.get('weekly_loss_limit_pct', 8.0) / 100
        if self._weekly_pnl / self._initial_balance <= -weekly_limit:
            return False, f"weekly_loss_limit: {self._weekly_pnl:.2f}U"

        return True, "ok"

    def get_risk_scale(self) -> float:
        """返回仓位缩放系数"""
        daily_pct = self._daily_pnl / self._initial_balance

        if daily_pct <= -0.02:
            return 0.3
        if daily_pct <= -0.01:
            return 0.5

        daily_profit_target = self._config.get('daily_profit_target_pct', 5.0) / 100
        if daily_pct >= daily_profit_target:
            return 0.5

        # 周亏损减仓
        weekly_pct = self._weekly_pnl / self._initial_balance
        if weekly_pct <= -0.05:
            return 0.5

        return 1.0

    def on_trade_close(self, pnl: float, symbol: str):
        """交易结束后更新状态"""
        self._daily_pnl += pnl
        self._weekly_pnl += pnl
        self._current_balance += pnl
        self._daily_trade_count += 1

        if pnl > 0:
            self._daily_win_count += 1
            self._consecutive_losses = 0
            self._symbol_loss_count[symbol] = 0
            # 盈利后冷却 10 分钟，防止同币种立刻重复开仓
            self._symbol_cooldown[symbol] = datetime.utcnow() + timedelta(minutes=10)
            logger.info(f"Symbol cooldown: {symbol} 10min after win (pnl={pnl:.2f}U)")
        else:
            self._consecutive_losses += 1
            self._symbol_loss_count[symbol] = self._symbol_loss_count.get(symbol, 0) + 1
            # 止损后冷却 30 分钟
            self._symbol_cooldown[symbol] = datetime.utcnow() + timedelta(minutes=30)
            logger.info(f"Symbol cooldown: {symbol} 30min (loss #{self._symbol_loss_count[symbol]})")

        self._symbol_daily_pnl[symbol] = self._symbol_daily_pnl.get(symbol, 0) + pnl

    def set_risk_level(self, level: int):
        """设置系统风险等级 (0-3)"""
        self._risk_level = level

    def daily_reset(self):
        """UTC 00:00 重置"""
        self._daily_pnl = 0.0
        self._daily_trade_count = 0
        self._daily_win_count = 0
        self._symbol_daily_pnl.clear()
        self._symbol_loss_count.clear()
        self._symbol_cooldown.clear()
        self._cooldown_until = None

    def weekly_reset(self):
        """每周一 00:00 重置"""
        self._weekly_pnl = 0.0

    def update_balance(self, balance: float):
        self._current_balance = balance
