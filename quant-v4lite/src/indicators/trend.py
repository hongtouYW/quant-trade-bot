import numpy as np
from typing import List

from src.core.models import Kline


def ema(klines: List[Kline], period: int) -> np.ndarray:
    """指数移动平均，返回与 klines 等长的数组"""
    closes = np.array([k.close for k in klines])
    multiplier = 2 / (period + 1)
    result = np.empty_like(closes)
    result[0] = closes[0]
    for i in range(1, len(closes)):
        result[i] = closes[i] * multiplier + result[i - 1] * (1 - multiplier)
    return result


def adx(klines: List[Kline], period: int = 14) -> float:
    """平均趋向指数，返回最新 ADX 值（0-100）"""
    if len(klines) < period * 2:
        return 0.0

    highs = np.array([k.high for k in klines])
    lows = np.array([k.low for k in klines])
    closes = np.array([k.close for k in klines])

    # True Range
    tr = np.maximum(
        highs[1:] - lows[1:],
        np.maximum(
            np.abs(highs[1:] - closes[:-1]),
            np.abs(lows[1:] - closes[:-1])
        )
    )

    # +DM / -DM
    up_move = highs[1:] - highs[:-1]
    down_move = lows[:-1] - lows[1:]

    plus_dm = np.where((up_move > down_move) & (up_move > 0), up_move, 0.0)
    minus_dm = np.where((down_move > up_move) & (down_move > 0), down_move, 0.0)

    # Wilder smoothing
    def wilder_smooth(arr, p):
        result = np.empty(len(arr))
        result[0] = np.mean(arr[:p])
        for i in range(1, len(arr)):
            result[i] = result[i - 1] - (result[i - 1] / p) + arr[i]
        return result

    atr_smooth = wilder_smooth(tr, period)
    plus_di = 100 * wilder_smooth(plus_dm, period) / np.where(atr_smooth > 0, atr_smooth, 1)
    minus_di = 100 * wilder_smooth(minus_dm, period) / np.where(atr_smooth > 0, atr_smooth, 1)

    dx = 100 * np.abs(plus_di - minus_di) / np.where((plus_di + minus_di) > 0, plus_di + minus_di, 1)
    adx_values = wilder_smooth(dx, period)

    return float(adx_values[-1])


def plus_di(klines: List[Kline], period: int = 14) -> float:
    """正趋向指标"""
    if len(klines) < period * 2:
        return 0.0
    highs = np.array([k.high for k in klines])
    lows = np.array([k.low for k in klines])
    closes = np.array([k.close for k in klines])

    tr = np.maximum(highs[1:] - lows[1:],
                    np.maximum(np.abs(highs[1:] - closes[:-1]),
                               np.abs(lows[1:] - closes[:-1])))
    up_move = highs[1:] - highs[:-1]
    down_move = lows[:-1] - lows[1:]
    pdm = np.where((up_move > down_move) & (up_move > 0), up_move, 0.0)

    atr_sum = np.mean(tr[-period:])
    pdm_sum = np.mean(pdm[-period:])
    if atr_sum == 0:
        return 0.0
    return float(100 * pdm_sum / atr_sum)


def minus_di(klines: List[Kline], period: int = 14) -> float:
    """负趋向指标"""
    if len(klines) < period * 2:
        return 0.0
    highs = np.array([k.high for k in klines])
    lows = np.array([k.low for k in klines])
    closes = np.array([k.close for k in klines])

    tr = np.maximum(highs[1:] - lows[1:],
                    np.maximum(np.abs(highs[1:] - closes[:-1]),
                               np.abs(lows[1:] - closes[:-1])))
    up_move = highs[1:] - highs[:-1]
    down_move = lows[:-1] - lows[1:]
    mdm = np.where((down_move > up_move) & (down_move > 0), down_move, 0.0)

    atr_sum = np.mean(tr[-period:])
    mdm_sum = np.mean(mdm[-period:])
    if atr_sum == 0:
        return 0.0
    return float(100 * mdm_sum / atr_sum)


def ema_alignment(klines: List[Kline],
                  fast: int = 20, mid: int = 50, slow: int = 200) -> str:
    """EMA 排列状态: 'bullish' / 'bearish' / 'mixed'"""
    if len(klines) < slow:
        return 'mixed'
    ema_f = ema(klines, fast)[-1]
    ema_m = ema(klines, mid)[-1]
    ema_s = ema(klines, slow)[-1]
    close = klines[-1].close

    if close > ema_f > ema_m > ema_s:
        return 'bullish'
    elif close < ema_f < ema_m < ema_s:
        return 'bearish'
    return 'mixed'


def macd(klines: List[Kline], fast: int = 12, slow: int = 26,
         signal: int = 9) -> tuple:
    """MACD: 返回 (macd_line, signal_line, histogram) 最新值"""
    ema_fast = ema(klines, fast)
    ema_slow = ema(klines, slow)
    macd_line = ema_fast - ema_slow

    # Signal line (EMA of MACD)
    multiplier = 2 / (signal + 1)
    sig = np.empty_like(macd_line)
    sig[0] = macd_line[0]
    for i in range(1, len(macd_line)):
        sig[i] = macd_line[i] * multiplier + sig[i - 1] * (1 - multiplier)

    hist = macd_line - sig
    return float(macd_line[-1]), float(sig[-1]), float(hist[-1])
