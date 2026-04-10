"""
Tier 2 趋势爆破扫描器 - FR-002
60 秒周期, 15min MACD + RSI + EMA 趋势启动检测
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
from scanner.base import ScannerBase
from data.kline_cache import kline_cache
from data.market_data import market_data
from data.indicators import macd, rsi, ema_cross, volume_ratio
from filters.wick_filter import wick_filter
from filters.funding_filter import funding_filter
from filters.blacklist_filter import is_tradable
from risk.risk_manager import risk_manager
from models.db_ops import save_signal
from core.constants import (
    TIER2_SCAN_INTERVAL, TIER2_RSI_LONG, TIER2_RSI_SHORT,
    TIER2_VOLUME_MULTIPLIER,
)
from core.logger import get_logger

logger = get_logger('tier2_trend')

MYT = timezone(timedelta(hours=8))


class Tier2TrendScanner(ScannerBase):

    def __init__(self, symbols: list, executor=None):
        self.symbols = symbols
        self.executor = executor
        self._scan_count = 0
        self._signal_count = 0
        # 去重: symbol -> last processed kline timestamp
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
                        logger.error("tier2.save_signal_error", error=str(e))
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
                                       symbol=sym, direction=sig['direction'])
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
        t0 = time.time()

        for symbol in self.symbols:
            sig = self._scan_one(symbol)
            if sig:
                signals.append(sig)

        elapsed = (time.time() - t0) * 1000
        if self._scan_count % 10 == 0:  # 每 10 次打一次日志
            logger.info("tier2.scan_done",
                       scanned=len(self.symbols), signals=len(signals),
                       elapsed_ms=f"{elapsed:.0f}")

        return signals

    def _scan_one(self, symbol: str) -> dict:
        # 1. 黑名单
        vol_24h = market_data.get_volume_24h(symbol)
        tradable, _ = is_tradable(symbol, vol_24h if vol_24h > 0 else None)
        if not tradable:
            return None

        # 2. 获取 15min K线 (需要至少 35 根做 MACD 26+9)
        klines = kline_cache.get(symbol, '15m', n=50)
        if len(klines) < 35:
            return None

        closes = [k.close for k in klines]
        volumes = [k.volume for k in klines]
        latest = klines[-1]

        # 3. MACD 柱状图转色
        _, _, hist = macd(closes)
        if len(hist) < 2:
            return None

        macd_cross = False
        direction = None
        if hist[-2] <= 0 and hist[-1] > 0:
            macd_cross = True
            direction = 'long'
        elif hist[-2] >= 0 and hist[-1] < 0:
            macd_cross = True
            direction = 'short'

        if not macd_cross:
            return None

        # 4. RSI 确认
        rsi_values = rsi(closes)
        if not rsi_values:
            return None
        current_rsi = rsi_values[-1]

        rsi_confirm = False
        if direction == 'long' and current_rsi >= TIER2_RSI_LONG:
            rsi_confirm = True
        elif direction == 'short' and current_rsi <= TIER2_RSI_SHORT:
            rsi_confirm = True

        if not rsi_confirm:
            return None

        # 5. EMA9/EMA21 穿越
        cross = ema_cross(closes, 9, 21)
        ema_confirm = False
        if direction == 'long' and cross == 'golden_cross':
            ema_confirm = True
        elif direction == 'short' and cross == 'death_cross':
            ema_confirm = True

        if not ema_confirm:
            return None

        # 6. 1H 成交量确认 (用 15min 近 4 根 vs 前 16 根)
        if len(volumes) >= 20:
            recent_4 = sum(volumes[-4:])
            prev_16 = sum(volumes[-20:-4]) / 4  # 平均每 4 根
            vol_ok = recent_4 > prev_16 * TIER2_VOLUME_MULTIPLIER if prev_16 > 0 else False
        else:
            vol_ok = True  # 数据不够时跳过

        if not vol_ok:
            return None

        # 7. Wick 过滤
        wick_passed, body_ratio = wick_filter(
            latest.open, latest.high, latest.low, latest.close
        )

        # 8. Funding 过滤
        funding_rate = market_data.get_funding_rate(symbol)
        funding_passed, _ = funding_filter(funding_rate)

        # 构建信号
        signal = {
            'tier': 'tier2',
            'symbol': symbol,
            'direction': direction,
            'price': latest.close,
            'volume_ratio': round(volume_ratio(volumes), 2),
            'price_change_pct': round(latest.change_pct, 4),
            'score': round(current_rsi, 1),  # 用 score 字段存 RSI
            'funding_rate': funding_rate,
            'body_ratio': round(body_ratio, 4),
            'cvd_aligned': True,  # Tier 2 不检查 CVD
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

        self._signal_count += 1
        return signal
