"""Signal Analyzer - Pure functions for market signal analysis.

Extracted from paper_trader.py and backtest_engine.py.
All functions are stateless and take raw market data as input.
"""
import requests
from typing import Optional


# ─── Coin Tiers (from paper_trader.py backtested profitability data) ──────
# v4 策略: 基于2023-2025回测 + 2026验证
# T1: 平均PnL>600U的连续盈利币 (加仓1.3x)
# T2: 平均PnL 300-600U (标准仓位1.0x)
# T3: 平均PnL <300U但仍盈利 (降仓0.7x)
COIN_TIERS = {
    # T1: 连续盈利>600U (26个) - 加仓1.3x
    'ICP/USDT': 'T1', 'XMR/USDT': 'T1', 'IOTA/USDT': 'T1', 'DASH/USDT': 'T1',
    'COMP/USDT': 'T1', 'KAVA/USDT': 'T1', 'UNI/USDT': 'T1', 'SAND/USDT': 'T1',
    'AXS/USDT': 'T1', 'NEAR/USDT': 'T1', 'DOT/USDT': 'T1', 'CHZ/USDT': 'T1',
    'ENJ/USDT': 'T1', 'ADA/USDT': 'T1', 'VET/USDT': 'T1', 'BCH/USDT': 'T1',
    'ATOM/USDT': 'T1', 'ROSE/USDT': 'T1', 'DYDX/USDT': 'T1', 'IMX/USDT': 'T1',
    'AAVE/USDT': 'T1', 'XLM/USDT': 'T1', 'LINK/USDT': 'T1', 'SXP/USDT': 'T1',
    'ALGO/USDT': 'T1', 'CRV/USDT': 'T1',
    # T2: 平均PnL 300-600U (24个) - 标准仓位1.0x
    'ALPHA/USDT': 'T2', 'MKR/USDT': 'T2', 'ETC/USDT': 'T2', 'NEO/USDT': 'T2',
    'THETA/USDT': 'T2', 'ZEC/USDT': 'T2', 'RENDER/USDT': 'T2', 'GRT/USDT': 'T2',
    'SNX/USDT': 'T2', 'HBAR/USDT': 'T2', 'CELO/USDT': 'T2', 'ETH/USDT': 'T2',
    'FIL/USDT': 'T2', 'HYPE/USDT': 'T2', 'SHIB/USDT': 'T2', 'BNB/USDT': 'T2',
    'PYTH/USDT': 'T2', 'BTC/USDT': 'T2', 'LINA/USDT': 'T2', 'FLOKI/USDT': 'T2',
    'INIT/USDT': 'T2', 'SEI/USDT': 'T2', 'XRP/USDT': 'T2', 'ORDI/USDT': 'T2',
    # T3: 平均PnL <300U但仍盈利 (17个) - 降仓0.7x
    'WIF/USDT': 'T3', 'FET/USDT': 'T3', 'LTC/USDT': 'T3', 'LEVER/USDT': 'T3',
    'MATIC/USDT': 'T3', 'ENA/USDT': 'T3', 'MANA/USDT': 'T3', 'PENGU/USDT': 'T3',
    'STRK/USDT': 'T3', 'INJ/USDT': 'T3', 'DOGE/USDT': 'T3', 'OP/USDT': 'T3',
    'BNX/USDT': 'T3', 'TRUMP/USDT': 'T3', 'TRX/USDT': 'T3', 'ONE/USDT': 'T3',
    'JUP/USDT': 'T3',
}

# Tier仓位乘数 (与 paper_trader.py 完全一致)
TIER_MULTIPLIER = {'T1': 1.3, 'T2': 1.0, 'T3': 0.7}

# 持续亏损币 - 完全跳过 (回测验证)
SKIP_COINS = {'BERA/USDT', 'IP/USDT', 'LIT/USDT', 'TROY/USDT',
              'VIRTUAL/USDT', 'BONK/USDT', 'PEPE/USDT',
              'DUSK/USDT', 'FARTCOIN/USDT', 'ANIME/USDT'}

# 1000x token mapping (Binance uses 1000XXXUSDT for sub-cent tokens)
SYMBOL_1000 = {'BONK', 'PEPE', 'SHIB', 'FLOKI'}

# Exchange API endpoints
EXCHANGE_API = {
    'binance': {
        'base': 'https://fapi.binance.com',
        'klines': '/fapi/v1/klines',
        'price': '/fapi/v1/ticker/price',
    },
    'bitget': {
        'base': 'https://api.bitget.com',
        'klines': '/api/v2/mix/market/candles',
        'price': '/api/v2/mix/market/tickers',
    },
}
BINANCE_FUTURES = 'https://fapi.binance.com'  # kept for backward compat

# BTC trend cache (avoid redundant API calls during a scan cycle)
_btc_trend_cache = {'data': None, 'ts': 0}


# ─── Technical Indicators ───────────────────────────────────

def calculate_rsi(prices: list, period: int = 14) -> float:
    """Calculate RSI (Relative Strength Index)."""
    if len(prices) < period:
        return 50.0

    deltas = [prices[i] - prices[i - 1] for i in range(1, len(prices))]
    gains = [d if d > 0 else 0 for d in deltas]
    losses = [-d if d < 0 else 0 for d in deltas]

    avg_gain = sum(gains[-period:]) / period
    avg_loss = sum(losses[-period:]) / period

    if avg_loss == 0:
        return 100.0

    rs = avg_gain / avg_loss
    return 100 - (100 / (1 + rs))


