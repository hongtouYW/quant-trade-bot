# -*- coding: utf-8 -*-
"""
XMR合约价格监控和止损止盈通知系统
支持10倍杠杆的风险管理和Telegram实时通知
"""

import ccxt
import requests
import pandas as pd
import numpy as np
import time
import json
import os
from datetime import datetime, timedelta
from typing import Dict, Optional, List
import threading

class XMRLeverageMonitor:
    """XMR杠杆合约价格监控器"""
    
    def __init__(self, entry_price: float = 502.41, leverage: int = 10):
        self.entry_price = entry_price
        self.leverage = leverage
        self.position_type = "long"  # 假设做多，如果做空请改为"short"
        
        # 止损止盈设置
        self.stop_loss_percentage = 3.0  # 3% (考虑10倍杠杆，实际亏损30%)
        self.take_profit_percentage = 5.0  # 5% (考虑10倍杠杆，实际盈利50%)
        
        # 计算具体价位
        if self.position_type == "long":
            self.stop_loss_price = self.entry_price * (1 - self.stop_loss_percentage / 100)
            self.take_profit_price = self.entry_price * (1 + self.take_profit_percentage / 100)
        else:  # short
            self.stop_loss_price = self.entry_price * (1 + self.stop_loss_percentage / 100)
            self.take_profit_price = self.entry_price * (1 - self.take_profit_percentage / 100)
        
        # 状态跟踪
        self.position_active = True
        self.notifications_sent = {
            'stop_loss': False,
            'take_profit': False,
            'entry_confirmed': False
        }
        
        # 价格历史（用于分析）
        self.price_history = []
        
        # 初始化交易所和通知
        self.exchanges = self._init_exchanges()
        self.telegram = self._init_telegram()
        
        print(f"🚀 XMR 合约监控启动")
        print(f"💰 入场价格: ${self.entry_price:.2f}")
        print(f"📊 杠杆倍数: {self.leverage}x")
        print(f"📈 持仓方向: {self.position_type.upper()}")
        print(f"🛡️ 止损价格: ${self.stop_loss_price:.2f} (-{self.stop_loss_percentage}%)")
        print(f"🎯 止盈价格: ${self.take_profit_price:.2f} (+{self.take_profit_percentage}%)")
        print(f"⚠️ 有效亏损: -{self.stop_loss_percentage * self.leverage}%")
        print(f"💎 有效盈利: +{self.take_profit_percentage * self.leverage}%")
    
    def _init_exchanges(self):
        """初始化支持XMR的交易所"""
        exchanges = {}
        
        # Kraken (主要XMR交易所)
        try:
            exchanges['kraken'] = ccxt.kraken({
                'enableRateLimit': True,
                'timeout': 30000
            })
        except:
            pass
        
        # Bitfinex
        try:
            exchanges['bitfinex'] = ccxt.bitfinex({
                'enableRateLimit': True,
                'timeout': 30000
            })
        except:
            pass
        
        return exchanges
    
    def _init_telegram(self):
        """初始化Telegram通知"""
        try:
            # 从配置文件读取Telegram配置
            config_path = '/Users/hongtou/newproject/quant-trade-bot/config.json'
            if os.path.exists(config_path):
                with open(config_path, 'r') as f:
                    config = json.load(f)
                
                bot_token = config.get('telegram', {}).get('bot_token')
                chat_id = config.get('telegram', {}).get('chat_id')
                
                if bot_token and chat_id:
                    return TelegramNotifier(bot_token, chat_id)
            
            # 备用：从环境变量读取
            bot_token = os.getenv('TELEGRAM_BOT_TOKEN')
            chat_id = os.getenv('TELEGRAM_CHAT_ID')
            
            if bot_token and chat_id:
                return TelegramNotifier(bot_token, chat_id)
            
        except Exception as e:
            print(f"⚠️ Telegram初始化失败: {e}")
        
        return None
    
    def get_current_price(self):
        """获取当前XMR价格"""
        prices = []
        
        # 方法1: CoinGecko API
        try:
            response = requests.get(
                'https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd',
                timeout=10
            )
            data = response.json()
            cg_price = data['monero']['usd']
            prices.append(cg_price)
        except Exception as e:
            print(f"❌ CoinGecko失败: {e}")
        
        # 方法2: 交易所价格
        for exchange_name, exchange in self.exchanges.items():
            try:
                symbols_to_try = ['XMR/USDT', 'XMR/USD', 'XMRUSDT']
                
                for symbol in symbols_to_try:
                    try:
                        ticker = exchange.fetch_ticker(symbol)
                        price = ticker.get('last', 0)
                        
                        if price and price > 0:
                            prices.append(price)
                            break
                    except:
                        continue
            except Exception as e:
                continue
        
        if prices:
            # 使用价格中位数作为最准确的价格
            median_price = np.median(prices)
            return median_price
        
        return None
    
    def calculate_pnl(self, current_price: float):
        """计算当前盈亏"""
        if self.position_type == "long":
            price_change_pct = (current_price - self.entry_price) / self.entry_price * 100
        else:  # short
            price_change_pct = (self.entry_price - current_price) / self.entry_price * 100
        
        # 考虑杠杆
        leveraged_pnl = price_change_pct * self.leverage
        
        return {
            'price_change_pct': price_change_pct,
            'leveraged_pnl': leveraged_pnl,
            'current_price': current_price
        }
    
    def check_stop_loss_take_profit(self, current_price: float):
        """检查止损止盈触发"""
        alerts = []
        
        if self.position_type == "long":
            # 做多检查
            if current_price <= self.stop_loss_price and not self.notifications_sent['stop_loss']:
                alerts.append('stop_loss')
                self.notifications_sent['stop_loss'] = True
                self.position_active = False
                
            elif current_price >= self.take_profit_price and not self.notifications_sent['take_profit']:
                alerts.append('take_profit')
                self.notifications_sent['take_profit'] = True
                self.position_active = False
        
        else:  # short
            # 做空检查
            if current_price >= self.stop_loss_price and not self.notifications_sent['stop_loss']:
                alerts.append('stop_loss')
                self.notifications_sent['stop_loss'] = True
                self.position_active = False
                
            elif current_price <= self.take_profit_price and not self.notifications_sent['take_profit']:
                alerts.append('take_profit')
                self.notifications_sent['take_profit'] = True
                self.position_active = False
        
        return alerts
    
    def send_notifications(self, current_price: float, alerts: List[str], pnl_data: Dict):
        """发送通知"""
        if not self.telegram:
            print("⚠️ Telegram未配置，无法发送通知")
            return
        
        for alert_type in alerts:
            if alert_type == 'stop_loss':
                self.telegram.send_stop_loss_alert(
                    current_price=current_price,
                    entry_price=self.entry_price,
                    stop_loss_price=self.stop_loss_price,
                    leverage=self.leverage,
                    pnl=pnl_data['leveraged_pnl'],
                    position_type=self.position_type
                )
                
            elif alert_type == 'take_profit':
                self.telegram.send_take_profit_alert(
                    current_price=current_price,
                    entry_price=self.entry_price,
                    take_profit_price=self.take_profit_price,
                    leverage=self.leverage,
                    pnl=pnl_data['leveraged_pnl'],
                    position_type=self.position_type
                )
    
    def run_monitoring_cycle(self):
        """运行单次监控循环"""
        current_price = self.get_current_price()
        
        if current_price is None:
            print("❌ 无法获取价格，跳过本次检查")
            return False
        
        # 记录价格历史
        self.price_history.append({
            'timestamp': datetime.now(),
            'price': current_price
        })
        
        # 保持历史记录在100条以内
        if len(self.price_history) > 100:
            self.price_history.pop(0)
        
        # 计算盈亏
        pnl_data = self.calculate_pnl(current_price)
        
        # 检查止损止盈
        alerts = self.check_stop_loss_take_profit(current_price)
        
        # 打印当前状态
        timestamp = datetime.now().strftime('%H:%M:%S')
        pnl_color = "🟢" if pnl_data['leveraged_pnl'] > 0 else "🔴"
        status_emoji = "🚨" if alerts else "📊"
        
        print(f"\n{status_emoji} {timestamp} XMR价格监控")
        print(f"💰 当前价格: ${current_price:.2f}")
        print(f"📈 入场价格: ${self.entry_price:.2f}")
        print(f"📊 价格变化: {pnl_data['price_change_pct']:+.2f}%")
        print(f"{pnl_color} 杠杆盈亏: {pnl_data['leveraged_pnl']:+.2f}%")
        print(f"🛡️ 止损距离: {((current_price - self.stop_loss_price) / current_price * 100):+.2f}%")
        print(f"🎯 止盈距离: {((self.take_profit_price - current_price) / current_price * 100):+.2f}%")
        
        if alerts:
            print(f"🚨 触发警报: {', '.join(alerts)}")
        
        # 发送通知
        if alerts:
            self.send_notifications(current_price, alerts, pnl_data)
        
        return self.position_active
    
    def start_continuous_monitoring(self, interval: int = 30):
        """开始连续监控"""
        # 发送初始确认消息
        if self.telegram and not self.notifications_sent['entry_confirmed']:
            self.telegram.send_position_opened(
                entry_price=self.entry_price,
                leverage=self.leverage,
                stop_loss=self.stop_loss_price,
                take_profit=self.take_profit_price,
                position_type=self.position_type
            )
            self.notifications_sent['entry_confirmed'] = True
        
        print(f"\n🚀 开始连续监控 XMR 合约")
        print(f"⏰ 更新间隔: {interval}秒")
        print(f"🔔 Telegram通知: {'已配置' if self.telegram else '未配置'}")
        print("按 Ctrl+C 停止监控\n")
        
        try:
            while self.position_active:
                position_still_active = self.run_monitoring_cycle()
                
                if not position_still_active:
                    print(f"\n🏁 持仓已平仓，监控结束")
                    break
                
                print(f"⏰ {interval}秒后下次检查...")
                time.sleep(interval)
                
        except KeyboardInterrupt:
            print("\n👋 监控已手动停止")
            if self.telegram:
                self.telegram.send_monitoring_stopped()
        
        except Exception as e:
            print(f"\n❌ 监控出错: {e}")
            if self.telegram:
                self.telegram.send_error_alert(str(e))
    
    def save_price_history(self):
        """保存价格历史"""
        filename = f"xmr_price_history_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        history_data = {
            'entry_price': self.entry_price,
            'leverage': self.leverage,
            'position_type': self.position_type,
            'stop_loss_price': self.stop_loss_price,
            'take_profit_price': self.take_profit_price,
            'price_history': [
                {
                    'timestamp': entry['timestamp'].isoformat(),
                    'price': entry['price']
                } for entry in self.price_history
            ],
            'final_status': {
                'position_active': self.position_active,
                'notifications_sent': self.notifications_sent
            }
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(history_data, f, ensure_ascii=False, indent=2)
        
        print(f"📁 价格历史已保存: {filename}")


