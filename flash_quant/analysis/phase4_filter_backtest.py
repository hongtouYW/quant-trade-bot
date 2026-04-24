#!/usr/bin/env python3
"""
Phase 4: 用 Phase 3 发现的特征做过滤器, 回测 105 笔
看过滤掉的坏信号能省多少亏损, 误杀多少好信号
"""
import json


def load():
    with open('/Users/hongtou/newproject/flash_quant/analysis/trades_with_features.json') as f:
        return json.load(f)['trades']


def evaluate(trades, filter_rules, name):
    """给定过滤规则, 计算过滤后的总盈亏"""
    kept = []
    filtered = []

    for t in trades:
        f = t.get('features', {}) or {}
        lev = t.get('leverage', 20)

        # 检查是否应该过滤
        reasons = []
        if filter_rules.get('max_leverage') and lev > filter_rules['max_leverage']:
            reasons.append(f"lev>{filter_rules['max_leverage']}")
        if filter_rules.get('max_change_1h') is not None:
            c1 = f.get('change_1h_pct')
            if c1 is not None and c1 > filter_rules['max_change_1h']:
                reasons.append(f"change_1h>{filter_rules['max_change_1h']}")
        if filter_rules.get('min_atr_pct') is not None:
            atr = f.get('atr_pct')
            if atr is not None and atr < filter_rules['min_atr_pct']:
                reasons.append(f"atr<{filter_rules['min_atr_pct']}")
        if filter_rules.get('max_btc_24h') is not None:
            b = f.get('btc_change_24h_pct')
            if b is not None and b > filter_rules['max_btc_24h']:
                reasons.append(f"btc_24h>{filter_rules['max_btc_24h']}")
        if filter_rules.get('max_rsi') is not None:
            r = f.get('rsi_14')
            if r is not None and r > filter_rules['max_rsi']:
                reasons.append(f"rsi>{filter_rules['max_rsi']}")
        if filter_rules.get('max_volume_ratio') is not None:
            v = f.get('volume_ratio')
            if v is not None and v > filter_rules['max_volume_ratio']:
                reasons.append(f"vol_r>{filter_rules['max_volume_ratio']}")

        if reasons:
            filtered.append({**t, 'filter_reasons': reasons})
        else:
            kept.append(t)

    # 统计
    total_pnl = sum(t['pnl'] for t in kept)
    tp_kept = sum(1 for t in kept if t['close_reason'] == 'tp')
    sl_kept = sum(1 for t in kept if t['close_reason'] in ('sl', 'hard_sl_-50%'))
    win_rate = tp_kept / len(kept) * 100 if kept else 0

    saved_loss = -sum(t['pnl'] for t in filtered if t['pnl'] <= 0)
    lost_profit = sum(t['pnl'] for t in filtered if t['pnl'] > 0)
    net_saved = saved_loss - lost_profit

    print(f"\n--- {name} ---")
    print(f"过滤规则: {filter_rules}")
    print(f"保留交易: {len(kept)} 笔 ({tp_kept} TP / {sl_kept} SL, 胜率 {win_rate:.1f}%)")
    print(f"过滤掉:   {len(filtered)} 笔")
    print(f"  - 省掉坏信号: 节约 {saved_loss:.0f} U")
    print(f"  - 误杀好信号: 损失 {lost_profit:.0f} U")
    print(f"  - 净节约:     {net_saved:+.0f} U")
    print(f"过滤后总盈亏: {total_pnl:+.0f} U")

    return {
        'name': name,
        'kept': len(kept),
        'filtered': len(filtered),
        'tp_kept': tp_kept,
        'sl_kept': sl_kept,
        'win_rate': win_rate,
        'total_pnl': total_pnl,
        'saved_loss': saved_loss,
        'lost_profit': lost_profit,
        'net_saved': net_saved,
    }


def main():
    trades = load()

    print("=" * 70)
    print("Phase 4 过滤器回测 (105 笔交易)")
    print("=" * 70)
    base_pnl = sum(t['pnl'] for t in trades)
    print(f"原始总盈亏: {base_pnl:+.0f} U")

    results = []

    # 测试不同过滤规则
    results.append(evaluate(trades, {
        'max_leverage': 25
    }, "规则 1: 只砍杠杆 >25x"))

    results.append(evaluate(trades, {
        'max_change_1h': 3.0
    }, "规则 2: 只砍 1H 涨幅 >3%"))

    results.append(evaluate(trades, {
        'max_rsi': 73
    }, "规则 3: 只砍 RSI >73"))

    results.append(evaluate(trades, {
        'max_btc_24h': 1.5
    }, "规则 4: 只砍 BTC 24H 涨 >1.5%"))

    results.append(evaluate(trades, {
        'max_leverage': 25,
        'max_change_1h': 3.0,
    }, "规则 5: 杠杆 + 1H 涨幅"))

    results.append(evaluate(trades, {
        'max_leverage': 25,
        'max_change_1h': 3.0,
        'max_rsi': 75,
    }, "规则 6: 杠杆 + 1H 涨幅 + RSI"))

    results.append(evaluate(trades, {
        'max_leverage': 25,
        'max_change_1h': 3.0,
        'max_rsi': 75,
        'max_btc_24h': 2.0,
    }, "规则 7: 四重过滤"))

    results.append(evaluate(trades, {
        'max_leverage': 25,
        'max_change_1h': 2.0,
        'max_rsi': 72,
        'max_btc_24h': 1.5,
        'max_volume_ratio': 6.0,
    }, "规则 8: 严格五重过滤"))

    # 总结最佳
    print("\n" + "=" * 70)
    print("对比 (按过滤后盈亏排序):")
    print("=" * 70)
    sorted_res = sorted(results, key=lambda x: -x['total_pnl'])
    print(f"\n{'规则':<35} {'保留':<8} {'胜率':<8} {'盈亏':<12}")
    print(f"{'原始 (不过滤)':<35} {len(trades):<8} {sum(1 for t in trades if t['close_reason']=='tp')/len(trades)*100:<8.1f} {base_pnl:+.0f} U")
    for r in sorted_res:
        print(f"{r['name']:<35} {r['kept']:<8} {r['win_rate']:<8.1f} {r['total_pnl']:+.0f} U")

    # 保存
    with open('/Users/hongtou/newproject/flash_quant/analysis/phase4_results.json', 'w') as f:
        json.dump({'base_pnl': base_pnl, 'results': results}, f, indent=2, default=str)


if __name__ == '__main__':
    main()
