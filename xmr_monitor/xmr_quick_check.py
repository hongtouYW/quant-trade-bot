#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
XMR快速价格检查 - 单次运行
"""

import requests
import json
from datetime import datetime

class XMRQuickCheck:
    def __init__(self):
        self.entry_price = 502.41
        self.leverage = 10
        self.principal = 100
        
    def get_price(self):
        """获取当前价格 - 优先使用CoinGecko"""
        try:
            # CoinGecko API (更准确)
            url = 'https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd'
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                return data['monero']['usd']
        except:
            pass
            
        try:
            # 币安API作为备用
            url = "https://api.binance.com/api/v3/ticker/price?symbol=XMRUSDT"
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                return float(data['price'])
        except:
            pass
            
        return None
    
    def calculate_roi(self, current_price):
        """计算投资回报率"""
        price_change_percent = (current_price - self.entry_price) / self.entry_price * 100
        leveraged_roi = price_change_percent * self.leverage
        pnl_amount = (leveraged_roi / 100) * self.principal
        return price_change_percent, leveraged_roi, pnl_amount
    
    def check_now(self):
        """立即检查一次"""
        print(f"🎯 XMR快速价格检查 - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print("=" * 60)
        
        current_price = self.get_price()
        if not current_price:
            print("❌ 无法获取价格数据")
            return
        
        price_change, roi, pnl = self.calculate_roi(current_price)
        
        # 颜色显示
        if pnl >= 0:
            color = "\033[92m"  # 绿色
            symbol = "📈"
        else:
            color = "\033[91m"  # 红色
            symbol = "📉"
        reset = "\033[0m"
        
        print(f"💰 当前价格: ${current_price:.2f}")
        print(f"📊 入场价格: ${self.entry_price:.2f}")
        print(f"📈 价格变化: {price_change:+.2f}%")
        print(f"💎 杠杆倍数: {self.leverage}x")
        print(f"💵 投资回报率: {color}{roi:+.2f}%{reset}")
        print(f"💰 盈亏金额: {color}{symbol} ${pnl:+.2f}U{reset} (本金{self.principal}U)")
        
        # 风险提示
        stop_loss_price = self.entry_price * 0.98  # -2%
        take_profit_price = self.entry_price * 1.02  # +2%
        
        if current_price <= stop_loss_price:
            print("🚨 价格已触及止损线！")
        elif current_price >= take_profit_price:
            print("🎉 价格已达到止盈目标！")
        else:
            distance_to_stop = ((current_price - stop_loss_price) / current_price) * 100
            distance_to_profit = ((take_profit_price - current_price) / current_price) * 100
            print(f"📊 距止损: {distance_to_stop:.1f}% | 距止盈: {distance_to_profit:.1f}%")
        
        print("=" * 60)

if __name__ == "__main__":
    checker = XMRQuickCheck()
    checker.check_now()