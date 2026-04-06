"""
PreFilter: Layer 1 of the signal extraction pipeline.
Cost = 0 (pure keyword + pattern matching).
Expects 10-20% pass rate.
"""
import re

# Crypto trading keywords (EN + CN)
KEYWORDS = {
    # Coins
    'btc', 'bitcoin', 'eth', 'ethereum', 'bnb', 'sol', 'solana', 'xrp',
    'ada', 'doge', 'avax', 'dot', 'matic', 'link', 'uni', 'atom',
    'apt', 'arb', 'op', 'sui', 'sei', 'jup', 'wif', 'pepe',
    'usdt', 'usdc',
    # Trading actions EN
    'long', 'short', 'buy', 'sell', 'entry', 'target', 'stop loss',
    'take profit', 'tp', 'sl', 'leverage', 'liquidation', 'breakout',
    'support', 'resistance', 'bullish', 'bearish', 'pump', 'dump',
    'signal', 'trade', 'position', 'margin', 'futures',
    # Trading actions CN
    '做多', '做空', '买入', '卖出', '入场', '止损', '止盈',
    '杠杆', '爆仓', '突破', '支撑', '阻力', '多头', '空头',
    '开仓', '平仓', '仓位', '合约', '现货', '看涨', '看跌',
    '目标价', '回调', '反弹', '信号',
}

# Price pattern: matches things like 68000, 68000.50, $68,000
PRICE_PATTERN = re.compile(r'\$?\d{1,3}(?:,?\d{3})*\.?\d*')

# Leverage pattern: 2x, 10x, 20X, etc.
LEVERAGE_PATTERN = re.compile(r'\b\d{1,3}[xX]\b')

# Minimum message length
MIN_LENGTH = 10


def prefilter(text: str) -> bool:
    """
    Returns True if message likely contains a trading signal.
    Layer 1 filter — free, runs on every message.
    """
    if not text or len(text.strip()) < MIN_LENGTH:
        return False

    text_lower = text.lower()

    # Check for keyword matches (need at least 2 for higher confidence)
    keyword_hits = sum(1 for kw in KEYWORDS if kw in text_lower)
    if keyword_hits >= 2:
        return True

    # Check for price pattern + at least one keyword
    if keyword_hits >= 1 and PRICE_PATTERN.search(text):
        return True

    # Check for leverage mention + keyword
    if keyword_hits >= 1 and LEVERAGE_PATTERN.search(text):
        return True

    return False
