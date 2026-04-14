#!/usr/bin/env python3
"""
Flash Quant — 回测 V2: 100 币 × 纯 Tier 1
方案 A: 删掉 Tier 2, 扩大监控范围, 只留严格爆发信号
"""
import sys, json, time
from datetime import datetime, timezone, timedelta
from collections import defaultdict

sys.path.insert(0, '.')
from data.indicators import ema, adx

INITIAL_CAPITAL = 10000
LEVERAGE = 20
STOP_LOSS_PRICE_PCT = 0.005
TAKER_FEE = 0.0005
SLIPPAGE = 0.0005
MAX_HOLD_HOURS = 4
MAX_POSITIONS = 5
MARGIN_PER_TRADE = 300
WICK_BODY_RATIO_MIN = 0.55

# Tier 1 only: 严格阈值
VOLUME_RATIO_MIN = 5.0
PRICE_CHANGE_MIN = 0.02

# 扩大到 100 币
SYMBOLS = [
    'BTC/USDT','ETH/USDT','SOL/USDT','BNB/USDT','XRP/USDT',
    'DOGE/USDT','ADA/USDT','AVAX/USDT','LINK/USDT','DOT/USDT',
    'NEAR/USDT','APT/USDT','ATOM/USDT','SUI/USDT','TRX/USDT',
    'ARB/USDT','OP/USDT','FET/USDT','WLD/USDT','INJ/USDT',
    'RUNE/USDT','FIL/USDT','STX/USDT','IMX/USDT','MKR/USDT',
    'LTC/USDT','BCH/USDT','ETC/USDT','AAVE/USDT','GRT/USDT',
    'SNX/USDT','TIA/USDT','SEI/USDT','JUP/USDT','ENA/USDT',
    'TON/USDT','ORDI/USDT','ONDO/USDT','PYTH/USDT','RNDR/USDT',
    'BLUR/USDT','CELO/USDT','CFX/USDT','WOO/USDT','ALGO/USDT',
    'SAND/USDT','MANA/USDT','AXS/USDT','CRV/USDT','COMP/USDT',
    'UNI/USDT','SUSHI/USDT','YFI/USDT','BAL/USDT','ZRX/USDT',
    'ENJ/USDT','CHZ/USDT','GALA/USDT','MASK/USDT','LDO/USDT',
    'RPL/USDT','SSV/USDT','GMX/USDT','DYDX/USDT','PENDLE/USDT',
    'JTO/USDT','WIF/USDT','BONK/USDT','MEW/USDT','POPCAT/USDT',
    'NEIRO/USDT','1000PEPE/USDT','1000SHIB/USDT','FLOKI/USDT',
    'THETA/USDT','VET/USDT','HBAR/USDT','ICP/USDT','FTM/USDT',
    'KAVA/USDT','ROSE/USDT','ZIL/USDT','ONE/USDT','FLOW/USDT',
    'MINA/USDT','AGIX/USDT','OCEAN/USDT','AR/USDT','KAS/USDT',
    'TAO/USDT','BOME/USDT','TURBO/USDT','PEOPLE/USDT','ACE/USDT',
    'XAI/USDT','PIXEL/USDT','STRK/USDT','W/USDT','ETHFI/USDT',
]


def fetch_data(exchange, symbols, start):
    print(f"\n📡 拉取 {len(symbols)} 币历史数据...")
    data = {}
    for sym in symbols:
        try:
            print(f"  {sym}...", end=" ", flush=True)
            klines_5m = []
            since = int(start.timestamp() * 1000)
            while True:
                batch = exchange.fetch_ohlcv(sym, '5m', since=since, limit=1000)
                if not batch: break
                klines_5m.extend(batch)
                since = batch[-1][0] + 1
                if len(batch) < 1000: break
                time.sleep(0.05)

            klines_1h = []
            since = int(start.timestamp() * 1000)
            while True:
                batch = exchange.fetch_ohlcv(sym, '1h', since=since, limit=1000)
                if not batch: break
                klines_1h.extend(batch)
                since = batch[-1][0] + 1
                if len(batch) < 1000: break
                time.sleep(0.05)

            data[sym] = {'5m': klines_5m, '1h': klines_1h}
            print(f"✅ {len(klines_5m)}")
        except Exception as e:
            print(f"❌ {e}")
    return data


