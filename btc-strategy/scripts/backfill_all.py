#!/usr/bin/env python3
"""Full historical backfill for all data types."""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import argparse
from pipeline.orchestrator import PipelineOrchestrator
from config.settings import Settings


def main():
    parser = argparse.ArgumentParser(description="BTC Data Backfill")
    parser.add_argument("--start", default=None, help="Start date YYYY-MM-DD (default: from config)")
    parser.add_argument("--end", default=None, help="End date YYYY-MM-DD (default: now)")
    parser.add_argument("--report", action="store_true", help="Generate quality report after backfill")
    args = parser.parse_args()

    settings = Settings.load()
    orchestrator = PipelineOrchestrator(settings)

    print(f"Starting backfill: {args.start or settings.data.history_start} -> {args.end or 'now'}")
    print(f"Symbol: {settings.data.symbol}")
    print(f"Timeframes: {settings.data.kline_timeframes}")
    print(f"Data dir: {settings.data.data_dir}")
    print()

    summary = orchestrator.run_backfill(start_date=args.start, end_date=args.end)

    if args.report:
        print("\nGenerating quality report...")
        report_path = orchestrator.generate_report()
        print(f"Report saved: {report_path}")


if __name__ == "__main__":
    main()