class TelegramNotifier:
    """Telegram通知器"""
    
    def __init__(self, bot_token: str, chat_id: str):
        self.bot_token = bot_token
        self.chat_id = chat_id
        self.base_url = f"https://api.telegram.org/bot{bot_token}"
    
    def send_message(self, message: str):
        """发送消息"""
        url = f"{self.base_url}/sendMessage"
        payload = {
            "chat_id": self.chat_id,
            "text": message,
            "parse_mode": "HTML"
        }
        try:
            response = requests.post(url, json=payload, timeout=10)
            return response.json()
        except Exception as e:
            print(f"❌ Telegram发送失败: {e}")
            return None
    
    def send_position_opened(self, entry_price: float, leverage: int, stop_loss: float, 
                           take_profit: float, position_type: str):
        """发送开仓确认"""
        direction_emoji = "📈" if position_type == "long" else "📉"
        message = f"""
{direction_emoji} <b>XMR合约开仓确认</b>
━━━━━━━━━━━━━━━━━━━━━━━━
💰 入场价格: ${entry_price:.2f}
📊 杠杆倍数: {leverage}x
📈 持仓方向: {position_type.upper()}

🎯 <b>风险管理</b>
🛡️ 止损价格: ${stop_loss:.2f}
💎 止盈价格: ${take_profit:.2f}

🚨 <b>实时监控已启动</b>
⏰ 时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        return self.send_message(message)
    
    def send_stop_loss_alert(self, current_price: float, entry_price: float, 
                           stop_loss_price: float, leverage: int, pnl: float, position_type: str):
        """发送止损警报"""
        message = f"""
