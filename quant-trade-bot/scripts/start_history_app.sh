#!/bin/bash

# 启动交易历史分析服务的脚本

echo "🚀 启动交易历史分析服务..."

# 切换到正确的目录
cd /Users/hongtou/newproject/quant-trade-bot

# 检查必要文件是否存在
if [ ! -f "trading_history_app.py" ]; then
    echo "❌ 错误：trading_history_app.py 文件不存在"
    exit 1
fi

if [ ! -f "latest_analysis.json" ]; then
    echo "⚠️  警告：latest_analysis.json 文件不存在"
fi

if [ ! -f "latest_trades.json" ]; then
    echo "⚠️  警告：latest_trades.json 文件不存在"
fi

# 杀死可能已经运行的进程
lsof -ti:5002 | xargs kill -9 2>/dev/null || true

echo "📊 启动服务在端口 5002..."
echo "🌐 访问 http://localhost:5002 查看策略分析"

# 启动应用
exec python3 trading_history_app.py