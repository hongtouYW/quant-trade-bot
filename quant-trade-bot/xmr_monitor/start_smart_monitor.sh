#!/bin/bash
# 智能持仓监控启动脚本

cd "$(dirname "$0")"

echo "🚀 启动智能持仓监控..."

# 停止旧的监控进程
pkill -f "xmr_simple_telegram.py"
pkill -f "smart_position_monitor.py"

# 启动新的智能监控
nohup python3 smart_position_monitor.py > logs/smart_monitor.log 2>&1 &

PID=$!
echo "✅ 智能监控已启动 (PID: $PID)"
echo "📋 日志文件: logs/smart_monitor.log"
echo ""
echo "查看日志: tail -f logs/smart_monitor.log"
echo "停止监控: kill $PID"
