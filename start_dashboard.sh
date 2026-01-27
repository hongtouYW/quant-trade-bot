#!/bin/bash

# 绝对路径启动脚本 - 解决工作目录问题

PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"
PYTHON_FILE="$PROJECT_DIR/simple_dashboard_enhanced.py"

echo "🔧 检查项目环境..."
echo "📁 项目目录: $PROJECT_DIR"

# 检查项目目录
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ 错误：项目目录不存在: $PROJECT_DIR"
    exit 1
fi

# 检查Python文件
if [ ! -f "$PYTHON_FILE" ]; then
    echo "❌ 错误：Python文件不存在: $PYTHON_FILE"
    exit 1
fi

# 检查数据文件
if [ ! -f "$PROJECT_DIR/latest_analysis.json" ]; then
    echo "⚠️  警告：策略分析数据不存在，历史记录功能可能受限"
fi

if [ ! -f "$PROJECT_DIR/latest_trades.json" ]; then
    echo "⚠️  警告：交易历史数据不存在，历史记录功能可能受限"
fi

# 清理可能占用的端口
echo "🧹 清理端口5001..."
lsof -ti:5001 | xargs kill -9 2>/dev/null || true

# 切换到项目目录并启动
echo "🚀 启动量化交易监控面板..."
echo "📊 面板地址: http://localhost:5001"
echo "⏰ 启动时间: $(date)"

cd "$PROJECT_DIR" && exec python3 simple_dashboard_enhanced.py