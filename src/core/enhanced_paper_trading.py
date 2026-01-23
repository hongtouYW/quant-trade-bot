#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
增强版实盘模拟交易引擎
- 支持杠杆交易
- 完整的数据库记录
- 详细的Telegram通知
- 交易费用统计
"""

import ccxt
import json
import time
from datetime import datetime
import os
import sys
import sqlite3

sys.path.append(os.path.dirname(os.path.abspath(__file__)))
try:
    from utils.telegram_notify import TelegramNotify
except:
    TelegramNotify = None


class EnhancedPaperTradingBot:
    """增强版模拟交易机器人"""
    
    def __init__(self, initial_balance=1000, config_file='config.json', leverage=1):
        # 初始资金
        self.initial_balance = initial_balance
        self.balance = initial_balance
        self.leverage = leverage  # 杠杆倍数
        self.positions = {}
        self.trade_history = []
        self.start_time = datetime.now()
        self.last_report_date = None  # 上次报表日期
        
        # 交易配置
        self.config = self._load_config(config_file)
        self.symbols = ['BTC/USDT', 'ETH/USDT', 'XMR/USDT']
        self.risk_per_trade = 0.02  # 每笔2%风险
        self.max_position_size = 0.3  # 最大30%仓位
        self.stop_loss_pct = 0.03  # 3%止损
        self.take_profit_pct = 0.06  # 6%止盈
        
        # 费用和滑点
        self.maker_fee = 0.001  # 0.1% maker费
        self.taker_fee = 0.001  # 0.1% taker费
        self.slippage = 0.0005  # 0.05%滑点
        
        # 累计费用统计
        self.total_fees = 0
        
        # 初始化交易所
        self.exchange = ccxt.binance({
            'enableRateLimit': True,
            'timeout': 30000
        })
        
        # 初始化Telegram
        self.telegram = self._init_telegram()
        
        # 初始化数据库
        self.db_path = 'paper_trading.db'
        self._init_database()
        
        # 性能统计
        self.stats = {
            'total_trades': 0,
            'winning_trades': 0,
            'losing_trades': 0,
            'total_pnl': 0,
            'win_rate': 0,
            'total_fees': 0
        }
        
        print("🎯 增强版实盘模拟交易系统启动")
        print(f"💰 初始资金: ${initial_balance:,.2f}")
        print(f"📊 杠杆倍数: {leverage}x")
        print(f"📈 交易品种: {', '.join(self.symbols)}")
        print(f"⚠️ 单笔风险: {self.risk_per_trade*100}%")
        print(f"💾 数据库: {self.db_path}")
        
        self._send_notification(
            "🚀 实盘模拟交易启动",
            f"初始资金: ${initial_balance:,.2f}\n"
            f"杠杆: {leverage}x\n"
            f"监控品种: {', '.join(self.symbols)}"
        )
    
    def _load_config(self, config_file):
        """加载配置"""
        try:
            with open(config_file, 'r') as f:
                return json.load(f)
        except:
            return {}
    
    def _init_telegram(self):
        """初始化Telegram"""
        if not TelegramNotify:
            return None
        
        try:
            telegram_config = self.config.get('telegram', {})
            bot_token = telegram_config.get('bot_token')
            chat_id = telegram_config.get('chat_id')
            
            if bot_token and chat_id:
                return TelegramNotify(bot_token, chat_id)
        except Exception as e:
            print(f"⚠️ Telegram初始化失败: {e}")
        
        return None
    
    def _init_database(self):
        """初始化数据库"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 创建交易表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL,
                symbol TEXT NOT NULL,
                side TEXT NOT NULL,
                price REAL NOT NULL,
                quantity REAL NOT NULL,
                leverage INTEGER DEFAULT 1,
                cost REAL NOT NULL,
                fee REAL NOT NULL,
                pnl REAL DEFAULT 0,
                pnl_pct REAL DEFAULT 0,
                reason TEXT,
                balance_after REAL NOT NULL
            )
        ''')
        
        # 创建持仓表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS positions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT UNIQUE NOT NULL,
                quantity REAL NOT NULL,
                entry_price REAL NOT NULL,
                entry_time TEXT NOT NULL,
                leverage INTEGER DEFAULT 1,
                stop_loss REAL NOT NULL,
                take_profit REAL NOT NULL,
                cost REAL NOT NULL,
                status TEXT DEFAULT 'open'
            )
        ''')
        
        # 创建统计表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL,
                balance REAL NOT NULL,
                total_pnl REAL NOT NULL,
                total_trades INTEGER NOT NULL,
                winning_trades INTEGER NOT NULL,
                losing_trades INTEGER NOT NULL,
                win_rate REAL NOT NULL,
                total_fees REAL NOT NULL
            )
        ''')
        
        conn.commit()
        conn.close()
        
        print(f"✅ 数据库初始化完成: {self.db_path}")
    
    def _send_notification(self, title, message):
        """发送Telegram通知"""
        if self.telegram:
            try:
                full_message = f"<b>{title}</b>\n{message}"
                self.telegram.send_message(full_message)
            except Exception as e:
                print(f"❌ 通知发送失败: {e}")
    
    def get_current_price(self, symbol):
        """获取当前价格"""
        try:
            ticker = self.exchange.fetch_ticker(symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取价格失败 {symbol}: {e}")
            return None
    
    def calculate_position_size(self, symbol, entry_price):
        """计算仓位大小（考虑杠杆）"""
        # 基于风险的仓位计算
        risk_amount = self.balance * self.risk_per_trade
        position_value = risk_amount / self.stop_loss_pct
        
        # 限制最大仓位
        max_position_value = self.balance * self.max_position_size
        position_value = min(position_value, max_position_value)
        
        # 使用杠杆，实际占用保证金更少
        margin_required = position_value / self.leverage
        
        # 计算数量
        quantity = position_value / entry_price
        
        return quantity, position_value, margin_required
    
    def simulate_buy(self, symbol, price, quantity, position_type='long'):
        """模拟买入
        position_type: 'long' 做多, 'short' 做空
        """
        # 模拟滑点
        actual_price = price * (1 + self.slippage)
        
        # 计算总成本（杠杆后的保证金）
        position_value = quantity * actual_price
        margin_required = position_value / self.leverage
        fee = position_value * self.taker_fee
        total_cost = margin_required + fee
        
        if self.balance < total_cost:
            print(f"❌ 余额不足: 需要${total_cost:.2f}, 当前${self.balance:.2f}")
            return False
        
        # 扣除资金
        self.balance -= total_cost
        self.total_fees += fee
        
        # 记录持仓
        self.positions[symbol] = {
            'quantity': quantity,
            'entry_price': actual_price,
            'entry_time': datetime.now(),
            'leverage': self.leverage,
            'position_type': position_type,  # 'long' 或 'short'
            'stop_loss': actual_price * (1 - self.stop_loss_pct),
            'take_profit': actual_price * (1 + self.take_profit_pct),
            'cost': total_cost,
            'position_value': position_value,
            'margin': margin_required
        }
        
        # 保存到数据库
        self._save_trade_to_db({
            'timestamp': datetime.now().isoformat(),
            'symbol': symbol,
            'side': 'buy',
            'price': actual_price,
            'quantity': quantity,
            'leverage': self.leverage,
            'cost': total_cost,
            'fee': fee,
            'balance_after': self.balance
        })
        
        self._save_position_to_db(symbol, self.positions[symbol])
        
        # 显示信息
        position_icon = "📈 做多" if position_type == 'long' else "📉 做空"
        print(f"\n{'='*60}")
        print(f"✅ 模拟买入成功")
        print(f"{'='*60}")
        print(f"{position_icon} 交易对: {symbol}")
        print(f"💰 价格: ${actual_price:,.2f}")
        print(f"📊 数量: {quantity:.6f}")
        print(f"🔢 杠杆: {self.leverage}x")
        print(f"💵 仓位价值: ${position_value:,.2f}")
        print(f"💎 保证金: ${margin_required:,.2f}")
        print(f"💸 手续费: ${fee:.2f}")
        print(f"💰 余额: ${self.balance:,.2f}")
        print(f"🛡️ 止损: ${self.positions[symbol]['stop_loss']:.2f}")
        print(f"🎯 止盈: ${self.positions[symbol]['take_profit']:.2f}")
        print(f"{'='*60}\n")
        
        # 发送Telegram通知
        position_emoji = "📈" if position_type == 'long' else "📉"
        position_text = "做多" if position_type == 'long' else "做空"
        self._send_notification(
            f"{position_emoji} 开仓{position_text} - {symbol}",
            f"<b>买入详情</b>\n"
            f"━━━━━━━━━━━━━━\n"
            f"方向: {position_text}\n"
            f"价格: ${actual_price:,.2f}\n"
            f"数量: {quantity:.6f}\n"
            f"杠杆: {self.leverage}x\n"
            f"━━━━━━━━━━━━━━\n"
            f"💵 仓位价值: ${position_value:,.2f}\n"
            f"💎 保证金: ${margin_required:,.2f}\n"
            f"💸 手续费: ${fee:.2f}\n"
            f"━━━━━━━━━━━━━━\n"
            f"🛡️ 止损: ${self.positions[symbol]['stop_loss']:.2f}\n"
            f"🎯 止盈: ${self.positions[symbol]['take_profit']:.2f}\n"
            f"━━━━━━━━━━━━━━\n"
            f"💰 剩余余额: ${self.balance:,.2f}"
        )
        
        return True
    
    def simulate_sell(self, symbol, price, quantity, reason="手动"):
        """模拟卖出"""
        if symbol not in self.positions:
            print(f"❌ 无持仓: {symbol}")
            return False
        
        position = self.positions[symbol]
        
        # 模拟滑点
        actual_price = price * (1 - self.slippage)
        
        # 计算收益（考虑杠杆）
        position_value = quantity * actual_price
        entry_value = quantity * position['entry_price']
        
        # 杠杆交易的盈亏是放大的
        gross_pnl = (position_value - entry_value) * self.leverage
        fee = position_value * self.taker_fee
        net_pnl = gross_pnl - fee
        
        # 返还保证金
        margin_return = position['margin']
        
        # 更新余额（保证金 + 盈亏 - 手续费）
        self.balance += margin_return + net_pnl
        self.total_fees += fee
        
        # 计算盈亏百分比
        pnl_pct = (net_pnl / position['cost']) * 100
        
        # 更新统计
        self.stats['total_trades'] += 1
        self.stats['total_pnl'] += net_pnl
        self.stats['total_fees'] = self.total_fees
        
        if net_pnl > 0:
            self.stats['winning_trades'] += 1
            emoji = "🟢"
        else:
            self.stats['losing_trades'] += 1
            emoji = "🔴"
        
        self.stats['win_rate'] = (self.stats['winning_trades'] / 
                                  self.stats['total_trades'] * 100)
        
        # 保存到数据库
        self._save_trade_to_db({
            'timestamp': datetime.now().isoformat(),
            'symbol': symbol,
            'side': 'sell',
            'price': actual_price,
            'quantity': quantity,
            'leverage': self.leverage,
            'cost': position_value,
            'fee': fee,
            'pnl': net_pnl,
            'pnl_pct': pnl_pct,
            'reason': reason,
            'balance_after': self.balance
        })
        
        self._update_position_status(symbol, 'closed')
        self._save_stats_to_db()
        
        # 显示信息
        print(f"\n{'='*60}")
        print(f"✅ 模拟卖出成功")
        print(f"{'='*60}")
        print(f"📉 交易对: {symbol}")
        print(f"💰 价格: ${actual_price:,.2f}")
        print(f"📊 数量: {quantity:.6f}")
        print(f"🔢 杠杆: {self.leverage}x")
        print(f"💵 仓位价值: ${position_value:,.2f}")
        print(f"💸 手续费: ${fee:.2f}")
        print(f"{emoji} 盈亏: ${net_pnl:+,.2f} ({pnl_pct:+.2f}%)")
        print(f"📝 原因: {reason}")
        print(f"💰 余额: ${self.balance:,.2f}")
        print(f"{'='*60}")
        print(f"📊 总交易: {self.stats['total_trades']}")
        print(f"✅ 盈利: {self.stats['winning_trades']} | ❌ 亏损: {self.stats['losing_trades']}")
        print(f"📈 胜率: {self.stats['win_rate']:.1f}%")
        print(f"💵 总盈亏: ${self.stats['total_pnl']:+,.2f}")
        print(f"💸 总手续费: ${self.stats['total_fees']:,.2f}")
        print(f"{'='*60}\n")
        
        # 发送Telegram通知
        self._send_notification(
            f"📉 平仓 - {symbol}",
            f"<b>卖出详情</b>\n"
            f"━━━━━━━━━━━━━━\n"
            f"价格: ${actual_price:,.2f}\n"
            f"数量: {quantity:.6f}\n"
            f"杠杆: {self.leverage}x\n"
            f"原因: {reason}\n"
            f"━━━━━━━━━━━━━━\n"
            f"💵 仓位价值: ${position_value:,.2f}\n"
            f"💸 手续费: ${fee:.2f}\n"
            f"{emoji} <b>盈亏: ${net_pnl:+,.2f} ({pnl_pct:+.2f}%)</b>\n"
            f"━━━━━━━━━━━━━━\n"
            f"💰 当前余额: ${self.balance:,.2f}\n"
            f"📊 总盈亏: ${self.stats['total_pnl']:+,.2f}\n"
            f"💸 总手续费: ${self.stats['total_fees']:,.2f}\n"
            f"📈 胜率: {self.stats['win_rate']:.1f}%"
        )
        
        # 删除持仓
        del self.positions[symbol]
        
        return True
    
    def _save_trade_to_db(self, trade):
        """保存交易到数据库"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            INSERT INTO trades (timestamp, symbol, side, price, quantity, leverage, 
                              cost, fee, pnl, pnl_pct, reason, balance_after)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            trade['timestamp'],
            trade['symbol'],
            trade['side'],
            trade['price'],
            trade['quantity'],
            trade.get('leverage', 1),
            trade['cost'],
            trade['fee'],
            trade.get('pnl', 0),
            trade.get('pnl_pct', 0),
            trade.get('reason', ''),
            trade['balance_after']
        ))
        
        conn.commit()
        conn.close()
    
    def _save_position_to_db(self, symbol, position):
        """保存持仓到数据库"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            INSERT OR REPLACE INTO positions 
            (symbol, quantity, entry_price, entry_time, leverage, stop_loss, take_profit, cost, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            symbol,
            position['quantity'],
            position['entry_price'],
            position['entry_time'].isoformat(),
            position['leverage'],
            position['stop_loss'],
            position['take_profit'],
            position['cost'],
            'open'
        ))
        
        conn.commit()
        conn.close()
    
    def _update_position_status(self, symbol, status):
        """更新持仓状态"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('UPDATE positions SET status = ? WHERE symbol = ?', (status, symbol))
        
        conn.commit()
        conn.close()
    
    def _save_stats_to_db(self):
        """保存统计到数据库"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            INSERT INTO stats (timestamp, balance, total_pnl, total_trades, 
                             winning_trades, losing_trades, win_rate, total_fees)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ''', (
            datetime.now().isoformat(),
            self.balance,
            self.stats['total_pnl'],
            self.stats['total_trades'],
            self.stats['winning_trades'],
            self.stats['losing_trades'],
            self.stats['win_rate'],
            self.stats['total_fees']
        ))
        
        conn.commit()
        conn.close()
    
    def check_stop_loss_take_profit(self):
        """检查止损止盈"""
        for symbol, position in list(self.positions.items()):
            try:
                current_price = self.get_current_price(symbol)
                if not current_price:
                    continue
                
                # 检查止损
                if current_price <= position['stop_loss']:
                    print(f"🚨 触发止损: {symbol} @ ${current_price:.2f}")
                    self.simulate_sell(symbol, current_price, 
                                     position['quantity'], "止损")
                
                # 检查止盈
                elif current_price >= position['take_profit']:
                    print(f"🎉 触发止盈: {symbol} @ ${current_price:.2f}")
                    self.simulate_sell(symbol, current_price, 
                                     position['quantity'], "止盈")
                
            except Exception as e:
                print(f"❌ 检查失败 {symbol}: {e}")
    
    def display_portfolio(self):
        """显示投资组合"""
        print("\n" + "="*60)
        print(f"💼 投资组合状态 - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print("="*60)
        
        # 现金余额
        print(f"💰 现金余额: ${self.balance:.2f}")
        
        # 持仓
        total_position_value = 0
        total_unrealized_pnl = 0
        
        if self.positions:
            print("\n📊 当前持仓:")
            for symbol, pos in self.positions.items():
                current_price = self.get_current_price(symbol)
                if current_price:
                    position_value = pos['quantity'] * current_price
                    entry_value = pos['quantity'] * pos['entry_price']
                    
                    # 考虑杠杆的盈亏
                    unrealized_pnl = (position_value - entry_value) * pos['leverage']
                    unrealized_pnl_pct = (unrealized_pnl / pos['cost']) * 100
                    
                    total_position_value += position_value
                    total_unrealized_pnl += unrealized_pnl
                    
                    emoji = "🟢" if unrealized_pnl > 0 else "🔴"
                    print(f"\n  {symbol}:")
                    print(f"    数量: {pos['quantity']:.6f}")
                    print(f"    入场: ${pos['entry_price']:.2f}")
                    print(f"    现价: ${current_price:.2f}")
                    print(f"    杠杆: {pos['leverage']}x")
                    print(f"    {emoji} 浮盈: ${unrealized_pnl:+.2f} ({unrealized_pnl_pct:+.2f}%)")
        else:
            print("\n📊 当前持仓: 空仓")
        
        # 总资产
        total_equity = self.balance + total_unrealized_pnl
        total_pnl = total_equity - self.initial_balance
        total_return = (total_pnl / self.initial_balance) * 100
        
        print(f"\n💎 总资产: ${total_equity:.2f}")
        emoji = "🟢" if total_pnl > 0 else "🔴"
        print(f"{emoji} 总盈亏: ${total_pnl:+.2f} ({total_return:+.2f}%)")
        print(f"💸 累计手续费: ${self.total_fees:.2f}")
        
        # 交易统计
        if self.stats['total_trades'] > 0:
            print(f"\n📈 交易统计:")
            print(f"  总交易: {self.stats['total_trades']}")
            print(f"  胜率: {self.stats['win_rate']:.1f}%")
            print(f"  盈利: {self.stats['winning_trades']} | 亏损: {self.stats['losing_trades']}")
            print(f"  已实现盈亏: ${self.stats['total_pnl']:+.2f}")
        
        print("="*60 + "\n")
    
    def send_daily_report(self):
        """发送每日报表"""
        from datetime import date, timedelta
        
        today = date.today()
        
        # 检查是否已经发送过今天的报表
        if self.last_report_date == today:
            return
        
        print(f"\n{'='*60}")
        print(f"📊 生成每日报表 - {today}")
        print(f"{'='*60}")
        
        # 获取今天的交易记录
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        today_str = today.isoformat()
        cursor.execute('''
            SELECT * FROM trades 
            WHERE date(timestamp) = ? 
            ORDER BY timestamp DESC
        ''', (today_str,))
        
        trades = cursor.fetchall()
        
        # 计算今日统计
        daily_trades = len(trades)
        daily_pnl = sum(row[8] for row in trades if row[8])  # pnl字段
        daily_fees = sum(row[7] for row in trades)  # fee字段
        
        # 今日买入和卖出次数
        buy_count = sum(1 for row in trades if row[2] == 'buy')
        sell_count = sum(1 for row in trades if row[2] == 'sell')
        
        # 获取当前余额和总盈亏
        total_equity = self.balance
        for symbol, pos in self.positions.items():
            current_price = self.get_current_price(symbol)
            if current_price:
                position_value = pos['quantity'] * current_price
                entry_value = pos['quantity'] * pos['entry_price']
                unrealized_pnl = (position_value - entry_value) * pos['leverage']
                total_equity += unrealized_pnl
        
        total_pnl = total_equity - self.initial_balance
        total_return = (total_pnl / self.initial_balance) * 100
        
        # 生成报表内容
        report = f"<b>📊 每日交易报表</b>\n"
        report += f"━━━━━━━━━━━━━━\n"
        report += f"📅 日期: {today}\n"
        report += f"━━━━━━━━━━━━━━\n\n"
        
        report += f"<b>📈 今日交易</b>\n"
        report += f"总交易: {daily_trades} 笔\n"
        report += f"买入: {buy_count} | 卖出: {sell_count}\n"
        
        if daily_pnl != 0:
            pnl_emoji = "🟢" if daily_pnl > 0 else "🔴"
            report += f"{pnl_emoji} 今日盈亏: ${daily_pnl:+,.2f}\n"
        else:
            report += f"今日盈亏: $0.00\n"
        
        report += f"💸 今日手续费: ${daily_fees:.2f}\n"
        report += f"━━━━━━━━━━━━━━\n\n"
        
        report += f"<b>💼 账户状态</b>\n"
        report += f"💰 当前余额: ${self.balance:,.2f}\n"
        report += f"💎 总资产: ${total_equity:,.2f}\n"
        
        total_emoji = "🟢" if total_pnl > 0 else "🔴"
        report += f"{total_emoji} 总盈亏: ${total_pnl:+,.2f} ({total_return:+.2f}%)\n"
        report += f"💸 累计手续费: ${self.total_fees:,.2f}\n"
        report += f"━━━━━━━━━━━━━━\n\n"
        
        # 当前持仓
        if self.positions:
            report += f"<b>📊 当前持仓</b>\n"
            for symbol, pos in self.positions.items():
                current_price = self.get_current_price(symbol)
                if current_price:
                    position_value = pos['quantity'] * current_price
                    entry_value = pos['quantity'] * pos['entry_price']
                    unrealized_pnl = (position_value - entry_value) * pos['leverage']
                    unrealized_pnl_pct = (unrealized_pnl / pos['cost']) * 100
                    
                    position_type = pos.get('position_type', 'long')
                    pos_emoji = "📈" if position_type == 'long' else "📉"
                    pos_text = "做多" if position_type == 'long' else "做空"
                    
                    pnl_emoji = "🟢" if unrealized_pnl > 0 else "🔴"
                    report += f"{pos_emoji} {symbol} ({pos_text})\n"
                    report += f"   入场: ${pos['entry_price']:.2f} → 现价: ${current_price:.2f}\n"
                    report += f"   {pnl_emoji} 浮盈: ${unrealized_pnl:+.2f} ({unrealized_pnl_pct:+.2f}%)\n"
        else:
            report += f"<b>📊 当前持仓</b>\n空仓\n"
        
        report += f"━━━━━━━━━━━━━━\n\n"
        
        # 交易统计
        if self.stats['total_trades'] > 0:
            report += f"<b>📈 交易统计</b>\n"
            report += f"总交易: {self.stats['total_trades']} 笔\n"
            report += f"胜率: {self.stats['win_rate']:.1f}%\n"
            report += f"盈利: {self.stats['winning_trades']} | 亏损: {self.stats['losing_trades']}\n"
        
        # 打印到控制台
        print(report.replace('<b>', '').replace('</b>', ''))
        
        # 发送Telegram通知
        self._send_notification("📊 每日交易报表", report)
        
        # 更新报表日期
        self.last_report_date = today
        
        conn.close()
        
        print(f"✅ 每日报表已发送")
        print(f"{'='*60}\n")
    
    def check_daily_report_time(self):
        """检查是否到了发送报表的时间（凌晨1点）"""
        now = datetime.now()
        
        # 如果是凌晨1点且今天还没发送过报表
        if now.hour == 1 and now.minute < 5:  # 1:00-1:05之间
            from datetime import date
            if self.last_report_date != date.today():
                self.send_daily_report()


def test_trading():
    """测试交易功能"""
    print("🧪 测试交易系统...")
    
    bot = EnhancedPaperTradingBot(initial_balance=1000, leverage=3)
    
    # 测试买入BTC
    btc_price = bot.get_current_price('BTC/USDT')
    if btc_price:
        quantity, position_value, margin = bot.calculate_position_size('BTC/USDT', btc_price)
        print(f"\n测试买入 BTC/USDT:")
        print(f"  数量: {quantity:.6f}")
        print(f"  仓位价值: ${position_value:.2f}")
        print(f"  保证金: ${margin:.2f}")
        
        # 执行买入
        bot.simulate_buy('BTC/USDT', btc_price, quantity)
        
        # 显示持仓
        bot.display_portfolio()
    
    return bot


if __name__ == "__main__":
    test_trading()
