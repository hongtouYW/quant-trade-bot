# -*- coding: utf-8 -*-
"""
XMR合约价格监控系统 - 自动止损止盈通知
入场价格: $502.41
杠杆: 10倍
"""

import ccxt
import requests
import time
import json
import os
from datetime import datetime, timedelta
from utils.telegram_notify import TelegramNotify

class XMRContractMonitor:
    """XMR合约监控器"""
    
    def __init__(self, entry_price=502.41, leverage=10):
        self.entry_price = entry_price
        self.leverage = leverage
        self.symbol = 'XMR/USDT'
        
        # 止损止盈设置 (基于实际价格，非杠杆)
        self.stop_loss_percent = 2.0    # 2%止损 (实际杠杆后20%亏损)
        self.take_profit_percent = 2.0  # 2%止盈 (实际杠杆后20%盈利)
        
        # 计算具体价位
        self.stop_loss_price = entry_price * (1 - self.stop_loss_percent / 100)
        self.take_profit_price = entry_price * (1 + self.take_profit_percent / 100)
        
        # 预警价位 (提前预警)
        self.warning_distance = 0.5  # 0.5%距离时预警
        self.stop_loss_warning = entry_price * (1 - (self.stop_loss_percent - self.warning_distance) / 100)
        self.take_profit_warning = entry_price * (1 + (self.take_profit_percent - self.warning_distance) / 100)
        
        # 状态跟踪
        self.alerts_sent = {
            'stop_loss_warning': False,
            'take_profit_warning': False,
            'stop_loss': False,
            'take_profit': False
        }
        
        # 初始化Telegram机器人
        try:
            # 读取Telegram配置
            config_path = 'config.json'
            if os.path.exists(config_path):
                with open(config_path, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                    telegram_config = config.get('telegram', {})
                    bot_token = telegram_config.get('bot_token')
                    chat_id = telegram_config.get('chat_id')
                    
                    if bot_token and chat_id:
                        self.telegram = TelegramNotify(bot_token, chat_id)
                        self.telegram_available = True
                    else:
                        self.telegram_available = False
            else:
                self.telegram_available = False
        except Exception as e:
            self.telegram_available = False
            print(f"⚠️ Telegram配置读取失败: {e}")
            
        if not self.telegram_available:
            print("⚠️ Telegram配置不可用，将仅显示控制台消息")
        
        # 交易所连接
        self.exchanges = self._init_exchanges()
        
        print(f"🎯 XMR合约监控系统启动")
        print(f"💰 入场价格: ${entry_price:.2f}")
        print(f"📊 杠杆倍数: {leverage}x")
        print(f"🛡️ 止损价格: ${self.stop_loss_price:.2f} (-{self.stop_loss_percent}%)")
        print(f"🎯 止盈价格: ${self.take_profit_price:.2f} (+{self.take_profit_percent}%)")
        print(f"⚠️ 止损预警: ${self.stop_loss_warning:.2f}")
        print(f"⚠️ 止盈预警: ${self.take_profit_warning:.2f}")
    
    def _init_exchanges(self):
        """初始化交易所"""
        exchanges = {}
        
        try:
            exchanges['kraken'] = ccxt.kraken({'enableRateLimit': True, 'timeout': 30000})
        except:
            pass
        
        try:
            exchanges['bitfinex'] = ccxt.bitfinex({'enableRateLimit': True, 'timeout': 30000})
        except:
            pass
        
        return exchanges
    
    def get_current_price(self):
        """获取当前价格"""
        prices = []
        
        # 方法1: CoinGecko
        try:
            response = requests.get(
                'https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies=usd',
                timeout=10
            )
            data = response.json()
            prices.append(data['monero']['usd'])
        except:
            pass
        
        # 方法2: 交易所价格
        for exchange_name, exchange in self.exchanges.items():
            try:
                for symbol in ['XMR/USDT', 'XMR/USD']:
                    try:
                        ticker = exchange.fetch_ticker(symbol)
                        price = ticker.get('last', 0)
                        if price > 0:
                            prices.append(price)
                            break
                    except:
                        continue
            except:
                continue
        
        if prices:
            # 使用价格中位数
            import statistics
            return statistics.median(prices)
        
        return None
    
    def calculate_pnl(self, current_price):
        """计算盈亏和投资回报率"""
        price_change_percent = (current_price - self.entry_price) / self.entry_price * 100
        leveraged_pnl_percent = price_change_percent * self.leverage
        
        # 计算投资回报率 (ROI)
        roi = leveraged_pnl_percent
        
        # 假设100U本金计算具体盈亏金额
        capital = 100  # 假设本金
        unrealized_pnl_usd = (roi / 100) * capital
        
        return {
            'price_change_percent': price_change_percent,
            'leveraged_pnl_percent': leveraged_pnl_percent,
            'roi': roi,  # 投资回报率
            'unrealized_pnl_usd': unrealized_pnl_usd,
            'capital': capital
        }
    
    def send_alert(self, alert_type, current_price, pnl_data):
        """发送警报"""
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # 构建消息
        if alert_type == 'stop_loss_warning':
            message = f"⚠️ XMR止损预警 ⚠️\n"
            emoji = "🟡"
        elif alert_type == 'take_profit_warning':
            message = f"⚠️ XMR止盈预警 ⚠️\n"
            emoji = "🟡"
        elif alert_type == 'stop_loss':
            message = f"🚨 XMR止损触发 🚨\n"
            emoji = "🔴"
        elif alert_type == 'take_profit':
            message = f"🎉 XMR止盈触发 🎉\n"
            emoji = "🟢"
        else:
            message = f"📊 XMR价格更新\n"
            emoji = "📊"
        
        message += f"━━━━━━━━━━━━━━━━━━━━\n"
        message += f"💰 当前价格: ${current_price:.2f}\n"
        message += f"📈 入场价格: ${self.entry_price:.2f}\n"
        message += f"📊 价格变化: {pnl_data['price_change_percent']:+.2f}%\n"
        message += f"💎 杠杆: {self.leverage}x\n"
        message += f"💵 投资回报率: {pnl_data['roi']:+.2f}%\n"
        # 盈亏金额颜色显示
        pnl_amount = pnl_data['unrealized_pnl_usd']
        if pnl_amount >= 0:
            pnl_color = "🟢"
        else:
            pnl_color = "🔴"
        message += f"💰 盈亏金额: {pnl_color}${pnl_amount:+.2f}U\n"
        message += f"━━━━━━━━━━━━━━━━━━━━\n"
        message += f"🛡️ 止损价格: ${self.stop_loss_price:.2f}\n"
        message += f"🎯 止盈价格: ${self.take_profit_price:.2f}\n"
        message += f"⏰ 时间: {timestamp}"
        
        # 控制台输出
        print(f"\n{emoji} {alert_type.upper()}")
        print("="*50)
        print(message)
        print("="*50)
        
        # Telegram通知
        if self.telegram_available:
            try:
                self.telegram.send_message(message)
                print("✅ Telegram通知已发送")
            except Exception as e:
                print(f"❌ Telegram发送失败: {e}")
        
        # 标记为已发送
        self.alerts_sent[alert_type] = True
    
    def check_triggers(self, current_price):
        """检查触发条件"""
        pnl_data = self.calculate_pnl(current_price)
        
        # 止损触发
        if current_price <= self.stop_loss_price and not self.alerts_sent['stop_loss']:
            self.send_alert('stop_loss', current_price, pnl_data)
            return 'stop_loss'
        
        # 止盈触发
        elif current_price >= self.take_profit_price and not self.alerts_sent['take_profit']:
            self.send_alert('take_profit', current_price, pnl_data)
            return 'take_profit'
        
        # 止损预警
        elif current_price <= self.stop_loss_warning and not self.alerts_sent['stop_loss_warning']:
            self.send_alert('stop_loss_warning', current_price, pnl_data)
            return 'stop_loss_warning'
        
        # 止盈预警
        elif current_price >= self.take_profit_warning and not self.alerts_sent['take_profit_warning']:
            self.send_alert('take_profit_warning', current_price, pnl_data)
            return 'take_profit_warning'
        
        return None
    
    def display_status(self, current_price):
        """显示当前状态"""
        pnl_data = self.calculate_pnl(current_price)
        
        # 距离止损止盈的距离
        distance_to_stop_loss = ((current_price - self.stop_loss_price) / current_price) * 100
        distance_to_take_profit = ((self.take_profit_price - current_price) / current_price) * 100
        
        print(f"\n📊 {datetime.now().strftime('%H:%M:%S')} XMR合约状态")
        print(f"💰 当前价格: ${current_price:.2f}")
        print(f"📈 入场价格: ${self.entry_price:.2f}")
        print(f"📊 价格变化: {pnl_data['price_change_percent']:+.2f}%")
        print(f"💎 杠杆倍数: {self.leverage}x")
        print(f"💵 投资回报率: {pnl_data['roi']:+.2f}%")
        # 盈亏金额颜色显示
        pnl_amount = pnl_data['unrealized_pnl_usd']
        if pnl_amount >= 0:
            color_code = "\033[92m"  # 绿色
            reset_code = "\033[0m"   # 重置颜色
        else:
            color_code = "\033[91m"  # 红色  
            reset_code = "\033[0m"   # 重置颜色
        print(f"💰 盈亏金额: {color_code}${pnl_amount:+.2f}U{reset_code} (本金{pnl_data['capital']}U)")
        print(f"🛡️ 距止损: {distance_to_stop_loss:.2f}% (${self.stop_loss_price:.2f})")
        print(f"🎯 距止盈: {distance_to_take_profit:.2f}% (${self.take_profit_price:.2f})")
        
        # 风险状态
        if distance_to_stop_loss < 1:
            print("⚠️ 风险状态: 高危 - 接近止损")
        elif distance_to_take_profit < 1:
            print("🎯 状态: 接近止盈目标")
        else:
            print("📊 状态: 正常监控中")
    
    def run_monitoring(self, interval=300):
        """开始监控"""
        print(f"\n🚀 开始XMR合约监控")
        print(f"⏰ 检查间隔: {interval}秒 ({interval//60}分钟)")
        print(f"🔔 Telegram通知: {'✅ 已启用' if self.telegram_available else '❌ 未启用'}")
        print("按 Ctrl+C 停止监控")
        print("-" * 50)
        
        # 发送启动通知
        if self.telegram_available:
            start_message = f"🎯 XMR合约监控已启动\n"
            start_message += f"💰 入场价格: ${self.entry_price:.2f}\n"
            start_message += f"📊 杠杆: {self.leverage}x\n"
            start_message += f"🛡️ 止损: ${self.stop_loss_price:.2f}\n"
            start_message += f"🎯 止盈: ${self.take_profit_price:.2f}\n"
            start_message += f"⏰ 监控间隔: {interval}秒"
            
            try:
                self.telegram.send_message(start_message)
            except:
                pass
        
        try:
            while True:
                current_price = self.get_current_price()
                
                if current_price:
                    # 检查触发条件
                    trigger = self.check_triggers(current_price)
                    
                    # 显示状态
                    self.display_status(current_price)
                    
                    # 如果触发止损或止盈，询问是否继续监控
                    if trigger in ['stop_loss', 'take_profit']:
                        print(f"\n⚠️ 已触发{trigger}，是否继续监控？")
                        print("1. 继续监控")
                        print("2. 停止监控")
                        
                        try:
                            choice = input("请选择 (1-2，10秒后自动继续): ")
                            if choice == '2':
                                break
                        except:
                            print("自动继续监控...")
                    
                else:
                    print(f"❌ 无法获取价格数据，将在{interval}秒后重试")
                
                # 等待下次检查
                print(f"⏳ 下次检查: {(datetime.now() + timedelta(seconds=interval)).strftime('%H:%M:%S')}")
                time.sleep(interval)
        
        except KeyboardInterrupt:
            print(f"\n👋 监控已停止")
            
            # 发送停止通知
            if self.telegram_available:
                try:
                    self.telegram.send_message("⏹️ XMR合约监控已停止")
                except:
                    pass
        
        except Exception as e:
            error_message = f"❌ 监控程序异常: {e}"
            print(error_message)
            
            # 发送错误通知
            if self.telegram_available:
                try:
                    self.telegram.send_message(f"🚨 XMR监控异常\n{error_message}")
                except:
                    pass

def main():
    """主程序"""
    print("🎯 XMR合约监控系统")
    print("=" * 50)
    
    # 当前参数
    entry_price = 502.41
    leverage = 10
    
    print(f"💰 入场价格: ${entry_price}")
    print(f"📊 杠杆倍数: {leverage}x")
    
    # 让用户确认或修改参数
    try:
        new_entry = input(f"确认入场价格 (当前${entry_price}，回车确认): ").strip()
        if new_entry:
            entry_price = float(new_entry)
        
        new_leverage = input(f"确认杠杆倍数 (当前{leverage}x，回车确认): ").strip()
        if new_leverage:
            leverage = int(new_leverage)
    except:
        print("使用默认参数")
    
    # 创建监控器
    monitor = XMRContractMonitor(entry_price=entry_price, leverage=leverage)
    
    # 设置监控间隔
    print(f"\n⏰ 监控间隔选择:")
    print(f"1. 300秒 (5分钟) (推荐)")
    print(f"2. 60秒 (1分钟)")
    print(f"3. 30秒")
    
    try:
        interval_choice = input("请选择 (1-3，回车默认5分钟): ").strip()
        intervals = {'1': 300, '2': 60, '3': 30}
        interval = intervals.get(interval_choice, 300)
    except:
        interval = 300
    
    # 开始监控
    monitor.run_monitoring(interval=interval)

if __name__ == "__main__":
    main()