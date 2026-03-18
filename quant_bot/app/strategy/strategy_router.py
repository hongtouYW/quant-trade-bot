"""多策略路由器 - Phase 2 (Spec §29 扩展)
根据市场状态自动选择最佳策略:
  - TRENDING: 趋势跟随(回踩/突破) 优先
  - RANGING: 均值回归 优先, 趋势A级也可
  - 任意: 资金费套利 (极端费率时)
"""
import logging
from app.config import get

log = logging.getLogger(__name__)


class StrategyRouter:
    """策略路由器 - 管理多策略优先级"""

    def __init__(self, signal_engine, mean_reversion=None, funding_arb=None):
        self.signal_engine = signal_engine
        self.mean_reversion = mean_reversion
        self.funding_arb = funding_arb

    def find_best_setup(self, symbol, direction, snapshot, regime):
        """按优先级尝试所有策略, 返回最佳信号"""
        setups = []

        # 1. 趋势策略 (回踩 + 突破)
        if regime in ('TRENDING', 'RANGING'):
            trend_setup = self.signal_engine.find_setup(symbol, direction, snapshot)
            if trend_setup:
                setups.append(trend_setup)

        # 2. 均值回归 (仅RANGING)
        if self.mean_reversion and regime == 'RANGING':
            mr_setup = self.mean_reversion.find_setup(symbol, snapshot)
            if mr_setup:
                setups.append(mr_setup)

        # 3. 资金费套利 (任意市场状态)
        if self.funding_arb:
            fa_setup = self.funding_arb.find_setup(symbol, snapshot)
            if fa_setup:
                setups.append(fa_setup)

        if not setups:
            return None

        # 选择最佳: 按score排序
        best = max(setups, key=lambda s: s.score)

        if len(setups) > 1:
            strategies = [s.setup_type for s in setups]
            log.debug(f"{symbol} 多策略可选: {strategies} -> 选择 {best.setup_type} (score={best.score:.1f})")

        return best
