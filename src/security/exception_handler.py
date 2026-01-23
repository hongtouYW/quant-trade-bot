#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
异常处理和系统恢复能力
提供全面的异常捕获、恢复机制和系统监控
"""

import os
import sys
import time
import json
import threading
import traceback
import logging
from datetime import datetime, timedelta
from typing import Dict, Any, Optional, Callable, List
from dataclasses import dataclass, asdict
from functools import wraps
from enum import Enum
import pickle
import signal


class ErrorSeverity(Enum):
    """错误严重级别"""
    LOW = "low"           # 轻微错误，系统可继续运行
    MEDIUM = "medium"     # 中等错误，需要关注
    HIGH = "high"         # 严重错误，需要干预
    CRITICAL = "critical" # 致命错误，系统需要停止


class RecoveryStrategy(Enum):
    """恢复策略"""
    RETRY = "retry"                    # 重试
    FALLBACK = "fallback"              # 使用后备方案
    SKIP = "skip"                      # 跳过当前操作
    RESTART_COMPONENT = "restart"      # 重启组件
    SHUTDOWN = "shutdown"              # 安全关闭


@dataclass
class ErrorRecord:
    """错误记录"""
    timestamp: datetime
    error_type: str
    error_message: str
    severity: ErrorSeverity
    traceback: str
    component: str
    context: Dict[str, Any]
    recovery_strategy: RecoveryStrategy
    recovery_attempts: int = 0
    resolved: bool = False


@dataclass
class SystemHealth:
    """系统健康状态"""
    component: str
    status: str  # healthy, degraded, failed
    last_check: datetime
    error_count: int
    uptime: float
    metrics: Dict[str, Any]


class RecoveryManager:
    """恢复管理器"""
    
    def __init__(self, max_retries: int = 3, retry_delay: float = 1.0):
        self.max_retries = max_retries
        self.retry_delay = retry_delay
        self.recovery_handlers = {}
        self.fallback_handlers = {}
        self.logger = logging.getLogger("recovery_manager")
    
    def register_recovery_handler(self, error_type: type, handler: Callable):
        """注册恢复处理器"""
        self.recovery_handlers[error_type] = handler
        self.logger.info(f"注册恢复处理器: {error_type.__name__}")
    
    def register_fallback_handler(self, component: str, handler: Callable):
        """注册后备处理器"""
        self.fallback_handlers[component] = handler
        self.logger.info(f"注册后备处理器: {component}")
    
    def recover_from_error(self, error: Exception, component: str, context: Dict[str, Any]) -> bool:
        """从错误中恢复"""
        error_type = type(error)
        
        # 尝试特定错误类型的恢复
        if error_type in self.recovery_handlers:
            try:
                self.logger.info(f"尝试特定恢复: {error_type.__name__}")
                return self.recovery_handlers[error_type](error, context)
            except Exception as recovery_error:
                self.logger.error(f"恢复处理器失败: {recovery_error}")
        
        # 尝试组件后备方案
        if component in self.fallback_handlers:
            try:
                self.logger.info(f"使用后备方案: {component}")
                return self.fallback_handlers[component](error, context)
            except Exception as fallback_error:
                self.logger.error(f"后备处理器失败: {fallback_error}")
        
        return False
    
    def retry_with_backoff(self, func: Callable, *args, **kwargs) -> Any:
        """带退避的重试机制"""
        last_exception = None
        
        for attempt in range(self.max_retries + 1):
            try:
                return func(*args, **kwargs)
            except Exception as e:
                last_exception = e
                if attempt < self.max_retries:
                    delay = self.retry_delay * (2 ** attempt)  # 指数退避
                    self.logger.warning(f"重试 {attempt + 1}/{self.max_retries}, 延迟 {delay}s: {e}")
                    time.sleep(delay)
                else:
                    self.logger.error(f"重试失败，已达最大次数: {e}")
        
        raise last_exception


class ExceptionMonitor:
    """异常监控器"""
    
    def __init__(self, log_dir: str = "logs"):
        self.log_dir = log_dir
        self.error_log = os.path.join(log_dir, "errors.log")
        self.error_history = []
        self.error_stats = {}
        self.recovery_manager = RecoveryManager()
        self.system_health = {}
        self.alert_handlers = []
        self.lock = threading.Lock()
        
        # 创建日志目录
        os.makedirs(log_dir, exist_ok=True)
        
        # 设置日志记录
        self.logger = self._setup_logger()
        
        # 注册信号处理器
        self._setup_signal_handlers()
        
        # 启动健康检查线程
        self.health_check_thread = threading.Thread(target=self._health_check_loop, daemon=True)
        self.health_check_running = True
        self.health_check_thread.start()
    
    def _setup_logger(self) -> logging.Logger:
        """设置日志记录器"""
        logger = logging.getLogger("exception_monitor")
        logger.setLevel(logging.INFO)
        
        # 文件处理器
        file_handler = logging.FileHandler(self.error_log, encoding='utf-8')
        file_handler.setLevel(logging.ERROR)
        
        # 控制台处理器
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.WARNING)
        
        # 格式化器
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        file_handler.setFormatter(formatter)
        console_handler.setFormatter(formatter)
        
        logger.addHandler(file_handler)
        logger.addHandler(console_handler)
        
        return logger
    
    def _setup_signal_handlers(self):
        """设置信号处理器"""
        def signal_handler(signum, frame):
            self.logger.info(f"收到信号 {signum}, 开始安全关闭...")
            self.shutdown()
            sys.exit(0)
        
        signal.signal(signal.SIGINT, signal_handler)   # Ctrl+C
        signal.signal(signal.SIGTERM, signal_handler)  # 终止信号
    
    def record_error(self, error: Exception, component: str, 
                    context: Dict[str, Any] = None, 
                    severity: ErrorSeverity = ErrorSeverity.MEDIUM) -> ErrorRecord:
        """记录错误"""
        if context is None:
            context = {}
        
        error_record = ErrorRecord(
            timestamp=datetime.now(),
            error_type=type(error).__name__,
            error_message=str(error),
            severity=severity,
            traceback=traceback.format_exc(),
            component=component,
            context=context,
            recovery_strategy=self._determine_recovery_strategy(error, severity)
        )
        
        with self.lock:
            self.error_history.append(error_record)
            
            # 更新统计
            error_type = error_record.error_type
            if error_type not in self.error_stats:
                self.error_stats[error_type] = {'count': 0, 'last_occurrence': None}
            
            self.error_stats[error_type]['count'] += 1
            self.error_stats[error_type]['last_occurrence'] = error_record.timestamp
        
        # 记录日志
        self.logger.error(f"错误记录 [{component}]: {error_record.error_message}")
        
        # 发送警报
        self._send_alerts(error_record)
        
        # 尝试恢复
        self._attempt_recovery(error_record)
        
        return error_record
    
    def _determine_recovery_strategy(self, error: Exception, severity: ErrorSeverity) -> RecoveryStrategy:
        """确定恢复策略"""
        if severity == ErrorSeverity.CRITICAL:
            return RecoveryStrategy.SHUTDOWN
        elif severity == ErrorSeverity.HIGH:
            return RecoveryStrategy.RESTART_COMPONENT
        elif isinstance(error, (ConnectionError, TimeoutError)):
            return RecoveryStrategy.RETRY
        else:
            return RecoveryStrategy.FALLBACK
    
    def _attempt_recovery(self, error_record: ErrorRecord):
        """尝试恢复"""
        try:
            if error_record.recovery_strategy == RecoveryStrategy.RETRY:
                # 这里可以实现重试逻辑
                self.logger.info(f"计划重试: {error_record.component}")
            
            elif error_record.recovery_strategy == RecoveryStrategy.FALLBACK:
                # 这里可以实现后备方案
                self.logger.info(f"使用后备方案: {error_record.component}")
            
            elif error_record.recovery_strategy == RecoveryStrategy.SHUTDOWN:
                self.logger.critical("系统需要安全关闭")
                # 这里可以实现安全关闭逻辑
        
        except Exception as recovery_error:
            self.logger.error(f"恢复尝试失败: {recovery_error}")
    
    def _send_alerts(self, error_record: ErrorRecord):
        """发送警报"""
        for handler in self.alert_handlers:
            try:
                handler(error_record)
            except Exception as alert_error:
                self.logger.error(f"警报发送失败: {alert_error}")
    
    def register_alert_handler(self, handler: Callable[[ErrorRecord], None]):
        """注册警报处理器"""
        self.alert_handlers.append(handler)
        self.logger.info("注册警报处理器")
    
    def update_component_health(self, component: str, status: str, metrics: Dict[str, Any] = None):
        """更新组件健康状态"""
        if metrics is None:
            metrics = {}
        
        with self.lock:
            if component not in self.system_health:
                self.system_health[component] = SystemHealth(
                    component=component,
                    status=status,
                    last_check=datetime.now(),
                    error_count=0,
                    uptime=0,
                    metrics=metrics
                )
            else:
                health = self.system_health[component]
                health.status = status
                health.last_check = datetime.now()
                health.metrics.update(metrics)
    
    def _health_check_loop(self):
        """健康检查循环"""
        while self.health_check_running:
            try:
                self._perform_health_checks()
                time.sleep(30)  # 每30秒检查一次
            except Exception as e:
                self.logger.error(f"健康检查失败: {e}")
                time.sleep(60)  # 出错后延长检查间隔
    
    def _perform_health_checks(self):
        """执行健康检查"""
        current_time = datetime.now()
        
        with self.lock:
            for component, health in self.system_health.items():
                # 检查组件是否长时间未更新
                if current_time - health.last_check > timedelta(minutes=5):
                    health.status = "unknown"
                    self.logger.warning(f"组件 {component} 长时间未响应")
    
    def get_error_summary(self) -> Dict[str, Any]:
        """获取错误摘要"""
        with self.lock:
            recent_errors = [e for e in self.error_history 
                           if datetime.now() - e.timestamp < timedelta(hours=24)]
            
            return {
                'total_errors': len(self.error_history),
                'recent_errors_24h': len(recent_errors),
                'error_types': dict(self.error_stats),
                'system_health': {comp: asdict(health) for comp, health in self.system_health.items()}
            }
    
    def get_recovery_suggestions(self) -> List[str]:
        """获取恢复建议"""
        suggestions = []
        
        with self.lock:
            # 分析最近错误
            recent_errors = [e for e in self.error_history 
                           if datetime.now() - e.timestamp < timedelta(hours=1)]
            
            if len(recent_errors) > 10:
                suggestions.append("系统错误频率过高，建议检查系统资源和网络状态")
            
            # 分析错误类型
            error_types = {}
            for error in recent_errors:
                error_types[error.error_type] = error_types.get(error.error_type, 0) + 1
            
            for error_type, count in error_types.items():
                if count > 5:
                    suggestions.append(f"{error_type} 错误频发，建议针对性处理")
            
            # 分析组件健康
            for component, health in self.system_health.items():
                if health.status == "failed":
                    suggestions.append(f"组件 {component} 故障，需要重启或修复")
        
        return suggestions
    
    def shutdown(self):
        """安全关闭"""
        self.logger.info("开始系统关闭流程...")
        
        # 停止健康检查
        self.health_check_running = False
        
        # 保存错误历史
        self._save_error_history()
        
        self.logger.info("系统关闭完成")
    
    def _save_error_history(self):
        """保存错误历史"""
        try:
            history_file = os.path.join(self.log_dir, "error_history.pkl")
            with open(history_file, 'wb') as f:
                pickle.dump(self.error_history, f)
            self.logger.info("错误历史已保存")
        except Exception as e:
            self.logger.error(f"保存错误历史失败: {e}")


def exception_handler(component: str, severity: ErrorSeverity = ErrorSeverity.MEDIUM, 
                     monitor: ExceptionMonitor = None):
    """异常处理装饰器"""
    def decorator(func: Callable) -> Callable:
        @wraps(func)
        def wrapper(*args, **kwargs):
            try:
                return func(*args, **kwargs)
            except Exception as e:
                if monitor:
                    context = {
                        'function': func.__name__,
                        'args': str(args)[:100],  # 限制长度
                        'kwargs': str(kwargs)[:100]
                    }
                    monitor.record_error(e, component, context, severity)
                raise
        return wrapper
    return decorator


# 全局异常监控器
global_exception_monitor = ExceptionMonitor()


def setup_global_exception_handler():
    """设置全局异常处理器"""
    def handle_exception(exc_type, exc_value, exc_traceback):
        if issubclass(exc_type, KeyboardInterrupt):
            # 允许键盘中断
            sys.__excepthook__(exc_type, exc_value, exc_traceback)
            return
        
        # 记录未捕获的异常
        global_exception_monitor.record_error(
            exc_value, "global", 
            {'exc_type': exc_type.__name__},
            ErrorSeverity.CRITICAL
        )
    
    sys.excepthook = handle_exception


if __name__ == '__main__':
    # 测试代码
    print("🔧 测试异常处理和恢复系统")
    print("=" * 40)
    
    # 创建监控器
    monitor = ExceptionMonitor()
    
    # 注册警报处理器
    def print_alert(error_record: ErrorRecord):
        print(f"🚨 警报: {error_record.component} - {error_record.error_message}")
    
    monitor.register_alert_handler(print_alert)
    
    # 测试错误记录
    @exception_handler("test_component", ErrorSeverity.MEDIUM, monitor)
    def failing_function():
        raise ValueError("这是一个测试错误")
    
    try:
        failing_function()
    except:
        pass
    
    # 更新组件健康
    monitor.update_component_health("database", "healthy", {"connections": 10})
    monitor.update_component_health("api", "degraded", {"response_time": 2.5})
    
    # 获取摘要
    summary = monitor.get_error_summary()
    print(f"📊 错误摘要: {summary['total_errors']} 个总错误")
    print(f"📈 系统健康: {len(summary['system_health'])} 个组件")
    
    # 获取建议
    suggestions = monitor.get_recovery_suggestions()
    if suggestions:
        print("💡 恢复建议:")
        for suggestion in suggestions:
            print(f"  - {suggestion}")
    
    # 关闭
    monitor.shutdown()