#!/usr/bin/env python3
"""
统一量化交易面板启动脚本
整合实时监控和交易历史功能
"""

import os
import sys
import subprocess

def main():
    print("🚀 启动统一量化交易面板")
    print("=" * 50)
    
    # 项目目录
    project_dir = "/Users/hongtou/newproject/quant-trade-bot"
    unified_app = "/Users/hongtou/newproject/quant-trade-bot/unified_dashboard.py"
    
    print(f"📁 项目目录: {project_dir}")
    print(f"📄 应用文件: {unified_app}")
    
    # 检查文件是否存在
    if not os.path.exists(unified_app):
        print(f"❌ 应用文件不存在: {unified_app}")
        return False
    
    print("✅ 文件检查通过")
    print(f"📍 切换工作目录到: {project_dir}")
    
    # 切换工作目录
    os.chdir(project_dir)
    
    print("🛑 清理端口...")
    # 清理可能占用的端口
    for port in [5010, 5001, 5002]:
        try:
            subprocess.run(f"lsof -ti:{port} | xargs kill -9 2>/dev/null || echo '端口{port}清理完成'", 
                         shell=True, capture_output=True)
        except:
            pass
    
    print("🚀 启动统一面板...")
    print("📊 功能包括:")
    print("   • 实时监控 (首页)")
    print("   • 交易历史 (底部导航)")
    print("   • 策略分析")
    print("📱 访问 http://localhost:5010")
    print("-" * 50)
    
    try:
        # 启动应用
        subprocess.run([sys.executable, "unified_dashboard.py"], check=True)
    except KeyboardInterrupt:
        print("\n👋 应用已停止")
    except Exception as e:
        print(f"❌ 启动失败: {e}")
        return False
    
    return True

if __name__ == "__main__":
    main()