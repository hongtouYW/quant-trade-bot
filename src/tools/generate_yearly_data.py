#!/usr/bin/env python3
"""
生成一整年的量化交易数据
包含5个策略的完整交易记录和分析数据
"""

import json
import random
from datetime import datetime, timedelta
import math

def generate_yearly_trading_data():
    """生成一整年的交易数据 - 按照用户要求定制"""
    
    # 用户要求配置
    INITIAL_CAPITAL = 1000  # 本金1000U
    TARGET_ANNUAL_RETURN = 0.20  # 年收益20%
    DAILY_TRADES = 10  # 每天约10次交易
    STOP_LOSS = 0.05  # 止损5%
    
    # 策略配置 - 针对不同市场条件和风险等级
    strategies = {
        'BTC突破策略': {
            'risk_level': 'HIGH',
            'leverage_range': [3, 5],  # 杠杆倍数范围
            'trade_frequency': 2.5,
            'stop_profit_range': [0.08, 0.15],  # 止盈范围8%-15%
            'currencies': ['BTC/USDT'],
            'description': 'BTC价格突破关键阻力支撑位时进场'
        },
        'ETH量价策略': {
            'risk_level': 'MEDIUM',
            'leverage_range': [2, 4],
            'trade_frequency': 3.0,
            'stop_profit_range': [0.06, 0.12],
            'currencies': ['ETH/USDT'],
            'description': 'ETH成交量异常放大时的趋势跟踪'
        },
        '主流币轮动策略': {
            'risk_level': 'MEDIUM',
            'leverage_range': [2, 3],
            'trade_frequency': 2.0,
            'stop_profit_range': [0.05, 0.10],
            'currencies': ['SOL/USDT', 'ADA/USDT', 'DOT/USDT', 'AVAX/USDT'],
            'description': '基于相对强弱指标的主流币轮动'
        },
        '小币高频策略': {
            'risk_level': 'HIGH',
            'leverage_range': [5, 10],
            'trade_frequency': 1.5,
            'stop_profit_range': [0.10, 0.25],
            'currencies': ['MATIC/USDT', 'LINK/USDT', 'UNI/USDT'],
            'description': '小市值币种的高频套利机会'
        },
        '稳定套利策略': {
            'risk_level': 'LOW',
            'leverage_range': [1, 2],
            'trade_frequency': 1.0,
            'stop_profit_range': [0.03, 0.06],
            'currencies': ['BNB/USDT', 'XRP/USDT'],
            'description': '稳定币种的网格套利和均值回归'
        }
    }
    
    # 货币基础数据和风险评估
    currency_data = {
        'BTC/USDT': {
            'base_price': 42000,
            'volatility': 0.15,  # 波动率15%
            'market_cap_rank': 1,
            'risk_score': 3,  # 1-5风险评分
            'liquidity': 'VERY_HIGH'
        },
        'ETH/USDT': {
            'base_price': 2500,
            'volatility': 0.18,
            'market_cap_rank': 2,
            'risk_score': 3,
            'liquidity': 'VERY_HIGH'
        },
        'SOL/USDT': {
            'base_price': 95,
            'volatility': 0.25,
            'market_cap_rank': 5,
            'risk_score': 4,
            'liquidity': 'HIGH'
        },
        'ADA/USDT': {
            'base_price': 0.85,
            'volatility': 0.22,
            'market_cap_rank': 8,
            'risk_score': 4,
            'liquidity': 'HIGH'
        },
        'DOT/USDT': {
            'base_price': 12,
            'volatility': 0.28,
            'market_cap_rank': 12,
            'risk_score': 4,
            'liquidity': 'MEDIUM'
        },
        'AVAX/USDT': {
            'base_price': 32,
            'volatility': 0.30,
            'market_cap_rank': 15,
            'risk_score': 4,
            'liquidity': 'MEDIUM'
        },
        'MATIC/USDT': {
            'base_price': 1.1,
            'volatility': 0.35,
            'market_cap_rank': 20,
            'risk_score': 5,
            'liquidity': 'MEDIUM'
        },
        'LINK/USDT': {
            'base_price': 14,
            'volatility': 0.32,
            'market_cap_rank': 18,
            'risk_score': 4,
            'liquidity': 'MEDIUM'
        },
        'UNI/USDT': {
            'base_price': 8,
            'volatility': 0.38,
            'market_cap_rank': 25,
            'risk_score': 5,
            'liquidity': 'MEDIUM'
        },
        'BNB/USDT': {
            'base_price': 320,
            'volatility': 0.20,
            'market_cap_rank': 4,
            'risk_score': 3,
            'liquidity': 'HIGH'
        },
        'XRP/USDT': {
            'base_price': 0.62,
            'volatility': 0.25,
            'market_cap_rank': 6,
            'risk_score': 3,
            'liquidity': 'HIGH'
        }
    }
    
    # 生成一年的数据
    start_date = datetime(2025, 1, 21)  # 一年前
    end_date = datetime(2026, 1, 21)    # 今天
    
    all_trades = {}
    strategy_analysis = []
    
    print("🎯 开始生成一整年交易数据...")
    
    for strategy_name, config in strategies.items():
        print(f"📊 生成 {strategy_name} 数据...")
        
        strategy_trades = []
        total_trades = 0
        winning_trades = 0
        total_pnl = 0
        max_drawdown = 0
        current_drawdown = 0
        peak_value = 10000  # 起始资金
        
        # 遍历每一天
        current_date = start_date
        while current_date <= end_date:
            # 随机决定是否交易
            daily_trades = np.random.poisson(config['trade_frequency'])
            
            for _ in range(daily_trades):
                # 随机选择货币对
                currency = random.choice(config['currencies'])
                base_price = base_prices[currency]
                
                # 价格波动 (±10%)
                price_variation = random.uniform(-0.1, 0.1)
                current_price = base_price * (1 + price_variation)
                
                # 随机交易量
                if 'BTC' in currency:
                    amount = random.uniform(0.01, 0.1)
                elif 'ETH' in currency:
                    amount = random.uniform(0.1, 2.0)
                else:
                    amount = random.uniform(10, 500)
                
                # 决定盈亏
                is_winning = random.random() < config['base_win_rate']
                
                if is_winning:
                    pnl_percent = abs(random.normalvariate(config['avg_profit'], 0.5))
                    winning_trades += 1
                else:
                    pnl_percent = -abs(random.normalvariate(-config['avg_loss'], 0.3))
                
                trade_value = current_price * amount
                pnl = trade_value * (pnl_percent / 100)
                
                # 随机交易时间
                trade_time = current_date + timedelta(
                    hours=random.randint(0, 23),
                    minutes=random.randint(0, 59),
                    seconds=random.randint(0, 59)
                )
                
                trade_record = {
                    'timestamp': trade_time.isoformat(),
                    'symbol': currency,
                    'side': 'buy' if is_winning else random.choice(['buy', 'sell']),
                    'price': round(current_price, 2),
                    'amount': round(amount, 4),
                    'pnl': round(pnl, 2),
                    'strategy': strategy_name,
                    'trade_id': f"{strategy_name}_{total_trades}_{current_date.strftime('%Y%m%d')}"
                }
                
                strategy_trades.append(trade_record)
                total_trades += 1
                total_pnl += pnl
                
                # 计算回撤
                current_drawdown += pnl
                if current_drawdown > 0:
                    peak_value += current_drawdown
                    current_drawdown = 0
                else:
                    drawdown_percent = abs(current_drawdown / peak_value * 100)
                    max_drawdown = max(max_drawdown, drawdown_percent)
            
            current_date += timedelta(days=1)
        
        # 保存策略交易记录
        all_trades[strategy_name] = strategy_trades
        
        # 计算策略分析
        win_rate = (winning_trades / total_trades * 100) if total_trades > 0 else 0
        total_return = (total_pnl / 10000 * 100)  # 假设起始资金10000
        
        strategy_stats = {
            'strategy': strategy_name,
            'total_return': round(total_return, 2),
            'win_rate': round(win_rate, 1),
            'total_trades': total_trades,
            'winning_trades': winning_trades,
            'losing_trades': total_trades - winning_trades,
            'total_pnl': round(total_pnl, 2),
            'max_drawdown': round(-max_drawdown, 2),
            'avg_trade': round(total_pnl / total_trades, 2) if total_trades > 0 else 0
        }
        
        strategy_analysis.append(strategy_stats)
        print(f"✅ {strategy_name}: {total_trades}笔交易, {win_rate:.1f}%胜率, {total_return:.2f}%收益")
    
    return all_trades, strategy_analysis

