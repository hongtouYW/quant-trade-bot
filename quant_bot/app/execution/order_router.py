"""订单路由器 - 入场下单与执行管理 (Spec §17)
负责:
  - 限价入场 → 超时追市价 (§17.1)
  - 滑点控制 (§17.2)
  - 重试机制 (§17.3)
  - 挂单管理 (§17.4)
"""
import time
import logging
from app.config import get

log = logging.getLogger(__name__)

MAX_RETRIES = 2  # Spec §17.3: 最多重试2次


class OrderRouter:
    def __init__(self, exchange_client, slippage_guard, stop_manager):
        self.exchange = exchange_client
        self.slippage_guard = slippage_guard
        self.stop_manager = stop_manager

    def execute_entry(self, order_plan, paper_mode=False):
        """执行入场订单，返回 (order_result, error_msg)"""
        symbol = order_plan['symbol']
        side = order_plan['side']
        size = order_plan['size']
        entry_price = order_plan['entry']
        direction = order_plan['direction']

        if paper_mode:
            return {'id': f'paper_{int(time.time())}', 'status': 'filled'}, None

        # Spec §17.2: 滑点检查
        slip_ok, slip_pct = self.slippage_guard.check(symbol, side, size, entry_price)
        if not slip_ok:
            return None, f"滑点过高: {slip_pct:.3f}%"

        # 设置杠杆
        leverage = get('account', 'leverage', 10)
        self.exchange.set_leverage(symbol, leverage)

        entry_timeout = get('execution', 'entry_timeout_minutes', 3) * 60
        order = None

        for attempt in range(1, MAX_RETRIES + 2):
            try:
                # Spec §17.1: 限价入场
                order = self.exchange.create_order(
                    symbol, side, size,
                    price=entry_price, order_type='limit',
                )
                if not order:
                    log.warning(f"限价单失败 {symbol} (第{attempt}次), 改用市价")
                    order = self.exchange.create_order(symbol, side, size, order_type='market')
                    break

                # 等待成交
                order_id = order.get('id')
                filled = False
                start_ts = time.time()

                while time.time() - start_ts < entry_timeout:
                    time.sleep(3)
                    try:
                        status = self.exchange.exchange.fetch_order(order_id, f"{symbol}:USDT")
                        if status and status.get('status') == 'closed':
                            filled = True
                            order = status
                            break
                        elif status and status.get('status') == 'canceled':
                            order = None
                            break
                    except Exception:
                        pass

                if filled:
                    break

                # Spec §17.4: 超时未成交，取消并追市价
                log.info(f"限价单超时 {symbol}, 取消并追市价")
                self.exchange.cancel_order(order_id, symbol)
                time.sleep(0.5)
                order = self.exchange.create_order(symbol, side, size, order_type='market')
                break

            except Exception as e:
                log.error(f"下单异常 {symbol} (第{attempt}次): {e}")
                if attempt > MAX_RETRIES:
                    return None, f"下单失败超过重试上限: {e}"
                time.sleep(1)

        if not order:
            return None, "下单最终失败"

        # 挂交易所止损单
        stop_side = 'sell' if direction == 1 else 'buy'
        stop_result = self.stop_manager.place_stop(
            symbol, stop_side, size, order_plan['stop']
        )
        if not stop_result:
            log.error(f"止损挂单失败 {symbol} @ {order_plan['stop']}")

        return order, None

    def cancel_stale_orders(self, symbol):
        """Spec §17.4: 清理孤儿挂单"""
        try:
            open_orders = self.exchange.exchange.fetch_open_orders(f"{symbol}:USDT")
            for o in open_orders:
                if o.get('status') == 'open':
                    self.exchange.cancel_order(o['id'], symbol)
                    log.info(f"清理孤儿挂单 {symbol}: {o['id']}")
        except Exception as e:
            log.error(f"清理挂单失败 {symbol}: {e}")