def calculate_atr(candles: list, period: int = 14) -> Optional[float]:
    """Calculate ATR (Average True Range) from candle dicts with high/low/close."""
    if len(candles) < period + 1:
        return None

    true_ranges = []
    for i in range(1, len(candles)):
        high = candles[i]['high']
        low = candles[i]['low']
        prev_close = candles[i - 1]['close']
        tr = max(high - low, abs(high - prev_close), abs(low - prev_close))
        true_ranges.append(tr)

    return sum(true_ranges[-period:]) / period


def calculate_macd(closes: list, fast: int = 12, slow: int = 26,
                   signal: int = 9) -> Optional[dict]:
    """Calculate MACD (Moving Average Convergence Divergence).

    Returns: {macd, signal_line, histogram} or None if insufficient data.
    """
    if len(closes) < slow + signal:
        return None

    def _ema(data, period):
        k = 2 / (period + 1)
        ema = [data[0]]
        for price in data[1:]:
            ema.append(price * k + ema[-1] * (1 - k))
        return ema

    ema_fast = _ema(closes, fast)
    ema_slow = _ema(closes, slow)
    macd_line = [f - s for f, s in zip(ema_fast, ema_slow)]
    signal_line = _ema(macd_line[slow - 1:], signal)

    # Align lengths
    macd_val = macd_line[-1]
    signal_val = signal_line[-1]
    histogram = macd_val - signal_val

    prev_histogram = macd_line[-2] - signal_line[-2] if len(signal_line) >= 2 else 0

    return {
        'macd': macd_val,
        'signal_line': signal_val,
        'histogram': histogram,
        'prev_histogram': prev_histogram,
        'crossover_up': prev_histogram <= 0 and histogram > 0,
        'crossover_down': prev_histogram >= 0 and histogram < 0,
    }


def calculate_bollinger_bands(closes: list, period: int = 20,
                              std_dev: float = 2.0) -> Optional[dict]:
    """Calculate Bollinger Bands.

    Returns: {upper, middle, lower, bandwidth, percent_b} or None.
    """
    if len(closes) < period:
        return None

    window = closes[-period:]
    middle = sum(window) / period
    variance = sum((x - middle) ** 2 for x in window) / period
    std = variance ** 0.5

    upper = middle + std_dev * std
    lower = middle - std_dev * std
    bandwidth = (upper - lower) / middle if middle > 0 else 0
    price = closes[-1]
    percent_b = (price - lower) / (upper - lower) if upper > lower else 0.5

    return {
        'upper': upper,
        'middle': middle,
        'lower': lower,
        'bandwidth': bandwidth,
        'percent_b': percent_b,
    }


def calculate_adx(candles: list, period: int = 14) -> Optional[float]:
    """Calculate ADX (Average Directional Index) — trend strength 0-100."""
    if len(candles) < period * 2 + 1:
        return None

    plus_dm = []
    minus_dm = []
    tr_list = []

    for i in range(1, len(candles)):
        high = candles[i]['high']
        low = candles[i]['low']
        prev_high = candles[i - 1]['high']
        prev_low = candles[i - 1]['low']
        prev_close = candles[i - 1]['close']

        up_move = high - prev_high
        down_move = prev_low - low

        plus_dm.append(up_move if up_move > down_move and up_move > 0 else 0)
        minus_dm.append(down_move if down_move > up_move and down_move > 0 else 0)

        tr = max(high - low, abs(high - prev_close), abs(low - prev_close))
        tr_list.append(tr)

    def _smooth(data, p):
        s = sum(data[:p])
        result = [s]
        for val in data[p:]:
            s = s - s / p + val
            result.append(s)
        return result

    smoothed_tr = _smooth(tr_list, period)
    smoothed_plus = _smooth(plus_dm, period)
    smoothed_minus = _smooth(minus_dm, period)

    dx_list = []
    for i in range(len(smoothed_tr)):
        if smoothed_tr[i] == 0:
            dx_list.append(0)
            continue
        plus_di = 100 * smoothed_plus[i] / smoothed_tr[i]
        minus_di = 100 * smoothed_minus[i] / smoothed_tr[i]
        di_sum = plus_di + minus_di
        dx_list.append(abs(plus_di - minus_di) / di_sum * 100 if di_sum > 0 else 0)

    if len(dx_list) < period:
        return None

    adx = sum(dx_list[-period:]) / period
    return adx


# ─── Market Data Fetching ────────────────────────────────────

def binance_symbol(symbol: str) -> str:
    """Convert trading pair to Binance futures symbol."""
    s = symbol.upper().replace('/USDT', '').replace('USDT', '')
    if s in SYMBOL_1000:
        return f'1000{s}USDT'
    return f'{s}USDT'


def bitget_symbol(symbol: str) -> str:
    """Convert trading pair to Bitget futures symbol."""
    s = symbol.upper().replace('/USDT', '').replace('USDT', '').split(':')[0]
    return f'{s}USDT'


def exchange_symbol(symbol: str, exchange: str = 'binance') -> str:
    """Convert trading pair to exchange-specific symbol format."""
    if exchange == 'bitget':
        return bitget_symbol(symbol)
    return binance_symbol(symbol)


