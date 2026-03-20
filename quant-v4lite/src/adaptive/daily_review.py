import logging
from typing import List, Dict

from src.core.models import TradeRecord

logger = logging.getLogger(__name__)


class AdaptiveEngine:
    """
    自适应参数引擎 (Phase 3)
    每日 UTC 00:00 分析近 7 天交易，调整参数
    """

    def __init__(self, config: dict):
        self._enabled = config.get('adaptive', {}).get('enabled', False)
        self._lookback = config.get('adaptive', {}).get('lookback_days', 7)

    # 参数边界 — 防止自适应调参过于激进
    PARAM_BOUNDS = {
        'ranking.min_score': (55, 70),
        'liquidity_filter.min_volume_ratio': (0.6, 1.2),
        'execution.tp2_r': (2.0, 3.5),
        'execution.stop_atr_multiple': (0.8, 1.5),
    }

    def review(self, trade_history: List[TradeRecord],
               config: dict) -> dict:
        """分析交易记录，返回参数调整建议"""
        if not self._enabled or len(trade_history) < 10:
            return {}

        adjustments = {}

        # 统计
        wins = [t for t in trade_history if t.pnl > 0]
        losses = [t for t in trade_history if t.pnl <= 0]
        win_rate = len(wins) / len(trade_history) if trade_history else 0

        total_win = sum(t.pnl for t in wins)
        total_loss = abs(sum(t.pnl for t in losses))
        profit_factor = total_win / total_loss if total_loss > 0 else 999

        # 1. 胜率调整 (渐进式, 不跳到极端值)
        current_score = config.get('ranking', {}).get('min_score', 60)
        current_vol_ratio = config.get('liquidity_filter', {}).get('min_volume_ratio', 0.8)
        if win_rate < 0.25:
            new_score = min(current_score + 5, self.PARAM_BOUNDS['ranking.min_score'][1])
            new_vol = min(current_vol_ratio + 0.1, self.PARAM_BOUNDS['liquidity_filter.min_volume_ratio'][1])
            adjustments['ranking'] = {'min_score': new_score}
            adjustments['liquidity_filter'] = {'min_volume_ratio': round(new_vol, 1)}
        elif win_rate > 0.45:
            new_score = max(current_score - 5, self.PARAM_BOUNDS['ranking.min_score'][0])
            adjustments['ranking'] = {'min_score': new_score}

        # 2. 盈亏比调整 (温和)
        if profit_factor < 1.2:
            current_tp2 = config.get('execution', {}).get('tp2_r', 2.5)
            current_stop = config.get('execution', {}).get('stop_atr_multiple', 1.2)
            new_tp2 = min(current_tp2 + 0.2, self.PARAM_BOUNDS['execution.tp2_r'][1])
            new_stop = max(current_stop - 0.1, self.PARAM_BOUNDS['execution.stop_atr_multiple'][0])
            adjustments['execution'] = {
                'tp2_r': round(new_tp2, 1),
                'stop_atr_multiple': round(new_stop, 1),
            }

        # 3. 按策略分析 (仅供参考, 不自动降 confidence)
        strategy_pnl: Dict[str, float] = {}
        strategy_count: Dict[str, int] = {}
        for t in trade_history:
            s = t.strategy.value
            strategy_pnl[s] = strategy_pnl.get(s, 0) + t.pnl
            strategy_count[s] = strategy_count.get(s, 0) + 1

        confidence_adj = {}
        for strat, pnl in strategy_pnl.items():
            count = strategy_count.get(strat, 0)
            if pnl < 0 and count >= 10:
                confidence_adj[strat] = -0.05  # 温和降低, 不超过 -0.05
        if confidence_adj:
            adjustments['confidence_adjustments'] = confidence_adj

        # 4. 按小时统计 (仅分析, 不自动应用)
        hour_pnl: Dict[int, float] = {}
        for t in trade_history:
            h = t.open_time.hour
            hour_pnl[h] = hour_pnl.get(h, 0) + t.pnl

        if hour_pnl:
            sorted_hours = sorted(hour_pnl.items(), key=lambda x: x[1])
            worst_hours = [h for h, _ in sorted_hours[:4]]
            best_hours = [h for h, _ in sorted_hours[-8:]]
            adjustments['hour_analysis'] = {
                'best_hours': best_hours,
                'worst_hours': worst_hours,
            }

        logger.info(f"Adaptive review: win_rate={win_rate:.2f}, "
                    f"PF={profit_factor:.2f}, adjustments={len(adjustments)}")
        return adjustments
