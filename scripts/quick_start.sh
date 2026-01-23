#!/bin/bash
# 实盘模拟交易系统 - 快速启动指南

echo "╔════════════════════════════════════════════════════════════╗"
echo "║         🚀 实盘模拟交易系统 - 快速启动                      ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# 检查当前目录
if [ ! -f "integrated_trading_system.py" ]; then
    echo "❌ 错误: 请在 quant-trade-bot 目录下运行此脚本"
    exit 1
fi

# 显示菜单
echo "请选择操作:"
echo ""
echo "  1️⃣  测试系统 (检查所有组件是否正常)"
echo "  2️⃣  扫描市场 (查看当前交易机会)"
echo "  3️⃣  启动实盘模拟 (开始自动交易)"
echo "  4️⃣  查看使用文档"
echo "  5️⃣  退出"
echo ""
echo -n "请输入选项 [1-5]: "
read choice

case $choice in
    1)
        echo ""
        echo "🧪 运行系统测试..."
        python3 test_system.py
        ;;
    2)
        echo ""
        echo "🔍 扫描市场机会..."
        python3 simple_enhanced_strategy.py
        ;;
    3)
        echo ""
        echo "⚠️  注意: 这将启动实盘模拟交易系统"
        echo "   - 初始资金: \$1000 (模拟)"
        echo "   - 每5分钟扫描一次市场"
        echo "   - 发现信号自动执行模拟交易"
        echo "   - 自动监控止损止盈"
        echo "   - 发送Telegram通知"
        echo ""
        echo -n "确认启动? [y/N]: "
        read confirm
        
        if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
            echo ""
            echo "🚀 启动实盘模拟交易系统..."
            echo "💡 按 Ctrl+C 停止系统"
            echo ""
            sleep 2
            python3 integrated_trading_system.py
        else
            echo "❌ 已取消"
        fi
        ;;
    4)
        echo ""
        echo "📚 使用文档:"
        echo "════════════════════════════════════════════════════════════"
        cat TRADING_SYSTEM_README.md
        ;;
    5)
        echo "👋 再见!"
        exit 0
        ;;
    *)
        echo "❌ 无效选项"
        exit 1
        ;;
esac

echo ""
