import logging
import sys
from logging.handlers import RotatingFileHandler
from pathlib import Path


def setup_logger(config: dict) -> logging.Logger:
    """配置日志"""
    log_cfg = config.get('logging', {})
    level = getattr(logging, log_cfg.get('level', 'INFO').upper(), logging.INFO)
    log_file = log_cfg.get('file', 'logs/trading.log')
    max_size = log_cfg.get('max_size_mb', 100) * 1024 * 1024
    backup_count = log_cfg.get('backup_count', 10)

    # 确保日志目录存在
    Path(log_file).parent.mkdir(parents=True, exist_ok=True)

    formatter = logging.Formatter(
        '%(asctime)s | %(levelname)-8s | %(name)-25s | %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

    # 文件 handler
    file_handler = RotatingFileHandler(
        log_file, maxBytes=max_size, backupCount=backup_count, encoding='utf-8')
    file_handler.setFormatter(formatter)
    file_handler.setLevel(level)

    # 控制台 handler
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    console_handler.setLevel(level)

    # Root logger
    root = logging.getLogger()
    root.setLevel(level)
    root.addHandler(file_handler)
    root.addHandler(console_handler)

    # 降低 ccxt 日志级别
    logging.getLogger('ccxt').setLevel(logging.WARNING)

    return root
