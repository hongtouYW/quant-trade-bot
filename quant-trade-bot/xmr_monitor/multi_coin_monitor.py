# -*- coding: utf-8 -*-
"""
多币种监控系统 - 支持同时监控多个币种
包含 XMR 和 MEMES
"""

import requests
import json
import time
import os
from datetime import datetime

class CoinMonitor:
    """单个币种监控器"""
    
    def __init__(self, symbol, coin_id, entry_price, investment, leverage=1, stop_loss_percent=2.0, take_profit_percent=2.0, contract_address=None, is_short=False):
        self.symbol = symbol.upper()  # 如 XMR, MEMES
        self.coin_id = coin_id  # CoinGecko ID 或 'dex'
        self.contract_address = contract_address  # DEX代币合约地址
        self.entry_price = entry_price
        self.investment = investment  # 投资金额 (USDT)
        self.leverage = leverage
        self.is_short = is_short  # 是否做空
        
        # 止损止盈设置（做空时逻辑相反）
        self.stop_loss_percent = stop_loss_percent
        self.take_profit_percent = take_profit_percent
        
        if is_short:
            # 做空：止损价格更高，止盈价格更低
            self.stop_loss_price = entry_price * (1 + stop_loss_percent / 100)
            self.take_profit_price = entry_price * (1 - take_profit_percent / 100)
        else:
            # 做多：止损价格更低，止盈价格更高
            self.stop_loss_price = entry_price * (1 - stop_loss_percent / 100)
            self.take_profit_price = entry_price * (1 + take_profit_percent / 100)
        
        # 预警价位
        self.warning_distance = 0.5
        self.stop_loss_warning = entry_price * (1 - (stop_loss_percent - self.warning_distance) / 100)
        self.take_profit_warning = entry_price * (1 + (take_profit_percent - self.warning_distance) / 100)
        
        # 状态跟踪
        self.alerts_sent = {
            'stop_loss_warning': False,
            'take_profit_warning': False,
            'stop_loss': False,
            'take_profit': False
        }
        
        direction = "🔴 做空" if is_short else "🟢 做多"
        print(f"✅ {self.symbol} 监控已初始化 {direction}")
        print(f"   入场价格: ${entry_price:.6f}")
        print(f"   投资金额: ${investment:.2f}U")
        print(f"   杠杆倍数: {leverage}x")
        if is_short:
            print(f"   止损价格: ${self.stop_loss_price:.6f} (+{stop_loss_percent}%)")
            print(f"   止盈价格: ${self.take_profit_price:.6f} (-{take_profit_percent}%)")
        else:
            print(f"   止损价格: ${self.stop_loss_price:.6f} (-{stop_loss_percent}%)")
            print(f"   止盈价格: ${self.take_profit_price:.6f} (+{take_profit_percent}%)")
    
    def get_price(self):
        """获取币种价格 - 支持CoinGecko、Binance和DEX"""
        # 如果有合约地址，使用DexScreener API
        if self.contract_address:
            try:
                url = f'https://api.dexscreener.com/latest/dex/tokens/{self.contract_address}'
                response = requests.get(url, timeout=10)
                if response.status_code == 200:
                    data = response.json()
                    if data.get('pairs'):
                        price = float(data['pairs'][0]['priceUsd'])
                        print(f"✅ {self.symbol} 价格 (DexScreener): ${price:.6f}")
                        return price
            except Exception as e:
                print(f"❌ {self.symbol} DexScreener获取失败: {e}")
                return None
        
        # 先尝试CoinGecko
        try:
            url = f'https://api.coingecko.com/api/v3/simple/price?ids={self.coin_id}&vs_currencies=usd'
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                if self.coin_id in data:
                    price = data[self.coin_id]['usd']
                    print(f"✅ {self.symbol} 价格 (CoinGecko): ${price:.6f}")
                    return price
        except Exception as e:
            print(f"⚠️ {self.symbol} CoinGecko获取失败: {e}")
        
        # 如果CoinGecko失败，尝试Binance
        try:
            binance_symbol = f"{self.symbol}USDT"
            url = f"https://api.binance.com/api/v3/ticker/price?symbol={binance_symbol}"
            response = requests.get(url, timeout=10)
            if response.status_code == 200:
                data = response.json()
                price = float(data['price'])
                print(f"✅ {self.symbol} 价格 (Binance): ${price:.6f}")
                return price
        except Exception as e:
            print(f"❌ {self.symbol} Binance获取失败: {e}")
        
        return None
    
    def calculate_pnl(self, current_price):
        """计算盈亏（支持做空）"""
        if self.is_short:
            # 做空：价格下跌赚钱，价格上涨亏钱
            price_change_percent = (self.entry_price - current_price) / self.entry_price * 100
        else:
            # 做多：价格上涨赚钱，价格下跌亏钱
            price_change_percent = (current_price - self.entry_price) / self.entry_price * 100
        
        leveraged_roi = price_change_percent * self.leverage
        pnl_amount = (leveraged_roi / 100) * self.investment
        total_balance = self.investment + pnl_amount
        
        return {
            'price_change_percent': price_change_percent,
            'roi': leveraged_roi,
            'pnl_amount': pnl_amount,
            'total_balance': total_balance,
            'current_price': current_price
        }
    
    def get_status(self, current_price):
        """获取当前状态（支持做空）+ XMR关键价位提醒"""
        # XMR特殊价位提醒 - 计算详细止损信息
        if self.symbol == 'XMR':
            # 计算当前损失百分比
            pnl_data = self.calculate_pnl(current_price)
            loss_percent = abs(pnl_data['roi'])
            
            if current_price >= 475:
                return "🎯 建议减仓30%"
            elif current_price >= 470:
                return "⚡ 接近减仓位"
            elif current_price <= 460:
                # 计算止损时的损失
                stop_loss_pnl = self.calculate_pnl(460)
                stop_loss_percent = abs(stop_loss_pnl['roi'])
                stop_loss_amount = abs(stop_loss_pnl['pnl_amount'])
                return f"🚨🚨 止损价！立即平仓 | 损失{stop_loss_percent:.1f}% (${stop_loss_amount:.0f})"
            elif current_price <= 463:
                # 计算接近止损时的损失
                current_loss_amount = abs(pnl_data['pnl_amount'])
                stop_loss_pnl = self.calculate_pnl(460)
                stop_loss_percent = abs(stop_loss_pnl['roi'])
                return f"⚠️⚠️ 接近$460止损线 | 当前损失{loss_percent:.1f}% (${current_loss_amount:.0f}) | 止损将损失{stop_loss_percent:.1f}%"
        
        if self.is_short:
            # 做空：价格跌破止盈目标为止盈，价格突破止损价格为止损
            if current_price <= self.take_profit_price:
                return "🎉 已达止盈"
            elif current_price <= self.take_profit_warning:
                return "⚠️ 接近止盈"
            elif current_price >= self.stop_loss_price:
                return "🚨 已触止损"
            elif current_price >= self.stop_loss_warning:
                return "⚠️ 接近止损"
            else:
                return "📊 正常"
        else:
            # 做多：价格突破止盈目标为止盈，价格跌破止损价格为止损
            if current_price >= self.take_profit_price:
                return "🎉 已达止盈"
            elif current_price >= self.take_profit_warning:
                return "⚠️ 接近止盈"
            elif current_price <= self.stop_loss_price:
                return "🚨 已触止损"
            elif current_price <= self.stop_loss_warning:
                return "⚠️ 接近止损"
            else:
                return "📊 正常"
    
    def format_status_message(self, current_price):
        """格式化状态消息"""
        pnl_data = self.calculate_pnl(current_price)
        status = self.get_status(current_price)
        
        pnl_emoji = "📈" if pnl_data['pnl_amount'] >= 0 else "📉"
        roi_emoji = "🟢" if pnl_data['roi'] >= 0 else "🔴"
        
        direction = "🔴 做空" if self.is_short else "🟢 做多"
        return f"""<b>{self.symbol}</b> {status} {direction}
💰 现价: ${current_price:.6f}
📊 入场: ${self.entry_price:.6f}
📈 涨跌: {pnl_data['price_change_percent']:+.2f}%
💎 杠杆: {self.leverage}x
━━━━━━━━━━━━━━
💵 ROI: {roi_emoji} {pnl_data['roi']:+.2f}%
💰 盈亏: {pnl_emoji} ${pnl_data['pnl_amount']:+.2f}U"""


