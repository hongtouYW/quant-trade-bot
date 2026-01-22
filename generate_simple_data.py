# -*- coding: utf-8 -*-
"""
量化交易年度数据生成器
按用户要求: 2025.1.1-2026.1.1, 1000U本金, 20%收益, 日均10笔, 5%止损, 杠杆交易
"""

import json
import random
from datetime import datetime, timedelta

def generate_user_custom_data():
    """生成用户定制的年度交易数据"""
    
    # 用户要求配置
    INITIAL_CAPITAL = 1000
    TARGET_RETURN = 0.20
    DAILY_TRADES = 10
    STOP_LOSS = 0.05
    
    # 时间设置
    start_date = datetime(2025, 1, 1)
    end_date = datetime(2026, 1, 1)
    
    print("生成年度交易数据...")
    print(f"时间: {start_date.date()} - {end_date.date()}")
    print(f"本金: {INITIAL_CAPITAL} USDT")
    print(f"目标收益: {TARGET_RETURN*100}%")
    
    # 策略配置
    strategies = {
        'BTC突破策略': {
            'leverage': [3, 5],
            'profit': [0.08, 0.15],
            'currencies': ['BTC/USDT'],
            'freq': 2.0
        },
        'ETH量价策略': {
            'leverage': [2, 4],
            'profit': [0.06, 0.12],
            'currencies': ['ETH/USDT'],
            'freq': 2.5
        },
        '主流币策略': {
            'leverage': [2, 3],
            'profit': [0.05, 0.10],
            'currencies': ['SOL/USDT', 'ADA/USDT', 'DOT/USDT'],
            'freq': 2.0
        },
        '小币高频策略': {
            'leverage': [5, 10],
            'profit': [0.10, 0.25],
            'currencies': ['MATIC/USDT', 'LINK/USDT'],
            'freq': 2.5
        },
        '稳健策略': {
            'leverage': [1, 2],
            'profit': [0.03, 0.06],
            'currencies': ['BNB/USDT', 'XRP/USDT'],
            'freq': 1.0
        }
    }
    
    # 币种基础数据
    base_prices = {
        'BTC/USDT': 42000,
        'ETH/USDT': 2600,
        'SOL/USDT': 98,
        'ADA/USDT': 0.88,
        'DOT/USDT': 12.5,
        'MATIC/USDT': 1.15,
        'LINK/USDT': 14.2,
        'BNB/USDT': 315,
        'XRP/USDT': 0.65
    }
    
    all_trades = {}
    strategy_stats = []
    current_capital = INITIAL_CAPITAL
    
    for strategy_name, config in strategies.items():
        print(f"生成策略: {strategy_name}")
        
        trades = []
        total_pnl = 0
        win_count = 0
        total_count = 0
        
        # 遍历每一天
        current_date = start_date
        while current_date < end_date:
            daily_trades = max(0, int(random.normalvariate(config['freq'], 0.5)))
            
            for i in range(daily_trades):
                # 选择货币
                currency = random.choice(config['currencies'])
                base_price = base_prices[currency]
                
                # 计算价格 (年度趋势+波动)
                days_passed = (current_date - start_date).days
                trend = 1 + (days_passed / 365) * 0.5
                volatility = random.normalvariate(0, 0.2)
                current_price = base_price * trend * (1 + volatility)
                current_price = max(current_price, base_price * 0.3)
                
                # 选择杠杆
                leverage = random.randint(config['leverage'][0], config['leverage'][1])
                
                # 计算仓位
                position_ratio = random.uniform(0.01, 0.03)
                position_size = current_capital * position_ratio
                effective_position = position_size * leverage
                amount = effective_position / current_price
                
                # 止盈设置
                stop_profit = random.uniform(config['profit'][0], config['profit'][1])
                
                # 交易结果
                market_move = random.normalvariate(0, 0.15)
                
                if market_move <= -STOP_LOSS:
                    pnl_rate = -STOP_LOSS
                    exit_reason = "止损"
                elif market_move >= stop_profit:
                    pnl_rate = stop_profit
                    exit_reason = "止盈"
                    win_count += 1
                else:
                    if random.random() < 0.6:
                        pnl_rate = abs(market_move) * random.uniform(0.3, 0.7)
                        exit_reason = "获利"
                        win_count += 1
                    else:
                        pnl_rate = -abs(market_move) * random.uniform(0.3, 0.5)
                        exit_reason = "止损"
                
                # 计算盈亏
                trade_pnl = position_size * pnl_rate * leverage
                
                # 交易时间
                trade_time = current_date.replace(
                    hour=random.randint(0, 23),
                    minute=random.randint(0, 59)
                )
                
                # 交易记录
                trade_record = {
                    'timestamp': trade_time.isoformat(),
                    'trade_id': f"{strategy_name}_{current_date.strftime('%Y%m%d')}_{i+1}",
                    'strategy': strategy_name,
                    'symbol': currency,
                    'side': random.choice(['buy', 'sell']),
                    'entry_price': round(current_price, 6),
                    'exit_price': round(current_price * (1 + pnl_rate), 6),
                    'amount': round(amount, 6),
                    'leverage': f"{leverage}x" if leverage > 1 else "现货",
                    'position_size': round(position_size, 2),
                    'effective_position': round(effective_position, 2),
                    'pnl': round(trade_pnl, 2),
                    'pnl_rate': f"{pnl_rate*100:.2f}%",
                    'exit_reason': exit_reason,
                    'stop_loss': f"{STOP_LOSS*100}%",
                    'stop_profit': f"{stop_profit*100:.2f}%"
                }
                
                trades.append(trade_record)
                total_pnl += trade_pnl
                current_capital += trade_pnl
                total_count += 1
            
            current_date += timedelta(days=1)
        
        # 策略统计
        win_rate = (win_count / total_count * 100) if total_count > 0 else 0
        return_rate = (total_pnl / INITIAL_CAPITAL * 100)
        
        stats = {
            'strategy': strategy_name,
            'total_return': round(return_rate, 2),
            'win_rate': round(win_rate, 1),
            'total_trades': total_count,
            'winning_trades': win_count,
            'losing_trades': total_count - win_count,
            'total_pnl': round(total_pnl, 2),
            'avg_trade': round(total_pnl / total_count, 2) if total_count > 0 else 0,
            'max_drawdown': round(random.uniform(-8, -3), 2),
            'leverage_range': f"{config['leverage'][0]}-{config['leverage'][1]}x"
        }
        
        all_trades[strategy_name] = trades
        strategy_stats.append(stats)
        
        print(f"  完成: {total_count}笔, {win_rate:.1f}%胜率, {return_rate:.2f}%收益")
    
    return all_trades, strategy_stats, current_capital

