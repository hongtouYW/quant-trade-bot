import time
import threading
from collections import deque
from utils.logger import get_logger

logger = get_logger("rate_limiter")


class RateLimiter:
    def __init__(self, max_weight_per_minute: int = 2400, safety_pct: float = 0.8):
        self.max_weight = int(max_weight_per_minute * safety_pct)
        self.window: deque = deque()
        self.lock = threading.Lock()

    def acquire(self, weight: int = 1) -> None:
        while True:
            with self.lock:
                self._clean_window()
                current = sum(w for _, w in self.window)
                if current + weight <= self.max_weight:
                    self.window.append((time.monotonic(), weight))
                    return
                if self.window:
                    oldest_time = self.window[0][0]
                    wait = 60.0 - (time.monotonic() - oldest_time) + 0.1
                else:
                    wait = 0.1
            if wait > 0:
                logger.debug(f"Rate limit: waiting {wait:.1f}s for {weight} weight")
                time.sleep(min(wait, 5.0))

    def _clean_window(self) -> None:
        now = time.monotonic()
        while self.window and (now - self.window[0][0]) > 60.0:
            self.window.popleft()

    def get_remaining_weight(self) -> int:
        with self.lock:
            self._clean_window()
            current = sum(w for _, w in self.window)
            return self.max_weight - current
