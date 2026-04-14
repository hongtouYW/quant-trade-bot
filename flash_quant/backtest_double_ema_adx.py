#!/usr/bin/env python3
"""
DoubleEMA + ADX 震荡过滤 回测
路 A: 加 ADX >= 25 过滤, 震荡市不开仓
2025 全年, 30 主流币, 1H
"""
import sys, json, time
from datetime import datetime, timezone, timedelta
from collections import defaultdict

sys.path.insert(0, '.')

INITIAL_CAPITAL = 10000
LEVERAGE = 10
MARGIN_PER_TRADE = 300
TAKER_FEE = 0.0005
SLIPPAGE = 0.0003
MAX_POSITIONS = 5
STOPLOSS = -0.20
EMA_FAST = 9
EMA_SLOW = 21
EMA_TREND = 200
ADX_MIN = 25  # 新增: ADX >= 25 才开仓

SYMBOLS = [
    'BTC/USDT','ETH/USDT','SOL/USDT','BNB/USDT','XRP/USDT',
    'DOGE/USDT','ADA/USDT','AVAX/USDT','LINK/USDT','DOT/USDT',
    'NEAR/USDT','APT/USDT','ATOM/USDT','SUI/USDT','TRX/USDT',
    'LTC/USDT','BCH/USDT','ETC/USDT','UNI/USDT','AAVE/USDT',
    'FIL/USDT','INJ/USDT','ARB/USDT','OP/USDT','MKR/USDT',
    'ICP/USDT','HBAR/USDT','FTM/USDT','THETA/USDT','VET/USDT',
]


class IncrementalEMA:
    def __init__(self, period):
        self.period = period
        self.k = 2 / (period + 1)
        self.value = None
        self.count = 0
        self._sum = 0
    def update(self, price):
        self.count += 1
        if self.count <= self.period:
            self._sum += price
            if self.count == self.period:
                self.value = self._sum / self.period
        else:
            self.value = price * self.k + self.value * (1 - self.k)
        return self.value


class IncrementalADX:
    """增量 ADX 计算"""
    def __init__(self, period=14):
        self.period = period
        self.prev_high = None
        self.prev_low = None
        self.prev_close = None
        self.tr_sum = 0
        self.plus_dm_sum = 0
        self.minus_dm_sum = 0
        self.atr = None
        self.plus_di_smooth = None
        self.minus_di_smooth = None
        self.adx_val = None
        self.dx_sum = 0
        self.count = 0
        self.dx_count = 0

    def update(self, high, low, close):
        if self.prev_high is None:
            self.prev_high = high
            self.prev_low = low
            self.prev_close = close
            return None

        self.count += 1
        tr = max(high - low, abs(high - self.prev_close), abs(low - self.prev_close))
        up = high - self.prev_high
        down = self.prev_low - low
        plus_dm = up if up > down and up > 0 else 0
        minus_dm = down if down > up and down > 0 else 0

        self.prev_high = high
        self.prev_low = low
        self.prev_close = close

        if self.count <= self.period:
            self.tr_sum += tr
            self.plus_dm_sum += plus_dm
            self.minus_dm_sum += minus_dm
            if self.count == self.period:
                self.atr = self.tr_sum
                self.plus_di_smooth = self.plus_dm_sum
                self.minus_di_smooth = self.minus_dm_sum
            return None

        # Wilder smoothing
        self.atr = self.atr - self.atr / self.period + tr
        self.plus_di_smooth = self.plus_di_smooth - self.plus_di_smooth / self.period + plus_dm
        self.minus_di_smooth = self.minus_di_smooth - self.minus_di_smooth / self.period + minus_dm

        if self.atr == 0:
            return None

        plus_di = 100 * self.plus_di_smooth / self.atr
        minus_di = 100 * self.minus_di_smooth / self.atr
        di_sum = plus_di + minus_di

        if di_sum == 0:
            return None

        dx = 100 * abs(plus_di - minus_di) / di_sum
        self.dx_count += 1

        if self.dx_count <= self.period:
            self.dx_sum += dx
            if self.dx_count == self.period:
                self.adx_val = self.dx_sum / self.period
            return self.adx_val

        self.adx_val = (self.adx_val * (self.period - 1) + dx) / self.period
        return self.adx_val


