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
        max_429_retries = 10  # 429 单独计数，允许更多次

        attempt = 0
        rate_limit_hits = 0

        while attempt <= max_retries:
            try:
                resp = self.session.get(url, params=params, timeout=30)

                if resp.status_code == 429:
                    rate_limit_hits += 1
                    if rate_limit_hits > max_429_retries:
                        self.logger.warning(f"Rate limited {rate_limit_hits} times, skipping this request")
                        return []
                    retry_after = int(resp.headers.get("Retry-After", 60))
                    wait_time = retry_after + rate_limit_hits * 5  # 递增等待
                    self.logger.warning(f"Rate limited ({rate_limit_hits}/{max_429_retries}). Waiting {wait_time}s")
                    time.sleep(wait_time)
                    continue  # 不消耗 attempt 计数

                if resp.status_code == 418:
                    self.logger.error("IP banned by Binance. Waiting 5 minutes.")
                    time.sleep(300)
                    return []

                resp.raise_for_status()
                time.sleep(self.settings.collector.request_delay)
                return resp.json()

            except requests.exceptions.RequestException as e:
                attempt += 1
                if attempt <= max_retries:
                    delay = base_delay * (2 ** (attempt - 1))
                    self.logger.warning(
                        f"Request failed (attempt {attempt}/{max_retries}): {e}. Retry in {delay:.1f}s"
                    )
                    time.sleep(delay)
                else:
                    self.logger.error(f"Request failed after {max_retries} attempts: {e}")
                    return []  # 返回空而不是崩溃
