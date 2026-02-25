"""Signal Analyzer - Pure functions for market signal analysis.

Extracted from paper_trader.py and backtest_engine.py.
All functions are stateless and take raw market data as input.
"""
import requests
from typing import Optional


# ─── Coin Tiers ─────────────────────────────────────────────
COIN_TIERS = {
    'BTC/USDT': 'T1', 'ETH/USDT': 'T1', 'BNB/USDT': 'T1', 'SOL/USDT': 'T1',
    'XRP/USDT': 'T2', 'ADA/USDT': 'T2', 'AVAX/USDT': 'T2', 'DOT/USDT': 'T2',
    'LINK/USDT': 'T2', 'MATIC/USDT': 'T2', 'DOGE/USDT': 'T2', 'SHIB/USDT': 'T2',
    'UNI/USDT': 'T2', 'ATOM/USDT': 'T2', 'NEAR/USDT': 'T2', 'LTC/USDT': 'T2',
    'FIL/USDT': 'T2', 'AAVE/USDT': 'T2',
}

TIER_MULTIPLIER = {'T1': 1.0, 'T2': 0.9, 'T3': 0.7}

# 1000x token mapping
SYMBOL_1000 = {'BONK', 'PEPE', 'SHIB', 'FLOKI'}

BINANCE_FUTURES = 'https://fapi.binance.com'


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


# ─── Market Data Fetching ────────────────────────────────────

def binance_symbol(symbol: str) -> str:
    """Convert trading pair to Binance futures symbol."""
    s = symbol.upper().replace('/USDT', '').replace('USDT', '')
    if s in SYMBOL_1000:
        return f'1000{s}USDT'
    return f'{s}USDT'


def fetch_klines(symbol: str, interval: str = '1h', limit: int = 100,
                 timeout: int = 10) -> Optional[list]:
    """Fetch kline data from Binance Futures API.

    Returns list of candle dicts: [{time, open, high, low, close, volume}, ...]
    """
    try:
        bs = binance_symbol(symbol)
        resp = requests.get(
            f'{BINANCE_FUTURES}/fapi/v1/klines',
            params={'symbol': bs, 'interval': interval, 'limit': limit},
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
        print(f"[SignalAnalyzer] Failed to fetch klines for {symbol}: {e}")
        return None


def fetch_price(symbol: str, timeout: int = 5) -> Optional[float]:
    """Fetch current price from Binance Futures."""
    try:
        bs = binance_symbol(symbol)
        resp = requests.get(
            f'{BINANCE_FUTURES}/fapi/v1/ticker/price',
            params={'symbol': bs}, timeout=timeout,
        )
        resp.raise_for_status()
        data = resp.json()
        return float(data['price']) if 'price' in data else None
    except Exception:
        return None


def get_btc_trend(timeout: int = 10) -> dict:
    """Get BTC market trend direction and strength.

    Returns: {direction: 'up'|'down'|'neutral', strength: 0-3, price, ma50}
    """
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

        return {'direction': direction, 'strength': strength,
                'price': price, 'ma50': ma50}
    except Exception:
        return {'direction': 'neutral', 'strength': 0, 'price': 0, 'ma50': 0}


# ─── Core Signal Analysis ────────────────────────────────────

def analyze_signal(symbol: str, config: dict) -> tuple:
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
        klines = fetch_klines(symbol, '1h', 100)
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


# ─── Watchlist ────────────────────────────────────────────────

DEFAULT_WATCHLIST = [
    'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'XRP/USDT',
    'ADA/USDT', 'AVAX/USDT', 'DOT/USDT', 'LINK/USDT', 'MATIC/USDT',
    'DOGE/USDT', 'SHIB/USDT', 'UNI/USDT', 'ATOM/USDT', 'NEAR/USDT',
    'LTC/USDT', 'FIL/USDT', 'AAVE/USDT', 'APT/USDT', 'ARB/USDT',
    'OP/USDT', 'SUI/USDT', 'SEI/USDT', 'TIA/USDT', 'INJ/USDT',
    'FET/USDT', 'RNDR/USDT', 'WIF/USDT', 'PEPE/USDT', 'BONK/USDT',
]
