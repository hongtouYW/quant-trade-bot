#!/usr/bin/env python3
"""
Flash Quant — 回测引擎
2026-01-01 到现在, 用当前策略 (Tier1 爆发 + Tier2 次级爆发 + 大趋势过滤 + ADX)

用法: python3 backtest.py
注意: 需要连 Binance API (服务器上跑)
"""
import sys
import json
import time
from datetime import datetime, timezone, timedelta
from collections import defaultdict

# 添加项目路径
sys.path.insert(0, '.')
from data.indicators import ema, rsi, macd, adx
from core.constants import (
    TIER1_VOLUME_RATIO_MIN, TIER1_PRICE_CHANGE_MIN,
    TIER2_VOLUME_RATIO_MIN, TIER2_PRICE_CHANGE_MIN,
    WICK_BODY_RATIO_MIN, FUNDING_RATE_MAX,
    MAX_CONCURRENT_POSITIONS, MAX_MARGIN_PER_TRADE,
)

# 回测参数
INITIAL_CAPITAL = 10000
LEVERAGE = 20
STOP_LOSS_ROI = -0.10       # -10%
STOP_LOSS_PRICE_PCT = 0.005  # 0.5%
TAKER_FEE = 0.0005          # 0.05%
SLIPPAGE = 0.0005           # 0.05%
MAX_HOLD_HOURS = 8          # Tier2
MAX_POSITIONS = 5
MARGIN_PER_TRADE = 300

# 回测币种 (主流)
SYMBOLS = [
    'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT',
    'DOGE/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'DOT/USDT',
    'NEAR/USDT', 'APT/USDT', 'ATOM/USDT', 'SUI/USDT',
    'ARB/USDT', 'OP/USDT', 'FET/USDT', 'WLD/USDT', 'INJ/USDT',
    'RUNE/USDT',
]


def fetch_all_data(exchange, symbols, start_date):
    """拉取所有历史数据"""
    print(f"\n📡 拉取历史数据 ({start_date} → 现在)...")
    all_data = {}

    for sym in symbols:
        try:
            print(f"  {sym}...", end=" ", flush=True)
            # 5min K线
            klines_5m = []
            since = int(start_date.timestamp() * 1000)
            while True:
                batch = exchange.fetch_ohlcv(sym, '5m', since=since, limit=1000)
                if not batch:
                    break
                klines_5m.extend(batch)
                since = batch[-1][0] + 1
                if len(batch) < 1000:
                    break
                time.sleep(0.1)

            # 1H K线 (大趋势)
            klines_1h = []
            since = int(start_date.timestamp() * 1000)
            while True:
                batch = exchange.fetch_ohlcv(sym, '1h', since=since, limit=1000)
                if not batch:
                    break
                klines_1h.extend(batch)
                since = batch[-1][0] + 1
                if len(batch) < 1000:
                    break
                time.sleep(0.1)

            all_data[sym] = {
                '5m': klines_5m,
                '1h': klines_1h,
            }
            print(f"✅ {len(klines_5m)} 5m + {len(klines_1h)} 1h")

        except Exception as e:
            print(f"❌ {e}")

    return all_data


def get_trend(klines_1h, idx):
    """判断 1H 大趋势"""
    if idx < 55:
        return 'unknown'

    closes = [k[4] for k in klines_1h[idx-55:idx]]
    ema20 = ema(closes, 20)
    ema50 = ema(closes, 50)

    if not ema20 or not ema50:
        return 'unknown'

    diff = (ema20[-1] - ema50[-1]) / ema50[-1]

    # 动量确认
    recent = klines_1h[idx-3:idx]
    up = sum(1 for k in recent if k[4] > k[1])

    if diff > 0.001 and up >= 2:
        return 'bullish'
    elif diff < -0.001 and (3 - up) >= 2:
        return 'bearish'
    return 'sideways'


def get_adx_value(klines_5m, idx, period=14):
    """计算 ADX"""
    if idx < period * 3:
        return 0
    start = max(0, idx - period * 3)
    highs = [k[2] for k in klines_5m[start:idx]]
    lows = [k[3] for k in klines_5m[start:idx]]
    closes = [k[4] for k in klines_5m[start:idx]]
    vals = adx(highs, lows, closes, period)
    return vals[-1] if vals else 0


def wick_check(o, h, l, c):
    """反插针检查"""
    body = abs(c - o)
    uw = h - max(o, c)
    lw = min(o, c) - l
    total = body + uw + lw
    if total == 0:
        return False
    return body / total >= WICK_BODY_RATIO_MIN


