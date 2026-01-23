# -*- coding: utf-8 -*-
"""
潜力币种筛选器 - 大资金流向监控系统
识别主力资金部署和拉盘准备信号
"""

import json
import random
import time
from datetime import datetime, timedelta
from dataclasses import dataclass
from typing import List, Dict, Optional

@dataclass
class CoinAnalysis:
    """币种分析数据结构"""
    symbol: str
    current_price: float
    volume_24h: float
    volume_7d_avg: float
    volume_ratio: float  # 24h成交量/7日均值
    price_change_24h: float
    large_orders: List[Dict]  # 大单记录
    fund_flow: Dict  # 资金流向
    technical_signals: Dict  # 技术信号
    whale_activity: Dict  # 巨鲸活动
    potential_score: float  # 潜力评分 0-100

class PotentialCoinScanner:
    """潜力币种扫描器"""
    
    def __init__(self):
        self.symbols = [
            'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT',
            'DOT/USDT', 'LINK/USDT', 'MATIC/USDT', 'AVAX/USDT', 'ATOM/USDT',
            'FTM/USDT', 'NEAR/USDT', 'ALGO/USDT', 'VET/USDT', 'MANA/USDT',
            'SAND/USDT', 'AXS/USDT', 'ENJ/USDT', 'CHZ/USDT', 'LRC/USDT'
        ]
        
        # 筛选阈值
        self.volume_threshold = 3.0      # 成交量放大3倍以上
        self.whale_threshold = 100000    # 大单阈值10万USDT
        self.potential_threshold = 70    # 潜力评分阈值
        
        print("🔍 潜力币种扫描器初始化")
        print(f"📊 监控币种: {len(self.symbols)} 个")
        print(f"⚡ 成交量阈值: {self.volume_threshold}x")
        print(f"🐋 大单阈值: ${self.whale_threshold:,}")

    def get_simulated_market_data(self, symbol: str) -> Dict:
        """模拟获取市场数据"""
        base_prices = {
            'BTC/USDT': 95000, 'ETH/USDT': 3400, 'BNB/USDT': 600,
            'SOL/USDT': 180, 'ADA/USDT': 0.5, 'DOT/USDT': 8,
            'LINK/USDT': 20, 'MATIC/USDT': 1.2, 'AVAX/USDT': 40,
            'ATOM/USDT': 15, 'FTM/USDT': 0.8, 'NEAR/USDT': 6,
            'ALGO/USDT': 0.3, 'VET/USDT': 0.05, 'MANA/USDT': 0.6,
            'SAND/USDT': 0.4, 'AXS/USDT': 12, 'ENJ/USDT': 0.3,
            'CHZ/USDT': 0.08, 'LRC/USDT': 0.2
        }
        
        base_price = base_prices.get(symbol, 1.0)
        
        # 模拟价格波动
        price_change = random.uniform(-0.1, 0.15)  # -10% to +15%
        current_price = base_price * (1 + price_change)
        
        # 模拟成交量数据
        base_volume = random.uniform(10000000, 50000000)  # 1000万-5000万
        volume_multiplier = random.uniform(0.5, 8.0)     # 随机成交量倍数
        current_volume = base_volume * volume_multiplier
        
        return {
            'symbol': symbol,
            'current_price': current_price,
            'price_change_24h': price_change * 100,
            'volume_24h': current_volume,
            'volume_7d_avg': base_volume,
            'timestamp': datetime.now()
        }

    def analyze_volume_surge(self, volume_24h: float, volume_7d_avg: float) -> Dict:
        """分析成交量异动"""
        volume_ratio = volume_24h / volume_7d_avg if volume_7d_avg > 0 else 1
        
        if volume_ratio >= 5:
            level = "极度异常"
            score = 40
        elif volume_ratio >= 3:
            level = "高度异常"
            score = 30
        elif volume_ratio >= 2:
            level = "明显放量"
            score = 20
        elif volume_ratio >= 1.5:
            level = "温和放量"
            score = 10
        else:
            level = "正常"
            score = 0
            
        return {
            'ratio': volume_ratio,
            'level': level,
            'score': score,
            'description': f"24H成交量是7日均值的 {volume_ratio:.1f} 倍"
        }

    def simulate_large_orders(self, symbol: str, volume_24h: float) -> List[Dict]:
        """模拟大单监控"""
        large_orders = []
        
        # 根据成交量随机生成大单
        num_large_orders = max(1, int(volume_24h / 20000000))  # 每2000万成交量1个大单
        
        for _ in range(min(num_large_orders, 10)):  # 最多10个大单
            order_size = random.uniform(self.whale_threshold, volume_24h * 0.1)
            order_type = random.choice(['buy', 'sell'])
            
            large_orders.append({
                'size': order_size,
                'type': order_type,
                'timestamp': datetime.now() - timedelta(hours=random.randint(1, 24)),
                'price_impact': random.uniform(0.1, 2.0)  # 价格影响 0.1-2%
            })
        
        return sorted(large_orders, key=lambda x: x['size'], reverse=True)

    def analyze_fund_flow(self, price_change: float, volume_ratio: float, large_orders: List[Dict]) -> Dict:
        """分析资金流向"""
        # 买单和卖单统计
        buy_orders = [o for o in large_orders if o['type'] == 'buy']
        sell_orders = [o for o in large_orders if o['type'] == 'sell']
        
        buy_volume = sum(o['size'] for o in buy_orders)
        sell_volume = sum(o['size'] for o in sell_orders)
        
        net_flow = buy_volume - sell_volume
        
        # 资金流向判断
        if net_flow > 0 and price_change > 0:
            flow_direction = "强势流入"
            confidence = 0.8
        elif net_flow > 0 and price_change < 0:
            flow_direction = "抄底资金"
            confidence = 0.7
        elif net_flow < 0 and price_change > 0:
            flow_direction = "获利回吐"
            confidence = 0.6
        elif net_flow < 0 and price_change < 0:
            flow_direction = "恐慌抛售"
            confidence = 0.5
        else:
            flow_direction = "震荡整理"
            confidence = 0.3
            
        return {
            'net_flow': net_flow,
            'buy_volume': buy_volume,
            'sell_volume': sell_volume,
            'direction': flow_direction,
            'confidence': confidence,
            'score': min(30, abs(net_flow) / 1000000)  # 每百万净流入得1分，最高30分
        }

    def detect_technical_signals(self, price_change: float, volume_ratio: float) -> Dict:
        """检测技术信号"""
        signals = []
        score = 0
        
        # 价格突破信号
        if price_change > 5 and volume_ratio > 2:
            signals.append("放量突破")
            score += 25
        elif price_change > 3 and volume_ratio > 1.5:
            signals.append("温和突破")
            score += 15
            
        # 底部放量信号
        if -5 < price_change < 0 and volume_ratio > 3:
            signals.append("底部放量")
            score += 20
            
        # 异常放量信号
        if volume_ratio > 5:
            signals.append("异常放量")
            score += 15
            
        # 强势整理信号
        if -2 < price_change < 2 and volume_ratio > 2:
            signals.append("强势整理")
            score += 10
            
        return {
            'signals': signals,
            'score': min(score, 30),  # 最高30分
            'description': " | ".join(signals) if signals else "无明显信号"
        }

    def analyze_whale_activity(self, large_orders: List[Dict]) -> Dict:
        """分析巨鲸活动"""
        if not large_orders:
            return {'level': '无', 'score': 0, 'description': '未检测到大额交易'}
            
        # 统计巨鲸活动
        mega_orders = [o for o in large_orders if o['size'] > 1000000]  # 百万级大单
        whale_orders = [o for o in large_orders if o['size'] > 500000]  # 50万级大单
        
        total_whale_volume = sum(o['size'] for o in whale_orders)
        
        if mega_orders:
            level = "巨鲸出没"
            score = 25
        elif len(whale_orders) >= 3:
            level = "多鲸聚集"
            score = 20
        elif whale_orders:
            level = "鲸鱼活跃"
            score = 15
        else:
            level = "散户为主"
            score = 5
            
        return {
            'level': level,
            'mega_orders': len(mega_orders),
            'whale_orders': len(whale_orders),
            'total_volume': total_whale_volume,
            'score': score,
            'description': f"{level} - {len(whale_orders)}笔大单"
        }

    def calculate_potential_score(self, analysis: CoinAnalysis) -> float:
        """计算潜力评分"""
        score = 0
        
        # 成交量异动得分 (0-40分)
        volume_score = min(40, analysis.volume_ratio * 10)
        score += volume_score
        
        # 资金流向得分 (0-30分) 
        score += analysis.fund_flow.get('score', 0)
        
        # 技术信号得分 (0-30分)
        score += analysis.technical_signals.get('score', 0)
        
        # 巨鲸活动得分 (0-25分)
        score += analysis.whale_activity.get('score', 0)
        
        # 价格表现调整
        if analysis.price_change_24h > 10:
            score *= 0.8  # 涨幅过大打折扣
        elif analysis.price_change_24h < -10:
            score *= 0.9  # 跌幅过大打折扣
            
        return min(100, score)

    def scan_single_coin(self, symbol: str) -> CoinAnalysis:
        """扫描单个币种"""
        # 获取市场数据
        market_data = self.get_simulated_market_data(symbol)
        
        # 分析成交量
        volume_analysis = self.analyze_volume_surge(
            market_data['volume_24h'], 
            market_data['volume_7d_avg']
        )
        
        # 模拟大单数据
        large_orders = self.simulate_large_orders(symbol, market_data['volume_24h'])
        
        # 分析资金流向
        fund_flow = self.analyze_fund_flow(
            market_data['price_change_24h'],
            volume_analysis['ratio'],
            large_orders
        )
        
        # 检测技术信号
        technical_signals = self.detect_technical_signals(
            market_data['price_change_24h'],
            volume_analysis['ratio']
        )
        
        # 分析巨鲸活动
        whale_activity = self.analyze_whale_activity(large_orders)
        
        # 创建分析对象
        analysis = CoinAnalysis(
            symbol=symbol,
            current_price=market_data['current_price'],
            volume_24h=market_data['volume_24h'],
            volume_7d_avg=market_data['volume_7d_avg'],
            volume_ratio=volume_analysis['ratio'],
            price_change_24h=market_data['price_change_24h'],
            large_orders=large_orders,
            fund_flow=fund_flow,
            technical_signals=technical_signals,
            whale_activity=whale_activity,
            potential_score=0  # 稍后计算
        )
        
        # 计算最终评分
        analysis.potential_score = self.calculate_potential_score(analysis)
        
        return analysis

    def scan_all_coins(self) -> List[CoinAnalysis]:
        """扫描所有币种"""
        print("\n🔍 开始扫描潜力币种...")
        print("=" * 60)
        
        all_analyses = []
        
        for i, symbol in enumerate(self.symbols, 1):
            print(f"\r📊 扫描进度: {i}/{len(self.symbols)} - {symbol}", end="")
            
            analysis = self.scan_single_coin(symbol)
            all_analyses.append(analysis)
            
            # 模拟扫描延时
            time.sleep(0.1)
        
        print(f"\n✅ 扫描完成，共分析 {len(all_analyses)} 个币种")
        
        # 按潜力评分排序
        all_analyses.sort(key=lambda x: x.potential_score, reverse=True)
        
        return all_analyses

    def generate_report(self, analyses: List[CoinAnalysis]) -> Dict:
        """生成分析报告"""
        # 筛选高潜力币种
        high_potential = [a for a in analyses if a.potential_score >= self.potential_threshold]
        volume_surge = [a for a in analyses if a.volume_ratio >= self.volume_threshold]
        whale_activity = [a for a in analyses if a.whale_activity['score'] >= 20]
        
        report = {
            'scan_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'total_scanned': len(analyses),
            'high_potential_count': len(high_potential),
            'volume_surge_count': len(volume_surge),
            'whale_activity_count': len(whale_activity),
            'high_potential_coins': high_potential[:10],  # 前10名
            'volume_surge_coins': volume_surge[:10],
            'whale_activity_coins': whale_activity[:10],
            'summary': self._generate_summary(analyses)
        }
        
        return report

    def _generate_summary(self, analyses: List[CoinAnalysis]) -> Dict:
        """生成扫描总结"""
        if not analyses:
            return {'message': '无有效数据'}
            
        avg_score = sum(a.potential_score for a in analyses) / len(analyses)
        max_score = max(a.potential_score for a in analyses)
        
        top_coin = analyses[0] if analyses else None
        
        return {
            'average_score': round(avg_score, 2),
            'max_score': round(max_score, 2),
            'top_coin': top_coin.symbol if top_coin else None,
            'market_sentiment': self._judge_market_sentiment(avg_score),
            'scan_quality': 'excellent' if max_score > 80 else 'good' if max_score > 60 else 'normal'
        }

    def _judge_market_sentiment(self, avg_score: float) -> str:
        """判断市场情绪"""
        if avg_score >= 50:
            return "极度活跃"
        elif avg_score >= 40:
            return "高度活跃"
        elif avg_score >= 30:
            return "适度活跃"
        elif avg_score >= 20:
            return "相对平静"
        else:
            return "市场低迷"

    def print_detailed_report(self, report: Dict):
        """打印详细报告"""
        print("\n" + "="*60)
        print("🎯 潜力币种扫描报告")
        print("="*60)
        
        # 总览
        print(f"\n📊 扫描总览:")
        print(f"   扫描时间: {report['scan_time']}")
        print(f"   扫描币种: {report['total_scanned']} 个")
        print(f"   高潜力币种: {report['high_potential_count']} 个")
        print(f"   异常放量币种: {report['volume_surge_count']} 个")
        print(f"   巨鲸活跃币种: {report['whale_activity_count']} 个")
        
        # 市场情绪
        summary = report['summary']
        print(f"\n🌡️ 市场情绪: {summary['market_sentiment']}")
        print(f"   平均评分: {summary['average_score']}")
        print(f"   最高评分: {summary['max_score']}")
        print(f"   顶级币种: {summary['top_coin']}")
        
        # 高潜力币种详情
        if report['high_potential_coins']:
            print(f"\n🏆 高潜力币种 TOP 5:")
            print("-" * 40)
            for i, coin in enumerate(report['high_potential_coins'][:5], 1):
                emoji = "🥇" if i == 1 else "🥈" if i == 2 else "🥉" if i == 3 else "🏅"
                print(f"{emoji} {i}. {coin.symbol}")
                print(f"   💰 价格: ${coin.current_price:.4f} ({coin.price_change_24h:+.2f}%)")
                print(f"   📊 成交量放大: {coin.volume_ratio:.1f}x")
                print(f"   🎯 潜力评分: {coin.potential_score:.1f}/100")
                print(f"   💹 资金流向: {coin.fund_flow['direction']}")
                print(f"   🐋 巨鲸活动: {coin.whale_activity['level']}")
                if coin.technical_signals['signals']:
                    print(f"   📈 技术信号: {coin.technical_signals['description']}")
                print()
        
        # 异常放量警报
        if report['volume_surge_coins']:
            print(f"\n⚡ 异常放量警报:")
            for coin in report['volume_surge_coins'][:3]:
                print(f"   🔥 {coin.symbol}: {coin.volume_ratio:.1f}x 放量")
        
        # 巨鲸活动警报
        if report['whale_activity_coins']:
            print(f"\n🐋 巨鲸活动警报:")
            for coin in report['whale_activity_coins'][:3]:
                whale = coin.whale_activity
                print(f"   🚨 {coin.symbol}: {whale['level']} - {whale['whale_orders']}笔大单")

