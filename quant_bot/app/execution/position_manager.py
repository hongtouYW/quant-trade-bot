"""Position manager - manage open positions, TP/SL, trailing, time stop"""
import logging
import time
from datetime import datetime
from app.models.market import Position, TradeRecord
from app.indicators import calc
from app.config import get

log = logging.getLogger(__name__)


class PositionManager:
    def __init__(self, exchange_client, ohlcv_cache, risk_engine, cooldown_engine):
        self.exchange = exchange_client
        self.cache = ohlcv_cache
        self.risk_engine = risk_engine
        self.cooldown = cooldown_engine
        self.positions = []  # List[Position]
        self.trade_history = []  # List[TradeRecord]
        self._cfg = get('execution')

    def sync_positions(self):
        """Sync positions from exchange"""
        exchange_positions = self.exchange.fetch_positions()
        # Update existing positions with live PnL
        for pos in self.positions:
            for ep in exchange_positions:
                if ep['symbol'] == pos.symbol:
                    if pos.direction == 1 and ep['side'] == 'long':
                        pos.current_pnl = ep['unrealized_pnl']
                        if pos.entry_price > 0:
                            price_change = (ep.get('entry_price', pos.entry_price) - pos.entry_price) / pos.entry_price
                        break
                    elif pos.direction == -1 and ep['side'] == 'short':
                        pos.current_pnl = ep['unrealized_pnl']
                        break

    def manage_all(self, trend_scores=None):
        """Manage all open positions"""
        if trend_scores is None:
            trend_scores = {}

        for pos in list(self.positions):
            self._manage_position(pos, trend_scores.get(pos.symbol, 60))

    def _manage_position(self, pos, latest_score):
        """Manage a single position"""
        df_15m = self.cache.get(pos.symbol, '15m')
        if df_15m is None or len(df_15m) < 5:
            return

        latest_price = df_15m['close'].iloc[-1]

        # Calculate current PnL %
        if pos.entry_price > 0:
            if pos.direction == 1:
                pos.current_pnl_pct = (latest_price - pos.entry_price) / pos.entry_price
            else:
                pos.current_pnl_pct = (pos.entry_price - latest_price) / pos.entry_price

        # Check stop loss
        if self._hit_stop(pos, latest_price):
            self._close_position(pos, latest_price, 'stop_loss')
            return

        # Check TP1
        if not pos.tp1_done and self._hit_tp1(pos, latest_price):
            self._close_partial(pos, latest_price, 0.5, 'tp1')
            self._move_stop_to_breakeven(pos)

        # Check TP2
        if self._hit_tp2(pos, latest_price):
            self._close_position(pos, latest_price, 'tp2')
            return

        # Time stop - 75 min without progress
        holding_min = (datetime.utcnow() - pos.opened_at).total_seconds() / 60
        max_hold = self._cfg.get('max_holding_minutes', 75)
        if holding_min > max_hold and abs(pos.current_pnl_pct) < 0.005:
            self._close_position(pos, latest_price, 'time_stop')
            return

        # 趋势评分骤降
        if latest_score < 55:
            self._close_position(pos, latest_price, '趋势评分下降')
            return

        # 1H方向结构失效检查
        if self._direction_invalidated(pos):
            self._close_position(pos, latest_price, '1H方向失效')
            return

        # 反向强信号检查
        if self._reverse_signal_detected(pos, df_15m):
            self._close_position(pos, latest_price, '反向信号')
            return

        # 突破失败回到整理区间
        if pos.strategy_tag == 'compression_breakout' and self._breakout_failed(pos, df_15m):
            self._close_position(pos, latest_price, '突破失败')
            return

        # TP1后移动止盈
        if pos.tp1_done:
            self._trail_stop(pos, df_15m)

    def _hit_stop(self, pos, price):
        if pos.direction == 1:
            return price <= pos.stop_loss
        else:
            return price >= pos.stop_loss

    def _hit_tp1(self, pos, price):
        if pos.direction == 1:
            return price >= pos.tp1
        else:
            return price <= pos.tp1

    def _hit_tp2(self, pos, price):
        if pos.direction == 1:
            return price >= pos.tp2
        else:
            return price <= pos.tp2

    def _move_stop_to_breakeven(self, pos):
        """Move stop to entry + small buffer"""
        atr = 0
        df = self.cache.get(pos.symbol, '15m')
        if df is not None and len(df) >= 14:
            atr = calc.atr(df, 14).iloc[-1]

        if pos.direction == 1:
            pos.stop_loss = pos.entry_price + atr * 0.1
        else:
            pos.stop_loss = pos.entry_price - atr * 0.1

        log.info(f"Stop moved to breakeven for {pos.symbol}: {pos.stop_loss:.4f}")

    def _trail_stop(self, pos, df_15m):
        """Trail stop following recent structure"""
        if len(df_15m) < 5:
            return

        if pos.direction == 1:
            # Follow higher lows
            recent_low = df_15m['low'].iloc[-4:].min()
            if recent_low > pos.stop_loss:
                pos.stop_loss = recent_low
        else:
            recent_high = df_15m['high'].iloc[-4:].max()
            if recent_high < pos.stop_loss:
                pos.stop_loss = recent_high

    def _close_position(self, pos, price, reason):
        """Close full position"""
        side = 'sell' if pos.direction == 1 else 'buy'
        order = self.exchange.create_order(pos.symbol, side, pos.size, order_type='market')

        pnl = 0
        if pos.direction == 1:
            pnl = (price - pos.entry_price) * pos.size
        else:
            pnl = (pos.entry_price - price) * pos.size

        pnl_pct = pnl / pos.margin if pos.margin > 0 else 0

        record = TradeRecord(
            symbol=pos.symbol, direction=pos.direction,
            entry_price=pos.entry_price, exit_price=price,
            size=pos.size, pnl=pnl, pnl_pct=pnl_pct,
            setup_type=pos.strategy_tag, close_reason=reason,
            opened_at=pos.opened_at, closed_at=datetime.utcnow(),
        )
        self.trade_history.append(record)

        # Update risk engine
        balance = self.exchange.fetch_balance()
        equity = balance.get('equity', 2000)
        self.risk_engine.record_trade_result(pnl, equity)

        # Update cooldown
        if pnl < 0:
            self.cooldown.record_loss(pos.symbol, pos.direction, pos.strategy_tag)
        else:
            self.cooldown.record_win(pos.symbol, pos.direction)

        self.positions.remove(pos)
        self.risk_engine.positions = self.positions
        log.info(f"CLOSED {pos.symbol} {reason}: PnL={pnl:.2f} ({pnl_pct:.2%})")

    def _close_partial(self, pos, price, pct, reason):
        """Close partial position"""
        close_size = pos.size * pct
        side = 'sell' if pos.direction == 1 else 'buy'
        order = self.exchange.create_order(pos.symbol, side, close_size, order_type='market')

        pos.size -= close_size
        pos.tp1_done = True
        log.info(f"PARTIAL CLOSE {pos.symbol} {reason}: {pct:.0%} at {price:.4f}")

    def add_position(self, order_plan, order_result):
        """Add a new tracked position"""
        pos = Position(
            symbol=order_plan['symbol'],
            direction=order_plan['direction'],
            entry_price=order_plan['entry'],
            size=order_plan['size'],
            margin=order_plan['margin'],
            stop_loss=order_plan['stop'],
            tp1=order_plan['tp1'],
            tp2=order_plan['tp2'],
            opened_at=datetime.utcnow(),
            strategy_tag=order_plan['setup_type'],
            original_size=order_plan['size'],
        )
        self.positions.append(pos)
        self.risk_engine.positions = self.positions
        log.info(f"OPENED {pos.symbol} {'LONG' if pos.direction==1 else 'SHORT'} @ {pos.entry_price:.4f} size={pos.size:.4f}")
        return pos

    def get_positions_summary(self):
        result = []
        for pos in self.positions:
            result.append({
                'symbol': pos.symbol,
                'direction': 'LONG' if pos.direction == 1 else 'SHORT',
                'entry': pos.entry_price,
                'size': pos.size,
                'stop': pos.stop_loss,
                'tp1': pos.tp1,
                'tp2': pos.tp2,
                'tp1_done': pos.tp1_done,
                'pnl': round(pos.current_pnl, 2),
                'pnl_pct': round(pos.current_pnl_pct * 100, 2),
                'held_min': int((datetime.utcnow() - pos.opened_at).total_seconds() / 60),
            })
        return result
