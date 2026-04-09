"""
模拟下单器 - FR-020
Phase 1 唯一执行器, 不真实下单
"""
import time
from datetime import datetime, timezone, timedelta
from executor.base import ExecutorBase
from data.market_data import market_data
from risk.circuit_breaker import circuit_breaker
from core.constants import (
    PAPER_SLIPPAGE_PCT, PAPER_TAKER_FEE_PCT,
    TIER1_TAKE_PROFIT_LADDER, get_leverage_tier,
)
from core.logger import get_logger

logger = get_logger('paper_executor')


class PaperExecutor(ExecutorBase):
    """
    模拟盘执行器
    - 使用实时价格 (从 market_data 缓存)
    - 模拟滑点 0.05%
    - 模拟手续费 0.05% (Binance VIP 0)
    - 所有操作写入内存 (生产写 DB)
    """

    def __init__(self):
        # 内存持仓 (生产用 DB)
        self._positions = {}  # position_id -> dict
        self._trades = []     # 所有交易记录
        self._next_id = 1

    async def open_position(self, symbol: str, direction: str,
                            tier: str, margin: float, leverage: int,
                            stop_loss_roi: float, signal_id: int = None) -> dict:
        """模拟开仓"""
        # 1. 获取实时价格
        price = market_data.get_price(symbol)
        if price <= 0:
            logger.warning("paper.no_price", symbol=symbol)
            return {'error': 'no_price'}

        # 2. 模拟滑点
        if direction == 'long':
            entry_price = price * (1 + PAPER_SLIPPAGE_PCT)
        else:
            entry_price = price * (1 - PAPER_SLIPPAGE_PCT)

        # 3. 计算数量和手续费
        notional = margin * leverage
        quantity = notional / entry_price
        fee = notional * PAPER_TAKER_FEE_PCT

        # 4. 计算止损价
        stop_distance = abs(stop_loss_roi) / leverage
        if direction == 'long':
            stop_loss_price = entry_price * (1 - stop_distance)
        else:
            stop_loss_price = entry_price * (1 + stop_distance)

        # 5. 计算最大持仓时间
        tier_config = get_leverage_tier(symbol)
        max_hold_hours = tier_config.get('max_hold_hours', 20)

        # 6. 生成记录
        pos_id = self._next_id
        self._next_id += 1
        now = datetime.now(timezone.utc)

        position = {
            'id': pos_id,
            'signal_id': signal_id,
            'mode': 'paper',
            'tier': tier,
            'symbol': symbol,
            'direction': direction,
            'leverage': leverage,
            'margin': margin,
            'entry_price': entry_price,
            'quantity': quantity,
            'stop_loss_price': stop_loss_price,
            'take_profit_levels': TIER1_TAKE_PROFIT_LADDER if tier == 'tier1' else [],
            'open_time': now,
            'max_hold_until': now + timedelta(hours=max_hold_hours),
            'fee_open': fee,
            'status': 'open',
        }

        self._positions[pos_id] = position
        self._trades.append({
            **position,
            'action': 'open',
            'timestamp': now,
        })

        logger.info("paper.opened",
                    id=pos_id, symbol=symbol, direction=direction,
                    entry=round(entry_price, 2), leverage=leverage,
                    margin=margin, stop=round(stop_loss_price, 2))

        return position

    async def close_position(self, position_id: int, reason: str) -> dict:
        """模拟平仓"""
        pos = self._positions.get(position_id)
        if not pos:
            return {'error': 'position_not_found'}

        # 获取当前价格
        price = market_data.get_price(pos['symbol'])
        if price <= 0:
            price = pos['entry_price']  # fallback

        # 模拟滑点
        if pos['direction'] == 'long':
            exit_price = price * (1 - PAPER_SLIPPAGE_PCT)
        else:
            exit_price = price * (1 + PAPER_SLIPPAGE_PCT)

        # 计算 PnL
        notional = pos['margin'] * pos['leverage']
        fee_close = notional * PAPER_TAKER_FEE_PCT
        total_fee = pos['fee_open'] + fee_close

        if pos['direction'] == 'long':
            pnl_raw = (exit_price - pos['entry_price']) / pos['entry_price'] * notional
        else:
            pnl_raw = (pos['entry_price'] - exit_price) / pos['entry_price'] * notional

        pnl = pnl_raw - total_fee
        pnl_pct = pnl / pos['margin'] if pos['margin'] > 0 else 0

        # 更新持仓
        now = datetime.now(timezone.utc)
        pos['status'] = 'closed'
        pos['exit_price'] = exit_price
        pos['pnl'] = round(pnl, 4)
        pos['pnl_pct'] = round(pnl_pct, 4)
        pos['fee_total'] = round(total_fee, 4)
        pos['close_time'] = now
        pos['close_reason'] = reason

        # 从活跃持仓移除
        del self._positions[position_id]

        # 记录交易
        self._trades.append({
            **pos,
            'action': 'close',
            'timestamp': now,
        })

        # 通知风控
        circuit_breaker.record_trade(pnl, pos['symbol'], now)

        logger.info("paper.closed",
                    id=position_id, symbol=pos['symbol'],
                    pnl=round(pnl, 2), pnl_pct=f"{pnl_pct:.1%}",
                    reason=reason)

        return pos

    async def check_positions(self):
        """
        检查所有持仓: 止损/止盈/超时
        由 engine 主循环每 30s 调用一次
        """
        now = datetime.now(timezone.utc)
        to_close = []

        for pos_id, pos in list(self._positions.items()):
            price = market_data.get_price(pos['symbol'])
            if price <= 0:
                continue

            # 更新未实现盈亏
            notional = pos['margin'] * pos['leverage']
            if pos['direction'] == 'long':
                unrealized = (price - pos['entry_price']) / pos['entry_price'] * notional
            else:
                unrealized = (pos['entry_price'] - price) / pos['entry_price'] * notional

            roi = unrealized / pos['margin'] if pos['margin'] > 0 else 0

            # 1. 止损检查
            if pos['direction'] == 'long' and price <= pos['stop_loss_price']:
                to_close.append((pos_id, 'stop_loss'))
            elif pos['direction'] == 'short' and price >= pos['stop_loss_price']:
                to_close.append((pos_id, 'stop_loss'))

            # 2. 超时检查
            elif now >= pos['max_hold_until']:
                to_close.append((pos_id, 'timeout'))

            # 3. 阶梯止盈 (简化: 达到最高阶梯全平)
            elif pos.get('take_profit_levels'):
                highest_tp = max(tp[0] for tp in pos['take_profit_levels'])
                if roi >= highest_tp:
                    to_close.append((pos_id, f'take_profit_{roi:.0%}'))

        # 执行平仓
        for pos_id, reason in to_close:
            await self.close_position(pos_id, reason)

    # === 查询方法 ===

    def get_open_positions(self) -> list:
        return list(self._positions.values())

    def get_open_count(self) -> int:
        return len(self._positions)

    def get_open_symbols(self) -> set:
        return {p['symbol'] for p in self._positions.values()}

    def get_trades(self, mode: str = None) -> list:
        trades = [t for t in self._trades if t['action'] == 'close']
        if mode:
            trades = [t for t in trades if t.get('mode') == mode]
        return trades

    def get_stats(self) -> dict:
        """汇总统计"""
        closed = self.get_trades()
        if not closed:
            return {'total': 0, 'wins': 0, 'losses': 0,
                    'win_rate': 0, 'total_pnl': 0}

        wins = [t for t in closed if t.get('pnl', 0) > 0]
        losses = [t for t in closed if t.get('pnl', 0) <= 0]

        total_pnl = sum(t.get('pnl', 0) for t in closed)
        avg_win = sum(t['pnl'] for t in wins) / len(wins) if wins else 0
        avg_loss = sum(t['pnl'] for t in losses) / len(losses) if losses else 0

        return {
            'total': len(closed),
            'wins': len(wins),
            'losses': len(losses),
            'win_rate': len(wins) / len(closed) if closed else 0,
            'total_pnl': round(total_pnl, 2),
            'avg_win': round(avg_win, 2),
            'avg_loss': round(avg_loss, 2),
            'profit_factor': abs(avg_win / avg_loss) if avg_loss != 0 else 0,
            'open_positions': self.get_open_count(),
        }
