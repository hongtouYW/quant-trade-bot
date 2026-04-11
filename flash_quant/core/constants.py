"""
Flash Quant - 业务规则硬编码常量
=================================
对应 PRD BR-001 ~ BR-008
这些值不可在配置文件中修改,只能改代码。
"""
from enum import IntEnum


# === BR-001: 分级杠杆上限 ===
LEVERAGE_TIERS = {
    'tier_a_major': {
        'symbols': ['BTC', 'ETH'],
        'max_leverage': 50,
        'stop_loss_roi': -0.05,     # -5% ROI
        'max_hold_hours': 1,
    },
    'tier_a_large': {
        'symbols': ['SOL', 'BNB', 'XRP', 'DOGE', 'ADA', 'AVAX'],
        'max_leverage': 30,
        'stop_loss_roi': -0.08,     # -8% ROI
        'max_hold_hours': 4,
    },
    'tier_b': {
        'max_leverage': 20,
        'stop_loss_roi': -0.10,     # -10% ROI
        'max_hold_hours': 8,
    },
    'tier_c': {
        'max_leverage': 15,
        'stop_loss_roi': -0.10,
        'max_hold_hours': 20,
    },
}
MAX_LEVERAGE = 50  # 绝对上限


# === BR-002: Tier D 黑名单 ===
TIER_D_VOLUME_THRESHOLD = 10_000_000  # USD, 24h volume


# === BR-003: 新币黑名单 ===
NEW_LISTING_DAYS = 7


# === BR-004: 渐进式资金 ===
class Phase(IntEnum):
    P1_PAPER = 1
    P2_SMALL = 2
    P3_MEDIUM = 3
    P4_FULL = 4


PHASE_CAPITAL_LIMITS = {
    Phase.P1_PAPER: 0,
    Phase.P2_SMALL: 500,
    Phase.P3_MEDIUM: 2_000,
    Phase.P4_FULL: 10_000,
}


# === BR-005: 单笔上限 ===
MAX_MARGIN_PER_TRADE = 300  # USDT


# === BR-006: 服务端止损 ===
STOP_LOSS_PLACEMENT_TIMEOUT = 5  # seconds


# === BR-007: Tier 1 时段 ===
TIER1_TRADING_HOURS_UTC = (8, 22)  # 8:00 - 22:00 UTC


# === BR-008: 周末减仓 ===
WEEKEND_POSITION_MULTIPLIER = 0.5


# === 其他常量 ===
MAX_CONCURRENT_POSITIONS = 5
SIGNAL_LATENCY_TARGET_MS = 500
COOLDOWN_AFTER_CLOSE_HOURS = 2

# 过滤器参数
WICK_BODY_RATIO_MIN = 0.55
CVD_TOLERANCE = 0.10
CVD_LOOKBACK = 20
FUNDING_RATE_MAX = 0.0008  # 0.08%

# Tier 1 扫描参数
TIER1_SCAN_INTERVAL = 30         # seconds
# 快速验证模式 (正式值: 5.0 / 0.02 / 0.08 / 1.5 / 0.67)
TIER1_VOLUME_RATIO_MIN = 3.0     # 验证: 3x (正式: 5x)
TIER1_PRICE_CHANGE_MIN = 0.005   # 验证: 0.5% (正式: 2%)
TIER1_OI_CHANGE_MIN = 0.0
TIER1_TAKER_RATIO_LONG = 0.0
TIER1_TAKER_RATIO_SHORT = 999.0

# Tier 2 扫描参数 (验证模式放宽)
TIER2_SCAN_INTERVAL = 60
TIER2_RSI_LONG = 58          # 验证: 58 (正式: 65)
TIER2_RSI_SHORT = 42         # 验证: 42 (正式: 35)
TIER2_VOLUME_MULTIPLIER = 1.0  # 验证: 1.0 (正式: 1.5)

# Tier 3 扫描参数 (验证模式放宽)
TIER3_SCAN_INTERVAL = 3600       # 1H
TIER3_MIN_SCORE = 55             # 验证: 55 (正式: 75)

# 阶梯止盈 (Tier 1)
TIER1_TAKE_PROFIT_LADDER = [
    (0.15, 0.30),  # +15% ROI 平 30%
    (0.30, 0.30),  # +30% ROI 平 30%
    (0.60, 0.40),  # +60% ROI 平 40%
]

# 风控断路器
CIRCUIT_CONSECUTIVE_HALF = 3    # 连亏 N 笔仓位减半
CIRCUIT_CONSECUTIVE_PAUSE = 5   # 连亏 N 笔暂停
CIRCUIT_CONSECUTIVE_PAUSE_HOURS = 4
CIRCUIT_CONSECUTIVE_FULL_PAUSE = 8
CIRCUIT_CONSECUTIVE_FULL_PAUSE_HOURS = 24
CIRCUIT_DAILY_LOSS_PCT = 0.03   # -3%
CIRCUIT_WEEKLY_LOSS_PCT = 0.08  # -8%
CIRCUIT_WEEKLY_PAUSE_HOURS = 24
CIRCUIT_MONTHLY_LOSS_PCT = 0.15  # -15%
CIRCUIT_MONTHLY_PAUSE_HOURS = 72

# 黑天鹅
BLACK_SWAN_VOLATILITY_THRESHOLD = 0.05  # 5%
BLACK_SWAN_PAUSE_MINUTES = 30

# 模拟盘参数
PAPER_SLIPPAGE_PCT = 0.0005     # 0.05%
PAPER_TAKER_FEE_PCT = 0.0005   # Binance VIP 0


def get_leverage_tier(symbol: str) -> dict:
    """根据 symbol 返回对应的杠杆 tier 配置"""
    base = symbol.replace('/USDT', '').replace('USDT', '').split(':')[0].upper()

    for tier_name, tier_config in LEVERAGE_TIERS.items():
        if 'symbols' in tier_config and base in tier_config['symbols']:
            return {**tier_config, 'tier_name': tier_name}

    # 判断 Tier B vs Tier C 需要 24h volume 数据, 这里默认 Tier B
    return {**LEVERAGE_TIERS['tier_b'], 'tier_name': 'tier_b'}
