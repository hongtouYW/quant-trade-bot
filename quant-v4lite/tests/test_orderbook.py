"""Tests for OrderBookFilter analysis."""

import pytest

from src.core.models import OrderBook, OrderBookLevel, OrderBookAnalysis
from src.core.enums import Direction
from src.analysis.orderbook_filter import OrderBookFilter

from conftest import make_orderbook


# ────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────

def _make_wall_orderbook(direction: str, mid_price: float = 100.0) -> OrderBook:
    """Create an order book with a wall opposing the direction."""
    tick = mid_price * 0.001
    if direction == "sell_wall":
        # Huge asks, normal bids
        bids = [OrderBookLevel(price=mid_price - tick * (i + 1), quantity=10)
                for i in range(20)]
        asks = [OrderBookLevel(price=mid_price + tick * (i + 1), quantity=200)
                for i in range(20)]
    else:
        # Huge bids, normal asks
        bids = [OrderBookLevel(price=mid_price - tick * (i + 1), quantity=200)
                for i in range(20)]
        asks = [OrderBookLevel(price=mid_price + tick * (i + 1), quantity=10)
                for i in range(20)]
    return OrderBook(symbol="TEST/USDT:USDT", timestamp=1700000000000,
                     bids=bids, asks=asks)


def _make_imbalanced_orderbook(bid_qty: float, ask_qty: float,
                                mid_price: float = 100.0) -> OrderBook:
    """Create an order book with specific bid/ask quantity imbalance."""
    tick = mid_price * 0.0001
    bids = [OrderBookLevel(price=mid_price - tick * (i + 1), quantity=bid_qty)
            for i in range(20)]
    asks = [OrderBookLevel(price=mid_price + tick * (i + 1), quantity=ask_qty)
            for i in range(20)]
    return OrderBook(symbol="TEST/USDT:USDT", timestamp=1700000000000,
                     bids=bids, asks=asks)


# ────────────────────────────────────────────
# Basic functionality
# ────────────────────────────────────────────

class TestOrderBookFilterBasic:

    def test_symmetric_book_allows_entry(self, default_config):
        obf = OrderBookFilter(default_config)
        book = make_orderbook(mid_price=100.0, bid_qty=10, ask_qty=10)
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)

        assert isinstance(result, OrderBookAnalysis)
        assert result.can_enter is True

    def test_empty_book_allows_entry(self, default_config):
        obf = OrderBookFilter(default_config)
        book = OrderBook(symbol="TEST/USDT:USDT", timestamp=1700000000000,
                         bids=[], asks=[])
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)
        assert result.can_enter is True
        assert result.reason == "empty_orderbook"

    def test_score_adjustment_bounded(self, default_config):
        obf = OrderBookFilter(default_config)
        book = make_orderbook(mid_price=100.0, bid_qty=10, ask_qty=10)
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)
        assert -20 <= result.score_adjustment <= 20


# ────────────────────────────────────────────
# Wall detection
# ────────────────────────────────────────────

class TestWallDetection:

    def test_sell_wall_blocks_long(self, default_config):
        obf = OrderBookFilter(default_config)
        book = _make_wall_orderbook("sell_wall", mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)
        assert result.can_enter is False
        assert result.score_adjustment == -20
        assert "sell_wall" in result.reason

    def test_buy_wall_blocks_short(self, default_config):
        obf = OrderBookFilter(default_config)
        book = _make_wall_orderbook("buy_wall", mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.SHORT, 100.0, book)
        assert result.can_enter is False
        assert result.score_adjustment == -20
        assert "buy_wall" in result.reason

    def test_sell_wall_no_effect_on_short(self, default_config):
        obf = OrderBookFilter(default_config)
        book = _make_wall_orderbook("sell_wall", mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.SHORT, 100.0, book)
        # Sell wall should not block shorts
        # (may still fail on imbalance, but not wall)
        assert "sell_wall" not in result.reason


# ────────────────────────────────────────────
# Imbalance detection
# ────────────────────────────────────────────

class TestImbalanceDetection:

    def test_low_bid_ratio_blocks_long(self, default_config):
        """When bid_ratio very low, LONG should be blocked (wall or imbalance)."""
        obf = OrderBookFilter(default_config)
        book = _make_imbalanced_orderbook(bid_qty=2, ask_qty=100, mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)
        assert result.can_enter is False

    def test_low_ask_ratio_blocks_short(self, default_config):
        """When ask_ratio very low, SHORT should be blocked (wall or imbalance)."""
        obf = OrderBookFilter(default_config)
        book = _make_imbalanced_orderbook(bid_qty=100, ask_qty=2, mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.SHORT, 100.0, book)
        assert result.can_enter is False

    def test_strong_bid_boosts_long_score(self, default_config):
        """When bid_ratio > 0.60, LONG should get positive score adjustment."""
        obf = OrderBookFilter(default_config)
        book = _make_imbalanced_orderbook(bid_qty=80, ask_qty=20, mid_price=100.0)
        result = obf.analyze("TEST/USDT:USDT", Direction.LONG, 100.0, book)
        if result.can_enter:
            assert result.score_adjustment > 0
