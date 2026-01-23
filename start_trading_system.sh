#!/bin/bash

# 启动集成交易系统

echo "🚀 启动集成交易系统..."

# 进入项目目录
cd "$(dirname "$0")"

# 检查Python
if ! command -v python3 &> /dev/null; then
    echo "❌ 未找到 python3"
    exit 1
fi

# 检查依赖
echo "📦 检查依赖..."
python3 -c "import ccxt, pandas, talib" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "⚠️ 缺少依赖，正在安装..."
    pip3 install ccxt pandas ta-lib numpy requests
fi

# 运行系统
echo "▶️ 启动交易系统..."
python3 integrated_trading_system.py

echo ""
echo "✅ 系统已停止"
