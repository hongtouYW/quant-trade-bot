"""日志配置模块 (Spec §20)
统一日志格式，支持:
  - 控制台输出
  - 文件轮转
  - 按级别分流
"""
import os
import logging
from logging.handlers import RotatingFileHandler


def setup_logging(log_dir='logs', level=logging.INFO, max_bytes=10*1024*1024, backup_count=5):
    """配置全局日志

    Args:
        log_dir: 日志目录
        level: 日志级别
        max_bytes: 单文件最大字节数 (默认10MB)
        backup_count: 保留文件数
    """
    os.makedirs(log_dir, exist_ok=True)

    fmt = '%(asctime)s [%(levelname)s] %(name)s: %(message)s'
    formatter = logging.Formatter(fmt)

    root = logging.getLogger()
    root.setLevel(level)

    # 清除已有handler
    root.handlers.clear()

    # 控制台
    console = logging.StreamHandler()
    console.setLevel(level)
    console.setFormatter(formatter)
    root.addHandler(console)

    # 主日志文件 (轮转)
    main_handler = RotatingFileHandler(
        os.path.join(log_dir, 'quant_bot.log'),
        maxBytes=max_bytes,
        backupCount=backup_count,
    )
    main_handler.setLevel(level)
    main_handler.setFormatter(formatter)
    root.addHandler(main_handler)

    # 错误日志单独文件
    error_handler = RotatingFileHandler(
        os.path.join(log_dir, 'error.log'),
        maxBytes=max_bytes,
        backupCount=backup_count,
    )
    error_handler.setLevel(logging.ERROR)
    error_handler.setFormatter(formatter)
    root.addHandler(error_handler)

    # 交易日志
    trade_logger = logging.getLogger('trades')
    trade_handler = RotatingFileHandler(
        os.path.join(log_dir, 'trades.log'),
        maxBytes=max_bytes,
        backupCount=backup_count,
    )
    trade_handler.setFormatter(formatter)
    trade_logger.addHandler(trade_handler)

    logging.getLogger('urllib3').setLevel(logging.WARNING)
    logging.getLogger('ccxt').setLevel(logging.WARNING)

    return root
