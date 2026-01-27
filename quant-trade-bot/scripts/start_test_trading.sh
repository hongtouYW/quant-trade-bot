#!/bin/bash

# 🚀 启动模拟交易测试（1周）
# 策略: 日线趋势 + 15分钟入场 + 5分钟风控

clear
echo "========================================"
echo "  📊 启动模拟交易系统测试"
echo "========================================"
echo ""
echo "⏰ 策略时间框架:"
echo "   - 趋势判断: 日线 (1d)"
echo "   - 入场信号: 15分钟 (15m)"
echo "   - 风险控制: 5分钟 (5m)"
echo ""
echo "🔄 运行间隔:"
echo "   - 扫描新机会: 每5分钟"
echo "   - 检查持仓: 每30秒"
echo ""
echo "💰 初始资金: $1000"
echo "📈 杠杆倍数: 3x"
echo ""
echo "========================================"
echo ""

# 检查是否有config.json
if [ ! -f "config.json" ]; then
    echo "⚠️  未找到 config.json 配置文件"
    echo ""
    echo "请先创建配置文件："
    echo "  cp config.json.example config.json"
    echo "  nano config.json  # 填写你的API密钥"
    echo ""
    exit 1
fi

echo "✅ 配置文件检查通过"
echo ""

# 询问是否启动
read -p "是否启动模拟交易系统？(yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消"
    exit 0
fi

echo ""
echo "🚀 正在启动..."
echo ""

# 启动集成交易系统
cd "$(dirname "$0")"
python3 integrated_trading_system.py 2>&1 | tee -a trading_test.log &

PID=$!
echo "✅ 系统已启动 (PID: $PID)"
echo ""
echo "📝 日志文件: trading_test.log"
echo ""
echo "常用命令:"
echo "  查看实时日志:  tail -f trading_test.log"
echo "  查看交易记录:  python3 view_trading_records.py"
echo "  停止系统:      kill $PID"
echo "  查看Web界面:   http://localhost:5001"
echo ""
echo "💡 建议: 让系统运行1周，然后分析交易数据"
echo ""
