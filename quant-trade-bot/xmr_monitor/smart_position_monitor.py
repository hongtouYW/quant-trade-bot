#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
智能持仓监控 - 只监控已开仓的币种
如果没有持仓，不发Telegram通知
"""

import requests
import json
import time
import os
from datetime import datetime

class SmartPositionMonitor:
    """智能持仓监控 - 自动读取持仓文件"""
    
    def __init__(self, position_file='../my_xmr_position.json'):
        self.position_file = position_file
        self.position = None
        self.telegram_available = self._init_telegram()
        
        # 加载持仓
        if self.load_position():
            print(f"✅ 发现活跃持仓: {self.position['symbol']}")
            print(f"📱 Telegram通知: {'✅启用' if self.telegram_available else '❌未启用'}")
        else:
            print("ℹ️  当前无活跃持仓，不发送Telegram通知")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_paths = [
                '../config/config.json',
                os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'config', 'config.json')
            ]
            
            for path in config_paths:
                if os.path.exists(path):
                    with open(path, 'r', encoding='utf-8') as f:
                        config = json.load(f)
                        telegram_config = config.get('telegram', {})
                        self.bot_token = telegram_config.get('bot_token')
                        self.chat_id = telegram_config.get('chat_id')
                        
                        if self.bot_token and self.chat_id:
                            return True
        except Exception as e:
            print(f"❌ Telegram配置失败: {e}")
        return False
    
    def load_position(self):
        """加载持仓信息 - 只加载状态为OPEN的持仓"""
        try:
            # 尝试多个可能的路径
            paths = [
                self.position_file,
                os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'my_xmr_position.json'),
                '/Users/hongtou/newproject/quant-trade-bot/my_xmr_position.json'
            ]
            
            for path in paths:
                if os.path.exists(path):
                    with open(path, 'r', encoding='utf-8') as f:
                        data = json.load(f)
                    
                    # 检查持仓状态
                    if data.get('status') == 'OPEN':
                        self.position = {
                            'symbol': data['symbol'],
                            'entry_price': data['entry_price'],
                            'leverage': data.get('leverage', 1),
                            'position_size': data.get('position_size', 0),
                            'stop_loss': data.get('stop_loss'),
                            'take_profit': data.get('take_profit'),
                            'side': data.get('side', 'LONG')  # LONG 或 SHORT
                        }
                        return True
                    else:
                        print(f"ℹ️  持仓状态: {data.get('status')} (非OPEN)")
                        return False
        except Exception as e:
            print(f"❌ 加载持仓失败: {e}")
        return False
    
    def get_price(self):
        """获取价格 - 优先使用CoinGecko"""
        if not self.position:
            return None
        
        symbol = self.position['symbol'].replace('/USDT', '')
        
        # CoinGecko ID映射
        coin_id_map = {
            'XMR': 'monero',
            'BTC': 'bitcoin',
            'ETH': 'ethereum',
            'ADA': 'cardano'
        }
        
        coin_id = coin_id_map.get(symbol)
        
        # 优先使用CoinGecko
        if coin_id:
            try:
                url = f'https://api.coingecko.com/api/v3/simple/price?ids={coin_id}&vs_currencies=usd'
                response = requests.get(url, timeout=10)
                if response.status_code == 200:
                    data = response.json()
                    price = data[coin_id]['usd']
                    print(f"✅ 价格 (CoinGecko): ${price:.2f}")
                    return price
            except Exception as e:
                print(f"⚠️ CoinGecko获取失败: {e}")
        
        # 备用Binance
        try:
            binance_symbol = symbol + 'USDT'
            url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                price = float(data['price'])
                print(f"✅ 价格 (Binance): ${price:.2f}")
                return price
        except Exception as e:
            print(f"❌ Binance获取失败: {e}")
        
        return None
    
    def calculate_pnl(self, current_price):
        """计算盈亏 - 支持做多/做空"""
        if not self.position:
            return None
        
        entry_price = self.position['entry_price']
        leverage = self.position['leverage']
        position_size = self.position['position_size']
        is_short = self.position['side'] == 'SHORT'
        
        # 计算价格变化
        if is_short:
            # 做空：价格下跌赚钱
            price_change_percent = (entry_price - current_price) / entry_price * 100
        else:
            # 做多：价格上涨赚钱
            price_change_percent = (current_price - entry_price) / entry_price * 100
        
        # 计算盈亏
        roi = price_change_percent * leverage
        pnl_amount = (roi / 100) * position_size
        total_balance = position_size + pnl_amount
        
        return {
            'price_change_percent': price_change_percent,
            'roi': roi,
            'pnl_amount': pnl_amount,
            'total_balance': total_balance
        }
    
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
            response = requests.post(url, json=payload, timeout=10)
            if response.status_code == 200:
                print("✅ Telegram通知已发送")
            else:
                print(f"❌ Telegram发送失败: {response.status_code}")
        except Exception as e:
            print(f"❌ Telegram发送错误: {e}")
    
    def send_position_update(self, current_price, pnl_data):
        """发送持仓更新通知"""
        if not self.position or not self.telegram_available:
            return
        
        roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
        pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
        
        symbol = self.position['symbol'].replace('/USDT', '')
        side_emoji = "🔴 做空" if self.position['side'] == 'SHORT' else "🟢 做多"
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        message = f"""🎯 <b>{symbol} 持仓更新</b> {side_emoji}

