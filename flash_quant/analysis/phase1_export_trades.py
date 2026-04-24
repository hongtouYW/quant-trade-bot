#!/usr/bin/env python3
"""
Phase 1: 导出 112 笔实盘交易, 分 tp/sl 两组
供 Phase 2 提取市场特征
"""
import sqlite3
import json
from datetime import datetime
from collections import defaultdict

DB = '/Users/hongtou/newproject/flash_quant/analysis/luke_trades.db'
OUT = '/Users/hongtou/newproject/flash_quant/analysis/trades_raw.json'


def main():
    conn = sqlite3.connect(DB)
    cur = conn.cursor()

    cur.execute("""
        SELECT id, signal_id, opened_at, closed_at, symbol, direction,
               entry_price, exit_price, leverage, amount, position_size,
               notional, pnl, pnl_pct, fees, close_reason, status
        FROM trades WHERE status='closed'
        ORDER BY id
    """)

    rows = cur.fetchall()
    cols = [d[0] for d in cur.description]
    trades = [dict(zip(cols, r)) for r in rows]

    # 分组
    tp = [t for t in trades if t['close_reason'] == 'tp']
    sl = [t for t in trades if t['close_reason'] in ('sl', 'hard_sl_-50%')]

    print(f"Total closed: {len(trades)}")
    print(f"  TP (win): {len(tp)}")
    print(f"  SL (loss): {len(sl)}")

    # 统计
    print(f"\n=== TP 组 ===")
    print(f"  PnL: +{sum(t['pnl'] for t in tp):.0f}")
    print(f"  Avg PnL: +{sum(t['pnl'] for t in tp)/len(tp):.1f}")
    print(f"  Directions: LONG {sum(1 for t in tp if t['direction']=='LONG')}, SHORT {sum(1 for t in tp if t['direction']=='SHORT')}")
    print(f"  Avg leverage: {sum(t['leverage'] for t in tp)/len(tp):.1f}x")

    print(f"\n=== SL 组 ===")
    print(f"  PnL: {sum(t['pnl'] for t in sl):.0f}")
    print(f"  Avg PnL: {sum(t['pnl'] for t in sl)/len(sl):.1f}")
    print(f"  Directions: LONG {sum(1 for t in sl if t['direction']=='LONG')}, SHORT {sum(1 for t in sl if t['direction']=='SHORT')}")
    print(f"  Avg leverage: {sum(t['leverage'] for t in sl)/len(sl):.1f}x")

    # 币种分布
    tp_coins = defaultdict(int)
    sl_coins = defaultdict(int)
    for t in tp: tp_coins[t['symbol']] += 1
    for t in sl: sl_coins[t['symbol']] += 1

    # 只看两组都有的币
    common = set(tp_coins.keys()) & set(sl_coins.keys())
    print(f"\n=== 两组都出现过的币 (可直接比较) ===")
    for c in sorted(common, key=lambda x: -(tp_coins[x] + sl_coins[x])):
        print(f"  {c:12} TP:{tp_coins[c]:3} SL:{sl_coins[c]:3}")

    # 保存
    with open(OUT, 'w') as f:
        json.dump({
            'total': len(trades),
            'tp': tp,
            'sl': sl,
        }, f, default=str, indent=2)
    print(f"\nSaved to {OUT}")


if __name__ == '__main__':
    main()
