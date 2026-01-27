import ccxt
import pandas as pd
import json
import time
from datetime import datetime

from utils.data_loader import DataLoader
from utils.telegram_notify import TelegramNotify
from utils.risk_manager import RiskManager
from strategy.ma_strategy import MAStrategy, RSIStrategy, MACDStrategy, CombinedStrategy
from config_manager import config_manager

# 导入多时间框架配置
try:
    from config_multi_timeframe import (
        TIMEFRAMES, STRATEGY_CONFIG, TRADING_PAIRS, 
        RISK_MANAGEMENT, LEVERAGE_CONFIG, TIME_CONTROLS
    )
    USE_MULTI_TIMEFRAME = True
    print("📊 多时间框架模式已启用")
except ImportError:
    USE_MULTI_TIMEFRAME = False
    print("⚠️  使用默认单时间框架模式")


class TradingBot:
    """量化交易机器人主程序"""
    
    def __init__(self, config_path='config.json'):
        # 使用安全的配置管理器
        self.config_manager = config_manager
        
        # 验证配置
        config_errors = self.config_manager.validate_config()
        if config_errors:
            raise Exception(f"配置错误: {config_errors}")
        
        # 打印配置状态
        self.config_manager.print_config_status()
        
        # 初始化交易所连接
        self.exchange_name = 'binance'
        binance_config = self.config_manager.get_exchange_config('binance')
        
        self.data_loader = DataLoader(
            self.exchange_name,
            binance_config['api_key'],
            binance_config['secret']
        )
        
        # 初始化交易所API（用于下单）
        self.exchange = ccxt.binance({
            'apiKey': binance_config['api_key'],
            'secret': binance_config['secret'],
            'enableRateLimit': True,
            'sandbox': binance_config['sandbox'],  # 自动设置沙盒模式
            'options': {'defaultType': 'spot'}
        })
        
        # 初始化Telegram推送
        telegram_config = self.config_manager.get_telegram_config()
        self.telegram = TelegramNotify(
            telegram_config['bot_token'],
            telegram_config['chat_id']
        )
        
        # 初始化风控
        risk_config = self.config_manager.get_risk_config()
        self.risk_manager = RiskManager(
            max_position_pct=risk_config['max_position_pct'],
            max_loss_pct=risk_config['max_loss_pct'],
            max_daily_trades=risk_config['max_daily_trades']
        )
        
        # 初始化策略
        self.strategy = CombinedStrategy()
        
        # 交易参数
        self.symbols = TRADING_PAIRS['active_pairs']
        self.trend_timeframe = TIMEFRAMES['trend_analysis']  # 日线
        self.entry_timeframe = TIMEFRAMES['entry_signals']   # 15分钟
        self.risk_timeframe = TIMEFRAMES['risk_management']   # 5分钟
        self.positions = {}
        
        print("✅ 交易机器人初始化完成")
        if USE_MULTI_TIMEFRAME:
            print(f"📊 多时间框架策略已启用")
            print(f"   趋势分析: {self.trend_timeframe}")
            print(f"   入场信号: {self.entry_timeframe}")
            print(f"   风险监控: {self.risk_timeframe}")
    
    def analyze_daily_trend(self, symbol):
        """分析日线趋势"""
        df_daily = self.fetch_data(symbol, self.trend_timeframe, 100)
        if df_daily is None or len(df_daily) < 50:
            return {'direction': 'neutral', 'strength': 0}
            
        latest = df_daily.iloc[-1]
        
        # 多重趋势确认
        signals = []
        
        # MA趋势
        if latest['close'] > latest['ma_20'] > latest['ma_50']:
            signals.append(1)
        elif latest['close'] < latest['ma_20'] < latest['ma_50']:
            signals.append(-1)
        else:
            signals.append(0)
            
        # MACD趋势  
        if latest['macd'] > latest['macd_signal'] and latest['macd_hist'] > 0:
            signals.append(1)
        elif latest['macd'] < latest['macd_signal'] and latest['macd_hist'] < 0:
            signals.append(-1)
        else:
            signals.append(0)
            
        # RSI过滤
        rsi_filter = 0
        if latest['rsi'] > 70:
            rsi_filter = -0.5  # 超买减分
        elif latest['rsi'] < 30:
            rsi_filter = 0.5   # 超卖加分
            
        trend_score = sum(signals) + rsi_filter
        
        if trend_score >= 1.5:
            return {'direction': 'bullish', 'strength': min(0.8, trend_score/3)}
        elif trend_score <= -1.5:
            return {'direction': 'bearish', 'strength': min(0.8, abs(trend_score)/3)}
        else:
            return {'direction': 'neutral', 'strength': 0}
    
    def find_entry_signals_15m(self, symbol, trend_direction):
        """15分钟入场信号"""
        df_15m = self.fetch_data(symbol, self.entry_timeframe, 100)
        if df_15m is None or len(df_15m) < 30:
            return {'signal': 'hold', 'confidence': 0}
            
        latest = df_15m.iloc[-1]
        prev = df_15m.iloc[-2]
        confidence = 0
        
        if trend_direction == 'bullish':
            # 多头信号
            if latest['ema_12'] > latest['ema_26'] and prev['ema_12'] <= prev['ema_26']:
                confidence += 0.3  # EMA金叉
            if latest['rsi'] > 35 and prev['rsi'] <= 30:
                confidence += 0.25  # RSI超卖反弹
            if latest['close'] > df_15m['high'].rolling(10).max().shift(1).iloc[-1]:
                confidence += 0.35  # 突破阻力
                
            return {'signal': 'buy', 'confidence': confidence, 'price': latest['close']}
            
        elif trend_direction == 'bearish':
            # 空头信号  
            if latest['ema_12'] < latest['ema_26'] and prev['ema_12'] >= prev['ema_26']:
                confidence += 0.3  # EMA死叉
            if latest['rsi'] < 65 and prev['rsi'] >= 70:
                confidence += 0.25  # RSI超买回落
            if latest['close'] < df_15m['low'].rolling(10).min().shift(1).iloc[-1]:
                confidence += 0.35  # 跌破支撑
                
            return {'signal': 'sell', 'confidence': confidence, 'price': latest['close']}
            
        return {'signal': 'hold', 'confidence': 0}
    
    def fetch_data(self, symbol, timeframe=None, limit=100):
        """获取指定时间框架的行情数据"""
        try:
            if timeframe is None:
                timeframe = self.entry_timeframe
            df = self.data_loader.fetch_ohlcv(symbol, timeframe, limit)
            df = self.data_loader.add_all_indicators(df)
            return df
        except Exception as e:
            print(f"❌ 获取{symbol} {timeframe}数据失败: {e}")
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
                
                # 安全的下单逻辑
                if self.config_manager.is_live_trading():
                    # 实盘交易模式
                    print(f"🚨 实盘交易: 买入 {amount:.6f} {symbol}")
                    order = self.exchange.create_market_buy_order(symbol, amount)
                else:
                    # 模拟交易模式
                    print(f"📝 模拟交易: 买入 {amount:.6f} {symbol}")
                    order = {'id': f'paper_{int(time.time())}', 'status': 'closed'}
                    # 这里可以调用 paper_trading_env 来记录模拟交易
                
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
                
                # 安全的卖出逻辑
                if self.config_manager.is_live_trading():
                    # 实盘交易模式
                    print(f"🚨 实盘交易: 卖出 {pos['amount']:.6f} {symbol}")
                    order = self.exchange.create_market_sell_order(symbol, pos['amount'])
                else:
                    # 模拟交易模式
                    print(f"📝 模拟交易: 卖出 {pos['amount']:.6f} {symbol}")
                    order = {'id': f'paper_{int(time.time())}', 'status': 'closed'}
                
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
            print(f"🚨 {symbol} 触发止损 @ {current_price}")
            pnl = (current_price - pos['entry_price']) * pos['amount']
            
            # 执行卖出
            try:
                if self.config_manager.is_live_trading():
                    print(f"🚨 实盘止损: 卖出 {pos['amount']:.6f} {symbol}")
                    order = self.data_loader.exchange.create_market_sell_order(symbol, pos['amount'])
                    print(f"   订单ID: {order['id']}, 状态: {order['status']}")
                else:
                    print(f"📝 模拟止损: 卖出 {pos['amount']:.6f} {symbol}")
                    order = {'id': f'paper_stop_{int(time.time())}', 'status': 'closed'}
                
                print(f"   止损盈亏: {pnl:.2f} USDT")
                
                # 发送Telegram通知
                self.telegram.send_sell_alert(symbol, current_price, f"触发止损 | 盈亏: {pnl:.2f}U")
                
                # 更新风控记录
                self.risk_manager.update_trade(pnl)
                
                # 清除持仓
                del self.positions[symbol]
                
            except Exception as e:
                print(f"❌ 止损执行失败: {e}")
        
        # 止盈
        elif current_price >= pos['take_profit']:
            print(f"✅ {symbol} 触发止盈 @ {current_price}")
            pnl = (current_price - pos['entry_price']) * pos['amount']
            
            # 执行卖出
            try:
                if self.config_manager.is_live_trading():
                    print(f"🚨 实盘止盈: 卖出 {pos['amount']:.6f} {symbol}")
                    order = self.data_loader.exchange.create_market_sell_order(symbol, pos['amount'])
                    print(f"   订单ID: {order['id']}, 状态: {order['status']}")
                else:
                    print(f"📝 模拟止盈: 卖出 {pos['amount']:.6f} {symbol}")
                    order = {'id': f'paper_tp_{int(time.time())}', 'status': 'closed'}
                
                print(f"   止盈盈亏: {pnl:.2f} USDT")
                
                # 发送Telegram通知
                self.telegram.send_sell_alert(symbol, current_price, f"触发止盈 | 盈亏: {pnl:.2f}U")
                
                # 更新风控记录
                self.risk_manager.update_trade(pnl)
                
                # 清除持仓
                del self.positions[symbol]
                
            except Exception as e:
                print(f"❌ 止盈执行失败: {e}")
        
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
