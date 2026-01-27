#!/usr/bin/env python3
import ccxt
import json
from datetime import datetime

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
    symbol = 'XMR/USDT'
    
    print("=" * 60)
    print("准备平仓 XMR/USDT 持仓")
    print("=" * 60)
    
    # 先获取当前持仓
    positions = exchange.fetch_positions([symbol])
    xmr_position = None
    
    for pos in positions:
        if pos['symbol'] == 'XMR/USDT:USDT' and float(pos.get('contracts', 0) or 0) > 0:
            xmr_position = pos
            break
    
    if not xmr_position:
        print("❌ 未找到XMR持仓")
        exit(1)
    
    contracts = float(xmr_position.get('contracts', 0))
    side = xmr_position.get('side')
    entry_price = float(xmr_position.get('entryPrice', 0))
    unrealized_pnl = float(xmr_position.get('unrealizedPnl', 0))
    
    print(f"\n当前持仓:")
    print(f"  数量: {contracts} XMR")
    print(f"  方向: {side}")
    print(f"  开仓价: ${entry_price:.4f}")
    print(f"  未实现盈亏: ${unrealized_pnl:.2f}")
    
    # 获取当前市场价
    ticker = exchange.fetch_ticker(symbol)
    current_price = ticker['last']
    print(f"  当前价: ${current_price:.4f}")
    
    print("\n⚠️  即将执行市价平仓...")
    print("=" * 60)
    
    # 执行市价平仓（做多持仓需要卖出平仓）
    if side == 'long':
        order = exchange.create_market_sell_order(
            symbol=symbol,
            amount=contracts,
            params={'reduceOnly': True}  # 只减仓，不开新仓
        )
    else:
        order = exchange.create_market_buy_order(
            symbol=symbol,
            amount=contracts,
            params={'reduceOnly': True}
        )
    
    print("\n✅ 平仓成功！")
    print("=" * 60)
    print(f"订单ID: {order['id']}")
    print(f"成交价: ${order.get('average', current_price):.4f}")
    print(f"成交量: {order['filled']} XMR")
    print(f"最终盈亏: ${unrealized_pnl:.2f}")
    
    # 获取更新后的余额
    balance = exchange.fetch_balance({'type': 'future'})
    total = balance.get('total', {})
    usdt_balance = total.get('USDT', 0)
    
    print(f"\n账户余额: ${usdt_balance:.2f} USDT")
    print("=" * 60)
    
    # 确认持仓已清空
    positions_after = exchange.fetch_positions([symbol])
    has_position = False
    for pos in positions_after:
        if pos['symbol'] == 'XMR/USDT:USDT' and float(pos.get('contracts', 0) or 0) > 0:
            has_position = True
            break
    
    if not has_position:
        print("\n✅ 确认：XMR持仓已完全清空")
    else:
        print("\n⚠️  警告：可能还有部分持仓未清空")
    
except Exception as e:
    print(f"\n❌ 平仓失败: {e}")
    import traceback
    traceback.print_exc()
