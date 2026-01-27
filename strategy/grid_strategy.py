import pandas as pd
import numpy as np
from decimal import Decimal

class GridStrategy:
    """网格交易策略 - 适合震荡市场，稳定盈利"""
    
    def __init__(self, base_price=None, grid_size=0.01, grid_count=10, order_amount=100):
        """
        :param base_price: 基准价格，如果为None则自动设置
        :param grid_size: 网格间距（比例），如0.01表示1%
        :param grid_count: 网格数量（上下各多少个）
        :param order_amount: 每格订单金额
        """
        self.base_price = base_price
        self.grid_size = grid_size
        self.grid_count = grid_count
        self.order_amount = order_amount
        self.buy_orders = {}   # {price: amount}
        self.sell_orders = {}  # {price: amount}
        self.position = 0
        self.total_profit = 0
        
    def initialize(self, current_price):
        """初始化网格"""
        if self.base_price is None:
            self.base_price = current_price
            
        # 清空现有订单
        self.buy_orders = {}
        self.sell_orders = {}
        
        # 创建买单网格（基准价格下方）
        for i in range(1, self.grid_count + 1):
            price = self.base_price * (1 - self.grid_size * i)
            self.buy_orders[round(price, 2)] = self.order_amount
            
        # 创建卖单网格（基准价格上方）
        for i in range(1, self.grid_count + 1):
            price = self.base_price * (1 + self.grid_size * i)
            self.sell_orders[round(price, 2)] = self.order_amount
            
    def check_signals(self, current_price, executed_orders=None):
        """
        检查网格交易信号
        :param current_price: 当前价格
        :param executed_orders: 已执行订单列表
        :return: 新的订单列表
        """
        signals = []
        
        # 检查买单成交
        executed_buys = []
        for price, amount in self.buy_orders.items():
            if current_price <= price:
                signals.append({
                    'type': 'buy',
                    'price': price,
                    'amount': amount / price,
                    'reason': f'网格买单成交 @{price}'
                })
                executed_buys.append(price)
                self.position += amount / price
                
        # 移除已执行的买单，添加新的卖单
        for price in executed_buys:
            del self.buy_orders[price]
            # 在上方添加对应的卖单
            sell_price = round(price * (1 + self.grid_size), 2)
            self.sell_orders[sell_price] = self.order_amount
            
        # 检查卖单成交
        executed_sells = []
        for price, amount in self.sell_orders.items():
            if current_price >= price:
                signals.append({
                    'type': 'sell',
                    'price': price,
                    'amount': amount / price,
                    'reason': f'网格卖单成交 @{price}'
                })
                executed_sells.append(price)
                self.position -= amount / price
                
                # 计算利润
                buy_price = price / (1 + self.grid_size)
                profit = (price - buy_price) * (amount / price)
                self.total_profit += profit
                
        # 移除已执行的卖单，添加新的买单
        for price in executed_sells:
            del self.sell_orders[price]
            # 在下方添加对应的买单
            buy_price = round(price * (1 - self.grid_size), 2)
            self.buy_orders[buy_price] = self.order_amount
            
        return signals
    
    def get_status(self):
        """获取策略状态"""
        return {
            'strategy': 'Grid Trading',
            'base_price': self.base_price,
            'position': self.position,
            'total_profit': self.total_profit,
            'active_buy_orders': len(self.buy_orders),
            'active_sell_orders': len(self.sell_orders),
            'grid_range': f"{min(self.buy_orders.keys()) if self.buy_orders else 'N/A'} - {max(self.sell_orders.keys()) if self.sell_orders else 'N/A'}"
        }