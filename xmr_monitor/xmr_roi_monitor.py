#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
XMR监控 - 投资回报率百分比和颜色显示系统
入场价格: $502.41, 杠杆: 10x
"""

import ccxt
import time
import json
import os
from datetime import datetime

class XMRROIMonitor:
    """XMR投资回报率监控器 - 专注于ROI和颜色显示"""
    
    def __init__(self, entry_price=502.41, leverage=10, principal=100):
        self.entry_price = entry_price
        self.leverage = leverage  
        self.principal = principal  # 本金
        self.symbol = 'XMR/USDT'
        
        # 初始化交易所
        self.exchange = ccxt.binance({
            'apiKey': '',
            'secret': '',
            'sandbox': False,
            'options': {
                'defaultType': 'spot'  # 现货API获取价格
            }
        })
        
        print(f"🎯 XMR投资回报率监控系统")
        print(f"💰 入场价格: ${self.entry_price:.2f}")
        print(f"📊 杠杆倍数: {self.leverage}x")
        print(f"💎 本金: {self.principal}U")
        print("=" * 50)
    
    def get_current_price(self):
        """获取当前XMR价格"""
        try:
            ticker = self.exchange.fetch_ticker(self.symbol)
            return ticker['last']
        except Exception as e:
            print(f"❌ 获取价格失败: {e}")
            return self.entry_price
    
    def calculate_roi_data(self, current_price):
        """计算详细的投资回报率数据"""
        # 价格变化百分比
        price_change_percent = ((current_price - self.entry_price) / self.entry_price) * 100
        
        # 杠杆后的收益率
        leveraged_return_percent = price_change_percent * self.leverage
        
        # 盈亏金额 (基于本金)
        pnl_amount = self.principal * (leveraged_return_percent / 100)
        
        return {
            'current_price': current_price,
            'price_change_percent': price_change_percent,
            'roi_percent': leveraged_return_percent,
            'pnl_amount': pnl_amount,
            'total_balance': self.principal + pnl_amount
        }
    
    def format_with_color(self, amount, show_emoji=True):
        """格式化金额并添加颜色"""
        if amount >= 0:
            # 盈利 - 绿色
            color_code = '\033[92m'  # 绿色
            emoji = '🟢' if show_emoji else ''
            sign = '+'
        else:
            # 亏损 - 红色  
            color_code = '\033[91m'  # 红色
            emoji = '🔴' if show_emoji else ''
            sign = ''  # 负数自带负号
        
        reset_code = '\033[0m'
        return f"{color_code}{emoji}{sign}{amount:.2f}{reset_code}"
    
    def display_roi_status(self):
        """显示投资回报率状态 - 带颜色和百分比"""
        current_price = self.get_current_price()
        roi_data = self.calculate_roi_data(current_price)
        
        print(f"\n📊 {datetime.now().strftime('%H:%M:%S')} XMR投资回报率状态")
        print(f"💰 当前价格: ${roi_data['current_price']:.2f}")
        print(f"📈 入场价格: ${self.entry_price:.2f}")
        print(f"📊 价格变化: {self.format_with_color(roi_data['price_change_percent'], False)}%")
        print(f"💎 杠杆倍数: {self.leverage}x")
        
        # 投资回报率百分比 (带颜色)
        roi_percent = roi_data['roi_percent']
        print(f"💵 投资回报率: {self.format_with_color(roi_percent, False)}%")
        
        # 盈亏金额 (带颜色和表情)
        pnl_amount = roi_data['pnl_amount']
        print(f"💰 盈亏金额: {self.format_with_color(pnl_amount)}U")
        
        # 总余额
        total_balance = roi_data['total_balance']
        print(f"💳 总余额: {self.format_with_color(total_balance)}U (本金{self.principal}U)")
        
        print("-" * 50)
        
        return roi_data
    
    def run_monitor(self, interval=60):
        """运行监控 - 每分钟更新一次"""
        print(f"🚀 开始XMR投资回报率监控")
        print(f"⏰ 刷新间隔: {interval}秒")
        print(f"🎯 专注显示: 投资回报率百分比 + 红绿颜色")
        print("按 Ctrl+C 停止监控")
        print("=" * 50)
        
        try:
            while True:
                self.display_roi_status()
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")
        except Exception as e:
            print(f"❌ 监控错误: {e}")

if __name__ == "__main__":
    # 创建监控实例
    monitor = XMRROIMonitor(entry_price=502.41, leverage=10, principal=100)
    
    # 先显示一次当前状态
    monitor.display_roi_status()
    
    # 开始持续监控
    monitor.run_monitor(interval=60)