💰 现价: ${current_price:.2f}
📈 入场: ${self.position['entry_price']:.2f}
📊 涨跌: {pnl_data['price_change_percent']:+.2f}%
💎 杠杆: {self.position['leverage']}x

━━━━━━━━━━━━━━
💵 ROI: {roi_emoji}{pnl_data['roi']:+.2f}%
💰 盈亏: {pnl_emoji}${pnl_data['pnl_amount']:+.2f}U

⏰ 更新时间: {timestamp}"""
        
        self.send_telegram_message(message)
    
    def check_triggers(self, current_price, pnl_data):
        """检查止损止盈触发"""
        if not self.position:
            return None
        
        stop_loss = self.position.get('stop_loss')
        take_profit = self.position.get('take_profit')
        is_short = self.position['side'] == 'SHORT'
        
        if is_short:
            # 做空止损止盈逻辑
            if stop_loss and current_price >= stop_loss:
                return 'stop_loss'
            if take_profit and current_price <= take_profit:
                return 'take_profit'
        else:
            # 做多止损止盈逻辑
            if stop_loss and current_price <= stop_loss:
                return 'stop_loss'
            if take_profit and current_price >= take_profit:
                return 'take_profit'
        
        return None
    
    def display_status(self, current_price, pnl_data):
        """终端显示状态"""
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        # 颜色显示
        color = "\033[92m" if pnl_data['pnl_amount'] >= 0 else "\033[91m"
        reset = "\033[0m"
        
        symbol = self.position['symbol'].replace('/USDT', '')
        side = "做空" if self.position['side'] == 'SHORT' else "做多"
        
        status_msg = f"\n📊 {timestamp} {symbol} {side}\n"
        status_msg += f"💰 现价: ${current_price:.2f}\n"
        status_msg += f"📈 入场: ${self.position['entry_price']:.2f}\n"
        status_msg += f"📊 涨跌: {pnl_data['price_change_percent']:+.2f}%\n"
        status_msg += f"💎 杠杆: {self.position['leverage']}x\n"
        status_msg += f"💵 ROI: {color}{pnl_data['roi']:+.2f}%{reset}\n"
        status_msg += f"💰 盈亏: {color}${pnl_data['pnl_amount']:+.2f}U{reset}\n"
        status_msg += "-" * 50
        
        print(status_msg)
    
    def run_monitoring(self, interval=300):
        """运行监控"""
        # 如果没有持仓，不启动监控
        if not self.position:
            print("ℹ️  无活跃持仓，监控不启动")
            return
        
        symbol = self.position['symbol'].replace('/USDT', '')
        side = "做空" if self.position['side'] == 'SHORT' else "做多"
        
        print(f"\n🚀 开始{symbol}持仓监控 ({side}, {interval}秒间隔)")
        print(f"📊 入场价格: ${self.position['entry_price']:.2f}")
        print(f"💎 杠杆倍数: {self.position['leverage']}x")
        print("按 Ctrl+C 停止监控\n")
        
        # 发送启动消息
        if self.telegram_available:
            start_msg = f"""🚀 <b>{symbol}持仓监控启动</b>

━━━━━━━━━━━━━━
💰 入场价格: ${self.position['entry_price']:.2f}
💎 杠杆: {self.position['leverage']}x
📊 方向: {side}
⏰ 监控开始"""
            self.send_telegram_message(start_msg)
        
        try:
            while True:
                # 重新加载持仓状态（可能已平仓）
                if not self.load_position():
                    print("\n📴 持仓已关闭，停止监控")
                    if self.telegram_available:
                        self.send_telegram_message("📴 持仓已关闭，监控已停止")
                    break
                
                current_price = self.get_price()
                
                if current_price:
                    pnl_data = self.calculate_pnl(current_price)
                    
                    # 终端显示
                    self.display_status(current_price, pnl_data)
                    
                    # 发送Telegram更新
                    self.send_position_update(current_price, pnl_data)
                    
                    # 检查触发
                    trigger = self.check_triggers(current_price, pnl_data)
                    if trigger:
                        print(f"🔔 触发: {trigger}")
                else:
                    print(f"❌ 价格获取失败，{interval}秒后重试")
                
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")
            if self.telegram_available:
                self.send_telegram_message(f"⏹️ {symbol}监控已停止")

def main():
    """主函数"""
    monitor = SmartPositionMonitor()
    monitor.run_monitoring(interval=300)  # 5分钟间隔

if __name__ == "__main__":
    main()
