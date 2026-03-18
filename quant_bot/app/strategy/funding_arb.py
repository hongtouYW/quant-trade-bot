"""资金费套利策略 - Phase 2 (Spec §29 扩展)
当资金费率极端时，反向持仓收取资金费。
- 正资金费率过高(>0.05%) → 做空收取funding
- 负资金费率过低(<-0.05%) → 做多收取funding
仅在资金费率极端且技术面不反对时入场。
"""
import logging
import numpy as np
from datetime import datetime, timedelta
from app.indicators import calc
from app.models.market import Signal
from app.config import get

SIGNAL_EXPIRY_MINUTES = 3

log = logging.getLogger(__name__)


class FundingArbEngine:
    """资金费套利引擎"""

    def __init__(self, ohlcv_cache, exchange_client):
        self.cache = ohlcv_cache
        self.exchange = exchange_client
        # 参数
        self.min_funding_rate = 0.0005   # 0.05% 最低触发阈值
        self.ideal_funding_rate = 0.001  # 0.1% 理想费率
        self.adx_max = 30               # ADX太高说明强趋势，不做反向
        self.max_atrp = 3.0             # ATRP太高波动太大，不适合套利
        self.risk_pct = 0.002           # 单笔风险0.2% (比趋势策略低)
        self.stop_atr_mult = 2.0        # 止损更宽 (2x ATR)
        self.min_hold_hours = 8         # 至少持仓8小时才能收到一次funding

    def find_setup(self, symbol, snapshot):
        """检测资金费套利机会"""
        if not get('funding_arb', 'enable', False):
            return None

        # 获取当前资金费率
        funding_rate = snapshot.get('funding_rate', 0)
        if abs(funding_rate) < self.min_funding_rate:
            return None  # 费率不够极端

        df_15m = self.cache.get(symbol, '15m')
        if df_15m is None or len(df_15m) < 30:
            return None

        close = df_15m['close'].iloc[-1]

        # ATR / ATRP 检查
        atr_val = calc.atr(df_15m, 14).iloc[-1]
        if np.isnan(atr_val) or atr_val <= 0:
            return None

        atrp_vals = calc.atrp(df_15m, 14)
        curr_atrp = atrp_vals.iloc[-1] if len(atrp_vals) > 0 else 0
        if curr_atrp > self.max_atrp:
            return None  # 波动太大

        # ADX 检查 - 不要在强趋势中做反向
        adx_vals, _, _ = calc.adx(df_15m, 14)
        curr_adx = adx_vals.iloc[-1] if len(adx_vals) > 0 and not np.isnan(adx_vals.iloc[-1]) else 30
        if curr_adx > self.adx_max:
            return None

        # 方向: 反向于高费率方 (正费率多头付空头，所以做空收费)
        if funding_rate > self.min_funding_rate:
            direction = -1  # 做空收取正funding
        elif funding_rate < -self.min_funding_rate:
            direction = 1   # 做多收取负funding
        else:
            return None

        # 技术面不能强烈反对
        e20 = calc.ema(df_15m['close'], 20).iloc[-1]
        e50 = calc.ema(df_15m['close'], 50).iloc[-1]

        # 做空时价格不能远高于EMA (强上涨趋势)
        if direction == -1 and close > e20 * 1.02 and e20 > e50:
            return None  # 强上涨趋势，不做空套利
        # 做多时价格不能远低于EMA (强下跌趋势)
        if direction == 1 and close < e20 * 0.98 and e20 < e50:
            return None  # 强下跌趋势，不做多套利

        # 计算止损/止盈
        stop_dist = atr_val * self.stop_atr_mult
        if direction == 1:
            stop_loss = close - stop_dist
            # 套利TP: 保守，主要靠funding fee收益
            tp1 = close + stop_dist * 0.8  # 0.8R
            tp2 = close + stop_dist * 1.5  # 1.5R
        else:
            stop_loss = close + stop_dist
            tp1 = close - stop_dist * 0.8
            tp2 = close - stop_dist * 1.5

        # 预估8h funding收益
        # funding_pnl = notional * funding_rate * direction * -1
        # 正funding做空: pnl = notional * funding_rate (收到)
        estimated_funding_pct = abs(funding_rate) * 100

        log.info(
            f"资金费套利: {symbol} {'做空' if direction == -1 else '做多'} "
            f"funding={funding_rate*100:.4f}% ADX={curr_adx:.1f} "
            f"预估8h收益={estimated_funding_pct:.3f}%"
        )

        return Signal(
            symbol=symbol, direction=direction,
            setup_type='funding_arb',
            score=snapshot.get('final_score', 50) * 0.8,  # 较低优先级
            entry_price=close,
            stop_loss=stop_loss,
            tp1=tp1, tp2=tp2,
            risk_reward=0.8,
            expires_at=datetime.utcnow() + timedelta(minutes=SIGNAL_EXPIRY_MINUTES),
        )
