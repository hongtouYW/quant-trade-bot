#!/usr/bin/env python3
"""Resume backfill for remaining data types (agg_trades, whale_tracking)."""
import sys, os, json
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pandas as pd
from config.settings import Settings
from utils.rate_limiter import RateLimiter
from utils.time_utils import parse_date, utc8_now
from storage.parquet_store import ParquetStore
from storage.schema import SCHEMAS
from collectors.agg_trades_collector import AggTradesCollector
from quality.validator import DataValidator
from quality.reporter import QualityReporter
from utils.logger import get_logger

logger = get_logger("ResumeBackfill")

settings = Settings.load()
rl = RateLimiter(settings.binance.rate_limit_weight_per_min)
store = ParquetStore(settings.data.data_dir)
start = parse_date(settings.data.history_start)
end = utc8_now()

# Agg trades (1 year max) - streaming to avoid memory issues
logger.info("=== Resuming agg_trades backfill (streaming mode) ===")
try:
    atc = AggTradesCollector(settings, rl)
    total = atc.collect_historical_streaming(start, end, store, flush_every=50)
    logger.info(f"agg_trades: {total} records written total")
except Exception as e:
    logger.error(f"agg_trades failed: {e}")

# Derive whale_tracking from stored agg_trades
logger.info("=== Deriving whale_tracking from agg_trades ===")
try:
    agg_df = store.read("agg_trades")
    if not agg_df.empty:
        atc2 = AggTradesCollector(settings, rl)
        large = atc2.get_large_trades(agg_df)
        if not large.empty:
            whale_records = []
            for _, t in large.iterrows():
                whale_records.append({
                    "timestamp": t["timestamp"],
                    "source": "agg_trades",
                    "event_type": "large_trade",
                    "side": "sell" if t["is_buyer_maker"] else "buy",
                    "value_usd": t["trade_value_usd"],
                    "quantity": t["quantity"],
                    "price": t["price"],
                    "details": json.dumps({"agg_trade_id": int(t["agg_trade_id"])}),
                })
            whale_df = pd.DataFrame(whale_records)
            pk = SCHEMAS["whale_tracking"]["primary_key"]
            store.write("whale_tracking", whale_df, primary_key=pk)
            logger.info(f"whale_tracking: {len(whale_df)} large trades")
        else:
            logger.info("No large trades found for whale tracking")
    else:
        logger.info("No agg_trades data to derive whale tracking from")
except Exception as e:
    logger.error(f"whale_tracking failed: {e}")

# Compute trade flow
logger.info("=== Computing trade flow ===")
try:
    agg_df = store.read("agg_trades")
    if not agg_df.empty:
        atc3 = AggTradesCollector(settings, rl)
        flow = atc3.compute_trade_flow(agg_df)
        if not flow.empty:
            fpk = SCHEMAS["trade_flow"]["primary_key"]
            store.write("trade_flow", flow, primary_key=fpk)
            logger.info(f"trade_flow: {len(flow)} records")
except Exception as e:
    logger.error(f"trade_flow failed: {e}")

# Generate report
logger.info("=== Generating quality report ===")
validator = DataValidator(store, settings)
reporter = QualityReporter(validator, store, settings)
path = reporter.save_report()
logger.info(f"Report: {path}")
print("Done!")
