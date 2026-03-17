"""Position sizing based on fixed risk method"""
import logging
from app.config import get

log = logging.getLogger(__name__)


class PositionSizer:
    def __init__(self):
        self._cfg = get('execution')

    def build_plan(self, signal, equity, regime='TRENDING'):
        """Build order plan from signal using fixed-risk sizing"""
        risk_pct = self._cfg.get('risk_per_trade', 0.004)

        # Half risk in RANGING
        if regime == 'RANGING':
            risk_pct *= 0.5

        risk_amount = equity * risk_pct
        stop_distance = abs(signal.entry_price - signal.stop_loss)
        if stop_distance <= 0:
            return None

        # Notional = risk_amount / (stop_distance / entry_price)
        notional = risk_amount / (stop_distance / signal.entry_price)
        leverage = get('account', 'leverage', 10)
        margin = notional / leverage

        # Size in base currency
        size = notional / signal.entry_price

        # Checks
        max_margin_pct = get('account', 'max_margin_usage_pct', 0.90)
        if margin > equity * max_margin_pct * 0.3:
            # Scale down
            ratio = (equity * max_margin_pct * 0.3) / margin
            notional *= ratio
            margin *= ratio
            size *= ratio
            log.info(f"Position scaled down by {ratio:.2f} for {signal.symbol}")

        # Max notional check
        if notional > equity * 0.30 * leverage:
            notional = equity * 0.30 * leverage
            margin = notional / leverage
            size = notional / signal.entry_price

        if size <= 0:
            return None

        return {
            'symbol': signal.symbol,
            'direction': signal.direction,
            'side': 'buy' if signal.direction == 1 else 'sell',
            'entry': signal.entry_price,
            'stop': signal.stop_loss,
            'tp1': signal.tp1,
            'tp2': signal.tp2,
            'size': size,
            'notional': notional,
            'margin': margin,
            'risk_amount': risk_amount,
            'setup_type': signal.setup_type,
            'score': signal.score,
        }