def find_1h_index(klines_1h, ts_ms):
    """找到 5m 时间戳对应的 1h K线索引"""
    for i in range(len(klines_1h) - 1, -1, -1):
        if klines_1h[i][0] <= ts_ms:
            return i
    return 0


def run_backtest(all_data):
    """执行回测"""
    print(f"\n🔄 开始回测...")
    print(f"   策略: Tier1 (量比≥{TIER1_VOLUME_RATIO_MIN}x, 涨跌≥{TIER1_PRICE_CHANGE_MIN*100}%)")
    print(f"         Tier2 (量比≥{TIER2_VOLUME_RATIO_MIN}x, 涨跌≥{TIER2_PRICE_CHANGE_MIN*100}%)")
    print(f"         + 大趋势过滤 + ADX≥20 + Wick过滤")
    print(f"   杠杆: {LEVERAGE}x, 止损: {STOP_LOSS_ROI*100}% ROI")
    print(f"   本金: {INITIAL_CAPITAL}U, 单笔: {MARGIN_PER_TRADE}U")

    capital = INITIAL_CAPITAL
    trades = []
    open_positions = []
    equity_curve = []
    daily_pnl = defaultdict(float)
    signals_total = 0
    signals_filtered = 0

    # 遍历每个币的 5m K线
    for sym, data in all_data.items():
        klines_5m = data['5m']
        klines_1h = data['1h']

        if len(klines_5m) < 25 or len(klines_1h) < 55:
            continue

        # 从第 25 根开始 (需要历史数据算量比)
        for i in range(25, len(klines_5m)):
            k = klines_5m[i]
            ts, o, h, l, c, vol = k[0], k[1], k[2], k[3], k[4], k[5]
            date_str = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d')

            # 检查现有持仓: 止损/超时
            new_open = []
            for pos in open_positions:
                if pos['symbol'] != sym:
                    new_open.append(pos)
                    continue

                # 止损检查
                hit_sl = False
                if pos['direction'] == 'long' and l <= pos['stop_loss']:
                    exit_p = pos['stop_loss'] * (1 - SLIPPAGE)
                    hit_sl = True
                elif pos['direction'] == 'short' and h >= pos['stop_loss']:
                    exit_p = pos['stop_loss'] * (1 + SLIPPAGE)
                    hit_sl = True

                # 超时检查
                hold_ms = ts - pos['open_ts']
                hit_timeout = hold_ms > MAX_HOLD_HOURS * 3600 * 1000

                if hit_sl or hit_timeout:
                    if hit_sl:
                        reason = 'stop_loss'
                    else:
                        exit_p = c * (1 - SLIPPAGE if pos['direction'] == 'long' else 1 + SLIPPAGE)
                        reason = 'timeout'

                    notional = pos['margin'] * LEVERAGE
                    fee = notional * TAKER_FEE
                    if pos['direction'] == 'long':
                        pnl = (exit_p - pos['entry']) / pos['entry'] * notional - pos['fee'] - fee
                    else:
                        pnl = (pos['entry'] - exit_p) / pos['entry'] * notional - pos['fee'] - fee

                    capital += pnl
                    daily_pnl[date_str] += pnl
                    trades.append({
                        'symbol': sym, 'direction': pos['direction'],
                        'tier': pos['tier'],
                        'entry': pos['entry'], 'exit': round(exit_p, 6),
                        'pnl': round(pnl, 2), 'reason': reason,
                        'hold_hours': round(hold_ms / 3600000, 1),
                        'date': date_str,
                    })
                else:
                    new_open.append(pos)

            open_positions = new_open

            # 记录资金曲线 (每天一个点)
            if not equity_curve or equity_curve[-1]['date'] != date_str:
                unrealized = 0
                for pos in open_positions:
                    if pos['symbol'] == sym:
                        if pos['direction'] == 'long':
                            unrealized += (c - pos['entry']) / pos['entry'] * pos['margin'] * LEVERAGE
                        else:
                            unrealized += (pos['entry'] - c) / pos['entry'] * pos['margin'] * LEVERAGE
                equity_curve.append({'date': date_str, 'balance': round(capital + unrealized, 2)})

            # 最大持仓检查
            if len(open_positions) >= MAX_POSITIONS:
                continue

            # 同币检查
            if any(p['symbol'] == sym for p in open_positions):
                continue

            # 保证金检查
            margin = min(MARGIN_PER_TRADE, capital * 0.15)
            if margin < 50:
                continue

            # === 信号检测 ===
            prev_vols = [klines_5m[j][5] for j in range(i-20, i)]
            avg_vol = sum(prev_vols) / len(prev_vols) if prev_vols else 0
            vol_ratio = vol / avg_vol if avg_vol > 0 else 0
            price_change = (c - o) / o if o > 0 else 0

            # Tier 1: 量比≥5 + 涨跌≥2%
            is_tier1 = vol_ratio >= TIER1_VOLUME_RATIO_MIN and abs(price_change) >= TIER1_PRICE_CHANGE_MIN
            # Tier 2: 量比≥3 + 涨跌≥1%
            is_tier2 = vol_ratio >= TIER2_VOLUME_RATIO_MIN and abs(price_change) >= TIER2_PRICE_CHANGE_MIN

            if not is_tier1 and not is_tier2:
                continue

            tier = 'tier1' if is_tier1 else 'tier2'
            direction = 'long' if price_change > 0 else 'short'
            signals_total += 1

            # Wick 过滤
            if not wick_check(o, h, l, c):
                signals_filtered += 1
                continue

            # ADX 过滤
            adx_val = get_adx_value(klines_5m, i)
            if adx_val < 20:
                signals_filtered += 1
                continue

            # 大趋势过滤
            h_idx = find_1h_index(klines_1h, ts)
            trend = get_trend(klines_1h, h_idx)
            if trend == 'sideways':
                signals_filtered += 1
                continue
            if trend == 'bullish' and direction == 'short':
                signals_filtered += 1
                continue
            if trend == 'bearish' and direction == 'long':
                signals_filtered += 1
                continue

            # 开仓!
            entry = c * (1 + SLIPPAGE if direction == 'long' else 1 - SLIPPAGE)
            notional = margin * LEVERAGE
            fee = notional * TAKER_FEE

            if direction == 'long':
                sl = entry * (1 - STOP_LOSS_PRICE_PCT)
            else:
                sl = entry * (1 + STOP_LOSS_PRICE_PCT)

            open_positions.append({
                'symbol': sym, 'direction': direction, 'tier': tier,
                'entry': entry, 'stop_loss': sl, 'margin': margin,
                'fee': fee, 'open_ts': ts,
            })

    # 强平剩余持仓
    for pos in open_positions:
        sym_data = all_data.get(pos['symbol'], {}).get('5m', [])
        if sym_data:
            last = sym_data[-1]
            exit_p = last[4]
            notional = pos['margin'] * LEVERAGE
            fee = notional * TAKER_FEE
            if pos['direction'] == 'long':
                pnl = (exit_p - pos['entry']) / pos['entry'] * notional - pos['fee'] - fee
            else:
                pnl = (pos['entry'] - exit_p) / pos['entry'] * notional - pos['fee'] - fee
            capital += pnl
            trades.append({
                'symbol': pos['symbol'], 'direction': pos['direction'],
                'tier': pos['tier'],
                'entry': pos['entry'], 'exit': exit_p,
                'pnl': round(pnl, 2), 'reason': 'backtest_end',
                'hold_hours': 0, 'date': 'end',
            })

    return capital, trades, equity_curve, signals_total, signals_filtered, daily_pnl


