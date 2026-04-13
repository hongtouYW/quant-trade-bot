"""
Tier 2 次级爆发扫描器 (检讨后改版)
60 秒周期, 量比 ≥ 3x + 涨跌 ≥ 1% + ADX趋势确认

核心逻辑: 不预测方向, 跟随价格。
价格已经涨了 1% = 做多, 跌了 1% = 做空。
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
from scanner.base import ScannerBase
from data.kline_cache import kline_cache
from data.market_data import market_data
from data.indicators import adx
from filters.wick_filter import wick_filter
from filters.funding_filter import funding_filter
from filters.blacklist_filter import is_tradable
from risk.risk_manager import risk_manager
from models.db_ops import save_signal
from core.constants import TIER2_SCAN_INTERVAL, TIER2_VOLUME_RATIO_MIN, TIER2_PRICE_CHANGE_MIN
from core.logger import get_logger

logger = get_logger('tier2_burst')

MYT = timezone(timedelta(hours=8))


class Tier2TrendScanner(ScannerBase):

    def __init__(self, symbols: list, executor=None):
        self.symbols = symbols
        self.executor = executor
        self._scan_count = 0
        self._last_signal_ts = {}

    async def run(self):
        logger.info("tier2.started", symbols=len(self.symbols))
        while True:
            try:
                scan_signals = await self.scan()
                self._scan_count += 1

                for sig in scan_signals:
                    sym = sig['symbol']
                    kline_ts = sig.get('kline_timestamp', 0)
                    if self._last_signal_ts.get(sym) == kline_ts:
                        continue
                    self._last_signal_ts[sym] = kline_ts

                    try:
                        sig_id = save_signal(sig)
                    except Exception as e:
                        logger.error("tier2.save_error", error=str(e))
                        sig_id = None

                    if sig['final_decision'] == 'executed' and self.executor:
                        result = risk_manager.check(sig)
                        if result.approved:
                            await self.executor.open_position(
                                symbol=sym,
                                direction=sig['direction'],
                                tier='tier2',
                                margin=result.position_size,
                                leverage=result.leverage,
                                stop_loss_roi=result.stop_loss_roi,
                                signal_id=sig_id,
                            )
                            logger.info("tier2.trade_opened",
                                       symbol=sym, direction=sig['direction'],
                                       vol_ratio=sig['volume_ratio'])
                        else:
                            if sig_id:
                                try:
                                    from models.db_ops import _exec
                                    from models.signal import signals as sig_table
                                    from sqlalchemy import update
                                    _exec(update(sig_table).where(
                                        sig_table.c.id == sig_id
                                    ).values(final_decision='blocked',
                                             filter_reason=result.reason))
                                except Exception:
                                    pass

            except Exception as e:
                logger.error("tier2.scan_error", error=str(e))

            await asyncio.sleep(TIER2_SCAN_INTERVAL)

    async def scan(self) -> list:
        signals = []
        for symbol in self.symbols:
            sig = self._scan_one(symbol)
            if sig:
                signals.append(sig)

        if self._scan_count % 10 == 0:
            logger.info("tier2.scan_done",
                       scanned=len(self.symbols), signals=len(signals))
        return signals

    def _scan_one(self, symbol: str) -> dict:
        # 1. 黑名单
        vol_24h = market_data.get_volume_24h(symbol)
        tradable, _ = is_tradable(symbol, vol_24h if vol_24h > 0 else None)
        if not tradable:
            return None

        # 2. 获取 5min K线
        klines = kline_cache.get(symbol, '5m', n=21)
        if len(klines) < 2:
            return None

        latest = klines[-1]
        prev = klines[:-1]

        # 3. 量比 ≥ 3x
        avg_vol = sum(k.volume for k in prev) / len(prev) if prev else 0
        vol_ratio = latest.volume / avg_vol if avg_vol > 0 else 0
        if vol_ratio < TIER2_VOLUME_RATIO_MIN:
            return None

        # 4. 涨跌 ≥ 1% — 方向由价格决定
        price_change = latest.change_pct
        if abs(price_change) < TIER2_PRICE_CHANGE_MIN:
            return None

        direction = 'long' if price_change > 0 else 'short'

        # 4.5 大趋势过滤 — 不逆势, 震荡不开
        from data.trend import is_direction_allowed
        allowed, trend = is_direction_allowed(symbol, direction)
        if not allowed:
            return None

        # 5. ADX 趋势过滤 (≥ 20 才有趋势)
        if len(klines) >= 15:
            highs = [k.high for k in klines]
            lows = [k.low for k in klines]
            closes = [k.close for k in klines]
            adx_vals = adx(highs, lows, closes, 14)
            if adx_vals and adx_vals[-1] < 20:
                # 震荡市, 不开仓
                return None

        # 6. Wick 过滤
        wick_passed, body_ratio = wick_filter(
            latest.open, latest.high, latest.low, latest.close
        )

        # 7. Funding 过滤
        funding_rate = market_data.get_funding_rate(symbol)
        funding_passed, _ = funding_filter(funding_rate)

        signal = {
            'tier': 'tier2',
            'symbol': symbol,
            'direction': direction,
            'price': latest.close,
            'volume_ratio': round(vol_ratio, 2),
            'price_change_pct': round(price_change, 4),
            'funding_rate': funding_rate,
            'body_ratio': round(body_ratio, 4),
            'cvd_aligned': True,
            'funding_passed': funding_passed,
            'timestamp': datetime.now(MYT),
            'kline_timestamp': latest.timestamp,
        }

        if not (wick_passed and funding_passed):
            reasons = []
            if not wick_passed: reasons.append("wick")
            if not funding_passed: reasons.append("funding")
            signal['final_decision'] = 'filtered'
            signal['filter_reason'] = ','.join(reasons)
        else:
            signal['final_decision'] = 'executed'
            signal['filter_reason'] = None

        return signal
