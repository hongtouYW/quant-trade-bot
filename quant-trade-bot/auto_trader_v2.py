#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易机器人 v3.1 - 数据驱动
- 追踪止损功能
- 保存原始/最终止盈止损
- 记录止损止盈变化历史
"""

import sqlite3
import ccxt
import json
import time
import requests
from datetime import datetime, date
import os

# 项目根目录
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(os.path.dirname(SCRIPT_DIR), 'data', 'db', 'paper_trading.db')
CONFIG_PATH = os.path.join(SCRIPT_DIR, 'config', 'config.json')


class AutoTraderV2:
    def __init__(self):
        # 加载Binance配置
        with open(CONFIG_PATH, 'r') as f:
            config = json.load(f)

        # 初始化交易所
        self.exchange = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'}
        })

        # 从数据库加载配置
        self.load_config_from_db()

        # API地址
        self.api_url = "http://localhost:5001/api/recommendations"

        # 快照计数器
        self.snapshot_counter = 0

        # 记录每个持仓的最高/最低价
        self.price_extremes = {}

        print("🤖 量化交易机器人 v3.1 - 数据驱动 已启动")
        print(f"💰 初始资金: ${self.initial_capital}")
        print(f"🎯 目标利润: ${self.target_profit}")
        print(f"📊 最大持仓: {self.max_positions}")
        print(f"📈 单笔最大: ${self.max_position_size}")
        print(f"⭐ 最低评分: {self.min_score}分")
        print(f"🔧 默认杠杆: {self.default_leverage}x")
        print(f"💸 手续费率: {self.fee_rate * 100}%")
        print(f"🎯 追踪止损: 已启用")
        print("=" * 60)

    def load_config_from_db(self):
        """从account_config表加载配置"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        cursor.execute("SELECT key, value FROM account_config")
        config = {row[0]: row[1] for row in cursor.fetchall()}
        conn.close()

        # 设置配置（带默认值）
        self.initial_capital = float(config.get('initial_capital', 2000))
        self.target_profit = float(config.get('target_profit', 3400))
        self.max_position_size = float(config.get('max_position_size', 500))
        self.max_positions = int(config.get('max_positions', 10))
        self.default_leverage = int(config.get('default_leverage', 3))
        self.stop_loss_pct = float(config.get('stop_loss_pct', 1.5))
        self.take_profit_pct = float(config.get('take_profit_pct', 2.5))
        self.fee_rate = float(config.get('fee_rate', 0.001))
        self.min_score = int(config.get('min_score', 60))
        self.trading_enabled = config.get('trading_enabled', '1') == '1'

    def get_current_capital(self):
        """计算当前资金"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        # 计算已实现盈亏
        cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status = 'CLOSED'")
        total_pnl = cursor.fetchone()[0] or 0

        conn.close()
        return self.initial_capital + total_pnl

    def get_margin_used(self):
        """获取当前占用的保证金"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("SELECT SUM(amount) FROM real_trades WHERE status = 'OPEN'")
        margin = cursor.fetchone()[0] or 0
        conn.close()
        return margin

    def get_open_positions(self):
        """获取当前所有持仓"""
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM real_trades WHERE status = 'OPEN'")
        positions = [dict(row) for row in cursor.fetchall()]
        conn.close()
        return positions

    def get_recommendations(self):
        """从API获取策略推荐"""
        try:
            response = requests.get(self.api_url, timeout=180)
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
        """开仓 - 记录完整数据"""
        symbol = recommendation['symbol']
        signal = recommendation['signal']
        price = recommendation['price']
        stop_loss = recommendation['stop_loss']
        take_profit = recommendation['take_profit']
        score = recommendation['score']
        rsi = recommendation.get('rsi', 50)
        trend = recommendation.get('trend', 'neutral')
        reasons = recommendation.get('reasons', [])

        # 计算仓位大小（基于评分）
        current_capital = self.get_current_capital()
        margin_used = self.get_margin_used()
        available = current_capital - margin_used

        # 评分越高，仓位越大（但不超过最大仓位）
        if score >= 80:
            position_pct = 0.25
        elif score >= 70:
            position_pct = 0.20
        elif score >= 60:
            position_pct = 0.15
        else:
            position_pct = 0.10

        margin = min(available * position_pct, self.max_position_size)

        if margin < 50:  # 最小仓位50U
            print(f"⏭️  {symbol}: 可用资金不足 (${available:.2f})")
            return False

        # 方向
        direction = 'long' if signal == 'buy' else 'short'

        # 计算开仓手续费
        position_value = margin * self.default_leverage
        entry_fee = position_value * self.fee_rate

        print(f"\n{'='*60}")
        print(f"🎯 开仓: {symbol}")
        print(f"   方向: {direction.upper()}")
        print(f"   价格: ${price:.4f}")
        print(f"   保证金: ${margin:.2f}")
        print(f"   杠杆: {self.default_leverage}x")
        print(f"   止损: ${stop_loss:.4f} (-{self.stop_loss_pct}%)")
        print(f"   止盈: ${take_profit:.4f} (+{self.take_profit_pct}%)")
        print(f"   评分: {score}分")
        print(f"   RSI: {rsi:.1f}")
        print(f"   趋势: {trend}")
        print(f"   手续费: ${entry_fee:.4f}")
        print(f"{'='*60}")

        # 记录到数据库
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        entry_time = datetime.now().isoformat()
        reason_text = ', '.join(reasons) if reasons else f"评分{score}分"

        # 保存原始止盈止损
        cursor.execute("""
            INSERT INTO real_trades (
                symbol, direction, entry_price, amount, leverage,
                stop_loss, take_profit, entry_time, status,
                fee, score, reason, entry_score, entry_rsi, entry_trend,
                original_stop_loss, original_take_profit,
                assistant, mode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, ?, '量化交易', 'paper')
        """, (
            symbol, direction, price, margin, self.default_leverage,
            stop_loss, take_profit, entry_time,
            entry_fee, score, reason_text, score, rsi, trend,
            stop_loss, take_profit  # 保存原始值
        ))

        trade_id = cursor.lastrowid

        # 初始化价格极值
        self.price_extremes[trade_id] = {
            'highest': price,
            'lowest': price
        }

        # 记录交易信号
        cursor.execute("""
            INSERT INTO trade_signals (
                symbol, signal_type, score, rsi, trend, reasons, executed, trade_id
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        """, (symbol, signal, score, rsi, trend, json.dumps(reasons), trade_id))

        conn.commit()
        conn.close()

        print(f"✅ 开仓成功！")
        return True

    def update_trailing_stop(self, trade, current_price):
        """追踪止损逻辑 v3 - 盈利1%后才开始追踪"""
        trade_id = trade['id']
        direction = trade['direction']
        entry_price = trade['entry_price']
        current_sl = trade['stop_loss']
        current_tp = trade['take_profit']

        # 计算当前盈利百分比
        if direction == 'long':
            profit_pct = (current_price - entry_price) / entry_price * 100
        else:
            profit_pct = (entry_price - current_price) / entry_price * 100

        # 未达到1%盈利，不启动追踪
        if profit_pct < 1.0:
            return current_sl

        # 获取或初始化价格极值
        if trade_id not in self.price_extremes:
            self.price_extremes[trade_id] = {
                'highest': entry_price,
                'lowest': entry_price
            }

        extremes = self.price_extremes[trade_id]

        if direction == 'long':
            # 更新最高价
            if current_price > extremes['highest']:
                extremes['highest'] = current_price

            # 计算新止损 = 最高价 * (1 - 止损百分比)
            new_sl = extremes['highest'] * (1 - self.stop_loss_pct / 100)

            # 止损只能上移，不能下移
            if new_sl > current_sl:
                self._record_sl_change(trade_id, current_sl, new_sl, current_tp, current_tp,
                                       f"追踪止损上移 (盈利{profit_pct:.1f}% 最高价${extremes['highest']:.4f})",
                                       current_price, extremes['highest'], None)
                self._update_stop_loss(trade_id, new_sl)
                print(f"   📈 {trade['symbol']} 盈利{profit_pct:.1f}% 止损上移: ${current_sl:.4f} -> ${new_sl:.4f}")
                return new_sl
        else:
            # 更新最低价
            if current_price < extremes['lowest']:
                extremes['lowest'] = current_price

            # 计算新止损 = 最低价 * (1 + 止损百分比)
            new_sl = extremes['lowest'] * (1 + self.stop_loss_pct / 100)

            # 止损只能下移，不能上移
            if new_sl < current_sl:
                self._record_sl_change(trade_id, current_sl, new_sl, current_tp, current_tp,
                                       f"追踪止损下移 (盈利{profit_pct:.1f}% 最低价${extremes['lowest']:.4f})",
                                       current_price, None, extremes['lowest'])
                self._update_stop_loss(trade_id, new_sl)
                print(f"   📉 {trade['symbol']} 盈利{profit_pct:.1f}% 止损下移: ${current_sl:.4f} -> ${new_sl:.4f}")
                return new_sl

        return current_sl

    def _update_stop_loss(self, trade_id, new_sl):
        """更新止损价"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE real_trades
            SET stop_loss = ?, sl_tp_adjustments = COALESCE(sl_tp_adjustments, 0) + 1
            WHERE id = ?
        """, (new_sl, trade_id))
        conn.commit()
        conn.close()

    def _record_sl_change(self, trade_id, old_sl, new_sl, old_tp, new_tp, reason, current_price, highest, lowest):
        """记录止损止盈变化"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO sl_tp_history (trade_id, old_stop_loss, new_stop_loss, old_take_profit, new_take_profit,
                                       reason, current_price, highest_price, lowest_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (trade_id, old_sl, new_sl, old_tp, new_tp, reason, current_price, highest, lowest))
        conn.commit()
        conn.close()

    def check_and_close_positions(self):
        """检查并平仓"""
        positions = self.get_open_positions()
        closed_count = 0

        for pos in positions:
            symbol = pos['symbol']
            current_price = self.get_current_price(symbol)
            if not current_price:
                continue

            direction = pos['direction']
            entry_price = pos['entry_price']

            # 30分钟最低持仓保护 (数据显示<30min胜率0%)
            min_hold_ok = True
            try:
                entry_t = datetime.strptime(
                    str(pos['entry_time']).replace('T', ' ').split('.')[0],
                    '%Y-%m-%d %H:%M:%S'
                )
                hold_minutes = (datetime.now() - entry_t).total_seconds() / 60
                if hold_minutes < 30:
                    min_hold_ok = False
                    if direction == 'long':
                        emergency_pct = (current_price - entry_price) / entry_price * 100
                    else:
                        emergency_pct = (entry_price - current_price) / entry_price * 100
                    if emergency_pct < -5:
                        min_hold_ok = True
                        print(f"   \u26a0\ufe0f {symbol} 紧急平仓: 持仓{hold_minutes:.0f}分钟 亏损{emergency_pct:.1f}%")
                    else:
                        print(f"   \U0001f6e1 {symbol} 持仓保护中: {hold_minutes:.0f}/30分钟")
            except:
                pass

            # 先更新追踪止损
            stop_loss = self.update_trailing_stop(pos, current_price)
            take_profit = pos['take_profit']

            # 更新最大浮盈/浮亏
            self._update_max_profit_loss(pos, current_price)

            should_close = False
            close_reason = ""

            # 1. 检查止盈止损
            if direction == 'long':
                if current_price >= take_profit and min_hold_ok:
                    should_close = True
                    close_reason = "止盈"
                elif current_price <= stop_loss and min_hold_ok:
                    should_close = True
                    close_reason = "追踪止损" if stop_loss != pos.get('original_stop_loss') else "止损"
            else:  # short
                if current_price <= take_profit and min_hold_ok:
                    should_close = True
                    close_reason = "止盈"
                elif current_price >= stop_loss and min_hold_ok:
                    should_close = True
                    close_reason = "追踪止损" if stop_loss != pos.get('original_stop_loss') else "止损"

            # 2. 智能评估 - 主动止损
            if not should_close:
                smart_exit, smart_reason = self.evaluate_position_health(pos, current_price)
                if smart_exit:
                    should_close = True
                    close_reason = f"智能止损: {smart_reason}"

            if should_close:
                self.close_position(pos, current_price, close_reason)
                closed_count += 1
                # 清理价格极值记录
                if pos['id'] in self.price_extremes:
                    del self.price_extremes[pos['id']]
                time.sleep(1)

        return closed_count

    def evaluate_position_health(self, pos, current_price):
        """智能评估持仓健康度，决定是否提前止损"""
        symbol = pos['symbol']
        direction = pos['direction']
        entry_price = pos['entry_price']
        # Python 3.6 兼容
        entry_time_str = pos['entry_time'].replace('T', ' ').split('.')[0]
        entry_time = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
        holding_minutes = (datetime.now() - entry_time).total_seconds() / 60

        # 计算当前盈亏百分比
        if direction == 'long':
            pnl_pct = (current_price - entry_price) / entry_price * 100
        else:
            pnl_pct = (entry_price - current_price) / entry_price * 100

        # 获取当前市场指标
        try:
            ticker_symbol = symbol.replace('/', '')
            response = requests.get(f"http://localhost:5001/api/analysis/{ticker_symbol}", timeout=10)
            if response.status_code == 200:
                analysis = response.json()
                current_rsi = analysis.get('rsi', 50)
                current_trend = analysis.get('trend', 'neutral')
            else:
                return False, None
        except:
            return False, None

        # 规则1: 持仓超2小时且亏损
        if holding_minutes > 120 and pnl_pct < 0:
            return True, f"持仓{int(holding_minutes)}分钟无盈利"

        # 规则2: 趋势反转
        entry_trend = pos.get('entry_trend', 'neutral')
        if direction == 'long' and current_trend == 'bearish' and entry_trend != 'bearish':
            if pnl_pct < 0.5:
                return True, f"趋势反转({entry_trend}→{current_trend})"
        elif direction == 'short' and current_trend == 'bullish' and entry_trend != 'bullish':
            if pnl_pct < 0.5:
                return True, f"趋势反转({entry_trend}→{current_trend})"

        # 规则3: RSI反向极端时获利了结
        if direction == 'long' and current_rsi > 75 and pnl_pct > 0.5:
            return True, f"RSI超买({current_rsi:.0f})获利了结"
        elif direction == 'short' and current_rsi < 25 and pnl_pct > 0.5:
            return True, f"RSI超卖({current_rsi:.0f})获利了结"

        # 规则4: 浮亏超1%且持仓超30分钟
        if pnl_pct < -1.0 and holding_minutes > 30:
            return True, f"浮亏{pnl_pct:.1f}%超时"

        return False, None

    def _update_max_profit_loss(self, pos, current_price):
        """更新最大浮盈/浮亏"""
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        direction = pos['direction']
        entry_price = pos['entry_price']
        margin = pos['amount']
        leverage = pos['leverage']

        # 计算当前盈亏百分比
        if direction == 'long':
            pnl_pct = (current_price - entry_price) / entry_price * 100
        else:
            pnl_pct = (entry_price - current_price) / entry_price * 100

        # 更新最大浮盈/浮亏
        if pnl_pct > 0:
            cursor.execute("""
                UPDATE real_trades
                SET max_profit = MAX(COALESCE(max_profit, 0), ?)
                WHERE id = ?
            """, (pnl_pct, pos['id']))
        else:
            cursor.execute("""
                UPDATE real_trades
                SET max_loss = MIN(COALESCE(max_loss, 0), ?)
                WHERE id = ?
            """, (pnl_pct, pos['id']))

        conn.commit()
        conn.close()

    def close_position(self, position, exit_price, reason):
        """平仓 - 计算完整费用"""
        pos_id = position['id']
        symbol = position['symbol']
        direction = position['direction']
        entry_price = position['entry_price']
        margin = position['amount']
        leverage = position['leverage']
        entry_time_str = position['entry_time']

        # 获取当前止盈止损（可能已被追踪止损修改）
        current_sl = position['stop_loss']
        current_tp = position['take_profit']

        # 计算盈亏
        if direction == 'long':
            pnl_pct = (exit_price - entry_price) / entry_price
        else:
            pnl_pct = (entry_price - exit_price) / entry_price

        position_value = margin * leverage
        pnl_before_fee = position_value * pnl_pct

        # 计算手续费
        entry_fee = position.get('fee', position_value * self.fee_rate)
        exit_fee = position_value * self.fee_rate
        total_fee = entry_fee + exit_fee

        # 计算资金费（每8小时0.01%）
        # Python 3.6 兼容
        entry_time_clean = entry_time_str.replace('T', ' ').split('.')[0]
        entry_time = datetime.strptime(entry_time_clean, '%Y-%m-%d %H:%M:%S')
        exit_time = datetime.now()
        holding_hours = (exit_time - entry_time).total_seconds() / 3600
        funding_rate = 0.0001  # 0.01%
        funding_fee = position_value * funding_rate * (holding_hours / 8)

        # 计算持仓时长（分钟）
        duration_minutes = int(holding_hours * 60)

        # 最终盈亏
        pnl = pnl_before_fee - exit_fee - funding_fee
        roi = (pnl / margin) * 100

        # 获取原始止盈止损（兼容旧数据，None时用当前值）
        original_sl = position.get('original_stop_loss') or current_sl
        original_tp = position.get('original_take_profit') or current_tp
        adjustments = position.get('sl_tp_adjustments') or 0

        print(f"\n{'='*60}")
        print(f"🔔 平仓: {symbol}")
        print(f"   方向: {direction.upper()}")
        print(f"   入场: ${entry_price:.4f}")
        print(f"   出场: ${exit_price:.4f}")
        print(f"   保证金: ${margin:.2f}")
        print(f"   持仓时长: {duration_minutes}分钟")
        orig_sl_str = f"${original_sl:.4f}" if original_sl else "N/A"
        curr_sl_str = f"${current_sl:.4f}" if current_sl else "N/A"
        print(f"   原始止损: {orig_sl_str} → 最终: {curr_sl_str}")
        print(f"   止损调整次数: {adjustments or 0}")
        print(f"   价格盈亏: ${pnl_before_fee:+.2f}")
        print(f"   手续费: -${total_fee:.4f}")
        print(f"   资金费: -${funding_fee:.4f}")
        print(f"   实际盈亏: ${pnl:+.2f} ({roi:+.2f}%)")
        print(f"   原因: {reason}")
        print(f"{'='*60}")

        # 更新数据库
        conn = sqlite3.connect(DB_PATH)
        cursor = conn.cursor()

        exit_time_str = exit_time.isoformat()

        cursor.execute("""
            UPDATE real_trades SET
                exit_price = ?,
                exit_time = ?,
                status = 'CLOSED',
                pnl = ?,
                roi = ?,
                fee = ?,
                funding_fee = ?,
                duration_minutes = ?,
                close_reason = ?,
                final_stop_loss = ?,
                final_take_profit = ?
            WHERE id = ?
        """, (exit_price, exit_time_str, pnl, roi, total_fee, funding_fee, duration_minutes, reason,
              current_sl, current_tp, pos_id))

        # 记录资金费
        if funding_fee > 0:
            cursor.execute("""
                INSERT INTO funding_history (
                    trade_id, symbol, funding_rate, position_value, funding_fee, direction
                ) VALUES (?, ?, ?, ?, ?, ?)
            """, (pos_id, symbol, funding_rate, position_value, funding_fee, direction))

        conn.commit()
        conn.close()

        print(f"✅ 平仓成功！")
        return True

    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取{symbol}价格失败: {e}")
            return None

    def should_trade(self, recommendation):
        """判断是否应该交易"""
        if not self.trading_enabled:
            print("⏸️  交易已禁用")
            return False

        symbol = recommendation['symbol']
        score = recommendation['score']

        # 检查评分
        if score < self.min_score:
            return False

        # 检查持仓数量
        positions = self.get_open_positions()
        if len(positions) >= self.max_positions:
            print(f"⏭️  持仓已满 ({len(positions)}/{self.max_positions})")
            return False

        # 检查是否已持有该币种
        for pos in positions:
            if pos['symbol'] == symbol:
                return False

        # 检查可用资金
        available = self.get_current_capital() - self.get_margin_used()
        if available < 50:
            print(f"⏭️  可用资金不足 (${available:.2f})")
            return False

        return True

    def save_account_snapshot(self):
        """保存账户快照"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()

            current_capital = self.get_current_capital()
            margin_used = self.get_margin_used()
            available = current_capital - margin_used
            positions = self.get_open_positions()

            # 计算未实现盈亏
            unrealized_pnl = 0
            for pos in positions:
                current_price = self.get_current_price(pos['symbol'])
                if current_price:
                    if pos['direction'] == 'long':
                        pnl_pct = (current_price - pos['entry_price']) / pos['entry_price']
                    else:
                        pnl_pct = (pos['entry_price'] - current_price) / pos['entry_price']
                    unrealized_pnl += pos['amount'] * pos['leverage'] * pnl_pct

            # 计算已实现盈亏
            cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status = 'CLOSED'")
            realized_pnl = cursor.fetchone()[0] or 0

            # 计算总费用
            cursor.execute("SELECT SUM(fee), SUM(funding_fee) FROM real_trades WHERE status = 'CLOSED'")
            fees = cursor.fetchone()
            total_fees = fees[0] or 0
            total_funding_fees = fees[1] or 0

            # 计算最大回撤
            max_capital = max(self.initial_capital, current_capital)
            max_drawdown = ((max_capital - current_capital) / max_capital * 100) if max_capital > 0 else 0

            # 计算当日盈亏
            today = date.today().isoformat()
            cursor.execute("SELECT SUM(pnl) FROM real_trades WHERE status = 'CLOSED' AND DATE(exit_time) = ?", (today,))
            daily_pnl = cursor.fetchone()[0] or 0

            cursor.execute("""
                INSERT INTO account_snapshots (
                    total_capital, available_capital, margin_used,
                    unrealized_pnl, realized_pnl, total_fees, total_funding_fees,
                    open_positions, daily_pnl, max_drawdown
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                current_capital + unrealized_pnl, available, margin_used,
                unrealized_pnl, realized_pnl, total_fees, total_funding_fees,
                len(positions), daily_pnl, max_drawdown
            ))

            conn.commit()
            conn.close()
            print(f"📸 账户快照已保存")

        except Exception as e:
            print(f"❌ 保存快照失败: {e}")

    def update_daily_stats(self):
        """更新每日统计"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()

            today = date.today().isoformat()
            current_capital = self.get_current_capital()

            # 获取今日统计
            cursor.execute("""
                SELECT
                    COUNT(CASE WHEN DATE(entry_time) = ? THEN 1 END) as opened,
                    COUNT(CASE WHEN DATE(exit_time) = ? THEN 1 END) as closed,
                    SUM(CASE WHEN DATE(exit_time) = ? AND pnl > 0 THEN 1 ELSE 0 END) as wins,
                    SUM(CASE WHEN DATE(exit_time) = ? AND pnl < 0 THEN 1 ELSE 0 END) as losses,
                    SUM(CASE WHEN DATE(exit_time) = ? THEN pnl ELSE 0 END) as total_pnl,
                    SUM(CASE WHEN DATE(exit_time) = ? THEN fee ELSE 0 END) as total_fees,
                    SUM(CASE WHEN DATE(exit_time) = ? THEN funding_fee ELSE 0 END) as funding_fees,
                    MAX(CASE WHEN DATE(exit_time) = ? THEN pnl END) as best,
                    MIN(CASE WHEN DATE(exit_time) = ? THEN pnl END) as worst
                FROM real_trades
            """, (today, today, today, today, today, today, today, today, today))

            stats = cursor.fetchone()

            # 插入或更新每日统计
            cursor.execute("""
                INSERT OR REPLACE INTO daily_stats (
                    date, starting_capital, ending_capital,
                    trades_opened, trades_closed, win_trades, loss_trades,
                    total_pnl, total_fees, total_funding_fees, best_trade, worst_trade
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (
                today, self.initial_capital, current_capital,
                stats[0] or 0, stats[1] or 0, stats[2] or 0, stats[3] or 0,
                stats[4] or 0, stats[5] or 0, stats[6] or 0, stats[7], stats[8]
            ))

            conn.commit()
            conn.close()

        except Exception as e:
            print(f"❌ 更新每日统计失败: {e}")

    def check_circuit_breaker(self):
        """熔断机制：连续亏损时暂停开仓，30分钟后自动解锁"""
        try:
            conn = sqlite3.connect(DB_PATH)
            cursor = conn.cursor()
            cursor.execute("""
                SELECT pnl, exit_time FROM real_trades
                WHERE status = 'CLOSED'
                ORDER BY exit_time DESC
                LIMIT 5
            """)
            recent = cursor.fetchall()
            conn.close()

            if len(recent) < 5:
                return False

            # 最近5笔全部亏损 → 检查熔断
            all_losses = all(row[0] < 0 for row in recent)
            if all_losses:
                # 检查最后一笔亏损的时间，超过30分钟自动解锁
                last_exit = recent[0][1]
                try:
                    last_time = datetime.strptime(
                        str(last_exit).replace('T', ' ').split('.')[0],
                        '%Y-%m-%d %H:%M:%S'
                    )
                    minutes_since = (datetime.now() - last_time).total_seconds() / 60
                    if minutes_since > 30:
                        print(f"🔓 熔断解除：已冷却 {minutes_since:.0f} 分钟，恢复交易")
                        return False
                except Exception:
                    pass

                total_loss = sum(row[0] for row in recent)
                print(f"🚨 熔断触发：最近5笔全部亏损（合计 ${total_loss:.2f}），暂停开仓")
                return True

            return False
        except Exception:
            return False

    def run_once(self):
        """执行一次交易循环"""
        print(f"\n{'='*60}")
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')} - 开始扫描")
        print(f"{'='*60}")

        # 1. 检查持仓止盈止损
        print("\n🔍 检查持仓...")
        closed = self.check_and_close_positions()
        if closed > 0:
            print(f"✅ 平仓 {closed} 个")

        # 2. 熔断检查
        circuit_break = self.check_circuit_breaker()

        # 3. 获取推荐
        print("\n🔍 扫描交易机会...")
        recommendations = self.get_recommendations()

        # 显示状态
        current_capital = self.get_current_capital()
        margin_used = self.get_margin_used()
        available = current_capital - margin_used
        positions = self.get_open_positions()

        print(f"\n💰 当前资金: ${current_capital:.2f}")
        print(f"📊 保证金占用: ${margin_used:.2f}")
        print(f"💵 可用余额: ${available:.2f}")
        print(f"📈 持仓数: {len(positions)}/{self.max_positions}")

        # 4. 尝试开仓（熔断时不开新仓，但仍监控已有持仓）
        trades_made = 0
        if circuit_break:
            print("⏸️  熔断中：仅监控持仓，不开新仓")
        else:
            for rec in recommendations:
                if self.should_trade(rec):
                    if self.open_position(rec):
                        trades_made += 1
                        time.sleep(1)

        if trades_made > 0:
            print(f"\n✅ 本轮开仓 {trades_made} 个")

        # 4. 保存快照（每60次循环保存一次，约1小时）
        self.snapshot_counter += 1
        if self.snapshot_counter % 60 == 0:
            self.save_account_snapshot()

        # 5. 更新每日统计
        self.update_daily_stats()

    def run(self, interval=300):
        """持续运行"""
        print("\n🚀 量化交易开始运行...")
        print(f"⏰ 扫描间隔: {interval}秒")
        print("⚠️  按 Ctrl+C 停止\n")

        while True:
            try:
                self.run_once()
                print(f"\n😴 等待{interval}秒...\n")
                time.sleep(interval)
            except KeyboardInterrupt:
                print("\n\n⛔ 收到停止信号")
                self.save_account_snapshot()  # 退出前保存快照
                print("🛑 量化交易已停止")
                break
            except Exception as e:
                print(f"\n❌ 发生错误: {e}")
                import traceback
                traceback.print_exc()
                print("⏳ 5分钟后重试...")
                time.sleep(300)


if __name__ == '__main__':
    trader = AutoTraderV2()
    trader.run(interval=60)  # 5分钟扫描一次
