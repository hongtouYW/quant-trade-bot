"""Signal engine - pullback and compression breakout detection"""
import logging
import numpy as np
from datetime import datetime, timedelta
from app.indicators import calc
from app.models.market import Signal
from app.config import get

SIGNAL_EXPIRY_MINUTES = 3  # Spec §11.3: max 3 root 1m candles

log = logging.getLogger(__name__)


class SignalEngine:
    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache

    def find_setup(self, symbol, direction, snapshot):
        """Find best setup for symbol in given direction"""
        df_15m = self.cache.get(symbol, '15m')
        df_1m = self.cache.get(symbol, '1m')

        if df_15m is None or len(df_15m) < 25:
            return None

        setup_a = self._detect_pullback(symbol, direction, df_15m, snapshot)
        setup_b = self._detect_compression_breakout(symbol, direction, df_15m, snapshot)

        setups = [s for s in [setup_a, setup_b] if s is not None]
        if not setups:
            log.debug(f"无信号: {symbol} dir={direction} pullback={'fail' if setup_a is None else 'ok'} breakout={'fail' if setup_b is None else 'ok'}")
            return None

        best = max(setups, key=lambda s: s.score)
        return best

    def _detect_pullback(self, symbol, direction, df_15m, snapshot):
        """Pattern A: Trend pullback entry"""
        if len(df_15m) < 25:
            return None

        close = df_15m['close'].iloc[-1]
        e7 = calc.ema(df_15m['close'], 7).iloc[-1]
        e21 = calc.ema(df_15m['close'], 21).iloc[-1]

        atr_val = calc.atr(df_15m, 14).iloc[-1]
        if np.isnan(atr_val) or atr_val <= 0:
            return None

        if direction == 1:  # Long pullback
            # Price should have pulled back near EMA7-EMA21 zone (widened ±2%)
            if not (close <= e21 * 1.02 and close >= e21 * 0.975):
                if not (close <= e7 * 1.01 and close >= e21 * 0.975):
                    return None

            # Volume should not spike excessively during pullback
            recent_vol = df_15m['volume'].iloc[-5:]
            prior_vol = df_15m['volume'].iloc[-10:-5]
            if prior_vol.mean() > 0 and recent_vol.mean() > prior_vol.mean() * 1.5:
                return None

            # Check for reversal candle (relaxed)
            last = df_15m.iloc[-1]
            prev = df_15m.iloc[-2]
            reversal = False

            # Bullish engulfing
            if last['close'] > last['open'] and last['close'] > prev['high']:
                reversal = True
            # Long lower shadow
            body = abs(last['close'] - last['open'])
            lower_shadow = min(last['open'], last['close']) - last['low']
            if body > 0 and lower_shadow > body * 1.2:
                reversal = True
            # Close back above EMA7
            if close > e7:
                reversal = True
            # Bullish candle near support
            if last['close'] > last['open']:
                reversal = True

            if not reversal:
                return None

            # Volume recovery (relaxed)
            if last['volume'] < df_15m['volume'].iloc[-5:].mean() * 0.5:
                return None

            stop_loss = min(df_15m['low'].iloc[-5:].min(), close - atr_val * get('execution', 'stop_atr_multiple', 1.2))
            stop_dist = close - stop_loss
            if stop_dist <= 0:
                return None

            r = stop_dist
            tp1 = close + r * get('execution', 'tp1_r_multiple', 1.5)
            tp2 = close + r * get('execution', 'tp2_r_multiple', 2.8)

            return Signal(
                symbol=symbol, direction=1, setup_type='pullback',
                score=snapshot.get('final_score', snapshot.get('score', 60)),
                entry_price=close, stop_loss=stop_loss,
                tp1=tp1, tp2=tp2,
                risk_reward=get('execution', 'tp1_r_multiple', 1.5),
                expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
            )

        elif direction == -1:  # Short pullback (mirror, widened zone)
            if not (close >= e21 * 0.975 and close <= e21 * 1.025):
                if not (close >= e7 * 0.99 and close <= e21 * 1.025):
                    return None

            recent_vol = df_15m['volume'].iloc[-5:]
            prior_vol = df_15m['volume'].iloc[-10:-5]
            if prior_vol.mean() > 0 and recent_vol.mean() > prior_vol.mean() * 1.5:
                return None

            last = df_15m.iloc[-1]
            prev = df_15m.iloc[-2]
            reversal = False

            if last['close'] < last['open'] and last['close'] < prev['low']:
                reversal = True
            body = abs(last['close'] - last['open'])
            upper_shadow = last['high'] - max(last['open'], last['close'])
            if body > 0 and upper_shadow > body * 1.2:
                reversal = True
            if close < e7:
                reversal = True
            # Bearish candle near resistance
            if last['close'] < last['open']:
                reversal = True

            if not reversal:
                return None

            if last['volume'] < df_15m['volume'].iloc[-5:].mean() * 0.5:
                return None

            stop_loss = max(df_15m['high'].iloc[-5:].max(), close + atr_val * get('execution', 'stop_atr_multiple', 1.2))
            stop_dist = stop_loss - close
            if stop_dist <= 0:
                return None

            r = stop_dist
            tp1 = close - r * get('execution', 'tp1_r_multiple', 1.5)
            tp2 = close - r * get('execution', 'tp2_r_multiple', 2.8)

            return Signal(
                symbol=symbol, direction=-1, setup_type='pullback',
                score=snapshot.get('final_score', snapshot.get('score', 60)),
                entry_price=close, stop_loss=stop_loss,
                tp1=tp1, tp2=tp2,
                risk_reward=get('execution', 'tp1_r_multiple', 1.5),
                expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
            )

        return None

    def _detect_compression_breakout(self, symbol, direction, df_15m, snapshot):
        """Pattern B: Compression breakout"""
        if len(df_15m) < 25:
            return None

        close = df_15m['close'].iloc[-1]
        atr_val = calc.atr(df_15m, 14).iloc[-1]
        if np.isnan(atr_val) or atr_val <= 0:
            return None

        comp = calc.compression_range(df_15m, 20)
        if comp > 4.0:
            return None  # Not compressed enough

        recent_20 = df_15m.iloc[-20:]
        high_20 = recent_20['high'].max()
        low_20 = recent_20['low'].min()
        avg_vol = recent_20['volume'].mean()
        curr_vol = df_15m['volume'].iloc[-1]

        if direction == 1:  # Long breakout
            if close <= high_20 * 0.999:
                return None  # Not breaking out
            if curr_vol < avg_vol * 1.0:
                return None  # Not enough volume

            stop_loss = max(low_20, close - atr_val * get('execution', 'stop_atr_multiple', 1.2))
            stop_dist = close - stop_loss
            if stop_dist <= 0:
                return None

            r = stop_dist
            tp1 = close + r * get('execution', 'tp1_r_multiple', 1.5)
            tp2 = close + r * get('execution', 'tp2_r_multiple', 2.8)

            return Signal(
                symbol=symbol, direction=1, setup_type='compression_breakout',
                score=snapshot.get('final_score', snapshot.get('score', 60)) * 0.95,
                entry_price=close, stop_loss=stop_loss,
                tp1=tp1, tp2=tp2,
                risk_reward=get('execution', 'tp1_r_multiple', 1.5),
                expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
            )

        elif direction == -1:  # Short breakout
            if close >= low_20 * 1.001:
                return None
            if curr_vol < avg_vol * 1.0:
                return None

            stop_loss = min(high_20, close + atr_val * get('execution', 'stop_atr_multiple', 1.2))
            stop_dist = stop_loss - close
            if stop_dist <= 0:
                return None

            r = stop_dist
            tp1 = close - r * get('execution', 'tp1_r_multiple', 1.5)
            tp2 = close - r * get('execution', 'tp2_r_multiple', 2.8)

            return Signal(
                symbol=symbol, direction=-1, setup_type='compression_breakout',
                score=snapshot.get('final_score', snapshot.get('score', 60)) * 0.95,
                entry_price=close, stop_loss=stop_loss,
                tp1=tp1, tp2=tp2,
                risk_reward=get('execution', 'tp1_r_multiple', 1.5),
                expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
            )

        return None


