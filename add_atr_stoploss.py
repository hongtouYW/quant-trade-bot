#!/usr/bin/env python3
"""
Add ATR Dynamic Stop Loss to paper_trader.py
v0.5 Feature: ATR-based stop loss instead of fixed percentage
"""

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/paper_trader.py"

with open(file_path, "r") as f:
    content = f.read()

# 1. Add ATR calculation function after imports
atr_function = '''
    def calculate_atr(self, symbol, period=14):
        """计算ATR (Average True Range) - 衡量波动性"""
        try:
            # 获取K线数据
            url = f"https://fapi.binance.com/fapi/v1/klines?symbol={symbol}USDT&interval=1h&limit={period + 1}"
            response = requests.get(url, timeout=10)
            klines = response.json()

            if len(klines) < period + 1:
                return None

            true_ranges = []
            for i in range(1, len(klines)):
                high = float(klines[i][2])
                low = float(klines[i][3])
                prev_close = float(klines[i-1][4])

                # True Range = max(H-L, |H-PC|, |L-PC|)
                tr = max(high - low, abs(high - prev_close), abs(low - prev_close))
                true_ranges.append(tr)

            # ATR = 平均 True Range
            atr = sum(true_ranges[-period:]) / period

            # 计算ATR百分比 (相对于当前价格)
            current_price = float(klines[-1][4])
            atr_pct = (atr / current_price) * 100

            return {
                'atr': atr,
                'atr_pct': atr_pct,
                'price': current_price
            }
        except Exception as e:
            print(f"⚠️ {symbol} ATR计算失败: {e}")
            return None

    def get_dynamic_stop_pct(self, symbol):
        """根据ATR获取动态止损百分比"""
        atr_data = self.calculate_atr(symbol)

        if atr_data is None:
            # 默认1.5%
            return 0.015, "默认"

        atr_pct = atr_data['atr_pct']

        # ATR止损倍数: 1.5x ATR
        # 但限制在 1% ~ 3% 之间
        stop_pct = min(max(atr_pct * 1.5 / 100, 0.01), 0.03)

        volatility = "低" if atr_pct < 1.5 else ("高" if atr_pct > 3 else "中")

        print(f"📊 {symbol} ATR: {atr_pct:.2f}% | 波动: {volatility} | 止损: {stop_pct*100:.1f}%")

        return stop_pct, volatility
'''

# Find the position after load_positions method to insert ATR function
# Look for "def open_position" and insert before it
old_open_position_start = "    def open_position(self, symbol, analysis):"
new_open_position_start = atr_function + "\n" + old_open_position_start

if old_open_position_start in content and "def calculate_atr" not in content:
    content = content.replace(old_open_position_start, new_open_position_start)
    print("✅ Added ATR calculation function")
else:
    if "def calculate_atr" in content:
        print("⏭️ ATR function already exists")
    else:
        print("❌ Could not find open_position to insert ATR function")

# 2. Modify open_position to use ATR-based stop loss
old_stop_loss_long = '''                stop_loss = entry_price * 0.985  # -1.5%'''
new_stop_loss_long = '''                # ATR动态止损
                stop_pct, volatility = self.get_dynamic_stop_pct(symbol)
                stop_loss = entry_price * (1 - stop_pct)'''

if old_stop_loss_long in content:
    content = content.replace(old_stop_loss_long, new_stop_loss_long)
    print("✅ Modified LONG stop loss to use ATR")
else:
    print("⏭️ LONG stop loss already modified or not found")

old_stop_loss_short = '''                stop_loss = entry_price * 1.015  # +1.5%'''
new_stop_loss_short = '''                # ATR动态止损 (SHORT方向)
                if 'stop_pct' not in dir():
                    stop_pct, volatility = self.get_dynamic_stop_pct(symbol)
                stop_loss = entry_price * (1 + stop_pct)'''

if old_stop_loss_short in content:
    content = content.replace(old_stop_loss_short, new_stop_loss_short)
    print("✅ Modified SHORT stop loss to use ATR")
else:
    print("⏭️ SHORT stop loss already modified or not found")

# 3. Modify trailing stop to use ATR
old_trailing = '''            trailing_pct = 0.015  # 移动止损距离 1.5%'''
new_trailing = '''            # ATR动态移动止损距离
            atr_data = self.calculate_atr(symbol)
            if atr_data:
                trailing_pct = min(max(atr_data['atr_pct'] * 1.5 / 100, 0.01), 0.03)
            else:
                trailing_pct = 0.015  # 默认1.5%'''

if old_trailing in content:
    content = content.replace(old_trailing, new_trailing)
    print("✅ Modified trailing stop to use ATR")
else:
    print("⏭️ Trailing stop already modified or not found")

# Write back
with open(file_path, "w") as f:
    f.write(content)

print("\n🎉 ATR动态止损功能添加完成!")
print("波动大的币 → 止损宽 (最高3%)")
print("波动小的币 → 止损紧 (最低1%)")
