#!/usr/bin/env python3
# 测试XMR价格API差异

import requests
import json

print('🔍 测试不同API的XMR价格:')
print('=' * 50)

# 测试币安API
try:
    response = requests.get('https://api.binance.com/api/v3/ticker/price?symbol=XMRUSDT', timeout=10)
    if response.status_code == 200:
        data = response.json()
        print(f'🟡 币安 XMRUSDT: ${float(data["price"]):.2f}')
    else:
        print(f'❌ 币安API错误: {response.status_code}')
except Exception as e:
    print(f'❌ 币安API异常: {e}')

# 测试CoinGecko API
try:
    response = requests.get('https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd', timeout=10)
    if response.status_code == 200:
        data = response.json()
        print(f'🟢 CoinGecko Monero: ${data["monero"]["usd"]:.2f}')
    else:
        print(f'❌ CoinGecko错误: {response.status_code}')
except Exception as e:
    print(f'❌ CoinGecko异常: {e}')

# 测试其他币安交易对
pairs = ['XMRBUSD', 'XMRBTC', 'XMRETH']
print('\n🔍 测试其他交易对:')
for pair in pairs:
    try:
        response = requests.get(f'https://api.binance.com/api/v3/ticker/price?symbol={pair}', timeout=10)
        if response.status_code == 200:
            data = response.json()
            print(f'🟡 币安 {pair}: {data["price"]}')
        else:
            print(f'❌ {pair} 不存在或错误')
    except Exception as e:
        print(f'❌ {pair} 异常: {e}')

# 测试币安24h价格变化数据
print('\n🔍 测试币安24h数据:')
try:
    response = requests.get('https://api.binance.com/api/v3/ticker/24hr?symbol=XMRUSDT', timeout=10)
    if response.status_code == 200:
        data = response.json()
        print(f'📊 24h数据 - 当前: ${float(data["lastPrice"]):.2f}')
        print(f'📊 24h数据 - 开盘: ${float(data["openPrice"]):.2f}')
        print(f'📊 24h数据 - 最高: ${float(data["highPrice"]):.2f}')
        print(f'📊 24h数据 - 最低: ${float(data["lowPrice"]):.2f}')
    else:
        print(f'❌ 24h数据错误: {response.status_code}')
except Exception as e:
    print(f'❌ 24h数据异常: {e}')