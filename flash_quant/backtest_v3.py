#!/usr/bin/env python3
"""
Flash Quant — 回测 V3: 主流 Top 30 × 纯 Tier 1
排除山寨, 只留流动性最好的主流币
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
VOLUME_RATIO_MIN = 5.0
PRICE_CHANGE_MIN = 0.02

# 主流 Top 30 (按 24h 成交量排名, 排除山寨垃圾)
SYMBOLS = [
    # Tier A Major (最高流动性)
    'BTC/USDT', 'ETH/USDT',
    # Tier A Large (高流动性)
    'SOL/USDT', 'BNB/USDT', 'XRP/USDT', 'DOGE/USDT', 'ADA/USDT', 'AVAX/USDT',
    # Tier B (中等流动性, 但都是正经项目)
    'LINK/USDT', 'DOT/USDT', 'NEAR/USDT', 'APT/USDT', 'ATOM/USDT', 'SUI/USDT',
    'TRX/USDT', 'LTC/USDT', 'BCH/USDT', 'ETC/USDT', 'UNI/USDT', 'AAVE/USDT',
    'FIL/USDT', 'INJ/USDT', 'ARB/USDT', 'OP/USDT', 'MKR/USDT', 'ICP/USDT',
    'HBAR/USDT', 'FTM/USDT', 'THETA/USDT', 'VET/USDT',
]


def fetch_data(exchange, symbols, start):
    print(f"\n📡 拉取 {len(symbols)} 主流币数据...")
    data = {}
    for sym in symbols:
        try:
            print(f"  {sym}...", end=" ", flush=True)
            k5 = []; since = int(start.timestamp()*1000)
            while True:
                b = exchange.fetch_ohlcv(sym,'5m',since=since,limit=1000)
                if not b: break
                k5.extend(b); since=b[-1][0]+1
                if len(b)<1000: break
                time.sleep(0.05)
            k1h = []; since = int(start.timestamp()*1000)
            while True:
                b = exchange.fetch_ohlcv(sym,'1h',since=since,limit=1000)
                if not b: break
                k1h.extend(b); since=b[-1][0]+1
                if len(b)<1000: break
                time.sleep(0.05)
            data[sym] = {'5m':k5,'1h':k1h}
            print(f"✅ {len(k5)}")
        except Exception as e:
            print(f"❌ {e}")
    return data


def get_trend(k1h, idx):
    if idx<55: return 'unknown'
    c = [k[4] for k in k1h[idx-55:idx]]
    e20=ema(c,20); e50=ema(c,50)
    if not e20 or not e50: return 'unknown'
    d = (e20[-1]-e50[-1])/e50[-1]
    r = k1h[idx-3:idx]; up=sum(1 for k in r if k[4]>k[1])
    if d>0.001 and up>=2: return 'bullish'
    elif d<-0.001 and (3-up)>=2: return 'bearish'
    return 'sideways'


def get_adx_val(k, idx, p=14):
    if idx<p*3: return 0
    s=max(0,idx-p*3)
    v=adx([x[2] for x in k[s:idx]],[x[3] for x in k[s:idx]],[x[4] for x in k[s:idx]],p)
    return v[-1] if v else 0


def wick_ok(o,h,l,c):
    body=abs(c-o); t=body+(h-max(o,c))+(min(o,c)-l)
    return t>0 and body/t>=WICK_BODY_RATIO_MIN


def find_1h(k1h,ts):
    for i in range(len(k1h)-1,-1,-1):
        if k1h[i][0]<=ts: return i
    return 0


def backtest(data):
    print(f"\n🔄 V3 回测: 主流 Top 30 × 纯 Tier 1")
    cap=INITIAL_CAPITAL; trades=[]; pos=[]; curve=[]
    sigs=0; filt=0

    for sym,d in data.items():
        k5=d['5m']; k1h=d['1h']
        if len(k5)<25 or len(k1h)<55: continue

        for i in range(25,len(k5)):
            ts,o,h,l,c,vol = k5[i]
            ds = datetime.fromtimestamp(ts/1000).strftime('%Y-%m-%d')

            # 持仓检查
            np=[]
            for p in pos:
                if p['sym']!=sym: np.append(p); continue
                hit_sl=(p['dir']=='long' and l<=p['sl']) or (p['dir']=='short' and h>=p['sl'])
                hit_to=(ts-p['ts'])>MAX_HOLD_HOURS*3600000
                if hit_sl or hit_to:
                    ep=p['sl']*(1-SLIPPAGE if p['dir']=='long' else 1+SLIPPAGE) if hit_sl else c*(1-SLIPPAGE if p['dir']=='long' else 1+SLIPPAGE)
                    n=p['m']*LEVERAGE; fee=n*TAKER_FEE
                    pnl=((ep-p['e'])/p['e'] if p['dir']=='long' else (p['e']-ep)/p['e'])*n-p['f']-fee
                    cap+=pnl
                    trades.append({'symbol':sym,'direction':p['dir'],'tier':'tier1',
                        'entry':round(p['e'],6),'exit':round(ep,6),'pnl':round(pnl,2),
                        'reason':'stop_loss' if hit_sl else 'timeout',
                        'hold_hours':round((ts-p['ts'])/3600000,1),'date':ds})
                else: np.append(p)
            pos=np

            if not curve or curve[-1]['date']!=ds:
                curve.append({'date':ds,'balance':round(cap,2)})

            if len(pos)>=MAX_POSITIONS: continue
            if any(p['sym']==sym for p in pos): continue
            margin=min(MARGIN_PER_TRADE,cap*0.15)
            if margin<50: continue

            # 信号
            prev=[k5[j][5] for j in range(i-20,i)]
            avg=sum(prev)/len(prev) if prev else 0
            vr=vol/avg if avg>0 else 0
            pc=(c-o)/o if o>0 else 0

            if vr<VOLUME_RATIO_MIN or abs(pc)<PRICE_CHANGE_MIN: continue
            direction='long' if pc>0 else 'short'
            sigs+=1

            if not wick_ok(o,h,l,c): filt+=1; continue
            if get_adx_val(k5,i)<20: filt+=1; continue
            hi=find_1h(k1h,ts)
            trend=get_trend(k1h,hi)
            if trend=='sideways': filt+=1; continue
            if trend=='bullish' and direction=='short': filt+=1; continue
            if trend=='bearish' and direction=='long': filt+=1; continue

            e=c*(1+SLIPPAGE if direction=='long' else 1-SLIPPAGE)
            sl=e*(1-STOP_LOSS_PRICE_PCT if direction=='long' else 1+STOP_LOSS_PRICE_PCT)
            fee=margin*LEVERAGE*TAKER_FEE
            pos.append({'sym':sym,'dir':direction,'e':e,'sl':sl,'m':margin,'f':fee,'ts':ts})

    # 强平
    for p in pos:
        last=data[p['sym']]['5m'][-1]; ep=last[4]
        n=p['m']*LEVERAGE; fee=n*TAKER_FEE
        pnl=((ep-p['e'])/p['e'] if p['dir']=='long' else (p['e']-ep)/p['e'])*n-p['f']-fee
        cap+=pnl
        trades.append({'symbol':p['sym'],'direction':p['dir'],'tier':'tier1',
            'entry':round(p['e'],6),'exit':round(ep,6),'pnl':round(pnl,2),
            'reason':'end','hold_hours':0,'date':'end'})

    return cap,trades,curve,sigs,filt


def report(cap,trades,curve,sigs,filt):
    wins=[t for t in trades if t['pnl']>0]
    losses=[t for t in trades if t['pnl']<=0]
    pnl=sum(t['pnl'] for t in trades)
    aw=sum(t['pnl'] for t in wins)/len(wins) if wins else 0
    al=abs(sum(t['pnl'] for t in losses)/len(losses)) if losses else 1
    pf=round(aw/al,2) if al>0 else 0
    be=round(al/(aw+al)*100,1) if (aw+al)>0 else 50

    print(f"\n{'='*60}")
    print(f"📊 V3: 主流 Top 30 × 纯 Tier 1")
    print(f"{'='*60}")
    print(f"  初始: {INITIAL_CAPITAL:,.0f} U → 最终: {cap:,.2f} U")
    print(f"  盈亏: {pnl:+,.2f} U ({pnl/INITIAL_CAPITAL*100:+.1f}%)")
    print(f"  交易: {len(trades)} (信号{sigs}, 过滤{filt})")
    if trades:
        print(f"  胜率: {len(wins)/len(trades)*100:.1f}% ({len(wins)}胜 {len(losses)}负)")
    print(f"  盈亏比: {pf}  (盈亏平衡需 {be}% 胜率)")
    print(f"  平均盈: +{aw:.2f}  平均亏: -{al:.2f}")

    # 按方向
    lo=[t for t in trades if t['direction']=='long']
    sh=[t for t in trades if t['direction']=='short']
    if lo: print(f"  做多: {len(lo)}笔, 胜率{sum(1 for t in lo if t['pnl']>0)/len(lo)*100:.0f}%")
    if sh: print(f"  做空: {len(sh)}笔, 胜率{sum(1 for t in sh if t['pnl']>0)/len(sh)*100:.0f}%")

    # 月度
    monthly=defaultdict(lambda:{'t':0,'p':0,'w':0})
    for t in trades:
        m=t['date'][:7]
        if m=='end': continue
        monthly[m]['t']+=1; monthly[m]['p']+=t['pnl']
        if t['pnl']>0: monthly[m]['w']+=1
    print(f"\n  📅 月度:")
    for m in sorted(monthly):
        d=monthly[m]
        print(f"  {m}: {d['t']:3d}笔 胜率{d['w']/d['t']*100:5.1f}% PnL{d['p']:+8.2f}")

    result = {
        'period':'2026-01 to now','strategy':'V3: Tier1 Only × Top 30 Mainstream',
        'initial_capital':INITIAL_CAPITAL,'final_capital':round(cap,2),
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
    ex=ccxt.binance({'options':{'defaultType':'future'}})
    start=datetime(2026,1,1,tzinfo=timezone.utc)
    data=fetch_data(ex,SYMBOLS,start)
    cap,trades,curve,sigs,filt=backtest(data)
    report(cap,trades,curve,sigs,filt)
