#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""查询ERC20代币合约信息"""

import requests
import json

address = '0xf74548802f4c700315f019fde17178b392ee4444'

print(f'查询合约地址: {address}')
print('=' * 70)

# 尝试从CoinGecko查询
print('\n从 CoinGecko 查询...')
try:
    url = f'https://api.coingecko.com/api/v3/coins/ethereum/contract/{address}'
    response = requests.get(url, timeout=10)
    
    if response.status_code == 200:
        data = response.json()
        print(f"✅ 找到代币信息:")
        print(f"  名称: {data.get('name', 'N/A')}")
        print(f"  符号: {data.get('symbol', 'N/A').upper()}")
        
        market_data = data.get('market_data', {})
        if market_data:
            price = market_data.get('current_price', {}).get('usd')
            if price:
                print(f"  当前价格: ${price:.8f}")
            
            market_cap = market_data.get('market_cap', {}).get('usd')
            if market_cap:
                print(f"  市值: ${market_cap:,.0f}")
            
            change_24h = market_data.get('price_change_percentage_24h')
            if change_24h:
                print(f"  24h变化: {change_24h:+.2f}%")
            
            total_volume = market_data.get('total_volume', {}).get('usd')
            if total_volume:
                print(f"  24h交易量: ${total_volume:,.0f}")
        
        print(f"\n  合约地址: {address}")
        
        links = data.get('links', {})
        if links.get('homepage'):
            print(f"  官网: {links['homepage'][0] if links['homepage'] else 'N/A'}")
        if links.get('twitter_screen_name'):
            print(f"  Twitter: @{links['twitter_screen_name']}")
            
    else:
        print(f'❌ CoinGecko返回状态码: {response.status_code}')
        if response.status_code == 404:
            print('   该代币未在CoinGecko收录')
        
except Exception as e:
    print(f'❌ 查询失败: {e}')

# 尝试从Etherscan API查询
print('\n从 Etherscan 查询基本信息...')
try:
    # 不需要API key的基本查询
    url = f'https://api.etherscan.io/api?module=token&action=tokeninfo&contractaddress={address}'
    response = requests.get(url, timeout=10)
    
    if response.status_code == 200:
        data = response.json()
        if data.get('status') == '1' and data.get('result'):
            result = data['result'][0] if isinstance(data['result'], list) else data['result']
            print(f"✅ Etherscan信息:")
            print(f"  名称: {result.get('tokenName', 'N/A')}")
            print(f"  符号: {result.get('symbol', 'N/A')}")
            print(f"  精度: {result.get('divisor', 'N/A')}")
            print(f"  总供应量: {result.get('totalSupply', 'N/A')}")
        else:
            print('❌ 无法获取代币信息')
    else:
        print(f'❌ Etherscan API请求失败')
        
except Exception as e:
    print(f'❌ Etherscan查询失败: {e}')

print('\n' + '=' * 70)
print('提示: 如果是新币或未上市币种，可能无法查询到完整信息')
