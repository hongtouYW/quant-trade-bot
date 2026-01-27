#!/bin/bash
# XMR & MEMES (BSC) 监督器 - 后台运行

cd /Users/hongtou/newproject/quant-trade-bot

echo "🚀 启动 XMR & MEMES (BSC) 监督器..."
echo "监控币种: XMR/USDT (Binance期货), MEMES (BSC链)"
echo "检查间隔: 10分钟"
echo "按 Ctrl+C 停止监控"
echo "========================================"

while true; do
    echo ""
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 执行监控检查..."
    python3 xmr_memes_bsc_monitor.py
    echo ""
    echo "下次检查: $(date -v+10M '+%Y-%m-%d %H:%M:%S')"
    echo "----------------------------------------"
    sleep 600  # 10分钟 = 600秒
done
