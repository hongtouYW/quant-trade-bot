import os
import json
from pathlib import Path
from dataclasses import dataclass, field
from typing import List, Optional
from dotenv import load_dotenv


@dataclass
class BinanceConfig:
    api_key: str = ""
    api_secret: str = ""
    base_url: str = "https://fapi.binance.com"
    data_url: str = "https://fapi.binance.com"
    ws_url: str = "wss://fstream.binance.com"
    rate_limit_weight_per_min: int = 2400
    safety_margin: float = 0.8


@dataclass
class DataConfig:
    symbol: str = "BTCUSDT"
    kline_timeframes: List[str] = field(
        default_factory=lambda: ["1m", "5m", "15m", "1h", "4h", "1d"]
    )
    history_start: str = "2024-01-01"
    timezone: str = "Asia/Shanghai"
    data_dir: Path = field(default_factory=lambda: Path(__file__).parent.parent / "data")


@dataclass
class CollectorConfig:
    kline_batch_size: int = 1000
    funding_rate_batch_size: int = 1000
    oi_batch_size: int = 500
    oi_period: str = "5m"
    agg_trades_batch_size: int = 1000
    agg_trades_window_hours: int = 1
    large_trade_threshold_usd: float = 100000.0
    max_retries: int = 5
    retry_base_delay: float = 1.0
    request_delay: float = 0.1
    liquidation_flush_interval: int = 300


@dataclass
class Settings:
    binance: BinanceConfig
    data: DataConfig
    collector: CollectorConfig

    @classmethod
    def load(cls, env_path: Optional[str] = None) -> "Settings":
        if env_path:
            load_dotenv(env_path)
        else:
            project_root = Path(__file__).parent.parent
            env_file = project_root / ".env"
            if env_file.exists():
                load_dotenv(env_file)

        api_key = os.getenv("BINANCE_API_KEY", "")
        api_secret = os.getenv("BINANCE_API_SECRET", "")

        if not api_key or not api_secret:
            fallback = Path(__file__).parent.parent.parent / "quant-trade-bot" / "config" / "config.json"
            if fallback.exists():
                with open(fallback) as f:
                    cfg = json.load(f)
                binance_cfg = cfg.get("binance", {})
                api_key = api_key or binance_cfg.get("api_key", "")
                api_secret = api_secret or binance_cfg.get("api_secret", "")

        binance = BinanceConfig(api_key=api_key, api_secret=api_secret)
        data = DataConfig()
        collector = CollectorConfig()

        data_dir_env = os.getenv("DATA_DIR")
        if data_dir_env:
            data.data_dir = Path(data_dir_env)

        history_start = os.getenv("HISTORY_START")
        if history_start:
            data.history_start = history_start

        return cls(binance=binance, data=data, collector=collector)
