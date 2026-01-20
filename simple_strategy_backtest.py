#!/usr/bin/env python3
"""
简化版策略回测分析
避免numpy路径冲突问题
"""

import json
import sys
from datetime import datetime, timedelta
import math
import random

# 模拟pandas功能
class SimpleDataFrame:
    def __init__(self, data):
        self.data = data
        self.index = list(range(len(data)))
        
    def __len__(self):
        return len(self.data)
        
    def iloc(self, idx):
        if isinstance(idx, slice):
            return SimpleDataFrame(self.data[idx])
        return self.data[idx]
    
    def __getitem__(self, key):
        if isinstance(key, str):
            return [row[key] for row in self.data]
        return self.data[key]

def generate_btc_data(months=6):
    """生成BTC模拟数据"""
    print(f"🔄 生成过去{months}个月的BTC模拟数据...")
    
    # 6个月的小时数据
    hours = months * 30 * 24
    data = []
    
    # 起始价格
    price = 45000.0
    
    for hour in range(hours):
        # 模拟价格变动 (-3% 到 +3%)
        change = (random.random() - 0.5) * 0.06
        price = price * (1 + change)
        
        # 确保价格在合理范围
        price = max(20000, min(100000, price))
        
        # 生成OHLCV
        high = price * (1 + abs(random.random() * 0.02))
        low = price * (1 - abs(random.random() * 0.02))
        open_price = data[-1]['close'] if data else price
        volume = random.uniform(100, 1000)
        
        timestamp = datetime.now() - timedelta(hours=hours-hour)
        
        data.append({
            'timestamp': timestamp,
            'open': open_price,
            'high': high,
            'low': low,
            'close': price,
            'volume': volume
        })
    
    print(f"✅ 生成了{len(data)}条数据")
    return data

def calculate_ma(prices, period):
    """计算移动平均"""
    if len(prices) < period:
        return [None] * len(prices)
    
    mas = [None] * (period - 1)
    for i in range(period - 1, len(prices)):
        ma = sum(prices[i-period+1:i+1]) / period
        mas.append(ma)
    
    return mas

def calculate_rsi(prices, period=14):
    """计算RSI"""
    if len(prices) < period + 1:
        return [None] * len(prices)
    
    gains = []
    losses = []
    
    for i in range(1, len(prices)):
        change = prices[i] - prices[i-1]
        gains.append(max(0, change))
        losses.append(max(0, -change))
    
    rsi_values = [None] * period
    
    if len(gains) >= period:
        # 初始RS
        avg_gain = sum(gains[:period]) / period
        avg_loss = sum(losses[:period]) / period
        
        if avg_loss == 0:
            rs = 100
        else:
            rs = avg_gain / avg_loss
        
        rsi = 100 - (100 / (1 + rs))
        rsi_values.append(rsi)
        
        # 后续RSI
        for i in range(period, len(gains)):
            avg_gain = (avg_gain * (period - 1) + gains[i]) / period
            avg_loss = (avg_loss * (period - 1) + losses[i]) / period
            
            if avg_loss == 0:
                rs = 100
            else:
                rs = avg_gain / avg_loss
            
            rsi = 100 - (100 / (1 + rs))
            rsi_values.append(rsi)
    
    return rsi_values

