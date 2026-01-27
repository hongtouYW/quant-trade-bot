#!/usr/bin/env python3
# XMR价格$509的盈亏计算

entry_price = 502.41
current_price = 509.0
leverage = 10
principal = 100

# 计算
price_change = (current_price - entry_price) / entry_price * 100
roi = price_change * leverage
pnl = (roi / 100) * principal
total_balance = principal + pnl

# 止损止盈位
stop_loss = entry_price * 0.98  # -2%
take_profit = entry_price * 1.02  # +2%
take_profit_warning = entry_price * 1.015  # +1.5% 预警

print(f'🎯 XMR价格分析 - ${current_price}')
print(f'💰 当前价格: ${current_price:.2f}')
print(f'📊 入场价格: ${entry_price:.2f}')
print(f'📈 价格变化: +{price_change:.2f}%')
print(f'💎 杠杆倍数: {leverage}x')
print(f'💵 投资回报率: +{roi:.2f}%')
print(f'💰 盈亏金额: 📈 ${pnl:+.2f}U')
print(f'💳 总余额: ${total_balance:.2f}U')
print('━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
print(f'🛡️ 止损价格: ${stop_loss:.2f}')
print(f'🎯 止盈价格: ${take_profit:.2f}')
print(f'⚠️ 止盈预警: ${take_profit_warning:.2f}')

# 状态分析
if current_price >= take_profit:
    print('🎉 已达到止盈目标！建议考虑平仓！')
elif current_price >= take_profit_warning:
    print('⚠️ 接近止盈目标，建议密切关注！')
else:
    distance_to_profit = ((take_profit - current_price) / current_price) * 100
    print(f'📊 距止盈还有: {distance_to_profit:.1f}%')