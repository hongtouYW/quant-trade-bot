"""Simple Rate Limiter using in-memory store (upgrade to Redis later)"""
import time
from functools import wraps
from collections import defaultdict
from flask import jsonify, request
from flask_jwt_extended import get_jwt_identity


# In-memory rate limit store: {key: [timestamps]}
_rate_store = defaultdict(list)


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
            key = f"{user_key}:{fn.__name__}"

            now = time.time()
            cutoff = now - window_seconds

            # Clean old entries
            _rate_store[key] = [t for t in _rate_store[key] if t > cutoff]

            if len(_rate_store[key]) >= max_requests:
                return jsonify({'error': 'Rate limit exceeded'}), 429

            _rate_store[key].append(now)
            return fn(*args, **kwargs)
        return wrapper
    return decorator
