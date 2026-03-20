#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
回测引擎 - Backtesting Engine
用历史K线数据模拟 paper_trader.py 的交易策略
纯函数，不依赖网络/数据库
"""

from datetime import datetime, timezone


def calculate_rsi(prices, period=14):
    """计算RSI"""
    if len(prices) < period:
        return 50

    deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
    gains = [d if d > 0 else 0 for d in deltas]
    losses = [-d if d < 0 else 0 for d in deltas]

    avg_gain = sum(gains[-period:]) / period
    avg_loss = sum(losses[-period:]) / period

    if avg_loss == 0:
        return 100

    rs = avg_gain / avg_loss
    rsi = 100 - (100 / (1 + rs))
    return rsi


def calculate_atr_from_candles(candles, period=14):
    """从K线数据计算ATR"""
    if len(candles) < period + 1:
        return None, None

    true_ranges = []
    for i in range(1, len(candles)):
        high = candles[i]['high']
        low = candles[i]['low']
        prev_close = candles[i-1]['close']
        tr = max(high - low, abs(high - prev_close), abs(low - prev_close))
        true_ranges.append(tr)

    atr = sum(true_ranges[-period:]) / period
    current_price = candles[-1]['close']
    atr_pct = (atr / current_price) * 100 if current_price > 0 else 0
    return atr, atr_pct


def get_dynamic_stop_pct(atr_pct, multiplier=2.0, range_min=0.03, range_max=0.08):
    """根据ATR获取动态止损百分比"""
    if atr_pct is None:
        return 0.02, 'unknown'

    stop_pct = max(range_min, min(range_max, atr_pct * multiplier / 100))

    if atr_pct > 3:
        volatility = 'high'
    elif atr_pct > 1.5:
        volatility = 'medium'
    else:
        volatility = 'low'

    return stop_pct, volatility




def calculate_macd(closes, fast=12, slow=26, signal_period=9):
    """计算MACD (v5)"""
    if len(closes) < slow + signal_period:
        return None

    def ema(data, period):
        multiplier = 2.0 / (period + 1)
        result = [data[0]]
        for i in range(1, len(data)):
            result.append(data[i] * multiplier + result[-1] * (1 - multiplier))
        return result

    ema_fast = ema(closes, fast)
    ema_slow = ema(closes, slow)

    macd_line = [f - s for f, s in zip(ema_fast, ema_slow)]
    signal_line = ema(macd_line[slow-1:], signal_period)  # Start after slow EMA stabilizes

    # Align lengths
    macd_val = macd_line[-1]
    signal_val = signal_line[-1]
    histogram = macd_val - signal_val
    prev_histogram = macd_line[-2] - signal_line[-2] if len(signal_line) >= 2 else 0

    return {
        'macd': macd_val,
        'signal': signal_val,
        'histogram': histogram,
        'prev_histogram': prev_histogram,
        'crossover_up': prev_histogram <= 0 and histogram > 0,
        'crossover_down': prev_histogram >= 0 and histogram < 0,
    }


def calculate_bollinger_bands(closes, period=20, std_dev=2.0):
    """计算布林带 (v5)"""
    if len(closes) < period:
        return None

    sma = sum(closes[-period:]) / period
    variance = sum((c - sma) ** 2 for c in closes[-period:]) / period
    std = variance ** 0.5

    upper = sma + std_dev * std
    lower = sma - std_dev * std
    bandwidth = (upper - lower) / sma * 100 if sma > 0 else 0
    price = closes[-1]
    percent_b = (price - lower) / (upper - lower) if upper > lower else 0.5

    return {
        'upper': upper,
        'middle': sma,
        'lower': lower,
        'bandwidth': bandwidth,
        'percent_b': percent_b,
        'price': price,
    }


def calculate_adx(candles, period=14):
    """计算ADX (v5) - Wilder's smoothing"""
    if len(candles) < period * 2 + 1:
        return None

    plus_dm = []
    minus_dm = []
    tr_list = []

    for i in range(1, len(candles)):
        high = candles[i]['high']
        low = candles[i]['low']
        prev_high = candles[i-1]['high']
        prev_low = candles[i-1]['low']
        prev_close = candles[i-1]['close']

        up_move = high - prev_high
        down_move = prev_low - low

        pdm = up_move if up_move > down_move and up_move > 0 else 0
        mdm = down_move if down_move > up_move and down_move > 0 else 0

        tr = max(high - low, abs(high - prev_close), abs(low - prev_close))

        plus_dm.append(pdm)
        minus_dm.append(mdm)
        tr_list.append(tr)

    if len(tr_list) < period:
        return None

    # Wilder's smoothing (first value is SMA, then EMA-like)
    def wilder_smooth(data, period):
        result = [sum(data[:period]) / period]
        for i in range(period, len(data)):
            result.append((result[-1] * (period - 1) + data[i]) / period)
        return result

    smooth_tr = wilder_smooth(tr_list, period)
    smooth_pdm = wilder_smooth(plus_dm, period)
    smooth_mdm = wilder_smooth(minus_dm, period)

    # Calculate +DI and -DI
    dx_list = []
    for i in range(len(smooth_tr)):
        if smooth_tr[i] == 0:
            continue
        plus_di = 100 * smooth_pdm[i] / smooth_tr[i]
        minus_di = 100 * smooth_mdm[i] / smooth_tr[i]
        di_sum = plus_di + minus_di
        if di_sum > 0:
            dx_list.append(100 * abs(plus_di - minus_di) / di_sum)

    if len(dx_list) < period:
        return None

    # ADX = smoothed DX
    adx = sum(dx_list[-period:]) / period
    return round(adx, 1)


