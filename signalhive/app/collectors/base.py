"""
Base collector interface for all platform collectors.
"""
from abc import ABC, abstractmethod
from typing import AsyncIterator
from dataclasses import dataclass
from datetime import datetime
from enum import Enum


class HealthStatus(Enum):
    HEALTHY = "healthy"
    DEGRADED = "degraded"
    DOWN = "down"


@dataclass
class RawMessage:
    channel_id: int
    platform: str
    author_name: str
    message_text: str
    message_url: str | None
    timestamp: datetime


class BaseCollector(ABC):
    @abstractmethod
    async def connect(self) -> bool:
        """Establish connection to platform."""

    @abstractmethod
    async def listen(self) -> AsyncIterator[RawMessage]:
        """Yield messages as they arrive."""

    @abstractmethod
    async def health_check(self) -> HealthStatus:
        """Return current health. Called every 5 minutes."""

    @abstractmethod
    async def disconnect(self) -> None:
        """Graceful shutdown."""
