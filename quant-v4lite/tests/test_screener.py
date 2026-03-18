"""Tests for SymbolScreener three-level pipeline."""

import pytest
from datetime import datetime, timedelta
from unittest.mock import patch, MagicMock

from src.core.models import Kline, SymbolInfo, TrendScore
from src.core.enums import Direction
from src.analysis.screener import SymbolScreener

from conftest import generate_klines, generate_trending_klines


# ────────────────────────────────────────────
# Helpers
# ────────────────────────────────────────────

def _make_symbol_info(symbol: str, base: str, listing_days: int = 30) -> SymbolInfo:
    listing_date = datetime.utcnow() - timedelta(days=listing_days)
    return SymbolInfo(
        symbol=symbol,
        base=base,
        quote="USDT",
        price_precision=2,
        qty_precision=3,
        min_qty=0.001,
        min_notional=5.0,
        tick_size=0.01,
        listing_date=listing_date,
    )


def _make_ticker(quote_volume: float = 10_000_000,
                 bid: float = 100.0, ask: float = 100.01) -> dict:
    return {"quoteVolume": quote_volume, "bid": bid, "ask": ask}


# ────────────────────────────────────────────
# Level 1 Filter
# ────────────────────────────────────────────

class TestLevel1Filter:

    def test_passes_valid_symbol(self, default_config):
        # Widen ATRP filter for synthetic data
        default_config["volatility_filter"]["atrp_min_pct"] = 0.0
        default_config["volatility_filter"]["atrp_max_pct"] = 100.0
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("BTC/USDT:USDT", "BTC")]
        tickers = {"BTC/USDT:USDT": _make_ticker()}
        klines_dict = {"BTC/USDT:USDT": generate_trending_klines(50, direction="up", start_price=100.0)}

        result = screener.level1_filter(symbols, tickers, klines_dict)
        assert "BTC/USDT:USDT" in result

    def test_filters_low_volume(self, default_config):
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("LOWVOL/USDT:USDT", "LOWVOL")]
        tickers = {"LOWVOL/USDT:USDT": _make_ticker(quote_volume=100_000)}
        klines_dict = {"LOWVOL/USDT:USDT": generate_klines(50)}

        result = screener.level1_filter(symbols, tickers, klines_dict)
        assert len(result) == 0

    def test_filters_blacklisted(self, default_config):
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("LUNA/USDT:USDT", "LUNA")]
        tickers = {"LUNA/USDT:USDT": _make_ticker()}
        klines_dict = {"LUNA/USDT:USDT": generate_klines(50)}

        result = screener.level1_filter(symbols, tickers, klines_dict)
        assert len(result) == 0

    def test_filters_new_listing(self, default_config):
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("NEW/USDT:USDT", "NEW", listing_days=3)]
        tickers = {"NEW/USDT:USDT": _make_ticker()}
        klines_dict = {"NEW/USDT:USDT": generate_klines(50)}

        result = screener.level1_filter(symbols, tickers, klines_dict)
        assert len(result) == 0

    def test_filters_high_spread(self, default_config):
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("SPREAD/USDT:USDT", "SPREAD")]
        tickers = {"SPREAD/USDT:USDT": _make_ticker(bid=100.0, ask=101.0)}
        klines_dict = {"SPREAD/USDT:USDT": generate_klines(50)}

        result = screener.level1_filter(symbols, tickers, klines_dict)
        assert len(result) == 0


# ────────────────────────────────────────────
# Level 2 Scoring
# ────────────────────────────────────────────

class TestLevel2Score:

    def test_returns_trend_score(self, default_config):
        screener = SymbolScreener(default_config)
        klines = generate_trending_klines(250, direction="up")
        result = screener.level2_score("BTC/USDT:USDT", klines)

        assert isinstance(result, TrendScore)
        assert result.symbol == "BTC/USDT:USDT"
        assert result.total_score > 0

    def test_trending_scores_higher_than_ranging(self, default_config):
        from conftest import generate_ranging_klines
        screener = SymbolScreener(default_config)

        trending = generate_trending_klines(250, direction="up")
        ranging = generate_klines(250, trend=0, volatility=0.05)

        t_score = screener.level2_score("TREND/USDT:USDT", trending)
        r_score = screener.level2_score("RANGE/USDT:USDT", ranging)
        assert t_score.total_score > r_score.total_score

    def test_bullish_direction(self, default_config):
        screener = SymbolScreener(default_config)
        klines = generate_trending_klines(250, direction="up")
        result = screener.level2_score("UP/USDT:USDT", klines)
        assert result.direction == Direction.LONG

    def test_bearish_direction(self, default_config):
        screener = SymbolScreener(default_config)
        klines = generate_trending_klines(250, direction="down")
        result = screener.level2_score("DOWN/USDT:USDT", klines)
        assert result.direction == Direction.SHORT


# ────────────────────────────────────────────
# Full scan (async)
# ────────────────────────────────────────────

class TestScan:

    @pytest.mark.asyncio
    async def test_scan_returns_list(self, default_config):
        # Disable ATRP filter to focus on scan logic
        default_config["volatility_filter"]["atrp_min_pct"] = 0.0
        default_config["volatility_filter"]["atrp_max_pct"] = 100.0
        default_config["ranking"]["min_score"] = 0
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("BTC/USDT:USDT", "BTC")]
        tickers = {"BTC/USDT:USDT": _make_ticker()}
        klines = generate_trending_klines(250, direction="up")
        klines_dict = {"BTC/USDT:USDT": klines}

        with patch.object(screener, 'level3_deduplicate', side_effect=lambda s, k: s):
            result = await screener.scan(symbols, tickers, klines_dict)

        assert isinstance(result, list)

    @pytest.mark.asyncio
    async def test_scan_filters_low_score(self, default_config):
        default_config["ranking"]["min_score"] = 999
        default_config["volatility_filter"]["atrp_min_pct"] = 0.0
        default_config["volatility_filter"]["atrp_max_pct"] = 100.0
        screener = SymbolScreener(default_config)
        symbols = [_make_symbol_info("BTC/USDT:USDT", "BTC")]
        tickers = {"BTC/USDT:USDT": _make_ticker()}
        klines_dict = {"BTC/USDT:USDT": generate_klines(250)}

        with patch.object(screener, 'level3_deduplicate', side_effect=lambda s, k: s):
            result = await screener.scan(symbols, tickers, klines_dict)

        assert len(result) == 0
