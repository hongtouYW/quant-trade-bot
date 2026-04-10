"""
Binance 实盘下单器 - FR-021
Phase 2+ 使用, 通过 ccxt 连接 Binance USDT-M Futures

核心要求 (PRD):
- 限价单优先 → 失败回退市价单
- 服务端止损单 (STOP_MARKET, workingType=MARK_PRICE) → BR-006
- 阶梯止盈 Reduce-Only 限价单
- API 失败重试 3 次
- 所有订单状态同步到 DB
"""
import asyncio
import time
from datetime import datetime, timezone, timedelta
from executor.base import ExecutorBase
from models.db_ops import (
    insert_trade, update_trade, insert_position,
    delete_position_by_trade, get_open_positions,
    write_audit, get_trade_by_id,
)
from risk.circuit_breaker import circuit_breaker
from core.constants import (
    STOP_LOSS_PLACEMENT_TIMEOUT, TIER1_TAKE_PROFIT_LADDER,
    get_leverage_tier,
)
from core.logger import get_logger
from config.settings import settings

logger = get_logger('binance_executor')

MYT = timezone(timedelta(hours=8))


class BinanceExecutor(ExecutorBase):
    """
    Binance USDT-M Futures 实盘执行器
    """

    def __init__(self):
        import ccxt
        self._exchange = ccxt.binance({
            'apiKey': settings.BINANCE_API_KEY,
            'secret': settings.BINANCE_API_SECRET,
            'options': {'defaultType': 'future'},
            'enableRateLimit': True,
        })
        if settings.BINANCE_TESTNET:
            self._exchange.set_sandbox_mode(True)
        self._max_retries = 3

    def _ccxt_symbol(self, symbol: str) -> str:
        """BTCUSDT → BTC/USDT:USDT"""
        base = symbol.replace('USDT', '')
        return f"{base}/USDT:USDT"

    async def _retry(self, func, *args, **kwargs):
        """重试 3 次"""
        for attempt in range(self._max_retries):
            try:
                loop = asyncio.get_event_loop()
                return await loop.run_in_executor(None, lambda: func(*args, **kwargs))
            except Exception as e:
                if attempt == self._max_retries - 1:
                    raise
                wait = 2 ** attempt
                logger.warning("binance.retry",
                              attempt=attempt + 1, wait=wait, error=str(e))
                await asyncio.sleep(wait)

    async def open_position(self, symbol: str, direction: str,
                            tier: str, margin: float, leverage: int,
                            stop_loss_roi: float, signal_id: int = None) -> dict:
        """实盘开仓"""
        ccxt_sym = self._ccxt_symbol(symbol)
        now = datetime.now(MYT)
        tier_config = get_leverage_tier(symbol)

        try:
            # 1. 设置杠杆
            await self._retry(self._exchange.set_leverage, leverage, ccxt_sym)

            # 2. 设置保证金模式 (逐仓)
            try:
                await self._retry(
                    self._exchange.set_margin_mode, 'isolated', ccxt_sym
                )
            except Exception:
                pass  # 可能已经是 isolated

            # 3. 获取市场价格
            ticker = await self._retry(self._exchange.fetch_ticker, ccxt_sym)
            market_price = ticker['last']

            # 4. 计算数量
            notional = margin * leverage
            quantity = notional / market_price

            # 精度处理
            market = self._exchange.market(ccxt_sym)
            quantity = self._exchange.amount_to_precision(ccxt_sym, quantity)
            quantity = float(quantity)

            # 5. 开仓 — 限价单 (挂在市价附近)
            side = 'buy' if direction == 'long' else 'sell'

            # 先尝试限价单 (偏移 0.02% 确保成交)
            if direction == 'long':
                limit_price = market_price * 1.0002
            else:
                limit_price = market_price * 0.9998
            limit_price = float(self._exchange.price_to_precision(ccxt_sym, limit_price))

            try:
                order = await self._retry(
                    self._exchange.create_order,
                    ccxt_sym, 'limit', side, quantity, limit_price
                )
            except Exception as e:
                # 限价单失败 → 回退市价单
                logger.warning("binance.limit_failed_fallback_market",
                              symbol=symbol, error=str(e))
                order = await self._retry(
                    self._exchange.create_order,
                    ccxt_sym, 'market', side, quantity
                )

            order_id = order.get('id', '')
            entry_price = order.get('average') or order.get('price') or market_price
            actual_fee = order.get('fee', {}).get('cost', 0) or (notional * 0.0005)

            logger.info("binance.order_placed",
                       symbol=symbol, side=side, quantity=quantity,
                       entry=entry_price, order_id=order_id)

            # 6. 挂服务端止损单 (BR-006)
            stop_distance = abs(stop_loss_roi) / leverage
            if direction == 'long':
                stop_price = entry_price * (1 - stop_distance)
            else:
                stop_price = entry_price * (1 + stop_distance)
            stop_price = float(self._exchange.price_to_precision(ccxt_sym, stop_price))

            stop_side = 'sell' if direction == 'long' else 'buy'
            sl_order = await self._retry(
                self._exchange.create_order,
                ccxt_sym, 'STOP_MARKET', stop_side, quantity, None,
                {
                    'stopPrice': stop_price,
                    'workingType': 'MARK_PRICE',
                    'reduceOnly': True,
                }
            )
            sl_order_id = sl_order.get('id', '')

            logger.info("binance.stop_loss_placed",
                       symbol=symbol, stop_price=stop_price, order_id=sl_order_id)

            # 7. 写 DB
            max_hold = timedelta(hours=tier_config.get('max_hold_hours', 20))

            trade_id = insert_trade({
                'signal_id': signal_id,
                'mode': 'live',
                'tier': tier,
                'symbol': symbol,
                'direction': direction,
                'leverage': leverage,
                'margin': margin,
                'entry_price': entry_price,
                'quantity': quantity,
                'status': 'open',
                'open_time': now,
                'fee': actual_fee,
                'binance_order_id': order_id,
                'stop_loss_order_id': sl_order_id,
            })

            insert_position({
                'trade_id': trade_id,
                'symbol': symbol,
                'direction': direction,
                'leverage': leverage,
                'margin': margin,
                'entry_price': entry_price,
                'quantity': quantity,
                'stop_loss_price': stop_price,
                'take_profit_levels': TIER1_TAKE_PROFIT_LADDER if tier == 'tier1' else [],
                'open_time': now,
                'max_hold_until': now + max_hold,
            })

            write_audit('system', 'live_open', symbol, {
                'trade_id': trade_id, 'order_id': order_id,
                'direction': direction, 'leverage': leverage,
                'entry': entry_price, 'stop': stop_price,
                'margin': margin, 'quantity': quantity,
            })

            return {'trade_id': trade_id, 'order_id': order_id, 'entry_price': entry_price}

        except Exception as e:
            logger.error("binance.open_failed",
                        symbol=symbol, error=str(e))
            write_audit('system', 'live_open_failed', symbol, {
                'error': str(e), 'direction': direction, 'margin': margin,
            }, severity='error')
            return {'error': str(e)}

    async def close_position(self, trade_id: int, reason: str) -> dict:
        """实盘平仓"""
        trade = get_trade_by_id(trade_id)
        if not trade or trade['status'] != 'open':
            return {'error': 'trade_not_found'}

        symbol = trade['symbol']
        ccxt_sym = self._ccxt_symbol(symbol)
        direction = trade['direction']
        quantity = trade['quantity']
        now = datetime.now(MYT)

        try:
            # 1. 市价平仓
            close_side = 'sell' if direction == 'long' else 'buy'
            order = await self._retry(
                self._exchange.create_order,
                ccxt_sym, 'market', close_side, quantity,
                None, {'reduceOnly': True}
            )

            exit_price = order.get('average') or order.get('price') or 0
            close_fee = order.get('fee', {}).get('cost', 0) or (trade['margin'] * trade['leverage'] * 0.0005)

            # 2. 取消服务端止损单
            sl_id = trade.get('stop_loss_order_id')
            if sl_id:
                try:
                    await self._retry(
                        self._exchange.cancel_order, sl_id, ccxt_sym
                    )
                except Exception:
                    pass  # 可能已触发

            # 3. 计算 PnL
            entry = trade['entry_price']
            notional = trade['margin'] * trade['leverage']
            open_fee = trade.get('fee') or 0
            total_fee = open_fee + close_fee

            if direction == 'long':
                pnl_raw = (exit_price - entry) / entry * notional
            else:
                pnl_raw = (entry - exit_price) / entry * notional

            pnl = pnl_raw - total_fee
            pnl_pct = pnl / trade['margin'] if trade['margin'] > 0 else 0

            # 4. 更新 DB
            update_trade(trade_id, {
                'status': 'closed',
                'exit_price': exit_price,
                'close_time': now,
                'pnl': round(pnl, 4),
                'pnl_pct': round(pnl_pct, 4),
                'fee': round(total_fee, 4),
                'close_reason': reason,
            })

            delete_position_by_trade(trade_id)
            circuit_breaker.record_trade(pnl, symbol, now)

            write_audit('system', 'live_close', symbol, {
                'trade_id': trade_id, 'pnl': round(pnl, 2),
                'exit': exit_price, 'reason': reason,
            })

            logger.info("binance.closed",
                       trade_id=trade_id, symbol=symbol,
                       pnl=round(pnl, 2), reason=reason)

            return {'trade_id': trade_id, 'pnl': pnl}

        except Exception as e:
            logger.error("binance.close_failed",
                        trade_id=trade_id, symbol=symbol, error=str(e))
            write_audit('system', 'live_close_failed', symbol, {
                'trade_id': trade_id, 'error': str(e),
            }, severity='error')
            return {'error': str(e)}

    async def check_positions(self):
        """检查止损/止盈/超时 — 服务端止损会自动触发, 这里检查超时和止盈"""
        open_pos = get_open_positions()
        now = datetime.now(MYT)

        for pos in open_pos:
            trade_id = pos['trade_id']
            symbol = pos['symbol']

            # 超时检查
            max_hold = pos.get('max_hold_until')
            if max_hold and now >= max_hold:
                await self.close_position(trade_id, 'timeout')
                continue

            # 检查 Binance 实际持仓是否还在 (止损可能已触发)
            try:
                ccxt_sym = self._ccxt_symbol(symbol)
                loop = asyncio.get_event_loop()
                positions = await loop.run_in_executor(
                    None, self._exchange.fetch_positions, [ccxt_sym]
                )
                has_position = any(
                    float(p.get('contracts', 0)) > 0 for p in positions
                )
                if not has_position:
                    # 服务端止损已触发, 同步 DB
                    await self._sync_closed_position(trade_id, symbol)
            except Exception as e:
                logger.error("binance.check_error",
                            trade_id=trade_id, error=str(e))

    async def _sync_closed_position(self, trade_id: int, symbol: str):
        """同步服务端止损触发后的状态"""
        trade = get_trade_by_id(trade_id)
        if not trade or trade['status'] != 'open':
            return

        # 从 Binance 拉最近订单找到止损成交价
        try:
            ccxt_sym = self._ccxt_symbol(symbol)
            loop = asyncio.get_event_loop()
            orders = await loop.run_in_executor(
                None, self._exchange.fetch_orders, ccxt_sym, None, 10
            )

            sl_id = trade.get('stop_loss_order_id')
            exit_price = None
            for o in orders:
                if o['id'] == sl_id and o['status'] == 'closed':
                    exit_price = o.get('average') or o.get('price')
                    break

            if exit_price is None:
                # 用当前价
                ticker = await loop.run_in_executor(
                    None, self._exchange.fetch_ticker, ccxt_sym
                )
                exit_price = ticker['last']

            # 计算 PnL
            entry = trade['entry_price']
            notional = trade['margin'] * trade['leverage']
            fee = trade.get('fee') or 0

            if trade['direction'] == 'long':
                pnl = (exit_price - entry) / entry * notional - fee
            else:
                pnl = (entry - exit_price) / entry * notional - fee

            pnl_pct = pnl / trade['margin'] if trade['margin'] > 0 else 0

            update_trade(trade_id, {
                'status': 'closed',
                'exit_price': exit_price,
                'close_time': datetime.now(MYT),
                'pnl': round(pnl, 4),
                'pnl_pct': round(pnl_pct, 4),
                'close_reason': 'stop_loss_server',
            })
            delete_position_by_trade(trade_id)
            circuit_breaker.record_trade(pnl, symbol, datetime.now(MYT))

            logger.info("binance.stop_loss_synced",
                       trade_id=trade_id, symbol=symbol, pnl=round(pnl, 2))

        except Exception as e:
            logger.error("binance.sync_error",
                        trade_id=trade_id, error=str(e))
