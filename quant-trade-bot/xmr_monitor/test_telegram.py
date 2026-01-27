#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""测试Telegram通知"""

import json
import os
import requests

def test_telegram():
    """测试Telegram配置"""
    # 尝试加载配置
    config_paths = [
        '../config/config.json',
        os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'config', 'config.json')
    ]
    
    for config_path in config_paths:
        if os.path.exists(config_path):
            print(f"✅ 找到配置文件: {config_path}")
            with open(config_path, 'r', encoding='utf-8') as f:
                config = json.load(f)
                telegram_config = config.get('telegram', {})
                bot_token = telegram_config.get('bot_token')
                chat_id = telegram_config.get('chat_id')
                
                if bot_token and chat_id:
                    print(f"✅ Bot Token: {bot_token[:10]}...")
                    print(f"✅ Chat ID: {chat_id}")
                    
                    # 测试发送消息
                    url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
                    payload = {
                        "chat_id": chat_id,
                        "text": "🧪 <b>Telegram测试消息</b>\n\n✅ XMR监控系统连接正常！",
                        "parse_mode": "HTML"
                    }
                    
                    print("\n📤 发送测试消息...")
                    response = requests.post(url, json=payload, timeout=10)
                    
                    if response.status_code == 200:
                        print("✅ Telegram消息发送成功！")
                        print(f"响应: {response.json()}")
                    else:
                        print(f"❌ Telegram发送失败")
                        print(f"状态码: {response.status_code}")
                        print(f"响应: {response.text}")
                    return
                else:
                    print("❌ Telegram配置不完整")
    
    print("❌ 未找到配置文件")

if __name__ == "__main__":
    test_telegram()
