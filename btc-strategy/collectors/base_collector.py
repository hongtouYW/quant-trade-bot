import time
import requests
from abc import ABC, abstractmethod
from typing import Optional
from datetime import datetime
import pandas as pd

from config.settings import Settings
from utils.rate_limiter import RateLimiter
from utils.logger import get_logger


class BaseCollector(ABC):
    def __init__(self, settings: Settings, rate_limiter: RateLimiter):
        self.settings = settings
        self.rate_limiter = rate_limiter
        self.logger = get_logger(self.__class__.__name__)
        self.session = requests.Session()
        self.session.headers.update({"X-MBX-APIKEY": settings.binance.api_key})

    @abstractmethod
    def collect_historical(self, start_time: datetime, end_time: datetime) -> pd.DataFrame:
        ...

    @abstractmethod
    def collect_incremental(self, last_timestamp: Optional[datetime] = None) -> pd.DataFrame:
        ...

    @abstractmethod
    def get_data_type(self) -> str:
        ...

    def _request(self, url: str, params: dict, weight: int = 1) -> list:
        self.rate_limiter.acquire(weight)

        max_retries = self.settings.collector.max_retries
        base_delay = self.settings.collector.retry_base_delay

        for attempt in range(max_retries + 1):
            try:
                resp = self.session.get(url, params=params, timeout=30)

                if resp.status_code == 429:
                    retry_after = int(resp.headers.get("Retry-After", 60))
                    self.logger.warning(f"Rate limited. Waiting {retry_after}s")
                    time.sleep(retry_after)
                    continue

                if resp.status_code == 418:
                    self.logger.error("IP banned by Binance. Stopping.")
                    raise RuntimeError("IP banned by Binance (418)")

                resp.raise_for_status()
                time.sleep(self.settings.collector.request_delay)
                return resp.json()

            except requests.exceptions.RequestException as e:
                if attempt < max_retries:
                    delay = base_delay * (2 ** attempt)
                    self.logger.warning(
                        f"Request failed (attempt {attempt + 1}): {e}. Retry in {delay:.1f}s"
                    )
                    time.sleep(delay)
                else:
                    self.logger.error(f"Request failed after {max_retries + 1} attempts: {e}")
                    raise
