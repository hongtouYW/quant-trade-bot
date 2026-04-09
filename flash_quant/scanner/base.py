"""
扫描器基类
"""
from abc import ABC, abstractmethod


class ScannerBase(ABC):

    @abstractmethod
    async def scan(self) -> list:
        """执行一次扫描, 返回信号列表"""
        pass

    @abstractmethod
    async def run(self):
        """主循环"""
        pass
