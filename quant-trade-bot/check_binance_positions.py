#!/usr/bin/env python3
import ccxt
import json

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化Binance
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {
        'defaultType': 'future'
    }
})

try:
    # 获取合约持仓
    positions = exchange.fetch_positions()
    
    # 过滤出有持仓的
    open_positions = [p for p in positions if float(p.get('contracts', 0)) > 0]
    
    print("=" * 60)
    print("Binance合约持仓")
    print("=" * 60)
    
    if not open_positions:
        print("\n当前无持仓")
    else:
        for pos in open_positions:
            symbol = pos['symbol']
            contracts = float(pos.get('contracts', 0) or 0)
            side = pos.get('side', 'N/A')
            entry_price = float(pos.get('entryPrice', 0) or 0)
            mark_price = float(pos.get('markPrice', 0) or 0)
            leverage = float(pos.get('leverage') or 0) if pos.get('leverage') is not None else 0
            unrealized_pnl = float(pos.get('unrealizedPnl', 0) or 0)
            liquidation_price = float(pos.get('liquidationPrice', 0) or 0)
            margin = float(pos.get('initialMargin', 0) or 0)
            
            print(f"\n交易对: {symbol}")
            print(f"  方向: {side}")
            print(f"  数量: {contracts}")
            print(f"  杠杆: {leverage}x")
            print(f"  开仓价: ${entry_price:.4f}")
            print(f"  标记价: ${mark_price:.4f}")
            print(f"  保证金: ${margin:.2f}")
            print(f"  未实现盈亏: ${unrealized_pnl:.2f}")
            
            if unrealized_pnl != 0:
                pnl_pct = (unrealized_pnl / margin * 100) if margin > 0 else 0
                print(f"  盈亏率: {pnl_pct:.2f}%")
            
            if liquidation_price > 0:
                print(f"  强平价: ${liquidation_price:.4f}")
                if side == 'long':
                    gap = ((mark_price - liquidation_price) / mark_price) * 100
                    print(f"  距离强平: {gap:.2f}%")
                else:
                    gap = ((liquidation_price - mark_price) / mark_price) * 100
                    print(f"  距离强平: {gap:.2f}%")
    
    print("\n" + "=" * 60)
    
    # 获取账户余额
    balance = exchange.fetch_balance({'type': 'future'})
    total = balance.get('total', {})
    usdt_balance = total.get('USDT', 0)
    print(f"\nUSDT余额: ${usdt_balance:.2f}")
    
except Exception as e:
    print(f"获取持仓失败: {e}")
    import traceback
    traceback.print_exc()
