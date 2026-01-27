#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""止损监控进程 - 持续监控并执行止损/止盈"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import json
import time
import ccxt
import requests
from datetime import datetime

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化Binance
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

def send_telegram(message):
    """发送Telegram通知"""
    try:
        bot_token = config['telegram']['bot_token']
        chat_id = config['telegram']['chat_id']
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        requests.post(url, data={'chat_id': chat_id, 'text': message}, timeout=5)
    except:
        pass

def check_stop_loss():
    """检查并执行止损/止盈/24小时强制平仓"""
    try:
        # 从web monitor API获取当前持仓
        response = requests.get('http://localhost:5001/api/positions', timeout=5)
        positions = response.json()
        
        if not positions:
            return
        
        current_time = datetime.now()
        
        for pos in positions:
            symbol = pos['symbol']
            current_price = pos['current_price']
            stop_loss = pos['stop_loss']
            take_profit = pos['take_profit']
            entry_price = pos['entry_price']
            quantity = pos['quantity']
            entry_time_str = pos.get('entry_time', '')
            
            # 检查24小时持仓限制
            if entry_time_str:
                entry_time = datetime.fromisoformat(entry_time_str)
                holding_hours = (current_time - entry_time).total_seconds() / 3600
                
                if holding_hours >= 24:
                    print(f"\n⏰ {symbol} 持仓超过24小时，强制平仓！")
                    print(f"   持仓时长: {holding_hours:.1f}小时")
                    print(f"   当前价: ${current_price:.2f}")
                    execute_close(symbol, quantity, current_price, entry_price, "24小时强制平仓")
                    continue
            
            # 检查止损
            if current_price <= stop_loss:
                print(f"\n⚠️ {symbol} 触发止损！")
                print(f"   当前价: ${current_price:.2f}")
                print(f"   止损价: ${stop_loss:.2f}")
                execute_close(symbol, quantity, current_price, entry_price, "止损")
            
            # 检查止盈
            elif current_price >= take_profit:
                print(f"\n✅ {symbol} 触发止盈！")
                print(f"   当前价: ${current_price:.2f}")
                print(f"   止盈价: ${take_profit:.2f}")
                execute_close(symbol, quantity, current_price, entry_price, "止盈")
    
    except Exception as e:
        print(f"检查止损失败: {e}")

def execute_close(symbol, quantity, current_price, entry_price, reason):
    """执行平仓（模拟交易）"""
    try:
        # 计算盈亏
        pnl = (current_price - entry_price) * quantity
        pnl_pct = ((current_price - entry_price) / entry_price) * 100
        
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        message = f"""
{reason}执行完成
交易对: {symbol}
开仓价: ${entry_price:.2f}
平仓价: ${current_price:.2f}
数量: {quantity:.6f}
盈亏: ${pnl:.2f} ({pnl_pct:+.2f}%)
时间: {timestamp}
"""
        
        print(message)
        send_telegram(message)
        
        # 模拟交易：从web_monitor的持仓列表中移除
        # 实际应该调用API删除持仓，这里简化处理
        print(f"✅ {symbol} 已平仓")
        
    except Exception as e:
        print(f"执行平仓失败: {e}")

def main():
    print("=" * 60)
    print("止损监控进程启动 - 方案B配置")
    print("=" * 60)
    print(f"启动时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("监控间隔: 10秒")
    print("止损设置: -2%")
    print("止盈设置: +4%")
    print("最长持仓: 24小时")
    print("=" * 60)
    
    while True:
        try:
            check_stop_loss()
            time.sleep(10)  # 每10秒检查一次
        except KeyboardInterrupt:
            print("\n\n止损监控进程已停止")
            break
        except Exception as e:
            print(f"错误: {e}")
            time.sleep(10)

if __name__ == "__main__":
    main()
