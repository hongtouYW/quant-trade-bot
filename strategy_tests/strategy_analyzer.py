# -*- coding: utf-8 -*-
"""
当前交易策略分析报告
多空双向交易能力详解
"""

import json
from datetime import datetime

class TradingStrategyAnalyzer:
    """交易策略分析器"""
    
    def __init__(self):
        self.strategy_types = {
            'long_only': '仅做多策略',
            'short_only': '仅做空策略', 
            'long_short': '多空双向策略',
            'market_neutral': '市场中性策略'
        }
    
    def analyze_current_strategies(self):
        """分析当前策略配置"""
        
        analysis = {
            'strategy_overview': {
                '策略类型': '多空双向交易',
                '支持方向': ['做多(BUY)', '做空(SELL)'],
                '交易模式': '趋势跟随 + 均值回归',
                '风险控制': '动态止损 + 仓位管理'
            },
            
            'signal_types': {
                '买入信号': [
                    'buy - 趋势上涨时买入',
                    'buy_dip - 趋势中回调买入',
                    'buy_oversold - RSI超卖区买入'
                ],
                '卖出信号': [
                    'sell - 趋势下跌时卖出',
                    'sell_rally - 趋势中反弹卖出', 
                    'sell_overbought - RSI超买区卖出'
                ],
                '观望信号': [
                    'hold - 无明确方向时观望'
                ]
            },
            
            'strategy_details': {
                'MA策略': {
                    '多头条件': 'MA5上穿MA20 (金叉)',
                    '空头条件': 'MA5下穿MA20 (死叉)',
                    '双向交易': True
                },
                'RSI策略': {
                    '多头条件': 'RSI < 30 (超卖反弹)', 
                    '空头条件': 'RSI > 70 (超买回调)',
                    '双向交易': True
                },
                'MACD策略': {
                    '多头条件': 'MACD上穿信号线',
                    '空头条件': 'MACD下穿信号线',
                    '双向交易': True
                },
                '多时间框架策略': {
                    '日线趋势': '判断主要方向(多头/空头/震荡)',
                    '15分钟入场': '精确入场点选择',
                    '双向交易': True
                }
            },
            
            'position_management': {
                '多头仓位': {
                    '开仓条件': '趋势向上 + 技术确认',
                    '加仓策略': '回调时分批买入',
                    '止盈方式': '动态跟踪止盈',
                    '止损设置': '2-5% ATR止损'
                },
                '空头仓位': {
                    '开仓条件': '趋势向下 + 技术确认', 
                    '加仓策略': '反弹时分批卖出',
                    '止盈方式': '动态跟踪止盈',
                    '止损设置': '2-5% ATR止损'
                }
            },
            
            'risk_control': {
                '单向风险': '每笔交易最大2%资金风险',
                '组合风险': '多空仓位可同时持有',
                '杠杆控制': '1-3倍动态杠杆',
                '仓位限制': '单笔最大30%资金'
            }
        }
        
        return analysis
    
    def generate_trading_examples(self):
        """生成交易示例"""
        
        examples = {
            '多头交易示例': {
                '场景': 'BTC从94000突破95000',
                '信号': 'buy (MA金叉 + 放量突破)',
                '入场': '95000 USDT',
                '止损': '93100 USDT (-2%)',
                '目标': '98500 USDT (+3.7%)',
                '仓位': '正数(做多)',
                '盈亏计算': '(卖出价 - 买入价) × 仓位大小'
            },
            
            '空头交易示例': {
                '场景': 'ETH从3500跌破3400',
                '信号': 'sell (MA死叉 + 放量下跌)',
                '入场': '3400 USDT',
                '止损': '3468 USDT (+2%)', 
                '目标': '3230 USDT (-5%)',
                '仓位': '负数(做空)',
                '盈亏计算': '(买入价 - 卖出价) × |仓位大小|'
            },
            
            '震荡策略示例': {
                '场景': 'SOL在180-200区间震荡',
                '多头信号': '接近180支撑买入',
                '空头信号': '接近200阻力卖出',
                '网格交易': '高抛低吸获取价差',
                '双向收益': '多空都能盈利'
            }
        }
        
        return examples
    
    def get_strategy_recommendations(self):
        """策略建议"""
        
        recommendations = {
            '市场环境适应': {
                '牛市策略': '以做多为主，空头为辅助',
                '熊市策略': '以做空为主，多头抄底',
                '震荡市策略': '高频多空切换，区间操作',
                '趋势市策略': '顺势而为，减少逆向操作'
            },
            
            '仓位配置建议': {
                '保守型': '多头60% + 空头40%',
                '平衡型': '多头50% + 空头50%', 
                '激进型': '根据趋势90%集中一个方向',
                '对冲型': '多空同时持仓降低风险'
            },
            
            '优化建议': {
                '技术改进': '增加更多技术指标确认',
                '风控升级': '动态调整止损位',
                '时机把握': '结合市场情绪指标',
                '资金管理': '分散投资多个品种'
            }
        }
        
        return recommendations

