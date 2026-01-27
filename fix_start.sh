#!/bin/bash

# 完全解决路径问题的启动脚本
set -e  # 遇到错误立即退出

echo "🔧 修复路径问题并启动量化交易系统..."

# 定义绝对路径
BASE_DIR="/Users/hongtou/newproject"
PROJECT_DIR="/Users/hongtou/newproject/quant-trade-bot"

# 检查目录结构
echo "📁 检查目录结构..."
if [ ! -d "$BASE_DIR" ]; then
    echo "❌ 基础目录不存在: $BASE_DIR"
    exit 1
fi

if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ 项目目录不存在: $PROJECT_DIR"
    exit 1
fi

# 列出文件确认
echo "📋 项目文件列表:"
ls -la "$PROJECT_DIR"/*.py | head -5

# 强制清理端口
echo "🧹 清理所有相关端口..."
for port in 5000 5001 5002; do
    lsof -ti:$port | xargs kill -9 2>/dev/null || true
done

# 切换到项目目录并启动
echo "🚀 切换到项目目录: $PROJECT_DIR"
cd "$PROJECT_DIR"

echo "📍 当前工作目录: $(pwd)"
echo "✅ Python文件检查: $(ls simple_dashboard.py 2>/dev/null && echo '存在' || echo '不存在')"

# 直接在项目目录中启动
echo "🎯 启动监控面板..."
exec python3 simple_dashboard.py