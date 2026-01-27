#!/bin/bash
# 多币种监控启动脚本

cd /Users/hongtou/newproject/quant-trade-bot/xmr_monitor

# 停止旧进程
pkill -f multi_coin_monitor

# 等待进程完全停止
sleep 2

# 启动新进程
nohup python3 -u /Users/hongtou/newproject/quant-trade-bot/xmr_monitor/multi_coin_monitor.py > logs/multi_coin.log 2>&1 &

# 获取PID
PID=$!
echo "✅ 多币种监控已启动"
echo "📊 PID: $PID"
echo "📝 日志: logs/multi_coin.log"
echo ""
echo "查看日志: tail -f logs/multi_coin.log"
echo "停止监控: pkill -f multi_coin_monitor"
