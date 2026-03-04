#!/usr/bin/env python3
"""Resume backfill for remaining data types (agg_trades, whale_tracking).
Supports full resume: detects existing data and continues from where it left off.
"""
import sys, os, json, time
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


def run_backfill():
    settings = Settings.load()
    rl = RateLimiter(settings.binance.rate_limit_weight_per_min)
    store = ParquetStore(settings.data.data_dir)
    start = parse_date(settings.data.history_start)
    end = utc8_now()

    # Agg trades (1 year max) - streaming with auto-resume
    logger.info("=== Resuming agg_trades backfill (streaming mode) ===")
    try:
        atc = AggTradesCollector(settings, rl)
        total = atc.collect_historical_streaming(start, end, store, flush_every=50)
        logger.info(f"agg_trades: {total} records written total")
    except Exception as e:
        logger.error(f"agg_trades failed: {e}", exc_info=True)
        return False  # 返回失败，让外层重试

    # Derive whale_tracking from stored agg_trades (逐文件处理，避免 OOM)
    logger.info("=== Deriving whale_tracking from agg_trades ===")
    try:
        import pyarrow.parquet as pq
        agg_dir = os.path.join(settings.data.data_dir, "agg_trades")
        pq_files = sorted([f for f in os.listdir(agg_dir) if f.endswith(".parquet")])
        atc2 = AggTradesCollector(settings, rl)
        total_whale = 0
        for pf in pq_files:
            chunk = pd.read_parquet(os.path.join(agg_dir, pf))
            large = atc2.get_large_trades(chunk)
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
                total_whale += len(whale_df)
            del chunk
        logger.info(f"whale_tracking: {total_whale} large trades total")
    except Exception as e:
        logger.error(f"whale_tracking failed: {e}", exc_info=True)

    # Compute trade flow (逐文件处理，避免 OOM)
    logger.info("=== Computing trade flow ===")
    try:
        agg_dir = os.path.join(settings.data.data_dir, "agg_trades")
        pq_files = sorted([f for f in os.listdir(agg_dir) if f.endswith(".parquet")])
        atc3 = AggTradesCollector(settings, rl)
        total_flow = 0
        for pf in pq_files:
            chunk = pd.read_parquet(os.path.join(agg_dir, pf))
            flow = atc3.compute_trade_flow(chunk)
            if not flow.empty:
                fpk = SCHEMAS["trade_flow"]["primary_key"]
                store.write("trade_flow", flow, primary_key=fpk)
                total_flow += len(flow)
            del chunk
        logger.info(f"trade_flow: {total_flow} records total")
    except Exception as e:
        logger.error(f"trade_flow failed: {e}", exc_info=True)

    # Generate report
    logger.info("=== Generating quality report ===")
    try:
        validator = DataValidator(store, settings)
        reporter = QualityReporter(validator, store, settings)
        path = reporter.save_report()
        logger.info(f"Report: {path}")
    except Exception as e:
        logger.error(f"Report generation failed: {e}", exc_info=True)

    return True


if __name__ == "__main__":
    max_crash_retries = 5
    for retry in range(max_crash_retries):
        logger.info(f"=== Backfill attempt {retry + 1}/{max_crash_retries} ===")
        try:
            success = run_backfill()
            if success:
                logger.info("Backfill completed successfully!")
                break
            else:
                logger.warning(f"Backfill returned failure, retrying in 5 min...")
                time.sleep(300)
        except Exception as e:
            logger.error(f"Backfill crashed: {e}", exc_info=True)
            if retry < max_crash_retries - 1:
                wait = 300 * (retry + 1)
                logger.info(f"Retrying in {wait}s...")
                time.sleep(wait)
            else:
                logger.error("Max retries exhausted. Giving up.")

    print("Done!")
