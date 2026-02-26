import pandas as pd
from datetime import datetime
from typing import Optional

from collectors.base_collector import BaseCollector
from utils.time_utils import utc8_to_ms, ms_to_utc8, utc8_now, UTC8
from storage.schema import FUNDING_RATE_SCHEMA


class FundingRateCollector(BaseCollector):
    def __init__(self, settings, rate_limiter):
        super().__init__(settings, rate_limiter)
        self.endpoint = f"{self.settings.binance.base_url}/fapi/v1/fundingRate"
        self.batch_size = self.settings.collector.funding_rate_batch_size

    def get_data_type(self) -> str:
        return "funding_rate"

    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        all_data = []
        current_start = utc8_to_ms(start_time)
        end_ms = utc8_to_ms(end_time)

        while current_start < end_ms:
            params = {
                "symbol": self.settings.data.symbol,
                "startTime": current_start,
                "endTime": end_ms,
                "limit": self.batch_size,
            }

            raw = self._request(self.endpoint, params, weight=1)
            if not raw:
                break

            df = self._parse(raw)
            all_data.append(df)

            last_ts = raw[-1]["fundingTime"]
            current_start = last_ts + 1

            if len(raw) < self.batch_size:
                break

        if not all_data:
            return pd.DataFrame()

        result = pd.concat(all_data, ignore_index=True)
        result = result.drop_duplicates(subset=["timestamp"], keep="last")
        self.logger.info(f"Collected {len(result)} funding rate records")
        return result

    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        if last_timestamp is None:
            return pd.DataFrame()

        start_time = datetime.fromtimestamp(
            (utc8_to_ms(last_timestamp) - 8 * 3600 * 1000) / 1000, tz=UTC8
        )
        return self.collect_historical(start_time, utc8_now())

    def _parse(self, raw: list) -> pd.DataFrame:
        records = []
        for r in raw:
            records.append({
                "timestamp": ms_to_utc8(r["fundingTime"]),
                "symbol": r["symbol"],
                "funding_rate": float(r["fundingRate"]),
                "mark_price": float(r.get("markPrice", 0)),
            })
        return pd.DataFrame(records)
