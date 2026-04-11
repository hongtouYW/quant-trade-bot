"""
风险管理总入口 - FR-030 ~ FR-034
信号进来后,在这里做最终的通过/拒绝判断
"""
from dataclasses import dataclass
from risk.circuit_breaker import circuit_breaker
from risk.black_swan import black_swan_monitor
from risk.position_risk import (
    calculate_position_size, validate_leverage, get_leverage_tier,
)
from core.constants import MAX_CONCURRENT_POSITIONS, get_leverage_tier as get_tier
from core.logger import get_logger

logger = get_logger('risk_manager')


@dataclass
class RiskCheckResult:
    approved: bool
    position_size: float
    leverage: int
    stop_loss_roi: float
    reason: str = ""


class RiskManager:
    """
    风控主入口
    按顺序检查: 黑天鹅 → 断路器 → 持仓数 → 同币冷却 → 杠杆 → 仓位
    """

    def __init__(self):
        self._open_positions_count = 0
        self._open_symbols = set()

    def update_positions(self, count: int, symbols: set):
        """从 DB/内存更新当前持仓状态"""
        self._open_positions_count = count
        self._open_symbols = symbols

    def check(self, signal: dict) -> RiskCheckResult:
        """
        综合风控检查

        Args:
            signal: 信号字典,包含 symbol, direction, tier 等

        Returns:
            RiskCheckResult
        """
        symbol = signal.get('symbol', '')
        tier_config = get_tier(symbol)

        # 1. 黑天鹅熔断 (FR-033)
        if black_swan_monitor.is_active():
            return RiskCheckResult(
                False, 0, 0, 0,
                "black_swan_active"
            )

        # 2. 断路器 (FR-031 + FR-032)
        active, reason = circuit_breaker.is_active()
        if active:
            return RiskCheckResult(
                False, 0, 0, 0,
                f"circuit_breaker: {reason}"
            )

        # 3. 最大持仓数 (FR-030) — 从 DB 实时读
        from models.db_ops import count_open_trades, get_open_symbols
        current_count = count_open_trades()
        if current_count >= MAX_CONCURRENT_POSITIONS:
            return RiskCheckResult(
                False, 0, 0, 0,
                f"max_positions_{current_count}/{MAX_CONCURRENT_POSITIONS}"
            )

        # 4. 同币冷却 (FR-034)
        cooled, remaining = circuit_breaker.is_symbol_cooled(symbol)
        if cooled:
            return RiskCheckResult(
                False, 0, 0, 0,
                f"symbol_cooldown_{remaining}min"
            )

        # 5. 同币已有持仓 — 从 DB 实时读
        open_symbols = get_open_symbols()
        if symbol in open_symbols:
            return RiskCheckResult(
                False, 0, 0, 0,
                f"symbol_already_open"
            )

        # 6. 杠杆验证 (BR-001)
        leverage = tier_config['max_leverage']
        valid, max_lev, tier_name = validate_leverage(symbol, leverage)
        if not valid:
            return RiskCheckResult(
                False, 0, 0, 0,
                f"leverage_{leverage}_exceeds_{max_lev}_{tier_name}"
            )

        # 7. 仓位计算 (BR-005 + BR-008 + FR-031)
        consecutive = circuit_breaker.get_consecutive_losses()
        position_size = calculate_position_size(
            consecutive_losses=consecutive
        )

        stop_loss_roi = tier_config['stop_loss_roi']

        logger.info("risk_check.approved",
                    symbol=symbol, leverage=leverage,
                    position_size=position_size, tier=tier_name)

        return RiskCheckResult(
            approved=True,
            position_size=position_size,
            leverage=leverage,
            stop_loss_roi=stop_loss_roi,
        )


# 全局实例
risk_manager = RiskManager()
