#!/bin/bash
# 保活脚本 - 确保核心服务持续运行

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"
TRADING_PID="$PROJECT_DIR/logs/trading_system.pid"
XMR_PID="$PROJECT_DIR/xmr_monitor/xmr_monitor.pid"

LOG_FILE="$PROJECT_DIR/logs/keep_alive.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

check_and_restart() {
    local SERVICE_NAME=$1
    local PID_FILE=$2
    local START_COMMAND=$3
    
    if [ -f "$PID_FILE" ] && kill -0 $(cat "$PID_FILE") 2>/dev/null; then
        return 0  # 服务运行中
    else
        log_message "⚠️  $SERVICE_NAME 未运行，正在重启..."
        eval "$START_COMMAND"
        log_message "✅ $SERVICE_NAME 已重启"
        return 1
    fi
}

log_message "🔍 检查核心服务状态..."

# 检查实盘模拟交易
check_and_restart \
    "实盘模拟交易" \
    "$TRADING_PID" \
    "cd $PROJECT_DIR && nohup python3 src/core/integrated_trading_system.py > logs/trading_system.log 2>&1 & echo \$! > $TRADING_PID"

# 检查XMR监控
check_and_restart \
    "XMR监控" \
    "$XMR_PID" \
    "cd $PROJECT_DIR/xmr_monitor && nohup python3 xmr_continuous_notify.py > logs/xmr_monitor.log 2>&1 & echo \$! > $XMR_PID"

log_message "✅ 检查完成"
