#!/bin/bash
# XMR持仓监控 - 后台运行

cd /Users/hongtou/newproject/quant-trade-bot

echo "🚀 启动 XMR 持仓监控..."
echo "========================================"

nohup python3 -u monitor_xmr_position.py > logs/xmr_position_monitor.log 2>&1 &
PID=$!
echo "✅ 监控已启动 (PID: $PID)"
echo "日志文件: logs/xmr_position_monitor.log"
echo ""
echo "查看日志: tail -f logs/xmr_position_monitor.log"
echo "停止监控: kill $PID"
