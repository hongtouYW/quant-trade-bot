#!/bin/bash

# 启动增强版监控面板的脚本

echo "🚀 启动增强版量化交易监控面板..."

# 切换到正确的目录
cd /Users/hongtou/newproject/quant-trade-bot

# 检查必要文件是否存在
if [ ! -f "simple_dashboard_enhanced.py" ]; then
    echo "❌ 错误：simple_dashboard_enhanced.py 文件不存在"
    exit 1
fi

# 杀死可能已经运行的进程
lsof -ti:5001 | xargs kill -9 2>/dev/null || true

echo "📊 启动服务在端口 5001..."
echo "🌐 访问 http://localhost:5001 查看完整监控面板"

# 启动应用
exec python3 simple_dashboard_enhanced.py