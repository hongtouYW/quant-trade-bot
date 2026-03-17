import logging
from datetime import datetime
from typing import List, Dict, Optional
from dataclasses import dataclass, field

from src.core.models import Kline, Signal, TradeRecord
from src.core.enums import Direction, SignalStrategy, FillType
from src.strategy.aggregator import SignalAggregator
from src.analysis.screener import SymbolScreener
from src.risk.position_sizer import PositionSizer
from src.risk.stop_manager import StopManager
from src.risk.risk_control import RiskControl
from src.analysis.regime import DEFAULT_ROUTING

logger = logging.getLogger(__name__)


@dataclass
class BacktestPosition:
    symbol: str
    direction: Direction
    strategy: SignalStrategy
    entry_price: float
    quantity: float
    margin: float
    stop_loss: float
    initial_stop: float
    take_profits: list
    best_price: float
    open_time: datetime
    remaining_pct: float = 1.0
    tp1_hit: bool = False


@dataclass
class BacktestResult:
    total_return_pct: float = 0
    max_drawdown_pct: float = 0
    win_rate: float = 0
    profit_factor: float = 0
    sharpe_ratio: float = 0
    total_trades: int = 0
    avg_trades_per_day: float = 0
    max_consecutive_losses: int = 0
    total_fees: float = 0
    trades: List[TradeRecord] = field(default_factory=list)
    equity_curve: List[float] = field(default_factory=list)