🚨 <b>XMR止损触发！</b>
━━━━━━━━━━━━━━━━━━━━━━━━
📉 当前价格: ${current_price:.2f}
💰 入场价格: ${entry_price:.2f}
🛡️ 止损价格: ${stop_loss_price:.2f}

📊 <b>交易详情</b>
📈 持仓方向: {position_type.upper()}
📊 杠杆倍数: {leverage}x
💸 实际亏损: {pnl:.2f}%

⚠️ <b>请及时平仓！</b>
⏰ 触发时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        return self.send_message(message)
    
    def send_take_profit_alert(self, current_price: float, entry_price: float, 
                             take_profit_price: float, leverage: int, pnl: float, position_type: str):
        """发送止盈警报"""
        message = f"""
🎉 <b>XMR止盈触发！</b>
━━━━━━━━━━━━━━━━━━━━━━━━
📈 当前价格: ${current_price:.2f}
💰 入场价格: ${entry_price:.2f}
🎯 止盈价格: ${take_profit_price:.2f}

📊 <b>交易详情</b>
📈 持仓方向: {position_type.upper()}
📊 杠杆倍数: {leverage}x
💎 实际盈利: +{pnl:.2f}%

✅ <b>建议平仓获利！</b>
⏰ 触发时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
"""
        return self.send_message(message)
    
    def send_monitoring_stopped(self):
        """发送监控停止通知"""
        message = f"""
⏹️ <b>XMR监控已停止</b>
━━━━━━━━━━━━━━━━━━━━━━━━
⏰ 停止时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
📝 手动停止监控
"""
        return self.send_message(message)
    
    def send_error_alert(self, error_msg: str):
        """发送错误警报"""
        message = f"""
❌ <b>XMR监控错误</b>
━━━━━━━━━━━━━━━━━━━━━━━━
🚨 错误信息: {error_msg}
⏰ 发生时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

请检查网络连接和系统状态
"""
        return self.send_message(message)


