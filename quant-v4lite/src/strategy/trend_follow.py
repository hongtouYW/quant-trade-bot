import logging
from typing import List, Optional

from src.core.models import Kline, Signal
from src.core.enums import Direction, SignalStrategy
from src.indicators.trend import ema, adx, ema_alignment
from src.indicators.volatility import atr
from src.indicators.volume import volume_ratio
from .base import BaseStrategy

logger = logging.getLogger(__name__)


class TrendFollowStrategy(BaseStrategy):
    """
    趋势跟踪策略
    1H: EMA 多头/空头排列 + ADX > 25
    15m: 回踩 EMA20 + 放量确认
    """

    name = "trend_follow"

    def check_signal(self, symbol: str,
                     klines_1h: List[Kline],
                     klines_15m: List[Kline],
                     direction: Direction,
                     config: dict) -> Optional[Signal]:

        if len(klines_1h) < 200 or len(klines_15m) < 30:
            return None

        exec_cfg = config.get('execution', {})
        trend_cfg = config.get('trend_filter', {})

        # 1H 过滤: EMA 排列 + ADX
        align = ema_alignment(klines_1h,
                              trend_cfg.get('ema_fast', 20),
                              trend_cfg.get('ema_mid', 50),
                              trend_cfg.get('ema_slow', 200))

        if direction == Direction.LONG and align != 'bullish':
            logger.debug(f"[TF] {symbol} {direction.name}: EMA align={align}, need bullish")
            return None
        if direction == Direction.SHORT and align != 'bearish':
            logger.debug(f"[TF] {symbol} {direction.name}: EMA align={align}, need bearish")
            return None

        adx_val = adx(klines_1h, trend_cfg.get('adx_period', 14))
        if adx_val < trend_cfg.get('adx_strong', 25):
            logger.debug(f"[TF] {symbol}: ADX={adx_val:.1f} < 25")
            return None

        logger.info(f"[TF] {symbol} {direction.name}: EMA={align} ADX={adx_val:.1f} ✓ checking 15m...")

        # 15m 入场条件
        ema20_15m = ema(klines_15m, 20)
        prev_bar = klines_15m[-2]
        curr_bar = klines_15m[-1]
        ema20_prev = ema20_15m[-2]
        ema20_curr = ema20_15m[-1]

        if direction == Direction.LONG:
            # 前一根低点接近 EMA20 (< 0.3%)
            touch_dist = abs(prev_bar.low - ema20_prev) / ema20_prev
            if touch_dist > 0.008:
                logger.info(f"[TF] {symbol} LONG 15m ✗ pullback dist={touch_dist:.4f} > 0.008")
                return None
            # 收盘 > EMA20（不强制阳线，因为当前K线可能未完成）
            if curr_bar.close < ema20_curr:
                logger.info(f"[TF] {symbol} LONG 15m ✗ close={curr_bar.close:.4f} < EMA20={ema20_curr:.4f}")
                return None
        else:
            # 做空镜像
            touch_dist = abs(prev_bar.high - ema20_prev) / ema20_prev
            if touch_dist > 0.008:
                logger.info(f"[TF] {symbol} SHORT 15m ✗ pullback dist={touch_dist:.4f} > 0.008")
                return None
            if curr_bar.close > ema20_curr:
                logger.info(f"[TF] {symbol} SHORT 15m ✗ close={curr_bar.close:.4f} > EMA20={ema20_curr:.4f}")
                return None

        # 成交量确认（用已完成K线，排除当前未完成K线）
        vol_r = volume_ratio(klines_15m[:-1], recent=1, baseline=20)
        min_vol = config.get('liquidity_filter', {}).get('min_volume_ratio', 1.2)
        if vol_r < min_vol:
            logger.info(f"[TF] {symbol} 15m ✗ vol_ratio={vol_r:.2f} < {min_vol}")
            return None

        # 计算止损止盈
        entry_price = curr_bar.close
        atr_val = atr(klines_15m, 14)
        stop_mult = exec_cfg.get('stop_atr_multiple', 1.2)
        tp1_r = exec_cfg.get('tp1_r', 1.5)
        tp2_r = exec_cfg.get('tp2_r', 2.5)
        tp1_pct = exec_cfg.get('tp1_close_pct', 0.5)
        tp2_pct = exec_cfg.get('tp2_close_pct', 0.5)

        stop_dist = atr_val * stop_mult

        if direction == Direction.LONG:
            stop_loss = entry_price - stop_dist
            tp1_price = entry_price + stop_dist * tp1_r
            tp2_price = entry_price + stop_dist * tp2_r
        else:
            stop_loss = entry_price + stop_dist
            tp1_price = entry_price - stop_dist * tp1_r
            tp2_price = entry_price - stop_dist * tp2_r

        # 盈亏比检查 (加权平均: TP1*pct + TP2*pct)
        risk_reward = tp1_r * tp1_pct + tp2_r * tp2_pct
        min_rr = exec_cfg.get('min_risk_reward', 1.8)
        if risk_reward < min_rr:
            return None

        return Signal(
            symbol=symbol,
            direction=direction,
            strategy=SignalStrategy.TREND_FOLLOW,
            entry_price=entry_price,
            stop_loss=stop_loss,
            take_profits=[(tp1_price, tp1_pct), (tp2_price, tp2_pct)],
            risk_reward=risk_reward,
            confidence=0.80,
            trend_score=adx_val,
        )