class BacktestEngine:
    """
    回测引擎
    模拟成本: taker 0.04%, maker 0.02%, 滑点 0.015%
    """

    TAKER_FEE = 0.0004
    MAKER_FEE = 0.0002
    SLIPPAGE = 0.00015

    def __init__(self, config: dict):
        self._config = config
        self._aggregator = SignalAggregator()
        self._sizer = PositionSizer()

    def run(self, data: Dict[str, Dict[str, List[Kline]]],
            start: str, end: str) -> BacktestResult:
        """
        data: {symbol: {'1h': [klines], '15m': [klines]}}
        """
        cfg = self._config
        balance = cfg.get('account', {}).get('balance', 2000)
        initial_balance = balance
        leverage = cfg.get('account', {}).get('leverage', 10)

        result = BacktestResult()
        positions: List[BacktestPosition] = []
        risk = RiskControl(cfg, balance)
        stop_mgr = StopManager(cfg)
        routing = DEFAULT_ROUTING

        start_dt = datetime.fromisoformat(start)
        end_dt = datetime.fromisoformat(end)

        peak_balance = balance
        max_dd = 0
        consecutive_losses = 0
        max_consec = 0

        # 按 15m 时间步推进
        all_symbols = list(data.keys())
        if not all_symbols:
            return result

        ref_symbol = all_symbols[0]
        ref_klines = data[ref_symbol].get('15m', [])

        for step, ref_k in enumerate(ref_klines):
            ts = datetime.utcfromtimestamp(ref_k.timestamp / 1000)
            if ts < start_dt or ts > end_dt:
                continue

            # 更新持仓
            closed_positions = []
            for pos in positions:
                sym_15m = data.get(pos.symbol, {}).get('15m', [])
                current_bar = None
                for k in sym_15m:
                    if k.timestamp == ref_k.timestamp:
                        current_bar = k
                        break

                if not current_bar:
                    continue

                price = current_bar.close

                # 更新最优价
                if pos.direction == Direction.LONG:
                    pos.best_price = max(pos.best_price, current_bar.high)
                    # 止损检查
                    if current_bar.low <= pos.stop_loss:
                        pnl = self._calc_pnl(pos, pos.stop_loss, 'stop_loss')
                        result.trades.append(self._make_record(pos, pos.stop_loss, pnl, 'stop_loss', ts))
                        balance += pnl
                        closed_positions.append(pos)
                        risk.on_trade_close(pnl, pos.symbol)
                        if pnl < 0:
                            consecutive_losses += 1
                            max_consec = max(max_consec, consecutive_losses)
                        else:
                            consecutive_losses = 0
                        continue
                    # TP1
                    if not pos.tp1_hit and pos.take_profits:
                        tp1 = pos.take_profits[0][0]
                        if current_bar.high >= tp1:
                            tp_pct = pos.take_profits[0][1]
                            pnl = self._calc_pnl(pos, tp1, 'tp1', tp_pct)
                            balance += pnl
                            pos.tp1_hit = True
                            pos.remaining_pct -= tp_pct
                            result.trades.append(self._make_record(pos, tp1, pnl, 'tp1', ts))
                            risk.on_trade_close(pnl, pos.symbol)
                            consecutive_losses = 0
                else:
                    pos.best_price = min(pos.best_price, current_bar.low)
                    if current_bar.high >= pos.stop_loss:
                        pnl = self._calc_pnl(pos, pos.stop_loss, 'stop_loss')
                        result.trades.append(self._make_record(pos, pos.stop_loss, pnl, 'stop_loss', ts))
                        balance += pnl
                        closed_positions.append(pos)
                        risk.on_trade_close(pnl, pos.symbol)
                        if pnl < 0:
                            consecutive_losses += 1
                            max_consec = max(max_consec, consecutive_losses)
                        else:
                            consecutive_losses = 0
                        continue
                    if not pos.tp1_hit and pos.take_profits:
                        tp1 = pos.take_profits[0][0]
                        if current_bar.low <= tp1:
                            tp_pct = pos.take_profits[0][1]
                            pnl = self._calc_pnl(pos, tp1, 'tp1', tp_pct)
                            balance += pnl
                            pos.tp1_hit = True
                            pos.remaining_pct -= tp_pct
                            result.trades.append(self._make_record(pos, tp1, pnl, 'tp1', ts))
                            risk.on_trade_close(pnl, pos.symbol)
                            consecutive_losses = 0

                # 移除已完成仓位
                if pos.remaining_pct <= 0.01:
                    closed_positions.append(pos)

            for cp in closed_positions:
                if cp in positions:
                    positions.remove(cp)

            # 回撤计算
            peak_balance = max(peak_balance, balance)
            dd = (peak_balance - balance) / peak_balance if peak_balance > 0 else 0
            max_dd = max(max_dd, dd)
            result.equity_curve.append(balance)

            # 每小时才检测新信号 (step % 4 == 0 for 15m)
            if step % 4 != 0:
                continue

            # 风控
            can_trade, _ = risk.pre_trade_check()
            if not can_trade:
                continue

            max_pos = routing.get('max_positions', 3)
            if len(positions) >= max_pos:
                continue

            # 扫描信号
            for sym in all_symbols:
                if len(positions) >= max_pos:
                    break
                if any(p.symbol == sym for p in positions):
                    continue

                klines_1h = data.get(sym, {}).get('1h', [])
                sym_15m = data.get(sym, {}).get('15m', [])

                # 截取到当前时间
                k1h = [k for k in klines_1h if k.timestamp <= ref_k.timestamp]
                k15m = [k for k in sym_15m if k.timestamp <= ref_k.timestamp]

                if len(k1h) < 200 or len(k15m) < 30:
                    continue

                signal = self._aggregator.scan(
                    sym, k1h[-220:], k15m[-100:],
                    routing['strategies'], routing['direction_bias'], cfg)

                if not signal:
                    continue

                risk_scale = risk.get_risk_scale()
                pos_size = self._sizer.calculate(
                    balance, signal, routing, risk_scale, cfg,
                    risk.consecutive_losses)

                if not pos_size:
                    continue

                # 模拟滑点
                if signal.direction == Direction.LONG:
                    actual_entry = signal.entry_price * (1 + self.SLIPPAGE)
                else:
                    actual_entry = signal.entry_price * (1 - self.SLIPPAGE)

                positions.append(BacktestPosition(
                    symbol=sym,
                    direction=signal.direction,
                    strategy=signal.strategy,
                    entry_price=actual_entry,
                    quantity=pos_size.quantity,
                    margin=pos_size.margin,
                    stop_loss=signal.stop_loss,
                    initial_stop=signal.stop_loss,
                    take_profits=signal.take_profits,
                    best_price=actual_entry,
                    open_time=ts,
                ))

        # 计算结果
        result.total_return_pct = (balance - initial_balance) / initial_balance * 100
        result.max_drawdown_pct = max_dd * 100
        result.total_trades = len(result.trades)
        result.max_consecutive_losses = max_consec

        wins = [t for t in result.trades if t.pnl > 0]
        losses = [t for t in result.trades if t.pnl <= 0]
        result.win_rate = len(wins) / len(result.trades) if result.trades else 0

        total_win = sum(t.pnl for t in wins)
        total_loss = abs(sum(t.pnl for t in losses))
        result.profit_factor = total_win / total_loss if total_loss > 0 else 0

        result.total_fees = sum(t.fee for t in result.trades)

        days = (end_dt - start_dt).days or 1
        result.avg_trades_per_day = result.total_trades / days

        return result

    def _calc_pnl(self, pos: BacktestPosition, exit_price: float,
                  reason: str, close_pct: float = 1.0) -> float:
        qty = pos.quantity * pos.remaining_pct * close_pct
        if pos.direction == Direction.LONG:
            raw_pnl = (exit_price - pos.entry_price) * qty
        else:
            raw_pnl = (pos.entry_price - exit_price) * qty

        # 双边手续费
        fee = (pos.entry_price * qty + exit_price * qty) * self.TAKER_FEE
        return raw_pnl - fee

    def _make_record(self, pos: BacktestPosition, exit_price: float,
                     pnl: float, reason: str, ts: datetime) -> TradeRecord:
        qty = pos.quantity * pos.remaining_pct
        fee = (pos.entry_price * qty + exit_price * qty) * self.TAKER_FEE
        return TradeRecord(
            id=f"bt_{pos.symbol}_{ts.strftime('%H%M')}",
            symbol=pos.symbol,
            direction=pos.direction,
            strategy=pos.strategy,
            entry_price=pos.entry_price,
            exit_price=exit_price,
            quantity=qty,
            margin=pos.margin,
            pnl=pnl,
            fee=fee,
            fill_type=FillType.MARKET,
            open_time=pos.open_time,
            close_time=ts,
            close_reason=reason,
        )
