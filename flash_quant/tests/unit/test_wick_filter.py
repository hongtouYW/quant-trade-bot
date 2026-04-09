"""
Wick Filter 单元测试 - TC-WICK-001 ~ TC-WICK-010
"""
import pytest
from filters.wick_filter import wick_filter
from core.exceptions import InvalidKlineError


class TestWickFilter:

    def test_001_big_bullish_candle(self):
        """大阳线, 实体 75% → PASS"""
        passed, ratio = wick_filter(100, 102, 100, 101.5)
        assert passed is True
        assert ratio >= 0.55

    def test_002_big_bearish_candle(self):
        """大阴线, 实体 75% → PASS"""
        passed, ratio = wick_filter(100, 100.5, 98, 98.5)
        assert passed is True
        assert ratio >= 0.55

    def test_003_long_upper_wick(self):
        """长上影线, 插针多 → FAIL"""
        passed, ratio = wick_filter(100, 110, 99, 100.5)
        assert passed is False

    def test_004_long_lower_wick(self):
        """长下影线, 插针空 → FAIL"""
        passed, ratio = wick_filter(100, 100.5, 90, 99.5)
        assert passed is False

    def test_005_doji(self):
        """十字星 (实体 0) → FAIL"""
        passed, ratio = wick_filter(100, 105, 95, 100)
        assert passed is False
        assert ratio < 0.01

    def test_006_flat_line(self):
        """一字线 (total=0) → FAIL"""
        passed, ratio = wick_filter(100, 100, 100, 100)
        assert passed is False
        assert ratio == 0.0

    def test_007_boundary_pass(self):
        """实体 恰好 > 0.55 → PASS"""
        # body=2, upper=0.5, lower=0.5, total=3, ratio=0.667
        passed, ratio = wick_filter(100, 102.5, 100, 102)
        assert passed is True
        assert ratio >= 0.55

    def test_008_boundary_fail(self):
        """实体 < 0.55 → FAIL"""
        # body=0.5, upper=2, lower=0.5, total=3, ratio=0.167
        passed, ratio = wick_filter(100, 103, 100, 100.5)
        assert passed is False

    def test_009_zero_price_raises(self):
        """价格为 0 → 异常"""
        with pytest.raises(InvalidKlineError):
            wick_filter(0, 100, 99, 100)

    def test_010_negative_price_raises(self):
        """负价格 → 异常"""
        with pytest.raises(InvalidKlineError):
            wick_filter(-1, 100, 99, 100)

    def test_high_less_than_low_raises(self):
        """high < low → 异常"""
        with pytest.raises(InvalidKlineError):
            wick_filter(100, 99, 101, 100)

    def test_none_price_raises(self):
        """None 价格 → 异常"""
        with pytest.raises(InvalidKlineError):
            wick_filter(None, 100, 99, 100)

    def test_string_price_raises(self):
        """字符串价格 → 异常"""
        with pytest.raises(InvalidKlineError):
            wick_filter("100", 101, 99, 100)

    def test_healthy_candle_variants(self):
        """多种健康 K线"""
        # 纯阳线无影线
        passed, _ = wick_filter(100, 105, 100, 105)
        assert passed is True

        # 纯阴线无影线
        passed, _ = wick_filter(105, 105, 100, 100)
        assert passed is True
