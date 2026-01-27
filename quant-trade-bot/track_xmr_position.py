#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""计算XMR持仓详情和风险"""

import json
from datetime import datetime

# 持仓信息
entry_price = 464.65
position_size = 1000  # USDT
leverage = 10

print("=" * 70)
print("📊 XMR/USDT 持仓详情")
print("=" * 70)
print(f"买入时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
print(f"入场价格: ${entry_price:.2f}")
print(f"投入资金: ${position_size:,.0f} USDT")
print(f"杠杆: {leverage}x")

# 计算持仓量
position_value = position_size * leverage
quantity = position_value / entry_price

print(f"\n持仓详情:")
print(f"  持仓价值: ${position_value:,.0f} USDT")
print(f"  XMR数量: {quantity:.4f} XMR")
print(f"  保证金: ${position_size:,.0f} USDT")

# 爆仓价格（简化计算，不考虑手续费）
# 10x杠杆，亏损10%就爆仓
liquidation_loss_pct = 1.0 / leverage
liquidation_price = entry_price * (1 - liquidation_loss_pct)

print(f"\n⚠️ 风险提示:")
print(f"  爆仓价格: ${liquidation_price:.2f} (-{liquidation_loss_pct*100:.1f}%)")
print(f"  距离爆仓: ${entry_price - liquidation_price:.2f}")
print(f"  爆仓幅度: {liquidation_loss_pct*100:.1f}%")

# 建议止损止盈
stop_loss_pct = 0.05  # -5%
take_profit_pct = 0.10  # +10%

stop_loss_price = entry_price * (1 - stop_loss_pct)
take_profit_price = entry_price * (1 + take_profit_pct)

# 计算盈亏（10x杠杆）
stop_loss_amount = -position_size * (stop_loss_pct * leverage)
take_profit_amount = position_size * (take_profit_pct * leverage)

print(f"\n💡 建议止损止盈:")
print(f"  止损价: ${stop_loss_price:.2f} (-{stop_loss_pct*100:.0f}%)")
print(f"    → 亏损: ${stop_loss_amount:.0f} USDT ({stop_loss_amount/position_size*100:.0f}%)")
print(f"")
print(f"  止盈价: ${take_profit_price:.2f} (+{take_profit_pct*100:.0f}%)")
print(f"    → 盈利: ${take_profit_amount:.0f} USDT (+{take_profit_amount/position_size*100:.0f}%)")

# 不同价格的盈亏
print(f"\n📈 盈亏表 (10x杠杆):")
print(f"{'价格':<12} {'变化':<10} {'盈亏(USDT)':<15} {'盈亏率':<10}")
print("-" * 55)

price_levels = [
    (liquidation_price, "爆仓"),
    (445.00, -4.2),
    (455.00, -2.1),
    (entry_price, 0),
    (475.00, 2.2),
    (485.00, 4.4),
    (500.00, 7.6),
    (take_profit_price, 10.0),
]

for price, change in price_levels:
    if isinstance(change, str):
        pnl = -position_size
        pnl_pct = -100
        label = f"({change})"
    else:
        price_change_pct = change / 100
        pnl = position_size * price_change_pct * leverage
        pnl_pct = (pnl / position_size) * 100
        label = ""
    
    print(f"${price:<10.2f} {change if isinstance(change, str) else f'{change:+.1f}%':<9} ${pnl:>+13.0f} {pnl_pct:>+9.0f}% {label}")

print("\n" + "=" * 70)
print("⚠️⚠️⚠️ 风险警告 ⚠️⚠️⚠️")
print("=" * 70)
print("❌ 10x杠杆风险极高！")
print("❌ 价格下跌10%就会爆仓")
print("❌ 当前价$464.65，爆仓价$418.19，仅相差$46.46")
print("❌ 建议立即设置止损$441.42 (-5%)")
print("❌ 或考虑降低杠杆到3x-5x")
print("=" * 70)

# 实时监控建议
print("\n💡 监控建议:")
print("  1. 立即在Binance设置止损单: $441.42")
print("  2. 设置止盈单: $511.12 (+10%)")
print("  3. 密切关注价格，不要离开")
print("  4. 如果回调至$455，考虑减仓")
print("  5. RSI当前28超卖，有反弹机会")

# 保存持仓记录
position_record = {
    "symbol": "XMR/USDT",
    "side": "LONG",
    "entry_price": entry_price,
    "quantity": quantity,
    "position_size": position_size,
    "leverage": leverage,
    "liquidation_price": liquidation_price,
    "stop_loss": stop_loss_price,
    "take_profit": take_profit_price,
    "entry_time": datetime.now().isoformat(),
    "status": "OPEN"
}

with open('my_xmr_position.json', 'w') as f:
    json.dump(position_record, f, indent=2)

print(f"\n✅ 持仓已记录到: my_xmr_position.json")
print("=" * 70)