def save_data(trades, stats, final_capital):
    """保存数据"""
    
    # 保存交易记录
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_trades.json', 'w', encoding='utf-8') as f:
        json.dump(trades, f, ensure_ascii=False, indent=2)
    
    # 保存策略分析
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_analysis.json', 'w', encoding='utf-8') as f:
        json.dump(stats, f, ensure_ascii=False, indent=2)
    
    # 状态信息
    total_trades = sum(len(t) for t in trades.values())
    total_pnl = final_capital - 1000
    
    status = {
        'total_balance': round(final_capital, 2),
        'total_pnl': round(total_pnl, 2),
        'return_rate': round((total_pnl / 1000) * 100, 2),
        'is_trading': True,
        'total_trades': total_trades,
        'active_strategies': len(trades),
        'last_update': datetime.now().isoformat()
    }
    
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_status.json', 'w', encoding='utf-8') as f:
        json.dump(status, f, ensure_ascii=False, indent=2)
    
    print(f"\n数据生成完成!")
    print(f"总交易: {total_trades}笔")
    print(f"最终资金: {final_capital:.2f} USDT")
    print(f"总收益: {total_pnl:.2f} USDT ({(total_pnl/1000)*100:.1f}%)")
    print(f"策略数: {len(trades)}")

if __name__ == "__main__":
    try:
        trades, stats, final_capital = generate_user_custom_data()
        save_data(trades, stats, final_capital)
        print("\n成功! 可以查看统一面板数据了!")
    except Exception as e:
        print(f"错误: {e}")