#!/bin/bash
# 智能交易监控启动脚本 (2合1)
# - 有持仓时监控盈亏
# - 无持仓时扫描买入信号

cd "$(dirname "$0")"

echo "🚀 启动智能交易监控 (2合1)..."

# 停止旧的监控进程
pkill -f "xmr_simple_telegram.py"
pkill -f "smart_position_monitor.py"
pkill -f "smart_trading_monitor.py"
pkill -f "xmr_memes_monitor.py"

sleep 1

# 启动新的智能监控
nohup python3 smart_trading_monitor.py > logs/smart_trading_monitor.log 2>&1 &

PID=$!
echo "✅ 智能交易监控已启动 (PID: $PID)"
echo ""
echo "📋 功能:"
echo "   - 有持仓时: 监控盈亏，发送持仓更新"
echo "   - 无持仓时: 扫描买入信号，发现机会通知"
echo ""
echo "📋 日志: tail -f logs/smart_trading_monitor.log"
echo "⏹️  停止: kill $PID"
