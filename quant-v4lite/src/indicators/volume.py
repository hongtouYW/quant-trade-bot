import numpy as np
from typing import List

from src.core.models import Kline


def volume_ratio(klines: List[Kline], recent: int = 5,
                 baseline: int = 20) -> float:
    """量比: 近期量/均量 倍数"""
    if len(klines) < recent + baseline:
        return 1.0
    recent_avg = np.mean([k.volume for k in klines[-recent:]])
    baseline_avg = np.mean([k.volume for k in klines[-recent - baseline:-recent]])
    if baseline_avg == 0:
        return 1.0
    return float(recent_avg / baseline_avg)


def taker_buy_ratio(klines: List[Kline], period: int = 10) -> float:
    """主买占比: 0-1（0.5 = 买卖均衡）"""
    total_vol = sum(k.volume for k in klines[-period:])
    if total_vol == 0:
        return 0.5
    buy_vol = sum(k.taker_buy_volume for k in klines[-period:])
    return float(buy_vol / total_vol)
