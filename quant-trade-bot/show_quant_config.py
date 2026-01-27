#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""显示量化系统监控配置"""

import sys
sys.path.insert(0, '/Users/hongtou/newproject/quant-trade-bot')
from config.config_multi_timeframe import TRADING_PAIRS, STRATEGY_CONFIG

print('=' * 70)
print('📊 量化模拟系统监控配置 (增强版)')
print('=' * 70)
print(f'\n监控币种数: {len(TRADING_PAIRS["active_pairs"])} 个')
print(f'最大持仓数: {STRATEGY_CONFIG["multi_timeframe"]["max_positions"]}')
print('\n监控币种列表:')

# 分类显示
print('\n💰 主流币 (4个):')
for i, coin in enumerate(TRADING_PAIRS['active_pairs'][:4], 1):
    print(f'  {i}. {coin}')

print('\n🔗 Layer1 公链 (3个):')
for i, coin in enumerate(TRADING_PAIRS['active_pairs'][4:7], 1):
    print(f'  {i}. {coin}')

print('\n💎 DeFi (3个):')
for i, coin in enumerate(TRADING_PAIRS['active_pairs'][7:10], 1):
    print(f'  {i}. {coin}')

print('\n🚀 热门Altcoins (5个):')
for i, coin in enumerate(TRADING_PAIRS['active_pairs'][10:], 1):
    print(f'  {i}. {coin}')

print('\n策略参数:')
print(f'  - 止损: -2%')
print(f'  - 止盈: +4%')
print(f'  - 最长持仓: 24小时')
print(f'  - 扫描间隔: 5分钟')
print(f'  - 趋势阈值: 0.5')
print(f'  - 信心阈值: 0.4')
print('=' * 70)

print('\n💡 提示:')
print('  - 量化系统会自动扫描这15个币种')
print('  - 发现信号后自动开仓（模拟交易）')
print('  - 最多同时持有8个仓位')
print('  - 每个仓位独立止损止盈')
print('  - 超过24小时强制平仓')
