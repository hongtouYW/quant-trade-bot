import pandas as pd
from datetime import datetime, timedelta
from typing import Optional

from collectors.base_collector import BaseCollector
from utils.time_utils import utc8_to_ms, ms_to_utc8, utc8_now, UTC8
from storage.schema import AGG_TRADES_SCHEMA


class AggTradesCollector(BaseCollector):
    """
    Aggregated trades: /fapi/v1/aggTrades
    - Max 1 year lookback
    - startTime/endTime window must be <= 1 hour
    - 1000 records per request, weight=2
    """

    def __init__(self, settings, rate_limiter):
        super().__init__(settings, rate_limiter)
        self.endpoint = f"{self.settings.binance.base_url}/fapi/v1/aggTrades"
        self.batch_size = self.settings.collector.agg_trades_batch_size
        self.window_ms = self.settings.collector.agg_trades_window_hours * 3600 * 1000
        self.large_threshold = self.settings.collector.large_trade_threshold_usd
        self.extra_delay = 0.4  # Extra delay between requests to avoid 429

    def get_data_type(self) -> str:
        return "agg_trades"

    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        now = utc8_now()
        max_start = now - timedelta(days=365)
        if start_time < max_start:
            self.logger.warning(
                f"AggTrades limited to 1 year. Adjusting start from {start_time} to {max_start}"
            )
            start_time = max_start

        all_data = []
        window_start = utc8_to_ms(start_time)
        end_ms = utc8_to_ms(min(end_time, now))

        total_windows = (end_ms - window_start) // self.window_ms + 1
        processed = 0

        while window_start < end_ms:
            window_end = min(window_start + self.window_ms - 1, end_ms)

            df = self._fetch_window(window_start, window_end)
            if not df.empty:
                all_data.append(df)

            window_start = window_end + 1
            processed += 1

            if processed % 100 == 0:
                pct = min(100, processed / total_windows * 100)
                self.logger.info(f"AggTrades progress: {processed}/{total_windows} windows ({pct:.1f}%)")

        if not all_data:
            return pd.DataFrame()

        result = pd.concat(all_data, ignore_index=True)
        result = result.drop_duplicates(subset=["agg_trade_id"], keep="last")
        self.logger.info(f"Collected {len(result)} agg trades")
        return result

    def collect_historical_streaming(self, start_time: datetime, end_time: datetime,
                                     store, flush_every: int = 50) -> int:
        """Collect and write to parquet incrementally every flush_every windows."""
        from storage.schema import AGG_TRADES_SCHEMA
        now = utc8_now()
        max_start = now - timedelta(days=365)
        if start_time < max_start:
            self.logger.warning(
                f"AggTrades limited to 1 year. Adjusting start from {start_time} to {max_start}"
            )
            start_time = max_start

        window_start = utc8_to_ms(start_time)
        end_ms = utc8_to_ms(min(end_time, now))
        total_windows = (end_ms - window_start) // self.window_ms + 1
        processed = 0
        total_written = 0
        buffer = []

        while window_start < end_ms:
            window_end = min(window_start + self.window_ms - 1, end_ms)

            df = self._fetch_window(window_start, window_end)
            if not df.empty:
                buffer.append(df)

            window_start = window_end + 1
            processed += 1

            if processed % flush_every == 0 and buffer:
                chunk = pd.concat(buffer, ignore_index=True)
                chunk = chunk.drop_duplicates(subset=["agg_trade_id"], keep="last")
                pk = AGG_TRADES_SCHEMA["primary_key"]
                store.write("agg_trades", chunk, primary_key=pk)
                total_written += len(chunk)
                buffer.clear()
                pct = min(100, processed / total_windows * 100)
                self.logger.info(
                    f"AggTrades: {processed}/{total_windows} ({pct:.1f}%), "
                    f"written {total_written} total"
                )

        if buffer:
            chunk = pd.concat(buffer, ignore_index=True)
            chunk = chunk.drop_duplicates(subset=["agg_trade_id"], keep="last")
            pk = AGG_TRADES_SCHEMA["primary_key"]
            store.write("agg_trades", chunk, primary_key=pk)
            total_written += len(chunk)

        self.logger.info(f"AggTrades streaming complete: {total_written} records")
        return total_written

    def _fetch_window(self, start_ms: int, end_ms: int, max_pages: int = 5) -> pd.DataFrame:
        """Fetch agg trades in a time window. Limit pagination to max_pages to avoid rate limits."""
        import time as _time
        all_records = []

        params = {
            "symbol": self.settings.data.symbol,
            "startTime": start_ms,
            "endTime": end_ms,
            "limit": self.batch_size,
        }

        raw = self._request(self.endpoint, params, weight=2)
        if not raw:
            return pd.DataFrame()

        all_records.extend(raw)
        pages = 1

        while len(raw) == self.batch_size and pages < max_pages:
            _time.sleep(self.extra_delay)
            last_id = raw[-1]["a"]
            params = {
                "symbol": self.settings.data.symbol,
                "fromId": last_id + 1,
                "limit": self.batch_size,
            }
            raw = self._request(self.endpoint, params, weight=2)
            if not raw:
                break

            in_window = [r for r in raw if r["T"] <= end_ms]
            all_records.extend(in_window)
            pages += 1

            if len(in_window) < len(raw):
                break

        return self._parse(all_records)

    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        if last_timestamp is None:
            return pd.DataFrame()

        start = datetime.fromtimestamp(
            (utc8_to_ms(last_timestamp) - 2 * 3600 * 1000) / 1000, tz=UTC8
        )
        return self.collect_historical(start, utc8_now())

    def get_large_trades(self, df: pd.DataFrame) -> pd.DataFrame:
        if df.empty:
            return df
        return df[df["trade_value_usd"] >= self.large_threshold].copy()

    def compute_trade_flow(self, df: pd.DataFrame, period: str = "5min") -> pd.DataFrame:
        if df.empty:
            return pd.DataFrame()

        df = df.copy()
        df["timestamp"] = pd.to_datetime(df["timestamp"])
        df = df.set_index("timestamp")

        buys = df[~df["is_buyer_maker"]]
        sells = df[df["is_buyer_maker"]]

        def agg(group, side_df):
            return pd.DataFrame({
                f"{side_df}_volume": group["quantity"].sum(),
                f"{side_df}_value_usd": group["trade_value_usd"].sum(),
                f"large_{side_df}_count": (group["trade_value_usd"] >= self.large_threshold).sum(),
                f"large_{side_df}_value_usd": group.loc[
                    group["trade_value_usd"] >= self.large_threshold, "trade_value_usd"
                ].sum(),
            })

        buy_flow = buys.resample(period).agg({
            "quantity": "sum",
            "trade_value_usd": "sum",
        }).rename(columns={"quantity": "buy_volume", "trade_value_usd": "buy_value_usd"})

        sell_flow = sells.resample(period).agg({
            "quantity": "sum",
            "trade_value_usd": "sum",
        }).rename(columns={"quantity": "sell_volume", "trade_value_usd": "sell_value_usd"})

        large_buys = buys[buys["trade_value_usd"] >= self.large_threshold].resample(period).agg({
            "trade_value_usd": ["count", "sum"],
        })
        large_buys.columns = ["large_buy_count", "large_buy_value_usd"]

        large_sells = sells[sells["trade_value_usd"] >= self.large_threshold].resample(period).agg({
            "trade_value_usd": ["count", "sum"],
        })
        large_sells.columns = ["large_sell_count", "large_sell_value_usd"]

        trade_count = df.resample(period)["quantity"].count().rename("trade_count")

        flow = pd.concat([buy_flow, sell_flow, large_buys, large_sells, trade_count], axis=1)
        flow = flow.fillna(0)
        flow["net_flow_usd"] = flow["buy_value_usd"] - flow["sell_value_usd"]

        flow = flow.reset_index()
        flow = flow.rename(columns={"index": "timestamp"})

        int_cols = ["large_buy_count", "large_sell_count", "trade_count"]
        for c in int_cols:
            flow[c] = flow[c].astype(int)

        return flow

    def _parse(self, raw: list) -> pd.DataFrame:
        records = []
        for r in raw:
            price = float(r["p"])
            qty = float(r["q"])
            records.append({
                "agg_trade_id": r["a"],
                "timestamp": ms_to_utc8(r["T"]),
                "price": price,
                "quantity": qty,
                "first_trade_id": r["f"],
                "last_trade_id": r["l"],
                "is_buyer_maker": r["m"],
                "trade_value_usd": price * qty,
            })
        return pd.DataFrame(records)
