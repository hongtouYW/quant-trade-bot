#!/usr/bin/env python3
"""
资金费率套利回测
Delta Neutral: 现货买入 + 永续做空 = 只赚费率
2026-01 到现在
"""
import sys, json, time
from datetime import datetime, timezone, timedelta
from collections import defaultdict

INITIAL_CAPITAL = 10000
TAKER_FEE = 0.0005       # 开仓手续费 0.05%
MIN_FUNDING_RATE = 0.0001 # 费率 > 0.01% 才开仓 (太低不值得)
REBALANCE_THRESHOLD = 0.05 # 价格偏离 5% 重新平衡

# 监控的币 (高流动性, 费率通常为正)
SYMBOLS = [
    'BTC/USDT', 'ETH/USDT', 'SOL/USDT', 'BNB/USDT', 'XRP/USDT',
    'DOGE/USDT', 'ADA/USDT', 'AVAX/USDT', 'LINK/USDT', 'SUI/USDT',
]


def fetch_funding_history(exchange, symbol, start):
    """拉取资金费率历史"""
    all_rates = []
    since = int(start.timestamp() * 1000)
    while True:
        try:
            rates = exchange.fetch_funding_rate_history(symbol, since=since, limit=1000)
            if not rates:
                break
            all_rates.extend(rates)
            since = rates[-1]['timestamp'] + 1
            if len(rates) < 1000:
                break
            time.sleep(0.1)
        except Exception as e:
            print("  Error: %s" % e)
            break
    return all_rates


def fetch_prices(exchange, symbol, start):
    """拉取 1H K线 (用于计算持仓盈亏)"""
    klines = []
    since = int(start.timestamp() * 1000)
    while True:
        batch = exchange.fetch_ohlcv(symbol, '1h', since=since, limit=1000)
        if not batch:
            break
        klines.extend(batch)
        since = batch[-1][0] + 1
        if len(batch) < 1000:
            break
        time.sleep(0.05)
    return klines


def backtest():
    import ccxt
    exchange = ccxt.binance({'options': {'defaultType': 'future'}})
    start = datetime(2026, 1, 1, tzinfo=timezone.utc)

    print("=" * 60)
    print("Funding Rate Arbitrage Backtest")
    print("Delta Neutral: Spot Long + Perp Short = Collect Funding")
    print("=" * 60)

    # 拉数据
    all_data = {}
    for sym in SYMBOLS:
        print("  %s..." % sym, end=" ", flush=True)
        try:
            rates = fetch_funding_history(exchange, sym, start)
            prices = fetch_prices(exchange, sym, start)
            all_data[sym] = {'rates': rates, 'prices': prices}
            print("OK %d rates, %d prices" % (len(rates), len(prices)))
        except Exception as e:
            print("SKIP %s" % e)

    print("\nRunning backtest...")

    # === 策略 1: 单币 BTC 套利 ===
    print("\n--- Strategy 1: BTC Only ---")
    btc_result = single_coin_arb(all_data.get('BTC/USDT', {}), 'BTC', INITIAL_CAPITAL)

    # === 策略 2: 多币轮动 (选费率最高的) ===
    print("\n--- Strategy 2: Multi-Coin Rotation ---")
    multi_result = multi_coin_arb(all_data, INITIAL_CAPITAL)

    # === 策略 3: 全部币同时持有 ===
    print("\n--- Strategy 3: All Coins Parallel ---")
    parallel_result = parallel_arb(all_data, INITIAL_CAPITAL)

    # 保存最佳结果
    best = max([btc_result, multi_result, parallel_result], key=lambda x: x['total_pnl'])
    best['strategy'] = 'Funding Rate Arbitrage (Best: %s)' % best['name']
    best['period'] = '2026-01 to now'
    best['initial_capital'] = INITIAL_CAPITAL

    with open('backtest_result.json', 'w') as f:
        json.dump(best, f, indent=2, default=str)
    print("\nSaved best result to backtest_result.json")