def analyze_signal_v5(candles, config=None):
    """V5信号分析 — MACD+RSI+BB+ADX三重确认 (100分+奖励)

    Scoring:
    - MACD(12,26,9): 25pts
    - RSI(14): 20pts
    - BB(20,2): 20pts
    - ADX(14): 15pts
    - Volume: 10pts
    - BTC filter: 10pts (N/A in backtest, give 5pts base)
    - Triple Confirm bonus: +10pts
    """
    if len(candles) < 100:
        return 0, None

    closes = [c['close'] for c in candles]
    volumes = [c['volume'] for c in candles]
    current_price = closes[-1]

    config = config or {}
    adx_min = config.get('adx_min_threshold', 25)

    # --- ADX gate ---
    adx = calculate_adx(candles, 14)
    if adx is None or adx < adx_min:
        return 0, None  # Skip ranging market

    # --- Calculate all indicators ---
    rsi = calculate_rsi(closes, 14)
    macd = calculate_macd(closes, 12, 26, 9)
    bb = calculate_bollinger_bands(closes, 20, 2.0)
    atr, atr_pct = calculate_atr_from_candles(candles, 14)

    if macd is None or bb is None:
        return 0, None

    votes = {'LONG': 0, 'SHORT': 0}
    score = 0

    # 1. MACD (25pts)
    macd_score = 0
    if macd['crossover_up']:
        macd_score = 25
        votes['LONG'] += 2
    elif macd['crossover_down']:
        macd_score = 25
        votes['SHORT'] += 2
    elif macd['histogram'] > 0 and macd['histogram'] > macd['prev_histogram']:
        macd_score = 15
        votes['LONG'] += 1
    elif macd['histogram'] < 0 and macd['histogram'] < macd['prev_histogram']:
        macd_score = 15
        votes['SHORT'] += 1
    else:
        macd_score = 5
    score += macd_score

    # 2. RSI (20pts)
    rsi_score = 0
    if rsi < 25:
        rsi_score = 20
        votes['LONG'] += 2
    elif rsi > 75:
        rsi_score = 20
        votes['SHORT'] += 2
    elif rsi < 35:
        rsi_score = 15
        votes['LONG'] += 1
    elif rsi > 65:
        rsi_score = 15
        votes['SHORT'] += 1
    elif rsi < 45:
        rsi_score = 8
        votes['LONG'] += 1
    elif rsi > 55:
        rsi_score = 8
        votes['SHORT'] += 1
    else:
        rsi_score = 3
    score += rsi_score

    # 3. Bollinger Bands (20pts)
    bb_score = 0
    if bb['percent_b'] < 0.05:
        bb_score = 20
        votes['LONG'] += 2
    elif bb['percent_b'] > 0.95:
        bb_score = 20
        votes['SHORT'] += 2
    elif bb['percent_b'] < 0.2:
        bb_score = 15
        votes['LONG'] += 1
    elif bb['percent_b'] > 0.8:
        bb_score = 15
        votes['SHORT'] += 1
    elif bb['percent_b'] < 0.35:
        bb_score = 8
        votes['LONG'] += 1
    elif bb['percent_b'] > 0.65:
        bb_score = 8
        votes['SHORT'] += 1
    else:
        bb_score = 3
    score += bb_score

    # 4. ADX (15pts) - trend strength
    adx_score = 0
    if adx >= 40:
        adx_score = 15  # Strong trend
    elif adx >= 30:
        adx_score = 12
    elif adx >= 25:
        adx_score = 8
    else:
        adx_score = 3
    score += adx_score

    # 5. Volume (10pts)
    avg_volume = sum(volumes[-20:]) / 20 if len(volumes) >= 20 else 1
    recent_volume = volumes[-1]
    volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1
    if volume_ratio > 1.5:
        vol_score = 10
    elif volume_ratio > 1.2:
        vol_score = 7
    elif volume_ratio > 1:
        vol_score = 5
    else:
        vol_score = 2
    score += vol_score

    # 6. BTC filter (10pts) - N/A in single-coin backtest, give base 5pts
    score += 5

    # --- Direction ---
    if votes['LONG'] > votes['SHORT']:
        direction = 'LONG'
    elif votes['SHORT'] > votes['LONG']:
        direction = 'SHORT'
    else:
        direction = 'LONG' if rsi < 50 else 'SHORT'

    # --- Triple Confirmation Bonus (+10pts) ---
    macd_dir = 'LONG' if macd['histogram'] > 0 else 'SHORT'
    rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
    bb_dir = 'LONG' if bb['percent_b'] < 0.4 else ('SHORT' if bb['percent_b'] > 0.6 else None)
    if bb_dir and macd_dir == rsi_dir == bb_dir == direction:
        score += 10

    # SHORT bias
    short_bias = config.get('short_bias', 1.05)
    if direction == 'SHORT' and short_bias != 1.0:
        score = int(score * short_bias)

    analysis = {
        'price': current_price,
        'direction': direction,
        'score': score,
        'rsi': rsi,
        'macd_histogram': macd['histogram'],
        'bb_percent_b': bb['percent_b'],
        'adx': adx,
        'atr': atr,
        'atr_pct': atr_pct,
        'volume_ratio': volume_ratio,
    }

    return score, analysis


def calculate_position_size_v5(score, available, config=None, atr=None, price=None):
    """V5仓位计算 - 固定10x，ATR风险控制

    Args:
        score: 信号评分
        available: 可用资金
        config: 策略配置
        atr: ATR值
        price: 当前价格

    Returns:
        (amount, leverage)
    """
    config = config or {}
    leverage = config.get('max_leverage', 10)
    max_size = config.get('max_position_size', 150)

    # ATR-based sizing: risk 1% of capital per trade
    if atr and price and atr > 0:
        risk_per_trade = available * 0.01
        stop_distance = atr * 1.5  # 1.5x ATR stop
        stop_pct = stop_distance / price
        # amount = risk / (stop_pct * leverage)
        amount = risk_per_trade / (stop_pct * leverage) if stop_pct > 0 else 0
        amount = min(amount, max_size, available * 0.3)
    else:
        # Fallback: score-based sizing
        if score >= 90:
            amount = min(max_size, available * 0.25)
        elif score >= 80:
            amount = min(max_size * 0.8, available * 0.2)
        elif score >= 75:
            amount = min(max_size * 0.6, available * 0.15)
        else:
            amount = min(max_size * 0.4, available * 0.1)

    return max(0, amount), leverage



def analyze_signal_v6(candles, config=None):
    """V6信号分析 - v5三重确认(MACD+RSI+BB+ADX) + v4.2 MA趋势过滤
    评分体系(总分110): MACD25 + RSI20 + BB20 + ADX15 + MA10 + Vol10 + Triple10
    ADX gate: 20 (比v5的25宽松，适应3x杠杆)
    """
    if len(candles) < 100:
        return 0, None

    closes = [c['close'] for c in candles]
    volumes = [c['volume'] for c in candles]
    current_price = closes[-1]

    config = config or {}
    adx_min = config.get('adx_min_threshold', 20)

    adx = calculate_adx(candles, 14)
    if adx is None or adx < adx_min:
        return 0, None

    rsi = calculate_rsi(closes, 14)
    macd = calculate_macd(closes, 12, 26, 9)
    bb = calculate_bollinger_bands(closes, 20, 2.0)
    atr, atr_pct = calculate_atr_from_candles(candles, 14)

    if macd is None or bb is None:
        return 0, None

    votes = {'LONG': 0, 'SHORT': 0}
    score = 0

    # 1. MACD (25pts)
    if macd['crossover_up']:
        score += 25; votes['LONG'] += 2
    elif macd['crossover_down']:
        score += 25; votes['SHORT'] += 2
    elif macd['histogram'] > 0 and macd['histogram'] > macd['prev_histogram']:
        score += 15; votes['LONG'] += 1
    elif macd['histogram'] < 0 and macd['histogram'] < macd['prev_histogram']:
        score += 15; votes['SHORT'] += 1
    else:
        score += 5

    # 2. RSI (20pts)
    if rsi < 25:
        score += 20; votes['LONG'] += 2
    elif rsi > 75:
        score += 20; votes['SHORT'] += 2
    elif rsi < 35:
        score += 15; votes['LONG'] += 1
    elif rsi > 65:
        score += 15; votes['SHORT'] += 1
    elif rsi < 45:
        score += 8; votes['LONG'] += 1
    elif rsi > 55:
        score += 8; votes['SHORT'] += 1
    else:
        score += 3

    # 3. BB (20pts)
    if bb['percent_b'] < 0.05:
        score += 20; votes['LONG'] += 2
    elif bb['percent_b'] > 0.95:
        score += 20; votes['SHORT'] += 2
    elif bb['percent_b'] < 0.2:
        score += 15; votes['LONG'] += 1
    elif bb['percent_b'] > 0.8:
        score += 15; votes['SHORT'] += 1
    elif bb['percent_b'] < 0.35:
        score += 8; votes['LONG'] += 1
    elif bb['percent_b'] > 0.65:
        score += 8; votes['SHORT'] += 1
    else:
        score += 3

    # 4. ADX (15pts)
    if adx >= 40:
        score += 15
    elif adx >= 30:
        score += 12
    elif adx >= 25:
        score += 8
    elif adx >= 20:
        score += 5
    else:
        score += 3

    # 5. MA趋势 (10pts) - 新增
    ma7 = sum(closes[-7:]) / 7
    ma20 = sum(closes[-20:]) / 20
    ma50 = sum(closes[-50:]) / 50 if len(closes) >= 50 else ma20

    if current_price > ma7 > ma20 > ma50:
        score += 10; votes['LONG'] += 1
    elif current_price < ma7 < ma20 < ma50:
        score += 10; votes['SHORT'] += 1
    elif current_price > ma7 > ma20:
        score += 6; votes['LONG'] += 1
    elif current_price < ma7 < ma20:
        score += 6; votes['SHORT'] += 1
    else:
        score += 2

    # 6. Volume (10pts)
    avg_volume = sum(volumes[-20:]) / 20 if len(volumes) >= 20 else 1
    recent_volume = volumes[-1]
    volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1
    if volume_ratio > 1.5:
        score += 10
    elif volume_ratio > 1.2:
        score += 7
    elif volume_ratio > 1:
        score += 5
    else:
        score += 2

    # Direction
    if votes['LONG'] > votes['SHORT']:
        direction = 'LONG'
    elif votes['SHORT'] > votes['LONG']:
        direction = 'SHORT'
    else:
        direction = 'LONG' if rsi < 50 else 'SHORT'

    # Triple Confirmation Bonus (+10pts)
    macd_dir = 'LONG' if macd['histogram'] > 0 else 'SHORT'
    rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
    bb_dir = 'LONG' if bb['percent_b'] < 0.4 else ('SHORT' if bb['percent_b'] > 0.6 else None)
    if bb_dir and macd_dir == rsi_dir == bb_dir == direction:
        score += 10

    # RSI/MA conflict penalty (x0.85)
    trend_dir = 'LONG' if current_price > ma20 else 'SHORT'
    if rsi_dir != trend_dir:
        score = int(score * 0.85)

    # SHORT bias
    short_bias = config.get('short_bias', 1.05)
    if direction == 'SHORT' and short_bias != 1.0:
        score = int(score * short_bias)

    analysis = {
        'price': current_price,
        'direction': direction,
        'score': score,
        'rsi': rsi,
        'macd_histogram': macd['histogram'],
        'bb_percent_b': bb['percent_b'],
        'adx': adx,
        'atr': atr,
        'atr_pct': atr_pct,
        'volume_ratio': volume_ratio,
        'ma7': ma7, 'ma20': ma20, 'ma50': ma50,
    }

    return score, analysis


