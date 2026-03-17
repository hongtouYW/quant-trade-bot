"""滑点控制 - 下单前检查预期滑点"""
import logging
from app.config import get

log = logging.getLogger(__name__)


class SlippageGuard:
    def __init__(self, exchange_client):
        self.exchange = exchange_client
        self.max_slippage_pct = 0.15  # 最大允许滑点 0.15%

    def check(self, symbol, side, size, entry_price):
        """检查预期滑点，返回 (通过, 预期滑点%)"""
        try:
            ob = self.exchange.fetch_order_book(symbol, limit=10)
            if not ob:
                return False, 999

            if side == 'buy':
                asks = ob.get('asks', [])
                if not asks:
                    return False, 999
                # 模拟吃单，计算加权平均成交价
                filled = 0
                cost = 0
                for price, qty in asks:
                    take = min(qty, size - filled)
                    cost += take * price
                    filled += take
                    if filled >= size:
                        break
                if filled <= 0:
                    return False, 999
                avg_price = cost / filled
                slippage = (avg_price - entry_price) / entry_price * 100
            else:
                bids = ob.get('bids', [])
                if not bids:
                    return False, 999
                filled = 0
                cost = 0
                for price, qty in bids:
                    take = min(qty, size - filled)
                    cost += take * price
                    filled += take
                    if filled >= size:
                        break
                if filled <= 0:
                    return False, 999
                avg_price = cost / filled
                slippage = (entry_price - avg_price) / entry_price * 100

            if slippage > self.max_slippage_pct:
                log.warning(f"滑点过高 {symbol}: {slippage:.3f}% > {self.max_slippage_pct}%")
                return False, slippage

            return True, slippage
        except Exception as e:
            log.error(f"滑点检查失败 {symbol}: {e}")
            return True, 0  # 检查失败时放行，避免阻塞
