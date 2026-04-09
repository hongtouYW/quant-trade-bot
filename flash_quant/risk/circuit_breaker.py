"""
断路器引擎 - FR-031, FR-032, FR-034
管理连亏/时段/同币冷却断路器
"""
from datetime import datetime, timezone, timedelta
from core.constants import (
    CIRCUIT_CONSECUTIVE_PAUSE, CIRCUIT_CONSECUTIVE_PAUSE_HOURS,
    CIRCUIT_CONSECUTIVE_FULL_PAUSE, CIRCUIT_CONSECUTIVE_FULL_PAUSE_HOURS,
    CIRCUIT_DAILY_LOSS_PCT, CIRCUIT_WEEKLY_LOSS_PCT, CIRCUIT_WEEKLY_PAUSE_HOURS,
    CIRCUIT_MONTHLY_LOSS_PCT, CIRCUIT_MONTHLY_PAUSE_HOURS,
    COOLDOWN_AFTER_CLOSE_HOURS,
)
from core.logger import get_logger

logger = get_logger('circuit_breaker')


class CircuitBreakerEngine:
    """
    断路器状态管理 (内存版, 生产可持久化到 DB)
    """

    def __init__(self):
        # type -> (active: bool, expires_at: datetime, reason: str)
        self._breakers = {}
        # symbol -> last_close_time
        self._symbol_cooldowns = {}
        # 交易记录 (用于计算连亏/日亏)
        self._recent_trades = []

    def record_trade(self, pnl: float, symbol: str, close_time: datetime = None):
        """记录一笔交易结果"""
        if close_time is None:
            close_time = datetime.now(timezone.utc)
        self._recent_trades.append({
            'pnl': pnl,
            'symbol': symbol,
            'close_time': close_time,
        })
        self._symbol_cooldowns[symbol] = close_time

        # 检查是否需要触发断路器
        self._check_consecutive_losses()
        self._check_period_losses()

    def is_active(self, types: list = None) -> tuple:
        """
        检查断路器是否激活

        Args:
            types: 要检查的类型列表, None 表示检查全部

        Returns:
            (active: bool, reason: str)
        """
        now = datetime.now(timezone.utc)

        # 清理过期的
        expired = [k for k, v in self._breakers.items()
                   if v['expires_at'] <= now]
        for k in expired:
            logger.info("circuit_breaker.expired", type=k)
            del self._breakers[k]

        # 检查
        for btype, bdata in self._breakers.items():
            if types and btype not in types:
                continue
            if bdata['active'] and bdata['expires_at'] > now:
                return True, f"{btype}: {bdata['reason']}"

        return False, ""

    def is_symbol_cooled(self, symbol: str) -> tuple:
        """
        FR-034: 检查同币冷却

        Returns:
            (cooled: bool, remaining_minutes: int)
        """
        if symbol not in self._symbol_cooldowns:
            return False, 0

        last_close = self._symbol_cooldowns[symbol]
        now = datetime.now(timezone.utc)
        if last_close.tzinfo is None:
            last_close = last_close.replace(tzinfo=timezone.utc)

        elapsed = now - last_close
        cooldown = timedelta(hours=COOLDOWN_AFTER_CLOSE_HOURS)

        if elapsed < cooldown:
            remaining = (cooldown - elapsed).total_seconds() / 60
            return True, int(remaining)

        return False, 0

    def activate(self, btype: str, duration_hours: float, reason: str):
        """手动激活断路器"""
        now = datetime.now(timezone.utc)
        self._breakers[btype] = {
            'active': True,
            'expires_at': now + timedelta(hours=duration_hours),
            'reason': reason,
        }
        logger.warning("circuit_breaker.activated",
                       type=btype, duration_hours=duration_hours, reason=reason)

    def deactivate(self, btype: str):
        """手动停用断路器"""
        if btype in self._breakers:
            del self._breakers[btype]
            logger.info("circuit_breaker.deactivated", type=btype)

    def reset_symbol_cooldown(self, symbol: str):
        """手动重置同币冷却"""
        if symbol in self._symbol_cooldowns:
            del self._symbol_cooldowns[symbol]

    def get_consecutive_losses(self, window_hours: int = 24) -> int:
        """计算窗口内连续亏损笔数"""
        now = datetime.now(timezone.utc)
        cutoff = now - timedelta(hours=window_hours)

        recent = [t for t in self._recent_trades
                  if t['close_time'] >= cutoff]

        if not recent:
            return 0

        # 从最新往前数连续亏损
        count = 0
        for t in reversed(recent):
            if t['pnl'] < 0:
                count += 1
            else:
                break

        return count

    def get_period_pnl(self, hours: int) -> float:
        """计算指定时间段内的总 PnL"""
        now = datetime.now(timezone.utc)
        cutoff = now - timedelta(hours=hours)
        return sum(t['pnl'] for t in self._recent_trades
                   if t['close_time'] >= cutoff)

    def _check_consecutive_losses(self):
        """检查连亏断路器"""
        losses = self.get_consecutive_losses()

        if losses >= CIRCUIT_CONSECUTIVE_FULL_PAUSE:
            self.activate(
                'consecutive_loss',
                CIRCUIT_CONSECUTIVE_FULL_PAUSE_HOURS,
                f"consecutive_loss_{losses}_pause_{CIRCUIT_CONSECUTIVE_FULL_PAUSE_HOURS}h"
            )
        elif losses >= CIRCUIT_CONSECUTIVE_PAUSE:
            self.activate(
                'consecutive_loss',
                CIRCUIT_CONSECUTIVE_PAUSE_HOURS,
                f"consecutive_loss_{losses}_pause_{CIRCUIT_CONSECUTIVE_PAUSE_HOURS}h"
            )

    def _check_period_losses(self, balance: float = 10000):
        """检查时段断路器 (日/周/月)"""
        daily_pnl = self.get_period_pnl(24)
        weekly_pnl = self.get_period_pnl(24 * 7)
        monthly_pnl = self.get_period_pnl(24 * 30)

        if balance <= 0:
            return

        # 单日
        if daily_pnl / balance <= -CIRCUIT_DAILY_LOSS_PCT:
            # 暂停到当日 UTC 23:59
            now = datetime.now(timezone.utc)
            remaining = (24 - now.hour) + (60 - now.minute) / 60
            self.activate('daily_loss', remaining,
                         f"daily_loss_{daily_pnl:.1f}U_{daily_pnl/balance:.1%}")

        # 单周
        if weekly_pnl / balance <= -CIRCUIT_WEEKLY_LOSS_PCT:
            self.activate('weekly_loss', CIRCUIT_WEEKLY_PAUSE_HOURS,
                         f"weekly_loss_{weekly_pnl:.1f}U_{weekly_pnl/balance:.1%}")

        # 单月
        if monthly_pnl / balance <= -CIRCUIT_MONTHLY_LOSS_PCT:
            self.activate('monthly_loss', CIRCUIT_MONTHLY_PAUSE_HOURS,
                         f"monthly_loss_{monthly_pnl:.1f}U_{monthly_pnl/balance:.1%}")


# 全局实例
circuit_breaker = CircuitBreakerEngine()