def fetch_data(exchange, symbols, start, end):
    print("Fetching %d coins 1H..." % len(symbols))
    data = {}
    for sym in symbols:
        try:
            print("  %s..." % sym, end=" ", flush=True)
            klines = []
            since = int(start.timestamp() * 1000)
            end_ms = int(end.timestamp() * 1000)
            while since < end_ms:
                batch = exchange.fetch_ohlcv(sym, '1h', since=since, limit=1000)
                if not batch: break
                klines.extend([b for b in batch if b[0] < end_ms])
                since = batch[-1][0] + 1
                if len(batch) < 1000: break
                time.sleep(0.05)
            data[sym] = klines
            print("OK %d" % len(klines))
        except Exception as e:
            print("SKIP %s" % e)
    return data


def backtest(data):
    print("\nDoubleEMA + ADX>=%d Filter (2025, 30 coins)" % ADX_MIN)

    cap = INITIAL_CAPITAL
    trades = []
    positions = {}
    curve = []
    signals = 0
    filtered_adx = 0
    peak = INITIAL_CAPITAL
    max_dd = 0

    for sym, klines in data.items():
        ema_f = IncrementalEMA(EMA_FAST)
        ema_s = IncrementalEMA(EMA_SLOW)
        ema_t = IncrementalEMA(EMA_TREND)
        adx_calc = IncrementalADX(14)
        prev_f = None
        prev_s = None

        for k in klines:
            ts, o, h, l, c, vol = k
            ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d')

            f = ema_f.update(c)
            s = ema_s.update(c)
            t = ema_t.update(c)
            adx_val = adx_calc.update(h, l, c)

            if ema_t.count < EMA_TREND or prev_f is None:
                prev_f = f; prev_s = s
                continue

            # 持仓检查
            if sym in positions:
                pos = positions[sym]
                pnl_pct = (c - pos['entry']) / pos['entry']

                if pnl_pct <= STOPLOSS / LEVERAGE:
                    ep = c * (1 - SLIPPAGE)
                    pnl = pnl_pct * pos['margin'] * LEVERAGE - pos['fee'] - pos['margin']*LEVERAGE*TAKER_FEE
                    cap += pnl
                    trades.append({'symbol':sym,'direction':'long','tier':'ema_adx',
                        'entry':round(pos['entry'],6),'exit':round(ep,6),
                        'pnl':round(pnl,2),'reason':'stop_loss',
                        'hold_hours':round((ts-pos['ts'])/3600000,1),'date':ds})
                    del positions[sym]
                    prev_f = f; prev_s = s; continue

                if (prev_f >= prev_s and f < s) or c < t:
                    ep = c * (1 - SLIPPAGE)
                    pp = (ep - pos['entry']) / pos['entry']
                    pnl = pp * pos['margin'] * LEVERAGE - pos['fee'] - pos['margin']*LEVERAGE*TAKER_FEE
                    cap += pnl
                    trades.append({'symbol':sym,'direction':'long','tier':'ema_adx',
                        'entry':round(pos['entry'],6),'exit':round(ep,6),
                        'pnl':round(pnl,2),'reason':'ema_cross_sell',
                        'hold_hours':round((ts-pos['ts'])/3600000,1),'date':ds})
                    del positions[sym]

            elif sym not in positions:
                if len(positions) >= MAX_POSITIONS:
                    prev_f = f; prev_s = s; continue
                margin = min(MARGIN_PER_TRADE, cap * 0.15)
                if margin < 50:
                    prev_f = f; prev_s = s; continue

                # 金叉 + 趋势
                if prev_f <= prev_s and f > s and l > t:
                    # ADX 过滤: 震荡市不开
                    if adx_val is not None and adx_val < ADX_MIN:
                        filtered_adx += 1
                        prev_f = f; prev_s = s; continue

                    signals += 1
                    entry = c * (1 + SLIPPAGE)
                    fee = margin * LEVERAGE * TAKER_FEE
                    positions[sym] = {'entry':entry,'margin':margin,'fee':fee,'ts':ts}

            prev_f = f; prev_s = s

            if not curve or curve[-1]['date'] != ds:
                unr = sum((c - p['entry'])/p['entry']*p['margin']*LEVERAGE
                          for s2,p in positions.items() if s2==sym)
                bal = cap + unr
                curve.append({'date':ds,'balance':round(bal,2)})
                if bal > peak: peak = bal
                dd = (peak-bal)/peak if peak>0 else 0
                if dd > max_dd: max_dd = dd

    for sym, pos in positions.items():
        if data.get(sym):
            c = data[sym][-1][4]
            pp = (c-pos['entry'])/pos['entry']
            pnl = pp*pos['margin']*LEVERAGE - pos['fee'] - pos['margin']*LEVERAGE*TAKER_FEE
            cap += pnl
            trades.append({'symbol':sym,'direction':'long','tier':'ema_adx',
                'entry':round(pos['entry'],6),'exit':round(c,6),
                'pnl':round(pnl,2),'reason':'year_end','hold_hours':0,'date':'end'})

    return cap, trades, curve, signals, filtered_adx, max_dd


