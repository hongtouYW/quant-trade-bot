#!/usr/bin/env python3
"""
Phase 3: 统计对比 tp 组 vs sl 组 的特征差异
找显著区别, 评估是否可做过滤器
"""
import json
import statistics
from collections import defaultdict


def mean(data):
    return statistics.mean(data) if data else None


def median(data):
    return statistics.median(data) if data else None


def analyze():
    with open('/Users/hongtou/newproject/flash_quant/analysis/trades_with_features.json') as f:
        d = json.load(f)

    trades = d['trades']
    tp = [t for t in trades if t['close_reason'] == 'tp']
    sl = [t for t in trades if t['close_reason'] in ('sl', 'hard_sl_-50%')]

    print("=" * 70)
    print("tp vs sl 特征对比 (TP=%d, SL=%d)" % (len(tp), len(sl)))
    print("=" * 70)

    # 提取所有特征名
    all_features = set()
    for t in trades:
        if t.get('features'):
            all_features.update(t['features'].keys())

    print(f"\n{'Feature':<28} {'TP(avg)':<12} {'SL(avg)':<12} {'Diff':<10} {'Signal?'}")
    print("-" * 70)

    signals = []
    for feat in sorted(all_features):
        tp_vals = [t['features'].get(feat) for t in tp if t['features'].get(feat) is not None]
        sl_vals = [t['features'].get(feat) for t in sl if t['features'].get(feat) is not None]

        if len(tp_vals) < 5 or len(sl_vals) < 5:
            continue

        # 布尔特征
        if isinstance(tp_vals[0], bool):
            tp_rate = sum(tp_vals) / len(tp_vals)
            sl_rate = sum(sl_vals) / len(sl_vals)
            diff = abs(tp_rate - sl_rate)
            marker = "*** STRONG" if diff > 0.20 else ("** MID" if diff > 0.10 else "")
            print(f"{feat:<28} {tp_rate:<12.3f} {sl_rate:<12.3f} {diff:<10.3f} {marker}")
            if diff > 0.15:
                signals.append((feat, 'bool', tp_rate, sl_rate, diff))
            continue

        tp_avg = mean(tp_vals)
        sl_avg = mean(sl_vals)
        if tp_avg is None or sl_avg is None:
            continue

        # 相对差异
        pooled_std = statistics.pstdev(tp_vals + sl_vals)
        if pooled_std > 0:
            effect_size = abs(tp_avg - sl_avg) / pooled_std
        else:
            effect_size = 0

        marker = ""
        if effect_size > 0.5:
            marker = "*** STRONG"
        elif effect_size > 0.3:
            marker = "** MID"
        elif effect_size > 0.15:
            marker = "* WEAK"

        print(f"{feat:<28} {tp_avg:<12.3f} {sl_avg:<12.3f} {tp_avg-sl_avg:<+10.3f} {marker}")

        if effect_size > 0.3:
            signals.append((feat, 'numeric', tp_avg, sl_avg, effect_size))

    # 方向维度
    print(f"\n=== 方向特征 ===")
    tp_long = sum(1 for t in tp if t['direction'] == 'LONG')
    tp_short = sum(1 for t in tp if t['direction'] == 'SHORT')
    sl_long = sum(1 for t in sl if t['direction'] == 'LONG')
    sl_short = sum(1 for t in sl if t['direction'] == 'SHORT')
    print(f"TP: LONG {tp_long} ({tp_long/len(tp)*100:.0f}%), SHORT {tp_short} ({tp_short/len(tp)*100:.0f}%)")
    print(f"SL: LONG {sl_long} ({sl_long/len(sl)*100:.0f}%), SHORT {sl_short} ({sl_short/len(sl)*100:.0f}%)")

    # 杠杆
    print(f"\n=== 杠杆 ===")
    tp_lev = [t['leverage'] for t in tp]
    sl_lev = [t['leverage'] for t in sl]
    print(f"TP leverage: avg={mean(tp_lev):.1f}, median={median(tp_lev)}")
    print(f"SL leverage: avg={mean(sl_lev):.1f}, median={median(sl_lev)}")

    # 分段统计杠杆
    print(f"\n杠杆分段 (胜率):")
    for low, high in [(10, 20), (20, 25), (25, 30), (30, 40), (40, 100)]:
        tp_in = sum(1 for lev in tp_lev if low <= lev < high)
        sl_in = sum(1 for lev in sl_lev if low <= lev < high)
        total = tp_in + sl_in
        if total > 0:
            print(f"  {low}-{high}x: TP={tp_in}, SL={sl_in}, 胜率={tp_in/total*100:.0f}% ({total} 笔)")

    # 关键组合
    print(f"\n=== 💎 最强过滤器候选 ===")
    signals.sort(key=lambda x: -x[4])
    for s in signals[:10]:
        feat, typ, tp_val, sl_val, eff = s
        if typ == 'bool':
            print(f"  {feat}: TP率={tp_val:.2f}, SL率={sl_val:.2f} (diff {eff:.2f})")
        else:
            print(f"  {feat}: TP平均={tp_val:.2f}, SL平均={sl_val:.2f} (effect {eff:.2f})")

    # 评估
    print(f"\n=== 评估 ===")
    strong_signals = [s for s in signals if s[4] > 0.5]
    mid_signals = [s for s in signals if 0.3 < s[4] <= 0.5]

    print(f"强特征 (effect > 0.5): {len(strong_signals)} 个")
    print(f"中特征 (0.3-0.5):      {len(mid_signals)} 个")

    if len(strong_signals) >= 2:
        print("\n✅ 找到强特征! 值得做过滤器 → Phase 4 回测")
    elif len(strong_signals) + len(mid_signals) >= 3:
        print("\n🤔 有中等特征, 组合起来可能有效 → 值得试回测")
    else:
        print("\n❌ 没有明显特征 → 算法很难区分 tp/sl")
        print("   → 建议转向监控工具方案")


if __name__ == '__main__':
    analyze()
