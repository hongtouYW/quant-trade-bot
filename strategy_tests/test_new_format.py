#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# 测试新的Telegram格式

import requests
import json

# 读取配置
with open('config.json', 'r') as f:
    config = json.load(f)

bot_token = config['telegram']['bot_token']
chat_id = config['telegram']['chat_id']

# 模拟盈利状态的消息
current_price = 512.61
entry_price = 502.41
roi = ((current_price - entry_price) / entry_price) * 1000  # 10x杠杆
pnl = 20.30
total_balance = 120.30

# 使用新格式
pnl_emoji = "📈" if pnl >= 0 else "📉"
roi_emoji = "🟢" if roi >= 0 else "🔴"

message = f"""🎉 <b>止盈触发测试</b> 🎉
━━━━━━━━━━━━━━
💰 价格: ${current_price:.2f}
📊 入场: ${entry_price:.2f}
💵 ROI: {roi_emoji} {roi:+.1f}%
💰 盈亏: {pnl_emoji} ${pnl:+.2f}U
💳 余额: ${total_balance:.2f}U
🎯 建议考虑平仓获利！"""

url = f'https://api.telegram.org/bot{bot_token}/sendMessage'
payload = {
    'chat_id': chat_id,
    'text': message,
    'parse_mode': 'HTML'
}

response = requests.post(url, json=payload)
print(f'新格式测试结果: {response.status_code}')
if response.status_code == 200:
    print('✅ 新的颜色格式测试成功！')
    print('📱 您应该看到带绿色emoji的盈亏显示')
else:
    print(f'❌ 测试失败: {response.text}')