def report(cap, trades, curve, signals, filtered_adx, max_dd):
    wins = [t for t in trades if t['pnl']>0]
    losses = [t for t in trades if t['pnl']<=0]
    pnl = sum(t['pnl'] for t in trades)
    aw = sum(t['pnl'] for t in wins)/len(wins) if wins else 0
    al = abs(sum(t['pnl'] for t in losses)/len(losses)) if losses else 1
    pf = round(aw/al,2) if al else 0
    be = round(al/(aw+al)*100,1) if (aw+al) else 50

    reasons = defaultdict(int)
    for t in trades: reasons[t['reason']] += 1

    print("\n" + "="*60)
    print("DoubleEMA + ADX>=%d — 2025 Full Year" % ADX_MIN)
    print("="*60)
    print("  %d U -> %.2f U  PnL: %+.2f (%+.1f%%)" % (INITIAL_CAPITAL, cap, pnl, pnl/INITIAL_CAPITAL*100))
    print("  Max DD: %.1f%%  Trades: %d  Signals: %d  ADX filtered: %d" % (max_dd*100, len(trades), signals, filtered_adx))
    if trades:
        print("  WR: %.1f%% (%dW/%dL)  PF: %.2f  BreakEven: %.1f%%" % (
            len(wins)/len(trades)*100, len(wins), len(losses), pf, be))
        print("  Avg Win: +%.2f  Avg Loss: -%.2f  Avg Hold: %.0fh" % (
            aw, al, sum(t.get('hold_hours',0) for t in trades)/len(trades)))
    print("  Reasons: %s" % dict(reasons))

    monthly = defaultdict(lambda:{'t':0,'p':0,'w':0})
    for t in trades:
        m = t['date'][:7]
        if m=='end': continue
        monthly[m]['t']+=1; monthly[m]['p']+=t['pnl']
        if t['pnl']>0: monthly[m]['w']+=1
    print("\n  Monthly:")
    for m in sorted(monthly):
        d=monthly[m]
        print("  %s: %3d  WR%5.1f%%  PnL%+9.2f" % (m, d['t'], d['w']/d['t']*100 if d['t'] else 0, d['p']))

    # 和无 ADX 版本对比
    print("\n  vs No-ADX version:")
    print("  No-ADX:  1147 trades, 23.7%% WR, -96.5%%")
    print("  +ADX%d:  %d trades, %.1f%% WR, %+.1f%%" % (ADX_MIN, len(trades),
        len(wins)/len(trades)*100 if trades else 0, pnl/INITIAL_CAPITAL*100))

    coins = defaultdict(lambda:{'t':0,'p':0,'w':0})
    for t in trades:
        coins[t['symbol']]['t']+=1; coins[t['symbol']]['p']+=t['pnl']
        if t['pnl']>0: coins[t['symbol']]['w']+=1
    print("\n  Top coins:")
    for c,d in sorted(coins.items(), key=lambda x:-x[1]['p'])[:10]:
        print("    %-12s %3d  WR%5.1f%%  PnL%+9.2f" % (c, d['t'], d['w']/d['t']*100 if d['t'] else 0, d['p']))

    result = {
        'period':'2025-01 to 2025-12',
        'strategy':'DoubleEMA + ADX>=%d (1H, 10x)' % ADX_MIN,
        'initial_capital':INITIAL_CAPITAL,'final_capital':round(cap,2),
        'total_pnl':round(pnl,2),'total_trades':len(trades),
        'wins':len(wins),'losses':len(losses),
        'win_rate':round(len(wins)/len(trades)*100,1) if trades else 0,
        'profit_factor':pf,'breakeven_winrate':be,
        'max_drawdown':round(max_dd*100,1),
        'equity_curve':curve,'trades':trades[-50:],
    }
    with open('backtest_result.json','w') as f:
        json.dump(result,f,indent=2,default=str)
    print("\nSaved!")


if __name__=="__main__":
    import ccxt
    ex=ccxt.binance({'options':{'defaultType':'future'}})
    start=datetime(2025,1,1,tzinfo=timezone.utc)
    end=datetime(2026,1,1,tzinfo=timezone.utc)
    data=fetch_data(ex,SYMBOLS,start,end)
    cap,trades,curve,signals,filtered_adx,max_dd=backtest(data)
    report(cap,trades,curve,signals,filtered_adx,max_dd)
