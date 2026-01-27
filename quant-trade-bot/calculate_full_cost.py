#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""计算完整的持仓成本（包括资金费率）"""

import ccxt
import json
from datetime import datetime, timedelta

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化Binance
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

try:
    # ETH持仓信息
    eth_entry_time = "2026-01-23 14:35:47"  # 从API获取的
    eth_entry = 2948.62
    eth_current = 2889.33
    eth_quantity = 0.09158
    eth_leverage = 3
    eth_cost = 90.28
    
    # BTC持仓信息
    btc_entry_time = "2026-01-23 14:35:46"
    btc_entry = 89621.39
    btc_current = 87978.00
    btc_quantity = 0.00335
    btc_leverage = 3
    btc_cost = 100.35
    
    # 计算持仓天数
    entry_dt = datetime.strptime(eth_entry_time, "%Y-%m-%d %H:%M:%S")
    now_dt = datetime.now()
    days_held = (now_dt - entry_dt).total_seconds() / 86400
    hours_held = (now_dt - entry_dt).total_seconds() / 3600
    
    # 资金费率每8小时收取一次（00:00, 08:00, 16:00 UTC）
    # Binance合约资金费率一般在0.01%左右，但会波动
    # 假设平均资金费率 0.01%（实际需要查询历史费率）
    funding_periods = int(hours_held / 8) + 1  # 已经经过的资金费率周期
    avg_funding_rate = 0.0001  # 0.01%
    
    print("=" * 60)
    print("完整持仓成本分析（包括资金费率）")
    print("=" * 60)
    
    print(f"\n持仓时间:")
    print(f"  开仓时间: {eth_entry_time}")
    print(f"  当前时间: {now_dt.strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"  持仓时长: {days_held:.1f}天 ({hours_held:.1f}小时)")
    print(f"  资金费率周期: {funding_periods}次")
    
    # ETH资金费率
    eth_position_value = eth_quantity * eth_entry * eth_leverage
    eth_funding_per_period = eth_position_value * avg_funding_rate
    eth_total_funding = eth_funding_per_period * funding_periods
    
    # ETH价格盈亏
    eth_price_pnl = (eth_current - eth_entry) * eth_quantity * eth_leverage
    
    # ETH总盈亏
    eth_total_pnl = eth_price_pnl - eth_total_funding
    
    print(f"\nETH/USDT:")
    print(f"  持仓价值: ${eth_position_value:.2f}")
    print(f"  单次资金费率: ${eth_funding_per_period:.4f} (0.01%)")
    print(f"  累计资金费率: ${eth_total_funding:.2f} ({funding_periods}次)")
    print(f"  价格盈亏: ${eth_price_pnl:.2f}")
    print(f"  总盈亏: ${eth_total_pnl:.2f}")
    
    # BTC资金费率
    btc_position_value = btc_quantity * btc_entry * btc_leverage
    btc_funding_per_period = btc_position_value * avg_funding_rate
    btc_total_funding = btc_funding_per_period * funding_periods
    
    # BTC价格盈亏
    btc_price_pnl = (btc_current - btc_entry) * btc_quantity * btc_leverage
    
    # BTC总盈亏
    btc_total_pnl = btc_price_pnl - btc_total_funding
    
    print(f"\nBTC/USDT:")
    print(f"  持仓价值: ${btc_position_value:.2f}")
    print(f"  单次资金费率: ${btc_funding_per_period:.4f} (0.01%)")
    print(f"  累计资金费率: ${btc_total_funding:.2f} ({funding_periods}次)")
    print(f"  价格盈亏: ${btc_price_pnl:.2f}")
    print(f"  总盈亏: ${btc_total_pnl:.2f}")
    
    # 总计
    total_funding = eth_total_funding + btc_total_funding
    total_price_pnl = eth_price_pnl + btc_price_pnl
    total_pnl = eth_total_pnl + btc_total_pnl
    
    print(f"\n" + "=" * 60)
    print(f"总计:")
    print(f"  价格盈亏: ${total_price_pnl:.2f}")
    print(f"  资金费率: -${total_funding:.2f}")
    print(f"  实际总盈亏: ${total_pnl:.2f}")
    print("=" * 60)
    
    print(f"\n注意:")
    print(f"  - 资金费率每8小时收取一次")
    print(f"  - 费率会根据市场多空比例波动")
    print(f"  - 这里使用平均费率0.01%估算")
    print(f"  - 实际费率可能更高或更低")
    print(f"  - 持仓时间越长，累计费用越多")
    
    # 尝试获取真实的资金费率历史
    print(f"\n正在查询真实资金费率...")
    try:
        eth_funding = exchange.fetch_funding_rate_history('ETH/USDT', limit=20)
        if eth_funding:
            recent_rate = eth_funding[-1]['fundingRate']
            print(f"  ETH最新资金费率: {recent_rate*100:.4f}%")
    except:
        print(f"  无法获取实时资金费率")
    
except Exception as e:
    print(f"计算失败: {e}")
    import traceback
    traceback.print_exc()
