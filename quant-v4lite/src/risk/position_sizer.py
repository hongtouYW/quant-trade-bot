import logging
from typing import Optional

from src.core.models import Signal, PositionSize

logger = logging.getLogger(__name__)


class PositionSizer:
    """
    固定风险法仓位计算
    risk_amount = balance × 0.4% × 各种缩放系数
    """

    def calculate(self, balance: float, signal: Signal,
                  routing_config: dict, risk_scale: float,
                  config: dict, consecutive_losses: int = 0) -> Optional[PositionSize]:

        risk_cfg = config.get('risk', {})
        leverage = config.get('account', {}).get('leverage', 10)

        # 基础风险金额
        risk_pct = risk_cfg.get('per_trade_risk_pct', 0.4) / 100
        risk_amount = balance * risk_pct

        # 市场状态缩放
        position_scale = routing_config.get('position_scale', 1.0)
        risk_amount *= position_scale

        # 日内表现缩放
        risk_amount *= risk_scale

        # 连亏缩放
        if consecutive_losses >= 4:
            risk_amount *= 0.50
        elif consecutive_losses >= 3:
            risk_amount *= 0.70
        elif consecutive_losses >= 2:
            risk_amount *= 0.85

        # 多策略确认加成
        if signal.multi_confirm:
            risk_amount *= 1.15

        # 止损距离
        stop_distance = abs(signal.entry_price - signal.stop_loss)
        if stop_distance == 0 or signal.entry_price == 0:
            return None

        stop_distance_pct = stop_distance / signal.entry_price

        # 名义持仓 = 风险金额 / 止损距离百分比
        notional = risk_amount / stop_distance_pct
        margin = notional / leverage
        quantity = notional / signal.entry_price

        # 上限检查
        max_single = risk_cfg.get('max_single_margin_pct', 5.0) / 100
        min_margin_pct = 0.01  # 最低 1% 本金

        if margin > balance * max_single:
            margin = balance * max_single
            notional = margin * leverage
            quantity = notional / signal.entry_price

        if margin < balance * min_margin_pct:
            logger.debug(f"Margin too small: {margin:.2f} < {balance * min_margin_pct:.2f}")
            return None

        return PositionSize(
            margin=margin,
            notional=notional,
            quantity=quantity,
            risk_amount=risk_amount,
            stop_distance_pct=stop_distance_pct,
        )
