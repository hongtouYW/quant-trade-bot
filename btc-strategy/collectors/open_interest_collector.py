import pandas as pd
from datetime import datetime, timedelta
from typing import Optional

from collectors.base_collector import BaseCollector
from utils.time_utils import utc8_to_ms, ms_to_utc8, utc8_now, UTC8
from storage.schema import OPEN_INTEREST_SCHEMA


class OpenInterestCollector(BaseCollector):
    """
    Open Interest history: /futures/data/openInterestHist
    CRITICAL: Only 30 days of data available. Must run daily to archive.
    """

    def __init__(self, settings, rate_limiter):
        super().__init__(settings, rate_limiter)
        self.endpoint = f"{self.settings.binance.data_url}/futures/data/openInterestHist"
        self.snapshot_endpoint = f"{self.settings.binance.base_url}/fapi/v1/openInterest"
        self.batch_size = self.settings.collector.oi_batch_size
        self.period = self.settings.collector.oi_period
        self.max_lookback_days = 30

    def get_data_type(self) -> str:
        return "open_interest"

    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        now = utc8_now()
        earliest = now - timedelta(days=self.max_lookback_days)

        if start_time < earliest:
            self.logger.warning(
                f"OI history limited to 30 days. Adjusting start from {start_time} to {earliest}"
            )
            start_time = earliest

        all_data = []
        current_start = utc8_to_ms(start_time)
        end_ms = utc8_to_ms(min(end_time, now))

        while current_start < end_ms:
            params = {
                "symbol": self.settings.data.symbol,
                "period": self.period,
                "startTime": current_start,
                "endTime": end_ms,
                "limit": self.batch_size,
            }

            raw = self._request(self.endpoint, params, weight=1)
            if not raw:
                break

            df = self._parse(raw)
            all_data.append(df)

            last_ts = raw[-1]["timestamp"]
            current_start = last_ts + 1

            if len(raw) < self.batch_size:
                break

        if not all_data:
            return pd.DataFrame()

        result = pd.concat(all_data, ignore_index=True)
        result = result.drop_duplicates(subset=["timestamp"], keep="last")
        self.logger.info(f"Collected {len(result)} OI records")
        return result

    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        now = utc8_now()
        if last_timestamp is None:
            start = now - timedelta(days=self.max_lookback_days)
        else:
            start = datetime.fromtimestamp(
                (utc8_to_ms(last_timestamp) - 2 * 86400 * 1000) / 1000, tz=UTC8
            )
        return self.collect_historical(start, now)

    def get_current_oi(self) -> Optional[dict]:
        try:
            params = {"symbol": self.settings.data.symbol}
            raw = self._request(self.snapshot_endpoint, params, weight=1)
            return {
                "timestamp": utc8_now(),
                "symbol": raw["symbol"],
                "sum_open_interest": float(raw["openInterest"]),
                "sum_open_interest_value": 0.0,
            }
        except Exception as e:
            self.logger.error(f"Failed to get current OI: {e}")
            return None

    def _parse(self, raw: list) -> pd.DataFrame:
        records = []
        for r in raw:
            records.append({
                "timestamp": ms_to_utc8(r["timestamp"]),
                "symbol": r["symbol"],
                "sum_open_interest": float(r["sumOpenInterest"]),
                "sum_open_interest_value": float(r["sumOpenInterestValue"]),
            })
        return pd.DataFrame(records)
