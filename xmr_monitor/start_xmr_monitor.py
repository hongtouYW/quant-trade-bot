#!/usr/bin/env python3
# XMR监控自动启动脚本

import sys
import os
sys.path.append('.')

from xmr_contract_monitor import XMRContractMonitor

if __name__ == "__main__":
    print('🚀 启动XMR自动监控系统...')
    monitor = XMRContractMonitor(entry_price=502.41, leverage=10)
    monitor.run_monitoring(interval=300)  # 5分钟间隔