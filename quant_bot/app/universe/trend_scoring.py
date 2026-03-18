"""Dual-layer trend scoring: momentum (0-60) + quality (0-40)"""
import logging
import numpy as np
from app.indicators import calc
from app.config import get

log = logging.getLogger(__name__)


class TrendScoring:
    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache

    def score_symbol(self, symbol):
        """Return (momentum_score, quality_score, final_score, direction, regime)"""
        df_5m = self.cache.get(symbol, '5m')
        df_15m = self.cache.get(symbol, '15m')
        df_1h = self.cache.get(symbol, '1h')

        if df_1h is None or len(df_1h) < 50:
            return 0, 0, 0, 0, 'UNKNOWN'
        if df_15m is None or len(df_15m) < 20:
            return 0, 0, 0, 0, 'UNKNOWN'
        if df_5m is None or len(df_5m) < 10:
            return 0, 0, 0, 0, 'UNKNOWN'

        m_score = self._momentum_score(df_5m, df_1h)
        q_score = self._quality_score(df_15m, df_1h)
        final = m_score + q_score
        direction = self._direction(df_1h)
        regime = self._regime(df_15m, df_1h)

        return m_score, q_score, final, direction, regime

    def _momentum_score(self, df_5m, df_1h):
        """Layer 1: Momentum score 0-60"""
        score = 0

        # 5m ROC clarity (0-15)
        roc_vals = calc.roc(df_5m['close'], 6)
        if len(roc_vals) >= 6:
            recent_roc = roc_vals.iloc[-6:]
            consistency = (recent_roc > 0).sum() / 6 if recent_roc.iloc[-1] > 0 else (recent_roc < 0).sum() / 6
            score += min(15, consistency * 15)

        # EMA 7/21/55 alignment (0-15)
        if len(df_5m) >= 55:
            e7 = calc.ema(df_5m['close'], 7).iloc[-1]
            e21 = calc.ema(df_5m['close'], 21).iloc[-1]
            e55 = calc.ema(df_5m['close'], 55).iloc[-1]
            if e7 > e21 > e55 or e7 < e21 < e55:
                score += 15
            elif (e7 > e21 and e21 > e55 * 0.998) or (e7 < e21 and e21 < e55 * 1.002):
                score += 8

        # Volume surge (0-10)
        vr = calc.volume_ratio(df_5m, 20)
        if len(vr) > 0:
            curr_vr = vr.iloc[-1]
            if curr_vr > 2.0:
                score += 10
            elif curr_vr > 1.5:
                score += 7
            elif curr_vr > 1.2:
                score += 4

        # ATRP in sweet spot (0-10)
        atrp_val = calc.atrp(df_1h, 14)
        if len(atrp_val) > 0:
            a = atrp_val.iloc[-1]
            if 0.45 <= a <= 2.5:
                score += 10
            elif 0.25 <= a <= 4.5:
                score += 5

        # Taker direction alignment (0-10) - approximate with volume+price
        if len(df_5m) >= 3:
            last3 = df_5m.iloc[-3:]
            bullish_vol = last3[last3['close'] > last3['open']]['volume'].sum()
            bearish_vol = last3[last3['close'] <= last3['open']]['volume'].sum()
            total = bullish_vol + bearish_vol
            if total > 0:
                ratio = max(bullish_vol, bearish_vol) / total
                score += min(10, ratio * 12)

        return min(60, score)

    def _quality_score(self, df_15m, df_1h):
        """Layer 2: Trend quality score 0-40"""
        score = 0

        # 1H EMA 20/50/200 alignment (0-15)
        if len(df_1h) >= 200:
            e20 = calc.ema(df_1h['close'], 20).iloc[-1]
            e50 = calc.ema(df_1h['close'], 50).iloc[-1]
            e200 = calc.ema(df_1h['close'], 200).iloc[-1]
            if e20 > e50 > e200 or e20 < e50 < e200:
                score += 15
            elif abs(e20 - e50) / e50 < 0.005:
                score += 5
        elif len(df_1h) >= 50:
            e20 = calc.ema(df_1h['close'], 20).iloc[-1]
            e50 = calc.ema(df_1h['close'], 50).iloc[-1]
            if (e20 > e50) or (e20 < e50):
                score += 8

        # 1H ADX (0-10)
        adx_vals, _, _ = calc.adx(df_1h, 14)
        if len(adx_vals) > 0:
            curr_adx = adx_vals.iloc[-1]
            if not np.isnan(curr_adx):
                if curr_adx > 30:
                    score += 10
                elif curr_adx > 22:
                    score += 7
                elif curr_adx > 18:
                    score += 3

        # Distance from EMA20 (0-5)
        if len(df_1h) >= 20:
            e20 = calc.ema(df_1h['close'], 20).iloc[-1]
            price = df_1h['close'].iloc[-1]
            dist_pct = abs(price - e20) / e20 * 100 if e20 > 0 else 0
            if 0.3 <= dist_pct <= 2.0:
                score += 5
            elif dist_pct < 0.3:
                score += 2  # too close

        # 15m pullback recovery (0-5)
        if len(df_15m) >= 8:
            hl_trend = calc.recent_highs_lows(df_15m, 3)
            if hl_trend != 0:
                score += 5

        # Volatility stability (0-5) - fewer wicky candles = better
        if len(df_15m) >= 12:
            wicky = calc.count_wicky_candles(df_15m, 12, 0.65)
            if wicky <= 1:
                score += 5
            elif wicky <= 3:
                score += 3

        return min(40, score)

    def _direction(self, df_1h):
        """Determine trade direction from 1H structure"""
        if len(df_1h) < 50:
            return 0

        close = df_1h['close'].iloc[-1]
        e20 = calc.ema(df_1h['close'], 20).iloc[-1]
        e50 = calc.ema(df_1h['close'], 50).iloc[-1]

        adx_vals, _, _ = calc.adx(df_1h, 14)
        curr_adx = adx_vals.iloc[-1] if len(adx_vals) > 0 and not np.isnan(adx_vals.iloc[-1]) else 0
        adx_min = get('trend_filter', 'adx_min', 22)

        e20_series = calc.ema(df_1h['close'], 20)
        slope = calc.ema_slope(e20_series, 3)
        hl = calc.recent_highs_lows(df_1h, 3)

        has_e200 = len(df_1h) >= 200
        e200 = calc.ema(df_1h['close'], 200).iloc[-1] if has_e200 else None

        # 做多条件
        ema_long = close > e20 > e50
        if has_e200:
            ema_long = ema_long and e50 > e200
        if ema_long and curr_adx >= adx_min and slope > 0:
            return 1

        # 做空条件
        ema_short = close < e20 < e50
        if has_e200:
            ema_short = ema_short and e50 < e200
        if ema_short and curr_adx >= adx_min and slope < 0:
            return -1

        return 0

    def _regime(self, df_15m, df_1h):
        """Market regime: TRENDING / RANGING / EXTREME"""
        if len(df_1h) < 20 or len(df_15m) < 20:
            return 'UNKNOWN'

        adx_vals, _, _ = calc.adx(df_1h, 14)
        curr_adx = adx_vals.iloc[-1] if len(adx_vals) > 0 and not np.isnan(adx_vals.iloc[-1]) else 0

        # EXTREME check
        atrp_vals = calc.atrp(df_15m, 14)
        if len(atrp_vals) >= 3:
            recent_atrp = atrp_vals.iloc[-3:]
            mean_atrp = atrp_vals.iloc[-20:].mean() if len(atrp_vals) >= 20 else atrp_vals.mean()
            if not np.isnan(mean_atrp) and mean_atrp > 0:
                if recent_atrp.max() > mean_atrp * 3:
                    return 'EXTREME'

        # Count extreme candles in last 3 bars of 15m
        if len(df_15m) >= 3:
            last3 = df_15m.iloc[-3:]
            big_body_count = 0
            avg_body = (df_15m['close'] - df_15m['open']).abs().iloc[-20:].mean()
            for _, row in last3.iterrows():
                body = abs(row['close'] - row['open'])
                if avg_body > 0 and body > avg_body * 3:
                    big_body_count += 1
            if big_body_count >= 2:
                return 'EXTREME'

        # TRENDING
        if curr_adx >= 22:
            e20 = calc.ema(df_1h['close'], 20).iloc[-1]
            e50 = calc.ema(df_1h['close'], 50).iloc[-1]
            price = df_1h['close'].iloc[-1]
            if (e20 > e50 and price > e20) or (e20 < e50 and price < e20):
                return 'TRENDING'

        # RANGING
        if curr_adx < 18:
            comp = calc.compression_range(df_15m, 20)
            if comp < 3:
                return 'RANGING'

        if curr_adx >= 22:
            return 'TRENDING'

        return 'RANGING'

    def grade(self, final_score):
        if final_score >= get('trend_filter', 'score_min_a_grade', 78):
            return 'A'
        elif final_score >= 70:
            return 'B'
        elif final_score >= get('trend_filter', 'score_min_trade', 62):
            return 'C'
        return 'D'
