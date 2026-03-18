#!/usr/bin/env python3
"""运行回测"""
import os
import sys
import json
import logging
import argparse

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.config import load_config
from app.data.exchange_client import ExchangeClient
from app.backtest.engine import BacktestEngine

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
log = logging.getLogger(__name__)


def main():
    parser = argparse.ArgumentParser(description='QuantBot 回测')
    parser.add_argument('--symbols', nargs='+', default=['BTC/USDT', 'ETH/USDT', 'SOL/USDT'],
                        help='回测币种')
    parser.add_argument('--timeframe', default='15m', help='K线周期')
    parser.add_argument('--days', type=int, default=180, help='回测天数')
    parser.add_argument('--config', default=None, help='配置文件路径')
    parser.add_argument('--output', default='backtest_result.json', help='结果输出文件')
    args = parser.parse_args()

    load_config(args.config)

    # 获取历史数据
    api_key = os.environ.get('BINANCE_API_KEY', '')
    api_secret = os.environ.get('BINANCE_API_SECRET', '')
    exchange = ExchangeClient(api_key, api_secret, sandbox=False)

    symbol_data = {}
    bars_needed = args.days * 24 * 4  # 15分钟线

    for sym in args.symbols:
        log.info(f"下载 {sym} {args.days}天 {args.timeframe} 数据 ({bars_needed}根)...")
        if bars_needed > 1500:
            df = exchange.fetch_ohlcv_paginated(sym, args.timeframe, total_bars=bars_needed)
        else:
            df = exchange.fetch_ohlcv(sym, args.timeframe, limit=bars_needed)
        if df is not None and not df.empty:
            symbol_data[sym] = df
            log.info(f"  {sym}: {len(df)} 根K线")
        else:
            log.warning(f"  {sym}: 数据获取失败")

    if not symbol_data:
        log.error("无可用数据，退出")
        return

    # 运行回测
    engine = BacktestEngine()
    metrics = engine.run(symbol_data, args.symbols)

    # 输出结果
    print("\n" + "=" * 60)
    print("回测结果")
    print("=" * 60)
    print(f"总交易笔数: {metrics.get('total_trades', 0)}")
    print(f"总收益率: {metrics.get('total_return_pct', 0)}%")
    print(f"最终权益: {metrics.get('final_equity', 0)}U")
    print(f"胜率: {metrics.get('win_rate', 0)}%")
    print(f"盈亏比: {metrics.get('avg_rr', 0)}")
    print(f"Profit Factor: {metrics.get('profit_factor', 0)}")
    print(f"最大回撤: {metrics.get('max_drawdown_pct', 0)}%")
    print(f"Sharpe: {metrics.get('sharpe', 0)}")
    print(f"Sortino: {metrics.get('sortino', 0)}")
    print(f"最大连亏: {metrics.get('max_consecutive_losses', 0)}")
    print(f"总手续费: {metrics.get('total_fees', 0)}U")

    # 通过标准
    print("\n通过标准:")
    criteria = metrics.get('pass_criteria', {})
    for k, v in criteria.items():
        status = "✅" if v else "❌"
        print(f"  {status} {k}: {v}")

    # 月度收益
    print("\n月度收益:")
    for month, ret in metrics.get('monthly_returns', {}).items():
        print(f"  {month}: {ret:+.2f}U")

    # 保存结果
    output_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), args.output)
    with open(output_path, 'w') as f:
        json.dump(metrics, f, indent=2, default=str)
    log.info(f"结果已保存到 {output_path}")


if __name__ == '__main__':
    main()
