import logging
from typing import List, Optional, Tuple

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from .base import BaseStrategy
from .trend_follow import TrendFollowStrategy
from .pullback_breakout import PullbackBreakoutStrategy
from .mean_reversion import MeanReversionStrategy
from .vol_breakout import VolatilityBreakoutStrategy
from .funding_arb import FundingArbitrageStrategy

logger = logging.getLogger(__name__)


class SignalAggregator:
    """
    信号聚合器:
    1. 遍历策略检查信号
    2. 同币种多策略同方向 → 置信度 +0.10
    3. 方向偏好过滤
    4. 增强检查 (V4)
    """

    def __init__(self):
        self._strategies: dict[str, BaseStrategy] = {
            'trend_follow': TrendFollowStrategy(),
            'pullback_breakout': PullbackBreakoutStrategy(),
            'mean_reversion': MeanReversionStrategy(),
            'volatility_breakout': VolatilityBreakoutStrategy(),
            'funding_arbitrage': FundingArbitrageStrategy(),
        }

    def register_strategy(self, strategy: BaseStrategy):
        self._strategies[strategy.name] = strategy

    def scan(self, symbol: str,
             klines_1h: List[Kline],
             klines_15m: List[Kline],
             active_strategies: List[str],
             direction_bias: str,
             config: dict,
             funding_rate: float = 0.0,
             hours_to_funding: float = 99) -> Optional[Signal]:
        """扫描信号"""
        signals: List[Signal] = []

        # 确定扫描方向
        directions = []
        if direction_bias in ('long', 'both'):
            directions.append(Direction.LONG)
        if direction_bias in ('short', 'both'):
            directions.append(Direction.SHORT)

        for strat_name in active_strategies:
            # 检查 config 中策略是否启用
            strat_cfg = config.get('strategies', {}).get(strat_name, {})
            if not strat_cfg.get('enabled', True):
                continue
            strategy = self._strategies.get(strat_name)
            if not strategy:
                continue
            for direction in directions:
                if strat_name == 'funding_arbitrage':
                    signal = strategy.check_signal(
                        symbol, klines_1h, klines_15m, direction, config,
                        funding_rate=funding_rate,
                        hours_to_funding=hours_to_funding)
                else:
                    signal = strategy.check_signal(
                        symbol, klines_1h, klines_15m, direction, config)
                if signal:
                    signals.append(signal)

        if not signals:
            return None

        # 同方向多策略确认 → 置信度加成（需全部同向才算确认）
        best = max(signals, key=lambda s: s.confidence)
        same_dir_count = sum(1 for s in signals if s.direction == best.direction)
        opposite_count = len(signals) - same_dir_count
        if same_dir_count > 1 and opposite_count == 0:
            best.confidence = min(best.confidence + 0.10, 0.95)
            best.multi_confirm = True

        # 裁剪置信度
        best.confidence = max(0.10, min(0.95, best.confidence))

        return best

    def enhanced_check(self, signal: Signal,
                       orderbook_analysis=None,
                       consensus_result=None) -> Tuple[Optional[Signal], str]:
        """V4 增强检查"""
        # 订单簿过滤
        if orderbook_analysis and not orderbook_analysis.can_enter:
            return None, f"orderbook_reject: {orderbook_analysis.reason}"

        if orderbook_analysis:
            signal.trend_score += orderbook_analysis.score_adjustment

        # 多所共识
        if consensus_result:
            if consensus_result.recommendation == 'reject':
                return None, "consensus_reject"
            elif consensus_result.recommendation == 'strong':
                signal.confidence = min(signal.confidence + 0.05, 0.95)
            elif consensus_result.recommendation == 'weak':
                signal.confidence = max(signal.confidence - 0.10, 0.10)

        return signal, "passed"
