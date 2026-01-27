#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ADA实盘模拟交易监控系统"""

import requests
import json
import time
import sqlite3
import os
from datetime import datetime

class ADAMonitor:
    """ADA实盘模拟交易监控器"""
    
    def __init__(self):
        self.symbol = "ADA/USDT"
        self.current_price = 0
        
        # 交易参数（基于当前分析）
        self.long_entry_zone = 0.35      # 做多区域
        self.short_entry_zone = 0.55     # 做空区域
        self.stop_loss_pct = 0.10        # 10%止损
        self.take_profit_pct = 0.20      # 20%止盈
        
        # 关键价位
        self.key_levels = {
            'strong_support': 0.20,
            'mid_support': 0.30,
            'current_support': 0.35,
            'current_resistance': 0.45,
            'mid_resistance': 0.55,
            'strong_resistance': 0.60
        }
        
        # 初始化数据库
        self.init_database()
        
        # 加载Telegram配置
        self.telegram_available = self._init_telegram()
        
        print("🎯 ADA实盘模拟交易监控启动")
        print(f"📊 做多区域: ${self.long_entry_zone:.4f} 以下")
        print(f"📊 做空区域: ${self.short_entry_zone:.4f} 以上")
        print(f"📱 Telegram通知: {'✅启用' if self.telegram_available else '❌未启用'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_path = 'config/config.json'
            if os.path.exists(config_path):
                with open(config_path, 'r') as f:
                    config = json.load(f)
                    telegram_config = config.get('telegram', {})
                    self.bot_token = telegram_config.get('bot_token')
                    self.chat_id = telegram_config.get('chat_id')
                    return bool(self.bot_token and self.chat_id)
        except Exception as e:
            print(f"❌ Telegram配置失败: {e}")
        return False
    
    def send_telegram_message(self, message):
        """发送Telegram消息"""
        if not self.telegram_available:
            return
        
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/sendMessage"
            payload = {
                "chat_id": self.chat_id,
                "text": message,
                "parse_mode": "HTML"
            }
            response = requests.post(url, data=payload, timeout=10)
            return response.status_code == 200
        except Exception as e:
            print(f"❌ Telegram发送失败: {e}")
            return False
    
    def init_database(self):
        """初始化数据库"""
        db_path = 'data/db/paper_trading.db'
        os.makedirs(os.path.dirname(db_path), exist_ok=True)
        
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        # 创建ADA监控表
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS ada_signals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp TEXT NOT NULL,
                price REAL NOT NULL,
                signal_type TEXT NOT NULL,
                entry_price REAL,
                stop_loss REAL,
                take_profit REAL,
                reasoning TEXT,
                executed BOOLEAN DEFAULT FALSE
            )
        ''')
        
        conn.commit()
        conn.close()
    
    def get_ada_price(self):
        """获取ADA当前价格"""
        try:
            # CoinGecko API
            url = 'https://api.coingecko.com/api/v3/simple/price?ids=cardano&vs_currencies=usd'
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                return data['cardano']['usd']
        except Exception as e:
            print(f"CoinGecko失败: {e}")
        
        try:
            # 备用：Binance API
            url = 'https://api.binance.com/api/v3/ticker/price?symbol=ADAUSDT'
            response = requests.get(url)
            data = response.json()
            return float(data['price'])
        except Exception as e:
            print(f"价格获取失败: {e}")
            return None
    
    def analyze_signal(self, price):
        """分析交易信号"""
        signals = []
        
        # 做多信号检测
        if price <= self.long_entry_zone:
            signal = {
                'type': 'LONG',
                'entry_price': price,
                'stop_loss': price * (1 - self.stop_loss_pct),
                'take_profit': price * (1 + self.take_profit_pct),
                'reasoning': f"价格跌至做多区域 ${self.long_entry_zone:.4f}",
                'confidence': 'HIGH' if price < 0.33 else 'MEDIUM'
            }
            signals.append(signal)
        
        # 做空信号检测  
        elif price >= self.short_entry_zone:
            signal = {
                'type': 'SHORT',
                'entry_price': price,
                'stop_loss': price * (1 + self.stop_loss_pct),
                'take_profit': price * (1 - self.take_profit_pct),
                'reasoning': f"价格涨至做空区域 ${self.short_entry_zone:.4f}",
                'confidence': 'HIGH' if price > 0.58 else 'MEDIUM'
            }
            signals.append(signal)
        
        # 突破信号
        elif price > self.key_levels['strong_resistance']:
            signal = {
                'type': 'BREAKOUT_LONG',
                'entry_price': price,
                'stop_loss': self.key_levels['strong_resistance'],
                'take_profit': price * 1.25,
                'reasoning': "突破强阻力位 $0.60",
                'confidence': 'HIGH'
            }
            signals.append(signal)
        
        elif price < self.key_levels['strong_support']:
            signal = {
                'type': 'BREAKDOWN_SHORT',
                'entry_price': price,
                'stop_loss': self.key_levels['strong_support'],
                'take_profit': price * 0.80,
                'reasoning': "跌破强支撑位 $0.20",
                'confidence': 'HIGH'
            }
            signals.append(signal)
        
        return signals
    
    def save_signal(self, signal):
        """保存信号到数据库"""
        db_path = 'data/db/paper_trading.db'
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            INSERT INTO ada_signals (timestamp, price, signal_type, entry_price, stop_loss, take_profit, reasoning)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ''', (
            datetime.now().isoformat(),
            self.current_price,
            signal['type'],
            signal['entry_price'],
            signal['stop_loss'],
            signal['take_profit'],
            signal['reasoning']
        ))
        
        conn.commit()
        conn.close()
    
    def format_signal_message(self, signal):
        """格式化信号消息"""
        direction_emoji = "🟢" if 'LONG' in signal['type'] else "🔴"
        confidence_emoji = "🔥" if signal['confidence'] == 'HIGH' else "⚡"
        
        message = f"""{direction_emoji} <b>ADA交易信号</b> {confidence_emoji}

🎯 <b>{signal['type']}</b>
💰 入场价: ${signal['entry_price']:.4f}
🛡️ 止损价: ${signal['stop_loss']:.4f}
🎯 止盈价: ${signal['take_profit']:.4f}

💡 <b>分析</b>: {signal['reasoning']}
⚡ <b>信心度</b>: {signal['confidence']}
⏰ <b>时间</b>: {datetime.now().strftime('%H:%M:%S')}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 <b>风险管理</b>:
• 建议仓位: 5-10%
• 杠杆倍数: 3x (保守)
• 最大风险: 3%

⚠️ 此为模拟交易信号，仅供参考
"""
        return message
    
    def display_status(self):
        """显示监控状态"""
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        print(f"\n📊 {timestamp} ADA监控状态")
        print(f"💰 当前价格: ${self.current_price:.4f}")
        
        # 显示相对关键位的距离
        current_level = None
        for level_name, level_price in self.key_levels.items():
            distance = abs(self.current_price - level_price) / level_price * 100
            if distance < 5:  # 接近关键位
                print(f"⚠️ 接近 {level_name}: ${level_price:.4f} (距离{distance:.1f}%)")
                current_level = level_name
        
        # 当前区域判断
        if self.current_price <= self.long_entry_zone:
            zone = f"🟢 做多区域 (≤${self.long_entry_zone:.4f})"
        elif self.current_price >= self.short_entry_zone:
            zone = f"🔴 做空区域 (≥${self.short_entry_zone:.4f})"
        else:
            zone = f"⚡ 震荡区域 (${self.long_entry_zone:.4f} - ${self.short_entry_zone:.4f})"
            
        print(f"📈 交易区域: {zone}")
        print("-" * 50)
    
    def run_monitoring(self, interval=60):
        """运行监控"""
        print(f"\n🚀 开始ADA实盘监控 (间隔{interval}秒)")
        print("按 Ctrl+C 停止监控")
        
        try:
            while True:
                # 获取价格
                price = self.get_ada_price()
                
                if price:
                    self.current_price = price
                    
                    # 显示状态
                    self.display_status()
                    
                    # 分析信号
                    signals = self.analyze_signal(price)
                    
                    # 处理信号
                    for signal in signals:
                        print(f"🚨 检测到{signal['type']}信号!")
                        self.save_signal(signal)
                        
                        # 发送Telegram通知
                        telegram_msg = self.format_signal_message(signal)
                        if self.send_telegram_message(telegram_msg):
                            print("✅ Telegram通知已发送")
                        else:
                            print("❌ Telegram通知发送失败")
                else:
                    print("❌ 价格获取失败")
                
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 ADA监控已停止")
            if self.telegram_available:
                self.send_telegram_message("⏹️ ADA实盘监控已停止")

def main():
    """主函数"""
    monitor = ADAMonitor()
    monitor.run_monitoring(interval=60)  # 1分钟间隔

if __name__ == "__main__":
    main()