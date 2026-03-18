"""
运行回测
用法: python scripts/run_backtest.py [--start 2026-01-01] [--end 2026-03-01] [--data data/history]
"""
import asyncio
import json
import sys
import argparse
from pathlib import Path
from datetime import datetime

ROOT = Path(__file__).parent.parent
sys.path.insert(0, str(ROOT))

from dotenv import load_dotenv
load_dotenv(ROOT / ".env")

from src.core.config import Config
from src.core.models import Kline
from src.backtest.engine import BacktestEngine
from src.backtest.reporter import BacktestReporter


def load_history(data_dir: str) -> dict:
    """加载历史数据: {symbol: {'1h': [Kline], '15m': [Kline]}}"""
    path = Path(data_dir)
    if not path.exists():
        print(f"数据目录不存在: {path}")
        print("请先运行: python scripts/fetch_history.py")
        sys.exit(1)

    data = {}
    for sym_dir in sorted(path.iterdir()):
        if not sym_dir.is_dir():
            continue
        symbol = sym_dir.name.replace('_', '/').replace('/USDT/USDT', '/USDT:USDT')
        data[symbol] = {}

        for tf_file in sym_dir.iterdir():
            if not tf_file.suffix == '.json':
                continue
            tf = tf_file.stem  # '1h' or '15m'
            with open(tf_file, 'r') as f:
                raw = json.load(f)

            klines = []
            for bar in raw:
                klines.append(Kline(
                    timestamp=bar['timestamp'],
                    open=bar['open'],
                    high=bar['high'],
                    low=bar['low'],
                    close=bar['close'],
                    volume=bar['volume'],
                    quote_volume=bar['quote_volume'],
                    taker_buy_volume=bar.get('taker_buy_volume', 0),
                ))
            data[symbol][tf] = klines

    return data


def main():
    parser = argparse.ArgumentParser(description='运行回测')
    parser.add_argument('--start', type=str, default='2026-01-01', help='起始日期')
    parser.add_argument('--end', type=str, default='2026-03-01', help='结束日期')
    parser.add_argument('--data', type=str, default='data/history', help='数据目录')
    parser.add_argument('--output', type=str, default='data/backtest_report', help='报告输出目录')
    args = parser.parse_args()

    config = Config()
    cfg = config.data

    print(f"加载历史数据: {args.data}")
    data = load_history(args.data)
    print(f"共 {len(data)} 个交易对")

    if not data:
        print("无数据可回测")
        return

    print(f"回测区间: {args.start} ~ {args.end}")
    print(f"初始本金: {cfg.get('account', {}).get('balance', 2000)} USDT")
    print(f"杠杆: {cfg.get('account', {}).get('leverage', 10)}x")
    print()

    engine = BacktestEngine(cfg)
    result = engine.run(data, args.start, args.end)

    # 输出摘要
    print("=" * 50)
    print("回测结果摘要")
    print("=" * 50)
    print(f"总收益:     {result.total_return_pct:+.2f}%")
    print(f"最大回撤:   {result.max_drawdown_pct:.2f}%")
    print(f"胜率:       {result.win_rate*100:.1f}%")
    print(f"盈亏比:     {result.profit_factor:.2f}")
    print(f"总交易数:   {result.total_trades}")
    print(f"日均交易:   {result.avg_trades_per_day:.1f}")
    print(f"最大连亏:   {result.max_consecutive_losses}")
    print(f"总手续费:   {result.total_fees:.2f}U")

    # 生成报告
    reporter = BacktestReporter(result, args.output)
    reporter.generate()
    print(f"\n详细报告已保存到: {args.output}/")


if __name__ == '__main__':
    main()
