"""回测报告生成 (Spec §23.3)
生成结构化回测报告，支持:
  - 文本摘要
  - JSON完整输出
  - 按多维度分析 (币种/策略/时段/平仓原因)
"""
import json
import logging
from datetime import datetime

log = logging.getLogger(__name__)


def generate_text_report(metrics):
    """生成回测文本摘要"""
    if 'error' in metrics:
        return f"回测失败: {metrics['error']}"

    lines = [
        "=" * 60,
        "回测结果报告",
        "=" * 60,
        f"总交易笔数: {metrics.get('total_trades', 0)}",
        f"总收益率:   {metrics.get('total_return_pct', 0):+.2f}%",
        f"最终权益:   {metrics.get('final_equity', 0):.2f}U",
        f"胜率:       {metrics.get('win_rate', 0):.2f}%",
        f"盈亏比:     {metrics.get('avg_rr', 0):.2f}",
        f"Profit Factor: {metrics.get('profit_factor', 0):.2f}",
        f"最大回撤:   {metrics.get('max_drawdown_pct', 0):.2f}%",
        f"Sharpe:     {metrics.get('sharpe', 0):.2f}",
        f"Sortino:    {metrics.get('sortino', 0):.2f}",
        f"最大连亏:   {metrics.get('max_consecutive_losses', 0)}",
        f"总手续费:   {metrics.get('total_fees', 0):.2f}U",
        f"总资金费:   {metrics.get('total_funding_fees', 0):.2f}U",
        "",
    ]

    # Spec §23.4 通过标准
    lines.append("通过标准 (Spec §23.4):")
    criteria = metrics.get('pass_criteria', {})
    criteria_labels = {
        'max_dd_ok': '最大回撤 ≤ 15%',
        'win_rate_ok': '胜率 ≥ 38%',
        'avg_rr_ok': '盈亏比 ≥ 1.7',
        'profit_factor_ok': 'PF ≥ 1.25',
        'sample_ok': '样本 ≥ 200笔',
    }
    for k, label in criteria_labels.items():
        status = "PASS" if criteria.get(k) else "FAIL"
        lines.append(f"  [{status}] {label}")

    # 月度收益
    monthly = metrics.get('monthly_returns', {})
    if monthly:
        lines.append("")
        lines.append("月度收益:")
        for month, ret in sorted(monthly.items()):
            lines.append(f"  {month}: {ret:+.2f}U")

    # 按策略
    by_setup = metrics.get('by_setup', {})
    if by_setup:
        lines.append("")
        lines.append("按策略类型:")
        for setup, data in by_setup.items():
            trades = data.get('trades', 0)
            pnl = data.get('pnl', 0)
            wins = data.get('wins', 0)
            wr = wins / trades * 100 if trades > 0 else 0
            lines.append(f"  {setup}: {trades}笔, PnL={pnl:+.2f}U, 胜率={wr:.0f}%")

    # 按币种
    by_symbol = metrics.get('by_symbol', {})
    if by_symbol:
        lines.append("")
        lines.append("按币种:")
        for sym, data in sorted(by_symbol.items()):
            trades = data.get('trades', 0)
            pnl = data.get('pnl', 0)
            wins = data.get('wins', 0)
            wr = wins / trades * 100 if trades > 0 else 0
            lines.append(f"  {sym}: {trades}笔, PnL={pnl:+.2f}U, 胜率={wr:.0f}%")

    # 按时段
    by_period = metrics.get('by_period', {})
    if by_period:
        lines.append("")
        lines.append("按时段:")
        for period, data in sorted(by_period.items()):
            trades = data.get('trades', 0)
            pnl = data.get('pnl', 0)
            wr = data.get('win_rate', 0)
            lines.append(f"  {period}: {trades}笔, PnL={pnl:+.2f}U, 胜率={wr:.0f}%")

    lines.append("=" * 60)
    return "\n".join(lines)


def save_json_report(metrics, filepath):
    """保存JSON格式完整报告"""
    with open(filepath, 'w') as f:
        json.dump(metrics, f, indent=2, default=str)
    log.info(f"回测报告已保存: {filepath}")
