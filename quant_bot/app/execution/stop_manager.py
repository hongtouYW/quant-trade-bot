"""止损管理器 - 在交易所挂真实止损单"""
import logging

log = logging.getLogger(__name__)


class StopManager:
    def __init__(self, exchange_client):
        self.exchange = exchange_client
        self._stop_orders = {}  # {symbol: order_id}

    def place_stop(self, symbol, side, size, stop_price):
        """在交易所下止损单
        side: 平仓方向 (long仓用sell, short仓用buy)
        """
        try:
            # 使用 stopMarket 类型
            params = {
                'stopPrice': stop_price,
                'type': 'stop_market',
                'reduceOnly': True,
            }
            order = self.exchange.create_order(
                symbol, side, size, order_type='market', params=params
            )
            if order:
                self._stop_orders[symbol] = order.get('id', '')
                log.info(f"交易所止损单已挂: {symbol} {side} {size} @ {stop_price}")
                return order
            else:
                log.error(f"止损单挂失败: {symbol}")
                return None
        except Exception as e:
            log.error(f"止损单错误 {symbol}: {e}")
            return None

    def update_stop(self, symbol, side, size, new_stop_price):
        """更新止损价 - 先撤旧单再挂新单"""
        self.cancel_stop(symbol)
        return self.place_stop(symbol, side, size, new_stop_price)

    def cancel_stop(self, symbol):
        """撤销止损单"""
        order_id = self._stop_orders.get(symbol)
        if order_id:
            try:
                self.exchange.cancel_order(order_id, symbol)
                del self._stop_orders[symbol]
                log.info(f"止损单已撤: {symbol}")
            except Exception as e:
                log.warning(f"撤销止损单失败 {symbol}: {e}")

    def cancel_all(self):
        """撤销所有止损单"""
        for symbol in list(self._stop_orders.keys()):
            self.cancel_stop(symbol)

    def get_active_stops(self):
        return dict(self._stop_orders)
