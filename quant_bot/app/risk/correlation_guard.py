"""Correlation guard - prevent highly correlated positions"""
import logging
import numpy as np
from app.config import get

log = logging.getLogger(__name__)


class CorrelationGuard:
    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache
        self._corr_cfg = get('correlation')
        self._corr_matrix = {}  # {(sym1, sym2): correlation}

    def block(self, symbol, direction, active_positions):
        """Check if new position would be too correlated with existing ones"""
        if not self._corr_cfg.get('enable', True):
            return False

        max_corr = self._corr_cfg.get('max_same_dir_corr', 0.80)

        for pos in active_positions:
            if pos.direction != direction:
                continue

            corr = self._get_correlation(symbol, pos.symbol)
            if corr is not None and corr > max_corr:
                log.info(f"Correlation block: {symbol} vs {pos.symbol} = {corr:.2f}")
                return True

        return False

    def _get_correlation(self, sym1, sym2):
        """Calculate rolling correlation between two symbols"""
        key = tuple(sorted([sym1, sym2]))
        if key in self._corr_matrix:
            return self._corr_matrix[key]

        window = self._corr_cfg.get('window_bars', 72)
        df1 = self.cache.get(sym1, '15m')
        df2 = self.cache.get(sym2, '15m')

        if df1 is None or df2 is None or len(df1) < window or len(df2) < window:
            return None

        returns1 = df1['close'].pct_change().iloc[-window:]
        returns2 = df2['close'].pct_change().iloc[-window:]

        # Align by length
        min_len = min(len(returns1), len(returns2))
        r1 = returns1.iloc[-min_len:].values
        r2 = returns2.iloc[-min_len:].values

        mask = ~(np.isnan(r1) | np.isnan(r2))
        if mask.sum() < 20:
            return None

        corr = np.corrcoef(r1[mask], r2[mask])[0, 1]
        self._corr_matrix[key] = corr
        return corr

    def clear_cache(self):
        self._corr_matrix.clear()
