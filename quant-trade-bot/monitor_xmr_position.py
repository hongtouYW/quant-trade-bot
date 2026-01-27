#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""XMR持仓监控 - 止盈止损Telegram通知"""

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import ccxt
import json
import time
import requests
from datetime import datetime

# 读取配置
with open('config/config.json', 'r') as f:
    config = json.load(f)

# 初始化交易所
exchange = ccxt.binance({
    'apiKey': config['binance']['api_key'],
    'secret': config['binance']['api_secret'],
    'enableRateLimit': True,
    'options': {'defaultType': 'future'}
})

# 从文件加载持仓信息
def load_position():
    """从JSON文件加载持仓信息"""
    position_file = '/Users/hongtou/newproject/quant-trade-bot/my_xmr_position.json'
    try:
        with open(position_file, 'r') as f:
            data = json.load(f)
        return {
            'symbol': data['symbol'],
            'entry_price': data['entry_price'],
            'quantity': data['quantity'],
            'position_size': data['position_size'],
            'leverage': data['leverage'],
            'stop_loss': data['stop_loss'],
            'take_profit': data['take_profit'],
            'liquidation': data['liquidation_price']
        }
    except Exception as e:
        print(f"❌ 加载持仓文件失败: {e}")
        return None

POSITION = load_position()
if POSITION is None:
    print("❌ 无法加载持仓信息，退出")
    sys.exit(1)

# 启动时显示持仓信息
print(f"📊 XMR持仓监控已启动")
print(f"币种: {POSITION['symbol']}")
print(f"入场价: ${POSITION['entry_price']}")
print(f"仓位: ${POSITION['position_size']} USDT")
print(f"杠杆: {POSITION['leverage']}x")
print(f"数量: {POSITION['quantity']:.4f} XMR")
print(f"止损: ${POSITION['stop_loss']} (-5%)")
print(f"止盈: ${POSITION['take_profit']} (+10%)")
print(f"强平: ${POSITION['liquidation']} (-10%)")
print(f"{'='*60}")

def send_telegram(message):
    """发送Telegram通知并@用户"""
    try:
        bot_token = config['telegram']['bot_token']
        chat_id = config['telegram']['chat_id']
        
        message_with_mention = f"@Hzai5522\n\n{message}"
        
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        requests.post(url, data={
            'chat_id': chat_id, 
            'text': message_with_mention,
            'parse_mode': 'HTML'
        }, timeout=5)
        print("✅ Telegram通知已发送")
        return True
    except Exception as e:
        print(f"⚠️ Telegram发送失败: {e}")
        return False

def calculate_pnl(current_price):
    """计算盈亏"""
    price_change = ((current_price - POSITION['entry_price']) / POSITION['entry_price'])
    pnl_usdt = POSITION['position_size'] * price_change * POSITION['leverage']
    pnl_pct = (pnl_usdt / POSITION['position_size']) * 100
    return pnl_usdt, pnl_pct, price_change * 100

def check_position():
    """检查持仓状态"""
    try:
        # 获取当前价格
        ticker = exchange.fetch_ticker(POSITION['symbol'])
        current_price = ticker['last']
        
        # 计算盈亏
        pnl_usdt, pnl_pct, price_change_pct = calculate_pnl(current_price)
        
        # 距离关键价格
        to_stop_loss = ((current_price - POSITION['stop_loss']) / POSITION['stop_loss']) * 100
        to_take_profit = ((current_price - POSITION['take_profit']) / POSITION['take_profit']) * 100
        to_liquidation = ((current_price - POSITION['liquidation']) / POSITION['liquidation']) * 100
        
        status = {
            'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'current_price': current_price,
            'pnl_usdt': pnl_usdt,
            'pnl_pct': pnl_pct,
            'price_change_pct': price_change_pct,
            'to_stop_loss': to_stop_loss,
            'to_take_profit': to_take_profit,
            'to_liquidation': to_liquidation
        }
        
        return status
        
    except Exception as e:
        print(f"❌ 获取价格失败: {e}")
        return None

