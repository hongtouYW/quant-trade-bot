"""
爆仓猎手扫描器 — 大跌反弹策略

入场: 5min K线 跌幅 ≥ -3% AND 量比 ≥ 5x → 做多
止损: -1% 价格 (从入场价)
止盈: +2% 价格
超时: 60 分钟 (12 根 5min)
杠杆: 10x
仓位: 300U

回测 2025 全年: +3.0%, 77 笔, 胜率 45%, MaxDD 4.7%
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
from scanner.base import ScannerBase
from data.kline_cache import kline_cache
from filters.blacklist_filter import is_tradable
from data.market_data import market_data
from models.db_ops import save_signal
from core.logger import get_logger

logger = get_logger('liquidation_hunter')


# 策略参数 (与回测一致)
DROP_THRESHOLD = -0.03         # 5min 跌幅 ≥ 3%
VOLUME_RATIO_MIN = 5.0          # 量比 ≥ 5x
LOOKBACK_BARS = 20              # 量比基准
SCAN_INTERVAL = 30              # 30s 扫描一次

# 入场后管理 — 快进快出, 价格 SL/TP 不变, 杠杆放大 ROI
LEVERAGE = 50
STOP_LOSS_ROI = -0.50           # ROI -50% (= 价格 -1% × 50x)
TAKE_PROFIT_ROI = 1.00          # ROI +100% (= 价格 +2% × 50x)
MAX_HOLD_HOURS = 1              # 60 分钟


class LiquidationHunter(ScannerBase):
    """大跌反弹猎手"""

    NAME = 'liquidation_hunter'

    def __init__(self, symbols: list, executor=None):
        self.symbols = symbols
        self.executor = executor
        self._scan_count = 0
        # 去重: symbol -> last triggered kline timestamp
        self._last_trigger_ts = {}

    async def run(self):
        logger.info("liq_hunter.started",
                    symbols=len(self.symbols),
                    drop_threshold=DROP_THRESHOLD,
                    vol_ratio_min=VOLUME_RATIO_MIN)
        while True:
            try:
                signals = await self.scan()
                self._scan_count += 1

                for sig in signals:
                    sym = sig['symbol']
                    kline_ts = sig.get('kline_timestamp', 0)
                    if self._last_trigger_ts.get(sym) == kline_ts:
                        continue
                    self._last_trigger_ts[sym] = kline_ts

                    # 保存信号
                    try:
                        sig_id = save_signal(sig)
                    except Exception as e:
                        logger.error("liq_hunter.save_error", error=str(e))
                        sig_id = None

                    # 触发开仓 (做多 = 反弹交易)
                    if sig['final_decision'] == 'executed' and self.executor:
                        from risk.risk_manager import risk_manager
                        result = risk_manager.check(sig)
                        if result.approved:
                            await self.executor.open_position(
                                symbol=sym,
                                direction='long',
                                tier='liquidation',
                                margin=result.position_size,
                                leverage=LEVERAGE,
                                stop_loss_roi=STOP_LOSS_ROI,
                                signal_id=sig_id,
                            )
                            logger.info("liq_hunter.trade_opened",
                                       symbol=sym,
                                       drop_pct=sig['price_change_pct'],
                                       vol_ratio=sig['volume_ratio'])

            except Exception as e:
                logger.error("liq_hunter.scan_error", error=str(e))

            await asyncio.sleep(SCAN_INTERVAL)

    async def scan(self) -> list:
        signals = []
        t0 = time.time()

        for symbol in self.symbols:
            sig = self._scan_one(symbol)
            if sig:
                signals.append(sig)

        # 每次扫完都打日志 (有信号高亮)
        elapsed = (time.time() - t0) * 1000
        if signals:
            logger.info("liq_hunter.scan_done",
                        scanned=len(self.symbols),
                        triggered=len(signals),
                        elapsed_ms=f"{elapsed:.0f}")

        return signals

    def _scan_one(self, symbol: str):
        """扫描单个币: 检查最新 5min 是否符合大跌信号"""
        # 黑名单
        vol_24h = market_data.get_volume_24h(symbol)
        tradable, _ = is_tradable(symbol, vol_24h if vol_24h > 0 else None)
        if not tradable:
            return None

        # 拉 5min K线 (要 21 根: 20 根历史 + 1 根当前)
        klines = kline_cache.get(symbol, '5m', n=LOOKBACK_BARS + 1)
        if len(klines) < LOOKBACK_BARS + 1:
            return None

        latest = klines[-1]
        prev = klines[:-1]

        # 跌幅
        drop_pct = (latest.close - latest.open) / latest.open if latest.open > 0 else 0
        if drop_pct > DROP_THRESHOLD:  # 跌幅不够 (注意是负数比较)
            return None

        # 量比
        avg_vol = sum(k.volume for k in prev) / len(prev) if prev else 0
        vol_ratio = latest.volume / avg_vol if avg_vol > 0 else 0
        if vol_ratio < VOLUME_RATIO_MIN:
            return None

        # 触发! 构造信号
        signal = {
            'tier': 'liquidation',
            'symbol': symbol,
            'direction': 'long',
            'price': latest.close,
            'volume_ratio': round(vol_ratio, 2),
            'price_change_pct': round(drop_pct, 4),
            'oi_change_pct': 0,
            'taker_ratio': 0,
            'funding_rate': market_data.get_funding_rate(symbol),
            'body_ratio': 0,
            'cvd_aligned': True,
            'funding_passed': True,
            'timestamp': datetime.now(timezone.utc),
            'kline_timestamp': latest.timestamp,
            'final_decision': 'executed',
            'filter_reason': None,
        }

        return signal

    @property
    def stats(self) -> dict:
        return {
            'scan_count': self._scan_count,
            'symbols': len(self.symbols),
        }
