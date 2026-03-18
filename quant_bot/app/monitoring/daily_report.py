"""每日报告生成"""
import logging
from datetime import date
from collections import defaultdict
from app.monitoring.notifier import send_telegram

log = logging.getLogger(__name__)


def generate_daily_report(trade_history, risk_engine, active_pool):
    """生成并发送每日报告"""
    today = date.today()

    # 今日交易
    today_trades = [t for t in trade_history
                    if hasattr(t, 'closed_at') and t.closed_at.date() == today]

    if not today_trades and risk_engine.today_trades == 0:
        send_telegram(f"<b>📊 每日报告 {today}</b>\n今日无交易")
        return

    total = len(today_trades)
    wins = sum(1 for t in today_trades if t.pnl > 0)
    losses = total - wins
    win_rate = (wins / total * 100) if total > 0 else 0

    total_pnl = sum(t.pnl for t in today_trades)
    avg_pnl = total_pnl / total if total > 0 else 0

    # 盈亏比
    avg_win = 0
    avg_loss = 0
    win_trades = [t for t in today_trades if t.pnl > 0]
    loss_trades = [t for t in today_trades if t.pnl <= 0]
    if win_trades:
        avg_win = sum(t.pnl for t in win_trades) / len(win_trades)
    if loss_trades:
        avg_loss = abs(sum(t.pnl for t in loss_trades) / len(loss_trades))
    rr = avg_win / avg_loss if avg_loss > 0 else 0

    # 按币种分组
    by_symbol = defaultdict(lambda: {'count': 0, 'pnl': 0})
    for t in today_trades:
        by_symbol[t.symbol]['count'] += 1
        by_symbol[t.symbol]['pnl'] += t.pnl

    best_sym = max(by_symbol.items(), key=lambda x: x[1]['pnl'])[0] if by_symbol else '-'
    worst_sym = min(by_symbol.items(), key=lambda x: x[1]['pnl'])[0] if by_symbol else '-'

    # 按策略分组 (Spec §26.2: 哪个setup表现更好)
    by_setup = defaultdict(lambda: {'count': 0, 'pnl': 0, 'wins': 0})
    for t in today_trades:
        by_setup[t.setup_type]['count'] += 1
        by_setup[t.setup_type]['pnl'] += t.pnl
        if t.pnl > 0:
            by_setup[t.setup_type]['wins'] += 1

    setup_str = '\n'.join(
        f"  {k}: {v['count']}笔 {v['pnl']:+.2f}U 胜率{v['wins']/v['count']*100:.0f}%"
        for k, v in by_setup.items()
    )

    # 按时段分组 (4小时窗口)
    by_period = defaultdict(lambda: {'count': 0, 'pnl': 0})
    for t in today_trades:
        hour = t.closed_at.hour
        period = f"{(hour // 4) * 4:02d}-{(hour // 4) * 4 + 4:02d}UTC"
        by_period[period]['count'] += 1
        by_period[period]['pnl'] += t.pnl
    period_str = ', '.join(f"{k}({v['count']}笔{v['pnl']:+.1f})" for k, v in sorted(by_period.items()))

    # 触发的风控规则
    risk_events = []
    if risk_engine.daily_pnl_pct <= -0.03:
        risk_events.append("日亏损限制")
    if risk_engine.consecutive_losses >= 3:
        risk_events.append(f"连亏{risk_engine.consecutive_losses}笔")
    if risk_engine.daily_pnl_pct >= 0.025:
        risk_events.append("盈利保护模式")
    risk_str = ', '.join(risk_events) if risk_events else '无'

    # 平仓原因统计
    reasons = defaultdict(int)
    for t in today_trades:
        reasons[t.close_reason] += 1
    reason_str = ', '.join(f"{k}:{v}" for k, v in reasons.items())

    pnl_emoji = "📈" if total_pnl >= 0 else "📉"
    msg = (
        f"<b>{pnl_emoji} 每日报告 {today}</b>\n\n"
        f"开仓: {total}笔 | 胜率: {win_rate:.1f}%\n"
        f"净收益: {total_pnl:+.2f}U\n"
        f"盈亏比: {rr:.2f}\n"
        f"平均每笔: {avg_pnl:+.2f}U\n\n"
        f"<b>策略表现:</b>\n{setup_str}\n\n"
        f"最佳币种: {best_sym}\n"
        f"最差币种: {worst_sym}\n"
        f"平仓原因: {reason_str}\n\n"
        f"时段: {period_str}\n"
        f"风控触发: {risk_str}\n"
        f"活跃池: {len(active_pool)}个币种"
    )

    send_telegram(msg)
    log.info(f"每日报告已发送: {total}笔交易, PnL={total_pnl:+.2f}U")
