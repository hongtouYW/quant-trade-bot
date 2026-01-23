#!/bin/bash

echo "🔍 XMR监控状态检查"
echo "==================="

# 检查进程
if pgrep -f "xmr_daemon_simple.py" > /dev/null; then
    echo "✅ XMR监控进程正在运行"
    PID=$(pgrep -f "xmr_daemon_simple.py")
    echo "📝 进程ID: $PID"
    
    # 检查运行时间
    ps -o pid,etime,command -p $PID | tail -1
else
    echo "❌ XMR监控进程未运行"
fi

echo ""

# 检查日志文件
if [ -f "logs/xmr_daemon.log" ]; then
    echo "📋 日志文件大小: $(wc -c < logs/xmr_daemon.log) bytes"
    echo "📅 最后修改: $(stat -f %Sm logs/xmr_daemon.log)"
else
    echo "❌ 找不到日志文件"
fi

echo ""

# 检查网络连接
if curl -s --max-time 5 https://api.binance.com/api/v3/ping > /dev/null; then
    echo "🌐 Binance API 连接正常"
    
    # 获取当前XMR价格
    PRICE=$(curl -s "https://api.binance.com/api/v3/ticker/price?symbol=XMRUSDT" | grep -o '"price":"[^"]*"' | cut -d'"' -f4)
    if [ ! -z "$PRICE" ]; then
        echo "💰 当前 XMR 价格: $PRICE USDT"
    fi
else
    echo "❌ 无法连接到 Binance API"
fi

echo ""
echo "💡 停止监控: pkill -f xmr_daemon_simple.py"
echo "📋 查看日志: tail -f logs/xmr_daemon.log"