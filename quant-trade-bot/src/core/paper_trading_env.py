#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
模拟交易环境
用于安全测试策略，无实际资金风险
"""

import json
import time
from datetime import datetime, timedelta
import pandas as pd
import numpy as np

class PaperTradingEnvironment:
    """纸面交易环境"""
    
    def __init__(self, initial_balance=10000):
        self.initial_balance = initial_balance
        self.balance = initial_balance
        self.positions = {}
        self.trade_history = []
        self.start_time = datetime.now()
        self.performance_stats = {
            'total_trades': 0,
            'winning_trades': 0,
            'losing_trades': 0,
            'total_pnl': 0,
            'max_drawdown': 0,
            'current_drawdown': 0,
            'max_balance': initial_balance
        }
        
        print(f"🎯 模拟交易环境初始化完成")
        print(f"💰 初始资金: ${initial_balance:,.2f}")
        print(f"⏰ 开始时间: {self.start_time.strftime('%Y-%m-%d %H:%M:%S')}")
    
    def place_order(self, symbol, side, amount, price, order_type='market'):
        """下单"""
        timestamp = datetime.now()
        
        # 模拟订单费用 (0.1%)
        fee_rate = 0.001
        total_cost = amount * price
        fee = total_cost * fee_rate
        
        if side == 'buy':
            if self.balance < total_cost + fee:
                print(f"❌ 余额不足: 需要 ${total_cost + fee:.2f}, 当前 ${self.balance:.2f}")
                return False
                
            # 执行买入
            self.balance -= (total_cost + fee)
            
            if symbol in self.positions:
                # 增加持仓
                old_amount = self.positions[symbol]['amount']
                old_avg_price = self.positions[symbol]['avg_price']
                new_amount = old_amount + amount
                new_avg_price = (old_amount * old_avg_price + amount * price) / new_amount
                
                self.positions[symbol] = {
                    'amount': new_amount,
                    'avg_price': new_avg_price,
                    'side': 'long',
                    'entry_time': self.positions[symbol]['entry_time']
                }
            else:
                # 新建持仓
                self.positions[symbol] = {
                    'amount': amount,
                    'avg_price': price,
                    'side': 'long',
                    'entry_time': timestamp
                }
            
            print(f"✅ 买入成功: {amount:.6f} {symbol} @ ${price:.2f}")
            
        elif side == 'sell':
            if symbol not in self.positions or self.positions[symbol]['amount'] < amount:
                print(f"❌ 持仓不足: {symbol}")
                return False
                
            # 执行卖出
            revenue = amount * price - fee
            self.balance += revenue
            
            # 计算盈亏
            avg_price = self.positions[symbol]['avg_price']
            pnl = (price - avg_price) * amount - fee
            
            # 更新持仓
            self.positions[symbol]['amount'] -= amount
            
            if self.positions[symbol]['amount'] <= 0:
                # 完全平仓
                del self.positions[symbol]
            
            # 记录交易
            trade = {
                'timestamp': timestamp,
                'symbol': symbol,
                'side': side,
                'amount': amount,
                'price': price,
                'pnl': pnl,
                'fee': fee,
                'balance_after': self.balance
            }
            
            self.trade_history.append(trade)
            self.update_stats(trade)
            
            print(f"✅ 卖出成功: {amount:.6f} {symbol} @ ${price:.2f}, 盈亏: ${pnl:.2f}")
        
        return True
    
    def update_stats(self, trade):
        """更新统计数据"""
        self.performance_stats['total_trades'] += 1
        
        if trade['pnl'] > 0:
            self.performance_stats['winning_trades'] += 1
        else:
            self.performance_stats['losing_trades'] += 1
        
        self.performance_stats['total_pnl'] += trade['pnl']
        
        # 更新最大余额和回撤
        current_total = self.get_total_value()
        if current_total > self.performance_stats['max_balance']:
            self.performance_stats['max_balance'] = current_total
        
        drawdown = (self.performance_stats['max_balance'] - current_total) / self.performance_stats['max_balance']
        if drawdown > self.performance_stats['max_drawdown']:
            self.performance_stats['max_drawdown'] = drawdown
        
        self.performance_stats['current_drawdown'] = drawdown
    
    def get_total_value(self, current_prices=None):
        """获取总资产价值"""
        total_value = self.balance
        
        if current_prices:
            for symbol, position in self.positions.items():
                if symbol in current_prices:
                    total_value += position['amount'] * current_prices[symbol]
        
        return total_value
    
    def get_performance_report(self):
        """获取交易表现报告"""
        total_trades = self.performance_stats['total_trades']
        
        if total_trades == 0:
            win_rate = 0
        else:
            win_rate = self.performance_stats['winning_trades'] / total_trades * 100
        
        current_total = self.get_total_value()
        total_return = (current_total - self.initial_balance) / self.initial_balance * 100
        
        report = {
            '🕐 交易时长': str(datetime.now() - self.start_time),
            '💰 初始资金': f"${self.initial_balance:,.2f}",
            '💵 当前现金': f"${self.balance:,.2f}",
            '📊 总资产价值': f"${current_total:,.2f}",
            '📈 总收益率': f"{total_return:+.2f}%",
            '🎯 交易次数': total_trades,
            '✅ 胜率': f"{win_rate:.1f}%",
            '💸 总盈亏': f"${self.performance_stats['total_pnl']:+,.2f}",
            '📉 最大回撤': f"{self.performance_stats['max_drawdown']:.2%}",
            '📊 当前回撤': f"{self.performance_stats['current_drawdown']:.2%}",
            '👥 持仓数量': len(self.positions)
        }
        
        return report
    
    def print_performance(self):
        """打印表现报告"""
        report = self.get_performance_report()
        
        print("\n" + "=" * 50)
        print("📊 模拟交易表现报告")
        print("=" * 50)
        
        for key, value in report.items():
            print(f"{key}: {value}")
        
        if self.positions:
            print("\n💼 当前持仓:")
            for symbol, pos in self.positions.items():
                print(f"   {symbol}: {pos['amount']:.6f} @ ${pos['avg_price']:.2f}")
    
    def save_results(self, filename=None):
        """保存交易结果"""
        if filename is None:
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = f"paper_trading_results_{timestamp}.json"
        
        results = {
            'environment_info': {
                'initial_balance': self.initial_balance,
                'start_time': self.start_time.isoformat(),
                'end_time': datetime.now().isoformat()
            },
            'performance_stats': self.performance_stats,
            'current_balance': self.balance,
            'positions': {
                symbol: {
                    **pos,
                    'entry_time': pos['entry_time'].isoformat()
                }
                for symbol, pos in self.positions.items()
            },
            'trade_history': [
                {
                    **trade,
                    'timestamp': trade['timestamp'].isoformat() if hasattr(trade['timestamp'], 'isoformat') else str(trade['timestamp'])
                }
                for trade in self.trade_history
            ],
            'performance_report': self.get_performance_report()
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2)
        
        print(f"\n📁 交易结果已保存到: {filename}")
        return filename


class MockMarketData:
    """模拟市场数据"""
    
    @staticmethod
    def generate_realistic_price_movement(start_price, periods=100, volatility=0.02):
        """生成真实的价格走势"""
        # 使用几何布朗运动模拟价格
        returns = np.random.normal(0, volatility, periods)
        prices = [start_price]
        
        for i in range(1, periods):
            new_price = prices[-1] * (1 + returns[i])
            prices.append(new_price)
        
        return prices
    
    @staticmethod
    def get_current_mock_prices():
        """获取当前模拟价格"""
        base_prices = {
            'BTC/USDT': 95000,
            'ETH/USDT': 3500,
            'BNB/USDT': 600
        }
        
        # 添加小幅随机波动
        current_prices = {}
        for symbol, base_price in base_prices.items():
            volatility = np.random.normal(0, 0.01)  # 1%波动
            current_prices[symbol] = base_price * (1 + volatility)
        
        return current_prices


def demo_paper_trading():
    """演示纸面交易"""
    print("🎯 启动模拟交易演示")
    print("=" * 50)
    
    # 初始化环境
    env = PaperTradingEnvironment(initial_balance=10000)
    
    # 模拟一些交易
    print("\n📈 模拟交易执行:")
    print("-" * 30)
    
    # 买入BTC
    env.place_order('BTC/USDT', 'buy', 0.1, 95000)
    
    # 买入ETH
    env.place_order('ETH/USDT', 'buy', 1.0, 3500)
    
    # 模拟价格变化后卖出
    time.sleep(1)
    env.place_order('BTC/USDT', 'sell', 0.05, 96000)  # 盈利卖出
    
    time.sleep(1)
    env.place_order('ETH/USDT', 'sell', 0.5, 3400)   # 小亏损卖出
    
    # 打印结果
    env.print_performance()
    
    # 保存结果
    env.save_results()
    
    return env


if __name__ == '__main__':
    demo_paper_trading()