import logging
from typing import List, Dict

from src.core.models import Kline, SymbolInfo, TrendScore
from src.core.enums import Direction
from src.indicators.trend import ema, adx, ema_alignment
from src.indicators.momentum import roc
from src.indicators.volatility import atrp
from src.indicators.volume import volume_ratio
from .correlation import CorrelationCalculator

logger = logging.getLogger(__name__)


class SymbolScreener:
    """三级筛选管道: 基础过滤 → 趋势评分 → 相关性去重"""

    def __init__(self, config: dict):
        self._config = config
        self._correlation = CorrelationCalculator()

    def level1_filter(self, symbols: List[SymbolInfo],
                      tickers: Dict[str, dict],
                      klines_dict: Dict[str, List[Kline]]) -> List[str]:
        """
        Level 1: 基础过滤 (150 → ~120)
        - 24h 成交额 >= 5M USDT
        - spread <= 0.15%
        - ATRP 0.8%-4.5%
        - 上线 >= 7 天
        - 不在黑名单
        """
        liq = self._config.get('liquidity_filter', {})
        vol_filter = self._config.get('volatility_filter', {})
        blacklist = self._config.get('symbols', {}).get('blacklist', [])
        min_listing = self._config.get('symbols', {}).get('min_listing_days', 7)

        passed = []
        for si in symbols:
            if si.base in blacklist or si.symbol in blacklist:
                continue
            if si.listing_days < min_listing:
                continue

            ticker = tickers.get(si.symbol, {})
            quote_vol = ticker.get('quoteVolume', 0) or 0
            if quote_vol < liq.get('min_24h_quote_volume', 5_000_000):
                continue

            bid = ticker.get('bid', 0) or 0
            ask = ticker.get('ask', 0) or 0
            if bid > 0:
                spread = (ask - bid) / bid
                if spread > liq.get('max_spread_pct', 0.15) / 100:
                    continue

            klines = klines_dict.get(si.symbol, [])
            if len(klines) >= 20:
                vol_pct = atrp(klines, vol_filter.get('atr_period', 14))
                min_pct = vol_filter.get('atrp_min_pct', 0.8) / 100
                max_pct = vol_filter.get('atrp_max_pct', 4.5) / 100
                if vol_pct < min_pct or vol_pct > max_pct:
                    continue

            passed.append(si.symbol)

        logger.info(f"Level 1: {len(symbols)} → {len(passed)}")
        return passed

    def level2_score(self, symbol: str, klines_1h: List[Kline]) -> TrendScore:
        """
        Level 2: 趋势评分
        EMA排列(30) + ADX强度(25) + 动量(20) + 量比(15) + 流动性(10)
        """
        weights = self._config.get('ranking', {}).get('weights', {})
        detail = {}

        # EMA 排列 (30分)
        align = ema_alignment(klines_1h)
        if align == 'bullish':
            detail['ema_alignment'] = weights.get('ema_alignment', 30)
        elif align == 'bearish':
            detail['ema_alignment'] = weights.get('ema_alignment', 30)
        else:
            detail['ema_alignment'] = weights.get('ema_alignment', 30) * 0.3

        # ADX 强度 (25分)
        adx_val = adx(klines_1h, 14)
        detail['adx_strength'] = min(adx_val / 40, 1.0) * weights.get('adx_strength', 25)

        # 动量 ROC20 (20分)
        roc_val = roc(klines_1h, 20)
        detail['momentum'] = min(abs(roc_val) / 0.05, 1.0) * weights.get('momentum', 20)

        # 量比 (15分)
        vol_r = volume_ratio(klines_1h)
        detail['volume_expansion'] = min(vol_r / 3.0, 1.0) * weights.get('volume_expansion', 15)

        # 流动性 (10分) - 简化为固定分
        detail['liquidity'] = weights.get('liquidity', 10) * 0.7

        total = sum(detail.values())
        direction = Direction.LONG if align == 'bullish' else (
            Direction.SHORT if align == 'bearish' else Direction.LONG)

        return TrendScore(
            symbol=symbol,
            total_score=total,
            direction=direction,
            detail=detail,
        )

    def level3_deduplicate(self, scored: List[TrendScore],
                           klines_dict: Dict[str, List[Kline]]) -> List[TrendScore]:
        """Level 3: 相关性去重"""
        max_corr = self._config.get('risk', {}).get('max_correlation', 0.75)
        self._correlation.update_matrix(klines_dict)

        # 按分数排序
        scored_sorted = sorted(scored, key=lambda x: x.total_score, reverse=True)
        symbols_with_scores = [
            (s.symbol, s.total_score, s.direction.value) for s in scored_sorted
        ]
        kept_symbols = self._correlation.filter_correlated(
            symbols_with_scores, max_corr)

        return [s for s in scored_sorted if s.symbol in kept_symbols]

    async def scan(self, symbols: List[SymbolInfo],
                   tickers: Dict[str, dict],
                   klines_dict: Dict[str, List[Kline]]) -> List[TrendScore]:
        """完整三级筛选"""
        # Level 1
        passed = self.level1_filter(symbols, tickers, klines_dict)

        # Level 2
        min_score = self._config.get('ranking', {}).get('min_score', 60)
        top_n = self._config.get('ranking', {}).get('top_n', 15)
        scored = []
        for sym in passed:
            klines = klines_dict.get(sym, [])
            if len(klines) < 50:
                continue
            ts = self.level2_score(sym, klines)
            if ts.total_score >= min_score:
                scored.append(ts)

        scored.sort(key=lambda x: x.total_score, reverse=True)
        scored = scored[:top_n]

        # Level 3
        filtered_klines = {s.symbol: klines_dict[s.symbol]
                           for s in scored if s.symbol in klines_dict}
        if filtered_klines:
            scored = self.level3_deduplicate(scored, filtered_klines)

        logger.info(f"Screener: {len(symbols)} → {len(passed)} → {len(scored)}")
        return scored
