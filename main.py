import ccxt
import pandas as pd
import json
import time
from datetime import datetime

from utils.data_loader import DataLoader
from utils.telegram_notify import TelegramNotify
from utils.risk_manager import RiskManager
from strategy.ma_strategy import MAStrategy, RSIStrategy, MACDStrategy, CombinedStrategy


class TradingBot:
    """量化交易机器人主程序"""
    
    def __init__(self, config_path='config.json'):
        # 加载配置
        with open(config_path, 'r') as f:
            self.config = json.load(f)
        
        # 初始化交易所连接
        self.exchange_name = 'binance'
        self.data_loader = DataLoader(
            self.exchange_name,
            self.config['binance']['api_key'],
            self.config['binance']['api_secret']
        )
        
        # 初始化交易所API（用于下单）
        self.exchange = ccxt.binance({
            'apiKey': self.config['binance']['api_key'],
            'secret': self.config['binance']['api_secret'],
            'enableRateLimit': True,
            'options': {'defaultType': 'spot'}
        })
        
        # 初始化Telegram推送
        self.telegram = TelegramNotify(
            self.config['telegram']['bot_token'],
            self.config['telegram']['chat_id']
        )
        
        # 初始化风控
        self.risk_manager = RiskManager(
            max_position_pct=0.1,
            max_loss_pct=0.02,
            max_daily_trades=10
        )
        
        # 初始化策略
        self.strategy = CombinedStrategy()
        
        # 交易参数
        self.symbols = ['ETH/USDT', 'BTC/USDT']
        self.timeframe = '1h'
        self.positions = {}
        
        print("✅ 交易机器人初始化完成")
    
    def fetch_data(self, symbol, limit=100):
        """获取行情数据"""
        try:
            df = self.data_loader.fetch_ohlcv(symbol, self.timeframe, limit)
            df = self.data_loader.add_all_indicators(df)
            return df
        except Exception as e:
            print(f"❌ 获取{symbol}数据失败: {e}")
            return None
    
    def check_indicators(self, symbol, df):
        """检查指标并发送报警"""
        if df is None or len(df) < 2:
            return
        
        latest = df.iloc[-1]
        price = latest['close']
        
        # RSI报警
        if latest['rsi'] < 30:
            self.telegram.send_indicator_alert(symbol, "RSI", latest['rsi'], 30, "down")
        elif latest['rsi'] > 70:
            self.telegram.send_indicator_alert(symbol, "RSI", latest['rsi'], 70, "up")
        
        # 布林带报警
        if price < latest['bb_lower']:
            self.telegram.send_indicator_alert(symbol, "价格触及布林下轨", price, latest['bb_lower'], "down")
        elif price > latest['bb_upper']:
            self.telegram.send_indicator_alert(symbol, "价格触及布林上轨", price, latest['bb_upper'], "up")
    
    def execute_signal(self, symbol, signal, reason, df):
        """执行交易信号"""
        if signal is None:
            return
        
        # 检查风控
        can_trade, msg = self.risk_manager.can_trade()
        if not can_trade:
            print(f"⚠️ 风控限制: {msg}")
            return
        
        latest = df.iloc[-1]
        price = latest['close']
        atr = latest['atr'] if 'atr' in latest else None
        
        try:
            # 获取账户余额
            balance = self.exchange.fetch_balance()
            usdt_balance = balance['USDT']['free']
            
            if signal == 'buy' and symbol not in self.positions:
                # 计算仓位
                amount = self.risk_manager.calculate_position_size(usdt_balance, price, atr)
                
                if amount * price < 10:  # 最小交易额检查
                    print(f"⚠️ 交易金额过小，跳过")
                    return
                
                # 计算止损止盈
                stop_loss = self.risk_manager.calculate_stop_loss(price, 'buy', atr)
                take_profit = self.risk_manager.calculate_take_profit(price, stop_loss, 'buy')
                
                # 下单（模拟模式，实际下单需取消注释）
                # order = self.exchange.create_market_buy_order(symbol, amount)
                
                print(f"🟢 买入信号: {symbol} @ {price}, 数量: {amount:.6f}")
                print(f"   止损: {stop_loss:.2f}, 止盈: {take_profit:.2f}")
                
                # 发送Telegram通知
                self.telegram.send_buy_alert(symbol, price, reason)
                
                # 记录持仓
                self.positions[symbol] = {
                    'amount': amount,
                    'entry_price': price,
                    'stop_loss': stop_loss,
                    'take_profit': take_profit
                }
            
            elif signal == 'sell' and symbol in self.positions:
                pos = self.positions[symbol]
                
                # 下单（模拟模式，实际下单需取消注释）
                # order = self.exchange.create_market_sell_order(symbol, pos['amount'])
                
                pnl = (price - pos['entry_price']) * pos['amount']
                print(f"🔴 卖出信号: {symbol} @ {price}, 盈亏: {pnl:.2f} USDT")
                
                # 发送Telegram通知
                self.telegram.send_sell_alert(symbol, price, reason)
                
                # 更新风控记录
                self.risk_manager.update_trade(pnl)
                
                # 清除持仓
                del self.positions[symbol]
        
        except Exception as e:
            print(f"❌ 执行交易失败: {e}")
    
    def check_stop_loss_take_profit(self, symbol, current_price):
        """检查止损止盈"""
        if symbol not in self.positions:
            return
        
        pos = self.positions[symbol]
        
        # 止损
        if current_price <= pos['stop_loss']:
            print(f"⚠️ {symbol} 触发止损 @ {current_price}")
            self.telegram.send_sell_alert(symbol, current_price, "触发止损")
            # 执行卖出...
            del self.positions[symbol]
        
        # 止盈
        elif current_price >= pos['take_profit']:
            print(f"✅ {symbol} 触发止盈 @ {current_price}")
            self.telegram.send_sell_alert(symbol, current_price, "触发止盈")
            # 执行卖出...
            del self.positions[symbol]
        
        # 更新移动止损
        else:
            new_stop = self.risk_manager.get_trailing_stop(
                pos['entry_price'], current_price, 'buy'
            )
            if new_stop > pos['stop_loss']:
                pos['stop_loss'] = new_stop
                print(f"📈 {symbol} 更新移动止损至 {new_stop:.2f}")
    
    def run_once(self):
        """运行一次检查"""
        print(f"\n{'='*50}")
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"{'='*50}")
        
        for symbol in self.symbols:
            print(f"\n📊 检查 {symbol}...")
            
            # 获取数据
            df = self.fetch_data(symbol)
            if df is None:
                continue
            
            # 生成信号
            df = self.strategy.generate_signals(df)
            
            # 检查信号
            signal, reason = self.strategy.check_signal(df)
            
            if signal:
                print(f"   信号: {signal.upper()}, 原因: {reason}")
                self.execute_signal(symbol, signal, reason, df)
            else:
                print(f"   无交易信号")
            
            # 检查止损止盈
            current_price = df.iloc[-1]['close']
            self.check_stop_loss_take_profit(symbol, current_price)
            
            # 打印当前指标
            latest = df.iloc[-1]
            print(f"   价格: {current_price:.2f}")
            print(f"   RSI: {latest['rsi']:.2f}")
            print(f"   MA5: {latest['ma5']:.2f}, MA20: {latest['ma20']:.2f}")
    
    def run(self, interval=300):
        """持续运行"""
        print("🚀 交易机器人启动")
        self.telegram.send_message("🚀 交易机器人已启动")
        
        while True:
            try:
                self.run_once()
                print(f"\n⏳ 等待 {interval} 秒后下次检查...")
                time.sleep(interval)
            
            except KeyboardInterrupt:
                print("\n👋 交易机器人已停止")
                self.telegram.send_message("👋 交易机器人已停止")
                break
            
            except Exception as e:
                print(f"❌ 运行错误: {e}")
                time.sleep(60)


# 主程序入口
if __name__ == "__main__":
    import sys
    
    bot = TradingBot('config.json')
    
    if len(sys.argv) > 1 and sys.argv[1] == 'once':
        # 单次运行模式
        bot.run_once()
    else:
        # 持续运行模式（每5分钟检查一次）
        bot.run(interval=300)
