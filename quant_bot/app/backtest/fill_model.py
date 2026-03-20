"""成交模型 - 模拟真实成交环境 (Spec §23.1)
纳入:
  - 手续费 (taker 0.04%)
  - 滑点 (2 basis points)
  - 资金费率 (0.01% / 8h)
  - 部分成交 (≤10% bar volume, <30% abandon)
  - 最小下单单位
"""
import logging

log = logging.getLogger(__name__)

# 默认参数
DEFAULT_FEE_RATE = 0.0004       # Taker fee
DEFAULT_SLIPPAGE_BPS = 2        # 2 basis points
DEFAULT_FUNDING_RATE = 0.0001   # 0.01% per 8h
FUNDING_INTERVAL_BARS = 32      # 8h / 15m = 32 bars
MAX_FILL_PCT = 0.10             # 最多吃掉bar成交量的10%
MIN_FILL_RATIO = 0.30           # 低于30%成交则放弃


class FillModel:
    """回测成交模型"""

    def __init__(self, fee_rate=DEFAULT_FEE_RATE, slippage_bps=DEFAULT_SLIPPAGE_BPS,
                 funding_rate=DEFAULT_FUNDING_RATE):
        self.fee_rate = fee_rate
        self.slippage_pct = slippage_bps / 100  # convert bps to pct
        self.funding_rate = funding_rate

    def apply_slippage(self, price, direction):
        """入场时应用滑点"""
        return price * (1 + self.slippage_pct / 100 * direction)

    def apply_exit_slippage(self, price, direction):
        """出场时应用滑点"""
        return price * (1 - self.slippage_pct / 100 * direction)

    def calc_fee(self, notional):
        """计算手续费"""
        return abs(notional) * self.fee_rate

    def calc_funding(self, notional, direction):
        """计算单次资金费"""
        return notional * self.funding_rate * direction

    def check_partial_fill(self, notional, bar_volume, bar_close):
        """检查部分成交
        返回 (可成交名义值, 是否放弃)
        """
        bar_volume_usd = bar_volume * bar_close
        max_fill_usd = bar_volume_usd * MAX_FILL_PCT

        if notional <= max_fill_usd:
            return notional, False  # 全部成交

        fill_ratio = max_fill_usd / notional
        if fill_ratio < MIN_FILL_RATIO:
            return 0, True  # 成交量太低，放弃

        return max_fill_usd, False  # 部分成交

    def calc_pnl(self, direction, entry_price, exit_price, size):
        """计算毛利"""
        if direction == 1:
            return (exit_price - entry_price) * size
        else:
            return (entry_price - exit_price) * size
