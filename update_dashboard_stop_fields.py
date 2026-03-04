#!/usr/bin/env python3
"""
Update dashboard to show stop loss tracking fields
"""

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/trading_assistant_dashboard.py"

with open(file_path, "r") as f:
    content = f.read()

# 1. Update API to return new fields
old_select = "amount, leverage, pnl, roi, fee, funding_fee, entry_time, exit_time,"
new_select = "amount, leverage, pnl, roi, fee, funding_fee, entry_time, exit_time, initial_stop_loss, final_stop_loss, stop_move_count,"

if old_select in content and "initial_stop_loss" not in content:
    content = content.replace(old_select, new_select)
    print("✅ API添加新字段")
else:
    print("⏭️ API已有新字段或找不到")

# 2. Add display in trade card - after the reason field
old_display = """                                \${trade.exit_time ? \`
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">平仓</span>
                                    <span class="trade-card-detail-value">\${formatTime(trade.exit_time)}</span>
                                </div>
                                \` : ''}
                                </div>"""

new_display = """                                \${trade.exit_time ? \`
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">平仓</span>
                                    <span class="trade-card-detail-value">\${formatTime(trade.exit_time)}</span>
                                </div>
                                \` : ''}
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">初始止损</span>
                                    <span class="trade-card-detail-value">\${trade.initial_stop_loss ? '$' + formatNumber(trade.initial_stop_loss, 4) : '-'}</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">最终止损</span>
                                    <span class="trade-card-detail-value">\${trade.final_stop_loss ? '$' + formatNumber(trade.final_stop_loss, 4) : (trade.stop_loss ? '$' + formatNumber(trade.stop_loss, 4) : '-')}</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">移动次数</span>
                                    <span class="trade-card-detail-value">\${trade.stop_move_count || '-'}</span>
                                </div>
                                </div>"""

if old_display in content:
    content = content.replace(old_display, new_display)
    print("✅ 添加止损跟踪显示")
else:
    print("⚠️ 找不到显示位置，尝试其他方式...")

# Write back
with open(file_path, "w") as f:
    f.write(content)

print("\n🎉 Dashboard更新完成!")
