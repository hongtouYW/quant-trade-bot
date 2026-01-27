#!/bin/bash
# 重启核心服务

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"

echo "🔄 重启核心服务..."
echo ""

# 停止服务
bash "$PROJECT_DIR/scripts/stop_core_services.sh"

echo ""
echo "⏳ 等待3秒..."
sleep 3
echo ""

# 启动服务
bash "$PROJECT_DIR/scripts/start_core_services.sh"
