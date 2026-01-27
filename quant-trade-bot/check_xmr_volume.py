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
    
    # 获取最新K线数据（15分钟）
    ohlcv = exchange.fetch_ohlcv(symbol, '15m', limit=10)
    
    print("=" * 60)
    print("XMR/USDT 15分钟K线交易量")
    print("=" * 60)
    
    for candle in ohlcv[-6:]:
        timestamp = datetime.fromtimestamp(candle[0]/1000).strftime('%H:%M')
        open_price = candle[1]
        high = candle[2]
        low = candle[3]
        close = candle[4]
        volume = candle[5]
        
        change = ((close - open_price) / open_price) * 100
        arrow = "📈" if change > 0 else "📉" if change < 0 else "➡️"
        
        print(f"{timestamp}: ${close:.2f} {arrow} 成交量: {volume:.1f} XMR ({change:+.2f}%)")
    
    print("\n" + "=" * 60)
    print("订单簿深度分析（买卖盘力量）")
    print("=" * 60)
    
    # 获取订单簿
    orderbook = exchange.fetch_order_book(symbol, limit=20)
    
    # 分析买单和卖单
    bids = orderbook['bids'][:10]  # 前10档买单
    asks = orderbook['asks'][:10]  # 前10档卖单
    
    total_bid_volume = sum([bid[1] for bid in bids])
    total_ask_volume = sum([ask[1] for ask in asks])
    
    print(f"\n买盘前10档总量: {total_bid_volume:.2f} XMR")
    print(f"卖盘前10档总量: {total_ask_volume:.2f} XMR")
    print(f"买卖比: {total_bid_volume/total_ask_volume:.2f}")
    
    if total_bid_volume > total_ask_volume * 1.2:
        print("✅ 买盘力量较强，有支撑")
    elif total_bid_volume < total_ask_volume * 0.8:
        print("⚠️ 卖盘压力较大，买盘较弱")
    else:
        print("➡️ 买卖力量均衡")
    
    print("\n📊 买单前5档:")
    for i, bid in enumerate(bids[:5], 1):
        print(f"  {i}. ${bid[0]:.2f} - {bid[1]:.2f} XMR")
    
    print("\n📊 卖单前5档:")
    for i, ask in enumerate(asks[:5], 1):
        print(f"  {i}. ${ask[0]:.2f} - {ask[1]:.2f} XMR")
    
    # 获取最近成交
    trades = exchange.fetch_trades(symbol, limit=50)
    
    # 分析最近成交是买入还是卖出
    recent_buys = [t for t in trades if t['side'] == 'buy']
    recent_sells = [t for t in trades if t['side'] == 'sell']
    
    buy_volume = sum([t['amount'] for t in recent_buys])
    sell_volume = sum([t['amount'] for t in recent_sells])
    
    print("\n" + "=" * 60)
    print("最近50笔成交分析")
    print("=" * 60)
    print(f"买入成交: {len(recent_buys)}笔, {buy_volume:.2f} XMR")
    print(f"卖出成交: {len(recent_sells)}笔, {sell_volume:.2f} XMR")
    print(f"买入占比: {len(recent_buys)/len(trades)*100:.1f}%")
    
    if len(recent_buys) > len(recent_sells) * 1.2:
        print("✅ 主动买入较多，买盘活跃")
    elif len(recent_buys) < len(recent_sells) * 0.8:
        print("⚠️ 主动卖出较多，抛压较重")
    else:
        print("➡️ 买卖成交均衡")
    
    # 当前价格
    ticker = exchange.fetch_ticker(symbol)
    current_price = ticker['last']
    
    print("\n" + "=" * 60)
    print(f"当前价格: ${current_price:.2f}")
    print(f"你的开仓价: $480.43")
    print(f"你的强平价: $422.77")
    print(f"距离强平还有: {((current_price - 422.77) / current_price * 100):.2f}%")
    print("=" * 60)
    
except Exception as e:
    print(f"获取数据失败: {e}")
    import traceback
    traceback.print_exc()