def main():
    """主程序"""
    print("🔍 潜力币种筛选器")
    print("💡 识别大资金部署和拉盘信号")
    print("=" * 60)
    
    # 初始化扫描器
    scanner = PotentialCoinScanner()
    
    try:
        # 执行扫描
        analyses = scanner.scan_all_coins()
        
        # 生成报告
        report = scanner.generate_report(analyses)
        
        # 打印报告
        scanner.print_detailed_report(report)
        
        # 保存结果
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f'potential_coins_scan_{timestamp}.json'
        
        # 转换为可序列化格式
        serializable_report = {
            **report,
            'high_potential_coins': [
                {
                    'symbol': coin.symbol,
                    'current_price': coin.current_price,
                    'price_change_24h': coin.price_change_24h,
                    'volume_ratio': coin.volume_ratio,
                    'potential_score': coin.potential_score,
                    'fund_flow': coin.fund_flow,
                    'technical_signals': coin.technical_signals,
                    'whale_activity': coin.whale_activity
                }
                for coin in report['high_potential_coins']
            ]
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(serializable_report, f, ensure_ascii=False, indent=2, default=str)
        
        print(f"\n📁 扫描结果已保存: {filename}")
        
    except KeyboardInterrupt:
        print("\n👋 扫描被用户中断")
    except Exception as e:
        print(f"\n❌ 扫描出错: {e}")

if __name__ == "__main__":
    main()