"""均值回归策略 - Phase 2 (Spec §29 扩展)
在RANGING市场中检测超买超卖，价格触及布林带极值时反向入场。
"""
import logging
import numpy as np
from datetime import datetime, timedelta
from app.indicators import calc
from app.models.market import Signal
from app.config import get

SIGNAL_EXPIRY_MINUTES = 3

log = logging.getLogger(__name__)


class MeanReversionEngine:
    """均值回归信号引擎 - 仅在RANGING市场启用"""

    def __init__(self, ohlcv_cache):
        self.cache = ohlcv_cache
        # 策略参数
        self.bb_period = 20
        self.bb_std = 2.0
        self.rsi_period = 14
        self.rsi_oversold = 30
        self.rsi_overbought = 70
        self.adx_max = 25  # ADX低于此值才适合均值回归
        self.min_bb_width_pct = 1.0  # BB带宽最小1%，太窄不做
        self.max_bb_width_pct = 8.0  # BB带宽最大8%，太宽说明趋势中

    def find_setup(self, symbol, snapshot):
        """在RANGING市场中寻找均值回归信号"""
        regime = snapshot.get('regime', 'UNKNOWN')
        if regime != 'RANGING':
            return None

        df_15m = self.cache.get(symbol, '15m')
        if df_15m is None or len(df_15m) < 30:
            return None

        close = df_15m['close'].iloc[-1]

        # ADX检查 - 低ADX才适合均值回归
        adx_vals, _, _ = calc.adx(df_15m, self.rsi_period)
        curr_adx = adx_vals.iloc[-1] if len(adx_vals) > 0 and not np.isnan(adx_vals.iloc[-1]) else 30
        if curr_adx > self.adx_max:
            return None  # ADX太高，是趋势市场

        # 布林带
        upper, mid, lower = calc.bollinger_bands(df_15m['close'], self.bb_period, self.bb_std)
        curr_upper = upper.iloc[-1]
        curr_mid = mid.iloc[-1]
        curr_lower = lower.iloc[-1]

        if np.isnan(curr_upper) or np.isnan(curr_lower) or np.isnan(curr_mid):
            return None

        # BB带宽检查
        bb_width_pct = (curr_upper - curr_lower) / curr_mid * 100
        if bb_width_pct < self.min_bb_width_pct or bb_width_pct > self.max_bb_width_pct:
            return None

        # RSI
        rsi_vals = calc.rsi(df_15m['close'], self.rsi_period)
        curr_rsi = rsi_vals.iloc[-1] if len(rsi_vals) > 0 else 50
        if np.isnan(curr_rsi):
            return None

        # ATR for stop loss
        atr_val = calc.atr(df_15m, 14).iloc[-1]
        if np.isnan(atr_val) or atr_val <= 0:
            return None

        setup = None

        # === 做多: 超卖 + 价格触及下轨 ===
        if curr_rsi < self.rsi_oversold and close <= curr_lower * 1.002:
            # 确认: 最后一根K线出现反转信号
            last = df_15m.iloc[-1]
            if last['close'] > last['open']:  # 收阳
                stop_loss = close - atr_val * 1.5
                r = close - stop_loss
                if r > 0:
                    # TP目标: 中轨 (保守)
                    tp1 = curr_mid
                    tp2 = curr_upper * 0.98  # 接近上轨但不到
                    tp1_r = (tp1 - close) / r
                    if tp1_r >= 1.0:  # 至少1R
                        setup = Signal(
                            symbol=symbol, direction=1,
                            setup_type='mean_reversion',
                            score=snapshot.get('final_score', 60) * 0.9,
                            entry_price=close,
                            stop_loss=stop_loss,
                            tp1=tp1, tp2=tp2,
                            risk_reward=tp1_r,
                            expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
                        )
                        log.info(f"均值回归做多: {symbol} RSI={curr_rsi:.1f} BB%={(close-curr_lower)/(curr_upper-curr_lower)*100:.1f}%")

        # === 做空: 超买 + 价格触及上轨 ===
        elif curr_rsi > self.rsi_overbought and close >= curr_upper * 0.998:
            last = df_15m.iloc[-1]
            if last['close'] < last['open']:  # 收阴
                stop_loss = close + atr_val * 1.5
                r = stop_loss - close
                if r > 0:
                    tp1 = curr_mid
                    tp2 = curr_lower * 1.02
                    tp1_r = (close - tp1) / r
                    if tp1_r >= 1.0:
                        setup = Signal(
                            symbol=symbol, direction=-1,
                            setup_type='mean_reversion',
                            score=snapshot.get('final_score', 60) * 0.9,
                            entry_price=close,
                            stop_loss=stop_loss,
                            tp1=tp1, tp2=tp2,
                            risk_reward=tp1_r,
                            expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
                        )
                        log.info(f"均值回归做空: {symbol} RSI={curr_rsi:.1f} BB%={(close-curr_lower)/(curr_upper-curr_lower)*100:.1f}%")

        return setup
