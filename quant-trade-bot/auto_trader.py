#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
全自动交易机器人
- 定期获取策略推荐
- 自动开仓、平仓
- 风险控制
- 记录所有交易
"""

import sqlite3
import ccxt
import json
import time
import requests
from datetime import datetime
import os
import sys

# 项目根目录
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
# 数据库在上层目录
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), 'data', 'db', 'paper_trading.db')
CONFIG_PATH = os.path.join(SCRIPT_DIR, 'config', 'config.json')

class AutoTrader:
    def __init__(self):
        # 加载配置
        with open(CONFIG_PATH, 'r') as f:
            config = json.load(f)

        # 初始化交易所（模拟模式）
        self.exchange = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })

        # 风控参数
        self.initial_balance = 2000  # 初始资金
        self.max_positions = 5  # 最大持仓数
        self.position_size_pct = 0.15  # 每笔交易占总资金的15%
        self.min_score = 2  # 最低评分要求
        self.leverage = 3  # 杠杆倍数

        # API地址
        self.api_url = "http://localhost:5001/api/recommendations"

        print("🤖 全自动交易机器人已启动")
        print(f"💰 初始资金: ${self.initial_balance}")
        print(f"📊 最大持仓: {self.max_positions}")
        print(f"📈 单笔仓位: {self.position_size_pct * 100}%")
        print(f"⭐ 最低评分: {self.min_score}分")
        print(f"🔧 杠杆倍数: {self.leverage}x")
        print("=" * 60)

    def get_current_balance(self):
        """获取当前余额"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT balance FROM stats ORDER BY id DESC LIMIT 1")
        result = cursor.fetchone()
        conn.close()
        return result[0] if result else self.initial_balance

    def get_open_positions_count(self):
        """获取当前持仓数量"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM positions WHERE status = 'open'")
        count = cursor.fetchone()[0]
        conn.close()
        return count

    def get_open_positions(self):
        """获取当前所有持仓"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT symbol FROM positions WHERE status = 'open'")
        symbols = [row[0] for row in cursor.fetchall()]
        conn.close()
        return symbols

    def get_recommendations(self):
        """从API获取策略推荐"""
        try:
            response = requests.get(self.api_url, timeout=30)
            if response.status_code == 200:
                recommendations = response.json()
                print(f"📡 获取到 {len(recommendations)} 个推荐")
                return recommendations
            else:
                print(f"❌ API请求失败: {response.status_code}")
                return []
        except Exception as e:
            print(f"❌ 获取推荐失败: {e}")
            return []

    def open_position(self, recommendation):
        """开仓"""
        symbol = recommendation['symbol']
        signal = recommendation['signal']
        price = recommendation['price']
        stop_loss = recommendation['stop_loss']
        take_profit = recommendation['take_profit']
        score = recommendation['score']

        # 计算交易金额
        balance = self.get_current_balance()
        position_value = balance * self.position_size_pct
        quantity = (position_value * self.leverage) / price

        # 计算成本（实际占用资金）
        cost = position_value

        # 方向
        direction = 'long' if signal == 'buy' else 'short'

        print(f"\n{'='*60}")
        print(f"🎯 开仓: {symbol}")
        print(f"   方向: {direction} ({signal})")
        print(f"   价格: ${price:.4f}")
        print(f"   数量: {quantity:.4f}")
        print(f"   成本: ${cost:.2f}")
        print(f"   杠杆: {self.leverage}x")
        print(f"   止损: ${stop_loss:.4f}")
        print(f"   止盈: ${take_profit:.4f}")
        print(f"   评分: {score}分")
        print(f"{'='*60}")

        # 记录到数据库
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # 插入持仓记录
        cursor.execute("""
            INSERT INTO positions (
                symbol, amount, entry_price, open_time, leverage,
                stop_loss, take_profit, direction, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
        """, (
            symbol, quantity, price, datetime.now().isoformat(),
            self.leverage, stop_loss, take_profit, direction
        ))

        # 记录交易
        cursor.execute("""
            INSERT INTO trades (
                timestamp, symbol, side, price, quantity, leverage,
                cost, fee, pnl, pnl_pct, reason, balance_after
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            datetime.now().isoformat(), symbol, signal, price, quantity,
            self.leverage, cost, cost * 0.001, 0, 0,
            f"自动开仓-评分{score}分", balance - cost
        ))

        # 更新余额和统计（开仓不计入交易次数，只有完成平仓才算一次完整交易）
        new_balance = balance - cost
        cursor.execute("""
            UPDATE stats SET
                balance = ?,
                total_fees = total_fees + ?
            WHERE id = 1
        """, (new_balance, cost * 0.001))

        conn.commit()
        conn.close()

        print(f"✅ 开仓成功！余额: ${new_balance:.2f}")
        return True

    def should_trade(self, recommendation):
        """判断是否应该交易"""
        symbol = recommendation['symbol']
        score = recommendation['score']

        # 检查评分
        if score < self.min_score:
            print(f"⏭️  跳过 {symbol}: 评分太低 ({score}分 < {self.min_score}分)")
            return False

        # 检查持仓数量
        current_positions = self.get_open_positions_count()
        if current_positions >= self.max_positions:
            print(f"⏭️  跳过 {symbol}: 持仓已满 ({current_positions}/{self.max_positions})")
            return False

        # 检查是否已持有该币种
        open_symbols = self.get_open_positions()
        if symbol in open_symbols:
            print(f"⏭️  跳过 {symbol}: 已持有该币种")
            return False

        # 检查余额
        balance = self.get_current_balance()
        min_balance = self.initial_balance * 0.1  # 至少保留10%资金
        if balance < min_balance:
            print(f"⏭️  跳过 {symbol}: 余额不足 (${balance:.2f} < ${min_balance:.2f})")
            return False

        return True

    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取{symbol}价格失败: {e}")
            return None

    def check_and_close_positions(self):
        """检查并平仓（止盈/止损）"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        cursor.execute("SELECT * FROM positions WHERE status = 'open'")
        positions = cursor.fetchall()

        closed_count = 0
        for pos in positions:
            pos_id = pos[0]
            symbol = pos[1]
            amount = pos[2]
            entry_price = pos[3]
            leverage = pos[5]
            stop_loss = pos[6]
            take_profit = pos[7]
            direction = pos[8]

            # 获取当前价格
            current_price = self.get_current_price(symbol)
            if not current_price:
                continue

            should_close = False
            close_reason = ""

            # 检查止盈止损
            if direction == 'long':
                if current_price >= take_profit:
                    should_close = True
                    close_reason = "触发止盈"
                elif current_price <= stop_loss:
                    should_close = True
                    close_reason = "触发止损"
            else:  # short
                if current_price <= take_profit:
                    should_close = True
                    close_reason = "触发止盈"
                elif current_price >= stop_loss:
                    should_close = True
                    close_reason = "触发止损"

            if should_close:
                try:
                    self.close_position(pos_id, symbol, amount, entry_price, current_price,
                                      leverage, direction, close_reason)
                    closed_count += 1
                    time.sleep(1)
                except Exception as e:
                    print(f"❌ 平仓失败 {symbol}: {e}")

        conn.close()
        return closed_count

    def close_position(self, pos_id, symbol, amount, entry_price, close_price, leverage, direction, reason):
        """平仓"""
        # 计算盈亏
        if direction == 'long':
            pnl = (close_price - entry_price) * amount * leverage
        else:  # short
            pnl = (entry_price - close_price) * amount * leverage

        cost = (entry_price * amount) / leverage
        pnl_pct = (pnl / cost) * 100 if cost > 0 else 0

        print(f"\n{'='*60}")
        print(f"🔔 平仓: {symbol}")
        print(f"   方向: {direction}")
        print(f"   入场价: ${entry_price:.4f}")
        print(f"   平仓价: ${close_price:.4f}")
        print(f"   数量: {amount:.4f}")
        print(f"   盈亏: ${pnl:.2f} ({pnl_pct:+.2f}%)")
        print(f"   原因: {reason}")
        print(f"{'='*60}")

        # 获取当前余额
        balance = self.get_current_balance()

        # 记录到数据库
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # 更新持仓状态
        cursor.execute("""
            UPDATE positions SET
                status = 'closed',
                close_time = ?,
                close_price = ?,
                pnl = ?,
                pnl_pct = ?
            WHERE id = ?
        """, (datetime.now().isoformat(), close_price, pnl, pnl_pct, pos_id))

        # 记录交易
        side = 'sell' if direction == 'long' else 'buy'  # 平仓方向相反
        fee = cost * 0.001
        new_balance = balance + pnl - fee

        cursor.execute("""
            INSERT INTO trades (
                timestamp, symbol, side, price, quantity, leverage,
                cost, fee, pnl, pnl_pct, reason, balance_after
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (
            datetime.now().isoformat(), symbol, side, close_price, amount,
            leverage, cost, fee, pnl, pnl_pct, reason, new_balance
        ))

        # 更新余额和统计
        cursor.execute("""
            UPDATE stats SET
                balance = ?,
                total_trades = total_trades + 1,
                total_fees = total_fees + ?,
                total_pnl = total_pnl + ?
            WHERE id = 1
        """, (new_balance, fee, pnl))

        conn.commit()
        conn.close()

        print(f"✅ 平仓成功！余额: ${new_balance:.2f}")
        return True

    def run_once(self):
        """执行一次交易循环"""
        print(f"\n{'='*60}")
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} - 开始扫描")
        print(f"{'='*60}")

        # 1. 先检查现有持仓的止盈止损
        print("\n🔍 检查持仓止盈止损...")
        closed_count = self.check_and_close_positions()
        if closed_count > 0:
            print(f"✅ 平仓 {closed_count} 个持仓")
        else:
            print("💤 无需平仓")

        # 2. 获取推荐开新仓
        print("\n🔍 扫描新交易机会...")
        recommendations = self.get_recommendations()

        if not recommendations:
            print("📭 暂无推荐")
            return

        # 显示当前状态
        balance = self.get_current_balance()
        positions_count = self.get_open_positions_count()
        print(f"💰 当前余额: ${balance:.2f}")
        print(f"📊 当前持仓: {positions_count}/{self.max_positions}")
        print()

        # 尝试交易每个推荐
        trades_made = 0
        for rec in recommendations:
            if self.should_trade(rec):
                try:
                    self.open_position(rec)
                    trades_made += 1
                    time.sleep(1)  # 避免过快
                except Exception as e:
                    print(f"❌ 开仓失败 {rec['symbol']}: {e}")

        if trades_made > 0:
            print(f"\n✅ 本轮开仓 {trades_made} 个")
        else:
            print(f"\n💤 本轮无新开仓")

    def run(self):
        """持续运行"""
        print("\n🚀 开始自动交易...")
        print("⏰ 扫描间隔: 10分钟")
        print("⚠️  按 Ctrl+C 停止\n")

        while True:
            try:
                self.run_once()
                print(f"\n😴 等待10分钟...\n")
                time.sleep(600)  # 10分钟
            except KeyboardInterrupt:
                print("\n\n⛔ 收到停止信号")
                print("🛑 自动交易已停止")
                break
            except Exception as e:
                print(f"\n❌ 发生错误: {e}")
                print("⏳ 5分钟后重试...")
                time.sleep(300)

if __name__ == '__main__':
    trader = AutoTrader()
    trader.run()