def single_coin_arb(data, name, capital):
    """单币套利"""
    rates = data.get('rates', [])
    if not rates:
        print("  No data")
        return {'name': name, 'total_pnl': 0, 'final_capital': capital,
                'total_trades': 0, 'wins': 0, 'losses': 0, 'win_rate': 0,
                'equity_curve': [], 'trades': []}

    # 每笔用一半资金 (现货 + 合约)
    position_size = capital * 0.5  # 合约侧
    total_funding = 0
    total_fees = 0
    collections = 0
    negative_count = 0
    curve = []
    daily = defaultdict(float)

    # 开仓手续费 (现货+合约各一次)
    open_fee = position_size * TAKER_FEE * 2
    total_fees += open_fee

    for r in rates:
        rate = r.get('fundingRate', 0)
        ts = r.get('timestamp', 0)
        ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d') if ts else ''

        if rate > 0:
            # 做空收费率
            funding_income = position_size * rate
            total_funding += funding_income
            collections += 1
            daily[ds] += funding_income
        else:
            # 费率为负, 做空要付钱
            funding_cost = position_size * abs(rate)
            total_funding -= funding_cost
            negative_count += 1
            daily[ds] -= funding_cost

    # 平仓手续费
    close_fee = position_size * TAKER_FEE * 2
    total_fees += close_fee

    net_pnl = total_funding - total_fees
    final = capital + net_pnl

    # 资金曲线
    running = capital
    for ds in sorted(daily):
        running += daily[ds]
        curve.append({'date': ds, 'balance': round(running, 2)})

    days = len(set(daily.keys()))
    annual_rate = (net_pnl / capital) / (days/365) * 100 if days > 0 else 0

    print("  %s: %d funding events" % (name, len(rates)))
    print("  Positive: %d (%.0f%%)  Negative: %d" % (
        collections, collections/len(rates)*100 if rates else 0, negative_count))
    print("  Gross funding: %+.2f U" % total_funding)
    print("  Total fees: %.2f U" % total_fees)
    print("  Net PnL: %+.2f U (%+.1f%%)" % (net_pnl, net_pnl/capital*100))
    print("  Annualized: %.1f%%" % annual_rate)
    print("  Final: %.2f U" % final)

    return {
        'name': '%s Only' % name,
        'final_capital': round(final, 2),
        'total_pnl': round(net_pnl, 2),
        'total_trades': len(rates),
        'wins': collections,
        'losses': negative_count,
        'win_rate': round(collections/len(rates)*100, 1) if rates else 0,
        'profit_factor': round(total_funding / total_fees, 2) if total_fees else 0,
        'breakeven_winrate': 0,
        'equity_curve': curve,
        'trades': [],
        'annualized': round(annual_rate, 1),
    }