def analyze_signal_v6b(candles, config=None):
    """V6b: v4.2评分为基础 + MACD/BB/ADX额外加分
    基础分 = RSI(30) + MA趋势(30) + Volume(20) + PricePos(20) = 100
    加分 = MACD确认(+10) + ADX强趋势(+8) + BB极端(+7) = +25
    总分上限 = 125, min_score 60
    """
    if len(candles) < 100:
        return 0, None

    closes = [c['close'] for c in candles]
    volumes = [c['volume'] for c in candles]
    highs = [c['high'] for c in candles]
    lows = [c['low'] for c in candles]
    current_price = closes[-1]
    config = config or {}

    votes = {"LONG": 0, "SHORT": 0}

    # === 1. RSI (30pts) — 同v4 ===
    rsi = calculate_rsi(closes, 14)
    if rsi < 30:
        rsi_score = 30; votes["LONG"] += 1
    elif rsi > 70:
        rsi_score = 30; votes["SHORT"] += 1
    elif rsi < 45:
        rsi_score = 15; votes["LONG"] += 1
    elif rsi > 55:
        rsi_score = 15; votes["SHORT"] += 1
    else:
        rsi_score = 5

    # === 2. MA趋势 (30pts) — 同v4 ===
    ma7 = sum(closes[-7:]) / 7
    ma20 = sum(closes[-20:]) / 20
    ma50 = sum(closes[-50:]) / 50 if len(closes) >= 50 else ma20

    if current_price > ma7 > ma20 > ma50:
        trend_score = 30; votes["LONG"] += 2
    elif current_price < ma7 < ma20 < ma50:
        trend_score = 30; votes["SHORT"] += 2
    elif current_price > ma7 > ma20:
        trend_score = 15; votes["LONG"] += 1
    elif current_price < ma7 < ma20:
        trend_score = 15; votes["SHORT"] += 1
    else:
        trend_score = 5

    # === 3. Volume (20pts) — 同v4 ===
    avg_volume = sum(volumes[-20:]) / 20 if len(volumes) >= 20 else 1
    recent_volume = volumes[-1]
    volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1
    if volume_ratio > 1.5:
        volume_score = 20
    elif volume_ratio > 1.2:
        volume_score = 15
    elif volume_ratio > 1:
        volume_score = 10
    else:
        volume_score = 5

    # === 4. Price Position (20pts) — 同v4 ===
    high_50 = max(highs[-50:]) if len(highs) >= 50 else max(highs)
    low_50 = min(lows[-50:]) if len(lows) >= 50 else min(lows)
    price_position = (current_price - low_50) / (high_50 - low_50) if high_50 > low_50 else 0.5

    if price_position < 0.2:
        position_score = 20; votes["LONG"] += 1
    elif price_position > 0.8:
        position_score = 20; votes["SHORT"] += 1
    elif price_position < 0.35:
        position_score = 10; votes["LONG"] += 1
    elif price_position > 0.65:
        position_score = 10; votes["SHORT"] += 1
    else:
        position_score = 5

    total_score = rsi_score + trend_score + volume_score + position_score

    # === Direction ===
    if votes["LONG"] > votes["SHORT"]:
        direction = "LONG"
    elif votes["SHORT"] > votes["LONG"]:
        direction = "SHORT"
    else:
        direction = "LONG" if rsi < 50 else "SHORT"

    # === RSI/趋势冲突惩罚 (同v4) ===
    rsi_dir = "LONG" if rsi < 50 else "SHORT"
    trend_dir = "LONG" if current_price > ma20 else "SHORT"
    if rsi_dir != trend_dir:
        total_score = int(total_score * 0.85)

    # === v5 指标加分 (新增部分) ===
    macd = calculate_macd(closes, 12, 26, 9)
    bb = calculate_bollinger_bands(closes, 20, 2.0)
    adx = calculate_adx(candles, 14)

    # MACD确认 (+10)
    if macd:
        macd_dir = "LONG" if macd["histogram"] > 0 else "SHORT"
        if macd_dir == direction:
            if macd["crossover_up"] or macd["crossover_down"]:
                total_score += 10  # 交叉+方向一致
            elif (macd["histogram"] > 0 and macd["histogram"] > macd["prev_histogram"]) or                  (macd["histogram"] < 0 and macd["histogram"] < macd["prev_histogram"]):
                total_score += 6   # 动量增强+方向一致
            else:
                total_score += 3   # 方向一致

    # ADX强趋势 (+8)
    if adx is not None:
        if adx >= 35:
            total_score += 8
        elif adx >= 25:
            total_score += 5
        elif adx >= 20:
            total_score += 2

    # BB极端位置 (+7)
    if bb:
        if (direction == "LONG" and bb["percent_b"] < 0.1) or            (direction == "SHORT" and bb["percent_b"] > 0.9):
            total_score += 7
        elif (direction == "LONG" and bb["percent_b"] < 0.25) or              (direction == "SHORT" and bb["percent_b"] > 0.75):
            total_score += 4

    # SHORT bias
    short_bias = config.get("short_bias", 1.05)
    if direction == "SHORT" and short_bias != 1.0:
        total_score = int(total_score * short_bias)

    atr, atr_pct = calculate_atr_from_candles(candles, 14)

    analysis = {
        "price": current_price,
        "rsi": rsi,
        "ma7": ma7, "ma20": ma20, "ma50": ma50,
        "volume_ratio": volume_ratio,
        "price_position": price_position,
        "direction": direction,
        "score": total_score,
        "adx": adx,
        "macd_histogram": macd["histogram"] if macd else None,
        "bb_percent_b": bb["percent_b"] if bb else None,
        "atr": atr,
        "atr_pct": atr_pct,
    }

    return total_score, analysis


