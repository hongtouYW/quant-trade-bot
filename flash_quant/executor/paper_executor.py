"""
模拟下单器 - FR-020
Phase 1 唯一执行器, 不真实下单, 所有操作写 DB
"""
from datetime import datetime, timezone, timedelta
from executor.base import ExecutorBase
from data.market_data import market_data
from risk.circuit_breaker import circuit_breaker
from models.db_ops import (
    insert_trade, update_trade, insert_position,
    delete_position_by_trade, get_open_positions, get_open_trades,
    write_audit,
)
from core.constants import (
    PAPER_SLIPPAGE_PCT, PAPER_TAKER_FEE_PCT,
    TIER1_TAKE_PROFIT_LADDER, get_leverage_tier,
)
from core.logger import get_logger

logger = get_logger('paper_executor')

MYT = timezone(timedelta(hours=8))


class PaperExecutor(ExecutorBase):

    async def open_position(self, symbol: str, direction: str,
                            tier: str, margin: float, leverage: int,
                            stop_loss_roi: float, signal_id: int = None) -> dict:
        price = market_data.get_price(symbol)
        if price <= 0:
            logger.warning("paper.no_price", symbol=symbol)
            return {'error': 'no_price'}

        # 滑点
        if direction == 'long':
            entry_price = price * (1 + PAPER_SLIPPAGE_PCT)
        else:
            entry_price = price * (1 - PAPER_SLIPPAGE_PCT)

        notional = margin * leverage
        quantity = notional / entry_price
        fee = notional * PAPER_TAKER_FEE_PCT

        # 止损价
        stop_distance = abs(stop_loss_roi) / leverage
        if direction == 'long':
            stop_loss_price = entry_price * (1 - stop_distance)
        else:
            stop_loss_price = entry_price * (1 + stop_distance)

        # 持仓时间
        tier_config = get_leverage_tier(symbol)
        max_hold = timedelta(hours=tier_config.get('max_hold_hours', 20))
        now = datetime.now(MYT)

        # 写 DB: trades
        trade_id = insert_trade({
            'signal_id': signal_id,
            'mode': 'paper',
            'tier': tier,
            'symbol': symbol,
            'direction': direction,
            'leverage': leverage,
            'margin': margin,
            'entry_price': entry_price,
            'quantity': quantity,
            'status': 'open',
            'open_time': now,
            'fee': fee,
        })

        # 写 DB: positions
        tp_levels = TIER1_TAKE_PROFIT_LADDER if tier == 'tier1' else []
        insert_position({
            'trade_id': trade_id,
            'symbol': symbol,
            'direction': direction,
            'leverage': leverage,
            'margin': margin,
            'entry_price': entry_price,
            'quantity': quantity,
            'stop_loss_price': stop_loss_price,
            'take_profit_levels': tp_levels,
            'open_time': now,
            'max_hold_until': now + max_hold,
        })

        write_audit('system', 'open_position', symbol,
                    {'trade_id': trade_id, 'direction': direction,
                     'leverage': leverage, 'margin': margin,
                     'entry': round(entry_price, 6), 'stop': round(stop_loss_price, 6)})

        logger.info("paper.opened",
                    id=trade_id, symbol=symbol, direction=direction,
                    entry=round(entry_price, 2), leverage=leverage, margin=margin)

        return {'trade_id': trade_id, 'entry_price': entry_price}

    async def close_position(self, trade_id: int, reason: str) -> dict:
        # 从 DB 读取交易
        from models.db_ops import get_trade_by_id
        trade = get_trade_by_id(trade_id)
        if not trade or trade['status'] != 'open':
            return {'error': 'trade_not_found_or_closed'}

        symbol = trade['symbol']
        direction = trade['direction']
        price = market_data.get_price(symbol)
        if price <= 0:
            price = trade['entry_price']

        # 滑点
        if direction == 'long':
            exit_price = price * (1 - PAPER_SLIPPAGE_PCT)
        else:
            exit_price = price * (1 + PAPER_SLIPPAGE_PCT)

        # PnL
        entry = trade['entry_price']
        notional = trade['margin'] * trade['leverage']
        close_fee = notional * PAPER_TAKER_FEE_PCT
        open_fee = trade.get('fee') or (notional * PAPER_TAKER_FEE_PCT)
        total_fee = open_fee + close_fee

        if direction == 'long':
            pnl_raw = (exit_price - entry) / entry * notional
        else:
            pnl_raw = (entry - exit_price) / entry * notional

        pnl = pnl_raw - total_fee
        pnl_pct = pnl / trade['margin'] if trade['margin'] > 0 else 0

        now = datetime.now(MYT)

        # 更新 DB: trades
        update_trade(trade_id, {
            'status': 'closed',
            'exit_price': exit_price,
            'close_time': now,
            'pnl': round(pnl, 4),
            'pnl_pct': round(pnl_pct, 4),
            'fee': round(total_fee, 4),
            'close_reason': reason,
        })

        # 删除 positions
        delete_position_by_trade(trade_id)

        # 通知风控
        circuit_breaker.record_trade(pnl, symbol, now)

        write_audit('system', 'close_position', symbol,
                    {'trade_id': trade_id, 'pnl': round(pnl, 2),
                     'reason': reason, 'exit': round(exit_price, 6)})

        logger.info("paper.closed",
                    id=trade_id, symbol=symbol,
                    pnl=round(pnl, 2), pnl_pct=f"{pnl_pct:.1%}", reason=reason)

        return {'trade_id': trade_id, 'pnl': pnl}

    async def check_positions(self):
        """检查止损/止盈/超时"""
        open_pos = get_open_positions()
        now = datetime.now(MYT)

        for pos in open_pos:
            trade_id = pos['trade_id']
            symbol = pos['symbol']
            direction = pos['direction']
            price = market_data.get_price(symbol)
            if price <= 0:
                continue

            # 止损
            sl = pos['stop_loss_price']
            if direction == 'long' and price <= sl:
                await self.close_position(trade_id, 'stop_loss')
                continue
            elif direction == 'short' and price >= sl:
                await self.close_position(trade_id, 'stop_loss')
                continue

            # 超时
            max_hold = pos.get('max_hold_until')
            if max_hold and now >= max_hold:
                await self.close_position(trade_id, 'timeout')
                continue

            # 阶梯止盈 (简化: 最高阶梯全平)
            entry = pos['entry_price']
            leverage = pos['leverage']
            margin = pos['margin']
            notional = margin * leverage
            if direction == 'long':
                roi = (price - entry) / entry * leverage
            else:
                roi = (entry - price) / entry * leverage

            tp_levels = pos.get('take_profit_levels')
            if tp_levels:
                if isinstance(tp_levels, str):
                    import json
                    tp_levels = json.loads(tp_levels)
                if tp_levels:
                    highest_tp = max(tp[0] for tp in tp_levels)
                    if roi >= highest_tp:
                        await self.close_position(trade_id, f'take_profit_{roi:.0%}')
                        continue

            # 更新 position 的 current_price 和 unrealized_pnl
            from models.db_ops import update_position
            if direction == 'long':
                upnl = (price - entry) / entry * notional
            else:
                upnl = (entry - price) / entry * notional
            update_position(pos['id'], {
                'current_price': price,
                'unrealized_pnl': round(upnl, 4),
            })
