# -*- coding: utf-8 -*-
"""
XMR简化监控 + Telegram通知
只使用requests，不依赖ccxt
"""

import requests
import json
import time
import os
from datetime import datetime

class XMRSimpleMonitor:
    """简化的XMR监控器 - 带Telegram通知"""
    
    def __init__(self, entry_price=502.41, leverage=10):
        self.entry_price = entry_price
        self.leverage = leverage
        self.principal = 100
        
        # 止损止盈设置
        self.stop_loss_percent = 2.0
        self.take_profit_percent = 2.0
        
        self.stop_loss_price = entry_price * (1 - self.stop_loss_percent / 100)
        self.take_profit_price = entry_price * (1 + self.take_profit_percent / 100)
        
        # 预警价位
        self.warning_distance = 0.5
        self.stop_loss_warning = entry_price * (1 - (self.stop_loss_percent - self.warning_distance) / 100)
        self.take_profit_warning = entry_price * (1 + (self.take_profit_percent - self.warning_distance) / 100)
        
        # 状态跟踪
        self.alerts_sent = {
            'stop_loss_warning': False,
            'take_profit_warning': False,
            'stop_loss': False,
            'take_profit': False
        }
        
        # 加载Telegram配置
        self.telegram_available = self._init_telegram()
        
        print(f"🎯 XMR简化监控系统启动")
        print(f"💰 入场价格: ${entry_price:.2f}")
        print(f"📊 杠杆倍数: {leverage}x")
        print(f"🛡️ 止损价格: ${self.stop_loss_price:.2f}")
        print(f"🎯 止盈价格: ${self.take_profit_price:.2f}")
        print(f"📱 Telegram通知: {'✅启用' if self.telegram_available else '❌未启用'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            # 尝试多个可能的config.json位置
            config_paths = [
                'config.json',
                '../config.json', 
                '../config/config.json',  # 新的config目录
                os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'config', 'config.json')
            ]
            config_path = None
            
            for path in config_paths:
                if os.path.exists(path):
                    config_path = path
                    break
            
            if config_path and os.path.exists(config_path):
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                    telegram_config = config.get('telegram', {})
                    self.bot_token = telegram_config.get('bot_token')
                    self.chat_id = telegram_config.get('chat_id')
                    
                    if self.bot_token and self.chat_id:
                        # 测试发送启动消息
                        start_msg = f"""🎯 <b>XMR监控启动</b>
━━━━━━━━━━━━━━
💰 入场价格: ${self.entry_price:.2f}
🛡️ 止损位: ${self.stop_loss_price:.2f} (-2%)
🎯 止盈位: ${self.take_profit_price:.2f} (+2%)
📊 杠杆: {self.leverage}x
💎 本金: {self.principal}U
⏰ 监控开始"""
                        self.send_telegram_message(start_msg)
                        return True
        except Exception as e:
            print(f"❌ Telegram配置失败: {e}")
        return False
    
    def send_telegram_message(self, message):
        """发送Telegram消息"""
        if not hasattr(self, 'telegram_available') or not self.telegram_available:
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
    
    def get_price(self):
        """获取XMR价格 - 优先使用CoinGecko"""
        prices = []
        
        # 优先使用CoinGecko (更准确)
        try:
            response = requests.get('https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd', timeout=10)
            if response.status_code == 200:
                data = response.json()
                prices.append(data['monero']['usd'])
                print(f"✅ CoinGecko价格: ${data['monero']['usd']:.2f}")
        except Exception as e:
            print(f"❌ CoinGecko获取失败: {e}")
        
        # 币安作为备用 (数据可能有问题)
        try:
            response = requests.get("https://api.binance.com/api/v3/ticker/price?symbol=XMRUSDT", timeout=10)
            if response.status_code == 200:
                data = response.json()
                binance_price = float(data['price'])
                print(f"⚠️ 币安价格: ${binance_price:.2f} (仅作参考)")
                # 只有在CoinGecko失败时才使用币安价格
                if not prices:
                    prices.append(binance_price)
        except Exception as e:
            print(f"❌ 币安获取失败: {e}")
        
        return prices[0] if prices else None
    
    def check_network(self):
        """检查网络连接"""
        try:
            import requests
            response = requests.get("https://api.coingecko.com/api/v3/ping", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def calculate_pnl(self, current_price):
        """计算盈亏"""
        price_change_percent = (current_price - self.entry_price) / self.entry_price * 100
        leveraged_roi = price_change_percent * self.leverage
        pnl_amount = (leveraged_roi / 100) * self.principal
        total_balance = self.principal + pnl_amount
        
        return {
            'price_change_percent': price_change_percent,
            'roi': leveraged_roi,
            'pnl_amount': pnl_amount,
            'total_balance': total_balance
        }
    
    def check_triggers(self, current_price):
        """检查触发条件"""
        pnl_data = self.calculate_pnl(current_price)
        
        # 构建基本消息 - 带颜色emoji
        pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
        roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
        
        base_msg = f"""💰 价格: ${current_price:.2f}
📊 入场: ${self.entry_price:.2f}
💵 ROI: {roi_emoji} {pnl_data['roi']:+.2f}%
💰 盈亏: {pnl_emoji} ${pnl_data['pnl_amount']:+.2f}U
💳 余额: ${pnl_data['total_balance']:.2f}U"""
        
        # 止损触发
        if current_price <= self.stop_loss_price and not self.alerts_sent['stop_loss']:
            msg = f"""🚨 <b>止损触发</b> 🚨
━━━━━━━━━━━━━━
{base_msg}"""
            self.send_telegram_message(msg)
            self.alerts_sent['stop_loss'] = True
            return 'stop_loss'
        
        # 止盈触发
        elif current_price >= self.take_profit_price and not self.alerts_sent['take_profit']:
            msg = f"""🎉 <b>止盈触发</b> 🎉
━━━━━━━━━━━━━━
{base_msg}
🎯 建议考虑平仓获利！"""
            self.send_telegram_message(msg)
            self.alerts_sent['take_profit'] = True
            return 'take_profit'
        
        # 预警通知
        elif current_price <= self.stop_loss_warning and not self.alerts_sent['stop_loss_warning']:
            msg = f"""⚠️ <b>止损预警</b>
━━━━━━━━━━━━━━
{base_msg}
🛡️ 止损位: ${self.stop_loss_price:.2f}"""
            self.send_telegram_message(msg)
            self.alerts_sent['stop_loss_warning'] = True
            return 'stop_loss_warning'
        
        elif current_price >= self.take_profit_warning and not self.alerts_sent['take_profit_warning']:
            msg = f"""⚠️ <b>止盈预警</b>
━━━━━━━━━━━━━━
{base_msg}
🎯 止盈位: ${self.take_profit_price:.2f}"""
            self.send_telegram_message(msg)
            self.alerts_sent['take_profit_warning'] = True
            return 'take_profit_warning'
        
        return None
    
    def display_status(self, current_price, pnl_data):
        """显示状态"""
        import sys
        timestamp = datetime.now().strftime('%H:%M:%S')
        
        # 颜色显示
        if pnl_data['pnl_amount'] >= 0:
            color = "\033[92m"  # 绿色
        else:
            color = "\033[91m"  # 红色
        reset = "\033[0m"
        
        status_msg = f"\n📊 {timestamp} XMR监控状态\n"
        status_msg += f"💰 当前价格: ${current_price:.2f}\n"
        status_msg += f"📈 入场价格: ${self.entry_price:.2f}\n"
        status_msg += f"📊 价格变化: {pnl_data['price_change_percent']:+.2f}%\n"
        status_msg += f"💎 杠杆倍数: {self.leverage}x\n"
        status_msg += f"💵 投资回报率: {color}{pnl_data['roi']:+.2f}%{reset}\n"
        status_msg += f"💰 盈亏金额: {color}${pnl_data['pnl_amount']:+.2f}U{reset}\n"
        status_msg += f"💳 总余额: {color}${pnl_data['total_balance']:.2f}U{reset}\n"
        status_msg += f"🛡️ 距止损: {((current_price - self.stop_loss_price) / current_price * 100):.2f}%\n"
        status_msg += f"🎯 距止盈: {((self.take_profit_price - current_price) / current_price * 100):.2f}%\n"
        status_msg += "-" * 50
        
        print(status_msg)
        sys.stdout.flush()  # 强制刷新输出
    
    def run_monitoring(self, interval=300):
        """运行监控"""
        print(f"\n🚀 开始XMR监控 (间隔{interval}秒)")
        print("按 Ctrl+C 停止监控")
        
        try:
            while True:
                current_price = self.get_price()
                
                if current_price:
                    pnl_data = self.calculate_pnl(current_price)
                    trigger = self.check_triggers(current_price)
                    self.display_status(current_price, pnl_data)
                    
                    if trigger:
                        print(f"🔔 触发: {trigger}")
                else:
                    print(f"❌ 价格获取失败，{interval}秒后重试")
                
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已停止")
            if self.telegram_available:
                self.send_telegram_message("⏹️ XMR监控已停止")

def main():
    """主函数"""
    monitor = XMRSimpleMonitor(entry_price=502.41, leverage=10)
    monitor.run_monitoring(interval=300)  # 5分钟间隔

if __name__ == "__main__":
    main()