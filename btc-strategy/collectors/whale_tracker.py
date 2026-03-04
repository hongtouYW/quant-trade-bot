import os
import pandas as pd
import requests
from datetime import datetime
from typing import Optional
import json

from collectors.base_collector import BaseCollector
from collectors.agg_trades_collector import AggTradesCollector
from utils.time_utils import utc8_to_ms, ms_to_utc8, utc8_now, UTC8
from storage.schema import WHALE_TRACKING_SCHEMA


class WhaleTracker(BaseCollector):
    """
    Composite whale tracking:
    1. Large trades from AggTrades (>$100k)
    2. Order book depth imbalance snapshots
    3. Optional: Whale Alert API for on-chain transfers
    """

    def __init__(self, settings, rate_limiter, agg_trades_collector: AggTradesCollector):
        super().__init__(settings, rate_limiter)
        self.agg_trades = agg_trades_collector
        self.whale_alert_key = os.getenv("WHALE_ALERT_API_KEY", "")
        self.depth_endpoint = f"{self.settings.binance.base_url}/fapi/v1/depth"
        self.threshold = settings.collector.large_trade_threshold_usd

    def get_data_type(self) -> str:
        return "whale_tracking"

    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        agg_df = self.agg_trades.collect_historical(start_time, end_time)
        results = []

        if not agg_df.empty:
            large = self.agg_trades.get_large_trades(agg_df)
            if not large.empty:
                whale_from_trades = self._trades_to_whale(large)
                results.append(whale_from_trades)

        if self.whale_alert_key:
            wa_df = self._fetch_whale_alert(start_time, end_time)
            if not wa_df.empty:
                results.append(wa_df)

        if not results:
            return pd.DataFrame()

        result = pd.concat(results, ignore_index=True)
        result = result.sort_values("timestamp").reset_index(drop=True)
        self.logger.info(f"Collected {len(result)} whale events")
        return result

    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        if last_timestamp is None:
            return pd.DataFrame()

        start = datetime.fromtimestamp(
            (utc8_to_ms(last_timestamp) - 2 * 3600 * 1000) / 1000, tz=UTC8
        )
        df = self.collect_historical(start, utc8_now())

        depth = self.snapshot_depth()
        if depth is not None:
            df = pd.concat([df, pd.DataFrame([depth])], ignore_index=True)

        return df

    def snapshot_depth(self, limit: int = 20) -> Optional[dict]:
        try:
            params = {"symbol": self.settings.data.symbol, "limit": limit}
            raw = self._request(self.depth_endpoint, params, weight=5)

            bids = raw.get("bids", [])
            asks = raw.get("asks", [])

            bid_value = sum(float(b[0]) * float(b[1]) for b in bids)
            ask_value = sum(float(a[0]) * float(a[1]) for a in asks)
            imbalance = (bid_value - ask_value) / (bid_value + ask_value) if (bid_value + ask_value) > 0 else 0

            return {
                "timestamp": utc8_now(),
                "source": "orderbook",
                "event_type": "depth_imbalance",
                "side": "buy" if imbalance > 0 else "sell",
                "value_usd": abs(bid_value - ask_value),
                "quantity": 0.0,
                "price": float(bids[0][0]) if bids else 0.0,
                "details": json.dumps({
                    "bid_value": round(bid_value, 2),
                    "ask_value": round(ask_value, 2),
                    "imbalance": round(imbalance, 4),
                    "levels": limit,
                }),
            }
        except Exception as e:
            self.logger.error(f"Depth snapshot failed: {e}")
            return None

    def _trades_to_whale(self, large_df: pd.DataFrame) -> pd.DataFrame:
        records = []
        for _, t in large_df.iterrows():
            records.append({
                "timestamp": t["timestamp"],
                "source": "agg_trades",
                "event_type": "large_trade",
                "side": "sell" if t["is_buyer_maker"] else "buy",
                "value_usd": t["trade_value_usd"],
                "quantity": t["quantity"],
                "price": t["price"],
                "details": json.dumps({"agg_trade_id": int(t["agg_trade_id"])}),
            })
        return pd.DataFrame(records)

    def _fetch_whale_alert(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        if not self.whale_alert_key:
            return pd.DataFrame()

        try:
            start_ts = int(start_time.timestamp())
            end_ts = int(end_time.timestamp())

            url = "https://api.whale-alert.io/v1/transactions"
            params = {
                "api_key": self.whale_alert_key,
                "min_value": int(self.threshold),
                "start": start_ts,
                "end": min(end_ts, start_ts + 3600),
                "currency": "btc",
            }

            resp = requests.get(url, params=params, timeout=30)
            if resp.status_code != 200:
                return pd.DataFrame()

            data = resp.json()
            txs = data.get("transactions", [])

            records = []
            for tx in txs:
                records.append({
                    "timestamp": ms_to_utc8(tx["timestamp"] * 1000),
                    "source": "whale_alert",
                    "event_type": "transfer",
                    "side": "unknown",
                    "value_usd": float(tx.get("amount_usd", 0)),
                    "quantity": float(tx.get("amount", 0)),
                    "price": 0.0,
                    "details": json.dumps({
                        "from": tx.get("from", {}).get("owner", "unknown"),
                        "to": tx.get("to", {}).get("owner", "unknown"),
                        "hash": tx.get("hash", ""),
                    }),
                })
            return pd.DataFrame(records)

        except Exception as e:
            self.logger.warning(f"Whale Alert fetch failed: {e}")
            return pd.DataFrame()
