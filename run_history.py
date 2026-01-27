#!/usr/bin/env python3

# 启动历史记录应用
import os
import sys
import subprocess

# 确保在正确的目录
project_dir = "/Users/hongtou/newproject/quant-trade-bot"
history_file = os.path.join(project_dir, "trading_history_app.py")

print("📊 启动历史记录分析应用")
print(f"📁 项目目录: {project_dir}")
print(f"📄 历史应用文件: {history_file}")

# 检查目录和文件
if not os.path.exists(project_dir):
    print(f"❌ 项目目录不存在: {project_dir}")
    sys.exit(1)

if not os.path.exists(history_file):
    print(f"❌ 历史应用文件不存在: {history_file}")
    sys.exit(1)

print("✅ 文件检查通过")

# 切换到项目目录
os.chdir(project_dir)
print(f"📍 切换工作目录到: {os.getcwd()}")

# 运行历史记录应用
print("🚀 启动历史记录分析应用...")
print("📊 访问 http://localhost:5002 查看交易历史")
try:
    subprocess.run([sys.executable, "trading_history_app.py"], cwd=project_dir)
except KeyboardInterrupt:
    print("\n👋 历史记录应用已停止")
except Exception as e:
    print(f"❌ 启动失败: {e}")