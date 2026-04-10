"""
技术指标计算 (纯 Python, 不依赖 TA-Lib)
Tier 2 + Tier 3 共用
"""
from typing import List, Tuple
import math


def ema(data: List[float], period: int) -> List[float]:
    """指数移动平均线"""
    if len(data) < period:
        return []
    k = 2 / (period + 1)
    result = [sum(data[:period]) / period]
    for i in range(period, len(data)):
        result.append(data[i] * k + result[-1] * (1 - k))
    return result


def sma(data: List[float], period: int) -> List[float]:
    """简单移动平均线"""
    if len(data) < period:
        return []
    result = []
    for i in range(period - 1, len(data)):
        result.append(sum(data[i - period + 1:i + 1]) / period)
    return result


def rsi(closes: List[float], period: int = 14) -> List[float]:
    """RSI 相对强弱指标"""
    if len(closes) < period + 1:
        return []

    deltas = [closes[i] - closes[i - 1] for i in range(1, len(closes))]
    gains = [d if d > 0 else 0 for d in deltas]
    losses = [-d if d < 0 else 0 for d in deltas]

    avg_gain = sum(gains[:period]) / period
    avg_loss = sum(losses[:period]) / period

    result = []
    if avg_loss == 0:
        result.append(100.0)
    else:
        rs = avg_gain / avg_loss
        result.append(100 - 100 / (1 + rs))

    for i in range(period, len(deltas)):
        avg_gain = (avg_gain * (period - 1) + gains[i]) / period
        avg_loss = (avg_loss * (period - 1) + losses[i]) / period
        if avg_loss == 0:
            result.append(100.0)
        else:
            rs = avg_gain / avg_loss
            result.append(100 - 100 / (1 + rs))

    return result


def macd(closes: List[float], fast: int = 12, slow: int = 26,
         signal: int = 9) -> Tuple[List[float], List[float], List[float]]:
    """
    MACD 指标
    Returns: (macd_line, signal_line, histogram)
    """
    if len(closes) < slow + signal:
        return [], [], []

    ema_fast = ema(closes, fast)
    ema_slow = ema(closes, slow)

    # 对齐: ema_fast 从 index (fast-1) 开始, ema_slow 从 (slow-1) 开始
    offset = slow - fast
    macd_line = [ema_fast[i + offset] - ema_slow[i] for i in range(len(ema_slow))]

    signal_line = ema(macd_line, signal)

    # histogram 对齐
    hist_offset = signal - 1
    histogram = [macd_line[i + hist_offset] - signal_line[i] for i in range(len(signal_line))]

    return macd_line, signal_line, histogram


def adx(highs: List[float], lows: List[float], closes: List[float],
        period: int = 14) -> List[float]:
    """ADX 平均趋向指标"""
    if len(closes) < period * 2:
        return []

    tr_list = []
    plus_dm_list = []
    minus_dm_list = []

    for i in range(1, len(closes)):
        h = highs[i]
        l = lows[i]
        pc = closes[i - 1]
        tr = max(h - l, abs(h - pc), abs(l - pc))
        tr_list.append(tr)

        up = highs[i] - highs[i - 1]
        down = lows[i - 1] - lows[i]
        plus_dm_list.append(up if up > down and up > 0 else 0)
        minus_dm_list.append(down if down > up and down > 0 else 0)

    # Smoothed with Wilder's method
    def wilder_smooth(data, p):
        result = [sum(data[:p])]
        for i in range(p, len(data)):
            result.append(result[-1] - result[-1] / p + data[i])
        return result

    atr = wilder_smooth(tr_list, period)
    plus_di_smooth = wilder_smooth(plus_dm_list, period)
    minus_di_smooth = wilder_smooth(minus_dm_list, period)

    dx_list = []
    for i in range(len(atr)):
        if atr[i] == 0:
            dx_list.append(0)
            continue
        plus_di = 100 * plus_di_smooth[i] / atr[i]
        minus_di = 100 * minus_di_smooth[i] / atr[i]
        di_sum = plus_di + minus_di
        if di_sum == 0:
            dx_list.append(0)
        else:
            dx_list.append(100 * abs(plus_di - minus_di) / di_sum)

    adx_result = wilder_smooth(dx_list, period)
    return [v / period for v in adx_result]  # normalize


def bollinger_bands(closes: List[float], period: int = 20,
                    std_dev: float = 2.0) -> Tuple[List[float], List[float], List[float]]:
    """
    布林带
    Returns: (upper, middle, lower)
    """
    if len(closes) < period:
        return [], [], []

    middle = sma(closes, period)
    upper = []
    lower = []

    for i in range(len(middle)):
        start = i
        window = closes[start:start + period]
        avg = middle[i]
        variance = sum((x - avg) ** 2 for x in window) / period
        std = math.sqrt(variance)
        upper.append(avg + std_dev * std)
        lower.append(avg - std_dev * std)

    return upper, middle, lower


def ema_cross(data: List[float], fast_period: int = 9,
              slow_period: int = 21) -> str:
    """
    EMA 穿越检测
    Returns: 'golden_cross' / 'death_cross' / 'none'
    """
    if len(data) < slow_period + 2:
        return 'none'

    ema_fast = ema(data, fast_period)
    ema_slow = ema(data, slow_period)

    # 对齐
    offset = slow_period - fast_period
    if len(ema_fast) <= offset + 1 or len(ema_slow) < 2:
        return 'none'

    # 当前和前一根
    fast_curr = ema_fast[-1 + offset] if len(ema_fast) > offset else ema_fast[-1]
    fast_prev = ema_fast[-2 + offset] if len(ema_fast) > offset + 1 else ema_fast[-2]
    slow_curr = ema_slow[-1]
    slow_prev = ema_slow[-2]

    if fast_prev <= slow_prev and fast_curr > slow_curr:
        return 'golden_cross'
    elif fast_prev >= slow_prev and fast_curr < slow_curr:
        return 'death_cross'
    return 'none'


def volume_ratio(volumes: List[float], lookback: int = 20) -> float:
    """成交量比 (最新 vs 前 N 根均值)"""
    if len(volumes) < 2:
        return 0
    latest = volumes[-1]
    prev = volumes[:-1][-lookback:]
    avg = sum(prev) / len(prev) if prev else 0
    return latest / avg if avg > 0 else 0
