bind = '127.0.0.1:5300'
workers = 2
worker_class = 'sync'
timeout = 120
accesslog = '/var/log/signalhive/gunicorn_access.log'
errorlog = '/var/log/signalhive/gunicorn_error.log'
loglevel = 'info'
