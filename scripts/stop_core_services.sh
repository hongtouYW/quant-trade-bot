#!/bin/bash
# 停止核心服务

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"
TRADING_PID="$PROJECT_DIR/logs/trading_system.pid"
XMR_PID="$PROJECT_DIR/xmr_monitor/xmr_monitor.pid"

echo "🛑 停止核心服务..."
echo "=" * 50

# 停止实盘模拟交易
echo "1️⃣  停止实盘模拟交易监控..."
if [ -f "$TRADING_PID" ]; then
    PID=$(cat "$TRADING_PID")
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID"
        sleep 2
        if kill -0 "$PID" 2>/dev/null; then
            echo "   ⚠️  进程未响应，强制停止..."
            kill -9 "$PID"
        fi
        echo "   ✅ 已停止 (PID: $PID)"
    else
        echo "   ⚠️  进程不存在"
    fi
    rm -f "$TRADING_PID"
else
    echo "   ℹ️  未在运行"
fi

echo ""

# 停止XMR监控
echo "2️⃣  停止XMR定时更新..."
if [ -f "$XMR_PID" ]; then
    PID=$(cat "$XMR_PID")
    if kill -0 "$PID" 2>/dev/null; then
        kill "$PID"
        sleep 2
        if kill -0 "$PID" 2>/dev/null; then
            echo "   ⚠️  进程未响应，强制停止..."
            kill -9 "$PID"
        fi
        echo "   ✅ 已停止 (PID: $PID)"
    else
        echo "   ⚠️  进程不存在"
    fi
    rm -f "$XMR_PID"
else
    echo "   ℹ️  未在运行"
fi

echo ""
echo "=" * 50
echo "✅ 核心服务已停止"