def get_trend(klines_1h, idx):
    if idx < 55: return 'unknown'
    closes = [k[4] for k in klines_1h[idx-55:idx]]
    e20 = ema(closes, 20)
    e50 = ema(closes, 50)
    if not e20 or not e50: return 'unknown'
    diff = (e20[-1] - e50[-1]) / e50[-1]
    recent = klines_1h[idx-3:idx]
    up = sum(1 for k in recent if k[4] > k[1])
    if diff > 0.001 and up >= 2: return 'bullish'
    elif diff < -0.001 and (3-up) >= 2: return 'bearish'
    return 'sideways'


def get_adx_val(klines, idx, p=14):
    if idx < p*3: return 0
    s = max(0, idx-p*3)
    h = [k[2] for k in klines[s:idx]]
    l = [k[3] for k in klines[s:idx]]
    c = [k[4] for k in klines[s:idx]]
    v = adx(h, l, c, p)
    return v[-1] if v else 0


def wick_ok(o, h, l, c):
    body = abs(c-o)
    total = body + (h-max(o,c)) + (min(o,c)-l)
    return total > 0 and body/total >= WICK_BODY_RATIO_MIN


def find_1h(klines_1h, ts):
    for i in range(len(klines_1h)-1, -1, -1):
        if klines_1h[i][0] <= ts: return i
    return 0


def backtest(data):
    print(f"\n🔄 回测: 纯 Tier 1 × {len(data)} 币")
    print(f"   量比≥{VOLUME_RATIO_MIN}x + 涨跌≥{PRICE_CHANGE_MIN*100}% + 大趋势+ADX+Wick")

    capital = INITIAL_CAPITAL
    trades = []
    positions = []
    curve = []
    sigs = 0
    filtered = 0

    for sym, d in data.items():
        k5 = d['5m']
        k1h = d['1h']
        if len(k5) < 25 or len(k1h) < 55: continue

        for i in range(25, len(k5)):
            ts,o,h,l,c,vol = k5[i]
            ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d')

            # 检查持仓
            new_pos = []
            for p in positions:
                if p['sym'] != sym:
                    new_pos.append(p); continue
                hit_sl = (p['dir']=='long' and l<=p['sl']) or (p['dir']=='short' and h>=p['sl'])
                hit_to = (ts-p['ts']) > MAX_HOLD_HOURS*3600000
                if hit_sl or hit_to:
                    if hit_sl:
                        ep = p['sl']*(1-SLIPPAGE if p['dir']=='long' else 1+SLIPPAGE)
                        reason='stop_loss'
                    else:
                        ep = c*(1-SLIPPAGE if p['dir']=='long' else 1+SLIPPAGE)
                        reason='timeout'
                    n = p['margin']*LEVERAGE
                    fee = n*TAKER_FEE
                    pnl = ((ep-p['entry'])/p['entry'] if p['dir']=='long' else (p['entry']-ep)/p['entry'])*n - p['fee']-fee
                    capital += pnl
                    trades.append({'symbol':sym,'direction':p['dir'],'tier':'tier1',
                        'entry':round(p['entry'],6),'exit':round(ep,6),'pnl':round(pnl,2),
                        'reason':reason,'hold_hours':round((ts-p['ts'])/3600000,1),'date':ds})
                else:
                    new_pos.append(p)
            positions = new_pos

            if not curve or curve[-1]['date']!=ds:
                curve.append({'date':ds,'balance':round(capital,2)})

            if len(positions)>=MAX_POSITIONS: continue
            if any(p['sym']==sym for p in positions): continue
            margin = min(MARGIN_PER_TRADE, capital*0.15)
            if margin < 50: continue

            # 信号
            prev = [k5[j][5] for j in range(i-20,i)]
            avg = sum(prev)/len(prev) if prev else 0
            vr = vol/avg if avg>0 else 0
            pc = (c-o)/o if o>0 else 0

            if vr < VOLUME_RATIO_MIN or abs(pc) < PRICE_CHANGE_MIN: continue

            direction = 'long' if pc>0 else 'short'
            sigs += 1

            if not wick_ok(o,h,l,c): filtered+=1; continue
            if get_adx_val(k5,i) < 20: filtered+=1; continue

            hi = find_1h(k1h, ts)
            trend = get_trend(k1h, hi)
            if trend=='sideways': filtered+=1; continue
            if trend=='bullish' and direction=='short': filtered+=1; continue
            if trend=='bearish' and direction=='long': filtered+=1; continue

            entry = c*(1+SLIPPAGE if direction=='long' else 1-SLIPPAGE)
            sl = entry*(1-STOP_LOSS_PRICE_PCT if direction=='long' else 1+STOP_LOSS_PRICE_PCT)
            fee = margin*LEVERAGE*TAKER_FEE

            positions.append({'sym':sym,'dir':direction,'entry':entry,'sl':sl,
                'margin':margin,'fee':fee,'ts':ts})

    # 强平
    for p in positions:
        last = data[p['sym']]['5m'][-1]
        ep = last[4]
        n = p['margin']*LEVERAGE; fee = n*TAKER_FEE
        pnl = ((ep-p['entry'])/p['entry'] if p['dir']=='long' else (p['entry']-ep)/p['entry'])*n - p['fee']-fee
        capital += pnl
        trades.append({'symbol':p['sym'],'direction':p['dir'],'tier':'tier1',
            'entry':round(p['entry'],6),'exit':round(ep,6),'pnl':round(pnl,2),
            'reason':'end','hold_hours':0,'date':'end'})

    return capital, trades, curve, sigs, filtered