class EntryRefiner:
    """1m precision entry confirmation"""

    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache

    def confirm(self, signal):
        """Confirm signal on 1m timeframe. Returns refined signal or None."""
        # Check signal expiry (Spec §11.3)
        if signal.expires_at and datetime.utcnow() > signal.expires_at:
            log.info(f"信号过期: {signal.symbol} {signal.setup_type}")
            return None

        df_1m = self.cache.get(signal.symbol, '1m')
        if df_1m is None or len(df_1m) < 15:
            return signal  # Can't refine, pass through

        confirmed_count = 0
        close = df_1m['close'].iloc[-1]
        e9 = calc.ema(df_1m['close'], 9).iloc[-1]

        if signal.direction == 1:
            # 1m breakout of recent 5-bar high
            recent_high = df_1m['high'].iloc[-6:-1].max()
            if close > recent_high:
                confirmed_count += 1
            # Close above EMA9
            if close > e9:
                confirmed_count += 1
            # Volume surge
            avg_vol = df_1m['volume'].iloc[-10:].mean()
            if avg_vol > 0 and df_1m['volume'].iloc[-1] > avg_vol * 1.2:
                confirmed_count += 1
            # Higher low structure
            if len(df_1m) >= 4:
                lows = df_1m['low'].iloc[-4:]
                if lows.iloc[-1] > lows.iloc[-3]:
                    confirmed_count += 1

        elif signal.direction == -1:
            recent_low = df_1m['low'].iloc[-6:-1].min()
            if close < recent_low:
                confirmed_count += 1
            if close < e9:
                confirmed_count += 1
            avg_vol = df_1m['volume'].iloc[-10:].mean()
            if avg_vol > 0 and df_1m['volume'].iloc[-1] > avg_vol * 1.2:
                confirmed_count += 1
            if len(df_1m) >= 4:
                highs = df_1m['high'].iloc[-4:]
                if highs.iloc[-1] < highs.iloc[-3]:
                    confirmed_count += 1

        if confirmed_count < 1:
            return None  # Not confirmed

        # Check drift
        atr_15m = calc.atr(self.cache.get(signal.symbol, '15m'), 14)
        if len(atr_15m) > 0:
            drift = abs(close - signal.entry_price)
            max_drift = atr_15m.iloc[-1] * get('execution', 'max_entry_drift_atr', 0.35)
            if drift > max_drift:
                return None  # Drifted too far

        # Refine entry price to current 1m close
        signal.entry_price = close
        return signal


