"""
Constants 单元测试 - 验证 BR 硬编码值和 get_leverage_tier
"""
import pytest
from core.constants import (
    LEVERAGE_TIERS, MAX_LEVERAGE, MAX_MARGIN_PER_TRADE,
    TIER_D_VOLUME_THRESHOLD, NEW_LISTING_DAYS,
    get_leverage_tier, Phase, PHASE_CAPITAL_LIMITS,
)


class TestConstants:

    def test_max_leverage_is_50(self):
        assert MAX_LEVERAGE == 50

    def test_max_margin_is_300(self):
        assert MAX_MARGIN_PER_TRADE == 300

    def test_tier_d_threshold_10m(self):
        assert TIER_D_VOLUME_THRESHOLD == 10_000_000

    def test_new_listing_7_days(self):
        assert NEW_LISTING_DAYS == 7

    def test_phase_enum(self):
        assert Phase.P1_PAPER == 1
        assert Phase.P4_FULL == 4

    def test_phase_capital_limits(self):
        assert PHASE_CAPITAL_LIMITS[Phase.P1_PAPER] == 0
        assert PHASE_CAPITAL_LIMITS[Phase.P2_SMALL] == 500
        assert PHASE_CAPITAL_LIMITS[Phase.P4_FULL] == 10_000


class TestGetLeverageTier:

    def test_btc_is_tier_a_major(self):
        tier = get_leverage_tier("BTCUSDT")
        assert tier['max_leverage'] == 50
        assert tier['stop_loss_roi'] == -0.05

    def test_eth_is_tier_a_major(self):
        tier = get_leverage_tier("ETHUSDT")
        assert tier['max_leverage'] == 50

    def test_sol_is_tier_a_large(self):
        tier = get_leverage_tier("SOLUSDT")
        assert tier['max_leverage'] == 30
        assert tier['stop_loss_roi'] == -0.08

    def test_bnb_is_tier_a_large(self):
        tier = get_leverage_tier("BNBUSDT")
        assert tier['max_leverage'] == 30

    def test_unknown_defaults_tier_b(self):
        tier = get_leverage_tier("RANDOMUSDT")
        assert tier['max_leverage'] == 20
        assert tier['stop_loss_roi'] == -0.10

    def test_ccxt_format_btc(self):
        """ccxt 格式: BTC/USDT:USDT"""
        tier = get_leverage_tier("BTC/USDT:USDT")
        assert tier['max_leverage'] == 50

    def test_plain_format(self):
        """简单格式: BTC"""
        tier = get_leverage_tier("BTC")
        assert tier['max_leverage'] == 50

    def test_btc_leverage_never_exceeds_50(self):
        """BR-001: BTC 杠杆 ≤ 50"""
        tier = get_leverage_tier("BTCUSDT")
        assert tier['max_leverage'] <= MAX_LEVERAGE