def report(capital, trades, curve, sigs, filtered):
    wins = [t for t in trades if t['pnl']>0]
    losses = [t for t in trades if t['pnl']<=0]
    pnl = sum(t['pnl'] for t in trades)
    aw = sum(t['pnl'] for t in wins)/len(wins) if wins else 0
    al = abs(sum(t['pnl'] for t in losses)/len(losses)) if losses else 1
    pf = round(aw/al,2) if al>0 else 0
    be = round(al/(aw+al)*100,1) if (aw+al)>0 else 50

    print(f"\n{'='*60}")
    print(f"📊 V2 回测: 纯 Tier 1 × {len(SYMBOLS)} 币")
    print(f"{'='*60}")
    print(f"  初始: {INITIAL_CAPITAL:,.0f} U → 最终: {capital:,.2f} U")
    print(f"  盈亏: {pnl:+,.2f} U ({pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  交易: {len(trades)} (信号{sigs}, 过滤{filtered})")
    print(f"  胜率: {len(wins)/len(trades)*100:.1f}% ({len(wins)}胜 {len(losses)}负)" if trades else "")
    print(f"  盈亏比: {pf}  (盈亏平衡需 {be}% 胜率)")
    print(f"  平均盈: +{aw:.2f}  平均亏: -{al:.2f}")

    # 月度
    monthly = defaultdict(lambda:{'t':0,'p':0,'w':0})
    for t in trades:
        m = t['date'][:7]
        if m=='end': continue
        monthly[m]['t']+=1; monthly[m]['p']+=t['pnl']
        if t['pnl']>0: monthly[m]['w']+=1
    print(f"\n  📅 月度:")
    for m in sorted(monthly):
        d=monthly[m]
        print(f"  {m}: {d['t']:3d}笔 胜率{d['w']/d['t']*100:5.1f}% PnL{d['p']:+8.2f}")

    # 保存
    result = {
        'period':'2026-01 to now','strategy':'V2: Tier1 Only × 100 coins',
        'initial_capital':INITIAL_CAPITAL,'final_capital':round(capital,2),
        'total_pnl':round(pnl,2),'total_trades':len(trades),
        'wins':len(wins),'losses':len(losses),
        'win_rate':round(len(wins)/len(trades)*100,1) if trades else 0,
        'profit_factor':pf,'breakeven_winrate':be,
        'equity_curve':curve,'trades':trades[-50:],
    }
    with open('backtest_result.json','w') as f:
        json.dump(result,f,indent=2,default=str)
    print(f"\n💾 保存到 backtest_result.json")


if __name__=="__main__":
    import ccxt
    ex = ccxt.binance({'options':{'defaultType':'future'}})
    start = datetime(2026,1,1,tzinfo=timezone.utc)
    data = fetch_data(ex, SYMBOLS, start)
    cap, trades, curve, sigs, filt = backtest(data)
    report(cap, trades, curve, sigs, filt)