def monitor_position():
    """监控持仓并发送通知"""
    print("=" * 70)
    print("🔍 XMR持仓监控启动")
    print("=" * 70)
    print(f"币种: {POSITION['symbol']}")
    print(f"入场价: ${POSITION['entry_price']:.2f}")
    print(f"保证金: ${POSITION['position_size']:,.0f} USDT")
    print(f"杠杆: {POSITION['leverage']}x")
    print(f"止损价: ${POSITION['stop_loss']:.2f}")
    print(f"止盈价: ${POSITION['take_profit']:.2f}")
    print(f"爆仓价: ${POSITION['liquidation']:.2f}")
    print("=" * 70)
    
    # 状态标记
    stop_loss_triggered = False
    take_profit_triggered = False
    warning_sent = False
    
    check_count = 0
    
    while True:
        try:
            status = check_position()
            
            if not status:
                time.sleep(5)
                continue
            
            check_count += 1
            current_price = status['current_price']
            pnl_usdt = status['pnl_usdt']
            pnl_pct = status['pnl_pct']
            
            # 每次检查显示状态
            print(f"\n[{status['time']}] 检查 #{check_count}")
            print(f"  当前价: ${current_price:.2f} ({status['price_change_pct']:+.2f}%)")
            print(f"  盈亏: ${pnl_usdt:+.0f} USDT ({pnl_pct:+.0f}%)")
            print(f"  距止损: {status['to_stop_loss']:+.2f}%")
            print(f"  距止盈: {status['to_take_profit']:+.2f}%")
            print(f"  距爆仓: {status['to_liquidation']:+.2f}%")
            
            # 1. 检查止损（触发）
            if current_price <= POSITION['stop_loss'] and not stop_loss_triggered:
                message = f"""
🛑 止损触发！

币种: {POSITION['symbol']}
入场价: ${POSITION['entry_price']:.2f}
当前价: ${current_price:.2f} ({status['price_change_pct']:.2f}%)
止损价: ${POSITION['stop_loss']:.2f}

盈亏: ${pnl_usdt:.0f} USDT ({pnl_pct:.0f}%)
杠杆: {POSITION['leverage']}x

⚠️ 建议立即平仓止损！
"""
                if send_telegram(message):
                    stop_loss_triggered = True
                    print("🛑 止损通知已发送")
            
            # 2. 检查止盈（触发）
            elif current_price >= POSITION['take_profit'] and not take_profit_triggered:
                message = f"""
🎉 止盈触发！

币种: {POSITION['symbol']}
入场价: ${POSITION['entry_price']:.2f}
当前价: ${current_price:.2f} ({status['price_change_pct']:+.2f}%)
止盈价: ${POSITION['take_profit']:.2f}

盈利: ${pnl_usdt:+.0f} USDT ({pnl_pct:+.0f}%)
杠杆: {POSITION['leverage']}x

✅ 建议平仓获利！
"""
                if send_telegram(message):
                    take_profit_triggered = True
                    print("🎉 止盈通知已发送")
            
            # 3. 接近爆仓警告（距离爆仓5%以内）
            elif status['to_liquidation'] < 5 and not warning_sent:
                message = f"""
⚠️⚠️⚠️ 爆仓警告！

币种: {POSITION['symbol']}
当前价: ${current_price:.2f}
爆仓价: ${POSITION['liquidation']:.2f}
距离爆仓: 仅{status['to_liquidation']:.2f}%

当前亏损: ${pnl_usdt:.0f} USDT ({pnl_pct:.0f}%)

🔴 立即采取行动：
1. 平仓止损
2. 或追加保证金
3. 或降低杠杆
"""
                if send_telegram(message):
                    warning_sent = True
                    print("⚠️ 爆仓警告已发送")
            
            # 4. 每小时报告（每720次检查 = 60分钟）
            if check_count % 720 == 0:
                message = f"""
📊 XMR持仓小时报告

时间: {status['time']}
入场价: ${POSITION['entry_price']:.2f}
当前价: ${current_price:.2f} ({status['price_change_pct']:+.2f}%)

当前盈亏: ${pnl_usdt:+.0f} USDT ({pnl_pct:+.0f}%)

止损: ${POSITION['stop_loss']:.2f} (距离{abs(status['to_stop_loss']):.1f}%)
止盈: ${POSITION['take_profit']:.2f} (距离{abs(status['to_take_profit']):.1f}%)
爆仓: ${POSITION['liquidation']:.2f} (距离{abs(status['to_liquidation']):.1f}%)

持仓中...
"""
                send_telegram(message)
                print("📊 小时报告已发送")
            
            # 每5秒检查一次
            time.sleep(5)
            
        except KeyboardInterrupt:
            print("\n\n监控已停止")
            break
        except Exception as e:
            print(f"❌ 监控错误: {e}")
            time.sleep(5)

if __name__ == "__main__":
    try:
        # 发送启动通知
        start_message = f"""
🚀 XMR持仓监控已启动

币种: {POSITION['symbol']}
入场价: ${POSITION['entry_price']:.2f}
保证金: ${POSITION['position_size']:,.0f} USDT
杠杆: {POSITION['leverage']}x

止损: ${POSITION['stop_loss']:.2f} (-5%)
止盈: ${POSITION['take_profit']:.2f} (+10%)
爆仓: ${POSITION['liquidation']:.2f} (-10%)

将每5秒检查一次，触发时通知您
"""
        send_telegram(start_message)
        
        # 开始监控
        monitor_position()
        
    except Exception as e:
        print(f"\n❌ 监控失败: {e}")
        import traceback
        traceback.print_exc()
