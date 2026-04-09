"""
Blacklist Filter 单元测试 - BR-002, BR-003
"""
import pytest
from datetime import datetime, timezone, timedelta
from filters.blacklist_filter import is_tradable


class TestBlacklistFilter:

    def test_tier_a_blue_chip(self):
        """蓝筹币 → PASS"""
        passed, _ = is_tradable("BTCUSDT", volume_24h=50_000_000_000)
        assert passed is True

    def test_tier_c_decent_volume(self):
        """中等成交量山寨 → PASS"""
        passed, _ = is_tradable("ARBUSDT", volume_24h=50_000_000)
        assert passed is True

    def test_tier_d_low_volume(self):
        """Tier D 垃圾币 (24h < $10M) → FAIL"""
        passed, reason = is_tradable("SHITUSDT", volume_24h=5_000_000)
        assert passed is False
        assert "tier_d" in reason

    def test_new_listing_6_days(self):
        """上市 6 天 → FAIL"""
        listing = datetime.now(timezone.utc) - timedelta(days=6)
        passed, reason = is_tradable("NEWUSDT", volume_24h=100_000_000,
                                     listing_date=listing)
        assert passed is False
        assert "new_listing" in reason

    def test_new_listing_7_days(self):
        """上市 7 天 → PASS (边界)"""
        listing = datetime.now(timezone.utc) - timedelta(days=7)
        passed, _ = is_tradable("NEWUSDT", volume_24h=100_000_000,
                                listing_date=listing)
        assert passed is True

    def test_old_listing(self):
        """上市 100 天 → PASS"""
        listing = datetime.now(timezone.utc) - timedelta(days=100)
        passed, _ = is_tradable("OLDUSDT", volume_24h=100_000_000,
                                listing_date=listing)
        assert passed is True

    def test_empty_symbol(self):
        """空 symbol → FAIL"""
        passed, _ = is_tradable("", volume_24h=100_000_000)
        assert passed is False

    def test_none_volume_passes(self):
        """volume_24h=None (未知) → PASS (不过滤)"""
        passed, _ = is_tradable("BTCUSDT", volume_24h=None)
        assert passed is True

    def test_none_listing_passes(self):
        """listing_date=None → PASS (不过滤)"""
        passed, _ = is_tradable("BTCUSDT", volume_24h=100_000_000,
                                listing_date=None)
        assert passed is True
