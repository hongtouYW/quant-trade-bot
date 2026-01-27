#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# 测试Telegram通知

import requests
import json

# 读取配置
with open('config.json', 'r') as f:
    config = json.load(f)

bot_token = config['telegram']['bot_token']
chat_id = config['telegram']['chat_id']

# 发送实时测试消息
current_price = 509.09
entry_price = 502.41
roi = ((current_price - entry_price) / entry_price) * 1000  # 10x杠杆

message = f"""🔔 XMR监控测试通知
💰 当前价格: ${current_price:.2f}
📊 入场价格: ${entry_price:.2f}  
💵 投资回报率: +{roi:.1f}%
📊 距止盈预警还有: ${509.95 - current_price:.2f}
⏰ 测试时间: 现在"""

url = f'https://api.telegram.org/bot{bot_token}/sendMessage'
payload = {
    'chat_id': chat_id,
    'text': message
}

response = requests.post(url, json=payload)
print(f'Telegram测试结果: {response.status_code}')
if response.status_code == 200:
    print('✅ Telegram通知测试成功！')
    print('📱 您应该已经收到了测试消息')
else:
    print(f'❌ Telegram测试失败: {response.text}')