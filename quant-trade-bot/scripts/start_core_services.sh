#!/bin/bash
# 核心服务启动脚本 - 实盘模拟交易 + XMR监控

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"
cd "$PROJECT_DIR"

# PID文件
TRADING_PID="$PROJECT_DIR/logs/trading_system.pid"
XMR_PID="$PROJECT_DIR/xmr_monitor/xmr_monitor.pid"

# 日志文件
TRADING_LOG="$PROJECT_DIR/logs/trading_system.log"
XMR_LOG="$PROJECT_DIR/xmr_monitor/logs/xmr_monitor.log"

# 创建logs目录
mkdir -p "$PROJECT_DIR/logs"
mkdir -p "$PROJECT_DIR/xmr_monitor/logs"

echo "🚀 启动核心服务..."
echo "=" 50

# 1. 启动实盘模拟交易监控
echo "📊 启动实盘模拟交易监控..."
if [ -f "$TRADING_PID" ] && kill -0 $(cat "$TRADING_PID") 2>/dev/null; then
    echo "✅ 实盘模拟交易已在运行 (PID: $(cat $TRADING_PID))"
else
    nohup python3 "$PROJECT_DIR/src/core/integrated_trading_system.py" > "$TRADING_LOG" 2>&1 &
    echo $! > "$TRADING_PID"
    echo "✅ 实盘模拟交易已启动 (PID: $!)"
    echo "📋 日志: $TRADING_LOG"
fi

echo ""

# 2. 启动XMR定时更新
echo "💰 启动XMR定时更新..."
if [ -f "$XMR_PID" ] && kill -0 $(cat "$XMR_PID") 2>/dev/null; then
    echo "✅ XMR监控已在运行 (PID: $(cat $XMR_PID))"
else
    cd "$PROJECT_DIR/xmr_monitor"
    nohup python3 xmr_continuous_notify.py > "$XMR_LOG" 2>&1 &
    echo $! > "$XMR_PID"
    echo "✅ XMR监控已启动 (PID: $!)"
    echo "📋 日志: $XMR_LOG"
    cd "$PROJECT_DIR"
fi

echo ""
echo "=" * 50
echo "✅ 核心服务启动完成！"
echo ""
echo "📊 实盘模拟交易: $([ -f "$TRADING_PID" ] && echo "运行中 (PID: $(cat $TRADING_PID))" || echo "未运行")"
echo "💰 XMR监控: $([ -f "$XMR_PID" ] && echo "运行中 (PID: $(cat $XMR_PID))" || echo "未运行")"
echo ""
echo "💡 查看状态: ./scripts/check_core_services.sh"
echo "🛑 停止服务: ./scripts/stop_core_services.sh"
echo "📋 查看日志: tail -f $TRADING_LOG"
echo "📋 查看日志: tail -f $XMR_LOG"
