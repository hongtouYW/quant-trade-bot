#!/usr/bin/env python3
import sys; sys.path.insert(0, ".")
from config.settings import Settings
from utils.rate_limiter import RateLimiter
from collectors.kline_collector import KlineCollector
from collectors.funding_rate_collector import FundingRateCollector
from storage.parquet_store import ParquetStore
from storage.schema import SCHEMAS
from utils.time_utils import utc8_now
from datetime import timedelta

settings = Settings.load()
rl = RateLimiter(2400)
store = ParquetStore(settings.data.data_dir)

print("=== Test 1: Kline 1h (24h) ===")
kc = KlineCollector(settings, rl, "1h")
end = utc8_now()
start = end - timedelta(hours=24)
df = kc.collect_historical(start, end)
print(f"Got {len(df)} candles")
if not df.empty:
    pk = SCHEMAS["klines"]["primary_key"]
    store.write("klines/1h", df, primary_key=pk)
    print(f"Last close: {df.iloc[-1]['close']}")
    print(store.get_data_summary("klines/1h"))

print("\n=== Test 2: Funding Rate (7d) ===")
fc = FundingRateCollector(settings, rl)
start2 = end - timedelta(days=7)
df2 = fc.collect_historical(start2, end)
print(f"Got {len(df2)} records")
if not df2.empty:
    pk = SCHEMAS["funding_rate"]["primary_key"]
    store.write("funding_rate", df2, primary_key=pk)
    print(f"Latest rate: {df2.iloc[-1]['funding_rate']}")

print("\nDone!")