def fetch_klines(symbol: str, interval: str = '1h', limit: int = 100,
                 timeout: int = 10, exchange: str = 'binance') -> Optional[list]:
    """Fetch kline data from exchange API.

    Returns list of candle dicts: [{time, open, high, low, close, volume}, ...]
    """
    try:
        api = EXCHANGE_API.get(exchange, EXCHANGE_API['binance'])
        es = exchange_symbol(symbol, exchange)

        if exchange == 'bitget':
            # Bitget uses different interval format: '1h' → '1H', '4h' → '4H', '1d' → '1D'
            bg_interval = interval.upper() if interval[-1] in ('h', 'd', 'w') else interval
            # Bitget mix market candles
            resp = requests.get(
                f"{api['base']}{api['klines']}",
                params={
                    'symbol': es,
                    'productType': 'USDT-FUTURES',
                    'granularity': bg_interval,
                    'limit': str(limit),
                },
                timeout=timeout,
            )
            resp.raise_for_status()
            raw = resp.json().get('data', [])
            # Bitget returns: [[ts, open, high, low, close, vol, quoteVol], ...]
            # sorted newest first, reverse for chronological order
            raw.reverse()
            return [{
                'time': int(c[0]),
                'open': float(c[1]),
                'high': float(c[2]),
                'low': float(c[3]),
                'close': float(c[4]),
                'volume': float(c[5]),
            } for c in raw]
        else:
            # Binance
            resp = requests.get(
                f"{api['base']}{api['klines']}",
                params={'symbol': es, 'interval': interval, 'limit': limit},
                timeout=timeout,
            )
            resp.raise_for_status()
            raw = resp.json()
            return [{
                'time': c[0],
                'open': float(c[1]),
                'high': float(c[2]),
                'low': float(c[3]),
                'close': float(c[4]),
                'volume': float(c[5]),
            } for c in raw]
    except Exception as e:
        print(f"[SignalAnalyzer] Failed to fetch klines for {symbol} ({exchange}): {e}")
        return None


def fetch_price(symbol: str, timeout: int = 5,
                exchange: str = 'binance') -> Optional[float]:
    """Fetch current price from exchange."""
    try:
        api = EXCHANGE_API.get(exchange, EXCHANGE_API['binance'])
        es = exchange_symbol(symbol, exchange)

        if exchange == 'bitget':
            resp = requests.get(
                f"{api['base']}{api['price']}",
                params={'symbol': es, 'productType': 'USDT-FUTURES'},
                timeout=timeout,
            )
            resp.raise_for_status()
            data = resp.json().get('data', [])
            # Verify we got the right symbol (endpoint may return all tickers)
            for item in data:
                if item.get('symbol', '').upper() == es.upper():
                    return float(item['lastPr'])
            # Fallback: if only one result returned, trust it
            if len(data) == 1:
                return float(data[0]['lastPr'])
            return None
        else:
            resp = requests.get(
                f"{api['base']}{api['price']}",
                params={'symbol': es}, timeout=timeout,
            )
            resp.raise_for_status()
            data = resp.json()
            return float(data['price']) if 'price' in data else None
    except Exception as e:
        print(f"[SignalAnalyzer] fetch_price {symbol} failed: {e}")
        return None


def get_btc_trend(timeout: int = 10) -> dict:
    """Get BTC market trend direction and strength.

    Cached for 120 seconds to avoid redundant API calls when scanning ~150 coins.
    Returns: {direction: 'up'|'down'|'neutral', strength: 0-3, price, ma50}
    """
    import time as _time
    now = _time.time()
    if _btc_trend_cache['data'] and (now - _btc_trend_cache['ts']) < 120:
        return _btc_trend_cache['data']

    try:
        candles = fetch_klines('BTC/USDT', '1h', 60, timeout=timeout)
        if not candles or len(candles) < 50:
            return {'direction': 'neutral', 'strength': 0, 'price': 0, 'ma50': 0}

        closes = [c['close'] for c in candles]
        price = closes[-1]
        ma7 = sum(closes[-7:]) / 7
        ma20 = sum(closes[-20:]) / 20
        ma50 = sum(closes[-50:]) / 50

        strength = 0
        if price > ma7 > ma20:
            direction = 'up'
            strength = 1
            if price > ma50:
                strength = 2
            if price > ma7 > ma20 > ma50:
                strength = 3
        elif price < ma7 < ma20:
            direction = 'down'
            strength = 1
            if price < ma50:
                strength = 2
            if price < ma7 < ma20 < ma50:
                strength = 3
        else:
            direction = 'neutral'

        result = {'direction': direction, 'strength': strength,
                  'price': price, 'ma50': ma50}
        _btc_trend_cache['data'] = result
        _btc_trend_cache['ts'] = now
        return result
    except Exception:
        return {'direction': 'neutral', 'strength': 0, 'price': 0, 'ma50': 0}


# ─── Core Signal Analysis ────────────────────────────────────

