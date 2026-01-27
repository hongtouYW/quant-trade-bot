#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""资金费率实时扣除机制说明"""

from datetime import datetime, timedelta

print("=" * 70)
print("合约交易资金费率机制详解")
print("=" * 70)

# 示例：1月23日14:35开仓ETH
entry_time = datetime(2026, 1, 23, 14, 35, 47)
entry_price = 2948.62
position_value = 810.10  # 持仓价值

print(f"\n【关键概念】")
print(f"\n1️⃣ 入场价格：固定不变")
print(f"   开仓价: ${entry_price:.2f}")
print(f"   ❌ 不会随时间改变")
print(f"   ✅ 永远是 ${entry_price:.2f}")

print(f"\n2️⃣ 持仓成本：会因资金费率增加")
print(f"   初始成本: $90.28（保证金）")
print(f"   每8小时扣资金费率: ~$0.08")
print(f"   3天后累计成本: $90.28 + $0.81 = $91.09")
print(f"   ⚠️ 成本在增加，不是减少！")

print(f"\n3️⃣ 资金费率扣除时间（UTC时间）")
funding_times = ["00:00", "08:00", "16:00"]
print(f"   每天3次: {', '.join(funding_times)}")
print(f"   北京时间: 08:00, 16:00, 00:00")

print(f"\n4️⃣ 实时扣除，不是平仓时结算")
print(f"   ❌ 错误：等到平仓时才一次性扣除")
print(f"   ✅ 正确：每8小时自动从账户扣除")

print(f"\n" + "=" * 70)
print(f"实际扣费时间表（示例）")
print(f"=" * 70)

# 模拟扣费时间表
current_time = entry_time
funding_schedule = []

for i in range(10):
    # 找到下一个资金费率时间点
    next_funding = current_time.replace(minute=0, second=0)
    hour = next_funding.hour
    
    if hour < 8:
        next_funding = next_funding.replace(hour=8)
    elif hour < 16:
        next_funding = next_funding.replace(hour=16)
    else:
        next_funding = next_funding.replace(hour=0)
        next_funding += timedelta(days=1)
    
    if next_funding <= current_time:
        next_funding += timedelta(hours=8)
    
    funding_schedule.append(next_funding)
    current_time = next_funding + timedelta(seconds=1)

print(f"\n开仓时间: {entry_time.strftime('%Y-%m-%d %H:%M')}")
print(f"\n资金费率扣除记录:")

cumulative_fee = 0
for i, funding_time in enumerate(funding_schedule, 1):
    fee = 0.081  # 每次约$0.081
    cumulative_fee += fee
    
    # 计算此时的浮盈
    # 假设价格从2948跌到2889
    days_passed = (funding_time - entry_time).total_seconds() / 86400
    simulated_price = entry_price - (entry_price - 2889) * min(days_passed / 3, 1)
    price_pnl = (simulated_price - entry_price) * 0.09158 * 3
    
    # 实际浮盈 = 价格盈亏 - 累计资金费率
    actual_pnl = price_pnl - cumulative_fee
    
    print(f"\n第{i}次: {funding_time.strftime('%Y-%m-%d %H:%M')}")
    print(f"   扣除: -${fee:.3f}")
    print(f"   累计费用: -${cumulative_fee:.2f}")
    print(f"   当时价格: ${simulated_price:.2f}")
    print(f"   价格盈亏: ${price_pnl:.2f}")
    print(f"   实际浮盈: ${actual_pnl:.2f} (含费用)")

print(f"\n" + "=" * 70)
print(f"【系统应该如何处理】")
print(f"=" * 70)

print(f"""
1. 记录每次资金费率扣除
   ✅ 在数据库中记录每次扣费
   ✅ funding_fees 表记录: 时间、金额、持仓

2. 计算浮盈时包含费用
   ❌ 错误: 浮盈 = 当前价 - 开仓价
   ✅ 正确: 浮盈 = (当前价 - 开仓价) - 累计资金费率

3. 显示时分开展示
   - 价格盈亏: -$32.81
   - 资金费率: -$1.71
   - 总盈亏: -$34.52

4. 平仓时的最终结算
   ✅ 费用已经在持仓期间扣除了
   ✅ 平仓只需计算价格差 + 平仓手续费
   ✅ 不需要再扣资金费率（已经扣过了）
""")

print(f"\n" + "=" * 70)
print(f"【当前系统需要改进】")
print(f"=" * 70)

print(f"""
❌ 当前问题:
   - 只计算价格盈亏
   - 没有记录资金费率
   - 浮盈不准确

✅ 需要添加:
   1. 创建 funding_fees 表
   2. 每8小时自动记录资金费率
   3. 计算浮盈时减去累计费用
   4. 在监控面板显示完整成本
""")

print(f"\n要我帮你实现这个功能吗？")
