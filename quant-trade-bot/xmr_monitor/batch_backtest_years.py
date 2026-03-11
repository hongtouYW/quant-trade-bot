#!/usr/bin/env python3
"""批量回测 2020/2021/2022 全币种 × 全策略，结果写入 backtest_history.db"""
import sys, os, sqlite3, time, json
sys.path.insert(0, '/opt/trading-bot/quant-trade-bot/xmr_monitor')

from trading_assistant_dashboard import fetch_historical_klines, WATCH_SYMBOLS, STRATEGY_PRESETS, BACKTEST_DB, SKIP_COINS
from backtest_engine import run_backtest
from datetime import datetime

YEARS = [int(sys.argv[1])] if len(sys.argv) > 1 else [2020, 2021, 2022]
STRATEGIES = ['v1', 'v2', 'v3', 'v4.1']
INITIAL_CAPITAL = 2000

# 排除已知有问题的币种
skip = set(SKIP_COINS)

conn = sqlite3.connect(BACKTEST_DB)
c = conn.cursor()

# 查已有数据避免重复
c.execute("SELECT symbol, year, strategy_version FROM backtest_runs WHERE year IN ({})".format(
    ','.join(str(y) for y in YEARS)))
existing = set((r[0], r[1], r[2]) for r in c.fetchall())
print(f"已有 {len(existing)} 条记录，将跳过")

total = 0
success = 0
errors = 0
no_data = 0

symbols = [s for s in WATCH_SYMBOLS if s not in skip]
total_expected = len(symbols) * len(STRATEGIES) * len(YEARS)
print(f"计划运行: {len(symbols)}币种 × {len(STRATEGIES)}策略 × {len(YEARS)}年 = {total_expected} 次")
print(f"年份: {YEARS}")
print("=" * 60)

for year in YEARS:
    yr_success = 0
    yr_skip = 0
    yr_nodata = 0
    yr_start = time.time()

    for si, sym in enumerate(symbols):
        for strategy in STRATEGIES:
            total += 1

            if (sym, year, strategy) in existing:
                yr_skip += 1
                continue

            try:
                candles = fetch_historical_klines(sym, year)
                if not candles or len(candles) < 100:
                    yr_nodata += 1
                    no_data += 1
                    continue

                preset = STRATEGY_PRESETS[strategy]
                config = {
                    'initial_capital': INITIAL_CAPITAL,
                    'fee_rate': 0.0005,
                    'max_positions': 3,
                    'max_same_direction': 2
                }
                config.update(preset['config'])

                result = run_backtest(candles, config)
                s = result['summary']

                c.execute('''INSERT INTO backtest_runs
                    (symbol, year, initial_capital, final_capital, total_pnl,
                     win_rate, total_trades, win_trades, loss_trades,
                     max_drawdown, profit_factor, avg_win, avg_loss,
                     best_trade, worst_trade, bankrupt, strategy_version, run_time, note)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                    (sym, year, s['initial_capital'], s['final_capital'], s['total_pnl'],
                     s['win_rate'], s['total_trades'], s['win_trades'], s['loss_trades'],
                     s['max_drawdown'], s['profit_factor'], s['avg_win'], s['avg_loss'],
                     s['best_trade'], s['worst_trade'], 1 if s['bankrupt'] else 0,
                     strategy, datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                     f'batch_{year}'))
                run_id = c.lastrowid

                for t in result['trades']:
                    c.execute('''INSERT INTO backtest_trades
                        (run_id, trade_id, direction, entry_price, exit_price,
                         amount, leverage, pnl, roi, fee, funding_fee,
                         reason, entry_time, exit_time, score, stop_moves)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                        (run_id, t['trade_id'], t['direction'], t['entry_price'], t['exit_price'],
                         t['amount'], t['leverage'], t['pnl'], t['roi'], t['fee'], t['funding_fee'],
                         t['reason'], t['entry_time'], t['exit_time'], t['score'], t['stop_moves']))

                conn.commit()
                success += 1
                yr_success += 1

            except Exception as e:
                errors += 1
                print(f"  ERROR: {sym}/{strategy}/{year}: {e}")

        # 每10个币种打印进度
        if (si + 1) % 10 == 0:
            elapsed = time.time() - yr_start
            print(f"  {year}: {si+1}/{len(symbols)} 币种完成 | 成功={yr_success} 无数据={yr_nodata} 跳过={yr_skip} | {elapsed:.0f}s")

    elapsed = time.time() - yr_start
    print(f"✅ {year}年完成: 成功={yr_success} 无数据={yr_nodata} 跳过={yr_skip} | 耗时 {elapsed:.0f}s")
    print()

conn.close()
print("=" * 60)
print(f"全部完成: 总计={total} 成功={success} 无数据={no_data} 错误={errors}")
