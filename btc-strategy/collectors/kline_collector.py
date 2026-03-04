import pandas as pd
from datetime import datetime
from typing import Optional

from collectors.base_collector import BaseCollector
from utils.time_utils import utc8_to_ms, ms_to_utc8, utc8_now, interval_to_ms, UTC8
from storage.schema import KLINE_SCHEMA


class KlineCollector(BaseCollector):
    def __init__(self, settings, rate_limiter, timeframe: str = "1h"):
        super().__init__(settings, rate_limiter)
        self.timeframe = timeframe
        self.endpoint = f"{self.settings.binance.base_url}/fapi/v1/klines"
        self.batch_size = self.settings.collector.kline_batch_size
        self.interval_ms = interval_to_ms(timeframe)

    def get_data_type(self) -> str:
        return f"klines/{self.timeframe}"

    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        all_data = []
        current_start = utc8_to_ms(start_time)
        end_ms = utc8_to_ms(end_time)

        total_expected = (end_ms - current_start) // self.interval_ms
        fetched = 0

        while current_start < end_ms:
            params = {
                "symbol": self.settings.data.symbol,
                "interval": self.timeframe,
                "startTime": current_start,
                "endTime": end_ms,
                "limit": self.batch_size,
            }

            raw = self._request(self.endpoint, params, weight=1)
            if not raw:
                break

            df = self._parse(raw)
            all_data.append(df)
            fetched += len(df)

            last_open_time = raw[-1][0]
            current_start = last_open_time + self.interval_ms

            if len(raw) < self.batch_size:
                break

            if total_expected > 0 and fetched % 5000 == 0:
                pct = min(100, fetched / total_expected * 100)
                self.logger.info(f"[{self.timeframe}] Progress: {fetched}/{total_expected} ({pct:.1f}%)")

        if not all_data:
            return pd.DataFrame()

        result = pd.concat(all_data, ignore_index=True)
        result = result.drop_duplicates(subset=["timestamp"], keep="last")
        self.logger.info(f"[{self.timeframe}] Collected {len(result)} candles")
        return result

    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        if last_timestamp is None:
            return pd.DataFrame()

        overlap = max(self.interval_ms * 2, 60000)
        start_time = datetime.fromtimestamp(
            (utc8_to_ms(last_timestamp) - overlap) / 1000, tz=UTC8
        )
        end_time = utc8_now()
        return self.collect_historical(start_time, end_time)

    def _parse(self, raw: list) -> pd.DataFrame:
        records = []
        for k in raw:
            records.append({
                "timestamp": ms_to_utc8(k[0]),
                "open": float(k[1]),
                "high": float(k[2]),
                "low": float(k[3]),
                "close": float(k[4]),
                "volume": float(k[5]),
                "close_time": ms_to_utc8(k[6]),
                "quote_volume": float(k[7]),
                "trade_count": int(k[8]),
                "taker_buy_volume": float(k[9]),
                "taker_buy_quote_volume": float(k[10]),
            })
        return pd.DataFrame(records)
