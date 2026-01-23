#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
并发安全保护模块
提供线程安全的交易操作和资源管理
"""

import threading
import time
import queue
import weakref
from typing import Dict, Any, Optional, Callable, List
from dataclasses import dataclass
from datetime import datetime
import logging
from functools import wraps
import contextlib
from collections import defaultdict


@dataclass
class LockInfo:
    """锁信息"""
    name: str
    thread_id: str
    acquired_at: datetime
    lock_type: str  # 'read', 'write', 'exclusive'


class ReadWriteLock:
    """读写锁实现"""
    
    def __init__(self, name: str = "unnamed"):
        self.name = name
        self._read_ready = threading.Condition(threading.RLock())
        self._readers = 0
        self.logger = logging.getLogger(f"rwlock_{name}")
    
    def acquire_read(self, timeout: Optional[float] = None):
        """获取读锁"""
        acquired = self._read_ready.acquire(timeout=timeout)
        if not acquired:
            raise TimeoutError(f"获取读锁超时: {self.name}")
        
        try:
            self._readers += 1
            self.logger.debug(f"获取读锁: {threading.current_thread().name}, 读者数: {self._readers}")
            return True
        except:
            self._read_ready.release()
            raise
    
    def release_read(self):
        """释放读锁"""
        with self._read_ready:
            self._readers -= 1
            self.logger.debug(f"释放读锁: {threading.current_thread().name}, 读者数: {self._readers}")
            if self._readers == 0:
                self._read_ready.notifyAll()
    
    def acquire_write(self, timeout: Optional[float] = None):
        """获取写锁"""
        acquired = self._read_ready.acquire(timeout=timeout)
        if not acquired:
            raise TimeoutError(f"获取写锁超时: {self.name}")
        
        try:
            # 等待所有读者完成
            start_time = time.time()
            while self._readers > 0:
                if timeout and (time.time() - start_time) > timeout:
                    raise TimeoutError(f"等待读者完成超时: {self.name}")
                self._read_ready.wait(timeout=0.1)
            
            self.logger.debug(f"获取写锁: {threading.current_thread().name}")
            return True
        except:
            self._read_ready.release()
            raise
    
    def release_write(self):
        """释放写锁"""
        self.logger.debug(f"释放写锁: {threading.current_thread().name}")
        self._read_ready.release()
    
    @contextlib.contextmanager
    def read_lock(self, timeout: Optional[float] = None):
        """读锁上下文管理器"""
        self.acquire_read(timeout)
        try:
            yield
        finally:
            self.release_read()
    
    @contextlib.contextmanager
    def write_lock(self, timeout: Optional[float] = None):
        """写锁上下文管理器"""
        self.acquire_write(timeout)
        try:
            yield
        finally:
            self.release_write()


class ThreadSafeCounter:
    """线程安全计数器"""
    
    def __init__(self, initial_value: int = 0):
        self._value = initial_value
        self._lock = threading.Lock()
    
    def increment(self, delta: int = 1) -> int:
        """增加计数"""
        with self._lock:
            self._value += delta
            return self._value
    
    def decrement(self, delta: int = 1) -> int:
        """减少计数"""
        with self._lock:
            self._value -= delta
            return self._value
    
    def get(self) -> int:
        """获取当前值"""
        with self._lock:
            return self._value
    
    def set(self, value: int) -> int:
        """设置值"""
        with self._lock:
            self._value = value
            return self._value


class ThreadSafeDict:
    """线程安全字典"""
    
    def __init__(self):
        self._data = {}
        self._lock = ReadWriteLock("thread_safe_dict")
    
    def get(self, key: str, default=None):
        """获取值"""
        with self._lock.read_lock():
            return self._data.get(key, default)
    
    def set(self, key: str, value: Any):
        """设置值"""
        with self._lock.write_lock():
            self._data[key] = value
    
    def update(self, updates: Dict[str, Any]):
        """批量更新"""
        with self._lock.write_lock():
            self._data.update(updates)
    
    def delete(self, key: str):
        """删除键"""
        with self._lock.write_lock():
            self._data.pop(key, None)
    
    def keys(self):
        """获取所有键"""
        with self._lock.read_lock():
            return list(self._data.keys())
    
    def items(self):
        """获取所有项"""
        with self._lock.read_lock():
            return list(self._data.items())
    
    def copy(self):
        """复制字典"""
        with self._lock.read_lock():
            return self._data.copy()


class ResourcePool:
    """线程安全的资源池"""
    
    def __init__(self, max_size: int = 10):
        self.max_size = max_size
        self._pool = queue.Queue(maxsize=max_size)
        self._created_count = 0
        self._active_count = 0
        self._lock = threading.Lock()
        self.logger = logging.getLogger("resource_pool")
    
    def create_resource(self):
        """创建新资源 - 子类需要实现"""
        raise NotImplementedError("子类必须实现 create_resource 方法")
    
    def destroy_resource(self, resource):
        """销毁资源 - 子类可以重写"""
        pass
    
    def acquire(self, timeout: Optional[float] = None) -> Any:
        """获取资源"""
        try:
            # 尝试从池中获取资源
            resource = self._pool.get_nowait()
            with self._lock:
                self._active_count += 1
            self.logger.debug(f"从池中获取资源, 活跃数: {self._active_count}")
            return resource
        
        except queue.Empty:
            # 池为空，尝试创建新资源
            with self._lock:
                if self._created_count < self.max_size:
                    resource = self.create_resource()
                    self._created_count += 1
                    self._active_count += 1
                    self.logger.debug(f"创建新资源, 总数: {self._created_count}, 活跃数: {self._active_count}")
                    return resource
            
            # 无法创建新资源，等待现有资源
            try:
                resource = self._pool.get(timeout=timeout)
                with self._lock:
                    self._active_count += 1
                self.logger.debug(f"等待获取资源, 活跃数: {self._active_count}")
                return resource
            except queue.Empty:
                raise TimeoutError("获取资源超时")
    
    def release(self, resource):
        """释放资源"""
        try:
            self._pool.put_nowait(resource)
            with self._lock:
                self._active_count -= 1
            self.logger.debug(f"释放资源回池, 活跃数: {self._active_count}")
        except queue.Full:
            # 池已满，销毁资源
            self.destroy_resource(resource)
            with self._lock:
                self._created_count -= 1
                self._active_count -= 1
            self.logger.debug(f"池已满，销毁资源, 总数: {self._created_count}")
    
    @contextlib.contextmanager
    def get_resource(self, timeout: Optional[float] = None):
        """资源上下文管理器"""
        resource = self.acquire(timeout)
        try:
            yield resource
        finally:
            self.release(resource)
    
    def get_stats(self) -> Dict[str, int]:
        """获取池统计"""
        with self._lock:
            return {
                'pool_size': self._pool.qsize(),
                'created_count': self._created_count,
                'active_count': self._active_count,
                'max_size': self.max_size
            }


class ConcurrencyManager:
    """并发管理器"""
    
    def __init__(self):
        self.locks = {}
        self.counters = {}
        self.resource_pools = {}
        self.lock_registry = ThreadSafeDict()
        self.active_operations = ThreadSafeDict()
        self._global_lock = threading.RLock()
        self.logger = logging.getLogger("concurrency_manager")
        
        # 死锁检测相关
        self.lock_dependencies = defaultdict(set)
        self.thread_locks = defaultdict(set)
    
    def get_lock(self, name: str, lock_type: str = "read_write") -> ReadWriteLock:
        """获取命名锁"""
        with self._global_lock:
            if name not in self.locks:
                if lock_type == "read_write":
                    self.locks[name] = ReadWriteLock(name)
                else:
                    self.locks[name] = threading.RLock()
                
                self.logger.debug(f"创建锁: {name} ({lock_type})")
            
            return self.locks[name]
    
    def get_counter(self, name: str) -> ThreadSafeCounter:
        """获取命名计数器"""
        with self._global_lock:
            if name not in self.counters:
                self.counters[name] = ThreadSafeCounter()
                self.logger.debug(f"创建计数器: {name}")
            
            return self.counters[name]
    
    def register_resource_pool(self, name: str, pool: ResourcePool):
        """注册资源池"""
        with self._global_lock:
            self.resource_pools[name] = pool
            self.logger.info(f"注册资源池: {name}")
    
    def get_resource_pool(self, name: str) -> Optional[ResourcePool]:
        """获取资源池"""
        with self._global_lock:
            return self.resource_pools.get(name)
    
    def start_operation(self, operation_id: str, operation_type: str, metadata: Dict[str, Any] = None):
        """开始操作"""
        if metadata is None:
            metadata = {}
        
        operation_info = {
            'id': operation_id,
            'type': operation_type,
            'thread_id': threading.current_thread().ident,
            'start_time': datetime.now(),
            'metadata': metadata
        }
        
        self.active_operations.set(operation_id, operation_info)
        self.logger.debug(f"开始操作: {operation_id} ({operation_type})")
    
    def end_operation(self, operation_id: str):
        """结束操作"""
        operation_info = self.active_operations.get(operation_id)
        if operation_info:
            duration = datetime.now() - operation_info['start_time']
            self.logger.debug(f"结束操作: {operation_id}, 耗时: {duration.total_seconds():.3f}s")
            self.active_operations.delete(operation_id)
    
    def detect_deadlock(self) -> List[str]:
        """检测死锁"""
        warnings = []
        
        # 简单的死锁检测逻辑
        current_thread = threading.current_thread().ident
        
        # 检查长时间运行的操作
        for op_id, op_info in self.active_operations.items():
            duration = datetime.now() - op_info['start_time']
            if duration.total_seconds() > 30:  # 超过30秒
                warnings.append(f"操作 {op_id} 运行时间过长: {duration.total_seconds():.1f}s")
        
        return warnings
    
    def get_concurrency_stats(self) -> Dict[str, Any]:
        """获取并发统计"""
        with self._global_lock:
            stats = {
                'locks_count': len(self.locks),
                'counters_count': len(self.counters),
                'resource_pools_count': len(self.resource_pools),
                'active_operations': len(self.active_operations.keys()),
                'resource_pool_stats': {}
            }
            
            # 资源池统计
            for name, pool in self.resource_pools.items():
                stats['resource_pool_stats'][name] = pool.get_stats()
        
        return stats


def thread_safe(lock_name: str = None, timeout: float = 10.0):
    """线程安全装饰器"""
    def decorator(func: Callable) -> Callable:
        # 使用函数名作为默认锁名
        actual_lock_name = lock_name or f"{func.__module__}.{func.__name__}"
        
        @wraps(func)
        def wrapper(*args, **kwargs):
            manager = get_global_concurrency_manager()
            lock = manager.get_lock(actual_lock_name)
            
            # 如果是读写锁，默认使用写锁
            if isinstance(lock, ReadWriteLock):
                with lock.write_lock(timeout=timeout):
                    return func(*args, **kwargs)
            else:
                acquired = lock.acquire(timeout=timeout)
                if not acquired:
                    raise TimeoutError(f"获取锁超时: {actual_lock_name}")
                try:
                    return func(*args, **kwargs)
                finally:
                    lock.release()
        
        return wrapper
    return decorator


def concurrent_operation(operation_type: str, operation_id: str = None):
    """并发操作装饰器"""
    def decorator(func: Callable) -> Callable:
        @wraps(func)
        def wrapper(*args, **kwargs):
            manager = get_global_concurrency_manager()
            
            # 生成操作ID
            actual_operation_id = operation_id or f"{func.__name__}_{int(time.time() * 1000)}"
            
            manager.start_operation(actual_operation_id, operation_type)
            try:
                result = func(*args, **kwargs)
                return result
            finally:
                manager.end_operation(actual_operation_id)
        
        return wrapper
    return decorator


# 全局并发管理器
_global_concurrency_manager = None


def get_global_concurrency_manager() -> ConcurrencyManager:
    """获取全局并发管理器"""
    global _global_concurrency_manager
    if _global_concurrency_manager is None:
        _global_concurrency_manager = ConcurrencyManager()
    return _global_concurrency_manager


def initialize_concurrency_protection():
    """初始化并发保护"""
    manager = get_global_concurrency_manager()
    
    # 创建常用的锁
    manager.get_lock("trade_execution")
    manager.get_lock("balance_update")
    manager.get_lock("order_management")
    manager.get_lock("config_access")
    
    # 创建计数器
    manager.get_counter("active_trades")
    manager.get_counter("api_requests")
    
    logging.getLogger("concurrency_manager").info("并发保护已初始化")


# 示例资源池实现
class DatabaseConnectionPool(ResourcePool):
    """数据库连接池示例"""
    
    def __init__(self, max_connections: int = 5):
        super().__init__(max_connections)
        self.connection_params = {}
    
    def create_resource(self):
        """创建数据库连接"""
        # 这里应该创建真实的数据库连接
        # 为了演示，我们返回一个模拟连接
        connection_id = f"conn_{int(time.time() * 1000)}_{threading.current_thread().ident}"
        self.logger.info(f"创建数据库连接: {connection_id}")
        return {'id': connection_id, 'created_at': datetime.now()}
    
    def destroy_resource(self, resource):
        """销毁数据库连接"""
        self.logger.info(f"销毁数据库连接: {resource['id']}")


def main():
    """演示并发安全功能"""
    print("🔒 并发安全保护系统演示")
    print("=" * 40)
    
    # 初始化并发保护
    initialize_concurrency_protection()
    manager = get_global_concurrency_manager()
    
    # 演示线程安全装饰器
    @thread_safe("demo_function")
    @concurrent_operation("demo")
    def safe_function(value: int) -> int:
        time.sleep(0.1)  # 模拟工作
        return value * 2
    
    # 演示多线程执行
    import concurrent.futures
    
    print("🧵 多线程执行演示...")
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as executor:
        futures = [executor.submit(safe_function, i) for i in range(10)]
        results = [future.result() for future in concurrent.futures.as_completed(futures)]
    
    print(f"✅ 执行完成，结果: {sorted(results)}")
    
    # 演示资源池
    print("\n💾 资源池演示...")
    db_pool = DatabaseConnectionPool(max_connections=3)
    manager.register_resource_pool("database", db_pool)
    
    def use_database():
        pool = manager.get_resource_pool("database")
        with pool.get_resource(timeout=5.0) as connection:
            print(f"使用连接: {connection['id']}")
            time.sleep(0.2)
    
    # 并发使用资源池
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as executor:
        futures = [executor.submit(use_database) for _ in range(8)]
        for future in concurrent.futures.as_completed(futures):
            future.result()
    
    # 显示统计
    stats = manager.get_concurrency_stats()
    print(f"\n📊 并发统计:")
    for key, value in stats.items():
        print(f"   {key}: {value}")


if __name__ == '__main__':
    main()