def print_report(capital, trades, equity_curve, signals_total, signals_filtered, daily_pnl):
    """打印回测报告"""
    wins = [t for t in trades if t['pnl'] > 0]
    losses = [t for t in trades if t['pnl'] <= 0]
    total_pnl = sum(t['pnl'] for t in trades)
    total_fee_est = len(trades) * MARGIN_PER_TRADE * LEVERAGE * TAKER_FEE * 2

    print(f"\n{'='*70}")
    print(f"📊 Flash Quant 回测报告")
    print(f"{'='*70}")
    print(f"  期间: 2026-01-01 → 现在")
    print(f"  币种: {len(SYMBOLS)} 个")
    print(f"  策略: Tier1+Tier2 爆发跟随 + 大趋势过滤 + ADX")
    print(f"  杠杆: {LEVERAGE}x, 止损: {STOP_LOSS_ROI*100}% ROI")
    print(f"")
    print(f"{'='*70}")
    print(f"  📈 资金")
    print(f"  初始:        {INITIAL_CAPITAL:,.2f} U")
    print(f"  最终:        {capital:,.2f} U")
    print(f"  盈亏:        {total_pnl:+,.2f} U ({total_pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  手续费(估):  {total_fee_est:,.2f} U")
    print(f"")
    print(f"{'='*70}")
    print(f"  📊 交易")
    print(f"  总信号:      {signals_total}")
    print(f"  被过滤:      {signals_filtered} ({signals_filtered/signals_total*100:.0f}%)" if signals_total else "  被过滤:      0")
    print(f"  开仓数:      {len(trades)}")
    print(f"  胜:          {len(wins)}")
    print(f"  负:          {len(losses)}")
    print(f"  胜率:        {len(wins)/len(trades)*100:.1f}%" if trades else "  胜率:        -")
    if wins:
        print(f"  平均盈利:    {sum(t['pnl'] for t in wins)/len(wins):+.2f} U")
    if losses:
        print(f"  平均亏损:    {sum(t['pnl'] for t in losses)/len(losses):+.2f} U")
    if wins and losses:
        avg_w = sum(t['pnl'] for t in wins) / len(wins)
        avg_l = abs(sum(t['pnl'] for t in losses) / len(losses))
        print(f"  盈亏比:      {avg_w/avg_l:.2f}" if avg_l > 0 else "  盈亏比:      -")

    # 最大回撤
    if equity_curve:
        peak = equity_curve[0]['balance']
        max_dd = 0
        for e in equity_curve:
            if e['balance'] > peak:
                peak = e['balance']
            dd = (peak - e['balance']) / peak
            if dd > max_dd:
                max_dd = dd
        print(f"  最大回撤:    {max_dd*100:.1f}%")

    # 按 Tier 分
    t1 = [t for t in trades if t['tier'] == 'tier1']
    t2 = [t for t in trades if t['tier'] == 'tier2']
    print(f"")
    print(f"  Tier 1:      {len(t1)} 笔, 胜率 {sum(1 for t in t1 if t['pnl']>0)/len(t1)*100:.0f}%, PnL {sum(t['pnl'] for t in t1):+.2f}" if t1 else "  Tier 1:      0 笔")
    print(f"  Tier 2:      {len(t2)} 笔, 胜率 {sum(1 for t in t2 if t['pnl']>0)/len(t2)*100:.0f}%, PnL {sum(t['pnl'] for t in t2):+.2f}" if t2 else "  Tier 2:      0 笔")

    # 按方向分
    longs = [t for t in trades if t['direction'] == 'long']
    shorts = [t for t in trades if t['direction'] == 'short']
    print(f"  做多:        {len(longs)} 笔, 胜率 {sum(1 for t in longs if t['pnl']>0)/len(longs)*100:.0f}%" if longs else "  做多:        0 笔")
    print(f"  做空:        {len(shorts)} 笔, 胜率 {sum(1 for t in shorts if t['pnl']>0)/len(shorts)*100:.0f}%" if shorts else "  做空:        0 笔")

    # 按月份
    print(f"")
    print(f"{'='*70}")
    print(f"  📅 月度表现")
    monthly = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0})
    for t in trades:
        m = t['date'][:7] if t['date'] != 'end' else 'end'
        monthly[m]['trades'] += 1
        monthly[m]['pnl'] += t['pnl']
        if t['pnl'] > 0:
            monthly[m]['wins'] += 1

    for m in sorted(monthly.keys()):
        d = monthly[m]
        wr = d['wins']/d['trades']*100 if d['trades'] else 0
        print(f"  {m}: {d['trades']:3d}笔  胜率{wr:5.1f}%  PnL {d['pnl']:+8.2f} U")

    # 最近 10 笔
    print(f"")
    print(f"{'='*70}")
    print(f"  📋 最近 10 笔交易")
    for t in trades[-10:]:
        icon = "✅" if t['pnl'] > 0 else "❌"
        print(f"  {icon} {t['date']} {t['symbol']:12s} {t['direction']:5s} {t['tier']:5s} PnL:{t['pnl']:+8.2f} ({t['reason']})")

    print(f"\n{'='*70}")

    # 保存结果
    result = {
        'period': '2026-01 to now',
        'initial_capital': INITIAL_CAPITAL,
        'final_capital': round(capital, 2),
        'total_pnl': round(total_pnl, 2),
        'total_trades': len(trades),
        'wins': len(wins),
        'losses': len(losses),
        'win_rate': round(len(wins)/len(trades)*100, 1) if trades else 0,
        'equity_curve': equity_curve[-30:],  # 最近30天
        'trades': trades[-50:],  # 最近50笔
    }
    with open('backtest_result.json', 'w') as f:
        json.dump(result, f, indent=2, default=str)
    print(f"\n💾 结果保存到 backtest_result.json")


if __name__ == "__main__":
    import ccxt
    exchange = ccxt.binance({'options': {'defaultType': 'future'}})

    start = datetime(2026, 1, 1, tzinfo=timezone.utc)
    all_data = fetch_all_data(exchange, SYMBOLS, start)

    capital, trades, curve, sig_total, sig_filtered, daily = run_backtest(all_data)
    print_report(capital, trades, curve, sig_total, sig_filtered, daily)
