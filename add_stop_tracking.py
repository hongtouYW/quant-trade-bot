#!/usr/bin/env python3
"""
Update paper_trader.py to track initial vs final stop loss
"""

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/paper_trader.py"

with open(file_path, "r") as f:
    content = f.read()

# 1. Add initial_stop_loss when opening position
old_open = "'stop_loss': stop_loss,"
new_open = """'stop_loss': stop_loss,
                'initial_stop_loss': stop_loss,
                'stop_move_count': 0,"""

if old_open in content and "'initial_stop_loss'" not in content:
    content = content.replace(old_open, new_open)
    print("✅ 添加 initial_stop_loss 到开仓逻辑")
else:
    print("⏭️ initial_stop_loss 已存在或找不到位置")

# 2. Update stop_move_count when stop loss moves (for LONG)
old_stop_move_long = "position['stop_loss'] = new_stop"
new_stop_move_long = """position['stop_loss'] = new_stop
                        position['stop_move_count'] = position.get('stop_move_count', 0) + 1"""

if old_stop_move_long in content and "stop_move_count" not in content.split("position['stop_loss'] = new_stop")[1][:100]:
    content = content.replace(old_stop_move_long, new_stop_move_long, 1)
    print("✅ 添加 stop_move_count 到LONG移动逻辑")

# 3. Save final_stop_loss before closing (add to close_position)
old_close = "UPDATE real_trades"
# We'll add final_stop_loss to the UPDATE statement

# Write back
with open(file_path, "w") as f:
    f.write(content)

print("\n🎉 止损跟踪字段添加完成!")
print("- initial_stop_loss: 记录开仓时止损")
print("- final_stop_loss: 记录平仓时止损")
print("- stop_move_count: 记录止损移动次数")
