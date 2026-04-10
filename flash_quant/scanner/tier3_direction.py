"""
Tier 3 1H 方向扫描器 - FR-003
1H 整点扫描, 综合评分系统
评分 = RSI(25) + MA(25) + Volume(25) + Position(25) + MACD(10) + ADX(10) + BB(5) = 125
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
from scanner.base import ScannerBase
from data.kline_cache import kline_cache
from data.market_data import market_data
from data.indicators import rsi, macd, adx, bollinger_bands, ema, sma, volume_ratio
from filters.wick_filter import wick_filter
from filters.funding_filter import funding_filter
from filters.blacklist_filter import is_tradable
from risk.risk_manager import risk_manager
from models.db_ops import save_signal
from core.constants import TIER3_SCAN_INTERVAL, TIER3_MIN_SCORE
from core.logger import get_logger

logger = get_logger('tier3_direction')

MYT = timezone(timedelta(hours=8))

# Tier 3 只扫 Tier A 蓝筹
TIER3_SYMBOLS = [
    'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
    'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'DOTUSDT',
    'NEARUSDT', 'APTUSDT', 'ATOMUSDT', 'TRXUSDT', 'SUIUSDT',
]


class Tier3DirectionScanner(ScannerBase):

    def __init__(self, symbols: list = None, executor=None):
        self.symbols = symbols or TIER3_SYMBOLS
        self.executor = executor
        self._scan_count = 0
        self._last_signal_ts = {}

    async def run(self):
        logger.info("tier3.started", symbols=len(self.symbols))
        while True:
            try:
                # 等待整点 (1H K线收盘)
                now = datetime.now(MYT)
                # 在每小时的第 0-1 分钟扫描
                if now.minute <= 1:
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
                            logger.error("tier3.save_error", error=str(e))
                            sig_id = None

                        if sig['final_decision'] == 'executed' and self.executor:
                            result = risk_manager.check(sig)
                            if result.approved:
                                await self.executor.open_position(
                                    symbol=sym,
                                    direction=sig['direction'],
                                    tier='tier3',
                                    margin=result.position_size,
                                    leverage=result.leverage,
                                    stop_loss_roi=result.stop_loss_roi,
                                    signal_id=sig_id,
                                )
                                logger.info("tier3.trade_opened",
                                           symbol=sym, direction=sig['direction'],
                                           score=sig.get('score'))

            except Exception as e:
                logger.error("tier3.scan_error", error=str(e))

            await asyncio.sleep(60)  # 每分钟检查一次是否整点

    async def scan(self) -> list:
        signals = []
        for symbol in self.symbols:
            sig = self._scan_one(symbol)
            if sig:
                signals.append(sig)

        if signals:
            logger.info("tier3.scan_done",
                       scanned=len(self.symbols), signals=len(signals))
        return signals

    def _scan_one(self, symbol: str) -> dict:
        # 黑名单
        vol_24h = market_data.get_volume_24h(symbol)
        tradable, _ = is_tradable(symbol, vol_24h if vol_24h > 0 else None)
        if not tradable:
            return None

        # 1H K线 (需要至少 50 根)
        klines = kline_cache.get(symbol, '1h', n=50)
        if len(klines) < 30:
            return None

        closes = [k.close for k in klines]
        highs = [k.high for k in klines]
        lows = [k.low for k in klines]
        volumes = [k.volume for k in klines]
        latest = klines[-1]

        # === 评分系统 ===
        score = 0
        direction_votes = {'long': 0, 'short': 0}

        # 1. RSI 评分 (0-25)
        rsi_vals = rsi(closes, 14)
        if rsi_vals:
            r = rsi_vals[-1]
            if r > 55:
                s = min(25, (r - 50) * 0.5)
                score += s
                direction_votes['long'] += 1
            elif r < 45:
                s = min(25, (50 - r) * 0.5)
                score += s
                direction_votes['short'] += 1

        # 2. MA 评分 (0-25) — 价格 vs EMA20
        ema20 = ema(closes, 20)
        if ema20:
            price = closes[-1]
            ma = ema20[-1]
            diff_pct = (price - ma) / ma * 100
            if diff_pct > 0:
                s = min(25, diff_pct * 5)
                score += s
                direction_votes['long'] += 1
            elif diff_pct < 0:
                s = min(25, abs(diff_pct) * 5)
                score += s
                direction_votes['short'] += 1

        # 3. Volume 评分 (0-25)
        vr = volume_ratio(volumes, 20)
        if vr > 1:
            s = min(25, (vr - 1) * 10)
            score += s

        # 4. Position 评分 (0-25) — 价格在近期范围的位置
        if len(closes) >= 20:
            high_20 = max(closes[-20:])
            low_20 = min(closes[-20:])
            range_20 = high_20 - low_20
            if range_20 > 0:
                pos = (closes[-1] - low_20) / range_20  # 0=底部, 1=顶部
                if pos > 0.6:
                    s = min(25, (pos - 0.5) * 50)
                    score += s
                    direction_votes['long'] += 1
                elif pos < 0.4:
                    s = min(25, (0.5 - pos) * 50)
                    score += s
                    direction_votes['short'] += 1

        # 5. MACD 加分 (0-10)
        _, _, hist = macd(closes)
        if len(hist) >= 2:
            if hist[-1] > 0 and hist[-1] > hist[-2]:
                score += min(10, abs(hist[-1]) * 1000)
                direction_votes['long'] += 1
            elif hist[-1] < 0 and hist[-1] < hist[-2]:
                score += min(10, abs(hist[-1]) * 1000)
                direction_votes['short'] += 1

        # 6. ADX 加分 (0-10) — 趋势强度
        adx_vals = adx(highs, lows, closes, 14)
        if adx_vals and adx_vals[-1] > 20:
            score += min(10, (adx_vals[-1] - 20) * 0.5)

        # 7. BB 加分 (0-5) — 布林带突破
        upper, middle, lower = bollinger_bands(closes, 20)
        if upper and lower:
            if closes[-1] > upper[-1]:
                score += 5
                direction_votes['long'] += 1
            elif closes[-1] < lower[-1]:
                score += 5
                direction_votes['short'] += 1

        # 评分不够
        score = round(score, 1)
        if score < TIER3_MIN_SCORE:
            return None

        # 确定方向 (投票)
        if direction_votes['long'] > direction_votes['short']:
            direction = 'long'
        elif direction_votes['short'] > direction_votes['long']:
            direction = 'short'
        else:
            return None  # 无法判断方向

        # Wick + Funding 过滤
        wick_passed, body_ratio = wick_filter(
            latest.open, latest.high, latest.low, latest.close
        )
        funding_rate = market_data.get_funding_rate(symbol)
        funding_passed, _ = funding_filter(funding_rate)

        signal = {
            'tier': 'tier3',
            'symbol': symbol,
            'direction': direction,
            'price': latest.close,
            'score': score,
            'volume_ratio': round(vr, 2),
            'price_change_pct': round(latest.change_pct, 4),
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