def analyze_signal(symbol: str, config: dict, exchange: str = 'binance') -> tuple:
    """Analyze trading signal for a symbol (0-100 score).

    Args:
        symbol: Trading pair e.g. 'BTC/USDT'
        config: Dict with strategy parameters:
            - enable_btc_filter (bool)
            - enable_trend_filter (bool)
            - short_bias (float, e.g. 1.05)
            - long_min_score (int, default 70)

    Returns:
        (score: int, analysis: dict | None)
    """
    try:
        klines = fetch_klines(symbol, '1h', 100, exchange=exchange)
        if not klines or len(klines) < 50:
            return 0, None

        closes = [c['close'] for c in klines]
        volumes = [c['volume'] for c in klines]
        highs = [c['high'] for c in klines]
        lows = [c['low'] for c in klines]

        current_price = closes[-1]

        # Direction voting system
        votes = {'LONG': 0, 'SHORT': 0}

        # 1. RSI analysis (30 pts)
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

        # 2. Trend analysis (30 pts)
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

        # 3. Volume analysis (20 pts)
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

        # 4. Price position (20 pts)
        high_50 = max(highs[-50:])
        low_50 = min(lows[-50:])
        price_position = ((current_price - low_50) / (high_50 - low_50)
                          if high_50 > low_50 else 0.5)

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

        # Direction by vote
        if votes['LONG'] > votes['SHORT']:
            direction = 'LONG'
        elif votes['SHORT'] > votes['LONG']:
            direction = 'SHORT'
        else:
            direction = 'LONG' if rsi < 50 else 'SHORT'

        total_score = rsi_score + trend_score + volume_score + position_score

        # Coin's own trend
        coin_has_own_trend = False
        if direction == 'LONG' and current_price > ma7 > ma20:
            coin_has_own_trend = True
        elif direction == 'SHORT' and current_price < ma7 < ma20:
            coin_has_own_trend = True

        # === BTC Trend Filter ===
        if config.get('enable_btc_filter', True):
            btc_trend = get_btc_trend()
            btc_dir = btc_trend['direction']
            btc_str = btc_trend['strength']
            btc_ma50 = btc_trend.get('ma50', 0)
            btc_price = btc_trend.get('price', 0)
            btc_below_ma50 = btc_price > 0 and btc_ma50 > 0 and btc_price < btc_ma50

            if btc_dir == 'down' and direction == 'LONG':
                if coin_has_own_trend:
                    total_score = int(total_score * 0.35)
                elif btc_str >= 2:
                    total_score = int(total_score * 0.15)
                else:
                    total_score = int(total_score * 0.25)
            elif btc_below_ma50 and direction == 'LONG':
                if coin_has_own_trend:
                    total_score = int(total_score * 0.50)
                else:
                    total_score = int(total_score * 0.35)
            elif btc_dir == 'up' and direction == 'SHORT':
                if coin_has_own_trend:
                    total_score = int(total_score * 0.75)
                elif btc_str >= 2:
                    total_score = int(total_score * 0.45)
                else:
                    total_score = int(total_score * 0.60)
        else:
            btc_dir = 'neutral'

        # RSI/trend conflict penalty
        if config.get('enable_trend_filter', True):
            rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
            trend_dir = 'LONG' if current_price > ma20 else 'SHORT'
            if rsi_dir != trend_dir and not coin_has_own_trend:
                total_score = int(total_score * 0.85)

        # SHORT bias from backtest data
        short_bias = float(config.get('short_bias', 1.05))
        if direction == 'SHORT':
            total_score = int(total_score * short_bias)

        analysis = {
            'price': current_price,
            'rsi': rsi,
            'ma7': ma7,
            'ma20': ma20,
            'ma50': ma50,
            'volume_ratio': volume_ratio,
            'price_position': price_position,
            'direction': direction,
            'score': total_score,
            'btc_trend': btc_dir,
            'coin_own_trend': coin_has_own_trend,
        }

        return total_score, analysis

    except Exception as e:
        print(f"[SignalAnalyzer] {symbol} analysis failed: {e}")
        return 0, None


# ─── Position Sizing ─────────────────────────────────────────

def calculate_position_size(score: int, available: float,
                            config: dict, symbol: str = None) -> tuple:
    """Calculate position size and leverage based on signal score.

    Args:
        score: Signal score (0-100)
        available: Available capital
        config: Strategy config dict with max_leverage, max_position_size
        symbol: Trading pair for tier-based sizing

    Returns:
        (size: float, leverage: int)
    """
    max_leverage = int(config.get('max_leverage', 3))
    max_size = float(config.get('max_position_size', 500))
    leverage = min(3, max_leverage)  # v4.2 default: fixed 3x

    if score >= 85:
        size = min(150, available * 0.08)
    elif score >= 80:
        size = min(250, available * 0.15)
    elif score >= 75:
        size = min(350, available * 0.22)
    elif score >= 70:
        size = min(250, available * 0.15)
    elif score >= 60:
        size = min(150, available * 0.1)
    else:
        return 0, leverage

    # Cap at max position size
    size = min(size, max_size)

    # Tier multiplier
    if symbol:
        tier = COIN_TIERS.get(symbol, 'T3')
        multiplier = TIER_MULTIPLIER.get(tier, 0.7)
        size = size * multiplier

    size = max(50, int(size))
    return size, leverage


# ─── Stop-Loss / Take-Profit Calculation ─────────────────────