def analyze_signal(candles):
    """分析交易信号（0-100分），从K线数据"""
    if len(candles) < 50:
        return 0, None

    closes = [c['close'] for c in candles]
    volumes = [c['volume'] for c in candles]
    highs = [c['high'] for c in candles]
    lows = [c['low'] for c in candles]

    current_price = closes[-1]

    votes = {'LONG': 0, 'SHORT': 0}

    # 1. RSI (30分)
    rsi = calculate_rsi(closes)
    if rsi < 30:
        rsi_score = 30
        votes['LONG'] += 1
    elif rsi > 70:
        rsi_score = 30
        votes['SHORT'] += 1
    elif rsi < 45:
        rsi_score = 15
        votes['LONG'] += 1
    elif rsi > 55:
        rsi_score = 15
        votes['SHORT'] += 1
    else:
        rsi_score = 5

    # 2. 趋势 (30分)
    ma7 = sum(closes[-7:]) / 7
    ma20 = sum(closes[-20:]) / 20
    ma50 = sum(closes[-50:]) / 50

    if current_price > ma7 > ma20 > ma50:
        trend_score = 30
        votes['LONG'] += 2
    elif current_price < ma7 < ma20 < ma50:
        trend_score = 30
        votes['SHORT'] += 2
    elif current_price > ma7 > ma20:
        trend_score = 15
        votes['LONG'] += 1
    elif current_price < ma7 < ma20:
        trend_score = 15
        votes['SHORT'] += 1
    else:
        trend_score = 5

    # 3. 成交量 (20分)
    avg_volume = sum(volumes[-20:]) / 20
    recent_volume = volumes[-1]
    volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1

    if volume_ratio > 1.5:
        volume_score = 20
    elif volume_ratio > 1.2:
        volume_score = 15
    elif volume_ratio > 1:
        volume_score = 10
    else:
        volume_score = 5

    # 4. 价格位置 (20分)
    high_50 = max(highs[-50:])
    low_50 = min(lows[-50:])
    price_position = (current_price - low_50) / (high_50 - low_50) if high_50 > low_50 else 0.5

    if price_position < 0.2:
        position_score = 20
        votes['LONG'] += 1
    elif price_position > 0.8:
        position_score = 20
        votes['SHORT'] += 1
    elif price_position < 0.35:
        position_score = 10
        votes['LONG'] += 1
    elif price_position > 0.65:
        position_score = 10
        votes['SHORT'] += 1
    else:
        position_score = 5

    # 方向投票
    if votes['LONG'] > votes['SHORT']:
        direction = 'LONG'
    elif votes['SHORT'] > votes['LONG']:
        direction = 'SHORT'
    else:
        direction = 'LONG' if rsi < 50 else 'SHORT'

    total_score = rsi_score + trend_score + volume_score + position_score

    # RSI/趋势冲突惩罚
    rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
    trend_dir = 'LONG' if current_price > ma20 else 'SHORT'
    if rsi_dir != trend_dir:
        total_score = int(total_score * 0.85)

    analysis = {
        'price': current_price,
        'rsi': rsi,
        'ma7': ma7,
        'ma20': ma20,
        'ma50': ma50,
        'volume_ratio': volume_ratio,
        'price_position': price_position,
        'direction': direction,
        'score': total_score
    }

    return total_score, analysis


def calculate_position_size(score, available, max_leverage=5, high_score_leverage=None, dynamic_leverage=False):
    """根据信号强度计算仓位大小和杠杆
    high_score_leverage: 85+评分专用杠杆上限(None则用max_leverage)
    dynamic_leverage: v4.3动态杠杆模式
    """
    if dynamic_leverage:
        # v4.3.1 动态杠杆：评分越高杠杆越大 (3-10x)
        if score >= 85:
            size = min(150, available * 0.08)
            leverage = min(10, max_leverage)
        elif score >= 80:
            size = min(250, available * 0.15)
            leverage = min(7, max_leverage)
        elif score >= 75:
            size = min(350, available * 0.22)
            leverage = min(5, max_leverage)
        elif score >= 70:
            size = min(250, available * 0.15)
            leverage = min(4, max_leverage)
        elif score >= 60:
            size = min(150, available * 0.1)
            leverage = min(3, max_leverage)
        else:
            return 0, 3
    else:
        # 旧版逻辑
        hs_lev = high_score_leverage if high_score_leverage else max_leverage
        if score >= 85:
            size = min(400, available * 0.25)
            leverage = min(5, hs_lev)
        elif score >= 75:
            size = min(300, available * 0.2)
            leverage = min(3, max_leverage)
        elif score >= 70:
            size = min(200, available * 0.15)
            leverage = min(3, max_leverage)
        elif score >= 60:
            size = min(150, available * 0.1)
            leverage = min(3, max_leverage)
        elif score >= 55:
            size = min(100, available * 0.08)
            leverage = min(3, max_leverage)
        else:
            return 0, max_leverage

    return size, leverage


def calculate_dynamic_tpsl(score, trend_strength, direction_matches_trend, fixed_tp_mode=False):
    """动态止盈止损

    Args:
        score: 信号评分 (60-100)
        trend_strength: 趋势强度 (0-3, 0=无趋势, 3=强趋势)
        direction_matches_trend: 方向是否顺势 (True/False)
        fixed_tp_mode: True=固定止盈(v4.3.1), False=移动止盈(v4.3)

    Returns:
        (roi_stop_loss, roi_take_profit, roi_trailing_start, roi_trailing_distance)
    """
    if fixed_tp_mode:
        # v4.3.1 固定止盈目标
        if direction_matches_trend and trend_strength >= 2:
            return -8, 12, 0, 0  # 强趋势顺势 1.5:1
        elif direction_matches_trend and trend_strength >= 1:
            return -8, 10, 0, 0  # 普通顺势 1.25:1
        elif not direction_matches_trend:
            return -5, 6, 0, 0   # 逆势 1.2:1
        else:
            return -6, 8, 0, 0   # 震荡/弱势 1.33:1
    else:
        # v4.3 移动止盈
        if direction_matches_trend and trend_strength >= 2:
            return -8, 0, 8, 4   # 强趋势顺势：让利润跑
        elif direction_matches_trend and trend_strength >= 1:
            return -8, 0, 6, 3   # 普通顺势
        elif not direction_matches_trend:
            return -5, 0, 4, 2   # 逆势：保守快出
        else:
            return -6, 0, 5, 3   # 震荡/弱势


def calculate_dynamic_leverage_v431(score, max_lev=15):
    """动态杠杆 — v4.3.1 激进版"""
    if score >= 85:
        return min(15, max_lev)  # 高分15x
    elif score >= 75:
        return min(12, max_lev)  # 中高12x
    elif score >= 65:
        return min(8, max_lev)   # 标准8x
    else:
        return min(5, max_lev)   # 低分5x


