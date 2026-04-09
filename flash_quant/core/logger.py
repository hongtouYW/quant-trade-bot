"""
Flash Quant - 结构化日志
NFR-004: 可观测性
"""
import logging
import json
import sys
from datetime import datetime


class JsonFormatter(logging.Formatter):
    """JSON 格式化器"""

    def format(self, record):
        log_data = {
            'timestamp': datetime.utcnow().isoformat() + 'Z',
            'level': record.levelname,
            'logger': record.name,
            'message': record.getMessage(),
        }

        # 添加额外字段 (structlog 风格)
        if hasattr(record, 'extra_fields'):
            log_data.update(record.extra_fields)

        if record.exc_info and record.exc_info[0]:
            log_data['exception'] = self.formatException(record.exc_info)

        return json.dumps(log_data, ensure_ascii=False, default=str)


class StructLogger:
    """简单的结构化日志包装器"""

    def __init__(self, name: str):
        self._logger = logging.getLogger(name)

    def _log(self, level, event, **kwargs):
        record = self._logger.makeRecord(
            self._logger.name, level, '', 0, event, (), None
        )
        record.extra_fields = kwargs
        self._logger.handle(record)

    def debug(self, event, **kwargs):
        self._log(logging.DEBUG, event, **kwargs)

    def info(self, event, **kwargs):
        self._log(logging.INFO, event, **kwargs)

    def warning(self, event, **kwargs):
        self._log(logging.WARNING, event, **kwargs)

    def error(self, event, **kwargs):
        self._log(logging.ERROR, event, **kwargs)

    def critical(self, event, **kwargs):
        self._log(logging.CRITICAL, event, **kwargs)


def setup_logging(level: str = 'INFO', json_format: bool = True):
    """初始化日志系统"""
    root = logging.getLogger()
    root.setLevel(getattr(logging, level.upper(), logging.INFO))

    # 清除已有 handler
    root.handlers.clear()

    handler = logging.StreamHandler(sys.stdout)
    if json_format:
        handler.setFormatter(JsonFormatter())
    else:
        handler.setFormatter(logging.Formatter(
            '%(asctime)s [%(levelname)s] %(name)s: %(message)s'
        ))
    root.addHandler(handler)


def get_logger(name: str) -> StructLogger:
    """获取模块级 logger"""
    return StructLogger(f'flash_quant.{name}')


# 默认 logger
logger = get_logger('main')
