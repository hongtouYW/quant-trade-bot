"""Tests for SmartOrderRouter."""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch

from src.core.models import OrderBook, OrderBookLevel
from src.core.enums import FillType
from src.exchange.smart_router import SmartOrderRouter

from conftest import make_orderbook


# ────────────────────────────────────────────
# Mock exchange
# ────────────────────────────────────────────

def _make_mock_exchange():
    """Create a mock ExchangeClient."""
    exchange = AsyncMock()

    exchange.fetch_orderbook.return_value = make_orderbook(
        mid_price=100.0, bid_qty=10, ask_qty=10
    )

    exchange.place_market_order.return_value = {
        "id": "mkt_001",
        "status": "closed",
        "price": 100.0,
        "filled": 1.0,
    }

    exchange.place_limit_order.return_value = {
        "id": "lmt_001",
        "status": "open",
        "price": 99.99,
    }

    exchange.get_order_status.return_value = {
        "id": "lmt_001",
        "status": "closed",
        "price": 99.99,
        "filled": 1.0,
    }

    exchange.cancel_order.return_value = True

    return exchange


# ────────────────────────────────────────────
# Disabled / urgent → market order
# ────────────────────────────────────────────

class TestMarketFallback:

    @pytest.mark.asyncio
    async def test_disabled_uses_market(self, default_config):
        default_config["smart_order"]["enabled"] = False
        exchange = _make_mock_exchange()
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_entry("BTC/USDT:USDT", "buy", 1.0)

        assert fill_type == FillType.MARKET
        exchange.place_market_order.assert_awaited_once()

    @pytest.mark.asyncio
    async def test_urgent_uses_market(self, default_config):
        exchange = _make_mock_exchange()
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_entry(
            "BTC/USDT:USDT", "buy", 1.0, urgency="stop_loss"
        )

        assert fill_type == FillType.MARKET
        exchange.place_market_order.assert_awaited_once()


# ────────────────────────────────────────────
# Maker success path
# ────────────────────────────────────────────

class TestMakerPath:

    @pytest.mark.asyncio
    async def test_maker_fill_returns_maker_type(self, default_config):
        exchange = _make_mock_exchange()
        # Maker order fills immediately
        exchange.get_order_status.return_value = {
            "id": "lmt_001", "status": "closed", "price": 99.99, "filled": 1.0,
        }
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_entry("BTC/USDT:USDT", "buy", 1.0)

        assert fill_type == FillType.MAKER
        assert result["status"] == "closed"

    @pytest.mark.asyncio
    async def test_maker_fail_falls_to_chase(self, default_config):
        exchange = _make_mock_exchange()
        # Maker order not filled
        exchange.get_order_status.side_effect = [
            {"id": "lmt_001", "status": "open"},  # maker check
            {"id": "lmt_002", "status": "closed", "price": 100.01, "filled": 1.0},  # chase check
        ]
        exchange.place_limit_order.side_effect = [
            {"id": "lmt_001"},  # maker
            {"id": "lmt_002"},  # chase
        ]
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_entry("BTC/USDT:USDT", "buy", 1.0)

        assert fill_type == FillType.CHASE
        assert exchange.cancel_order.await_count >= 1


# ────────────────────────────────────────────
# Chase → Market fallback
# ────────────────────────────────────────────

class TestChaseFallback:

    @pytest.mark.asyncio
    async def test_chase_fail_falls_to_market(self, default_config):
        exchange = _make_mock_exchange()
        # Both maker and chase not filled
        exchange.get_order_status.return_value = {"id": "lmt_001", "status": "open"}
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_entry("BTC/USDT:USDT", "buy", 1.0)

        assert fill_type == FillType.MARKET
        exchange.place_market_order.assert_awaited_once()


# ────────────────────────────────────────────
# Exit orders
# ────────────────────────────────────────────

class TestExitOrders:

    @pytest.mark.asyncio
    async def test_stop_loss_exit_uses_market(self, default_config):
        exchange = _make_mock_exchange()
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_exit(
            "BTC/USDT:USDT", "sell", 1.0, reason="stop_loss"
        )

        assert fill_type == FillType.MARKET
        exchange.place_market_order.assert_awaited_once()

    @pytest.mark.asyncio
    async def test_tp_exit_tries_maker(self, default_config):
        exchange = _make_mock_exchange()
        # Maker fills on TP exit
        exchange.get_order_status.return_value = {
            "id": "lmt_001", "status": "closed", "price": 110.0, "filled": 1.0,
        }
        router = SmartOrderRouter(exchange, default_config)

        result, fill_type = await router.execute_exit(
            "BTC/USDT:USDT", "sell", 1.0, reason="tp"
        )

        assert fill_type == FillType.MAKER


# ────────────────────────────────────────────
# Stats tracking
# ────────────────────────────────────────────

class TestStats:

    @pytest.mark.asyncio
    async def test_stats_count_increments(self, default_config):
        default_config["smart_order"]["enabled"] = False
        exchange = _make_mock_exchange()
        router = SmartOrderRouter(exchange, default_config)

        await router.execute_entry("BTC/USDT:USDT", "buy", 1.0)
        await router.execute_entry("ETH/USDT:USDT", "buy", 2.0)

        stats = router.stats
        assert stats["market"] == 2
        assert "maker_rate" in stats