class FakeBreakoutFilter:
    """假突破过滤器 - 5项检查"""

    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache

    def reject(self, signal):
        """返回 True 表示疑似假突破，应放弃"""
        df_15m = self.cache.get(signal.symbol, '15m')
        df_1m = self.cache.get(signal.symbol, '1m')
        df_1h = self.cache.get(signal.symbol, '1h')
        if df_15m is None or len(df_15m) < 10:
            return False

        reject_count = 0
        last = df_15m.iloc[-1]
        body = abs(last['close'] - last['open'])
        total = last['high'] - last['low']

        if signal.direction == 1:
            # 条件1: 突破K线上影过长，收盘不够强
            upper_shadow = last['high'] - max(last['open'], last['close'])
            if total > 0 and upper_shadow > body * 2:
                reject_count += 1
                log.info(f"假突破: {signal.symbol} 做多 - 上影线过长")

            # 条件2: 突破时量能不足
            avg_vol = df_15m['volume'].iloc[-10:].mean()
            if avg_vol > 0 and last['volume'] < avg_vol * 0.8:
                reject_count += 1

            # 条件3: 突破后1m立刻回到区间内
            if df_1m is not None and len(df_1m) >= 3:
                recent_high_15m = df_15m['high'].iloc[-3:-1].max()
                if df_1m['close'].iloc[-1] < recent_high_15m * 0.998:
                    reject_count += 1

            # 条件4: 15m突破位离1H关键阻力太近
            if df_1h is not None and len(df_1h) >= 20:
                h1_high = df_1h['high'].iloc[-20:].max()
                dist_to_resistance = abs(h1_high - last['close']) / last['close'] * 100
                if dist_to_resistance < 0.3:  # 距阻力不到0.3%
                    reject_count += 1

            # 条件5: ATR已显著放大，可能晚于最佳点
            atrp_vals = calc.atrp(df_15m, 14)
            if len(atrp_vals) >= 20:
                curr = atrp_vals.iloc[-1]
                avg = atrp_vals.iloc[-20:].mean()
                if not np.isnan(avg) and avg > 0 and curr > avg * 2.5:
                    reject_count += 1

        elif signal.direction == -1:
            # 镜像检查
            lower_shadow = min(last['open'], last['close']) - last['low']
            if total > 0 and lower_shadow > body * 2:
                reject_count += 1
                log.info(f"假突破: {signal.symbol} 做空 - 下影线过长")

            avg_vol = df_15m['volume'].iloc[-10:].mean()
            if avg_vol > 0 and last['volume'] < avg_vol * 0.8:
                reject_count += 1

            if df_1m is not None and len(df_1m) >= 3:
                recent_low_15m = df_15m['low'].iloc[-3:-1].min()
                if df_1m['close'].iloc[-1] > recent_low_15m * 1.002:
                    reject_count += 1

            if df_1h is not None and len(df_1h) >= 20:
                h1_low = df_1h['low'].iloc[-20:].min()
                dist_to_support = abs(last['close'] - h1_low) / last['close'] * 100
                if dist_to_support < 0.3:
                    reject_count += 1

            atrp_vals = calc.atrp(df_15m, 14)
            if len(atrp_vals) >= 20:
                curr = atrp_vals.iloc[-1]
                avg = atrp_vals.iloc[-20:].mean()
                if not np.isnan(avg) and avg > 0 and curr > avg * 2.5:
                    reject_count += 1

        # 命中3项以上就判定为假突破
        return reject_count >= 3
