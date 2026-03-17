import numpy as np
from typing import List, Dict, Tuple

from src.core.models import Kline


class CorrelationCalculator:
    """相关性计算，用于去重同方向高相关标的"""

    def __init__(self):
        self._matrix: Dict[str, Dict[str, float]] = {}

    def calculate_returns(self, klines: List[Kline]) -> np.ndarray:
        """计算收益率序列"""
        closes = np.array([k.close for k in klines])
        if len(closes) < 2:
            return np.array([])
        return np.diff(closes) / closes[:-1]

    def correlation(self, klines_a: List[Kline], klines_b: List[Kline]) -> float:
        """两个币种的收益率相关系数"""
        ret_a = self.calculate_returns(klines_a)
        ret_b = self.calculate_returns(klines_b)

        min_len = min(len(ret_a), len(ret_b))
        if min_len < 10:
            return 0.0

        ret_a = ret_a[-min_len:]
        ret_b = ret_b[-min_len:]

        corr = np.corrcoef(ret_a, ret_b)[0, 1]
        return float(corr) if not np.isnan(corr) else 0.0

    def update_matrix(self, klines_dict: Dict[str, List[Kline]]):
        """更新相关性矩阵"""
        symbols = list(klines_dict.keys())
        self._matrix = {}
        for i, s1 in enumerate(symbols):
            self._matrix[s1] = {}
            for j, s2 in enumerate(symbols):
                if i == j:
                    self._matrix[s1][s2] = 1.0
                elif j < i and s2 in self._matrix and s1 in self._matrix[s2]:
                    self._matrix[s1][s2] = self._matrix[s2][s1]
                else:
                    self._matrix[s1][s2] = self.correlation(
                        klines_dict[s1], klines_dict[s2])

    def get_correlation(self, symbol_a: str, symbol_b: str) -> float:
        return self._matrix.get(symbol_a, {}).get(symbol_b, 0.0)

    def filter_correlated(self, symbols_with_scores: List[Tuple[str, float, str]],
                          max_corr: float = 0.75) -> List[str]:
        """
        去除同方向高相关标的，保留分数最高的
        symbols_with_scores: [(symbol, score, direction)]
        """
        kept = []
        for symbol, score, direction in symbols_with_scores:
            is_correlated = False
            for kept_sym in kept:
                corr = self.get_correlation(symbol, kept_sym)
                if corr > max_corr:
                    is_correlated = True
                    break
            if not is_correlated:
                kept.append(symbol)
        return kept
