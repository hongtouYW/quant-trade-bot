#!/bin/bash
# 启动Web监控面板

echo "🌐 启动实盘模拟交易Web监控..."
echo ""

cd "$(dirname "$0")"

# 检查Flask
python3 -c "import flask" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "📦 安装Flask..."
    pip3 install flask
fi

echo "✅ 启动Web服务器..."
echo "📊 访问地址: http://localhost:5001"
echo "💡 按 Ctrl+C 停止服务器"
echo ""

python3 web_monitor.py
