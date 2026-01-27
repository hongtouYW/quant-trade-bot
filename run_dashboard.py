#!/usr/bin/env python3

# 绝对路径启动脚本 - 解决所有路径问题
import os
import sys
import subprocess

# 确保在正确的目录
project_dir = "/Users/hongtou/newproject/quant-trade-bot"
dashboard_file = os.path.join(project_dir, "simple_dashboard.py")

print("🔧 路径问题最终解决方案")
print(f"📁 项目目录: {project_dir}")
print(f"📄 Dashboard文件: {dashboard_file}")

# 检查目录和文件
if not os.path.exists(project_dir):
    print(f"❌ 项目目录不存在: {project_dir}")
    sys.exit(1)

if not os.path.exists(dashboard_file):
    print(f"❌ Dashboard文件不存在: {dashboard_file}")
    sys.exit(1)

print("✅ 文件检查通过")

# 切换到项目目录
os.chdir(project_dir)
print(f"📍 切换工作目录到: {os.getcwd()}")

# 运行dashboard
print("🚀 启动Dashboard...")
try:
    subprocess.run([sys.executable, "simple_dashboard.py"], cwd=project_dir)
except KeyboardInterrupt:
    print("\n👋 Dashboard已停止")
except Exception as e:
    print(f"❌ 启动失败: {e}")