"""Technical indicator calculations using pandas/numpy"""
import numpy as np
import pandas as pd


def ema(series, period):
    return series.ewm(span=period, adjust=False).mean()


def sma(series, period):
    return series.rolling(window=period).mean()


def atr(df, period=14):
    high = df['high']
    low = df['low']
    close = df['close']
    prev_close = close.shift(1)
    tr = pd.concat([
        high - low,
        (high - prev_close).abs(),
        (low - prev_close).abs(),
    ], axis=1).max(axis=1)
    return tr.rolling(window=period).mean()


def atrp(df, period=14):
    """ATR as percentage of close"""
    a = atr(df, period)
    return (a / df['close']) * 100


def rsi(series, period=14):
    delta = series.diff()
    gain = delta.where(delta > 0, 0.0)
    loss = (-delta).where(delta < 0, 0.0)
    avg_gain = gain.ewm(alpha=1/period, min_periods=period).mean()
    avg_loss = loss.ewm(alpha=1/period, min_periods=period).mean()
    rs = avg_gain / avg_loss.replace(0, np.nan)
    return 100 - (100 / (1 + rs))


def adx(df, period=14):
    high = df['high']
    low = df['low']
    close = df['close']

    plus_dm = high.diff()
    minus_dm = -low.diff()
    plus_dm = plus_dm.where((plus_dm > minus_dm) & (plus_dm > 0), 0.0)
    minus_dm = minus_dm.where((minus_dm > plus_dm) & (minus_dm > 0), 0.0)

    a = atr(df, period)
    a = a.replace(0, np.nan)

    plus_di = 100 * ema(plus_dm, period) / a
    minus_di = 100 * ema(minus_dm, period) / a

    dx = 100 * (plus_di - minus_di).abs() / (plus_di + minus_di).replace(0, np.nan)
    adx_val = ema(dx, period)
    return adx_val, plus_di, minus_di


def bollinger_bands(series, period=20, std_dev=2):
    mid = sma(series, period)
    std = series.rolling(window=period).std()
    upper = mid + std_dev * std
    lower = mid - std_dev * std
    return upper, mid, lower


def roc(series, period=6):
    """Rate of change"""
    return (series / series.shift(period) - 1) * 100


def volume_ratio(df, period=20):
    """Current volume / average volume"""
    avg_vol = sma(df['volume'], period)
    return df['volume'] / avg_vol.replace(0, np.nan)


def ema_slope(series, period=3):
    """Slope of EMA over last N bars"""
    if len(series) < period + 1:
        return 0
    vals = series.iloc[-period:]
    return (vals.iloc[-1] - vals.iloc[0]) / vals.iloc[0] if vals.iloc[0] != 0 else 0


def recent_highs_lows(df, n=3):
    """Check if recent N bars have higher highs/lows or lower highs/lows"""
    if len(df) < n + 1:
        return 0
    highs = df['high'].iloc[-(n+1):].values
    lows = df['low'].iloc[-(n+1):].values

    hh = all(highs[i+1] > highs[i] for i in range(len(highs)-1))
    hl = all(lows[i+1] > lows[i] for i in range(len(lows)-1))
    lh = all(highs[i+1] < highs[i] for i in range(len(highs)-1))
    ll = all(lows[i+1] < lows[i] for i in range(len(lows)-1))

    if hh and hl:
        return 1   # uptrend
    elif lh and ll:
        return -1  # downtrend
    return 0


def shadow_ratio(candle_row):
    """Ratio of shadows to body. High ratio = wick-heavy = bad"""
    o, h, l, c = candle_row['open'], candle_row['high'], candle_row['low'], candle_row['close']
    body = abs(c - o)
    total = h - l
    if total == 0:
        return 0
    return 1 - (body / total)


def count_wicky_candles(df, n=12, threshold=0.65):
    """Count candles with high shadow ratio in last n bars"""
    if len(df) < n:
        return 0
    recent = df.iloc[-n:]
    count = 0
    for _, row in recent.iterrows():
        if shadow_ratio(row) > threshold:
            count += 1
    return count


def compression_range(df, n=20):
    """Width of price range over last n bars as percentage"""
    if len(df) < n:
        return float('inf')
    recent = df.iloc[-n:]
    h = recent['high'].max()
    l = recent['low'].min()
    mid = (h + l) / 2
    if mid == 0:
        return float('inf')
    return ((h - l) / mid) * 100
