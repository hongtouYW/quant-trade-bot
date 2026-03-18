#!/usr/bin/env python3
"""运行参数优化 - 快速版 (减少搜索空间)"""
import os
import sys
import json
import logging

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.config import load_config
from app.data.exchange_client import ExchangeClient
from app.backtest.optimizer import ParameterOptimizer

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
log = logging.getLogger(__name__)


# 精简搜索空间: 3×3×3×3 = 81 个组合 (~2.7小时)
FAST_PARAM_GRID = {
    'stop_atr_multiple': [1.5, 2.0, 3.0],
    'tp1_r_multiple': [1.0, 1.5, 2.0],
    'tp2_r_multiple': [2.0, 2.8, 3.5],
    'risk_per_trade': [0.002, 0.004, 0.006],
}


def main():
    load_config()

    api_key = os.environ.get('BINANCE_API_KEY', '')
    api_secret = os.environ.get('BINANCE_API_SECRET', '')
    exchange = ExchangeClient(api_key, api_secret, sandbox=False)

    symbols = ['BTC/USDT', 'ETH/USDT']  # 2 symbols for speed
    days = 30
    bars_needed = days * 24 * 4  # 2880 bars

    symbol_data = {}
    for sym in symbols:
        log.info(f"下载 {sym} {days}天数据...")
        df = exchange.fetch_ohlcv_paginated(sym, '15m', total_bars=bars_needed)
        if df is not None and not df.empty:
            symbol_data[sym] = df
            log.info(f"  {sym}: {len(df)} 根K线")

    if not symbol_data:
        log.error("无数据")
        return

    # 运行优化
    optimizer = ParameterOptimizer(symbol_data, symbols)
    results = optimizer.optimize(
        param_grid=FAST_PARAM_GRID,
        metric='profit_factor',
        top_n=10,
        max_combos=81,
    )

    print("\n" + "=" * 60)
    print(optimizer.get_summary(10))
    print("=" * 60)

    suggestions = optimizer.suggest_config_update()
    if suggestions:
        print("\n建议配置更新:")
        for k, v in suggestions.items():
            print(f"  {k}: {v['current']} -> {v['suggested']}")

    # 保存结果
    output = []
    for r in results:
        output.append({
            'params': r['params'],
            'score': r['score'],
            'total_return_pct': r['metrics'].get('total_return_pct', 0),
            'win_rate': r['metrics'].get('win_rate', 0),
            'profit_factor': r['metrics'].get('profit_factor', 0),
            'max_drawdown_pct': r['metrics'].get('max_drawdown_pct', 0),
            'sharpe': r['metrics'].get('sharpe', 0),
            'total_trades': r['metrics'].get('total_trades', 0),
        })
    with open('optimize_result.json', 'w') as f:
        json.dump(output, f, indent=2, default=str)
    log.info("结果已保存到 optimize_result.json")


if __name__ == '__main__':
    main()
