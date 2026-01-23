import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import json
import ccxt
import time
from strategy import *

class StrategyBacktester:
    """多策略回测分析引擎"""
    
    def __init__(self, initial_balance=10000, commission=0.001):
        """
        初始化回测引擎
        :param initial_balance: 初始资金（USDT）
        :param commission: 手续费率（默认0.1%）
        """
        self.initial_balance = initial_balance
        self.commission = commission
        
    def fetch_historical_data(self, symbol='BTC/USDT', timeframe='1h', months=6):
        """获取历史数据"""
        print(f"📊 获取 {symbol} 过去 {months} 个月的 {timeframe} 数据...")
        
        # 计算开始时间
        end_time = datetime.now()
        start_time = end_time - timedelta(days=months * 30)
        
        try:
            with open('config.json', 'r') as f:
                config = json.load(f)
            
            exchange = ccxt.binance({
                'apiKey': config['exchanges']['binance']['api_key'],
                'secret': config['exchanges']['binance']['secret'],
                'sandbox': False
            })
            
            # 获取K线数据
            since = int(start_time.timestamp() * 1000)
            limit = 1000
            all_ohlcv = []
            
            while since < int(end_time.timestamp() * 1000):
                try:
                    ohlcv = exchange.fetch_ohlcv(symbol, timeframe, since, limit)
                    if not ohlcv:
                        break
                    
                    all_ohlcv.extend(ohlcv)
                    since = ohlcv[-1][0] + 1
                    time.sleep(0.1)  # 避免API限制
                    
                except Exception as e:
                    print(f"获取数据时出错: {e}")
                    break
            
            # 转换为DataFrame
            df = pd.DataFrame(all_ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
            df.set_index('timestamp', inplace=True)
            
            print(f"✅ 获取到 {len(df)} 条数据，时间范围: {df.index[0]} 到 {df.index[-1]}")
            return df
            
        except Exception as e:
            print(f"❌ 获取历史数据失败: {e}")
            # 返回模拟数据
            return self.generate_mock_data(months)
    
    def generate_mock_data(self, months=6):
        """生成模拟数据（当API不可用时）"""
        print("🔄 生成模拟数据用于回测...")
        
        # 生成6个月的小时数据
        periods = months * 30 * 24  # 小时数
        dates = pd.date_range(end=datetime.now(), periods=periods, freq='H')
        
        # 模拟价格走势（基于随机游走）
        initial_price = 45000
        returns = np.random.normal(0.0001, 0.02, periods)  # 日收益率均值0.01%，波动2%
        prices = [initial_price]
        
        for r in returns[1:]:
            prices.append(prices[-1] * (1 + r))
        
        # 生成OHLCV数据
        data = []
        for i, price in enumerate(prices):
            high = price * (1 + abs(np.random.normal(0, 0.01)))
            low = price * (1 - abs(np.random.normal(0, 0.01)))
            open_price = prices[i-1] if i > 0 else price
            close_price = price
            volume = np.random.uniform(100, 1000)
            
            data.append({
                'open': open_price,
                'high': high,
                'low': low,
                'close': close_price,
                'volume': volume
            })
        
        df = pd.DataFrame(data, index=dates)
        return df
    
    def run_strategy_backtest(self, df, strategy, strategy_name):
        """运行单个策略回测"""
        print(f"\n🚀 正在回测 {strategy_name}...")
        
        # 重置状态
        balance = self.initial_balance
        position = 0
        entry_price = 0
        trades = []
        equity_curve = []
        
        # 初始化策略
        if hasattr(strategy, 'initialize'):
            strategy.initialize(df.iloc[0]['close'])
        
        # 回测循环
        for i in range(50, len(df)):  # 前50个数据用于技术指标计算
            current_data = df.iloc[:i+1]
            current_price = df.iloc[i]['close']
            timestamp = df.index[i]
            
            # 检查策略信号
            signal = None
            reason = ""
            
            if hasattr(strategy, 'generate_signals'):
                # 传统策略（如MA策略）
                signals_df = strategy.generate_signals(current_data)
                if signals_df is not None and len(signals_df) > 0:
                    latest = signals_df.iloc[-1]
                    if latest.get('buy_signal', False) and position == 0:
                        signal = 'buy'
                        reason = f"{strategy_name} 买入信号"
                    elif latest.get('sell_signal', False) and position > 0:
                        signal = 'sell'
                        reason = f"{strategy_name} 卖出信号"
            
            elif hasattr(strategy, 'check_signal'):
                # 高级策略（如均值回归）
                signal, reason = strategy.check_signal(current_data)
            
            elif hasattr(strategy, 'check_signals'):
                # 网格或配对交易策略
                signals = strategy.check_signals(current_price)
                if signals:
                    for s in signals:
                        if s['type'] == 'buy' and position == 0:
                            signal = 'buy'
                            reason = s['reason']
                            break
                        elif s['type'] == 'sell' and position > 0:
                            signal = 'sell'
                            reason = s['reason']
                            break
            
            # 执行交易
            if signal == 'buy' and position == 0 and balance > 0:
                # 买入
                amount = (balance * 0.95) / current_price
                cost = amount * current_price * (1 + self.commission)
                
                if cost <= balance:
                    balance -= cost
                    position = amount
                    entry_price = current_price
                    
                    trades.append({
                        'timestamp': timestamp,
                        'type': 'buy',
                        'price': current_price,
                        'amount': amount,
                        'balance': balance,
                        'reason': reason
                    })
                    
                    # 更新策略状态
                    if hasattr(strategy, 'update_position'):
                        strategy.update_position('buy', current_price)
            
            elif signal == 'sell' and position > 0:
                # 卖出
                revenue = position * current_price * (1 - self.commission)
                pnl = revenue - (position * entry_price * (1 + self.commission))
                
                balance += revenue
                
                trades.append({
                    'timestamp': timestamp,
                    'type': 'sell',
                    'price': current_price,
                    'amount': position,
                    'pnl': pnl,
                    'balance': balance,
                    'reason': reason
                })
                
                position = 0
                entry_price = 0
                
                # 更新策略状态
                if hasattr(strategy, 'update_position'):
                    strategy.update_position('sell', current_price)
            
            # 记录权益曲线
            equity = balance + (position * current_price)
            equity_curve.append({
                'timestamp': timestamp,
                'equity': equity,
                'balance': balance,
                'position_value': position * current_price,
                'price': current_price
            })
        
        # 计算回测结果
        return self.calculate_results(trades, equity_curve, strategy_name)
    
    def calculate_results(self, trades, equity_curve, strategy_name):
        """计算回测结果统计"""
        if not trades:
            return {
                'strategy': strategy_name,
                'total_trades': 0,
                'win_rate': 0,
                'total_pnl': 0,
                'final_balance': self.initial_balance,
                'return_pct': 0,
                'max_drawdown': 0,
                'sharpe_ratio': 0,
                'trades': trades,
                'equity_curve': equity_curve
            }
        
        # 计算基本统计
        sell_trades = [t for t in trades if t['type'] == 'sell']
        winning_trades = [t for t in sell_trades if t.get('pnl', 0) > 0]
        losing_trades = [t for t in sell_trades if t.get('pnl', 0) <= 0]
        
        win_rate = len(winning_trades) / len(sell_trades) if sell_trades else 0
        total_pnl = sum(t.get('pnl', 0) for t in sell_trades)
        avg_win = np.mean([t.get('pnl', 0) for t in winning_trades]) if winning_trades else 0
        avg_loss = np.mean([t.get('pnl', 0) for t in losing_trades]) if losing_trades else 0
        
        # 计算最大回撤和夏普比率
        equity_df = pd.DataFrame(equity_curve)
        if len(equity_df) > 0:
            equity_df['peak'] = equity_df['equity'].cummax()
            equity_df['drawdown'] = (equity_df['peak'] - equity_df['equity']) / equity_df['peak']
            max_drawdown = equity_df['drawdown'].max()
            
            # 计算日收益率
            equity_df['returns'] = equity_df['equity'].pct_change()
            daily_returns = equity_df['returns'].dropna()
            
            if len(daily_returns) > 0 and daily_returns.std() > 0:
                sharpe_ratio = daily_returns.mean() / daily_returns.std() * np.sqrt(365 * 24)  # 年化夏普
            else:
                sharpe_ratio = 0
        else:
            max_drawdown = 0
            sharpe_ratio = 0
        
        final_equity = equity_curve[-1]['equity'] if equity_curve else self.initial_balance
        return_pct = (final_equity - self.initial_balance) / self.initial_balance
        
        return {
            'strategy': strategy_name,
            'total_trades': len(trades),
            'buy_trades': len([t for t in trades if t['type'] == 'buy']),
            'sell_trades': len(sell_trades),
            'winning_trades': len(winning_trades),
            'losing_trades': len(losing_trades),
            'win_rate': win_rate,
            'total_pnl': total_pnl,
            'avg_pnl': total_pnl / len(sell_trades) if sell_trades else 0,
            'avg_win': avg_win,
            'avg_loss': avg_loss,
            'profit_factor': abs(avg_win * len(winning_trades) / (avg_loss * len(losing_trades))) if losing_trades and avg_loss != 0 else float('inf'),
            'initial_balance': self.initial_balance,
            'final_balance': final_equity,
            'return_pct': return_pct,
            'annual_return': return_pct * 2,  # 6个月数据，年化收益
            'max_drawdown': max_drawdown,
            'sharpe_ratio': sharpe_ratio,
            'trades': trades,
            'equity_curve': equity_curve
        }
    
    def compare_strategies(self, df, strategies):
        """对比多个策略的回测结果"""
        print("🔥 开始多策略回测分析...")
        results = []
        
        for strategy_name, strategy in strategies.items():
            result = self.run_strategy_backtest(df, strategy, strategy_name)
            results.append(result)
        
        # 打印对比报告
        self.print_comparison_report(results)
        return results
    
    def print_comparison_report(self, results):
        """打印策略对比报告"""
        print("\n" + "="*80)
        print("📊 策略对比分析报告 (过去6个月)")
        print("="*80)
        
        # 表头
        print(f"{'策略':<15} {'总收益':<10} {'年化收益':<10} {'胜率':<8} {'交易次数':<8} {'最大回撤':<10} {'夏普比率':<10}")
        print("-"*80)
        
        # 按收益率排序
        sorted_results = sorted(results, key=lambda x: x['return_pct'], reverse=True)
        
        for result in sorted_results:
            print(f"{result['strategy']:<15} "
                  f"{result['return_pct']:>8.2%} "
                  f"{result['annual_return']:>9.2%} "
                  f"{result['win_rate']:>6.2%} "
                  f"{result['sell_trades']:>7d} "
                  f"{result['max_drawdown']:>8.2%} "
                  f"{result['sharpe_ratio']:>9.2f}")
        
        print("="*80)
        
        # 详细分析
        best_return = max(sorted_results, key=lambda x: x['return_pct'])
        best_sharpe = max(sorted_results, key=lambda x: x['sharpe_ratio'])
        best_winrate = max(sorted_results, key=lambda x: x['win_rate'])
        lowest_dd = min(sorted_results, key=lambda x: x['max_drawdown'])
        
        print(f"🏆 最佳收益策略: {best_return['strategy']} ({best_return['return_pct']:.2%})")
        print(f"⚡ 最佳夏普比率: {best_sharpe['strategy']} ({best_sharpe['sharpe_ratio']:.2f})")
        print(f"🎯 最高胜率策略: {best_winrate['strategy']} ({best_winrate['win_rate']:.2%})")
        print(f"🛡️ 最低回撤策略: {lowest_dd['strategy']} ({lowest_dd['max_drawdown']:.2%})")
        print("="*80)


def main():
    """主函数 - 运行策略回测分析"""
    
    # 初始化回测器
    backtester = StrategyBacktester(initial_balance=10000, commission=0.001)
    
    # 获取6个月历史数据
    df = backtester.fetch_historical_data('BTC/USDT', '1h', 6)
    
    if df is None or len(df) < 100:
        print("❌ 数据不足，无法进行回测")
        return
    
    # 定义要测试的策略
    strategies = {
        'MA均线策略': MAStrategy(fast_period=5, slow_period=20),
        '网格交易': GridStrategy(grid_size=0.02, grid_count=8),
        '均值回归': MeanReversionStrategy(zscore_threshold=2.0),
        '动量突破': MomentumBreakoutStrategy(lookback=20, atr_multiplier=2.0)
    }
    
    # 运行策略对比
    results = backtester.compare_strategies(df, strategies)
    
    # 保存结果
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    for result in results:
        # 清理数据以便JSON序列化
        clean_result = {k: v for k, v in result.items() if k not in ['trades', 'equity_curve']}
        
        with open(f"backtest_results_{result['strategy'].replace(' ', '_')}_{timestamp}.json", 'w') as f:
            json.dump(clean_result, f, indent=2, default=str)
    
    print(f"\n💾 回测结果已保存到 backtest_results_*_{timestamp}.json")

if __name__ == "__main__":
    main()