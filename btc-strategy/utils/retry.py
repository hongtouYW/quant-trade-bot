import functools
import time
import requests
from utils.logger import get_logger

logger = get_logger("retry")


def retry_on_failure(max_retries: int = 5, base_delay: float = 1.0,
                     backoff_factor: float = 2.0,
                     retryable_exceptions=(
                         requests.exceptions.RequestException,
                         ConnectionError,
                         TimeoutError,
                     )):
    def decorator(func):
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            last_exc = None
            for attempt in range(max_retries + 1):
                try:
                    return func(*args, **kwargs)
                except retryable_exceptions as e:
                    last_exc = e
                    if attempt < max_retries:
                        delay = base_delay * (backoff_factor ** attempt)
                        logger.warning(
                            f"{func.__name__} failed (attempt {attempt + 1}/{max_retries + 1}): {e}. "
                            f"Retrying in {delay:.1f}s..."
                        )
                        time.sleep(delay)
                    else:
                        logger.error(
                            f"{func.__name__} failed after {max_retries + 1} attempts: {e}"
                        )
            raise last_exc
        return wrapper
    return decorator