def multi_coin_arb(all_data, capital):
    """多币轮动: 每 8h 选费率最高的币"""
    position_size = capital * 0.5
    total_funding = 0
    total_fees = 0
    switches = 0
    current_coin = None
    curve = []
    daily = defaultdict(float)

    # 合并所有费率按时间排序
    all_rates = []
    for sym, data in all_data.items():
        for r in data.get('rates', []):
            r['symbol'] = sym
            all_rates.append(r)
    all_rates.sort(key=lambda x: x.get('timestamp', 0))

    # 按 8h 分组
    groups = defaultdict(list)
    for r in all_rates:
        ts = r.get('timestamp', 0)
        bucket = ts // (8 * 3600 * 1000)
        groups[bucket].append(r)

    for bucket in sorted(groups):
        rates_in_period = groups[bucket]
        # 找费率最高的币
        best = max(rates_in_period, key=lambda x: x.get('fundingRate', 0))
        rate = best.get('fundingRate', 0)
        sym = best.get('symbol', '')
        ts = best.get('timestamp', 0)
        ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d') if ts else ''

        # 换币需要手续费
        if sym != current_coin and current_coin is not None:
            switch_fee = position_size * TAKER_FEE * 4  # 关旧+开新 (现货+合约各2)
            total_fees += switch_fee
            switches += 1
        elif current_coin is None:
            total_fees += position_size * TAKER_FEE * 2
        current_coin = sym

        if rate > MIN_FUNDING_RATE:
            income = position_size * rate
            total_funding += income
            daily[ds] += income
        elif rate < 0:
            cost = position_size * abs(rate)
            total_funding -= cost
            daily[ds] -= cost

    # 平仓费
    total_fees += position_size * TAKER_FEE * 2

    net_pnl = total_funding - total_fees
    final = capital + net_pnl
    days = len(set(daily.keys()))
    annual = (net_pnl / capital) / (days/365) * 100 if days > 0 else 0

    running = capital
    for ds in sorted(daily):
        running += daily[ds]
        curve.append({'date': ds, 'balance': round(running, 2)})

    print("  Multi-coin rotation: %d switches" % switches)
    print("  Gross funding: %+.2f U" % total_funding)
    print("  Switch fees: %.2f U (of %.2f total)" % (switches * position_size * TAKER_FEE * 4, total_fees))
    print("  Net PnL: %+.2f U (%+.1f%%)" % (net_pnl, net_pnl/capital*100))
    print("  Annualized: %.1f%%" % annual)

    return {
        'name': 'Multi-Coin Rotation',
        'final_capital': round(final, 2),
        'total_pnl': round(net_pnl, 2),
        'total_trades': len(groups),
        'wins': sum(1 for g in groups.values() if max(r.get('fundingRate', 0) for r in g) > 0),
        'losses': sum(1 for g in groups.values() if max(r.get('fundingRate', 0) for r in g) <= 0),
        'win_rate': 0,
        'profit_factor': round(total_funding / total_fees, 2) if total_fees else 0,
        'breakeven_winrate': 0,
        'equity_curve': curve,
        'trades': [],
        'annualized': round(annual, 1),
    }


def parallel_arb(all_data, capital):
    """全部币同时持有, 各分一份资金"""
    n_coins = len(all_data)
    per_coin = capital / n_coins
    position_size = per_coin * 0.5  # 每币的合约侧

    total_funding = 0
    total_fees = 0
    curve_daily = defaultdict(float)

    # 每币开仓费
    total_fees += position_size * TAKER_FEE * 2 * n_coins

    for sym, data in all_data.items():
        for r in data.get('rates', []):
            rate = r.get('fundingRate', 0)
            ts = r.get('timestamp', 0)
            ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d') if ts else ''

            if rate > 0:
                total_funding += position_size * rate
                curve_daily[ds] += position_size * rate
            else:
                total_funding -= position_size * abs(rate)
                curve_daily[ds] -= position_size * abs(rate)

    # 平仓费
    total_fees += position_size * TAKER_FEE * 2 * n_coins

    net_pnl = total_funding - total_fees
    final = capital + net_pnl
    days = len(set(curve_daily.keys()))
    annual = (net_pnl / capital) / (days/365) * 100 if days > 0 else 0

    curve = []
    running = capital
    for ds in sorted(curve_daily):
        running += curve_daily[ds]
        curve.append({'date': ds, 'balance': round(running, 2)})

    print("  All %d coins parallel" % n_coins)
    print("  Per coin: %.0f U (%.0f spot + %.0f perp)" % (per_coin, per_coin*0.5, per_coin*0.5))
    print("  Gross funding: %+.2f U" % total_funding)
    print("  Total fees: %.2f U" % total_fees)
    print("  Net PnL: %+.2f U (%+.1f%%)" % (net_pnl, net_pnl/capital*100))
    print("  Annualized: %.1f%%" % annual)

    return {
        'name': 'All Coins Parallel',
        'final_capital': round(final, 2),
        'total_pnl': round(net_pnl, 2),
        'total_trades': 0,
        'wins': 0,
        'losses': 0,
        'win_rate': 0,
        'profit_factor': round(total_funding / total_fees, 2) if total_fees else 0,
        'breakeven_winrate': 0,
        'equity_curve': curve,
        'trades': [],
        'annualized': round(annual, 1),
    }


if __name__ == "__main__":
    backtest()