def main():
    """主程序"""
    print("📊 交易策略分析报告")
    print("=" * 60)
    
    analyzer = TradingStrategyAnalyzer()
    
    # 分析当前策略
    strategy_analysis = analyzer.analyze_current_strategies()
    
    print("\n🎯 当前策略概况:")
    print("-" * 30)
    overview = strategy_analysis['strategy_overview']
    for key, value in overview.items():
        if isinstance(value, list):
            print(f"{key}: {' | '.join(value)}")
        else:
            print(f"{key}: {value}")
    
    print(f"\n✅ 答案: 当前策略支持 **多空双向交易**")
    print("   - 可以做多(BUY)：看涨时买入")
    print("   - 可以做空(SELL)：看跌时卖出")
    print("   - 智能切换：根据市场趋势自动选择方向")
    
    print("\n📈 多头信号类型:")
    for signal in strategy_analysis['signal_types']['买入信号']:
        print(f"   • {signal}")
    
    print("\n📉 空头信号类型:")
    for signal in strategy_analysis['signal_types']['卖出信号']:
        print(f"   • {signal}")
    
    print("\n🔧 策略详细配置:")
    print("-" * 30)
    for strategy_name, config in strategy_analysis['strategy_details'].items():
        print(f"\n{strategy_name}:")
        for key, value in config.items():
            print(f"   {key}: {value}")
    
    # 交易示例
    examples = analyzer.generate_trading_examples()
    
    print(f"\n💡 交易示例:")
    print("-" * 30)
    
    # 多头示例
    long_example = examples['多头交易示例']
    print(f"\n🟢 多头交易:")
    print(f"   场景: {long_example['场景']}")
    print(f"   信号: {long_example['信号']}")
    print(f"   入场: {long_example['入场']}")
    print(f"   止损: {long_example['止损']}")
    print(f"   目标: {long_example['目标']}")
    
    # 空头示例
    short_example = examples['空头交易示例']
    print(f"\n🔴 空头交易:")
    print(f"   场景: {short_example['场景']}")
    print(f"   信号: {short_example['信号']}")
    print(f"   入场: {short_example['入场']}")
    print(f"   止损: {short_example['止损']}")
    print(f"   目标: {short_example['目标']}")
    
    # 策略建议
    recommendations = analyzer.get_strategy_recommendations()
    
    print(f"\n🎯 策略运用建议:")
    print("-" * 30)
    market_strategies = recommendations['市场环境适应']
    for market, strategy in market_strategies.items():
        print(f"   {market}: {strategy}")
    
    # 保存分析结果
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    
    full_report = {
        'analysis_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        'strategy_type': '多空双向交易',
        'detailed_analysis': strategy_analysis,
        'trading_examples': examples,
        'recommendations': recommendations
    }
    
    filename = f'strategy_analysis_report_{timestamp}.json'
    
    with open(filename, 'w', encoding='utf-8') as f:
        json.dump(full_report, f, ensure_ascii=False, indent=2, default=str)
    
    print(f"\n📁 详细分析报告已保存: {filename}")
    
    print(f"\n📋 总结:")
    print("   ✅ 支持多空双向交易")
    print("   ✅ 智能信号识别") 
    print("   ✅ 动态风险控制")
    print("   ✅ 适应不同市场环境")

if __name__ == "__main__":
    main()