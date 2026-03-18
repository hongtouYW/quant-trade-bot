"""
回测报表生成
- 权益曲线图 (PNG)
- 交易明细 (CSV)
- 统计摘要 (TXT)
"""
import csv
import logging
from pathlib import Path
from typing import List

logger = logging.getLogger(__name__)


class BacktestReporter:
    """回测报告生成器"""

    def __init__(self, result, output_dir: str = 'data/backtest_report'):
        self._result = result
        self._out = Path(output_dir)

    def generate(self):
        """生成全部报告文件"""
        self._out.mkdir(parents=True, exist_ok=True)

        self._write_summary()
        self._write_trades_csv()
        self._write_equity_curve()

        logger.info(f"回测报告已生成: {self._out}")

    def _write_summary(self):
        """统计摘要"""
        r = self._result
        filepath = self._out / 'summary.txt'

        lines = [
            "=" * 50,
            "V4-Lite 回测报告",
            "=" * 50,
            "",
            f"总收益率:       {r.total_return_pct:+.2f}%",
            f"最大回撤:       {r.max_drawdown_pct:.2f}%",
            f"胜率:           {r.win_rate*100:.1f}%",
            f"盈亏比:         {r.profit_factor:.2f}",
            f"总交易数:       {r.total_trades}",
            f"日均交易:       {r.avg_trades_per_day:.1f}",
            f"最大连亏:       {r.max_consecutive_losses}",
            f"总手续费:       {r.total_fees:.2f} USDT",
            "",
        ]

        # 按策略统计
        strategy_stats = {}
        for t in r.trades:
            s = t.strategy.value
            if s not in strategy_stats:
                strategy_stats[s] = {'wins': 0, 'losses': 0, 'pnl': 0}
            if t.pnl > 0:
                strategy_stats[s]['wins'] += 1
            else:
                strategy_stats[s]['losses'] += 1
            strategy_stats[s]['pnl'] += t.pnl

        if strategy_stats:
            lines.append("按策略统计:")
            lines.append("-" * 40)
            for name, stats in sorted(strategy_stats.items()):
                total = stats['wins'] + stats['losses']
                wr = stats['wins'] / total * 100 if total > 0 else 0
                lines.append(f"  {name:25s} | {total:3d}笔 | 胜率 {wr:5.1f}% | PnL {stats['pnl']:+8.2f}U")

        lines.append("")

        # 按方向统计
        long_trades = [t for t in r.trades if t.direction.name == 'LONG']
        short_trades = [t for t in r.trades if t.direction.name == 'SHORT']
        long_pnl = sum(t.pnl for t in long_trades)
        short_pnl = sum(t.pnl for t in short_trades)
        lines.append(f"做多: {len(long_trades)}笔 PnL={long_pnl:+.2f}U")
        lines.append(f"做空: {len(short_trades)}笔 PnL={short_pnl:+.2f}U")

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write('\n'.join(lines))

    def _write_trades_csv(self):
        """交易明细 CSV"""
        filepath = self._out / 'trades.csv'
        if not self._result.trades:
            return

        with open(filepath, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow([
                'ID', '交易对', '方向', '策略',
                '入场价', '出场价', '数量', '保证金',
                '盈亏', '手续费', '平仓原因',
                '开仓时间', '平仓时间',
            ])
            for t in self._result.trades:
                writer.writerow([
                    t.id, t.symbol, t.direction.name, t.strategy.value,
                    f"{t.entry_price:.6f}", f"{t.exit_price:.6f}",
                    f"{t.quantity:.4f}", f"{t.margin:.2f}",
                    f"{t.pnl:.2f}", f"{t.fee:.4f}", t.close_reason,
                    t.open_time.strftime('%Y-%m-%d %H:%M'),
                    t.close_time.strftime('%Y-%m-%d %H:%M'),
                ])

    def _write_equity_curve(self):
        """权益曲线数据 (CSV) + 简单图表"""
        curve = self._result.equity_curve
        if not curve:
            return

        # 数据 CSV
        filepath = self._out / 'equity_curve.csv'
        with open(filepath, 'w', newline='') as f:
            writer = csv.writer(f)
            writer.writerow(['step', 'balance'])
            for i, b in enumerate(curve):
                writer.writerow([i, f"{b:.2f}"])

        # 尝试绘图
        try:
            import matplotlib
            matplotlib.use('Agg')
            import matplotlib.pyplot as plt

            fig, ax = plt.subplots(figsize=(12, 5))
            ax.plot(curve, linewidth=1, color='#58a6ff')
            ax.axhline(y=curve[0], color='#8b949e', linestyle='--', linewidth=0.5)
            ax.set_title('V4-Lite Backtest Equity Curve', fontsize=14)
            ax.set_xlabel('Time Steps (15m)')
            ax.set_ylabel('Balance (USDT)')
            ax.set_facecolor('#0d1117')
            fig.patch.set_facecolor('#0d1117')
            ax.tick_params(colors='#c9d1d9')
            ax.xaxis.label.set_color('#c9d1d9')
            ax.yaxis.label.set_color('#c9d1d9')
            ax.title.set_color('#c9d1d9')
            for spine in ax.spines.values():
                spine.set_color('#30363d')

            fig.tight_layout()
            fig.savefig(self._out / 'equity_curve.png', dpi=150,
                        facecolor=fig.get_facecolor())
            plt.close(fig)
            logger.info("权益曲线图已生成")
        except ImportError:
            logger.warning("matplotlib 未安装，跳过图表生成")
