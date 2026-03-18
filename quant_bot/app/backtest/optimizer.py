"""参数自动调优器 - Phase 3 (Spec §29 扩展)
通过网格搜索在回测中寻找最优参数组合。
支持的可调参数:
  - risk_per_trade: 单笔风险百分比
  - stop_atr_multiple: 止损ATR倍数
  - tp1_r_multiple: TP1 R倍数
  - tp2_r_multiple: TP2 R倍数
  - adx_min: ADX最低阈值
  - score_min_trade: 最低开仓分数
"""
import logging
import itertools
import copy
import time
from typing import Dict, List, Any

from app.backtest.engine import BacktestEngine
from app.config import get

log = logging.getLogger(__name__)


# 默认搜索空间
DEFAULT_PARAM_GRID = {
    'risk_per_trade': [0.002, 0.003, 0.004, 0.005],
    'stop_atr_multiple': [1.5, 2.0, 2.5, 3.0],
    'tp1_r_multiple': [1.0, 1.2, 1.5, 2.0],
    'tp2_r_multiple': [2.0, 2.5, 3.0, 3.5],
}

# 可选扩展参数
EXTENDED_PARAM_GRID = {
    'adx_min': [18, 20, 22, 25],
    'score_min_trade': [58, 62, 66, 70],
}


class ParameterOptimizer:
    """网格搜索参数调优器"""

    def __init__(self, symbol_data, symbols=None):
        """
        symbol_data: {symbol: DataFrame} 回测数据
        symbols: 回测币种列表
        """
        self.symbol_data = symbol_data
        self.symbols = symbols
        self.results = []

    def optimize(self, param_grid=None, metric='sharpe', top_n=10, max_combos=500):
        """
        运行网格搜索优化
        param_grid: {param_name: [values]} 参数搜索空间
        metric: 优化目标指标 (sharpe, profit_factor, total_return_pct, win_rate)
        top_n: 返回前N个最优结果
        max_combos: 最大组合数限制 (防止过长)
        """
        if param_grid is None:
            param_grid = DEFAULT_PARAM_GRID

        # 生成所有参数组合
        param_names = list(param_grid.keys())
        param_values = list(param_grid.values())
        all_combos = list(itertools.product(*param_values))

        if len(all_combos) > max_combos:
            log.warning(f"参数组合数 {len(all_combos)} 超过限制 {max_combos}, 随机采样")
            import random
            random.shuffle(all_combos)
            all_combos = all_combos[:max_combos]

        log.info(f"参数调优开始: {len(all_combos)} 个组合, 优化目标={metric}")
        start_time = time.time()

        self.results = []
        for i, combo in enumerate(all_combos):
            params = dict(zip(param_names, combo))

            try:
                metrics = self._run_single(params)
                if metrics and 'error' not in metrics:
                    score = metrics.get(metric, 0)
                    self.results.append({
                        'params': params,
                        'score': score,
                        'metrics': metrics,
                    })
            except Exception as e:
                log.debug(f"组合 {params} 失败: {e}")

            if (i + 1) % 50 == 0:
                elapsed = time.time() - start_time
                log.info(f"进度: {i+1}/{len(all_combos)} ({elapsed:.1f}s)")

        elapsed = time.time() - start_time
        log.info(f"参数调优完成: {len(self.results)} 个有效结果, 耗时 {elapsed:.1f}s")

        # 按目标指标排序
        self.results.sort(key=lambda x: x['score'], reverse=True)
        return self.results[:top_n]

    def _run_single(self, params):
        """用指定参数运行一次回测"""
        # 以当前execution配置为基础, 覆盖优化参数
        base_cfg = copy.copy(get('execution'))
        base_cfg.update(params)
        engine = BacktestEngine(config=base_cfg)
        return engine.run(self.symbol_data, self.symbols)

    def get_best_params(self):
        """获取最优参数"""
        if not self.results:
            return None
        return self.results[0]

    def get_summary(self, top_n=5):
        """获取优化结果摘要"""
        if not self.results:
            return "无结果"

        lines = [f"参数调优结果 (共{len(self.results)}个组合)\n"]
        for i, r in enumerate(self.results[:top_n]):
            m = r['metrics']
            lines.append(
                f"#{i+1} [score={r['score']:.2f}]\n"
                f"  参数: {r['params']}\n"
                f"  收益: {m.get('total_return_pct', 0):.1f}% | "
                f"胜率: {m.get('win_rate', 0):.1f}% | "
                f"PF: {m.get('profit_factor', 0):.2f} | "
                f"Sharpe: {m.get('sharpe', 0):.2f} | "
                f"MaxDD: {m.get('max_drawdown_pct', 0):.1f}% | "
                f"交易: {m.get('total_trades', 0)}笔"
            )
        return '\n'.join(lines)

    def suggest_config_update(self):
        """生成建议的配置更新"""
        best = self.get_best_params()
        if not best:
            return None

        suggestions = {}
        for k, v in best['params'].items():
            current = get('execution', k, None) or get('trend_filter', k, None)
            if current is not None and current != v:
                suggestions[k] = {'current': current, 'suggested': v}

        return suggestions
