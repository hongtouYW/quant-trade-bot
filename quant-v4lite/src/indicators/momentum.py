import numpy as np
from typing import List

from src.core.models import Kline


def rsi(klines: List[Kline], period: int = 14) -> float:
    """相对强弱指标，输出 0-100"""
    if len(klines) < period + 1:
        return 50.0
    closes = np.array([k.close for k in klines])
    deltas = np.diff(closes)
    gains = np.where(deltas > 0, deltas, 0)
    losses = np.where(deltas < 0, -deltas, 0)

    avg_gain = np.mean(gains[-period:])
    avg_loss = np.mean(losses[-period:])

    if avg_loss == 0:
        return 100.0
    rs = avg_gain / avg_loss
    return float(100 - (100 / (1 + rs)))


def roc(klines: List[Kline], period: int = 20) -> float:
    """变化率，输出百分比小数（如 0.05 = 5%）"""
    if len(klines) <= period:
        return 0.0
    prev = klines[-1 - period].close
    if prev == 0:
        return 0.0
    return float((klines[-1].close - prev) / prev)
