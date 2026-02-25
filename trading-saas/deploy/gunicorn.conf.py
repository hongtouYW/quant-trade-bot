# Gunicorn Configuration for Trading SaaS
import os

# Server socket
bind = "127.0.0.1:5200"

# Worker processes (keep low for 4GB RAM server)
workers = 2
worker_class = "gthread"
threads = 2
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
max_requests = 1000
max_requests_jitter = 50

# Preload app for faster worker startup
preload_app = True
