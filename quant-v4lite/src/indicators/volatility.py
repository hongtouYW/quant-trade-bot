import numpy as np
from typing import List, Tuple

from src.core.models import Kline


def atr(klines: List[Kline], period: int = 14) -> float:
    """真实波幅均值，输出价格单位"""
    if len(klines) < 2:
        return 0.0
    trs = []
    for i in range(1, len(klines)):
        tr = max(
            klines[i].high - klines[i].low,
            abs(klines[i].high - klines[i - 1].close),
            abs(klines[i].low - klines[i - 1].close)
        )
        trs.append(tr)
    if len(trs) < period:
        return float(np.mean(trs)) if trs else 0
    return float(np.mean(trs[-period:]))


def atrp(klines: List[Kline], period: int = 14) -> float:
    """ATR 百分比，输出百分比小数（如 0.015 = 1.5%）"""
    atr_val = atr(klines, period)
    if klines[-1].close == 0:
        return 0
    return atr_val / klines[-1].close


def bollinger(klines: List[Kline], period: int = 20,
              std_dev: float = 2.0) -> Tuple[float, float, float]:
    """布林带，输出 (upper, mid, lower) 最新值"""
    closes = np.array([k.close for k in klines[-period:]])
    mid = float(np.mean(closes))
    std = float(np.std(closes))
    return (mid + std_dev * std, mid, mid - std_dev * std)


def bollinger_width(klines: List[Kline], period: int = 20,
                    std_dev: float = 2.0) -> float:
    """布林带宽度百分比"""
    upper, mid, lower = bollinger(klines, period, std_dev)
    if mid == 0:
        return 0.0
    return (upper - lower) / mid
