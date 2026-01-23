#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
集成实盘模拟系统 - 策略 + 实盘模拟 + Telegram通知
完整的自动化交易系统
"""

import sys
import os
import time
from datetime import datetime
import json

# 添加路径
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

# 尝试使用增强版策略，如果失败则使用简化版
try:
    from enhanced_strategy import EnhancedMultiTimeframeStrategy as StrategyEngine
except:
    from simple_enhanced_strategy import SimpleEnhancedStrategy as StrategyEngine

from live_paper_trading import LivePaperTradingBot
import ccxt


class IntegratedTradingSystem:
    """集成交易系统"""
    
    def __init__(self, initial_balance=1000, config_file='config.json'):
        print("🚀 初始化集成交易系统...")
        
        # 初始化交易所
        self.exchange = ccxt.binance({
            'enableRateLimit': True,
            'timeout': 30000
        })
        
        # 初始化策略
        self.strategy = StrategyEngine(self.exchange)
        
        # 初始化模拟交易
        self.bot = LivePaperTradingBot(initial_balance, config_file)
        
        # 交易对
        self.symbols = ['BTC/USDT', 'ETH/USDT', 'XMR/USDT', 'BNB/USDT', 'SOL/USDT']
        
        # 运行参数
        self.scan_interval = 300  # 5分钟扫描一次
        self.check_interval = 30  # 30秒检查一次止损止盈
        
        print("✅ 系统初始化完成")
    
    def execute_signal(self, symbol, signal):
        """执行交易信号"""
        if signal['type'] == 'buy':
            # 检查是否已有持仓
            if symbol in self.bot.positions:
                print(f"⚠️ {symbol} 已有持仓，跳过")
                return
            
            # 计算仓位
            quantity, position_value = self.bot.calculate_position_size(
                symbol, signal['price']
            )
            
            # 检查风险收益比
            if signal['type'] == 'buy':
                risk = signal['price'] - signal['stop_loss']
                reward = signal['take_profit'] - signal['price']
            else:
                risk = signal['stop_loss'] - signal['price']
                reward = signal['price'] - signal['take_profit']
            
            rr_ratio = reward / risk if risk > 0 else 0
            
            # 风险收益比至少要1:2
            if rr_ratio < 2:
                print(f"⚠️ {symbol} 风险收益比不足 (1:{rr_ratio:.2f}), 跳过")
                return
            
            # 执行买入
            success = self.bot.simulate_buy(symbol, signal['price'], quantity)
            
            if success:
                # 更新止损止盈
                self.bot.positions[symbol]['stop_loss'] = signal['stop_loss']
                self.bot.positions[symbol]['take_profit'] = signal['take_profit']
                
                print(f"✅ 交易执行成功")
                print(f"   信号条件: {', '.join(signal['conditions'])}")
                print(f"   风险收益比: 1:{rr_ratio:.2f}")
        
        elif signal['type'] == 'sell':
            # 如果有多头持仓，平仓
            if symbol in self.bot.positions:
                position = self.bot.positions[symbol]
                self.bot.simulate_sell(
                    symbol, 
                    signal['price'], 
                    position['quantity'],
                    "策略信号"
                )
    
    def run(self):
        """运行交易系统"""
        print("\n" + "="*60)
        print("🎯 集成交易系统启动")
        print("="*60)
        print(f"💰 初始资金: ${self.bot.initial_balance:,.2f}")
        print(f"📊 监控品种: {', '.join(self.symbols)}")
        print(f"🔄 扫描间隔: {self.scan_interval}秒")
        print(f"⏱️ 检查间隔: {self.check_interval}秒")
        print("="*60 + "\n")
        
        last_scan_time = 0
        check_count = 0
        
        try:
            while True:
                current_time = time.time()
                
                # 定期扫描市场信号
                if current_time - last_scan_time >= self.scan_interval:
                    print(f"\n🔍 扫描市场... ({datetime.now().strftime('%H:%M:%S')})")
                    
                    signals = self.strategy.scan_markets(self.symbols)
                    
                    # 执行信号
                    for symbol, signal in signals.items():
                        self.execute_signal(symbol, signal)
                    
                    last_scan_time = current_time
                
                # 检查止损止盈
                self.bot.check_stop_loss_take_profit()
                
                # 定期显示状态 (每10次检查)
                if check_count % 10 == 0:
                    self.bot.display_portfolio()
                
                # 等待
                time.sleep(self.check_interval)
                check_count += 1
                
        except KeyboardInterrupt:
            print("\n\n👋 系统停止...")
            self.shutdown()
    
    def shutdown(self):
        """关闭系统"""
        print("\n📊 生成最终报告...")
        
        # 显示最终状态
        self.bot.display_portfolio()
        
        # 保存结果
        filename = self.bot.save_results()
        
        print(f"\n✅ 系统已关闭")
        print(f"📄 交易记录: {filename}")


def main():
    """主程序"""
    # 读取配置
    config_file = 'config.json'
    
    if not os.path.exists(config_file):
        print(f"⚠️ 未找到配置文件: {config_file}")
        print("将使用默认配置（无Telegram通知）")
    
    # 创建并运行系统
    system = IntegratedTradingSystem(
        initial_balance=1000,
        config_file=config_file
    )
    
    system.run()


if __name__ == "__main__":
    main()
