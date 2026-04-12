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

    def test_max_leverage_is_20(self):
        assert MAX_LEVERAGE == 20

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

    def test_btc_is_20x(self):
        tier = get_leverage_tier("BTCUSDT")
        assert tier['max_leverage'] == 20
        assert tier['stop_loss_roi'] == -0.10

    def test_eth_is_20x(self):
        tier = get_leverage_tier("ETHUSDT")
        assert tier['max_leverage'] == 20

    def test_sol_is_20x(self):
        tier = get_leverage_tier("SOLUSDT")
        assert tier['max_leverage'] == 20

    def test_bnb_is_20x(self):
        tier = get_leverage_tier("BNBUSDT")
        assert tier['max_leverage'] == 20

    def test_unknown_defaults_20x(self):
        tier = get_leverage_tier("RANDOMUSDT")
        assert tier['max_leverage'] == 20
        assert tier['stop_loss_roi'] == -0.10

    def test_ccxt_format_btc(self):
        tier = get_leverage_tier("BTC/USDT:USDT")
        assert tier['max_leverage'] == 20

    def test_all_tiers_same_leverage(self):
        """检讨后统一 20x"""
        for name, config in LEVERAGE_TIERS.items():
            assert config['max_leverage'] == 20, f"{name} should be 20x"
            assert config['stop_loss_roi'] == -0.10, f"{name} should be -10%"
