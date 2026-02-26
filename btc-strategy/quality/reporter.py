import json
from pathlib import Path
from datetime import datetime

from quality.validator import DataValidator, ValidationResult
from storage.parquet_store import ParquetStore
from config.settings import Settings
from utils.time_utils import utc8_now
from utils.logger import get_logger

logger = get_logger("QualityReporter")


class QualityReporter:
    def __init__(self, validator: DataValidator, store: ParquetStore, settings: Settings):
        self.validator = validator
        self.store = store
        self.settings = settings

    def generate_report(self) -> dict:
        results = self.validator.validate_all()

        data_summary = {}
        data_types = [f"klines/{tf}" for tf in self.settings.data.kline_timeframes]
        data_types += ["funding_rate", "open_interest", "agg_trades", "liquidations", "whale_tracking"]

        for dt in data_types:
            data_summary[dt] = self.store.get_data_summary(dt)

        criticals = sum(1 for r in results if r.severity == "critical" and not r.passed)
        errors = sum(1 for r in results if r.severity == "error" and not r.passed)
        warnings = sum(1 for r in results if r.severity == "warning" and not r.passed)

        if criticals > 0:
            status = "critical"
        elif errors > 0:
            status = "error"
        elif warnings > 0:
            status = "warning"
        else:
            status = "healthy"

        report = {
            "generated_at": str(utc8_now()),
            "overall_status": status,
            "summary": {
                "critical": criticals,
                "errors": errors,
                "warnings": warnings,
                "passed": sum(1 for r in results if r.passed),
                "total_checks": len(results),
            },
            "data_summary": data_summary,
            "validation_results": [
                {
                    "data_type": r.data_type,
                    "check": r.check_name,
                    "passed": r.passed,
                    "severity": r.severity,
                    "message": r.message,
                    "details": r.details,
                }
                for r in results
            ],
        }

        return report

    def save_report(self, output_dir: Path = None) -> Path:
        if output_dir is None:
            output_dir = self.settings.data.data_dir / "reports"
        output_dir.mkdir(parents=True, exist_ok=True)

        report = self.generate_report()

        timestamp = utc8_now().strftime("%Y%m%d_%H%M%S")
        path = output_dir / f"quality_report_{timestamp}.json"

        with open(path, "w", encoding="utf-8") as f:
            json.dump(report, f, ensure_ascii=False, indent=2, default=str)

        latest = output_dir / "latest_report.json"
        with open(latest, "w", encoding="utf-8") as f:
            json.dump(report, f, ensure_ascii=False, indent=2, default=str)

        logger.info(f"Report saved: {path} (status: {report['overall_status']})")
        self._print_summary(report)

        return path

    def _print_summary(self, report: dict):
        status = report["overall_status"]
        s = report["summary"]
        logger.info(f"=== Data Quality Report ===")
        logger.info(f"Status: {status.upper()}")
        logger.info(f"Checks: {s['passed']}/{s['total_checks']} passed")

        if s["critical"]:
            logger.critical(f"CRITICAL: {s['critical']}")
        if s["errors"]:
            logger.error(f"Errors: {s['errors']}")
        if s["warnings"]:
            logger.warning(f"Warnings: {s['warnings']}")

        logger.info("--- Data Coverage ---")
        for dt, info in report["data_summary"].items():
            if info["rows"] > 0:
                logger.info(f"  {dt}: {info['rows']} rows, {info['from']} ~ {info['to']} ({info['size_mb']}MB)")
            else:
                logger.info(f"  {dt}: empty")
