"""
Tier 1 极速爆破扫描器 - FR-001
30 秒周期, 5min K线量价爆发检测
"""
import asyncio
import time
from datetime import datetime, timezone
from scanner.base import ScannerBase
from data.kline_cache import kline_cache
from data.cvd_calculator import cvd_calculator
from data.market_data import market_data
from filters.wick_filter import wick_filter
from filters.cvd_filter import cvd_filter
from filters.funding_filter import funding_filter
from filters.blacklist_filter import is_tradable
from risk.risk_manager import risk_manager
from models.db_ops import save_signal
from core.constants import (
    TIER1_SCAN_INTERVAL, TIER1_VOLUME_RATIO_MIN,
    TIER1_PRICE_CHANGE_MIN, TIER1_OI_CHANGE_MIN,
    TIER1_TAKER_RATIO_LONG, TIER1_TAKER_RATIO_SHORT,
    TIER1_TRADING_HOURS_UTC, TIER_D_VOLUME_THRESHOLD,
)
from core.logger import get_logger

logger = get_logger('tier1_scalper')


class Tier1Scalper(ScannerBase):

    def __init__(self, symbols: list, executor=None):
        self.symbols = symbols
        self.executor = executor
        self._scan_count = 0
        self._signal_count = 0

    def _is_trading_hours(self) -> bool:
        """BR-007: Tier 1 仅在 UTC 8-22"""
        hour = datetime.now(timezone.utc).hour
        start, end = TIER1_TRADING_HOURS_UTC
        return start <= hour < end

    async def run(self):
        logger.info("tier1.started", symbols=len(self.symbols))
        while True:
            try:
                if not self._is_trading_hours():
                    await asyncio.sleep(TIER1_SCAN_INTERVAL)
                    continue

                scan_signals = await self.scan()
                self._scan_count += 1

                for sig in scan_signals:
                    # 保存信号到 DB
                    try:
                        sig_id = save_signal(sig)
                    except Exception as e:
                        logger.error("tier1.save_signal_error", error=str(e))
                        sig_id = None

                    if sig['final_decision'] == 'executed' and self.executor:
                        result = risk_manager.check(sig)
                        if result.approved:
                            await self.executor.open_position(
                                symbol=sig['symbol'],
                                direction=sig['direction'],
                                tier='tier1',
                                margin=result.position_size,
                                leverage=result.leverage,
                                stop_loss_roi=result.stop_loss_roi,
                                signal_id=sig_id,
                            )
                        else:
                            sig['final_decision'] = 'blocked'
                            sig['filter_reason'] = result.reason
                            try:
                                save_signal(sig)
                            except Exception:
                                pass

            except Exception as e:
                logger.error("tier1.scan_error", error=str(e))

            await asyncio.sleep(TIER1_SCAN_INTERVAL)

    async def scan(self) -> list:
        """执行一次全量扫描"""
        signals = []
        t0 = time.time()

        for symbol in self.symbols:
            sig = self._scan_one(symbol)
            if sig:
                signals.append(sig)

        elapsed = (time.time() - t0) * 1000

        # 调试: 打印缓存详情
        cached_syms = kline_cache.symbols()
        check = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT']
        closed_counts = {s: len(kline_cache.get(s, '5m', 5)) for s in check}
        logger.info("tier1.scan_done",
                   scanned=len(self.symbols), signals=len(signals),
                   elapsed_ms=f"{elapsed:.0f}",
                   kline_symbols=len(cached_syms),
                   closed_klines_sample=str(closed_counts))

        return signals

    def _scan_one(self, symbol: str) -> dict:
        """扫描单个 symbol"""
        # 1. 黑名单检查
        vol_24h = market_data.get_volume_24h(symbol)
        # vol_24h=0 表示还没拉取数据, 不过滤 (传 None 跳过黑名单)
        tradable, reason = is_tradable(symbol, vol_24h if vol_24h > 0 else None)
        if not tradable:
            return None

        # 2. 获取 K线
        klines = kline_cache.get(symbol, '5m', n=21)
        if len(klines) < 2:
            return None

        latest = klines[-1]
        prev = klines[:-1]

        # 3. 量比
        avg_vol = sum(k.volume for k in prev) / len(prev) if prev else 0
        vol_ratio = latest.volume / avg_vol if avg_vol > 0 else 0
        if vol_ratio < TIER1_VOLUME_RATIO_MIN:
            return None

        # 4. 价格变化
        price_change = latest.change_pct
        if abs(price_change) < TIER1_PRICE_CHANGE_MIN:
            return None

        direction = 'long' if price_change > 0 else 'short'

        # 5. OI 变化
        oi_change = market_data.get_oi_change_pct(symbol)

        # 6. Taker 比例
        taker_ratio = market_data.get_taker_ratio(symbol)
        if direction == 'long' and taker_ratio < TIER1_TAKER_RATIO_LONG:
            return None
        if direction == 'short' and taker_ratio > TIER1_TAKER_RATIO_SHORT:
            return None

        # 7. Wick 过滤
        wick_passed, body_ratio = wick_filter(
            latest.open, latest.high, latest.low, latest.close
        )

        # 8. CVD 过滤 (Phase 1: aggTrade 未订阅, 暂时跳过, Phase 2 启用)
        price_series = [k.close for k in klines]
        cvd_series = cvd_calculator.get_cumulative(symbol)
        if len(cvd_series) >= 20:
            cvd_passed, cvd_reason = cvd_filter(price_series, cvd_series, direction)
        else:
            # Phase 1: CVD 数据不足时默认通过, 不阻塞信号
            cvd_passed, cvd_reason = True, "cvd_skip_phase1"

        # 9. Funding 过滤
        funding_rate = market_data.get_funding_rate(symbol)
        funding_passed, _ = funding_filter(funding_rate)

        # 构建信号
        signal = {
            'tier': 'tier1',
            'symbol': symbol,
            'direction': direction,
            'price': latest.close,
            'volume_ratio': round(vol_ratio, 2),
            'price_change_pct': round(price_change, 4),
            'oi_change_pct': round(oi_change, 4),
            'taker_ratio': round(taker_ratio, 2),
            'funding_rate': funding_rate,
            'body_ratio': round(body_ratio, 4),
            'cvd_aligned': cvd_passed,
            'funding_passed': funding_passed,
            'timestamp': datetime.now(timezone.utc),
        }

        # 过滤判定
        if not (wick_passed and cvd_passed and funding_passed):
            reasons = []
            if not wick_passed:
                reasons.append("wick")
            if not cvd_passed:
                reasons.append(f"cvd_{cvd_reason}")
            if not funding_passed:
                reasons.append("funding")
            signal['final_decision'] = 'filtered'
            signal['filter_reason'] = ','.join(reasons)
            self._signal_count += 1
            return signal

        signal['final_decision'] = 'executed'
        signal['filter_reason'] = None
        self._signal_count += 1
        return signal

    @property
    def stats(self) -> dict:
        return {
            'scan_count': self._scan_count,
            'signal_count': self._signal_count,
            'symbols': len(self.symbols),
        }
