import pandas as pd
import numpy as np
from datetime import datetime
import json

class Backtester:
    """回测引擎"""
    
    def __init__(self, initial_balance=10000, commission=0.001):
        """
        初始化回测引擎
        :param initial_balance: 初始资金（USDT）
        :param commission: 手续费率（默认0.1%）
        """
        self.initial_balance = initial_balance
        self.commission = commission
        self.reset()
    
    def reset(self):
        """重置回测状态"""
        self.balance = self.initial_balance
        self.position = 0
        self.position_value = 0
        self.entry_price = 0
        self.trades = []
        self.equity_curve = []
    
    def run(self, df, strategy):
        """
        运行回测
        :param df: 包含OHLCV数据的DataFrame
        :param strategy: 策略对象（需要有generate_signals方法）
        :return: 回测结果
        """
        self.reset()
        
        # 生成信号
        df = strategy.generate_signals(df)
        
        for i in range(1, len(df)):
            current = df.iloc[i]
            prev = df.iloc[i-1]
            price = current['close']
            timestamp = df.index[i]
            
            # 检查买入信号
            if current.get('buy_signal', False) and self.position == 0:
                # 计算可买入数量
                amount = (self.balance * 0.95) / price  # 留5%作为手续费缓冲
                cost = amount * price * (1 + self.commission)
                
                if cost <= self.balance:
                    self.balance -= cost
                    self.position = amount
                    self.entry_price = price
                    
                    self.trades.append({
                        'timestamp': timestamp,
                        'type': 'buy',
                        'price': price,
                        'amount': amount,
                        'balance': self.balance
                    })
            
            # 检查卖出信号
            elif current.get('sell_signal', False) and self.position > 0:
                # 卖出全部仓位
                revenue = self.position * price * (1 - self.commission)
                pnl = revenue - (self.position * self.entry_price)
                
                self.balance += revenue
                
                self.trades.append({
                    'timestamp': timestamp,
                    'type': 'sell',
                    'price': price,
                    'amount': self.position,
                    'pnl': pnl,
                    'balance': self.balance
                })
                
                self.position = 0
                self.entry_price = 0
            
            # 记录权益曲线
            equity = self.balance + (self.position * price)
            self.equity_curve.append({
                'timestamp': timestamp,
                'equity': equity,
                'balance': self.balance,
                'position_value': self.position * price
            })
        
        return self.get_results()
    
    def get_results(self):
        """计算回测结果统计"""
        if not self.trades:
            return {
                'total_trades': 0,
                'win_rate': 0,
                'total_pnl': 0,
                'final_balance': self.balance,
                'return_pct': 0,
                'max_drawdown': 0
            }
        
        # 计算胜率
        sell_trades = [t for t in self.trades if t['type'] == 'sell']
        winning_trades = [t for t in sell_trades if t.get('pnl', 0) > 0]
        win_rate = len(winning_trades) / len(sell_trades) if sell_trades else 0
        
        # 计算总盈亏
        total_pnl = sum(t.get('pnl', 0) for t in sell_trades)
        
        # 计算最大回撤
        equity_df = pd.DataFrame(self.equity_curve)
        if len(equity_df) > 0:
            equity_df['peak'] = equity_df['equity'].cummax()
            equity_df['drawdown'] = (equity_df['peak'] - equity_df['equity']) / equity_df['peak']
            max_drawdown = equity_df['drawdown'].max()
        else:
            max_drawdown = 0
        
        # 计算收益率
        final_equity = self.balance + (self.position * (self.trades[-1]['price'] if self.trades else 0))
        return_pct = (final_equity - self.initial_balance) / self.initial_balance
        
        return {
            'total_trades': len(self.trades),
            'buy_trades': len([t for t in self.trades if t['type'] == 'buy']),
            'sell_trades': len(sell_trades),
            'winning_trades': len(winning_trades),
            'losing_trades': len(sell_trades) - len(winning_trades),
            'win_rate': win_rate,
            'total_pnl': total_pnl,
            'avg_pnl': total_pnl / len(sell_trades) if sell_trades else 0,
            'initial_balance': self.initial_balance,
            'final_balance': final_equity,
            'return_pct': return_pct,
            'max_drawdown': max_drawdown,
            'trades': self.trades,
            'equity_curve': self.equity_curve
        }
    
    def print_report(self, results):
        """打印回测报告"""
        print("\n" + "="*50)
        print("📊 回测报告")
        print("="*50)
        print(f"初始资金: {results['initial_balance']:.2f} USDT")
        print(f"最终资金: {results['final_balance']:.2f} USDT")
        print(f"总收益率: {results['return_pct']:.2%}")
        print(f"总盈亏: {results['total_pnl']:.2f} USDT")
        print("-"*50)
        print(f"总交易次数: {results['total_trades']}")
        print(f"买入次数: {results['buy_trades']}")
        print(f"卖出次数: {results['sell_trades']}")
        print(f"盈利次数: {results['winning_trades']}")
        print(f"亏损次数: {results['losing_trades']}")
        print(f"胜率: {results['win_rate']:.2%}")
        print(f"平均盈亏: {results['avg_pnl']:.2f} USDT")
        print(f"最大回撤: {results['max_drawdown']:.2%}")
        print("="*50)


# 测试代码
if __name__ == "__main__":
    from utils.data_loader import DataLoader
    from strategy.ma_strategy import MAStrategy, CombinedStrategy
    
    with open('config.json', 'r') as f:
        config = json.load(f)
    
    # 加载数据
    loader = DataLoader('binance', config['binance']['api_key'], config['binance']['api_secret'])
    df = loader.fetch_ohlcv('BTC/USDT', '1h', 500)  # 获取更多历史数据用于回测
    
    print(f"数据范围: {df.index[0]} 到 {df.index[-1]}")
    print(f"数据条数: {len(df)}")
    
    # 测试MA策略回测
    print("\n>>> MA策略回测 <<<")
    ma_strategy = MAStrategy(fast_period=5, slow_period=20)
    backtester = Backtester(initial_balance=10000, commission=0.001)
    results = backtester.run(df, ma_strategy)
    backtester.print_report(results)
    
    # 测试组合策略回测
    print("\n>>> 组合策略回测 <<<")
    combined_strategy = CombinedStrategy()
    backtester2 = Backtester(initial_balance=10000, commission=0.001)
    results2 = backtester2.run(df, combined_strategy)
    backtester2.print_report(results2)
