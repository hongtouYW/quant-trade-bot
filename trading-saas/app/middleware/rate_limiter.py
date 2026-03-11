"""Rate Limiter — uses Redis if available, falls back to in-memory."""
import time
import threading
from functools import wraps
from collections import defaultdict
from flask import jsonify, request, current_app
from flask_jwt_extended import get_jwt_identity


# In-memory fallback store with lock for thread safety
_rate_store = defaultdict(list)
_rate_lock = threading.Lock()
_last_cleanup = 0


def _get_redis():
    """Try to get Redis connection from app config."""
    try:
        import redis
        url = current_app.config.get('REDIS_URL', '')
        if url:
            return redis.from_url(url, decode_responses=True)
    except Exception:
        pass
    return None


def _cleanup_store():
    """Periodically remove stale keys from in-memory store."""
    global _last_cleanup
    now = time.time()
    if now - _last_cleanup < 300:  # every 5 minutes
        return
    _last_cleanup = now
    stale = [k for k, v in _rate_store.items() if not v]
    for k in stale:
        _rate_store.pop(k, None)


def rate_limit(max_requests: int = 60, window_seconds: int = 60):
    """Rate limit decorator.

    Args:
        max_requests: Maximum requests allowed in window
        window_seconds: Time window in seconds
    """
    def decorator(fn):
        @wraps(fn)
        def wrapper(*args, **kwargs):
            try:
                identity = get_jwt_identity()
                user_key = f"{identity}" if identity else request.remote_addr
            except RuntimeError:
                user_key = request.remote_addr
            key = f"rl:{user_key}:{fn.__name__}"

            # Try Redis first
            r = _get_redis()
            if r:
                try:
                    pipe = r.pipeline()
                    now = time.time()
                    pipe.zremrangebyscore(key, 0, now - window_seconds)
                    pipe.zcard(key)
                    pipe.zadd(key, {str(now): now})
                    pipe.expire(key, window_seconds + 1)
                    results = pipe.execute()
                    count = results[1]
                    if count >= max_requests:
                        return jsonify({'error': 'Rate limit exceeded'}), 429
                    return fn(*args, **kwargs)
                except Exception:
                    pass  # Fall through to in-memory

            # In-memory fallback (with thread lock)
            now = time.time()
            cutoff = now - window_seconds

            with _rate_lock:
                _cleanup_store()
                _rate_store[key] = [t for t in _rate_store[key] if t > cutoff]
                if len(_rate_store[key]) >= max_requests:
                    return jsonify({'error': 'Rate limit exceeded'}), 429
                _rate_store[key].append(now)

            return fn(*args, **kwargs)
        return wrapper
    return decorator
