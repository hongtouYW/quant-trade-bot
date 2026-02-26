import sys
from datetime import datetime
from pathlib import Path

from config.settings import Settings
from utils.rate_limiter import RateLimiter
from utils.time_utils import parse_date, utc8_now
from utils.logger import get_logger
from storage.parquet_store import ParquetStore
from storage.schema import SCHEMAS
from collectors.kline_collector import KlineCollector
from collectors.funding_rate_collector import FundingRateCollector
from collectors.open_interest_collector import OpenInterestCollector
from collectors.agg_trades_collector import AggTradesCollector
from collectors.whale_tracker import WhaleTracker
from quality.validator import DataValidator
from quality.reporter import QualityReporter

logger = get_logger("Orchestrator")


class PipelineOrchestrator:
    def __init__(self, settings: Settings = None):
        if settings is None:
            settings = Settings.load()
        self.settings = settings
        self.rate_limiter = RateLimiter(settings.binance.rate_limit_weight_per_min)
        self.store = ParquetStore(settings.data.data_dir)
        self._init_collectors()

    def _init_collectors(self):
        self.kline_collectors = {
            tf: KlineCollector(self.settings, self.rate_limiter, timeframe=tf)
            for tf in self.settings.data.kline_timeframes
        }
        self.funding_collector = FundingRateCollector(self.settings, self.rate_limiter)
        self.oi_collector = OpenInterestCollector(self.settings, self.rate_limiter)
        self.agg_trades_collector = AggTradesCollector(self.settings, self.rate_limiter)
        self.whale_tracker = WhaleTracker(
            self.settings, self.rate_limiter, self.agg_trades_collector
        )

    def run_backfill(self, start_date: str = None, end_date: str = None) -> dict:
        start = parse_date(start_date or self.settings.data.history_start)
        end = utc8_now() if end_date is None else parse_date(end_date)

        logger.info(f"=== Starting backfill: {start} -> {end} ===")
        summary = {}

        # 1. K-lines (large timeframes first)
        tf_order = ["1d", "4h", "1h", "15m", "5m", "1m"]
        for tf in tf_order:
            if tf not in self.kline_collectors:
                continue
            logger.info(f"--- Backfilling klines/{tf} ---")
            try:
                collector = self.kline_collectors[tf]
                df = collector.collect_historical(start, end)
                if not df.empty:
                    pk = SCHEMAS["klines"]["primary_key"]
                    written = self.store.write(collector.get_data_type(), df, primary_key=pk)
                    summary[f"klines/{tf}"] = {"rows": written, "status": "ok"}
                else:
                    summary[f"klines/{tf}"] = {"rows": 0, "status": "empty"}
            except Exception as e:
                logger.error(f"klines/{tf} backfill failed: {e}")
                summary[f"klines/{tf}"] = {"rows": 0, "status": f"error: {e}"}

        # 2. Funding rate
        logger.info("--- Backfilling funding_rate ---")
        try:
            df = self.funding_collector.collect_historical(start, end)
            if not df.empty:
                pk = SCHEMAS["funding_rate"]["primary_key"]
                written = self.store.write("funding_rate", df, primary_key=pk)
                summary["funding_rate"] = {"rows": written, "status": "ok"}
            else:
                summary["funding_rate"] = {"rows": 0, "status": "empty"}
        except Exception as e:
            logger.error(f"funding_rate backfill failed: {e}")
            summary["funding_rate"] = {"rows": 0, "status": f"error: {e}"}

        # 3. Open Interest (30-day limit)
        logger.info("--- Backfilling open_interest (30-day max) ---")
        try:
            df = self.oi_collector.collect_historical(start, end)
            if not df.empty:
                pk = SCHEMAS["open_interest"]["primary_key"]
                written = self.store.write("open_interest", df, primary_key=pk)
                summary["open_interest"] = {"rows": written, "status": "ok"}
            else:
                summary["open_interest"] = {"rows": 0, "status": "empty"}
        except Exception as e:
            logger.error(f"open_interest backfill failed: {e}")
            summary["open_interest"] = {"rows": 0, "status": f"error: {e}"}

        # 4. Aggregated trades (1-year limit)
        logger.info("--- Backfilling agg_trades (1-year max) ---")
        try:
            df = self.agg_trades_collector.collect_historical(start, end)
            if not df.empty:
                pk = SCHEMAS["agg_trades"]["primary_key"]
                written = self.store.write("agg_trades", df, primary_key=pk)
                summary["agg_trades"] = {"rows": written, "status": "ok"}

                # Compute trade flow
                flow = self.agg_trades_collector.compute_trade_flow(df)
                if not flow.empty:
                    fpk = SCHEMAS["trade_flow"]["primary_key"]
                    self.store.write("trade_flow", flow, primary_key=fpk)
            else:
                summary["agg_trades"] = {"rows": 0, "status": "empty"}
        except Exception as e:
            logger.error(f"agg_trades backfill failed: {e}")
            summary["agg_trades"] = {"rows": 0, "status": f"error: {e}"}

        # 5. Whale tracking
        logger.info("--- Backfilling whale_tracking ---")
        try:
            df = self.whale_tracker.collect_historical(start, end)
            if not df.empty:
                pk = SCHEMAS["whale_tracking"]["primary_key"]
                written = self.store.write("whale_tracking", df, primary_key=pk)
                summary["whale_tracking"] = {"rows": written, "status": "ok"}
            else:
                summary["whale_tracking"] = {"rows": 0, "status": "empty"}
        except Exception as e:
            logger.error(f"whale_tracking backfill failed: {e}")
            summary["whale_tracking"] = {"rows": 0, "status": f"error: {e}"}

        logger.info(f"=== Backfill complete ===")
        for k, v in summary.items():
            logger.info(f"  {k}: {v['rows']} rows ({v['status']})")

        return summary

    def run_incremental(self) -> dict:
        logger.info("=== Starting incremental update ===")
        summary = {}

        # Priority 1: OI (30-day expiry)
        logger.info("--- Updating open_interest ---")
        try:
            last = self.store.get_last_timestamp("open_interest")
            df = self.oi_collector.collect_incremental(last)
            if not df.empty:
                pk = SCHEMAS["open_interest"]["primary_key"]
                written = self.store.write("open_interest", df, primary_key=pk)
                summary["open_interest"] = {"rows": written, "status": "ok"}
            else:
                summary["open_interest"] = {"rows": 0, "status": "no_new_data"}
        except Exception as e:
            logger.error(f"OI incremental failed: {e}")
            summary["open_interest"] = {"rows": 0, "status": f"error: {e}"}

        # Priority 2: K-lines
        for tf in ["1d", "4h", "1h", "15m", "5m", "1m"]:
            if tf not in self.kline_collectors:
                continue
            collector = self.kline_collectors[tf]
            dt = collector.get_data_type()
            try:
                last = self.store.get_last_timestamp(dt)
                if last is None:
                    logger.warning(f"{dt}: No existing data. Run backfill first.")
                    summary[dt] = {"rows": 0, "status": "needs_backfill"}
                    continue
                df = collector.collect_incremental(last)
                if not df.empty:
                    pk = SCHEMAS["klines"]["primary_key"]
                    written = self.store.write(dt, df, primary_key=pk)
                    summary[dt] = {"rows": written, "status": "ok"}
                else:
                    summary[dt] = {"rows": 0, "status": "no_new_data"}
            except Exception as e:
                logger.error(f"{dt} incremental failed: {e}")
                summary[dt] = {"rows": 0, "status": f"error: {e}"}

        # Priority 3: Funding rate
        try:
            last = self.store.get_last_timestamp("funding_rate")
            df = self.funding_collector.collect_incremental(last)
            if not df.empty:
                pk = SCHEMAS["funding_rate"]["primary_key"]
                written = self.store.write("funding_rate", df, primary_key=pk)
                summary["funding_rate"] = {"rows": written, "status": "ok"}
            else:
                summary["funding_rate"] = {"rows": 0, "status": "no_new_data"}
        except Exception as e:
            logger.error(f"Funding rate incremental failed: {e}")
            summary["funding_rate"] = {"rows": 0, "status": f"error: {e}"}

        # Priority 4: Agg trades
        try:
            last = self.store.get_last_timestamp("agg_trades")
            if last:
                df = self.agg_trades_collector.collect_incremental(last)
                if not df.empty:
                    pk = SCHEMAS["agg_trades"]["primary_key"]
                    written = self.store.write("agg_trades", df, primary_key=pk)
                    summary["agg_trades"] = {"rows": written, "status": "ok"}

                    flow = self.agg_trades_collector.compute_trade_flow(df)
                    if not flow.empty:
                        fpk = SCHEMAS["trade_flow"]["primary_key"]
                        self.store.write("trade_flow", flow, primary_key=fpk)
                else:
                    summary["agg_trades"] = {"rows": 0, "status": "no_new_data"}
            else:
                summary["agg_trades"] = {"rows": 0, "status": "needs_backfill"}
        except Exception as e:
            logger.error(f"AggTrades incremental failed: {e}")
            summary["agg_trades"] = {"rows": 0, "status": f"error: {e}"}

        # Priority 5: Whale tracking
        try:
            last = self.store.get_last_timestamp("whale_tracking")
            if last:
                df = self.whale_tracker.collect_incremental(last)
                if not df.empty:
                    pk = SCHEMAS["whale_tracking"]["primary_key"]
                    written = self.store.write("whale_tracking", df, primary_key=pk)
                    summary["whale_tracking"] = {"rows": written, "status": "ok"}
                else:
                    summary["whale_tracking"] = {"rows": 0, "status": "no_new_data"}
            else:
                summary["whale_tracking"] = {"rows": 0, "status": "needs_backfill"}
        except Exception as e:
            logger.error(f"Whale tracking incremental failed: {e}")
            summary["whale_tracking"] = {"rows": 0, "status": f"error: {e}"}

        logger.info("=== Incremental update complete ===")
        for k, v in summary.items():
            logger.info(f"  {k}: {v['rows']} rows ({v['status']})")

        return summary

    def generate_report(self) -> Path:
        validator = DataValidator(self.store, self.settings)
        reporter = QualityReporter(validator, self.store, self.settings)
        return reporter.save_report()
