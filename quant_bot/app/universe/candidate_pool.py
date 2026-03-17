"""候选池筛选 - 三层过滤"""
import logging
from datetime import datetime
from app.config import get

log = logging.getLogger(__name__)


class CandidatePool:
    def __init__(self, exchange_client, ohlcv_cache):
        self.exchange = exchange_client
        self.cache = ohlcv_cache
        self.filters = get('filters')

    def build(self):
        """从全市场构建候选池"""
        symbols = self.exchange.get_usdt_perpetuals()
        log.info(f"扫描 {len(symbols)} 个USDT永续合约")

        candidates = []
        for sym in symbols[:get('market', 'max_scan_symbols', 150)]:
            snap = self._evaluate_symbol(sym)
            if snap:
                candidates.append(snap)

        candidates.sort(key=lambda x: x['score'], reverse=True)
        pool_size = get('market', 'active_pool_size', 12)
        active = candidates[:pool_size]
        log.info(f"活跃池: 从 {len(candidates)} 个候选中选出 {len(active)} 个")
        return active

    def _evaluate_symbol(self, symbol):
        """三层过滤评估"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            if not ticker:
                return None

            # === 第一层: 硬过滤 ===

            # 24h成交额
            vol_24h = float(ticker.get('quoteVolume', 0) or 0)
            if vol_24h < self.filters.get('min_24h_volume', 8_000_000):
                return None

            # 点差
            spread = 0
            bid = float(ticker.get('bid', 0) or 0)
            ask = float(ticker.get('ask', 0) or 0)
            if bid > 0 and ask > 0:
                spread = (ask - bid) / bid * 100
            if spread > self.filters.get('max_spread_pct', 0.04):
                return None

            # 资金费率
            funding = self.exchange.fetch_funding_rate(symbol)
            if abs(funding) > self.filters.get('max_abs_funding_rate', 0.0075):
                return None

            # 持仓量(Open Interest)
            oi = self.exchange.fetch_open_interest(symbol)
            if oi < self.filters.get('min_open_interest', 3_000_000):
                return None

            # 上线天数 (通过市场信息检查)
            markets = self.exchange.load_markets()
            market_key = f"{symbol}:USDT"
            if market_key in markets:
                market_info = markets[market_key]
                # 尝试获取上线时间
                launch_ts = market_info.get('created')
                if launch_ts:
                    listing_days = (datetime.utcnow() - datetime.utcfromtimestamp(launch_ts / 1000)).days
                    if listing_days < self.filters.get('min_listing_days', 14):
                        return None

            # === 第二层: 波动过滤 ===
            from app.indicators import calc
            df_1h = self.cache.get(symbol, '1h')
            if df_1h is None or len(df_1h) < 20:
                return None

            atrp_val = calc.atrp(df_1h, 14)
            current_atrp = atrp_val.iloc[-1] if len(atrp_val) > 0 else 0
            if current_atrp < self.filters.get('min_atrp_1h', 0.25):
                return None
            if current_atrp > self.filters.get('max_atrp_1h', 4.5):
                return None

            # === 第三层: 流动性过滤 ===
            df_5m = self.cache.get(symbol, '5m')
            wicky = 0
            if df_5m is not None and len(df_5m) >= 12:
                wicky = calc.count_wicky_candles(df_5m, 12)
                if wicky >= 6:  # 插针过多，淘汰
                    return None

                # 近1小时成交额检查 (12根5m)
                recent_12_vol = df_5m['volume'].iloc[-12:].sum()
                avg_hourly_vol = df_5m['volume'].iloc[-60:].mean() * 12 if len(df_5m) >= 60 else 0
                if avg_hourly_vol > 0 and recent_12_vol < avg_hourly_vol * 0.5:
                    return None  # 近1小时量能太低

            last_price = float(ticker.get('last', 0) or 0)
            return {
                'symbol': symbol,
                'volume_24h': vol_24h,
                'open_interest': oi,
                'spread_pct': spread,
                'funding_rate': funding,
                'atrp_1h': current_atrp,
                'last_price': last_price,
                'wicky_count': wicky,
                'score': 0,  # 由 trend_scoring 填充
            }
        except Exception as e:
            log.debug(f"过滤跳过 {symbol}: {e}")
            return None