class SimpleBacktester:
    """简化版回测引擎"""
    
    def __init__(self, initial_balance=10000, commission=0.001):
        self.initial_balance = initial_balance
        self.commission = commission
    
    def backtest_ma_strategy(self, data, fast_period=5, slow_period=20):
        """回测MA策略"""
        print(f"🚀 回测MA策略 (MA{fast_period}/{slow_period})...")
        
        prices = [d['close'] for d in data]
        ma_fast = calculate_ma(prices, fast_period)
        ma_slow = calculate_ma(prices, slow_period)
        
        balance = self.initial_balance
        position = 0
        entry_price = 0
        trades = []
        
        for i in range(slow_period, len(data)):
            price = data[i]['close']
            
            if ma_fast[i] and ma_slow[i]:
                # 金叉 - 买入信号
                if ma_fast[i] > ma_slow[i] and ma_fast[i-1] <= ma_slow[i-1] and position == 0:
                    amount = (balance * 0.95) / price
                    cost = amount * price * (1 + self.commission)
                    
                    if cost <= balance:
                        balance -= cost
                        position = amount
                        entry_price = price
                        
                        trades.append({
                            'type': 'buy',
                            'price': price,
                            'amount': amount,
                            'timestamp': data[i]['timestamp']
                        })
                
                # 死叉 - 卖出信号
                elif ma_fast[i] < ma_slow[i] and ma_fast[i-1] >= ma_slow[i-1] and position > 0:
                    revenue = position * price * (1 - self.commission)
                    pnl = revenue - (position * entry_price * (1 + self.commission))
                    
                    balance += revenue
                    
                    trades.append({
                        'type': 'sell',
                        'price': price,
                        'amount': position,
                        'pnl': pnl,
                        'timestamp': data[i]['timestamp']
                    })
                    
                    position = 0
                    entry_price = 0
        
        final_equity = balance + (position * prices[-1])
        return self.analyze_results(trades, final_equity, f"MA{fast_period}/{slow_period}")
    
    def backtest_rsi_strategy(self, data, rsi_period=14, oversold=30, overbought=70):
        """回测RSI策略"""
        print(f"🚀 回测RSI策略 (RSI{rsi_period}, 超卖{oversold}/超买{overbought})...")
        
        prices = [d['close'] for d in data]
        rsi_values = calculate_rsi(prices, rsi_period)
        
        balance = self.initial_balance
        position = 0
        entry_price = 0
        trades = []
        
        for i in range(rsi_period + 1, len(data)):
            price = data[i]['close']
            
            if rsi_values[i]:
                # RSI超卖 - 买入信号
                if rsi_values[i] < oversold and position == 0:
                    amount = (balance * 0.95) / price
                    cost = amount * price * (1 + self.commission)
                    
                    if cost <= balance:
                        balance -= cost
                        position = amount
                        entry_price = price
                        
                        trades.append({
                            'type': 'buy',
                            'price': price,
                            'amount': amount,
                            'timestamp': data[i]['timestamp']
                        })
                
                # RSI超买 - 卖出信号
                elif rsi_values[i] > overbought and position > 0:
                    revenue = position * price * (1 - self.commission)
                    pnl = revenue - (position * entry_price * (1 + self.commission))
                    
                    balance += revenue
                    
                    trades.append({
                        'type': 'sell',
                        'price': price,
                        'amount': position,
                        'pnl': pnl,
                        'timestamp': data[i]['timestamp']
                    })
                    
                    position = 0
                    entry_price = 0
        
        final_equity = balance + (position * prices[-1])
        return self.analyze_results(trades, final_equity, f"RSI{rsi_period}")
    
    def backtest_grid_strategy(self, data, grid_size=0.02, grid_count=5):
        """回测网格策略"""
        print(f"🚀 回测网格策略 (网格间距{grid_size*100:.1f}%, {grid_count}格)...")
        
        base_price = data[len(data)//4]['close']  # 使用1/4处的价格作为基准
        balance = self.initial_balance
        trades = []
        
        # 创建网格
        buy_levels = []
        sell_levels = []
        
        for i in range(1, grid_count + 1):
            buy_price = base_price * (1 - grid_size * i)
            sell_price = base_price * (1 + grid_size * i)
            buy_levels.append({'price': buy_price, 'filled': False})
            sell_levels.append({'price': sell_price, 'filled': False})
        
        grid_positions = 0
        order_size = self.initial_balance / (grid_count * 2)  # 每格的订单金额
        
        for data_point in data:
            price = data_point['close']
            
            # 检查买入网格
            for buy_level in buy_levels:
                if not buy_level['filled'] and price <= buy_level['price']:
                    amount = order_size / price
                    cost = amount * price * (1 + self.commission)
                    
                    if balance >= cost:
                        balance -= cost
                        grid_positions += amount
                        buy_level['filled'] = True
                        
                        trades.append({
                            'type': 'buy',
                            'price': price,
                            'amount': amount,
                            'timestamp': data_point['timestamp']
                        })
            
            # 检查卖出网格
            for sell_level in sell_levels:
                if not sell_level['filled'] and price >= sell_level['price'] and grid_positions > 0:
                    amount = min(order_size / price, grid_positions)
                    revenue = amount * price * (1 - self.commission)
                    pnl = revenue - amount * base_price  # 简化PnL计算
                    
                    balance += revenue
                    grid_positions -= amount
                    sell_level['filled'] = True
                    
                    trades.append({
                        'type': 'sell',
                        'price': price,
                        'amount': amount,
                        'pnl': pnl,
                        'timestamp': data_point['timestamp']
                    })
        
        final_equity = balance + (grid_positions * data[-1]['close'])
        return self.analyze_results(trades, final_equity, "网格交易")
    
    def analyze_results(self, trades, final_equity, strategy_name):
        """分析回测结果"""
        if not trades:
            return {
                'strategy': strategy_name,
                'total_trades': 0,
                'win_rate': 0,
                'total_pnl': 0,
                'return_pct': 0,
                'final_equity': final_equity
            }
        
        sell_trades = [t for t in trades if t['type'] == 'sell']
        winning_trades = [t for t in sell_trades if t.get('pnl', 0) > 0]
        
        total_pnl = sum(t.get('pnl', 0) for t in sell_trades)
        win_rate = len(winning_trades) / len(sell_trades) if sell_trades else 0
        return_pct = (final_equity - self.initial_balance) / self.initial_balance
        
        return {
            'strategy': strategy_name,
            'total_trades': len(trades),
            'sell_trades': len(sell_trades),
            'winning_trades': len(winning_trades),
            'win_rate': win_rate,
            'total_pnl': total_pnl,
            'return_pct': return_pct,
            'annual_return': return_pct * 2,  # 6个月数据年化
            'final_equity': final_equity,
            'avg_pnl': total_pnl / len(sell_trades) if sell_trades else 0,
            'trades': trades  # 保存完整交易历史
        }

def main():
    """主函数"""
    print("🎯 开始6个月策略回测分析...")
    
    # 生成数据
    data = generate_btc_data(6)
    
    # 初始化回测器
    backtester = SimpleBacktester(initial_balance=10000, commission=0.001)
    
    # 回测多个策略
    strategies_results = []
    
    # 1. MA策略组合
    ma_configs = [
        (5, 20),   # 快速
        (10, 30),  # 中等
        (20, 50)   # 慢速
    ]
    
    for fast, slow in ma_configs:
        result = backtester.backtest_ma_strategy(data, fast, slow)
        strategies_results.append(result)
    
    # 2. RSI策略组合
    rsi_configs = [
        (14, 30, 70),  # 标准
        (14, 25, 75),  # 严格
        (21, 30, 70)   # 长周期
    ]
    
    for period, oversold, overbought in rsi_configs:
        result = backtester.backtest_rsi_strategy(data, period, oversold, overbought)
        strategies_results.append(result)
    
    # 3. 网格策略组合
    grid_configs = [
        (0.01, 10),  # 密集小网格
        (0.02, 8),   # 中等网格
        (0.03, 5)    # 宽松大网格
    ]
    
    for grid_size, grid_count in grid_configs:
        result = backtester.backtest_grid_strategy(data, grid_size, grid_count)
        strategies_results.append(result)
    
    # 打印报告
    print_comparison_report(strategies_results)
    
    # 保存结果
    save_results(strategies_results)

def print_comparison_report(results):
    """打印策略对比报告"""
    print("\n" + "="*80)
    print("📊 策略回测对比报告 (BTC/USDT - 过去6个月)")
    print("="*80)
    
    # 表头
    print(f"{'策略':<20} {'总收益':<10} {'年化收益':<10} {'胜率':<8} {'交易次数':<8} {'平均收益':<10}")
    print("-"*80)
    
    # 按收益率排序
    sorted_results = sorted(results, key=lambda x: x['return_pct'], reverse=True)
    
    for result in sorted_results:
        print(f"{result['strategy']:<20} "
              f"{result['return_pct']:>8.2%} "
              f"{result['annual_return']:>9.2%} "
              f"{result['win_rate']:>6.2%} "
              f"{result['sell_trades']:>7d} "
              f"{result['avg_pnl']:>8.2f}")
    
    print("="*80)
    
    # 策略分析
    if sorted_results:
        best = sorted_results[0]
        print(f"🏆 最佳策略: {best['strategy']}")
        print(f"   6个月收益: {best['return_pct']:.2%}")
        print(f"   年化收益: {best['annual_return']:.2%}")
        print(f"   胜率: {best['win_rate']:.2%}")
        print(f"   交易次数: {best['sell_trades']}")
        
        # 策略类别分析
        ma_results = [r for r in results if 'MA' in r['strategy']]
        rsi_results = [r for r in results if 'RSI' in r['strategy']]
        grid_results = [r for r in results if '网格' in r['strategy']]
        
        if ma_results:
            best_ma = max(ma_results, key=lambda x: x['return_pct'])
            print(f"📈 最佳MA策略: {best_ma['strategy']} ({best_ma['return_pct']:.2%})")
        
        if rsi_results:
            best_rsi = max(rsi_results, key=lambda x: x['return_pct'])
            print(f"📊 最佳RSI策略: {best_rsi['strategy']} ({best_rsi['return_pct']:.2%})")
        
        if grid_results:
            best_grid = max(grid_results, key=lambda x: x['return_pct'])
            print(f"🔲 最佳网格策略: {best_grid['strategy']} ({best_grid['return_pct']:.2%})")
    
    print("="*80)

def save_results(results):
    """保存回测结果和交易历史"""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    # 保存汇总结果
    summary_filename = f"strategy_analysis_{timestamp}.json"
    clean_results = []
    for result in results:
        clean_result = {k: v for k, v in result.items() if k != 'trades'}
        clean_results.append(clean_result)
    
    with open(summary_filename, 'w', encoding='utf-8') as f:
        json.dump(clean_results, f, indent=2, ensure_ascii=False, default=str)
    
    # 保存详细交易历史
    trades_filename = f"trading_history_{timestamp}.json"
    all_trades = {}
    
    for result in results:
        strategy_name = result['strategy']
        if 'trades' in result:
            all_trades[strategy_name] = [
                {
                    'timestamp': trade.get('timestamp', '').isoformat() if hasattr(trade.get('timestamp', ''), 'isoformat') else str(trade.get('timestamp', '')),
                    'type': trade.get('type', ''),
                    'price': round(trade.get('price', 0), 2),
                    'amount': round(trade.get('amount', 0), 6),
                    'pnl': round(trade.get('pnl', 0), 2) if trade.get('pnl') else None,
                    'value': round(trade.get('price', 0) * trade.get('amount', 0), 2)
                }
                for trade in result.get('trades', [])
            ]
    
    with open(trades_filename, 'w', encoding='utf-8') as f:
        json.dump(all_trades, f, indent=2, ensure_ascii=False, default=str)
    
    print(f"💾 回测结果已保存到: {summary_filename}")
    print(f"📊 交易历史已保存到: {trades_filename}")
    
    # 创建最新数据的符号链接
    try:
        import os
        if os.path.exists('latest_trades.json'):
            os.remove('latest_trades.json')
        if os.path.exists('latest_analysis.json'):
            os.remove('latest_analysis.json')
        
        os.symlink(trades_filename, 'latest_trades.json')
        os.symlink(summary_filename, 'latest_analysis.json')
        print("🔗 已创建最新数据链接")
    except:
        # 如果符号链接失败，直接复制文件
        import shutil
        shutil.copy2(trades_filename, 'latest_trades.json')
        shutil.copy2(summary_filename, 'latest_analysis.json')

if __name__ == "__main__":
    main()