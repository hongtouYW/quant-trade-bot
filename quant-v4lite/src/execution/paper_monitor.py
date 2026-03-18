"""
模拟交易持仓监控

在 paper trading 模式下替代 PositionMonitor:
- 从交易所拉取实时价格（不下单）
- 模拟止损、TP1/TP2、移动止盈、时间止损
- 平仓时更新 RiskControl 余额
"""
import asyncio
import logging
from datetime import datetime

from src.core.enums import Direction, FillType
from src.exchange.base import ExchangeClient
from src.risk.stop_manager import StopManager
from src.risk.portfolio import PortfolioManager
from src.risk.risk_control import RiskControl
from src.utils.telegram import TelegramNotifier

logger = logging.getLogger(__name__)


class PaperPositionMonitor:
    """模拟交易持仓监控 - 每 15 秒检查一次"""

    def __init__(self, exchange: ExchangeClient,
                 stop_manager: StopManager,
                 portfolio: PortfolioManager,
                 risk_control: RiskControl,
                 telegram: TelegramNotifier,
                 config: dict):
        self._exchange = exchange
        self._stop_mgr = stop_manager
        self._portfolio = portfolio
        self._risk = risk_control
        self._telegram = telegram
        self._cfg = config
        self._running = False
        self._db = None  # 由 engine 注入

    async def monitor_once(self):
        """单次扫描所有模拟持仓"""
        positions = list(self._portfolio.positions.values())

        for pos in positions:
            try:
                ticker = await self._exchange.fetch_ticker(pos.symbol)
                current_price = ticker.get('last', 0)
                if current_price <= 0:
                    continue

                # 更新最优价
                if pos.direction == Direction.LONG:
                    pos.best_price = max(pos.best_price, current_price)
                else:
                    pos.best_price = min(pos.best_price, current_price)

                # 检查止损触发
                sl_hit = False
                if pos.direction == Direction.LONG and current_price <= pos.stop_loss:
                    sl_hit = True
                elif pos.direction == Direction.SHORT and current_price >= pos.stop_loss:
                    sl_hit = True

                if sl_hit:
                    pnl = self._calc_pnl(pos, current_price)
                    self._close_paper_position(pos, current_price, pnl, "stop_loss")
                    continue

                # TP1 检查
                if not pos.tp1_hit and pos.take_profits:
                    tp1_price = pos.take_profits[0][0]
                    tp1_hit = False
                    if pos.direction == Direction.LONG and current_price >= tp1_price:
                        tp1_hit = True
                    elif pos.direction == Direction.SHORT and current_price <= tp1_price:
                        tp1_hit = True

                    if tp1_hit:
                        tp_pct = pos.take_profits[0][1]
                        partial_pnl = self._calc_pnl(pos, current_price) * tp_pct
                        fee_rate = self._cfg.get('execution', {}).get('fee_rate', 0.0004)
                        partial_fee = pos.notional * tp_pct * fee_rate * 2
                        pos.tp1_hit = True
                        self._portfolio.partial_close(pos.id, tp_pct)
                        self._risk.on_trade_close(partial_pnl, pos.symbol)
                        logger.info(f"[模拟] TP1 命中 {pos.symbol} @{current_price:.4f} "
                                    f"平仓 {tp_pct*100:.0f}% PnL={partial_pnl:+.2f}U "
                                    f"手续费={partial_fee:.4f}U")
                        # 保存 TP1 记录到数据库 + 更新活跃持仓
                        if self._db:
                            asyncio.ensure_future(self._save_and_notify(
                                pos, current_price, partial_pnl, partial_fee,
                                'tp1', 'TP1止盈(50%)',
                                pos.current_r(current_price)))
                            asyncio.ensure_future(self._db.update_open_position(
                                pos.id, {
                                    'stop_loss': pos.stop_loss,
                                    'best_price': pos.best_price,
                                    'remaining_pct': pos.remaining_pct,
                                    'tp1_hit': pos.tp1_hit,
                                }))
                        continue

                # TP2 检查 (第二个止盈)
                if pos.tp1_hit and len(pos.take_profits) > 1:
                    tp2_price = pos.take_profits[1][0]
                    tp2_hit = False
                    if pos.direction == Direction.LONG and current_price >= tp2_price:
                        tp2_hit = True
                    elif pos.direction == Direction.SHORT and current_price <= tp2_price:
                        tp2_hit = True

                    if tp2_hit:
                        pnl = self._calc_pnl(pos, current_price)
                        self._close_paper_position(pos, current_price, pnl, "tp2")
                        continue

                # 更新移动止损
                new_stop = self._stop_mgr.update(pos, current_price)
                if new_stop != pos.stop_loss:
                    old_stop = pos.stop_loss
                    pos.stop_loss = new_stop
                    logger.info(f"[模拟] 移动止损 {pos.symbol}: "
                                f"{old_stop:.4f} → {new_stop:.4f}")
                    if self._db:
                        asyncio.ensure_future(self._db.update_open_position(
                            pos.id, {
                                'stop_loss': pos.stop_loss,
                                'best_price': pos.best_price,
                                'remaining_pct': pos.remaining_pct,
                                'tp1_hit': pos.tp1_hit,
                            }))

                # 时间止损
                if self._stop_mgr.check_time_stop(pos, current_price):
                    pnl = self._calc_pnl(pos, current_price)
                    self._close_paper_position(pos, current_price, pnl, "time_stop")
                    continue

            except Exception as e:
                logger.error(f"[模拟] 监控错误 {pos.symbol}: {e}")

    async def _get_funding_fee(self, pos) -> float:
        """估算持仓期间的累计资金费率费用"""
        try:
            # 默认费率 0.01% 每8小时
            funding_rate = 0.0001
            funding_periods = int(pos.holding_minutes / (8 * 60))
            if funding_periods < 1:
                return 0.0
            notional = pos.notional * pos.remaining_pct
            fee = notional * funding_rate * funding_periods
            # LONG 付出正费率，SHORT 收到正费率
            return fee if pos.direction == Direction.LONG else -fee
        except Exception:
            return 0.0

    def _calc_pnl(self, pos, current_price: float) -> float:
        """计算模拟盈亏（含交易手续费）"""
        raw_pnl = pos.unrealized_pnl(current_price)
        fee_rate = self._cfg.get('execution', {}).get('fee_rate', 0.0004)
        fee = pos.notional * pos.remaining_pct * fee_rate * 2
        return raw_pnl - fee

    def _close_paper_position(self, pos, exit_price: float,
                               pnl: float, reason: str):
        """模拟平仓 + 保存到数据库"""
        self._portfolio.close_position(pos.id)
        self._risk.on_trade_close(pnl, pos.symbol)

        # 从活跃持仓表删除
        if self._db:
            asyncio.ensure_future(self._db.remove_open_position(pos.id))

        pnl_str = f"{pnl:+.2f}"
        r_val = pos.current_r(exit_price)
        fee_rate = self._cfg.get('execution', {}).get('fee_rate', 0.0004)
        fee = pos.notional * pos.remaining_pct * fee_rate * 2

        # 平仓原因中文映射
        reason_map = {
            'stop_loss': '止损',
            'tp1': 'TP1止盈(50%)',
            'tp2': 'TP2止盈(100%)',
            'trailing': '移动止盈',
            'time_stop': '时间止损(>60min且<0.3R)',
        }
        reason_cn = reason_map.get(reason, reason)

        logger.info(f"[模拟] 平仓 {pos.direction.name} {pos.symbol} "
                    f"@{exit_price:.4f} 原因={reason_cn} "
                    f"PnL={pnl_str}U R={r_val:.1f} 手续费={fee:.4f}U")

        # 异步保存到数据库 + 发通知
        asyncio.ensure_future(self._save_and_notify(
            pos, exit_price, pnl, fee, reason, reason_cn, r_val))

    async def _save_and_notify(self, pos, exit_price, pnl, fee,
                                reason, reason_cn, r_val):
        """异步保存交易记录到数据库并发送通知"""
        # 获取资金费
        funding_fee = await self._get_funding_fee(pos)

        # 保存到数据库
        if self._db:
            trade_record = {
                'id': f"paper_{pos.id}_{reason}",
                'symbol': pos.symbol,
                'direction': pos.direction.name,
                'strategy': pos.strategy.value,
                'entry_price': pos.entry_price,
                'exit_price': exit_price,
                'quantity': pos.quantity * pos.remaining_pct,
                'margin': pos.margin,
                'pnl': pnl,
                'fee': fee,
                'funding_fee': funding_fee,
                'fill_type': FillType.MARKET.value,
                'open_time': pos.open_time,
                'close_time': datetime.utcnow(),
                'close_reason': reason,
            }
            try:
                await self._db.insert_trade(trade_record)
                logger.info(f"[DB] 交易记录已保存: {pos.symbol} {reason}")
            except Exception as e:
                logger.error(f"[DB] 保存失败: {e}")

        # 发送通知
        pnl_str = f"{pnl:+.2f}"
        funding_str = f" | 资金费: {funding_fee:+.4f}U" if abs(funding_fee) > 0.001 else ""
        try:
            await self._telegram.notify(
                'trade',
                f"[模拟] 平仓 {pos.symbol} {pos.direction.name}\n"
                f"入场: {pos.entry_price:.4f} → 出场: {exit_price:.4f}\n"
                f"原因: {reason_cn} | PnL: {pnl_str}U | R: {r_val:.1f}\n"
                f"手续费: {fee:.4f}U{funding_str}"
            )
        except Exception as e:
            logger.error(f"[Telegram] 通知失败: {e}")

    async def monitor_loop(self):
        """持续监控循环"""
        self._running = True
        logger.info("[模拟] 持仓监控已启动 (15秒间隔)")
        while self._running:
            await self.monitor_once()
            await asyncio.sleep(15)

    def stop(self):
        self._running = False
