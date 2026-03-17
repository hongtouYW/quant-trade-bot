import logging
import uuid
from datetime import datetime
from typing import Dict, List, Optional

from src.core.models import Position, Signal
from src.core.enums import Direction, FillType

logger = logging.getLogger(__name__)


class PortfolioManager:
    """持仓管理"""

    def __init__(self):
        self._positions: Dict[str, Position] = {}  # id -> Position

    @property
    def positions(self) -> Dict[str, Position]:
        return self._positions

    @property
    def count(self) -> int:
        return len(self._positions)

    def has_symbol(self, symbol: str) -> bool:
        return any(p.symbol == symbol for p in self._positions.values())

    def same_direction_count(self, direction: Direction) -> int:
        return sum(1 for p in self._positions.values() if p.direction == direction)

    def total_margin(self) -> float:
        return sum(p.margin * p.remaining_pct for p in self._positions.values())

    def open_position(self, signal: Signal, margin: float,
                      notional: float, quantity: float,
                      fill_type: FillType = FillType.MARKET) -> Position:
        """创建新持仓"""
        pos_id = str(uuid.uuid4())[:8]
        pos = Position(
            id=pos_id,
            symbol=signal.symbol,
            direction=signal.direction,
            strategy=signal.strategy,
            entry_price=signal.entry_price,
            margin=margin,
            notional=notional,
            quantity=quantity,
            stop_loss=signal.stop_loss,
            initial_stop=signal.stop_loss,
            take_profits=signal.take_profits,
            best_price=signal.entry_price,
            open_time=datetime.utcnow(),
            fill_type=fill_type,
        )
        self._positions[pos_id] = pos
        logger.info(f"Opened {signal.direction.name} {signal.symbol} "
                     f"@{signal.entry_price} margin={margin:.2f}")
        return pos

    def close_position(self, pos_id: str) -> Optional[Position]:
        """移除持仓"""
        pos = self._positions.pop(pos_id, None)
        if pos:
            logger.info(f"Closed {pos.symbol} id={pos_id}")
        return pos

    def partial_close(self, pos_id: str, close_pct: float):
        """部分平仓"""
        pos = self._positions.get(pos_id)
        if pos:
            pos.remaining_pct -= close_pct
            if pos.remaining_pct <= 0.01:
                self.close_position(pos_id)
            else:
                logger.info(f"Partial close {pos.symbol} {close_pct*100:.0f}%, "
                           f"remaining {pos.remaining_pct*100:.0f}%")

    def get_by_symbol(self, symbol: str) -> Optional[Position]:
        for p in self._positions.values():
            if p.symbol == symbol:
                return p
        return None
