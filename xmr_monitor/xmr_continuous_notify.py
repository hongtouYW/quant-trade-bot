#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# 连续更新版本 - 定期发送价格更新

import sys
import os

sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from xmr_simple_telegram import XMRSimpleMonitor
import time

def main():
    print("📱 XMR定期通知版 - 每5分钟自动发送")
    print("=" * 50)
    
    monitor = XMRSimpleMonitor(entry_price=502.41, leverage=10)
    
    # 定期通知模式 - 每5分钟发送
    try:
        while True:
            # 检查网络连接
            if not monitor.check_network():
                print("❌ 网络连接失败，等待网络恢复...")
                time.sleep(30)
                continue
            
            current_price = monitor.get_price()
            if current_price:
                pnl_data = monitor.calculate_pnl(current_price)
                
                # 每次都发送Telegram更新（不依赖触发条件）
                pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
                roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
                
                # 判断风险状态
                if current_price >= monitor.take_profit_price:
                    status = "🎉 已达止盈目标"
                elif current_price >= monitor.take_profit_warning:
                    status = "⚠️ 接近止盈"
                elif current_price <= monitor.stop_loss_price:
                    status = "🚨 已触及止损"
                elif current_price <= monitor.stop_loss_warning:
                    status = "⚠️ 接近止损"
                else:
                    status = "📊 正常监控中"
                
                update_msg = f"""📊 <b>XMR定时更新</b>
━━━━━━━━━━━━━━
💰 当前价格: ${current_price:.2f}
📊 入场价格: ${monitor.entry_price:.2f}
💵 投资回报率: {roi_emoji} {pnl_data['roi']:+.2f}%
💰 盈亏金额: {pnl_emoji} ${pnl_data['pnl_amount']:+.2f}U
💳 账户余额: ${pnl_data['total_balance']:.2f}U
📈 状态: {status}
⏰ {time.strftime('%H:%M:%S')}"""
                
                monitor.send_telegram_message(update_msg)
                monitor.display_status(current_price, pnl_data)
                
                print("✅ 5分钟定时更新已发送")
            else:
                print("❌ 价格获取失败，5分钟后重试")
            
            # 等待5分钟
            print("⏳ 下次更新: 5分钟后")
            time.sleep(300)  # 5分钟 = 300秒
            
    except KeyboardInterrupt:
        print("\n👋 定时监控已停止")
        if monitor.telegram_available:
            monitor.send_telegram_message("⏹️ XMR定时监控已停止")

if __name__ == "__main__":
    main()