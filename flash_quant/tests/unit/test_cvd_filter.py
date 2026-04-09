"""
CVD Filter 单元测试 - TC-CVD-001 ~ TC-CVD-010
"""
import pytest
from filters.cvd_filter import cvd_filter


class TestCvdFilter:

    def test_001_price_high_cvd_high(self):
        """价格创新高, CVD 同步创新高 → PASS"""
        prices = list(range(100, 120)) + [125]  # 新高
        cvd = list(range(0, 20)) + [25]  # 同步新高
        passed, reason = cvd_filter(prices, cvd, 'long')
        assert passed is True
        assert reason == "ok"

    def test_002_price_high_cvd_flat(self):
        """价格创新高, CVD 持平 → FAIL (背离)"""
        prices = list(range(100, 120)) + [125]
        cvd = list(range(0, 20)) + [5]  # 远低于高点
        passed, reason = cvd_filter(prices, cvd, 'long')
        assert passed is False
        assert "divergence" in reason

    def test_003_price_high_cvd_down(self):
        """价格创新高, CVD 下降 → FAIL (强背离)"""
        prices = list(range(100, 120)) + [125]
        cvd = list(range(20, 0, -1)) + [-5]  # 持续下降
        passed, reason = cvd_filter(prices, cvd, 'long')
        assert passed is False

    def test_004_short_price_low_cvd_low(self):
        """做空: 价格新低, CVD 新低 → PASS"""
        prices = [120 - i * 0.5 for i in range(20)] + [95]  # 新低
        cvd = [-i for i in range(20)] + [-25]  # 同步新低
        passed, reason = cvd_filter(prices, cvd, 'short')
        assert passed is True

    def test_005_short_price_low_cvd_flat(self):
        """做空: 价格新低, CVD 持平 → FAIL"""
        prices = list(range(120, 100, -1)) + [95]
        cvd = list(range(0, -20, -1)) + [-5]  # 远高于低点
        passed, reason = cvd_filter(prices, cvd, 'short')
        assert passed is False

    def test_006_insufficient_data(self):
        """数据不足 (< 20 根) → FAIL"""
        passed, reason = cvd_filter([100, 101], [0, 1], 'long')
        assert passed is False
        assert "insufficient" in reason

    def test_007_empty_data(self):
        """空数据 → FAIL"""
        passed, reason = cvd_filter([], [], 'long')
        assert passed is False

    def test_008_tolerance_boundary_pass(self):
        """10% tolerance 边界内 → PASS"""
        prices = [100] * 19 + [110]  # 高点 110
        cvd = [10] * 19 + [9.5]  # 只差 5% (在 10% tolerance 内)
        passed, reason = cvd_filter(prices, cvd, 'long')
        assert passed is True

    def test_009_tolerance_boundary_fail(self):
        """超过 tolerance → FAIL"""
        prices = [100] * 19 + [110]
        cvd = [10] * 19 + [8]  # 差 20% (超过 10% tolerance)
        passed, reason = cvd_filter(prices, cvd, 'long')
        assert passed is False

    def test_010_invalid_direction(self):
        """无效方向 → FAIL"""
        prices = list(range(100, 121))
        cvd = list(range(0, 21))
        passed, reason = cvd_filter(prices, cvd, 'invalid')
        assert passed is False
        assert "invalid_direction" in reason
