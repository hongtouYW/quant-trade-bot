#!/usr/bin/env python3
"""
Update paper_trader.py to use new database tables
"""

file_path = "/opt/trading-bot/quant-trade-bot/xmr_monitor/paper_trader.py"

with open(file_path, "r") as f:
    content = f.read()

# 1. Add method to record account snapshot
snapshot_method = '''
    def record_snapshot(self):
        """记录账户快照"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            positions_value = sum(pos.get('amount', 0) for pos in self.positions.values())
            unrealized_pnl = 0

            cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status='CLOSED'")
            result = cursor.fetchone()
            realized_pnl = result[0] if result[0] else 0

            balance = self.initial_capital + realized_pnl
            equity = balance + unrealized_pnl

            cursor.execute("SELECT MAX(equity) FROM account_snapshots")
            result = cursor.fetchone()
            peak = result[0] if result[0] else equity
            max_drawdown = (peak - equity) / peak * 100 if peak > 0 else 0

            cursor.execute("INSERT INTO account_snapshots (balance, equity, positions_value, unrealized_pnl, realized_pnl, max_drawdown, positions_count) VALUES (?, ?, ?, ?, ?, ?, ?)",
                (balance, equity, positions_value, unrealized_pnl, realized_pnl, max_drawdown, len(self.positions)))

            conn.commit()
            conn.close()
        except Exception as e:
            print(f"⚠️ 快照记录失败: {e}")

    def record_signal(self, symbol, signal_type, score, price, atr_pct=None, executed=False, reason=None):
        """记录信号历史"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            cursor.execute("INSERT INTO signal_history (symbol, signal_type, score, price, atr_pct, executed, reason) VALUES (?, ?, ?, ?, ?, ?, ?)",
                (symbol, signal_type, score, price, atr_pct, 1 if executed else 0, reason))
            conn.commit()
            conn.close()
        except Exception as e:
            print(f"⚠️ 信号记录失败: {e}")

    def update_daily_stats(self):
        """更新每日统计"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            today = datetime.now().strftime('%Y-%m-%d')

            cursor.execute("SELECT COUNT(*), SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END), SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END), SUM(pnl), SUM(fee), MAX(pnl), MIN(pnl) FROM real_trades WHERE DATE(exit_time) = ? AND status = 'CLOSED'", (today,))
            row = cursor.fetchone()

            if row and row[0] > 0:
                trades, wins, losses, total_pnl, total_fee, max_win, max_loss = row
                win_rate = wins / trades * 100 if trades > 0 else 0
                cursor.execute("INSERT OR REPLACE INTO daily_stats (date, trades_count, win_count, loss_count, total_pnl, total_fee, win_rate, max_win, max_loss) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    (today, trades, wins or 0, losses or 0, total_pnl or 0, total_fee or 0, win_rate, max_win or 0, max_loss or 0))
                conn.commit()

            conn.close()
        except Exception as e:
            print(f"⚠️ 每日统计更新失败: {e}")

    def get_config(self, key, default=None):
        """从配置表获取配置"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            cursor.execute("SELECT value FROM config WHERE key = ?", (key,))
            result = cursor.fetchone()
            conn.close()
            return result[0] if result else default
        except:
            return default
'''

# Find position to insert - after calculate_atr method
insert_marker = "    def open_position(self, symbol, analysis):"
if insert_marker in content and "def record_snapshot" not in content:
    content = content.replace(insert_marker, snapshot_method + "\n" + insert_marker)
    print("✅ 添加 record_snapshot, record_signal, update_daily_stats, get_config 方法")
else:
    if "def record_snapshot" in content:
        print("⏭️ 这些方法已存在")
    else:
        print("❌ 找不到插入位置")

# Write back
with open(file_path, "w") as f:
    f.write(content)

print("\n🎉 paper_trader.py 更新完成!")
