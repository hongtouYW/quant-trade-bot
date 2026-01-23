#!/bin/bash
# XMR监控主服务脚本

SCRIPT_DIR="/Users/hongtou/newproject/quant-trade-bot/xmr_monitor"
PYTHON_SCRIPT="xmr_continuous_notify.py"
PID_FILE="${SCRIPT_DIR}/xmr_monitor.pid"
LOG_FILE="${SCRIPT_DIR}/logs/xmr_monitor.log"

cd "$SCRIPT_DIR"

case "$1" in
    start)
        if [ -f "$PID_FILE" ] && kill -0 `cat "$PID_FILE"` 2>/dev/null; then
            echo "❌ XMR监控已经在运行中 (PID: `cat $PID_FILE`)"
            exit 1
        fi
        
        echo "🚀 启动XMR监控服务..."
        echo "📂 工作目录: $SCRIPT_DIR"
        nohup python3 "$PYTHON_SCRIPT" > "$LOG_FILE" 2>&1 &
        echo $! > "$PID_FILE"
        echo "✅ XMR监控已启动 (PID: $!)"
        echo "📋 日志文件: $LOG_FILE"
        ;;
    
    stop)
        if [ -f "$PID_FILE" ]; then
            PID=`cat "$PID_FILE"`
            if kill -0 "$PID" 2>/dev/null; then
                echo "⏹️ 停止XMR监控服务..."
                kill "$PID"
                rm -f "$PID_FILE"
                echo "✅ XMR监控已停止"
            else
                echo "❌ XMR监控进程不存在"
                rm -f "$PID_FILE"
            fi
        else
            echo "❌ XMR监控未运行"
        fi
        ;;
    
    restart)
        $0 stop
        sleep 2
        $0 start
        ;;
    
    status)
        if [ -f "$PID_FILE" ] && kill -0 `cat "$PID_FILE"` 2>/dev/null; then
            PID=`cat "$PID_FILE"`
            echo "✅ XMR监控正在运行 (PID: $PID)"
            echo "📂 工作目录: $SCRIPT_DIR"
            echo "📋 日志文件: $LOG_FILE"
            echo "💾 PID文件: $PID_FILE"
        else
            echo "❌ XMR监控未运行"
        fi
        ;;
    
    log)
        if [ -f "$LOG_FILE" ]; then
            echo "📋 查看最新日志 (按 Ctrl+C 退出):"
            tail -f "$LOG_FILE"
        else
            echo "❌ 日志文件不存在"
        fi
        ;;
    
    logshow)
        if [ -f "$LOG_FILE" ]; then
            echo "📋 显示最近50行日志:"
            tail -n 50 "$LOG_FILE"
        else
            echo "❌ 日志文件不存在"
        fi
        ;;
    
    *)
        echo "🎯 XMR监控服务管理器"
        echo "使用方法: $0 {start|stop|restart|status|log|logshow}"
        echo ""
        echo "命令说明:"
        echo "  start    - 启动监控服务"
        echo "  stop     - 停止监控服务"
        echo "  restart  - 重启监控服务"
        echo "  status   - 查看运行状态"
        echo "  log      - 实时查看日志"
        echo "  logshow  - 显示最近日志"
        echo ""
        echo "文件位置:"
        echo "  📂 脚本目录: $SCRIPT_DIR"
        echo "  📋 日志文件: $LOG_FILE"
        exit 1
        ;;
esac

exit 0