def calculate_stop_take(entry_price: float, direction: str,
                        leverage: int, config: dict) -> dict:
    """Calculate stop-loss and take-profit prices.

    Returns dict with: stop_loss, take_profit, roi_stop_loss,
                       roi_trailing_start, roi_trailing_distance
    """
    roi_stop = float(config.get('roi_stop_loss', -10))
    roi_trail_start = float(config.get('roi_trailing_start', 6))
    roi_trail_dist = float(config.get('roi_trailing_distance', 3))

    stop_price_pct = roi_stop / (leverage * 100)
    tp_price_pct = roi_trail_start / (leverage * 100)

    if direction == 'LONG':
        stop_loss = entry_price * (1 + stop_price_pct)
        take_profit = entry_price * (1 + tp_price_pct)
    else:
        stop_loss = entry_price * (1 - stop_price_pct)
        take_profit = entry_price * (1 - tp_price_pct)

    return {
        'stop_loss': stop_loss,
        'take_profit': take_profit,
        'roi_stop_loss': roi_stop,
        'roi_trailing_start': roi_trail_start,
        'roi_trailing_distance': roi_trail_dist,
    }


# ─── V6 Signal Analysis (v4.2 base + MACD/ADX/BB bonus) ──────

def analyze_signal_v6(symbol: str, config: dict, exchange: str = 'binance') -> tuple:
    """V6 signal: v4.2 base scoring (0-100) + MACD/ADX/BB bonus (0-25) = max 125.

    Same base as analyze_signal() but with additional indicator bonuses.
    Matches paper_trader auto_trader_v6.py and backtest_engine analyze_signal_v6b().
    """
    try:
        klines = fetch_klines(symbol, '1h', 100, exchange=exchange)
        if not klines or len(klines) < 50:
            return 0, None

        closes = [c['close'] for c in klines]
        volumes = [c['volume'] for c in klines]
        highs = [c['high'] for c in klines]
        lows = [c['low'] for c in klines]

        current_price = closes[-1]

        # Direction voting system
        votes = {'LONG': 0, 'SHORT': 0}

        # 1. RSI analysis (30 pts)
        rsi = calculate_rsi(closes)
        if rsi < 30:
            rsi_score = 30; votes['LONG'] += 1
        elif rsi > 70:
            rsi_score = 30; votes['SHORT'] += 1
        elif rsi < 45:
            rsi_score = 15; votes['LONG'] += 1
        elif rsi > 55:
            rsi_score = 15; votes['SHORT'] += 1
        else:
            rsi_score = 5

        # 2. Trend analysis (30 pts)
        ma7 = sum(closes[-7:]) / 7
        ma20 = sum(closes[-20:]) / 20
        ma50 = sum(closes[-50:]) / 50

        if current_price > ma7 > ma20 > ma50:
            trend_score = 30; votes['LONG'] += 2
        elif current_price < ma7 < ma20 < ma50:
            trend_score = 30; votes['SHORT'] += 2
        elif current_price > ma7 > ma20:
            trend_score = 15; votes['LONG'] += 1
        elif current_price < ma7 < ma20:
            trend_score = 15; votes['SHORT'] += 1
        else:
            trend_score = 5

        # 3. Volume analysis (20 pts)
        avg_volume = sum(volumes[-20:]) / 20
        volume_ratio = volumes[-1] / avg_volume if avg_volume > 0 else 1
        if volume_ratio > 1.5:
            volume_score = 20
        elif volume_ratio > 1.2:
            volume_score = 15
        elif volume_ratio > 1:
            volume_score = 10
        else:
            volume_score = 5

        # 4. Price position (20 pts)
        high_50 = max(highs[-50:])
        low_50 = min(lows[-50:])
        price_position = ((current_price - low_50) / (high_50 - low_50)
                          if high_50 > low_50 else 0.5)
        if price_position < 0.2:
            position_score = 20; votes['LONG'] += 1
        elif price_position > 0.8:
            position_score = 20; votes['SHORT'] += 1
        elif price_position < 0.35:
            position_score = 10; votes['LONG'] += 1
        elif price_position > 0.65:
            position_score = 10; votes['SHORT'] += 1
        else:
            position_score = 5

        # Direction by vote
        if votes['LONG'] > votes['SHORT']:
            direction = 'LONG'
        elif votes['SHORT'] > votes['LONG']:
            direction = 'SHORT'
        else:
            direction = 'LONG' if rsi < 50 else 'SHORT'

        total_score = rsi_score + trend_score + volume_score + position_score

        # === RSI/trend conflict penalty (BEFORE bonus, matches Report v6b) ===
        rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
        trend_dir = 'LONG' if current_price > ma20 else 'SHORT'
        if rsi_dir != trend_dir:
            total_score = int(total_score * 0.85)

        # === V6 BONUS: MACD/ADX/BB (+25 max) ===
        bonus = 0
        macd = calculate_macd(closes)
        bb = calculate_bollinger_bands(closes)
        adx = calculate_adx(klines)

        # MACD confirmation (+3/+6/+10) — crossover-based (matches Report v6b)
        if macd:
            hist = macd['histogram']
            prev_hist = macd['prev_histogram']
            macd_dir = 'LONG' if hist > 0 else 'SHORT'
            if macd_dir == direction:
                if macd.get('crossover_up') or macd.get('crossover_down'):
                    bonus += 10  # crossover + direction match
                elif (hist > 0 and hist > prev_hist) or \
                     (hist < 0 and hist < prev_hist):
                    bonus += 6   # momentum increasing + direction match
                else:
                    bonus += 3   # direction match only

        # ADX trend strength (+2/+5/+8)
        if adx is not None:
            if adx >= 35:
                bonus += 8
            elif adx >= 25:
                bonus += 5
            elif adx >= 20:
                bonus += 2

        # BB extreme position (+4/+7)
        if bb:
            pct_b = bb['percent_b']
            if direction == 'LONG':
                if pct_b < 0.1:
                    bonus += 7
                elif pct_b < 0.25:
                    bonus += 4
            else:
                if pct_b > 0.9:
                    bonus += 7
                elif pct_b > 0.75:
                    bonus += 4

        total_score += bonus

        # BTC trend filter (same as v4 but simplified — no coin_has_own_trend for v6)
        btc_dir = 'neutral'
        if config.get('enable_btc_filter', True):
            btc_trend = get_btc_trend()
            btc_dir = btc_trend['direction']
            btc_str = btc_trend['strength']
            btc_ma50 = btc_trend.get('ma50', 0)
            btc_price = btc_trend.get('price', 0)
            btc_below_ma50 = btc_price > 0 and btc_ma50 > 0 and btc_price < btc_ma50

            if btc_dir == 'down' and direction == 'LONG':
                if btc_str >= 2:
                    total_score = int(total_score * 0.20)
                else:
                    total_score = int(total_score * 0.35)
            elif btc_below_ma50 and direction == 'LONG':
                total_score = int(total_score * 0.40)
            elif btc_dir == 'up' and direction == 'SHORT':
                if btc_str >= 2:
                    total_score = int(total_score * 0.50)
                else:
                    total_score = int(total_score * 0.65)

        # SHORT bias
        short_bias = float(config.get('short_bias', 1.05))
        if direction == 'SHORT':
            total_score = int(total_score * short_bias)

        # Cap score
        total_score = min(125, total_score)

        analysis = {
            'price': current_price,
            'rsi': rsi,
            'ma7': ma7,
            'ma20': ma20,
            'ma50': ma50,
            'volume_ratio': volume_ratio,
            'price_position': price_position,
            'direction': direction,
            'score': total_score,
            'bonus': bonus,
            'adx': adx,
            'macd_histogram': macd['histogram'] if macd else None,
            'bb_percent_b': bb['percent_b'] if bb else None,
            'btc_trend': btc_dir,
        }

        return total_score, analysis

    except Exception as e:
        print(f"[SignalAnalyzer-v6] {symbol} analysis failed: {e}")
        return 0, None


