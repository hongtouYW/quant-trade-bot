import logging

from src.core.models import OrderBook, OrderBookAnalysis
from src.core.enums import Direction

logger = logging.getLogger(__name__)


class OrderBookFilter:
    """
    订单簿深度分析 [V4]
    买卖墙检测 + 前10档失衡 + 挂单密度
    """

    def __init__(self, config: dict):
        ob_cfg = config.get('orderbook', {})
        self._wall_threshold = ob_cfg.get('wall_threshold', 3.0)
        self._imbalance_min = ob_cfg.get('imbalance_min_ratio', 0.65)
        self._zone_pct = ob_cfg.get('analysis_zone_pct', 0.5) / 100

    def analyze(self, symbol: str, direction: Direction,
                entry_price: float, book: OrderBook) -> OrderBookAnalysis:

        if not book.bids or not book.asks:
            return OrderBookAnalysis(can_enter=True, score_adjustment=0,
                                     reason="empty_orderbook")

        score_adj = 0

        # 1. 买卖墙检测 (入场价上下 zone_pct)
        bid_vol = sum(l.value for l in book.bids
                      if l.price >= entry_price * (1 - self._zone_pct))
        ask_vol = sum(l.value for l in book.asks
                      if l.price <= entry_price * (1 + self._zone_pct))

        if direction == Direction.LONG:
            if ask_vol > bid_vol * self._wall_threshold and bid_vol > 0:
                return OrderBookAnalysis(
                    can_enter=False, score_adjustment=-20,
                    reason=f"sell_wall: ask={ask_vol:.0f} > bid={bid_vol:.0f}x{self._wall_threshold}",
                    bid_volume_near=bid_vol, ask_volume_near=ask_vol)
        else:
            if bid_vol > ask_vol * self._wall_threshold and ask_vol > 0:
                return OrderBookAnalysis(
                    can_enter=False, score_adjustment=-20,
                    reason=f"buy_wall: bid={bid_vol:.0f} > ask={ask_vol:.0f}x{self._wall_threshold}",
                    bid_volume_near=bid_vol, ask_volume_near=ask_vol)

        # 2. 前 10 档买卖失衡
        top_bids = book.bids[:10]
        top_asks = book.asks[:10]
        total_bid = sum(l.value for l in top_bids)
        total_ask = sum(l.value for l in top_asks)
        total = total_bid + total_ask

        if total > 0:
            bid_ratio = total_bid / total
            if direction == Direction.LONG:
                if bid_ratio < 0.35:
                    return OrderBookAnalysis(
                        can_enter=False, score_adjustment=-15,
                        reason=f"bid_imbalance: {bid_ratio:.2f} < 0.35",
                        imbalance_ratio=bid_ratio)
                if bid_ratio > 0.60:
                    score_adj += 10
            else:
                ask_ratio = 1 - bid_ratio
                if ask_ratio < 0.35:
                    return OrderBookAnalysis(
                        can_enter=False, score_adjustment=-15,
                        reason=f"ask_imbalance: {ask_ratio:.2f} < 0.35",
                        imbalance_ratio=bid_ratio)
                if ask_ratio > 0.60:
                    score_adj += 10

        # 3. 挂单密度分析
        zone_1pct = entry_price * 0.01
        if direction == Direction.LONG:
            resistance = sum(l.value for l in book.asks
                             if l.price <= entry_price + zone_1pct)
            if resistance > total_ask * 0.5:
                score_adj -= 5
            else:
                score_adj += 5
        else:
            support = sum(l.value for l in book.bids
                          if l.price >= entry_price - zone_1pct)
            if support > total_bid * 0.5:
                score_adj -= 5
            else:
                score_adj += 5

        score_adj = max(-20, min(20, score_adj))

        return OrderBookAnalysis(
            can_enter=True,
            score_adjustment=score_adj,
            reason="passed",
            bid_volume_near=bid_vol,
            ask_volume_near=ask_vol,
            imbalance_ratio=bid_ratio if total > 0 else 0.5,
        )
