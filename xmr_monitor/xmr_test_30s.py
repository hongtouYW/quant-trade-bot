#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# 快速测试版 - 30秒间隔，测试Telegram通知

import sys
import os

# 添加路径
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from xmr_simple_telegram import XMRSimpleMonitor

def main():
    print("🧪 XMR监控快速测试版 - 30秒间隔")
    print("=" * 50)
    
    monitor = XMRSimpleMonitor(entry_price=502.41, leverage=10)
    
    # 短间隔测试
    monitor.run_monitoring(interval=30)  # 30秒间隔

if __name__ == "__main__":
    main()