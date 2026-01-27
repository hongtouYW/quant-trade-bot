#!/bin/bash
# XMR监控定时任务脚本 - 适合添加到crontab

SCRIPT_DIR="/Users/hongtou/newproject/quant-trade-bot"
PYTHON_SCRIPT="xmr_contract_monitor.py"
LAST_CHECK_FILE="${SCRIPT_DIR}/last_xmr_check.json"
LOG_FILE="${SCRIPT_DIR}/xmr_cron.log"

cd "$SCRIPT_DIR"

# 记录时间戳
echo "$(date '+%Y-%m-%d %H:%M:%S') - 开始检查XMR价格..." >> "$LOG_FILE"

# 运行单次检查（需要修改监控脚本支持单次模式）
timeout 60 python -c "
import sys
sys.path.append('.')
from xmr_contract_monitor import XMRContractMonitor
import json
import os

# 创建监控器
monitor = XMRContractMonitor(entry_price=502.41, leverage=10)

# 获取当前价格
current_price = monitor.get_current_price()
if current_price:
    # 检查触发条件
    trigger = monitor.check_triggers(current_price)
    
    # 保存检查结果
    result = {
        'timestamp': '$(date '+%Y-%m-%d %H:%M:%S')',
        'price': current_price,
        'trigger': trigger,
        'pnl_data': monitor.calculate_pnl(current_price)
    }
    
    with open('$LAST_CHECK_FILE', 'w') as f:
        json.dump(result, f, indent=2)
    
    print(f'价格检查完成: \${current_price:.2f}')
    if trigger:
        print(f'触发条件: {trigger}')
else:
    print('价格获取失败')
" >> "$LOG_FILE" 2>&1

echo "$(date '+%Y-%m-%d %H:%M:%S') - XMR检查完成" >> "$LOG_FILE"
echo "----------------------------------------" >> "$LOG_FILE"