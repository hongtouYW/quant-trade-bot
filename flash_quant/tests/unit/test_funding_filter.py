"""
Funding Filter 单元测试
"""
import pytest
from filters.funding_filter import funding_filter


class TestFundingFilter:

    def test_normal_positive(self):
        """正常正费率 → PASS"""
        passed, _ = funding_filter(0.0005)
        assert passed is True

    def test_normal_negative(self):
        """正常负费率 → PASS"""
        passed, _ = funding_filter(-0.0005)
        assert passed is True

    def test_zero(self):
        """零费率 → PASS"""
        passed, _ = funding_filter(0.0)
        assert passed is True

    def test_too_high(self):
        """费率过高 → FAIL"""
        passed, reason = funding_filter(0.001)
        assert passed is False
        assert "too_high" in reason

    def test_too_low(self):
        """费率过低 → FAIL"""
        passed, reason = funding_filter(-0.001)
        assert passed is False
        assert "too_low" in reason

    def test_boundary_pass(self):
        """恰好在阈值内 → PASS"""
        passed, _ = funding_filter(0.00079)
        assert passed is True

    def test_boundary_fail(self):
        """恰好超过阈值 → FAIL"""
        passed, _ = funding_filter(0.0008)
        assert passed is False

    def test_none(self):
        """None → FAIL"""
        passed, reason = funding_filter(None)
        assert passed is False

    def test_extreme_positive(self):
        """极端正费率 → FAIL"""
        passed, _ = funding_filter(0.03)
        assert passed is False