def run_backtest(candles_1h, config):
    """
    主回测函数 - 逐K线模拟交易

    candles_1h: list of dicts {time, open, high, low, close, volume}
    config: {initial_capital, min_score, fee_rate, max_positions, max_same_direction}

    returns: {trades, equity_curve, summary}
    """
    initial_capital = config.get('initial_capital', 2000)
    min_score = config.get('min_score', 70)
    fee_rate = config.get('fee_rate', 0.0005)
    max_positions = config.get('max_positions', 3)
    max_same_dir = config.get('max_same_direction', 2)
    cooldown_bars = config.get('cooldown', 12)
    max_leverage = config.get('max_leverage', 5)
    enable_trend_filter = config.get('enable_trend_filter', True)
    ma_slope_threshold = config.get('ma_slope_threshold', 0.01)
    long_min_score = config.get('long_min_score', min_score)  # LONG方向最低评分(v4.1)
    long_ma_slope_threshold = config.get('long_ma_slope_threshold', ma_slope_threshold)  # LONG方向MA斜率阈值(v4.1)
    high_score_leverage = config.get('high_score_leverage', None)  # 85+评分专用杠杆(v4.2)
    dynamic_leverage = config.get('dynamic_leverage', False)  # v4.3 动态杠杆
    dynamic_leverage_v431 = config.get('dynamic_leverage_v431', False)  # v4.3.1 激进杠杆
    dynamic_tpsl = config.get('dynamic_tpsl', False)  # v4.3 动态止盈止损(移动止盈)
    fixed_tp_mode = config.get('fixed_tp_mode', False)  # v4.3.1 固定止盈模式
    leverage_based_tpsl = config.get('leverage_based_tpsl', False)  # v4.3.1 杠杆联动止盈止损
    # V5/V8 模式
    v5_mode = config.get('v5_mode', False)
    v8_mode = config.get('v8_mode', False)
    tp1_roi = config.get('tp1_roi', 10)         # TP1: +10% ROI
    tp1_close_ratio = config.get('tp1_close_ratio', 0.5)  # TP1 平50%仓位
    tp2_roi = config.get('tp2_roi', 20)          # TP2: +20% ROI 开始尾随
    tp2_trail_dist = config.get('tp2_trail_distance', 5)   # TP2 尾随距离 5%
    adx_min_threshold = config.get('adx_min_threshold', 25)
    # ROI模式参数（基于本金盈亏%）— 作为默认值，dynamic_tpsl开启时会被覆盖
    roi_stop_loss = config.get('roi_stop_loss', -8)        # 止损: ROI跌到-8%平仓
    roi_take_profit = config.get('roi_take_profit', 0)     # 固定止盈目标，0表示用移动止盈
    roi_trailing_start = config.get('roi_trailing_start', 5)  # 启动移动止盈: ROI达+5%
    roi_trailing_distance = config.get('roi_trailing_distance', 3)  # 回撤距离: 从峰值回撤3%平仓

    capital = initial_capital
    positions = {}  # symbol -> position dict
    trades = []
    equity_curve = []
    cooldown_until = -1  # bar index until which we wait
    trade_id = 0
    bankrupt = False

    # 每100根K线记录一次资金曲线（避免数据点太多）
    curve_interval = max(1, len(candles_1h) // 500)

    for i in range(100, len(candles_1h)):
        candle = candles_1h[i]

        if capital <= 0:
            bankrupt = True
            break

        # === V5/V8 TP1 部分止盈检查 ===
        if v5_mode or v8_mode:
            tp1_closes = []
            for sym, pos in positions.items():
                if not pos.get('tp1_hit', False) and pos.get('v5_mode', False):
                    lev = pos['leverage']
                    ep = pos['entry_price']
                    tp1 = pos.get('tp1_roi', tp1_roi)
                    if pos['direction'] == 'LONG':
                        best_roi = ((candle['high'] - ep) / ep) * lev * 100
                    else:
                        best_roi = ((ep - candle['low']) / ep) * lev * 100
                    if best_roi >= tp1:
                        tp1_closes.append(sym)
            for sym in tp1_closes:
                pos = positions[sym]
                ep = pos['entry_price']
                lev = pos['leverage']
                ratio = pos.get('tp1_close_ratio', 0.5)
                tp1_val = pos.get('tp1_roi', tp1_roi)
                # Calculate TP1 exit price
                tp1_pct = tp1_val / (lev * 100)
                if pos['direction'] == 'LONG':
                    exit_price = ep * (1 + tp1_pct)
                else:
                    exit_price = ep * (1 - tp1_pct)
                # Close partial (ratio of amount)
                close_amount = pos['amount'] * ratio
                pnl, fee, funding = _calc_pnl(
                    {**pos, 'amount': close_amount}, exit_price, fee_rate, i - pos['bar_index'])
                capital += pnl
                trade_id += 1
                trades.append({
                    'trade_id': trade_id,
                    'direction': pos['direction'],
                    'entry_price': ep,
                    'exit_price': exit_price,
                    'amount': close_amount,
                    'leverage': lev,
                    'pnl': round(pnl, 2),
                    'roi': round(tp1_val, 1),
                    'fee': round(fee, 2),
                    'funding_fee': round(funding, 2),
                    'reason': f'V5 TP1 止盈 (+{tp1_val}% 平{int(ratio*100)}%)',
                    'entry_time': pos['entry_time'],
                    'exit_time': _ts_to_str(candle['time']),
                    'score': pos['score'],
                    'stop_moves': 0,
                    'stop_loss': round(pos['stop_loss'], 6),
                    'tp_trigger': round(exit_price, 6),
                })
                # Update remaining position
                pos['amount'] = pos['amount'] * (1 - ratio)
                pos['tp1_hit'] = True
                # After TP1, use TP2 trailing params
                pos['roi_trailing_start'] = pos.get('roi_trailing_start', tp2_roi)
                pos['roi_trailing_distance'] = pos.get('roi_trailing_distance', tp2_trail_dist)

        # === 检查所有持仓 ===
        symbols_to_close = []
        for sym, pos in positions.items():
            close_price, reason = _check_position_bar(pos, candle)
            if close_price is not None:
                symbols_to_close.append((sym, close_price, reason))

        for sym, close_price, reason in symbols_to_close:
            pos = positions[sym]
            pnl, fee, funding = _calc_pnl(pos, close_price, fee_rate, i - pos['bar_index'])
            capital += pnl
            trade_id += 1
            # 计算止盈触发价(移动止盈启动价)
            ep = pos['entry_price']
            lev = pos['leverage']
            ts = pos.get('roi_trailing_start', roi_trailing_start)
            tp_pct = ts / (lev * 100)
            if pos['direction'] == 'LONG':
                tp_trigger = ep * (1 + tp_pct)
            else:
                tp_trigger = ep * (1 - tp_pct)
            trades.append({
                'trade_id': trade_id,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': close_price,
                'amount': pos['amount'],
                'leverage': pos['leverage'],
                'pnl': round(pnl, 2),
                'roi': round((pnl / pos['amount']) * 100, 1) if pos['amount'] > 0 else 0,
                'fee': round(fee, 2),
                'funding_fee': round(funding, 2),
                'reason': reason,
                'entry_time': pos['entry_time'],
                'exit_time': _ts_to_str(candle['time']),
                'score': pos['score'],
                'stop_moves': pos.get('stop_move_count', 0),
                'stop_loss': round(pos['stop_loss'], 6),
                'tp_trigger': round(tp_trigger, 6)
            })
            del positions[sym]
            cooldown_until = i + cooldown_bars

        # === 记录资金曲线 ===
        if i % curve_interval == 0:
            # 计算浮动盈亏
            floating_pnl = 0
            for sym, pos in positions.items():
                fp, _, _ = _calc_pnl(pos, candle['close'], fee_rate, i - pos['bar_index'])
                floating_pnl += fp
            equity_curve.append({
                'time': candle['time'],
                'capital': round(capital + floating_pnl, 2)
            })

        # === 开仓逻辑 ===
        if i <= cooldown_until:
            continue
        if len(positions) >= max_positions:
            continue
        if capital <= 50:
            continue

        # 信号分析
        lookback = candles_1h[max(0, i-99):i+1]

        v6_mode = config.get('v6_mode', False)
        v6b_mode = config.get('v6b_mode', False)
        if v8_mode:
            score, analysis = analyze_signal_v8(lookback, config)
        elif v6b_mode:
            score, analysis = analyze_signal_v6b(lookback, config)
        elif v6_mode:
            score, analysis = analyze_signal_v6(lookback, config)
        elif v5_mode:
            # V5: MACD+RSI+BB+ADX 三重确认
            score, analysis = analyze_signal_v5(lookback, config)
        else:
            score, analysis = analyze_signal(lookback)

        if score < min_score or analysis is None:
            continue

        direction = analysis['direction']

        # v4.1: LONG方向使用更高的最低评分
        if direction == 'LONG' and score < long_min_score:
            continue

        if not v5_mode:
            # 趋势过滤：MA20斜率与方向冲突时跳过 (v4 only, v5 uses ADX)
            if enable_trend_filter and len(lookback) >= 25:
                ma20_now = sum(c['close'] for c in lookback[-20:]) / 20
                ma20_prev = sum(c['close'] for c in lookback[-25:-5]) / 20
                ma20_slope = (ma20_now - ma20_prev) / ma20_prev
                long_threshold = long_ma_slope_threshold
                if direction == 'LONG' and ma20_slope < -long_threshold:
                    continue
                if direction == 'SHORT' and ma20_slope > ma_slope_threshold:
                    continue

        # 方向限制
        long_count = sum(1 for p in positions.values() if p['direction'] == 'LONG')
        short_count = sum(1 for p in positions.values() if p['direction'] == 'SHORT')
        if direction == 'LONG' and long_count >= max_same_dir:
            continue
        if direction == 'SHORT' and short_count >= max_same_dir:
            continue

        # 计算仓位
        used = sum(p['amount'] for p in positions.values())
        available = capital - used
        if available < 50:
            continue

        if v8_mode or v5_mode:
            atr_val = analysis.get('atr')
            amount, leverage = calculate_position_size_v5(score, available, config, atr_val, analysis['price'])
        else:
            amount, leverage = calculate_position_size(score, available, max_leverage, high_score_leverage, dynamic_leverage)

            # v4.3.1 激进杠杆覆盖
            if dynamic_leverage_v431:
                leverage = calculate_dynamic_leverage_v431(score, max_leverage)

        if amount < 50:
            continue

        entry_price = analysis['price']

        # v4.3.1 杠杆联动止盈止损: 杠杆越高越紧
        if leverage_based_tpsl:
            if leverage >= 10:
                pos_roi_stop = -5;  pos_roi_trail_start = 6;  pos_roi_trail_dist = 2; pos_roi_tp = 0
            elif leverage >= 7:
                pos_roi_stop = -6;  pos_roi_trail_start = 7;  pos_roi_trail_dist = 2; pos_roi_tp = 0
            elif leverage >= 5:
                pos_roi_stop = -8;  pos_roi_trail_start = 8;  pos_roi_trail_dist = 3; pos_roi_tp = 0
            elif leverage >= 4:
                pos_roi_stop = -9;  pos_roi_trail_start = 9;  pos_roi_trail_dist = 3; pos_roi_tp = 0
            else:
                pos_roi_stop = -10; pos_roi_trail_start = 10; pos_roi_trail_dist = 3; pos_roi_tp = 0
        elif dynamic_tpsl or fixed_tp_mode:
            # v4.3 动态止盈止损
            trend_strength = analysis.get('trend_strength', 1)
            ma_trend = analysis.get('ma_trend', 'neutral')
            direction_matches_trend = (
                (direction == 'LONG' and ma_trend == 'up') or
                (direction == 'SHORT' and ma_trend == 'down')
            )
            pos_roi_stop, pos_roi_tp, pos_roi_trail_start, pos_roi_trail_dist = calculate_dynamic_tpsl(
                score, trend_strength, direction_matches_trend, fixed_tp_mode
            )
        else:
            pos_roi_stop = roi_stop_loss
            pos_roi_tp = roi_take_profit
            pos_roi_trail_start = roi_trailing_start
            pos_roi_trail_dist = roi_trailing_distance

        # ROI模式：按ROI反算止损价格（用于显示/记录）
        stop_price_pct = pos_roi_stop / (leverage * 100)
        if direction == 'LONG':
            stop_loss = entry_price * (1 + stop_price_pct)  # 负值所以是减
        else:
            stop_loss = entry_price * (1 - stop_price_pct)  # 负值所以是加

        # V5/V8 mode: use ATR-based stop and dual TP
        if v5_mode or v8_mode:
            atr_val = analysis.get('atr')
            if atr_val and atr_val > 0:
                atr_stop_pct = (atr_val * 1.5 / entry_price) * leverage * 100
                pos_roi_stop = -min(atr_stop_pct, 10)  # Cap at -10%
            else:
                pos_roi_stop = roi_stop_loss
            pos_roi_tp = 0  # Use trailing mode
            pos_roi_trail_start = tp2_roi  # TP2 trailing start at +20%
            pos_roi_trail_dist = tp2_trail_dist  # 5% distance

        # 用 bar index 作为 key（单币种回测）
        pos_key = f"pos_{trade_id + 1}"
        positions[pos_key] = {
            'direction': direction,
            'entry_price': entry_price,
            'amount': amount,
            'leverage': leverage,
            'stop_loss': stop_loss,
            'roi_stop_loss': pos_roi_stop,
            'roi_take_profit': pos_roi_tp,  # 固定止盈目标，0表示用移动止盈
            'roi_trailing_start': pos_roi_trail_start,
            'roi_trailing_distance': pos_roi_trail_dist,
            'peak_roi': 0,
            'entry_time': _ts_to_str(candle['time']),
            'score': score,
            'stop_move_count': 0,
            'bar_index': i,
            'v5_mode': v5_mode or v8_mode,
            'tp1_hit': False,
            'tp1_roi': tp1_roi,
            'tp1_close_ratio': tp1_close_ratio,
            'original_amount': amount,
        }

    # === 强制平仓剩余持仓 ===
    if candles_1h and positions:
        last_candle = candles_1h[-1]
        for sym, pos in list(positions.items()):
            pnl, fee, funding = _calc_pnl(pos, last_candle['close'], fee_rate,
                                           len(candles_1h) - 1 - pos['bar_index'])
            capital += pnl
            trade_id += 1
            ep = pos['entry_price']
            lev = pos['leverage']
            ts = pos.get('roi_trailing_start', roi_trailing_start)
            tp_pct = ts / (lev * 100)
            if pos['direction'] == 'LONG':
                tp_trigger = ep * (1 + tp_pct)
            else:
                tp_trigger = ep * (1 - tp_pct)
            trades.append({
                'trade_id': trade_id,
                'direction': pos['direction'],
                'entry_price': ep,
                'exit_price': last_candle['close'],
                'amount': pos['amount'],
                'leverage': pos['leverage'],
                'pnl': round(pnl, 2),
                'roi': round((pnl / pos['amount']) * 100, 1) if pos['amount'] > 0 else 0,
                'fee': round(fee, 2),
                'funding_fee': round(funding, 2),
                'reason': '回测结束强制平仓',
                'entry_time': pos['entry_time'],
                'exit_time': _ts_to_str(last_candle['time']),
                'score': pos['score'],
                'stop_moves': pos.get('stop_move_count', 0),
                'stop_loss': round(pos['stop_loss'], 6),
                'tp_trigger': round(tp_trigger, 6)
            })

    # 最终资金曲线点
    if candles_1h:
        equity_curve.append({
            'time': candles_1h[-1]['time'],
            'capital': round(capital, 2)
        })

    # === 统计 ===
    summary = _compute_summary(trades, initial_capital, capital, equity_curve, bankrupt)

    return {
        'trades': trades,
        'equity_curve': equity_curve,
        'summary': summary
    }


def _check_position_bar(pos, candle):
    """用一根K线的高低价检查持仓 — 支持固定止盈和移动止盈两种模式

    固定目标模式 (roi_take_profit > 0):
    - 止损: ROI跌到 roi_stop_loss 平仓
    - 止盈: ROI达到 roi_take_profit 平仓

    移动止盈模式 (roi_take_profit = 0 或未设置):
    - 止损: ROI跌到 roi_stop_loss 平仓
    - 移动止盈: ROI达到 roi_trailing_start 后开始跟踪
    - 跟踪距离: 从最高ROI回撤 roi_trailing_distance 则平仓
    """
    direction = pos['direction']
    entry_price = pos['entry_price']
    leverage = pos.get('leverage', 1)

    roi_stop = pos.get('roi_stop_loss', -8)
    roi_tp = pos.get('roi_take_profit', 0)  # 固定止盈目标，0表示用移动止盈
    roi_trail_start = pos.get('roi_trailing_start', 5)
    roi_trail_dist = pos.get('roi_trailing_distance', 3)

    high = candle['high']
    low = candle['low']

    # 计算本bar的最佳/最差ROI
    if direction == 'LONG':
        best_roi = ((high - entry_price) / entry_price) * leverage * 100
        worst_roi = ((low - entry_price) / entry_price) * leverage * 100
    else:
        best_roi = ((entry_price - low) / entry_price) * leverage * 100
        worst_roi = ((entry_price - high) / entry_price) * leverage * 100

    # 更新峰值ROI
    peak_roi = pos.get('peak_roi', 0)
    if best_roi > peak_roi:
        pos['peak_roi'] = best_roi
        peak_roi = best_roi

    # === 1. 止损检查: ROI跌到止损线 ===
    if worst_roi <= roi_stop:
        exit_pct = roi_stop / (leverage * 100)
        if direction == 'LONG':
            exit_price = entry_price * (1 + exit_pct)
        else:
            exit_price = entry_price * (1 - exit_pct)
        pos['stop_move_count'] = pos.get('stop_move_count', 0)
        return exit_price, f"触发止损 (ROI {roi_stop}%)"

    # === 2. 固定止盈模式: ROI达到目标就平仓 ===
    if roi_tp > 0 and best_roi >= roi_tp:
        exit_pct = roi_tp / (leverage * 100)
        if direction == 'LONG':
            exit_price = entry_price * (1 + exit_pct)
        else:
            exit_price = entry_price * (1 - exit_pct)
        pos['stop_move_count'] = pos.get('stop_move_count', 0) + 1
        return exit_price, f"触发止盈 (ROI +{roi_tp}%)"

    # === 3. 移动止盈模式: 峰值ROI超过启动线后，回撤超过距离就平仓 ===
    if roi_tp == 0 and peak_roi >= roi_trail_start:
        trail_exit_roi = peak_roi - roi_trail_dist
        if worst_roi <= trail_exit_roi:
            exit_pct = trail_exit_roi / (leverage * 100)
            if direction == 'LONG':
                exit_price = entry_price * (1 + exit_pct)
            else:
                exit_price = entry_price * (1 - exit_pct)
            pos['stop_move_count'] = pos.get('stop_move_count', 0) + 1
            if trail_exit_roi > 0:
                return exit_price, f"移动止盈 (ROI +{trail_exit_roi:.1f}%)"
            else:
                return exit_price, f"触发止损 (ROI {trail_exit_roi:.1f}%)"

    return None, None


def _calc_pnl(pos, exit_price, fee_rate, bars_held):
    """计算盈亏"""
    entry_price = pos['entry_price']
    amount = pos['amount']
    leverage = pos['leverage']
    direction = pos['direction']

    if direction == 'SHORT':
        pct = (entry_price - exit_price) / entry_price
    else:
        pct = (exit_price - entry_price) / entry_price

    pnl_raw = amount * pct * leverage
    fee = amount * leverage * fee_rate * 2  # 开+平
    funding = amount * leverage * 0.0001 * (bars_held / 8)  # 每8h收一次
    pnl = pnl_raw - fee - funding

    return pnl, fee, funding


def _compute_summary(trades, initial_capital, final_capital, equity_curve, bankrupt):
    """计算统计摘要"""
    total_trades = len(trades)
    win_trades = sum(1 for t in trades if t['pnl'] > 0)
    loss_trades = sum(1 for t in trades if t['pnl'] <= 0)
    win_rate = (win_trades / total_trades * 100) if total_trades > 0 else 0

    total_pnl = final_capital - initial_capital

    # 最大回撤
    max_drawdown = 0
    peak = initial_capital
    for point in equity_curve:
        if point['capital'] > peak:
            peak = point['capital']
        dd = ((peak - point['capital']) / peak) * 100 if peak > 0 else 0
        if dd > max_drawdown:
            max_drawdown = dd

    # 盈亏比
    total_win = sum(t['pnl'] for t in trades if t['pnl'] > 0)
    total_loss = abs(sum(t['pnl'] for t in trades if t['pnl'] <= 0))
    profit_factor = total_win / total_loss if total_loss > 0 else float('inf')

    avg_win = total_win / win_trades if win_trades > 0 else 0
    avg_loss = -total_loss / loss_trades if loss_trades > 0 else 0

    best_trade = max((t['pnl'] for t in trades), default=0)
    worst_trade = min((t['pnl'] for t in trades), default=0)

    return {
        'initial_capital': initial_capital,
        'final_capital': round(final_capital, 2),
        'total_pnl': round(total_pnl, 2),
        'total_trades': total_trades,
        'win_trades': win_trades,
        'loss_trades': loss_trades,
        'win_rate': round(win_rate, 1),
        'max_drawdown': round(max_drawdown, 2),
        'profit_factor': round(profit_factor, 2) if profit_factor != float('inf') else 999,
        'avg_win': round(avg_win, 2),
        'avg_loss': round(avg_loss, 2),
        'best_trade': round(best_trade, 2),
        'worst_trade': round(worst_trade, 2),
        'bankrupt': bankrupt
    }


def _ts_to_str(ts_ms):
    """毫秒时间戳转字符串"""
    dt = datetime.fromtimestamp(ts_ms / 1000, tz=timezone.utc)
    return dt.strftime('%Y-%m-%d %H:%M')


# ─── V8: ATR Dual Trailing Stop (ceyhun) ─────────────────────

def _calculate_atr_trail_bt(candles, atr_period, atr_factor):
    """Calculate ATR Trailing Stop for backtest candle list."""
    n = len(candles)
    if n < atr_period + 1:
        return [None] * n

    # Pre-compute ATR
    atrs = [None] * n
    for i in range(atr_period, n):
        trs = []
        for j in range(i - atr_period + 1, i + 1):
            h = candles[j]['high']
            l = candles[j]['low']
            pc = candles[j - 1]['close']
            trs.append(max(h - l, abs(h - pc), abs(l - pc)))
        atrs[i] = sum(trs) / atr_period

    trail = [None] * n
    start = atr_period
    trail[start] = candles[start]['close'] - atrs[start] * atr_factor

    for i in range(start + 1, n):
        sc = candles[i]['close']
        prev_sc = candles[i - 1]['close']
        sl = atrs[i] * atr_factor
        prev_trail = trail[i - 1]

        if sc > prev_trail and prev_sc > prev_trail:
            trail[i] = max(prev_trail, sc - sl)
        elif sc < prev_trail and prev_sc < prev_trail:
            trail[i] = min(prev_trail, sc + sl)
        elif sc > prev_trail:
            trail[i] = sc - sl
        else:
            trail[i] = sc + sl

    return trail


def analyze_signal_v8(candles, config=None):
    """V8信号分析 — ATR双轨交叉策略 (backtest版)

    Fast Trail: ATR(5)×0.5, Slow Trail: ATR(10)×3.0
    评分: 交叉新鲜度(35) + 区域强度(25) + 轨道间距(15) + 成交量(15) + 距离(10)
    """
    if len(candles) < 50:
        return 0, None

    config = config or {}
    closes = [c['close'] for c in candles]
    highs = [c['high'] for c in candles]
    lows = [c['low'] for c in candles]
    volumes = [c['volume'] for c in candles]
    current_price = closes[-1]

    fast_period = config.get('v8_fast_atr_period', 5)
    fast_factor = config.get('v8_fast_atr_factor', 0.5)
    slow_period = config.get('v8_slow_atr_period', 10)
    slow_factor = config.get('v8_slow_atr_factor', 3.0)

    trail1 = _calculate_atr_trail_bt(candles, fast_period, fast_factor)
    trail2 = _calculate_atr_trail_bt(candles, slow_period, slow_factor)

    if trail1[-1] is None or trail2[-1] is None or \
       trail1[-2] is None or trail2[-2] is None:
        return 0, None

    t1, t2 = trail1[-1], trail2[-1]
    t1_prev, t2_prev = trail1[-2], trail2[-2]

    # Crossover
    buy_cross = t1_prev <= t2_prev and t1 > t2
    sell_cross = t1_prev >= t2_prev and t1 < t2

    # Zones
    close = closes[-1]
    high = highs[-1]
    low = lows[-1]
    green = t1 > t2 and close > t2 and low > t2
    blue = t1 > t2 and close > t2 and low < t2
    red = t2 > t1 and close < t2 and high < t2
    yellow = t2 > t1 and close < t2 and high > t2

    # Barssince green/red
    bars_since_green, bars_since_red = None, None
    lookback = min(50, len(candles) - max(fast_period, slow_period) - 1)
    for j in range(lookback):
        idx = len(candles) - 1 - j
        if trail1[idx] is None or trail2[idx] is None:
            break
        c_j, l_j, h_j = closes[idx], lows[idx], highs[idx]
        t1_j, t2_j = trail1[idx], trail2[idx]
        if bars_since_green is None and t1_j > t2_j and c_j > t2_j and l_j > t2_j:
            bars_since_green = j
        if bars_since_red is None and t2_j > t1_j and c_j < t2_j and h_j < t2_j:
            bars_since_red = j
        if bars_since_green is not None and bars_since_red is not None:
            break
    bars_since_green = bars_since_green if bars_since_green is not None else 999
    bars_since_red = bars_since_red if bars_since_red is not None else 999

    is_bull = bars_since_green < bars_since_red

    # Direction
    if t1 > t2:
        direction = 'LONG'
    elif t2 > t1:
        direction = 'SHORT'
    else:
        direction = 'LONG' if is_bull else 'SHORT'

    # Scoring
    total_score = 0

    # 1. Crossover recency (0-35)
    if buy_cross or sell_cross:
        total_score += 35
    else:
        bars_since_cross = 30
        for j in range(1, min(30, len(candles) - max(fast_period, slow_period))):
            idx = len(candles) - 1 - j
            if trail1[idx] is None or trail2[idx] is None:
                break
            prev_idx = idx - 1
            if trail1[prev_idx] is None or trail2[prev_idx] is None:
                break
            if (trail1[prev_idx] <= trail2[prev_idx] and trail1[idx] > trail2[idx]) or \
               (trail1[prev_idx] >= trail2[prev_idx] and trail1[idx] < trail2[idx]):
                bars_since_cross = j
                break
        if bars_since_cross <= 3:
            total_score += 28
        elif bars_since_cross <= 6:
            total_score += 20
        elif bars_since_cross <= 12:
            total_score += 12
        else:
            total_score += 5

    # 2. Zone strength (0-25)
    if direction == 'LONG':
        total_score += 25 if green else (15 if blue else (3 if yellow else 8))
    else:
        total_score += 25 if red else (15 if yellow else (3 if blue else 8))

    # 3. Trail separation (0-15)
    trail_sep = abs(t1 - t2) / current_price * 100 if current_price > 0 else 0
    if trail_sep > 2.0:
        total_score += 15
    elif trail_sep > 1.0:
        total_score += 12
    elif trail_sep > 0.5:
        total_score += 8
    else:
        total_score += 3

    # 4. Volume (0-15)
    avg_vol = sum(volumes[-20:]) / 20 if len(volumes) >= 20 else 1
    vol_ratio = volumes[-1] / avg_vol if avg_vol > 0 else 1
    if vol_ratio > 1.5:
        total_score += 15
    elif vol_ratio > 1.2:
        total_score += 10
    elif vol_ratio > 1.0:
        total_score += 7
    else:
        total_score += 3

    # 5. Distance from Trail2 (0-10)
    dist_pct = abs(close - t2) / t2 * 100 if t2 > 0 else 0
    if dist_pct > 2.0:
        total_score += 10
    elif dist_pct > 1.0:
        total_score += 7
    else:
        total_score += 3

    # Penalties
    rsi = calculate_rsi(closes)
    if direction == 'LONG' and rsi > 80:
        total_score = int(total_score * 0.80)
    elif direction == 'SHORT' and rsi < 20:
        total_score = int(total_score * 0.80)

    # ADX filter
    adx = calculate_adx(candles)
    if config.get('v8_adx_filter', True) and adx is not None:
        if adx < 15:
            total_score = int(total_score * 0.60)
        elif adx < 20:
            total_score = int(total_score * 0.80)

    # SHORT bias
    short_bias = config.get('short_bias', 1.05)
    if direction == 'SHORT':
        total_score = int(total_score * short_bias)

    # ATR for sizing
    atr = None
    if len(candles) >= 15:
        trs = []
        for i in range(len(candles) - 14, len(candles)):
            h = candles[i]['high']
            l = candles[i]['low']
            pc = candles[i - 1]['close']
            trs.append(max(h - l, abs(h - pc), abs(l - pc)))
        atr = sum(trs) / 14

    analysis = {
        'price': current_price,
        'rsi': rsi,
        'direction': direction,
        'score': total_score,
        'volume_ratio': vol_ratio,
        'adx': adx,
        'atr': atr,
        'trail1': t1,
        'trail2': t2,
        'trail_separation_pct': round(trail_sep, 3),
        'zone': 'GREEN' if green else ('BLUE' if blue else ('RED' if red else ('YELLOW' if yellow else 'NEUTRAL'))),
        'is_bull': is_bull,
        'buy_crossover': buy_cross,
        'sell_crossover': sell_cross,
    }

    return total_score, analysis
