#!/usr/bin/env python3
"""Daily incremental update for all data types."""
import sys
import os
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from pipeline.orchestrator import PipelineOrchestrator
from config.settings import Settings


def main():
    settings = Settings.load()
    orchestrator = PipelineOrchestrator(settings)

    print("Running daily incremental update...")
    summary = orchestrator.run_incremental()

    print("\nGenerating quality report...")
    report_path = orchestrator.generate_report()
    print(f"Report saved: {report_path}")


if __name__ == "__main__":
    main()
