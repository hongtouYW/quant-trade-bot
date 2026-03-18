"""Position manager - manage open positions, TP/SL, trailing, time stop"""
import logging
import time
from datetime import datetime
from app.models.market import Position, TradeRecord
from app.indicators import calc
from app.config import get
from app.db import trade_store
from app.monitoring import notifier

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
        self._load_from_db()

    def _load_from_db(self):
        """Load open positions and trade history from database on startup"""
        try:
            self.positions = trade_store.load_open_positions()
            self.trade_history = trade_store.load_trade_history(200)
            if self.positions:
                log.info(f"从数据库恢复 {len(self.positions)} 个持仓")
            if self.trade_history:
                log.info(f"从数据库加载 {len(self.trade_history)} 条历史交易")
        except Exception as e:
            log.error(f"数据库加载失败: {e}")

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

        # 追踪资金费 (每8小时: 00:00, 08:00, 16:00 UTC)
        self._track_funding_fees()

        for pos in list(self.positions):
            self._manage_position(pos, trend_scores.get(pos.symbol, 60))

    def _track_funding_fees(self):
        """追踪每个持仓的资金费"""
        import time as _time
        now = _time.time()
        for pos in self.positions:
            # 每8小时检查一次资金费 (避免重复计算)
            if now - pos.last_funding_ts < 28800:  # 8h = 28800s
                continue
            try:
                rate = self.exchange.fetch_funding_rate(pos.symbol)
                if rate and rate != 0:
                    # 资金费 = 持仓名义价值 * 资金费率
                    df_15m = self.cache.get(pos.symbol, '15m')
                    if df_15m is not None and len(df_15m) > 0:
                        current_price = df_15m['close'].iloc[-1]
                        notional = current_price * pos.size
                        # 做多: 正费率付费, 负费率收费; 做空相反
                        funding_cost = notional * rate * pos.direction
                        pos.funding_fees += funding_cost
                        pos.last_funding_ts = now
                        # 更新数据库
                        trade_store.update_position_funding(pos.symbol, pos.direction, pos.funding_fees)
                        if abs(funding_cost) > 0.01:
                            log.info(f"资金费 {pos.symbol}: {funding_cost:+.4f}U (累计: {pos.funding_fees:+.4f}U)")
            except Exception as e:
                log.debug(f"资金费获取失败 {pos.symbol}: {e}")

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
        """结构化移动止盈 - 跟随higher low / lower high"""
        if len(df_15m) < 6:
            return

        if pos.direction == 1:
            # 做多: 找最近几根15m的higher low
            lows = df_15m['low'].iloc[-6:]
            # 寻找递增低点中最新的一个
            best_trail = pos.stop_loss
            for i in range(len(lows) - 1, 0, -1):
                if lows.iloc[i] > lows.iloc[i-1] and lows.iloc[i] > best_trail:
                    best_trail = lows.iloc[i]
                    break
            if best_trail > pos.stop_loss:
                pos.stop_loss = best_trail
                log.info(f"移动止损 {pos.symbol}: {pos.stop_loss:.4f}")
        else:
            # 做空: 找lower high
            highs = df_15m['high'].iloc[-6:]
            best_trail = pos.stop_loss
            for i in range(len(highs) - 1, 0, -1):
                if highs.iloc[i] < highs.iloc[i-1] and highs.iloc[i] < best_trail:
                    best_trail = highs.iloc[i]
                    break
            if best_trail < pos.stop_loss:
                pos.stop_loss = best_trail
                log.info(f"移动止损 {pos.symbol}: {pos.stop_loss:.4f}")

    def _direction_invalidated(self, pos):
        """检查1H方向结构是否失效"""
        import numpy as np
        df_1h = self.cache.get(pos.symbol, '1h')
        if df_1h is None or len(df_1h) < 50:
            return False

        close = df_1h['close'].iloc[-1]
        e20 = calc.ema(df_1h['close'], 20).iloc[-1]
        e50 = calc.ema(df_1h['close'], 50).iloc[-1]

        if pos.direction == 1:
            # 做多但价格跌破EMA50
            if close < e50:
                return True
        else:
            # 做空但价格涨破EMA50
            if close > e50:
                return True
        return False

    def _reverse_signal_detected(self, pos, df_15m):
        """检查是否出现反向强信号"""
        if df_15m is None or len(df_15m) < 5:
            return False

        last = df_15m.iloc[-1]
        prev = df_15m.iloc[-2]
        body = abs(last['close'] - last['open'])
        avg_body = (df_15m['close'] - df_15m['open']).abs().iloc[-20:].mean()

        if pos.direction == 1:
            # 做多时出现强大阴线 (实体 > 均值2倍 + 收盘跌破前低)
            if last['close'] < last['open'] and body > avg_body * 2 and last['close'] < prev['low']:
                return True
        else:
            # 做空时出现强大阳线
            if last['close'] > last['open'] and body > avg_body * 2 and last['close'] > prev['high']:
                return True
        return False

    def _breakout_failed(self, pos, df_15m):
        """突破单是否回到整理区间"""
        if df_15m is None or len(df_15m) < 25:
            return False

        # 计算近20根的整理区间
        comp_range = df_15m.iloc[-25:-5]
        mid = (comp_range['high'].max() + comp_range['low'].min()) / 2
        current = df_15m['close'].iloc[-1]

        if pos.direction == 1:
            # 价格回到区间中部以下
            if current < mid:
                return True
        else:
            if current > mid:
                return True
        return False

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

        # 计算手续费: Binance taker fee = 0.04%
        fee_rate = 0.0004
        entry_notional = pos.entry_price * pos.original_size
        exit_notional = price * pos.size
        entry_fee = entry_notional * fee_rate
        exit_fee = exit_notional * fee_rate
        # 加上TP1部分平仓时已产生的手续费
        total_fees = entry_fee + exit_fee + pos.entry_fee

        # 资金费从持仓累计
        funding_fees = pos.funding_fees

        # 净盈亏
        net_pnl = pnl - total_fees - funding_fees

        record = TradeRecord(
            symbol=pos.symbol, direction=pos.direction,
            entry_price=pos.entry_price, exit_price=price,
            size=pos.size, pnl=pnl, pnl_pct=pnl_pct,
            setup_type=pos.strategy_tag, close_reason=reason,
            opened_at=pos.opened_at, closed_at=datetime.utcnow(),
            fees=total_fees, funding_fees=funding_fees, net_pnl=net_pnl,
        )
        self.trade_history.append(record)

        # Save to database
        try:
            trade_store.save_trade(record)
            trade_store.close_position_in_db(pos.symbol, pos.direction)
        except Exception as e:
            log.error(f"交易保存到数据库失败: {e}")

        # Telegram notification
        notifier.notify_trade_close(record)

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
        """Close partial position with fee calculation"""
        close_size = pos.size * pct
        side = 'sell' if pos.direction == 1 else 'buy'
        order = self.exchange.create_order(pos.symbol, side, close_size, order_type='market')

        # 计算部分平仓手续费
        fee_rate = 0.0004
        partial_notional = price * close_size
        partial_fee = partial_notional * fee_rate
        pos.entry_fee += partial_fee  # 累计到持仓费用中

        pos.size -= close_size
        pos.tp1_done = True
        log.info(f"PARTIAL CLOSE {pos.symbol} {reason}: {pct:.0%} at {price:.4f} fee={partial_fee:.4f}U")

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

        # Save to database
        try:
            trade_store.save_position(pos)
        except Exception as e:
            log.error(f"持仓保存到数据库失败: {e}")

        log.info(f"OPENED {pos.symbol} {'LONG' if pos.direction==1 else 'SHORT'} @ {pos.entry_price:.4f} size={pos.size:.4f}")
        return pos

    def get_positions_summary(self):
        result = []
        for pos in self.positions:
            notional = round(pos.entry_price * pos.original_size, 2)
            result.append({
                'symbol': pos.symbol,
                'direction': 'LONG' if pos.direction == 1 else 'SHORT',
                'entry': pos.entry_price,
                'size': pos.size,
                'margin': round(pos.margin, 2),
                'notional': notional,
                'stop': pos.stop_loss,
                'tp1': pos.tp1,
                'tp2': pos.tp2,
                'tp1_done': pos.tp1_done,
                'pnl': round(pos.current_pnl, 2),
                'pnl_pct': round(pos.current_pnl_pct * 100, 2),
                'held_min': int((datetime.utcnow() - pos.opened_at).total_seconds() / 60),
            })
        return result