# ─── V5 Signal Analysis (10x leverage, triple confirmation) ───

def analyze_signal_v5(symbol: str, config: dict, exchange: str = 'binance') -> tuple:
    """V5 signal analysis: MACD + BB + RSI + ADX triple confirmation.

    Designed for 10x leverage — stricter entry, ADX trend filter.
    Returns: (score: int, analysis: dict | None)
    """
    try:
        klines = fetch_klines(symbol, '1h', 100, exchange=exchange)
        if not klines or len(klines) < 50:
            return 0, None

        closes = [c['close'] for c in klines]
        volumes = [c['volume'] for c in klines]
        current_price = closes[-1]

        # Calculate all indicators
        rsi = calculate_rsi(closes)
        macd = calculate_macd(closes)
        bb = calculate_bollinger_bands(closes)
        adx = calculate_adx(klines)
        atr = calculate_atr(klines)

        if not macd or not bb or adx is None:
            return 0, None

        # ADX gate: skip ranging markets (too risky at 10x)
        adx_min = int(config.get('adx_min_threshold', 25))
        if adx < adx_min:
            return 0, None

        # Direction voting
        votes = {'LONG': 0, 'SHORT': 0}
        total_score = 0

        # 1. RSI direction (0-20 pts)
        if rsi < 30:
            total_score += 20
            votes['LONG'] += 1
        elif rsi > 70:
            total_score += 20
            votes['SHORT'] += 1
        elif rsi < 40:
            total_score += 12
            votes['LONG'] += 1
        elif rsi > 60:
            total_score += 12
            votes['SHORT'] += 1
        else:
            total_score += 3

        # 2. MACD direction + momentum (0-25 pts)
        hist = macd['histogram']
        prev_hist = macd['prev_histogram']
        macd_val = macd['macd']

        if hist > 0 and macd_val > 0:
            total_score += 25
            votes['LONG'] += 2
        elif hist < 0 and macd_val < 0:
            total_score += 25
            votes['SHORT'] += 2
        elif hist > 0 and hist > prev_hist:
            total_score += 15
            votes['LONG'] += 1
        elif hist < 0 and hist < prev_hist:
            total_score += 15
            votes['SHORT'] += 1
        else:
            total_score += 5

        # 3. Bollinger Bands position (0-20 pts)
        pct_b = bb['percent_b']
        if pct_b < 0.1:
            total_score += 20
            votes['LONG'] += 1
        elif pct_b > 0.9:
            total_score += 20
            votes['SHORT'] += 1
        elif pct_b < 0.3:
            total_score += 12
            votes['LONG'] += 1
        elif pct_b > 0.7:
            total_score += 12
            votes['SHORT'] += 1
        else:
            total_score += 3

        # 4. ADX trend strength (0-15 pts)
        if adx > 40:
            total_score += 15
        elif adx > 30:
            total_score += 10
        else:
            total_score += 5

        # 5. Volume confirmation (0-10 pts)
        avg_vol = sum(volumes[-20:]) / 20
        vol_ratio = volumes[-1] / avg_vol if avg_vol > 0 else 1
        if vol_ratio > 1.5:
            total_score += 10
        elif vol_ratio > 1.2:
            total_score += 7
        elif vol_ratio > 1.0:
            total_score += 4
        else:
            total_score += 1

        # Determine direction
        if votes['LONG'] > votes['SHORT']:
            direction = 'LONG'
        elif votes['SHORT'] > votes['LONG']:
            direction = 'SHORT'
        else:
            direction = 'LONG' if rsi < 50 else 'SHORT'

        # Triple confirmation bonus: RSI + MACD + BB all agree
        rsi_dir = 'LONG' if rsi < 45 else ('SHORT' if rsi > 55 else 'neutral')
        macd_dir = 'LONG' if hist > 0 else ('SHORT' if hist < 0 else 'neutral')
        bb_dir = 'LONG' if pct_b < 0.35 else ('SHORT' if pct_b > 0.65 else 'neutral')
        if rsi_dir == macd_dir == bb_dir and rsi_dir != 'neutral':
            total_score += 10

        # BTC trend filter (0-10 pts bonus/penalty)
        if config.get('enable_btc_filter', True):
            btc_trend = get_btc_trend()
            btc_dir = btc_trend['direction']
            if btc_dir == 'down' and direction == 'LONG':
                total_score = int(total_score * 0.5)
            elif btc_dir == 'up' and direction == 'SHORT':
                total_score = int(total_score * 0.7)
            elif btc_dir == 'up' and direction == 'LONG':
                total_score += 5
            elif btc_dir == 'down' and direction == 'SHORT':
                total_score += 5
        else:
            btc_dir = 'neutral'

        # SHORT bias
        short_bias = float(config.get('short_bias', 1.05))
        if direction == 'SHORT':
            total_score = int(total_score * short_bias)

        # Cap at 100
        total_score = min(100, total_score)

        ma20 = sum(closes[-20:]) / 20
        ma50 = sum(closes[-50:]) / 50

        analysis = {
            'price': current_price,
            'rsi': rsi,
            'macd_histogram': hist,
            'bb_percent_b': pct_b,
            'bb_bandwidth': bb['bandwidth'],
            'adx': adx,
            'atr': atr,
            'volume_ratio': vol_ratio,
            'direction': direction,
            'score': total_score,
            'btc_trend': btc_dir,
            'triple_confirm': rsi_dir == macd_dir == bb_dir and rsi_dir != 'neutral',
            'ma20': ma20,
            'ma50': ma50,
        }

        return total_score, analysis

    except Exception as e:
        print(f"[SignalAnalyzer-v5] {symbol} analysis failed: {e}")
        return 0, None


