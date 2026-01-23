#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
实盘模拟交易系统 - 基于真实市场数据的模拟交易
特点:
1. 使用真实市场价格
2. 模拟订单滑点和手续费
3. 实时风险管理
4. Telegram实时通知
5. 完整的交易记录
"""

import ccxt
import json
import time
from datetime import datetime
import pandas as pd
import numpy as np
import os
import sys

# 添加utils路径
sys.path.append(os.path.dirname(os.path.abspath(__file__)))
try:
    from utils.telegram_notify import TelegramNotify
except:
    TelegramNotify = None

class LivePaperTradingBot:
    """实盘模拟交易机器人"""
    
    def __init__(self, initial_balance=1000, config_file='config.json'):
        # 初始资金
        self.initial_balance = initial_balance
        self.balance = initial_balance
        self.positions = {}
        self.trade_history = []
        self.start_time = datetime.now()
        
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
        
        # 初始化交易所
        self.exchange = ccxt.binance({
            'enableRateLimit': True,
            'timeout': 30000
        })
        
        # 初始化Telegram
        self.telegram = self._init_telegram()
        
        # 性能统计
        self.stats = {
            'total_trades': 0,
            'winning_trades': 0,
            'losing_trades': 0,
            'total_pnl': 0,
            'win_rate': 0,
            'max_drawdown': 0,
            'sharpe_ratio': 0
        }
        
        print("🎯 实盘模拟交易系统启动")
        print(f"💰 初始资金: ${initial_balance:,.2f}")
        print(f"📊 交易品种: {', '.join(self.symbols)}")
        print(f"⚠️ 单笔风险: {self.risk_per_trade*100}%")
        print(f"🛡️ 止损: {self.stop_loss_pct*100}% | 🎯 止盈: {self.take_profit_pct*100}%")
        
        if self.telegram:
            self._send_notification("🚀 实盘模拟交易启动", 
                                   f"初始资金: ${initial_balance:,.2f}")
    
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
        """计算仓位大小"""
        # 基于风险的仓位计算
        risk_amount = self.balance * self.risk_per_trade
        position_value = risk_amount / self.stop_loss_pct
        
        # 限制最大仓位
        max_position_value = self.balance * self.max_position_size
        position_value = min(position_value, max_position_value)
        
        # 计算数量
        quantity = position_value / entry_price
        
        return quantity, position_value
    
    def simulate_buy(self, symbol, price, quantity):
        """模拟买入"""
        # 模拟滑点
        actual_price = price * (1 + self.slippage)
        
        # 计算总成本（含手续费）
        cost = quantity * actual_price
        fee = cost * self.taker_fee
        total_cost = cost + fee
        
        if self.balance < total_cost:
            print(f"❌ 余额不足: 需要${total_cost:.2f}, 当前${self.balance:.2f}")
            return False
        
        # 扣除资金
        self.balance -= total_cost
        
        # 记录持仓
        self.positions[symbol] = {
            'quantity': quantity,
            'entry_price': actual_price,
            'entry_time': datetime.now(),
            'stop_loss': actual_price * (1 - self.stop_loss_pct),
            'take_profit': actual_price * (1 + self.take_profit_pct),
            'cost': total_cost
        }
        
        # 记录交易
        trade = {
            'timestamp': datetime.now().isoformat(),
            'symbol': symbol,
            'side': 'buy',
            'price': actual_price,
            'quantity': quantity,
            'cost': total_cost,
            'fee': fee
        }
        self.trade_history.append(trade)
        
        print(f"✅ 模拟买入: {quantity:.6f} {symbol} @ ${actual_price:.2f}")
        print(f"   成本: ${total_cost:.2f} (含手续费${fee:.2f})")
        print(f"   止损: ${self.positions[symbol]['stop_loss']:.2f}")
        print(f"   止盈: ${self.positions[symbol]['take_profit']:.2f}")
        
        # 发送通知
        self._send_notification(
            f"📈 买入 {symbol}",
            f"价格: ${actual_price:.2f}\n"
            f"数量: {quantity:.6f}\n"
            f"成本: ${total_cost:.2f}\n"
            f"止损: ${self.positions[symbol]['stop_loss']:.2f}\n"
            f"止盈: ${self.positions[symbol]['take_profit']:.2f}"
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
        
        # 计算收益
        revenue = quantity * actual_price
        fee = revenue * self.taker_fee
        net_revenue = revenue - fee
        
        # 计算盈亏
        cost_basis = (position['cost'] / position['quantity']) * quantity
        pnl = net_revenue - cost_basis
        pnl_pct = (pnl / cost_basis) * 100
        
        # 更新余额
        self.balance += net_revenue
        
        # 更新统计
        self.stats['total_trades'] += 1
        self.stats['total_pnl'] += pnl
        
        if pnl > 0:
            self.stats['winning_trades'] += 1
            emoji = "🟢"
        else:
            self.stats['losing_trades'] += 1
            emoji = "🔴"
        
        self.stats['win_rate'] = (self.stats['winning_trades'] / 
                                  self.stats['total_trades'] * 100)
        
        # 记录交易
        trade = {
            'timestamp': datetime.now().isoformat(),
            'symbol': symbol,
            'side': 'sell',
            'price': actual_price,
            'quantity': quantity,
            'revenue': net_revenue,
            'fee': fee,
            'pnl': pnl,
            'pnl_pct': pnl_pct,
            'reason': reason
        }
        self.trade_history.append(trade)
        
        # 删除持仓
        del self.positions[symbol]
        
        print(f"✅ 模拟卖出: {quantity:.6f} {symbol} @ ${actual_price:.2f}")
        print(f"   收益: ${net_revenue:.2f} (含手续费${fee:.2f})")
        print(f"   {emoji} 盈亏: ${pnl:+.2f} ({pnl_pct:+.2f}%)")
        print(f"   原因: {reason}")
        
        # 发送通知
        self._send_notification(
            f"📉 卖出 {symbol} - {reason}",
            f"价格: ${actual_price:.2f}\n"
            f"数量: {quantity:.6f}\n"
            f"收益: ${net_revenue:.2f}\n"
            f"{emoji} 盈亏: ${pnl:+.2f} ({pnl_pct:+.2f}%)\n"
            f"━━━━━━━━━━━━━━\n"
            f"当前余额: ${self.balance:.2f}\n"
            f"总盈亏: ${self.stats['total_pnl']:+.2f}\n"
            f"胜率: {self.stats['win_rate']:.1f}%"
        )
        
        return True
    
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
        """显示投资组合状态"""
        print("\n" + "="*60)
        print(f"💼 投资组合状态 - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print("="*60)
        
        # 现金余额
        print(f"💰 现金余额: ${self.balance:.2f}")
        
        # 持仓
        total_position_value = 0
        if self.positions:
            print("\n📊 当前持仓:")
            for symbol, pos in self.positions.items():
                current_price = self.get_current_price(symbol)
                if current_price:
                    current_value = pos['quantity'] * current_price
                    unrealized_pnl = current_value - pos['cost']
                    unrealized_pnl_pct = (unrealized_pnl / pos['cost']) * 100
                    
                    total_position_value += current_value
                    
                    emoji = "🟢" if unrealized_pnl > 0 else "🔴"
                    print(f"  {symbol}:")
                    print(f"    数量: {pos['quantity']:.6f}")
                    print(f"    入场: ${pos['entry_price']:.2f}")
                    print(f"    现价: ${current_price:.2f}")
                    print(f"    {emoji} 浮盈: ${unrealized_pnl:+.2f} ({unrealized_pnl_pct:+.2f}%)")
        else:
            print("\n📊 当前持仓: 空仓")
        
        # 总资产
        total_equity = self.balance + total_position_value
        total_pnl = total_equity - self.initial_balance
        total_return = (total_pnl / self.initial_balance) * 100
        
        print(f"\n💎 总资产: ${total_equity:.2f}")
        emoji = "🟢" if total_pnl > 0 else "🔴"
        print(f"{emoji} 总盈亏: ${total_pnl:+.2f} ({total_return:+.2f}%)")
        
        # 交易统计
        if self.stats['total_trades'] > 0:
            print(f"\n📈 交易统计:")
            print(f"  总交易: {self.stats['total_trades']}")
            print(f"  胜率: {self.stats['win_rate']:.1f}%")
            print(f"  盈利: {self.stats['winning_trades']} | 亏损: {self.stats['losing_trades']}")
        
        print("="*60 + "\n")
    
    def save_results(self):
        """保存交易结果"""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        filename = f"live_paper_trading_{timestamp}.json"
        
        results = {
            'start_time': self.start_time.isoformat(),
            'end_time': datetime.now().isoformat(),
            'initial_balance': self.initial_balance,
            'final_balance': self.balance,
            'positions': {k: {**v, 'entry_time': v['entry_time'].isoformat()} 
                         for k, v in self.positions.items()},
            'trade_history': self.trade_history,
            'stats': self.stats
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(results, f, indent=2, ensure_ascii=False)
        
        print(f"✅ 结果已保存: {filename}")
        return filename


def demo_strategy():
    """演示策略 - 简单的趋势跟踪"""
    bot = LivePaperTradingBot(initial_balance=1000)
    
    print("\n🎯 开始实盘模拟交易...")
    print("策略: 简单趋势跟踪 + 止损止盈")
    print("监控中...\n")
    
    try:
        check_count = 0
        while True:
            # 检查止损止盈
            bot.check_stop_loss_take_profit()
            
            # 每10次检查显示一次状态
            if check_count % 10 == 0:
                bot.display_portfolio()
            
            # 示例：简单买入信号（实际应该基于技术指标）
            # 这里仅作演示，实际使用时替换为你的策略
            
            # 等待30秒
            time.sleep(30)
            check_count += 1
            
    except KeyboardInterrupt:
        print("\n👋 停止交易...")
        bot.display_portfolio()
        bot.save_results()


if __name__ == "__main__":
    demo_strategy()