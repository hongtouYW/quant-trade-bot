#!/bin/bash
# 检查核心服务状态

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"
TRADING_PID="$PROJECT_DIR/logs/trading_system.pid"
XMR_PID="$PROJECT_DIR/xmr_monitor/xmr_monitor.pid"

echo "📊 核心服务状态检查"
echo "=" * 50

# 检查实盘模拟交易
echo "1️⃣  实盘模拟交易监控:"
if [ -f "$TRADING_PID" ] && kill -0 $(cat "$TRADING_PID") 2>/dev/null; then
    PID=$(cat "$TRADING_PID")
    UPTIME=$(ps -o etime= -p $PID | tr -d ' ')
    MEM=$(ps -o rss= -p $PID | awk '{printf "%.1f MB", $1/1024}')
    echo "   ✅ 运行中"
    echo "   📍 PID: $PID"
    echo "   ⏱️  运行时间: $UPTIME"
    echo "   💾 内存: $MEM"
else
    echo "   ❌ 未运行"
    [ -f "$TRADING_PID" ] && rm -f "$TRADING_PID"
fi

echo ""

# 检查XMR监控
echo "2️⃣  XMR定时更新:"
if [ -f "$XMR_PID" ] && kill -0 $(cat "$XMR_PID") 2>/dev/null; then
    PID=$(cat "$XMR_PID")
    UPTIME=$(ps -o etime= -p $PID | tr -d ' ')
    MEM=$(ps -o rss= -p $PID | awk '{printf "%.1f MB", $1/1024}')
    echo "   ✅ 运行中"
    echo "   📍 PID: $PID"
    echo "   ⏱️  运行时间: $UPTIME"
    echo "   💾 内存: $MEM"
else
    echo "   ❌ 未运行"
    [ -f "$XMR_PID" ] && rm -f "$XMR_PID"
fi

echo ""
echo "=" * 50

# 检查网络连接
echo "🌐 网络连接检查:"
if ping -c 1 8.8.8.8 > /dev/null 2>&1; then
    echo "   ✅ 网络正常"
else
    echo "   ⚠️  网络可能有问题"
fi

echo ""
echo "💡 启动服务: ./scripts/start_core_services.sh"
echo "🛑 停止服务: ./scripts/stop_core_services.sh"