def calculate_position_size_v5(score: int, available: float, config: dict,
                               symbol: str = None, atr: float = None,
                               price: float = None) -> tuple:
    """V5 position sizing — ATR-based, smaller positions for 10x.

    Returns: (size: float, leverage: int)
    """
    leverage = int(config.get('max_leverage', 10))
    max_size = float(config.get('max_position_size', 150))
    initial_capital = float(config.get('initial_capital', 2000))

    # Risk per trade: 1% of capital
    risk_per_trade = initial_capital * 0.01

    # ATR-based sizing: smaller position when volatility is high
    if atr and price and price > 0:
        atr_pct = atr / price
        atr_mult = float(config.get('atr_stop_multiplier', 1.5))
        stop_distance = atr_pct * atr_mult * leverage
        if stop_distance > 0:
            size = risk_per_trade / stop_distance
        else:
            size = 100
    else:
        # Fallback: score-based
        if score >= 90:
            size = min(150, available * 0.10)
        elif score >= 85:
            size = min(120, available * 0.08)
        elif score >= 80:
            size = min(100, available * 0.06)
        else:
            size = min(80, available * 0.05)

    # Cap
    size = min(size, max_size, available * 0.15)

    # Tier multiplier (only T1/T2 for 10x — skip T3 low-liquidity)
    if symbol:
        tier = COIN_TIERS.get(symbol, 'T3')
        if tier == 'T3':
            size = size * 0.5  # much smaller for T3 at 10x
        elif tier == 'T1':
            size = size * 1.2

    size = max(30, int(size))
    return size, leverage


def calculate_stop_take_v5(entry_price: float, direction: str,
                           leverage: int, config: dict,
                           atr: float = None) -> dict:
    """V5 stop/take with ATR-based dynamic SL and dual TP levels.

    Returns dict with: stop_loss, take_profit (TP1), tp2_price,
                       roi_stop_loss, tp1_roi, tp2_roi, roi_trailing_start,
                       roi_trailing_distance
    """
    roi_stop = float(config.get('roi_stop_loss', -8))
    tp1_roi = float(config.get('tp1_roi', 10))
    tp2_roi = float(config.get('tp2_roi', 20))
    roi_trail_dist = float(config.get('roi_trailing_distance', 5))
    use_atr = config.get('use_atr_stop', True)
    atr_mult = float(config.get('atr_stop_multiplier', 1.5))

    # ATR-based stop loss
    if use_atr and atr and atr > 0:
        atr_stop_pct = (atr * atr_mult) / entry_price
        roi_stop_from_atr = -atr_stop_pct * leverage * 100
        # Use the tighter of config SL and ATR SL, but not tighter than -5%
        roi_stop = max(roi_stop_from_atr, roi_stop, -15)
        roi_stop = min(roi_stop, -5)  # at least -5% ROI stop

    stop_price_pct = roi_stop / (leverage * 100)
    tp1_price_pct = tp1_roi / (leverage * 100)
    tp2_price_pct = tp2_roi / (leverage * 100)

    if direction == 'LONG':
        stop_loss = entry_price * (1 + stop_price_pct)
        take_profit = entry_price * (1 + tp1_price_pct)
        tp2_price = entry_price * (1 + tp2_price_pct)
    else:
        stop_loss = entry_price * (1 - stop_price_pct)
        take_profit = entry_price * (1 - tp1_price_pct)
        tp2_price = entry_price * (1 - tp2_price_pct)

    return {
        'stop_loss': stop_loss,
        'take_profit': take_profit,  # TP1 price
        'tp2_price': tp2_price,
        'roi_stop_loss': roi_stop,
        'tp1_roi': tp1_roi,
        'tp2_roi': tp2_roi,
        'roi_trailing_start': tp2_roi,  # trailing starts at TP2
        'roi_trailing_distance': roi_trail_dist,
    }


