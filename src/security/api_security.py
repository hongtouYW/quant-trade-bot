#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
API安全和限流机制
提供完整的API保护、限流、熔断功能
"""

import time
import threading
from collections import defaultdict, deque
from datetime import datetime, timedelta
import logging
from functools import wraps
from typing import Dict, Optional, Any
from dataclasses import dataclass


@dataclass
class RateLimit:
    """限流配置"""
    max_requests: int
    window_seconds: int
    burst_limit: int = None


@dataclass
class CircuitBreakerConfig:
    """熔断器配置"""
    failure_threshold: int = 5
    recovery_timeout: int = 60
    half_open_max_calls: int = 3


class CircuitBreakerState:
    """熔断器状态"""
    CLOSED = "closed"      # 正常状态
    OPEN = "open"          # 熔断状态
    HALF_OPEN = "half_open"  # 半开状态


class CircuitBreaker:
    """熔断器实现"""
    
    def __init__(self, config: CircuitBreakerConfig):
        self.config = config
        self.state = CircuitBreakerState.CLOSED
        self.failure_count = 0
        self.last_failure_time = None
        self.half_open_calls = 0
        self.lock = threading.Lock()
        self.logger = logging.getLogger(f"circuit_breaker_{id(self)}")
    
    def can_proceed(self) -> bool:
        """检查是否可以执行请求"""
        with self.lock:
            if self.state == CircuitBreakerState.CLOSED:
                return True
            
            elif self.state == CircuitBreakerState.OPEN:
                if self._should_attempt_reset():
                    self.state = CircuitBreakerState.HALF_OPEN
                    self.half_open_calls = 0
                    self.logger.info("熔断器状态: OPEN -> HALF_OPEN")
                    return True
                return False
            
            elif self.state == CircuitBreakerState.HALF_OPEN:
                if self.half_open_calls < self.config.half_open_max_calls:
                    self.half_open_calls += 1
                    return True
                return False
            
            return False
    
    def record_success(self):
        """记录成功请求"""
        with self.lock:
            if self.state == CircuitBreakerState.HALF_OPEN:
                self.state = CircuitBreakerState.CLOSED
                self.failure_count = 0
                self.logger.info("熔断器恢复: HALF_OPEN -> CLOSED")
            
            # 重置失败计数
            self.failure_count = max(0, self.failure_count - 1)
    
    def record_failure(self):
        """记录失败请求"""
        with self.lock:
            self.failure_count += 1
            self.last_failure_time = time.time()
            
            if self.state == CircuitBreakerState.HALF_OPEN:
                self.state = CircuitBreakerState.OPEN
                self.logger.warning("熔断器重新打开: HALF_OPEN -> OPEN")
            
            elif self.failure_count >= self.config.failure_threshold:
                self.state = CircuitBreakerState.OPEN
                self.logger.warning(f"熔断器打开: 失败次数 {self.failure_count}")
    
    def _should_attempt_reset(self) -> bool:
        """检查是否应该尝试重置"""
        if not self.last_failure_time:
            return True
        return time.time() - self.last_failure_time >= self.config.recovery_timeout
    
    def get_state(self) -> str:
        """获取当前状态"""
        return self.state


class RateLimiter:
    """限流器实现"""
    
    def __init__(self, config: RateLimit):
        self.config = config
        self.requests = deque()
        self.lock = threading.Lock()
        self.logger = logging.getLogger(f"rate_limiter_{id(self)}")
    
    def is_allowed(self, identifier: str) -> bool:
        """检查是否允许请求"""
        with self.lock:
            now = time.time()
            window_start = now - self.config.window_seconds
            
            # 清理过期请求
            while self.requests and self.requests[0] < window_start:
                self.requests.popleft()
            
            # 检查请求数量
            if len(self.requests) >= self.config.max_requests:
                self.logger.warning(f"限流触发: {identifier} 超过 {self.config.max_requests}/s")
                return False
            
            # 记录新请求
            self.requests.append(now)
            return True
    
    def get_stats(self) -> Dict[str, Any]:
        """获取限流统计"""
        with self.lock:
            now = time.time()
            window_start = now - self.config.window_seconds
            
            # 清理过期请求
            while self.requests and self.requests[0] < window_start:
                self.requests.popleft()
            
            return {
                'current_requests': len(self.requests),
                'max_requests': self.config.max_requests,
                'window_seconds': self.config.window_seconds,
                'remaining': max(0, self.config.max_requests - len(self.requests))
            }


class APISecurityManager:
    """API安全管理器"""
    
    def __init__(self):
        self.rate_limiters: Dict[str, RateLimiter] = {}
        self.circuit_breakers: Dict[str, CircuitBreaker] = {}
        self.security_events = deque(maxlen=1000)  # 保存最近1000个安全事件
        self.lock = threading.Lock()
        self.logger = logging.getLogger("api_security")
        
        # 默认配置
        self.default_rate_limits = {
            'binance': RateLimit(max_requests=10, window_seconds=1),
            'bitget': RateLimit(max_requests=8, window_seconds=1),
            'telegram': RateLimit(max_requests=3, window_seconds=1),
            'default': RateLimit(max_requests=5, window_seconds=1)
        }
        
        self.default_circuit_breaker = CircuitBreakerConfig(
            failure_threshold=5,
            recovery_timeout=60,
            half_open_max_calls=3
        )
        
        self._initialize_components()
    
    def _initialize_components(self):
        """初始化组件"""
        for service, config in self.default_rate_limits.items():
            self.rate_limiters[service] = RateLimiter(config)
            self.circuit_breakers[service] = CircuitBreaker(self.default_circuit_breaker)
    
    def check_request_permission(self, service: str, identifier: str) -> Dict[str, Any]:
        """检查请求权限"""
        service = service.lower()
        
        # 获取或创建限流器和熔断器
        if service not in self.rate_limiters:
            self.rate_limiters[service] = RateLimiter(self.default_rate_limits.get('default'))
            self.circuit_breakers[service] = CircuitBreaker(self.default_circuit_breaker)
        
        rate_limiter = self.rate_limiters[service]
        circuit_breaker = self.circuit_breakers[service]
        
        # 检查熔断器状态
        if not circuit_breaker.can_proceed():
            self._log_security_event('circuit_breaker_blocked', service, identifier)
            return {
                'allowed': False,
                'reason': 'circuit_breaker_open',
                'circuit_breaker_state': circuit_breaker.get_state(),
                'retry_after': self.default_circuit_breaker.recovery_timeout
            }
        
        # 检查限流
        if not rate_limiter.is_allowed(identifier):
            self._log_security_event('rate_limit_exceeded', service, identifier)
            return {
                'allowed': False,
                'reason': 'rate_limit_exceeded',
                'rate_limit_stats': rate_limiter.get_stats()
            }
        
        self._log_security_event('request_allowed', service, identifier)
        return {
            'allowed': True,
            'rate_limit_stats': rate_limiter.get_stats(),
            'circuit_breaker_state': circuit_breaker.get_state()
        }
    
    def record_request_result(self, service: str, success: bool, error: Exception = None):
        """记录请求结果"""
        service = service.lower()
        
        if service not in self.circuit_breakers:
            return
        
        circuit_breaker = self.circuit_breakers[service]
        
        if success:
            circuit_breaker.record_success()
        else:
            circuit_breaker.record_failure()
            self._log_security_event('request_failed', service, str(error))
    
    def _log_security_event(self, event_type: str, service: str, details: str):
        """记录安全事件"""
        event = {
            'timestamp': datetime.now(),
            'type': event_type,
            'service': service,
            'details': details
        }
        
        with self.lock:
            self.security_events.append(event)
        
        self.logger.info(f"安全事件: {event_type} | 服务: {service} | 详情: {details}")
    
    def get_security_stats(self) -> Dict[str, Any]:
        """获取安全统计"""
        stats = {}
        
        for service in self.rate_limiters.keys():
            rate_limiter = self.rate_limiters[service]
            circuit_breaker = self.circuit_breakers[service]
            
            stats[service] = {
                'rate_limit': rate_limiter.get_stats(),
                'circuit_breaker': {
                    'state': circuit_breaker.get_state(),
                    'failure_count': circuit_breaker.failure_count
                }
            }
        
        # 最近安全事件统计
        recent_events = list(self.security_events)[-100:]  # 最近100个事件
        event_counts = defaultdict(int)
        for event in recent_events:
            event_counts[event['type']] += 1
        
        stats['recent_events'] = dict(event_counts)
        stats['total_events'] = len(self.security_events)
        
        return stats
    
    def reset_service_limits(self, service: str):
        """重置服务限制"""
        service = service.lower()
        
        if service in self.rate_limiters:
            self.rate_limiters[service] = RateLimiter(
                self.default_rate_limits.get(service, self.default_rate_limits['default'])
            )
        
        if service in self.circuit_breakers:
            self.circuit_breakers[service] = CircuitBreaker(self.default_circuit_breaker)
        
        self.logger.info(f"已重置服务限制: {service}")


def api_security_decorator(service: str):
    """API安全装饰器"""
    def decorator(func):
        @wraps(func)
        def wrapper(*args, **kwargs):
            # 获取安全管理器实例
            security_manager = getattr(wrapper, '_security_manager', None)
            if not security_manager:
                # 如果没有设置安全管理器，直接执行
                return func(*args, **kwargs)
            
            # 生成请求标识符
            identifier = f"{func.__name__}_{int(time.time())}"
            
            # 检查权限
            permission = security_manager.check_request_permission(service, identifier)
            
            if not permission['allowed']:
                raise PermissionError(f"API请求被拒绝: {permission['reason']}")
            
            # 执行请求
            try:
                result = func(*args, **kwargs)
                security_manager.record_request_result(service, success=True)
                return result
            except Exception as e:
                security_manager.record_request_result(service, success=False, error=e)
                raise
        
        return wrapper
    return decorator


# 全局安全管理器实例
global_security_manager = APISecurityManager()


def set_global_security_manager(manager: APISecurityManager):
    """设置全局安全管理器"""
    global global_security_manager
    global_security_manager = manager


def get_global_security_manager() -> APISecurityManager:
    """获取全局安全管理器"""
    return global_security_manager


# 为装饰器设置安全管理器
def setup_security_for_function(func, security_manager: APISecurityManager):
    """为函数设置安全管理器"""
    func._security_manager = security_manager


if __name__ == '__main__':
    # 测试代码
    import time
    
    # 创建安全管理器
    security = APISecurityManager()
    
    # 测试限流
    print("🔒 测试API安全机制")
    print("=" * 40)
    
    print("📊 测试限流机制...")
    for i in range(12):
        permission = security.check_request_permission('binance', f'test_{i}')
        if permission['allowed']:
            print(f"  ✅ 请求 {i+1}: 允许")
        else:
            print(f"  ❌ 请求 {i+1}: 被拒绝 - {permission['reason']}")
    
    print("\n🔄 测试熔断器...")
    # 模拟失败请求
    for i in range(6):
        security.record_request_result('test_service', success=False, error=Exception(f"错误 {i+1}"))
        print(f"  💥 模拟失败 {i+1}")
    
    # 检查熔断器状态
    permission = security.check_request_permission('test_service', 'test_after_failures')
    print(f"  🚫 熔断器状态: {permission.get('circuit_breaker_state', 'unknown')}")
    
    print("\n📈 安全统计:")
    stats = security.get_security_stats()
    for service, service_stats in stats.items():
        if isinstance(service_stats, dict) and 'rate_limit' in service_stats:
            print(f"  📊 {service}:")
            print(f"    限流: {service_stats['rate_limit']['current_requests']}/{service_stats['rate_limit']['max_requests']}")
            print(f"    熔断: {service_stats['circuit_breaker']['state']}")