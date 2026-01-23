#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
XMR自动监控系统 - 有网络就自动跑
投资回报率百分比 + 红绿颜色显示
"""

import ccxt
import time
import requests
import json
from datetime import datetime
import os

class XMRAutoMonitor:
    """XMR自动监控 - 专注于投资回报率和颜色显示"""
    
    def __init__(self):
        self.entry_price = 502.41  # 入场价格
        self.leverage = 10         # 杠杆倍数
        self.principal = 100       # 本金100U
        self.symbol = 'XMRUSDT'    # 币安symbol格式
        
        print(f"🚀 XMR自动监控系统启动")
        print(f"💰 入场价格: ${self.entry_price:.2f}")
        print(f"📊 杠杆倍数: {self.leverage}x") 
        print(f"💎 本金: {self.principal}U")
        print(f"📡 只要有网络就会自动运行...")
        print("=" * 50)
    
    def get_price_binance_api(self):
        """使用币安公开API获取价格（无需API key）"""
        try:
            url = f"https://api.binance.com/api/v3/ticker/price?symbol={self.symbol}"
            response = requests.get(url, timeout=10)
            
            if response.status_code == 200:
                data = response.json()
                return float(data['price'])
            else:
                print(f"❌ API错误: {response.status_code}")
                return None
                
        except Exception as e:
            print(f"❌ 网络错误: {e}")
            return None
    
    def calculate_roi(self, current_price):
        """计算投资回报率数据"""
        if not current_price:
            return None
            
        # 价格变化百分比
        price_change = ((current_price - self.entry_price) / self.entry_price) * 100
        
        # 杠杆后收益率
        roi_percent = price_change * self.leverage
        
        # 盈亏金额
        pnl_amount = self.principal * (roi_percent / 100)
        
        # 总余额
        total_balance = self.principal + pnl_amount
        
        return {
            'current_price': current_price,
            'price_change': price_change,
            'roi_percent': roi_percent,
            'pnl_amount': pnl_amount,
            'total_balance': total_balance
        }
    
    def format_with_color(self, value, is_percent=False):
        """格式化数值并添加颜色"""
        if value >= 0:
            # 盈利 - 绿色
            color = '\033[92m'  # 绿色
            emoji = '🟢'
            sign = '+'
        else:
            # 亏损 - 红色
            color = '\033[91m'  # 红色  
            emoji = '🔴'
            sign = ''  # 负数自带负号
        
        reset = '\033[0m'
        
        if is_percent:
            return f"{color}{emoji}{sign}{value:.2f}%{reset}"
        else:
            return f"{color}{emoji}${sign}{value:.2f}U{reset}"
    
    def display_status(self, roi_data):
        """显示监控状态"""
        if not roi_data:
            print(f"❌ {datetime.now().strftime('%H:%M:%S')} 无法获取价格数据")
            return
            
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        print(f"\n📊 {timestamp} XMR投资回报率监控")
        print(f"💰 当前价格: ${roi_data['current_price']:.2f}")
        print(f"📈 入场价格: ${self.entry_price:.2f}")
        print(f"📊 价格变化: {self.format_with_color(roi_data['price_change'], True)}")
        print(f"💎 杠杆倍数: {self.leverage}x")
        print(f"💵 投资回报率: {self.format_with_color(roi_data['roi_percent'], True)}")
        print(f"💰 盈亏金额: {self.format_with_color(roi_data['pnl_amount'])}")
        print(f"💳 总余额: {self.format_with_color(roi_data['total_balance'])}")
        print("-" * 50)
    
    def check_network(self):
        """检查网络连接"""
        try:
            response = requests.get("https://api.binance.com/api/v3/ping", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def run_forever(self):
        """自动运行 - 有网络就监控"""
        print("🌐 检查网络连接...")
        
        retry_count = 0
        max_retries = 5
        
        while True:
            try:
                # 检查网络
                if not self.check_network():
                    print(f"❌ 网络连接失败，30秒后重试...")
                    time.sleep(30)
                    continue
                
                # 获取价格和计算数据
                current_price = self.get_price_binance_api()
                roi_data = self.calculate_roi(current_price)
                
                # 显示状态
                self.display_status(roi_data)
                
                # 重置错误计数
                retry_count = 0
                
                # 等待60秒
                print("⏳ 下次更新60秒后...")
                time.sleep(60)
                
            except KeyboardInterrupt:
                print("\n👋 监控已停止")
                break
                
            except Exception as e:
                retry_count += 1
                print(f"❌ 错误 ({retry_count}/{max_retries}): {e}")
                
                if retry_count >= max_retries:
                    print(f"❌ 连续错误次数过多，等待5分钟后重启...")
                    time.sleep(300)  # 等待5分钟
                    retry_count = 0
                else:
                    time.sleep(30)  # 等待30秒重试

if __name__ == "__main__":
    monitor = XMRAutoMonitor()
    
    # 显示启动信息
    print("\n🎯 XMR自动监控功能:")
    print("✅ 投资回报率百分比显示")  
    print("✅ 盈亏红绿颜色显示")
    print("✅ 自动网络检测")
    print("✅ 错误自动恢复")
    print("✅ 有网络就自动运行")
    print("\n按 Ctrl+C 停止监控")
    print("=" * 50)
    
    # 开始自动监控
    monitor.run_forever()