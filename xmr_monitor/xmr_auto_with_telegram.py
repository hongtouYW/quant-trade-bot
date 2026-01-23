# -*- coding: utf-8 -*-
"""
XMR合约价格监控系统 - 自动模式 + Telegram通知
入场价格: $502.41
杠杆: 10倍
"""

import sys
import os

# 添加当前目录到Python路径
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from xmr_contract_monitor import XMRContractMonitor

def main():
    """自动运行模式 - 有Telegram通知"""
    print("🎯 XMR合约监控系统 - 自动模式 (含Telegram通知)")
    print("=" * 50)
    
    # 使用默认参数，无需手动输入
    entry_price = 502.41
    leverage = 10
    interval = 300  # 5分钟间隔
    
    print(f"💰 入场价格: ${entry_price}")
    print(f"📊 杠杆倍数: {leverage}x")
    print(f"⏰ 监控间隔: {interval}秒 (5分钟)")
    print(f"📱 将通过Telegram发送重要通知")
    
    # 创建监控器
    monitor = XMRContractMonitor(entry_price=entry_price, leverage=leverage)
    
    # 开始监控
    monitor.run_monitoring(interval=interval)

if __name__ == "__main__":
    main()