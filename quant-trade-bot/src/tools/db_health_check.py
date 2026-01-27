#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
数据库健康检查工具
验证所有交易记录完整性
"""

import sqlite3
import sys
import os
from datetime import datetime
from tabulate import tabulate

# 添加项目路径
PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, PROJECT_ROOT)


class DatabaseHealthChecker:
    """数据库健康检查器"""
    
    def __init__(self, db_path='data/db/paper_trading.db'):
        self.db_path = os.path.join(PROJECT_ROOT, db_path)
        
        if not os.path.exists(self.db_path):
            print(f"❌ 数据库不存在: {self.db_path}")
            sys.exit(1)
    
    def check_all(self):
        """执行所有检查"""
        print("\n" + "="*70)
        print("🔍 数据库健康检查")
        print("="*70)
        print(f"📁 数据库: {self.db_path}")
        print(f"🕐 检查时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print("="*70)
        
        self.check_trades_table()
        self.check_positions_table()
        self.check_stats_table()
        self.check_data_integrity()
        self.display_summary()
        
        print("\n✅ 健康检查完成")
    
    def check_trades_table(self):
        """检查交易表"""
        print("\n📊 检查交易表 (trades)")
        print("-" * 70)
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 总交易数
        cursor.execute("SELECT COUNT(*) FROM trades")
        total_trades = cursor.fetchone()[0]
        
        # 买卖统计
        cursor.execute("SELECT side, COUNT(*) FROM trades GROUP BY side")
        side_stats = dict(cursor.fetchall())
        
        # 按币种统计
        cursor.execute("SELECT symbol, COUNT(*) FROM trades GROUP BY symbol")
        symbol_stats = cursor.fetchall()
        
        # 盈亏统计
        cursor.execute("""
            SELECT 
                COUNT(CASE WHEN pnl > 0 THEN 1 END) as wins,
                COUNT(CASE WHEN pnl < 0 THEN 1 END) as losses,
                SUM(pnl) as total_pnl,
                SUM(fee) as total_fees
            FROM trades WHERE side = 'sell'
        """)
        pnl_stats = cursor.fetchone()
        
        print(f"  总交易数: {total_trades}")
        print(f"  买入: {side_stats.get('buy', 0)}")
        print(f"  卖出: {side_stats.get('sell', 0)}")
        
        if pnl_stats[0] or pnl_stats[1]:
            win_rate = (pnl_stats[0] / (pnl_stats[0] + pnl_stats[1])) * 100
            print(f"  盈利交易: {pnl_stats[0]}")
            print(f"  亏损交易: {pnl_stats[1]}")
            print(f"  胜率: {win_rate:.1f}%")
            print(f"  总盈亏: ${pnl_stats[2]:+,.2f}")
            print(f"  总手续费: ${pnl_stats[3]:,.2f}")
        
        if symbol_stats:
            print("\n  按币种统计:")
            for symbol, count in symbol_stats:
                print(f"    {symbol}: {count} 笔")
        
        # 检查数据完整性
        cursor.execute("SELECT COUNT(*) FROM trades WHERE price IS NULL OR quantity IS NULL")
        null_count = cursor.fetchone()[0]
        
        if null_count > 0:
            print(f"\n  ⚠️ 警告: {null_count} 条记录有缺失数据")
        else:
            print(f"\n  ✅ 数据完整性: 良好")
        
        conn.close()
    
    def check_positions_table(self):
        """检查持仓表"""
        print("\n💼 检查持仓表 (positions)")
        print("-" * 70)
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 总持仓数
        cursor.execute("SELECT COUNT(*) FROM positions")
        total_positions = cursor.fetchone()[0]
        
        # 开仓/平仓统计
        cursor.execute("SELECT status, COUNT(*) FROM positions GROUP BY status")
        status_stats = dict(cursor.fetchall())
        
        # 当前持仓
        cursor.execute("""
            SELECT symbol, quantity, entry_price, leverage, stop_loss, take_profit
            FROM positions WHERE status = 'open'
        """)
        open_positions = cursor.fetchall()
        
        print(f"  总持仓记录: {total_positions}")
        print(f"  开仓中: {status_stats.get('open', 0)}")
        print(f"  已平仓: {status_stats.get('closed', 0)}")
        
        if open_positions:
            print("\n  当前持仓:")
            headers = ['币种', '数量', '入场价', '杠杆', '止损', '止盈']
            table_data = [
                [p[0], f"{p[1]:.6f}", f"${p[2]:.2f}", f"{p[3]}x", 
                 f"${p[4]:.2f}", f"${p[5]:.2f}"]
                for p in open_positions
            ]
            print(tabulate(table_data, headers=headers, tablefmt='simple'))
        
        conn.close()
    
    def check_stats_table(self):
        """检查统计表"""
        print("\n📈 检查统计表 (stats)")
        print("-" * 70)
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 总记录数
        cursor.execute("SELECT COUNT(*) FROM stats")
        total_stats = cursor.fetchone()[0]
        
        # 最新统计
        cursor.execute("""
            SELECT timestamp, balance, total_pnl, total_trades, win_rate, total_fees
            FROM stats ORDER BY timestamp DESC LIMIT 1
        """)
        latest_stats = cursor.fetchone()
        
        print(f"  统计记录数: {total_stats}")
        
        if latest_stats:
            print(f"\n  最新统计 ({latest_stats[0][:19]}):")
            print(f"    余额: ${latest_stats[1]:,.2f}")
            print(f"    总盈亏: ${latest_stats[2]:+,.2f}")
            print(f"    总交易: {latest_stats[3]}")
            print(f"    胜率: {latest_stats[4]:.1f}%")
            print(f"    总手续费: ${latest_stats[5]:,.2f}")
        
        conn.close()
    
    def check_data_integrity(self):
        """检查数据完整性"""
        print("\n🔐 数据完整性检查")
        print("-" * 70)
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        issues = []
        
        # 1. 检查买卖配对
        cursor.execute("SELECT side, COUNT(*) FROM trades GROUP BY side")
        side_counts = dict(cursor.fetchall())
        buy_count = side_counts.get('buy', 0)
        sell_count = side_counts.get('sell', 0)
        
        print(f"  买入交易: {buy_count}")
        print(f"  卖出交易: {sell_count}")
        
        if buy_count < sell_count:
            issues.append(f"卖出多于买入 ({sell_count} > {buy_count})")
        elif buy_count > sell_count:
            print(f"  ℹ️ 有 {buy_count - sell_count} 个未平仓位")
        
        # 2. 检查持仓与交易的一致性
        cursor.execute("SELECT COUNT(*) FROM positions WHERE status = 'open'")
        open_positions = cursor.fetchone()[0]
        
        unpaired_trades = buy_count - sell_count
        if unpaired_trades != open_positions:
            issues.append(f"持仓数({open_positions})与未配对交易数({unpaired_trades})不一致")
        
        # 3. 检查是否有负余额记录
        cursor.execute("SELECT COUNT(*) FROM trades WHERE balance_after < 0")
        negative_balance = cursor.fetchone()[0]
        
        if negative_balance > 0:
            issues.append(f"存在 {negative_balance} 条负余额记录")
        
        # 4. 检查时间顺序
        cursor.execute("""
            SELECT COUNT(*) FROM trades t1
            WHERE EXISTS (
                SELECT 1 FROM trades t2 
                WHERE t2.id < t1.id AND t2.timestamp > t1.timestamp
            )
        """)
        time_issues = cursor.fetchone()[0]
        
        if time_issues > 0:
            issues.append(f"存在 {time_issues} 条时间顺序异常记录")
        
        conn.close()
        
        if issues:
            print("\n  ⚠️ 发现以下问题:")
            for issue in issues:
                print(f"    - {issue}")
        else:
            print("\n  ✅ 数据完整性: 优秀")
    
    def display_summary(self):
        """显示汇总信息"""
        print("\n" + "="*70)
        print("📋 数据库摘要")
        print("="*70)
        
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 获取文件大小
        file_size = os.path.getsize(self.db_path) / 1024  # KB
        
        # 获取各表记录数
        cursor.execute("SELECT COUNT(*) FROM trades")
        trades_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM positions")
        positions_count = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM stats")
        stats_count = cursor.fetchone()[0]
        
        print(f"  文件大小: {file_size:.2f} KB")
        print(f"  交易记录: {trades_count}")
        print(f"  持仓记录: {positions_count}")
        print(f"  统计记录: {stats_count}")
        
        # 最早和最新交易时间
        cursor.execute("SELECT MIN(timestamp), MAX(timestamp) FROM trades")
        time_range = cursor.fetchone()
        
        if time_range[0]:
            print(f"  时间范围: {time_range[0][:10]} 至 {time_range[1][:10]}")
        
        conn.close()


def main():
    """主程序"""
    checker = DatabaseHealthChecker()
    checker.check_all()


if __name__ == "__main__":
    main()