def main():
    """主程序"""
    print("🚀 XMR 合约止损止盈监控系统")
    print("=" * 60)
    
    # 获取用户输入
    try:
        entry_price = float(input(f"💰 请输入入场价格 (默认: 502.41): ") or "502.41")
        leverage = int(input(f"📊 请输入杠杆倍数 (默认: 10): ") or "10")
        
        position_type = input(f"📈 请输入持仓方向 (long/short, 默认: long): ").strip().lower() or "long"
        if position_type not in ['long', 'short']:
            position_type = 'long'
        
        stop_loss_pct = float(input(f"🛡️ 请输入止损百分比 (默认: 3.0%): ") or "3.0")
        take_profit_pct = float(input(f"🎯 请输入止盈百分比 (默认: 5.0%): ") or "5.0")
        
        monitor_interval = int(input(f"⏰ 请输入监控间隔秒数 (默认: 30): ") or "30")
        
    except ValueError:
        print("❌ 输入格式错误，使用默认参数")
        entry_price = 502.41
        leverage = 10
        position_type = 'long'
        stop_loss_pct = 3.0
        take_profit_pct = 5.0
        monitor_interval = 30
    
    # 创建监控器
    monitor = XMRLeverageMonitor(entry_price, leverage)
    monitor.position_type = position_type
    monitor.stop_loss_percentage = stop_loss_pct
    monitor.take_profit_percentage = take_profit_pct
    
    # 重新计算止损止盈价格
    if monitor.position_type == "long":
        monitor.stop_loss_price = monitor.entry_price * (1 - monitor.stop_loss_percentage / 100)
        monitor.take_profit_price = monitor.entry_price * (1 + monitor.take_profit_percentage / 100)
    else:  # short
        monitor.stop_loss_price = monitor.entry_price * (1 + monitor.stop_loss_percentage / 100)
        monitor.take_profit_price = monitor.entry_price * (1 - monitor.take_profit_percentage / 100)
    
    print(f"\n✅ 监控参数确认:")
    print(f"💰 入场价格: ${monitor.entry_price:.2f}")
    print(f"📊 杠杆倍数: {monitor.leverage}x")
    print(f"📈 持仓方向: {monitor.position_type.upper()}")
    print(f"🛡️ 止损价格: ${monitor.stop_loss_price:.2f}")
    print(f"🎯 止盈价格: ${monitor.take_profit_price:.2f}")
    print(f"⏰ 监控间隔: {monitor_interval}秒")
    
    # 启动监控
    try:
        monitor.start_continuous_monitoring(interval=monitor_interval)
    except KeyboardInterrupt:
        print("\n👋 程序已退出")
    finally:
        monitor.save_price_history()

if __name__ == "__main__":
    main()