# ─── Watchlist ────────────────────────────────────────────────

DEFAULT_WATCHLIST = [
    # === 顶级流动性 (10) ===
    'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'XRP/USDT', 'BNB/USDT',
    'DOGE/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT',
    # === 主流公链 (15) ===
    'NEAR/USDT', 'SUI/USDT', 'APT/USDT', 'ATOM/USDT', 'FTM/USDT',
    'HBAR/USDT', 'XLM/USDT', 'ETC/USDT', 'LTC/USDT', 'BCH/USDT',
    'ALGO/USDT', 'ICP/USDT', 'FIL/USDT', 'XMR/USDT', 'TRX/USDT',
    # === Layer2/DeFi (15) ===
    'ARB/USDT', 'OP/USDT', 'MATIC/USDT', 'AAVE/USDT', 'UNI/USDT',
    'CRV/USDT', 'DYDX/USDT', 'INJ/USDT', 'SEI/USDT', 'STX/USDT',
    'RUNE/USDT', 'SNX/USDT', 'COMP/USDT', 'MKR/USDT', 'LDO/USDT',
    # === AI/新叙事 (15) ===
    'TAO/USDT', 'RENDER/USDT', 'FET/USDT', 'WLD/USDT', 'AGIX/USDT',
    'OCEAN/USDT', 'ARKM/USDT', 'PENGU/USDT', 'BERA/USDT', 'VIRTUAL/USDT',
    'AIXBT/USDT', 'GRASS/USDT', 'GRIFFAIN/USDT', 'GOAT/USDT', 'CGPT/USDT',
    # === 中市值热门 (25) ===
    'TIA/USDT', 'JUP/USDT', 'PYTH/USDT', 'JTO/USDT', 'ENA/USDT',
    'STRK/USDT', 'ZRO/USDT', 'WIF/USDT', 'BONK/USDT', 'PEPE/USDT',
    'SHIB/USDT', 'FLOKI/USDT', 'TRUMP/USDT', 'VET/USDT', 'AXS/USDT',
    'ROSE/USDT', 'DUSK/USDT', 'CHZ/USDT', 'ENJ/USDT', 'SAND/USDT',
    'ONDO/USDT', 'PENDLE/USDT', 'EIGEN/USDT', 'ETHFI/USDT', 'TON/USDT',
    # === GameFi/存储/其他 (15) ===
    'MANA/USDT', 'GALA/USDT', 'IMX/USDT', 'ORDI/USDT', 'SXP/USDT',
    'ZEC/USDT', 'DASH/USDT', 'WAVES/USDT', 'GRT/USDT', 'THETA/USDT',
    'IOTA/USDT', 'NEO/USDT', 'KAVA/USDT', 'ONE/USDT', 'CELO/USDT',
    # === DeFi/基础设施 (15) ===
    'CAKE/USDT', 'SUSHI/USDT', 'GMX/USDT', 'ENS/USDT', 'BLUR/USDT',
    'PEOPLE/USDT', 'MASK/USDT', '1INCH/USDT', 'ANKR/USDT', 'AR/USDT',
    'FLOW/USDT', 'EGLD/USDT', 'KAS/USDT', 'JASMY/USDT', 'NOT/USDT',
    # === Meme/热点 (15) ===
    'NEIRO/USDT', 'PNUT/USDT', 'POPCAT/USDT', 'TURBO/USDT', 'MEME/USDT',
    'BOME/USDT', 'DOGS/USDT', 'FARTCOIN/USDT', 'USUAL/USDT', 'ME/USDT',
    'MOODENG/USDT', 'BRETT/USDT', 'SPX/USDT', 'ANIME/USDT', 'SONIC/USDT',
    # === 高波动 (25) ===
    'IP/USDT', 'INIT/USDT', 'HYPE/USDT', 'LINA/USDT', 'LEVER/USDT',
    'ALPHA/USDT', 'LIT/USDT', 'UNFI/USDT', 'DGB/USDT', 'REN/USDT',
    'BSW/USDT', 'AMB/USDT', 'TROY/USDT', 'OMNI/USDT', 'BNX/USDT',
    'YGG/USDT', 'PIXEL/USDT', 'PORTAL/USDT', 'XAI/USDT', 'DYM/USDT',
    'MANTA/USDT', 'ZK/USDT', 'W/USDT', 'SAGA/USDT', 'RSR/USDT',
]  # Total: ~150 coins (matches paper_trader.py watchlist)
