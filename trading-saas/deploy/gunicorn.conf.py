# Gunicorn Configuration for Trading SaaS
import os

# Server socket
bind = "127.0.0.1:5200"

# Worker processes - single worker for WebSocket + SocketIO
# gevent handles concurrency via greenlets (coroutines)
workers = 1
worker_class = "gevent"
timeout = 120
keepalive = 5

# Logging - ensure directory exists
_log_dir = "/opt/trading-saas/logs"
os.makedirs(_log_dir, exist_ok=True)
accesslog = f"{_log_dir}/access.log"
errorlog = f"{_log_dir}/error.log"
loglevel = "info"

# Process naming
proc_name = "trading-saas"

# Restart workers after this many requests (prevent memory leaks)
max_requests = 5000
max_requests_jitter = 100

# Preload app for faster worker startup
preload_app = True