class MultiCoinMonitor:
    """多币种监控系统"""
    
    def __init__(self):
        self.coins = {}
        self.telegram_available = self._init_telegram()
        
        print("🎯 多币种监控系统启动")
        print(f"📱 Telegram通知: {'✅启用' if self.telegram_available else '❌未启用'}")
    
    def _init_telegram(self):
        """初始化Telegram"""
        try:
            config_paths = [
                'config.json',
                '../config.json', 
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
    
    def add_coin(self, symbol, coin_id, entry_price, investment, leverage=1, stop_loss_percent=2.0, take_profit_percent=2.0, contract_address=None, is_short=False):
        """添加币种监控"""
        coin = CoinMonitor(
            symbol=symbol,
            coin_id=coin_id,
            entry_price=entry_price,
            investment=investment,
            leverage=leverage,
            stop_loss_percent=stop_loss_percent,
            take_profit_percent=take_profit_percent,
            contract_address=contract_address,
            is_short=is_short
        )
        self.coins[symbol.upper()] = coin
        print(f"✅ {symbol.upper()} 已添加到监控列表")
    
    def check_network(self):
        """检查网络连接"""
        try:
            response = requests.get("https://api.coingecko.com/api/v3/ping", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def update_all_coins(self):
        """更新所有币种价格并发送通知"""
        if not self.check_network():
            print("❌ 网络连接失败")
            return False
        
        messages = []
        total_investment = 0
        total_pnl = 0
        
        print(f"\n{'='*60}")
        print(f"📊 多币种监控更新 - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"{'='*60}")
        
        for symbol, coin in self.coins.items():
            current_price = coin.get_price()
            if current_price:
                message = coin.format_status_message(current_price)
                messages.append(message)
                
                pnl_data = coin.calculate_pnl(current_price)
                total_investment += coin.investment
                total_pnl += pnl_data['pnl_amount']
        
        if messages:
            # 计算总体统计（仅用于控制台显示）
            total_balance = total_investment + total_pnl
            total_roi = (total_pnl / total_investment * 100) if total_investment > 0 else 0
            
            # 只发送XMR消息（重点关注）
            xmr_message = None
            for msg in messages:
                if '<b>XMR</b>' in msg:
                    xmr_message = msg
                    break
            
            if xmr_message:
                # 检查是否需要@提醒（只在关键价位）
                xmr_coin = self.coins.get('XMR')
                need_alert = False
                alert_prefix = ""
                
                if xmr_coin:
                    current_price = xmr_coin.get_price()
                    if current_price:
                        # 关键价位判断
                        if current_price >= 475:
                            need_alert = True
                            alert_prefix = "🎯 <b>减仓提醒</b> @Hzai5522\n"
                        elif current_price >= 470:
                            need_alert = True
                            alert_prefix = "⚡ <b>接近减仓位</b> @Hzai5522\n"
                        elif current_price <= 460:
                            need_alert = True
                            alert_prefix = "🚨🚨 <b>止损警报</b> @Hzai5522\n"
                        elif current_price <= 463:
                            need_alert = True
                            alert_prefix = "⚠️ <b>接近止损</b> @Hzai5522\n"
                
                # 构建消息
                if need_alert:
                    full_message = alert_prefix
                else:
                    full_message = "📊 <b>XMR 监控</b>\n"
                
                full_message += "━━━━━━━━━━━━━━\n\n"
                full_message += xmr_message
                
                # 根据价格添加操作建议
                if xmr_coin and current_price:
                        full_message += "\n\n━━━━━━━━━━━━━━\n"
                        if current_price >= 475:
                            full_message += "📢 <b>建议：立即平仓30%</b>\n"
                        elif current_price >= 470:
                            full_message += "💡 提示：接近$475减仓位\n"
                        elif current_price <= 460:
                            full_message += "🚨 <b>紧急：跌破止损！全平！</b>\n"
                        elif current_price <= 463:
                            full_message += "⚠️ 警告：接近$460止损线\n"
                        else:
                            full_message += "📊 状态：观望中，等待$470\n"
                
                full_message += f"\n⏰ {datetime.now().strftime('%H:%M:%S')}"
                self.send_telegram_message(full_message)
            
            # 控制台显示
            print(f"\n💎 总投资: ${total_investment:.2f}U")
            print(f"💰 总盈亏: ${total_pnl:+.2f}U")
            print(f"💵 总ROI: {total_roi:+.2f}%")
            print(f"💳 总余额: ${total_balance:.2f}U")
            print(f"{'='*60}\n")
            
            return True
        
        return False


def main():
    print("📱 多币种定期通知版 - 每5分钟自动发送")
    print("=" * 60)
    
    # 创建监控系统
    monitor = MultiCoinMonitor()
    
    # 添加 XMR 监控（做多20倍杠杆 - 已补仓）
    monitor.add_coin(
        symbol='XMR',
        coin_id='monero',
        entry_price=480.43,  # 补仓后平均价
        investment=3583.61,  # 总保证金（更新）
        leverage=20,
        stop_loss_percent=2.0,
        take_profit_percent=2.0,
        is_short=False  # 做多
    )
    
    # 添加 MEMES 监控 (DEX代币 - memes will continue)
    # 数量: 113,322.1889978 MEMES
    monitor.add_coin(
        symbol='MEMES',
        coin_id='dex',  # 标记为DEX代币
        entry_price=0.008810,
        investment=998.27,  # 113322.1889978 × 0.008810
        leverage=1,
        stop_loss_percent=5.0,
        take_profit_percent=10.0,
        contract_address='0xf74548802f4c700315f019fde17178b392ee4444'  # 以太坊合约地址
    )
    
    print("\n" + "=" * 60)
    print(f"监控币种数: {len(monitor.coins)}")
    print("=" * 60)
    
    # 发送启动消息
    if monitor.telegram_available:
        startup_msg = f"""🎯 <b>多币种监控启动</b>
━━━━━━━━━━━━━━
📊 监控币种: {len(monitor.coins)}个
💰 总投资: $1,100U

<b>XMR</b>
💰 入场: $502.41
💎 投资: $100U
📊 杠杆: 10x

<b>MEMES</b>
💰 入场: $0.008810
💎 投资: $1,000U
📊 杠杆: 1x

⏰ 更新频率: 5分钟
⏰ 启动时间: {datetime.now().strftime('%H:%M:%S')}"""
        monitor.send_telegram_message(startup_msg)
    
    # 定期更新
    try:
        while True:
            success = monitor.update_all_coins()
            
            if success:
                print("✅ 5分钟定时更新已完成")
            else:
                print("❌ 更新失败，5分钟后重试")
            
            print("⏳ 下次更新: 5分钟后")
            time.sleep(300)  # 5分钟
            
    except KeyboardInterrupt:
        print("\n👋 多币种监控已停止")
        if monitor.telegram_available:
            monitor.send_telegram_message("⏹️ 多币种监控已停止")

if __name__ == "__main__":
    main()
