"""回测引擎 - 基于历史K线模拟交易"""
import logging
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from dataclasses import dataclass, field
from typing import List, Dict, Optional

from app.indicators import calc
from app.config import get

log = logging.getLogger(__name__)


@dataclass
class BTPosition:
    symbol: str
    direction: int
    entry_price: float
    size: float
    margin: float
    stop_loss: float
    tp1: float
    tp2: float
    opened_at: datetime
    setup_type: str
    tp1_done: bool = False
    original_size: float = 0.0
    funding_fees: float = 0.0
    last_funding_bar: int = 0  # bar index of last funding charge


@dataclass
class BTTrade:
    symbol: str
    direction: int
    entry_price: float
    exit_price: float
    size: float
    pnl: float
    pnl_pct: float
    fees: float
    setup_type: str
    close_reason: str
    opened_at: datetime
    closed_at: datetime
    r_multiple: float = 0.0
    funding_fees: float = 0.0


class BacktestEngine:
    """回测引擎 - 模拟完整交易策略"""

    def __init__(self, config=None):
        self.cfg = config or get('execution')
        self.risk_cfg = config or get('risk')
        self.initial_balance = get('account', 'initial_balance', 2000)
        self.leverage = get('account', 'leverage', 10)
        self.fee_rate = 0.0004  # Binance taker fee
        self.slippage_pct = 0.02  # 2 basis points
        self.funding_rate = 0.0001  # 默认资金费率 0.01% per 8h
        self.funding_interval_bars = 32  # 8h / 15m = 32 bars
        self.partial_fill_enable = True  # 部分成交模型 (Spec §23)
        self.partial_fill_max_pct = 0.10  # 单笔最多吃掉bar成交量的10%
        self.partial_fill_min_ratio = 0.30  # 低于30%成交则放弃

        # 状态
        self.equity = self.initial_balance
        self.positions: List[BTPosition] = []
        self.trades: List[BTTrade] = []
        self.equity_curve = []
        self.daily_pnl = {}
        self.daily_equity = {}

    def run(self, symbol_data: Dict[str, pd.DataFrame], symbols: List[str] = None):
        """
        运行回测
        symbol_data: {symbol: DataFrame with columns [timestamp, open, high, low, close, volume]}
        symbols: 要回测的币种列表，None则使用全部
        """
        if symbols is None:
            symbols = list(symbol_data.keys())

        # 获取所有时间戳的并集
        all_timestamps = set()
        for sym in symbols:
            df = symbol_data[sym]
            all_timestamps.update(df['timestamp'].tolist())
        all_timestamps = sorted(all_timestamps)

        log.info(f"回测开始: {len(symbols)}个币种, {len(all_timestamps)}根K线")
        log.info(f"初始资金: {self.initial_balance}U, 杠杆: {self.leverage}x")

        self.equity = self.initial_balance
        daily_start_equity = self.equity
        current_date = None
        consecutive_losses = 0

        for i, ts in enumerate(all_timestamps):
            dt = pd.Timestamp(ts)
            today = dt.date()

            # 日切
            if current_date != today:
                if current_date:
                    self.daily_pnl[current_date] = self.equity - daily_start_equity
                    self.daily_equity[current_date] = self.equity
                current_date = today
                daily_start_equity = self.equity
                daily_loss = 0

            # 管理持仓
            for pos in list(self.positions):
                sym = pos.symbol
                if sym not in symbol_data:
                    continue
                df = symbol_data[sym]
                row = df[df['timestamp'] == ts]
                if row.empty:
                    continue
                bar = row.iloc[0]
                self._manage_bt_position(pos, bar, dt)

            # 日亏损检查
            daily_loss = (self.equity - daily_start_equity) / daily_start_equity if daily_start_equity > 0 else 0
            if daily_loss <= -0.03:
                continue  # 停止开仓

            # 连亏检查
            if consecutive_losses >= 5:
                continue

            # 最大持仓检查
            max_pos = get('execution', 'max_positions', 3)
            if len(self.positions) >= max_pos:
                continue

            # 扫描信号
            for sym in symbols:
                if any(p.symbol == sym for p in self.positions):
                    continue
                if len(self.positions) >= max_pos:
                    break

                df = symbol_data[sym]
                idx = df[df['timestamp'] == ts].index
                if len(idx) == 0:
                    continue
                bar_idx = idx[0]
                if bar_idx < 55:
                    continue

                window = df.iloc[:bar_idx + 1]
                signal = self._generate_signal(sym, window)
                if signal is None:
                    continue

                # 仓位计算 (支持优化器覆盖)
                risk_pct = self.cfg.get('risk_per_trade', get('execution', 'risk_per_trade', 0.004))
                risk_amount = self.equity * risk_pct
                stop_dist = abs(signal['entry'] - signal['stop'])
                if stop_dist <= 0:
                    continue

                notional = risk_amount / (stop_dist / signal['entry'])
                margin = notional / self.leverage
                size = notional / signal['entry']

                # 最大保证金检查
                if margin > self.equity * 0.30:
                    margin = self.equity * 0.30
                    notional = margin * self.leverage
                    size = notional / signal['entry']

                if margin <= 0 or margin > self.equity * 0.9:
                    continue

                # 部分成交模型 (Spec §23: partial fills)
                if self.partial_fill_enable:
                    bar_row = df[df['timestamp'] == ts]
                    if not bar_row.empty:
                        bar_volume_usd = bar_row.iloc[0]['volume'] * bar_row.iloc[0]['close']
                        max_fill_usd = bar_volume_usd * self.partial_fill_max_pct
                        if notional > max_fill_usd:
                            fill_ratio = max_fill_usd / notional
                            if fill_ratio < self.partial_fill_min_ratio:
                                continue  # 成交量太低, 放弃
                            # 部分成交: 缩小仓位
                            notional = max_fill_usd
                            margin = notional / self.leverage
                            size = notional / signal['entry']

                # 模拟滑点
                entry_price = signal['entry'] * (1 + self.slippage_pct / 100 * signal['direction'])
                fee = notional * self.fee_rate

                pos = BTPosition(
                    symbol=sym,
                    direction=signal['direction'],
                    entry_price=entry_price,
                    size=size,
                    margin=margin,
                    stop_loss=signal['stop'],
                    tp1=signal['tp1'],
                    tp2=signal['tp2'],
                    opened_at=dt.to_pydatetime() if hasattr(dt, 'to_pydatetime') else dt,
                    setup_type=signal['setup'],
                    original_size=size,
                )
                self.positions.append(pos)
                self.equity -= fee

            # 记录权益曲线
            unrealized = sum(self._calc_unrealized(p, symbol_data, ts) for p in self.positions)
            self.equity_curve.append({
                'timestamp': ts,
                'equity': self.equity + unrealized,
                'positions': len(self.positions),
            })

        # 最后一天
        if current_date:
            self.daily_pnl[current_date] = self.equity - daily_start_equity
            self.daily_equity[current_date] = self.equity

        # 强制平掉剩余持仓
        for pos in list(self.positions):
            sym = pos.symbol
            if sym in symbol_data:
                last_price = symbol_data[sym]['close'].iloc[-1]
                self._close_bt_position(pos, last_price, all_timestamps[-1], '回测结束')

        log.info(f"回测完成: {len(self.trades)}笔交易, 最终权益: {self.equity:.2f}U")
        return self.get_metrics()

    def _generate_signal(self, symbol, df):
        """从K线数据生成信号"""
        if len(df) < 55:
            return None

        close = df['close'].iloc[-1]

        # EMA计算
        e7 = calc.ema(df['close'], 7).iloc[-1]
        e20 = calc.ema(df['close'], 20).iloc[-1]
        e21 = calc.ema(df['close'], 21).iloc[-1]
        e50 = calc.ema(df['close'], 50).iloc[-1]

        # ADX
        adx_vals, _, _ = calc.adx(df, 14)
        curr_adx = adx_vals.iloc[-1] if len(adx_vals) > 0 and not np.isnan(adx_vals.iloc[-1]) else 0

        # ATR
        atr_val = calc.atr(df, 14).iloc[-1]
        if np.isnan(atr_val) or atr_val <= 0:
            return None

        adx_min = self.cfg.get('adx_min', get('trend_filter', 'adx_min', 22))
        if curr_adx < adx_min:
            return None

        # 方向判定
        direction = 0
        slope = calc.ema_slope(calc.ema(df['close'], 20), 3)
        if close > e20 > e50 and slope > 0:
            direction = 1
        elif close < e20 < e50 and slope < 0:
            direction = -1
        elif close < e50 and curr_adx >= 25 and slope < 0:
            # 快速做空: 价格跌破EMA50 + 强ADX + 下行斜率
            direction = -1

        if direction == 0:
            return None

        # Regime过滤 (与实盘一致)
        if len(df) >= 20:
            atrp_vals = calc.atrp(df, 14)
            if len(atrp_vals) >= 20:
                recent_atrp = atrp_vals.iloc[-3:]
                mean_atrp = atrp_vals.iloc[-20:].mean()
                if not np.isnan(mean_atrp) and mean_atrp > 0:
                    if recent_atrp.max() > mean_atrp * 3:
                        # EXTREME: 只允许做空
                        if direction != -1:
                            return None

        # 检测回踩
        setup = None
        stop_mult = self.cfg.get('stop_atr_multiple', get('execution', 'stop_atr_multiple', 1.2))
        tp1_mult = self.cfg.get('tp1_r_multiple', get('execution', 'tp1_r_multiple', 1.5))
        tp2_mult = self.cfg.get('tp2_r_multiple', get('execution', 'tp2_r_multiple', 2.8))

        if direction == 1:
            if close <= e21 * 1.003 and close >= e21 * 0.990:
                last = df.iloc[-1]
                if last['close'] > last['open'] or close > e7:
                    stop = min(df['low'].iloc[-5:].min(), close - atr_val * stop_mult)
                    r = close - stop
                    if r > 0:
                        setup = {
                            'direction': 1, 'entry': close, 'stop': stop,
                            'tp1': close + r * tp1_mult, 'tp2': close + r * tp2_mult,
                            'setup': 'pullback',
                        }
        elif direction == -1:
            if close >= e21 * 0.997 and close <= e21 * 1.010:
                last = df.iloc[-1]
                if last['close'] < last['open'] or close < e7:
                    stop = max(df['high'].iloc[-5:].max(), close + atr_val * stop_mult)
                    r = stop - close
                    if r > 0:
                        setup = {
                            'direction': -1, 'entry': close, 'stop': stop,
                            'tp1': close - r * tp1_mult, 'tp2': close - r * tp2_mult,
                            'setup': 'pullback',
                        }

        # 检测压缩突破
        if setup is None:
            comp = calc.compression_range(df, 20)
            if comp < 3.0:
                high_20 = df['high'].iloc[-20:].max()
                low_20 = df['low'].iloc[-20:].min()
                avg_vol = df['volume'].iloc[-20:].mean()
                curr_vol = df['volume'].iloc[-1]

                if direction == 1 and close > high_20 * 0.999 and curr_vol > avg_vol * 1.3:
                    stop = max(low_20, close - atr_val * stop_mult)
                    r = close - stop
                    if r > 0:
                        setup = {
                            'direction': 1, 'entry': close, 'stop': stop,
                            'tp1': close + r * tp1_mult, 'tp2': close + r * tp2_mult,
                            'setup': 'compression_breakout',
                        }
                elif direction == -1 and close < low_20 * 1.001 and curr_vol > avg_vol * 1.3:
                    stop = min(high_20, close + atr_val * stop_mult)
                    r = stop - close
                    if r > 0:
                        setup = {
                            'direction': -1, 'entry': close, 'stop': stop,
                            'tp1': close - r * tp1_mult, 'tp2': close - r * tp2_mult,
                            'setup': 'compression_breakout',
                        }

        # 均值回归 (Phase 2) - 趋势信号未找到 + ADX低
        if setup is None and curr_adx < 25 and get('mean_reversion', 'enable', False):
            upper, mid, lower = calc.bollinger_bands(df['close'], 20, 2.0)
            curr_upper = upper.iloc[-1]
            curr_mid = mid.iloc[-1]
            curr_lower = lower.iloc[-1]
            rsi_val = calc.rsi(df['close'], 14).iloc[-1]

            if not (np.isnan(curr_upper) or np.isnan(curr_lower) or np.isnan(rsi_val)):
                bb_width = (curr_upper - curr_lower) / curr_mid * 100
                if 1.0 <= bb_width <= 8.0:
                    # 做多: 超卖 + 下轨
                    if rsi_val < 30 and close <= curr_lower * 1.002:
                        last = df.iloc[-1]
                        if last['close'] > last['open']:
                            stop = close - atr_val * 1.5
                            r = close - stop
                            if r > 0 and (curr_mid - close) / r >= 1.0:
                                setup = {
                                    'direction': 1, 'entry': close, 'stop': stop,
                                    'tp1': curr_mid, 'tp2': curr_upper * 0.98,
                                    'setup': 'mean_reversion',
                                }
                    # 做空: 超买 + 上轨
                    elif rsi_val > 70 and close >= curr_upper * 0.998:
                        last = df.iloc[-1]
                        if last['close'] < last['open']:
                            stop = close + atr_val * 1.5
                            r = stop - close
                            if r > 0 and (close - curr_mid) / r >= 1.0:
                                setup = {
                                    'direction': -1, 'entry': close, 'stop': stop,
                                    'tp1': curr_mid, 'tp2': curr_lower * 1.02,
                                    'setup': 'mean_reversion',
                                }

        return setup

    def _manage_bt_position(self, pos, bar, dt):
        """管理回测持仓"""
        high = bar['high']
        low = bar['low']
        close = bar['close']

        # 止损检查 (使用high/low判断是否触发)
        if pos.direction == 1 and low <= pos.stop_loss:
            self._close_bt_position(pos, pos.stop_loss, dt, '止损')
            return
        if pos.direction == -1 and high >= pos.stop_loss:
            self._close_bt_position(pos, pos.stop_loss, dt, '止损')
            return

        # TP1
        if not pos.tp1_done:
            if pos.direction == 1 and high >= pos.tp1:
                # 平50%
                half = pos.size * 0.5
                pnl = (pos.tp1 - pos.entry_price) * half
                fee = abs(pos.tp1 * half) * self.fee_rate
                self.equity += pnl - fee
                pos.size -= half
                pos.tp1_done = True
                # 止损移到保本
                pos.stop_loss = pos.entry_price
            elif pos.direction == -1 and low <= pos.tp1:
                half = pos.size * 0.5
                pnl = (pos.entry_price - pos.tp1) * half
                fee = abs(pos.tp1 * half) * self.fee_rate
                self.equity += pnl - fee
                pos.size -= half
                pos.tp1_done = True
                pos.stop_loss = pos.entry_price

        # TP2
        if pos.direction == 1 and high >= pos.tp2:
            self._close_bt_position(pos, pos.tp2, dt, 'TP2')
            return
        if pos.direction == -1 and low <= pos.tp2:
            self._close_bt_position(pos, pos.tp2, dt, 'TP2')
            return

        # 资金费模拟 (每32根15m K线 = 8小时)
        bars_held = int((dt - pos.opened_at).total_seconds() / 900) if isinstance(pos.opened_at, datetime) else 0
        if bars_held > 0 and bars_held - pos.last_funding_bar >= self.funding_interval_bars:
            notional = close * pos.size
            funding_cost = notional * self.funding_rate * pos.direction
            pos.funding_fees += funding_cost
            self.equity -= funding_cost
            pos.last_funding_bar = bars_held

        # 时间止损
        holding = (dt - pos.opened_at).total_seconds() / 60 if isinstance(pos.opened_at, datetime) else 0
        max_hold = self.cfg.get('max_holding_minutes', get('execution', 'max_holding_minutes', 240))
        if holding > max_hold:
            pnl_pct = (close - pos.entry_price) / pos.entry_price * pos.direction
            if abs(pnl_pct) < 0.005:
                self._close_bt_position(pos, close, dt, '时间止损')
                return

    def _close_bt_position(self, pos, exit_price, dt, reason):
        """平仓"""
        # 滑点
        exit_price = exit_price * (1 - self.slippage_pct / 100 * pos.direction)
        fee = abs(exit_price * pos.size) * self.fee_rate

        if pos.direction == 1:
            pnl = (exit_price - pos.entry_price) * pos.size
        else:
            pnl = (pos.entry_price - exit_price) * pos.size

        pnl -= fee
        pnl_pct = pnl / pos.margin if pos.margin > 0 else 0

        # R倍数
        stop_dist = abs(pos.entry_price - pos.stop_loss) if pos.stop_loss != pos.entry_price else 1
        initial_risk = stop_dist * pos.original_size
        r_multiple = pnl / initial_risk if initial_risk > 0 else 0

        # 扣除累计资金费
        pnl -= pos.funding_fees

        trade = BTTrade(
            symbol=pos.symbol, direction=pos.direction,
            entry_price=pos.entry_price, exit_price=exit_price,
            size=pos.original_size, pnl=pnl, pnl_pct=pnl_pct,
            fees=fee, setup_type=pos.setup_type, close_reason=reason,
            opened_at=pos.opened_at,
            closed_at=dt.to_pydatetime() if hasattr(dt, 'to_pydatetime') else dt,
            r_multiple=r_multiple, funding_fees=pos.funding_fees,
        )
        self.trades.append(trade)
        self.equity += pnl

        if pos in self.positions:
            self.positions.remove(pos)

    def _calc_unrealized(self, pos, symbol_data, ts):
        """计算未实现盈亏"""
        if pos.symbol not in symbol_data:
            return 0
        df = symbol_data[pos.symbol]
        row = df[df['timestamp'] == ts]
        if row.empty:
            return 0
        price = row.iloc[0]['close']
        if pos.direction == 1:
            return (price - pos.entry_price) * pos.size
        else:
            return (pos.entry_price - price) * pos.size

    def get_metrics(self):
        """计算回测指标"""
        from app.backtest.metrics import calculate_metrics
        return calculate_metrics(self.trades, self.equity_curve, self.daily_pnl,
                                self.initial_balance, self.equity)