def save_trading_data(trades, analysis):
    """保存交易数据到文件"""
    
    # 保存交易记录
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_trades.json', 'w', encoding='utf-8') as f:
        json.dump(trades, f, ensure_ascii=False, indent=2)
    
    # 保存策略分析
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_analysis.json', 'w', encoding='utf-8') as f:
        json.dump(analysis, f, ensure_ascii=False, indent=2)
    
    # 生成总结报告
    total_trades_count = sum(len(strategy_trades) for strategy_trades in trades.values())
    total_pnl = sum(strategy['total_pnl'] for strategy in analysis)
    
    # 保存状态信息
    status = {
        'total_balance': 10000 + total_pnl,
        'total_pnl': total_pnl,
        'is_trading': True,
        'today_trades': random.randint(5, 15),
        'active_strategies': len(trades),
        'yearly_trades': total_trades_count,
        'last_update': datetime.now().isoformat()
    }
    
    with open('/Users/hongtou/newproject/quant-trade-bot/latest_status.json', 'w', encoding='utf-8') as f:
        json.dump(status, f, ensure_ascii=False, indent=2)
    
    print(f"\n📈 一整年交易数据生成完成!")
    print(f"📊 总交易笔数: {total_trades_count:,}")
    print(f"💰 总盈亏: {total_pnl:,.2f} USDT")
    print(f"📋 策略数量: {len(trades)}")
    print(f"💾 数据已保存到: latest_trades.json, latest_analysis.json")

if __name__ == "__main__":
    # 生成数据
    trades, analysis = generate_yearly_trading_data()
    
    # 保存数据
    save_trading_data(trades, analysis)
    
    print("\n🎉 年度交易数据生成完成！现在可以查看统一面板的完整数据了！")