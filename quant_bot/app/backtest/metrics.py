"""回测指标计算"""
import numpy as np
from collections import defaultdict


def calculate_metrics(trades, equity_curve, daily_pnl, initial_balance, final_equity):
    """计算全部回测指标"""
    if not trades:
        return {'error': '无交易记录'}

    pnls = [t.pnl for t in trades]
    pnl_pcts = [t.pnl_pct for t in trades]
    wins = [t for t in trades if t.pnl > 0]
    losses = [t for t in trades if t.pnl <= 0]

    total_return = (final_equity - initial_balance) / initial_balance
    win_rate = len(wins) / len(trades) if trades else 0

    # 平均盈亏比
    avg_win = np.mean([t.pnl for t in wins]) if wins else 0
    avg_loss = abs(np.mean([t.pnl for t in losses])) if losses else 1
    avg_rr = avg_win / avg_loss if avg_loss > 0 else 0

    # Profit Factor
    gross_profit = sum(t.pnl for t in wins)
    gross_loss = abs(sum(t.pnl for t in losses))
    profit_factor = gross_profit / gross_loss if gross_loss > 0 else float('inf')

    # 最大回撤
    max_dd = 0
    max_dd_pct = 0
    peak = initial_balance
    for point in equity_curve:
        eq = point['equity']
        if eq > peak:
            peak = eq
        dd = (peak - eq) / peak
        if dd > max_dd_pct:
            max_dd_pct = dd
            max_dd = peak - eq

    # 日最大回撤
    daily_dd = 0
    if daily_pnl:
        for d, pnl in daily_pnl.items():
            dd_pct = abs(pnl) / initial_balance if pnl < 0 else 0
            if dd_pct > daily_dd:
                daily_dd = dd_pct

    # 最大连亏
    max_consecutive_losses = 0
    current_streak = 0
    for t in trades:
        if t.pnl <= 0:
            current_streak += 1
            max_consecutive_losses = max(max_consecutive_losses, current_streak)
        else:
            current_streak = 0

    # 月收益率
    monthly_returns = defaultdict(float)
    for t in trades:
        key = t.closed_at.strftime('%Y-%m') if hasattr(t.closed_at, 'strftime') else 'unknown'
        monthly_returns[key] += t.pnl

    # Sharpe / Sortino (年化，假设日频)
    daily_returns = list(daily_pnl.values()) if daily_pnl else []
    sharpe = 0
    sortino = 0
    if len(daily_returns) > 10:
        dr = np.array(daily_returns) / initial_balance
        mean_r = np.mean(dr)
        std_r = np.std(dr)
        if std_r > 0:
            sharpe = mean_r / std_r * np.sqrt(365)
        downside = dr[dr < 0]
        if len(downside) > 0:
            downside_std = np.std(downside)
            if downside_std > 0:
                sortino = mean_r / downside_std * np.sqrt(365)

    # 按币种分组
    by_symbol = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0})
    for t in trades:
        by_symbol[t.symbol]['trades'] += 1
        by_symbol[t.symbol]['pnl'] += t.pnl
        if t.pnl > 0:
            by_symbol[t.symbol]['wins'] += 1

    # 按策略分组
    by_setup = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0})
    for t in trades:
        by_setup[t.setup_type]['trades'] += 1
        by_setup[t.setup_type]['pnl'] += t.pnl
        if t.pnl > 0:
            by_setup[t.setup_type]['wins'] += 1

    # 按时段分组 (小时)
    by_hour = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0})
    for t in trades:
        h = t.opened_at.hour if hasattr(t.opened_at, 'hour') else 0
        by_hour[h]['trades'] += 1
        by_hour[h]['pnl'] += t.pnl
        if t.pnl > 0:
            by_hour[h]['wins'] += 1

    # 按4小时时段分组 (Spec §23.3 + §26.2)
    by_period = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0, 'win_rate': 0})
    for t in trades:
        h = t.opened_at.hour if hasattr(t.opened_at, 'hour') else 0
        period = f"{(h // 4) * 4:02d}-{(h // 4) * 4 + 4:02d}UTC"
        by_period[period]['trades'] += 1
        by_period[period]['pnl'] += t.pnl
        if t.pnl > 0:
            by_period[period]['wins'] += 1
    for k, v in by_period.items():
        v['win_rate'] = round(v['wins'] / v['trades'] * 100, 1) if v['trades'] > 0 else 0

    # 按平仓原因分组
    by_reason = defaultdict(lambda: {'trades': 0, 'pnl': 0})
    for t in trades:
        by_reason[t.close_reason]['trades'] += 1
        by_reason[t.close_reason]['pnl'] += t.pnl

    # 总费用
    total_fees = sum(t.fees for t in trades)
    total_funding = sum(t.funding_fees for t in trades)

    return {
        'total_trades': len(trades),
        'total_return_pct': round(total_return * 100, 2),
        'total_return_u': round(final_equity - initial_balance, 2),
        'final_equity': round(final_equity, 2),
        'win_rate': round(win_rate * 100, 2),
        'avg_rr': round(avg_rr, 2),
        'profit_factor': round(profit_factor, 2),
        'max_drawdown_pct': round(max_dd_pct * 100, 2),
        'max_drawdown_u': round(max_dd, 2),
        'daily_max_drawdown_pct': round(daily_dd * 100, 2),
        'max_consecutive_losses': max_consecutive_losses,
        'sharpe': round(sharpe, 2),
        'sortino': round(sortino, 2),
        'total_fees': round(total_fees, 2),
        'total_funding_fees': round(total_funding, 2),
        'avg_pnl_per_trade': round(np.mean(pnls), 2),
        'monthly_returns': {k: round(v, 2) for k, v in sorted(monthly_returns.items())},
        'by_symbol': {k: {kk: round(vv, 2) if isinstance(vv, float) else vv
                          for kk, vv in v.items()} for k, v in by_symbol.items()},
        'by_setup': {k: {kk: round(vv, 2) if isinstance(vv, float) else vv
                         for kk, vv in v.items()} for k, v in by_setup.items()},
        'by_hour': {str(k): {kk: round(vv, 2) if isinstance(vv, float) else vv
                             for kk, vv in v.items()} for k, v in sorted(by_hour.items())},
        'by_period': {k: {kk: round(vv, 2) if isinstance(vv, float) else vv
                          for kk, vv in v.items()} for k, v in sorted(by_period.items())},
        'by_close_reason': {k: {kk: round(vv, 2) if isinstance(vv, float) else vv
                                for kk, vv in v.items()} for k, v in by_reason.items()},
        'pass_criteria': {
            'max_dd_ok': max_dd_pct <= 0.15,
            'win_rate_ok': win_rate >= 0.38,
            'avg_rr_ok': avg_rr >= 1.7,
            'profit_factor_ok': profit_factor >= 1.25,
            'sample_ok': len(trades) >= 200,
        }
    }
