#!/bin/bash
# 启动实盘模拟交易系统

echo "╔════════════════════════════════════════════════════════════╗"
echo "║         🚀 实盘模拟交易系统 v2.0                            ║"
echo "║         增强版 - 支持杠杆 + 数据库 + Telegram               ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

cd "$(dirname "$0")"

echo "📋 系统功能:"
echo "  ✅ 自动扫描市场（每5分钟）"
echo "  ✅ 自动执行交易信号"
echo "  ✅ 3倍杠杆交易"
echo "  ✅ 自动止损止盈（每30秒检查）"
echo "  ✅ 数据库完整记录"
echo "  ✅ Telegram实时通知"
echo ""

echo "⚙️ 当前配置:"
echo "  💰 初始资金: \$1,000"
echo "  🔢 杠杆: 3x"
echo "  📊 监控品种: BTC/USDT, ETH/USDT, XMR/USDT, BNB/USDT, SOL/USDT"
echo "  ⚠️ 单笔风险: 2%"
echo "  🛡️ 止损: 3% | 🎯 止盈: 6%"
echo "  💾 数据库: paper_trading.db"
echo ""

echo -n "确认启动? [y/N]: "
read confirm

if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
    echo "❌ 已取消"
    exit 0
fi

echo ""
echo "🚀 启动中..."
echo "💡 按 Ctrl+C 停止系统"
echo "💡 另开终端运行: python3 view_trading_records.py 查看记录"
echo ""
sleep 2

python3 integrated_trading_system.py

echo ""
echo "✅ 系统已停止"
echo ""
