# -*- coding: utf-8 -*-
"""
大资金监控系统 - 实时追踪主力资金动向
识别庄家吸筹、拉盘准备信号
"""

import json
import random
from datetime import datetime, timedelta
from typing import Dict, List, Optional

class BigMoneyTracker:
    """大资金追踪器"""
    
    def __init__(self):
        self.tracking_symbols = []
        self.whale_alerts = []
        self.accumulation_patterns = []
        
        # 监控阈值
        self.mega_order_threshold = 1000000    # 百万级大单
        self.whale_threshold = 500000          # 50万鲸鱼单
        self.accumulation_threshold = 5000000  # 500万累积量
        
        print("🐋 大资金监控系统启动")

    def detect_accumulation_pattern(self, symbol: str, timeframe: str = '1h') -> Dict:
        """检测吸筹模式"""
        # 模拟24小时内的交易数据
        orders = self._simulate_order_flow(symbol, hours=24)
        
        # 按价格区间分析订单分布
        price_ranges = self._analyze_price_distribution(orders)
        
        # 识别吸筹特征
        accumulation_signals = []
        score = 0
        
        # 1. 低位大量买单
        low_buy_volume = sum(o['size'] for o in orders 
                           if o['type'] == 'buy' and o['price_level'] == 'low')
        if low_buy_volume > self.accumulation_threshold:
            accumulation_signals.append("低位大量吸筹")
            score += 30
        
        # 2. 分散式买入（避免拉升价格）
        buy_orders = [o for o in orders if o['type'] == 'buy']
        if len(buy_orders) > 50 and max(o['size'] for o in buy_orders) < 200000:
            accumulation_signals.append("分散式吸筹")
            score += 25
        
        # 3. 压盘出货检测
        if self._detect_suppression_pattern(orders):
            accumulation_signals.append("压盘吸筹")
            score += 20
        
        # 4. 持续净买入
        net_buy = sum(o['size'] for o in orders if o['type'] == 'buy') - \
                  sum(o['size'] for o in orders if o['type'] == 'sell')
        if net_buy > 0:
            accumulation_signals.append(f"净买入{net_buy/1000000:.1f}M")
            score += 15
        
        return {
            'symbol': symbol,
            'pattern_detected': len(accumulation_signals) > 0,
            'signals': accumulation_signals,
            'score': score,
            'confidence': min(score / 100, 0.95),
            'net_flow': net_buy,
            'total_volume': sum(o['size'] for o in orders),
            'analysis_time': datetime.now()
        }

    def detect_pump_preparation(self, symbol: str) -> Dict:
        """检测拉盘准备信号"""
        # 获取最近数据
        recent_data = self._get_recent_market_data(symbol)
        order_flow = self._simulate_order_flow(symbol, hours=6)
        
        pump_signals = []
        confidence = 0
        
        # 1. 成交量递增模式
        volume_trend = recent_data.get('volume_trend', 'stable')
        if volume_trend == 'increasing':
            pump_signals.append("成交量递增")
            confidence += 0.2
        
        # 2. 大单买入增加
        large_buys = [o for o in order_flow 
                     if o['type'] == 'buy' and o['size'] > self.whale_threshold]
        if len(large_buys) >= 3:
            pump_signals.append(f"{len(large_buys)}笔大额买单")
            confidence += 0.25
        
        # 3. 盘口深度变化（买单增厚）
        if recent_data.get('bid_depth_increase', False):
            pump_signals.append("买盘深度增厚")
            confidence += 0.15
        
        # 4. 技术位突破准备
        if recent_data.get('near_breakout', False):
            pump_signals.append("接近技术突破位")
            confidence += 0.2
        
        # 5. 异常时间交易（非正常交易时间大单）
        off_hours_orders = [o for o in order_flow 
                           if o['timestamp'].hour in [0,1,2,3,4,5,6] and o['size'] > 100000]
        if off_hours_orders:
            pump_signals.append(f"异常时间{len(off_hours_orders)}笔大单")
            confidence += 0.18
        
        return {
            'symbol': symbol,
            'preparation_detected': len(pump_signals) > 0,
            'signals': pump_signals,
            'confidence': confidence,
            'risk_level': self._assess_pump_risk(confidence),
            'estimated_timeframe': self._estimate_pump_timeframe(pump_signals),
            'target_analysis': self._analyze_pump_targets(recent_data)
        }

    def monitor_whale_movements(self, symbols: List[str]) -> Dict:
        """监控鲸鱼动向"""
        whale_activities = []
        
        for symbol in symbols:
            # 获取大额交易
            large_orders = self._get_large_orders(symbol, hours=12)
            
            # 分析鲸鱼行为
            whale_behavior = self._analyze_whale_behavior(large_orders)
            
            if whale_behavior['significant']:
                whale_activities.append({
                    'symbol': symbol,
                    'behavior': whale_behavior,
                    'alert_level': whale_behavior['alert_level'],
                    'orders': large_orders[:5]  # 前5笔大单
                })
        
        # 生成鲸鱼活动报告
        return {
            'scan_time': datetime.now(),
            'active_whales': len(whale_activities),
            'high_alert_count': len([w for w in whale_activities if w['alert_level'] == 'high']),
            'whale_activities': sorted(whale_activities, 
                                     key=lambda x: x['behavior']['impact_score'], 
                                     reverse=True),
            'market_impact_summary': self._summarize_market_impact(whale_activities)
        }

    def _simulate_order_flow(self, symbol: str, hours: int = 24) -> List[Dict]:
        """模拟订单流数据"""
        orders = []
        current_time = datetime.now()
        
        # 基础价格
        base_price = {'BTC/USDT': 95000, 'ETH/USDT': 3400}.get(symbol, 1000)
        
        for i in range(hours * 10):  # 每小时10个订单
            order_time = current_time - timedelta(minutes=i*6)
            
            # 随机生成订单
            order_type = random.choice(['buy', 'sell'])
            size = random.lognormvariate(10, 2)  # 对数正态分布，产生少量大单
            size = max(1000, min(size, 5000000))  # 限制在1K-5M之间
            
            # 价格水平判断
            price_variation = random.uniform(-0.02, 0.02)
            current_price = base_price * (1 + price_variation)
            
            if current_price < base_price * 0.99:
                price_level = 'low'
            elif current_price > base_price * 1.01:
                price_level = 'high'
            else:
                price_level = 'middle'
            
            orders.append({
                'timestamp': order_time,
                'type': order_type,
                'size': size,
                'price': current_price,
                'price_level': price_level
            })
        
        return sorted(orders, key=lambda x: x['timestamp'])

    def _analyze_price_distribution(self, orders: List[Dict]) -> Dict:
        """分析价格分布"""
        if not orders:
            return {}
        
        prices = [o['price'] for o in orders]
        min_price, max_price = min(prices), max(prices)
        
        # 分成3个价格区间
        low_threshold = min_price + (max_price - min_price) * 0.33
        high_threshold = min_price + (max_price - min_price) * 0.67
        
        low_volume = sum(o['size'] for o in orders if o['price'] <= low_threshold)
        mid_volume = sum(o['size'] for o in orders if low_threshold < o['price'] <= high_threshold)
        high_volume = sum(o['size'] for o in orders if o['price'] > high_threshold)
        
        return {
            'low_volume': low_volume,
            'mid_volume': mid_volume,
            'high_volume': high_volume,
            'distribution_ratio': f"{low_volume/(low_volume+mid_volume+high_volume)*100:.1f}%:{mid_volume/(low_volume+mid_volume+high_volume)*100:.1f}%:{high_volume/(low_volume+mid_volume+high_volume)*100:.1f}%"
        }

    def _detect_suppression_pattern(self, orders: List[Dict]) -> bool:
        """检测压盘模式"""
        # 查找大额卖单在价格上涨时出现的模式
        sell_orders = [o for o in orders if o['type'] == 'sell' and o['size'] > 200000]
        
        # 如果有多个大额卖单在不同时间出现，且总量较大，可能是压盘
        if len(sell_orders) >= 3 and sum(o['size'] for o in sell_orders) > 1000000:
            return True
        return False

    def _get_recent_market_data(self, symbol: str) -> Dict:
        """获取最近市场数据"""
        return {
            'volume_trend': random.choice(['increasing', 'stable', 'decreasing']),
            'bid_depth_increase': random.choice([True, False]),
            'near_breakout': random.choice([True, False]),
            'price_compression': random.choice([True, False])  # 价格收窄
        }

    def _get_large_orders(self, symbol: str, hours: int = 12) -> List[Dict]:
        """获取大额订单"""
        all_orders = self._simulate_order_flow(symbol, hours)
        return [o for o in all_orders if o['size'] > self.whale_threshold]

    def _analyze_whale_behavior(self, large_orders: List[Dict]) -> Dict:
        """分析鲸鱼行为"""
        if not large_orders:
            return {'significant': False, 'alert_level': 'none', 'impact_score': 0}
        
        buy_volume = sum(o['size'] for o in large_orders if o['type'] == 'buy')
        sell_volume = sum(o['size'] for o in large_orders if o['type'] == 'sell')
        net_flow = buy_volume - sell_volume
        
        # 计算影响评分
        impact_score = abs(net_flow) / 1000000  # 百万为单位
        
        # 判断行为类型
        if net_flow > 2000000:
            behavior_type = "大量吸筹"
            alert_level = "high"
        elif net_flow > 500000:
            behavior_type = "积极买入"
            alert_level = "medium"
        elif net_flow < -2000000:
            behavior_type = "大量抛售"
            alert_level = "high"
        elif net_flow < -500000:
            behavior_type = "积极卖出"
            alert_level = "medium"
        else:
            behavior_type = "平衡交易"
            alert_level = "low"
        
        return {
            'significant': abs(net_flow) > 500000,
            'behavior_type': behavior_type,
            'alert_level': alert_level,
            'impact_score': impact_score,
            'net_flow': net_flow,
            'buy_volume': buy_volume,
            'sell_volume': sell_volume,
            'order_count': len(large_orders)
        }

    def _assess_pump_risk(self, confidence: float) -> str:
        """评估拉盘风险等级"""
        if confidence >= 0.8:
            return "极高"
        elif confidence >= 0.6:
            return "高"
        elif confidence >= 0.4:
            return "中等"
        elif confidence >= 0.2:
            return "低"
        else:
            return "极低"

    def _estimate_pump_timeframe(self, signals: List[str]) -> str:
        """估算拉盘时间框架"""
        signal_count = len(signals)
        
        if signal_count >= 4:
            return "24-48小时内"
        elif signal_count >= 3:
            return "2-7天内"
        elif signal_count >= 2:
            return "1-2周内"
        else:
            return "时间不明确"

    def _analyze_pump_targets(self, market_data: Dict) -> Dict:
        """分析拉盘目标位"""
        return {
            'short_term_target': "5-15%",
            'medium_term_target': "15-35%",
            'risk_reward_ratio': "1:2",
            'key_resistance_levels': ["当前价格+10%", "当前价格+25%", "当前价格+50%"]
        }

    def _summarize_market_impact(self, whale_activities: List[Dict]) -> Dict:
        """总结市场影响"""
        if not whale_activities:
            return {'overall_sentiment': 'neutral', 'risk_level': 'low'}
        
        total_impact = sum(w['behavior']['impact_score'] for w in whale_activities)
        net_flow = sum(w['behavior']['net_flow'] for w in whale_activities)
        
        if net_flow > 5000000:
            sentiment = "极度看多"
        elif net_flow > 1000000:
            sentiment = "看多"
        elif net_flow > -1000000:
            sentiment = "中性"
        elif net_flow > -5000000:
            sentiment = "看空"
        else:
            sentiment = "极度看空"
        
        return {
            'overall_sentiment': sentiment,
            'total_impact_score': total_impact,
            'net_market_flow': net_flow,
            'risk_level': 'high' if total_impact > 10 else 'medium' if total_impact > 5 else 'low'
        }

    def run_comprehensive_scan(self, symbols: List[str]) -> Dict:
        """运行综合扫描"""
        print("🔍 启动大资金综合扫描...")
        
        results = {
            'scan_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'symbols_scanned': len(symbols),
            'accumulation_analysis': [],
            'pump_preparation': [],
            'whale_monitoring': None,
            'alerts': []
        }
        
        # 1. 吸筹模式检测
        print("📊 检测吸筹模式...")
        for symbol in symbols[:10]:  # 前10个币种
            accumulation = self.detect_accumulation_pattern(symbol)
            if accumulation['pattern_detected']:
                results['accumulation_analysis'].append(accumulation)
        
        # 2. 拉盘准备检测
        print("🚀 检测拉盘准备...")
        for symbol in symbols[:10]:
            pump_prep = self.detect_pump_preparation(symbol)
            if pump_prep['preparation_detected']:
                results['pump_preparation'].append(pump_prep)
        
        # 3. 鲸鱼监控
        print("🐋 监控鲸鱼动向...")
        results['whale_monitoring'] = self.monitor_whale_movements(symbols[:15])
        
        # 4. 生成警报
        results['alerts'] = self._generate_alerts(results)
        
        print("✅ 大资金扫描完成")
        return results

    def _generate_alerts(self, scan_results: Dict) -> List[Dict]:
        """生成警报"""
        alerts = []
        
        # 高置信度吸筹警报
        for acc in scan_results['accumulation_analysis']:
            if acc['confidence'] > 0.7:
                alerts.append({
                    'type': 'accumulation',
                    'symbol': acc['symbol'],
                    'level': 'high',
                    'message': f"{acc['symbol']} 检测到主力吸筹",
                    'confidence': acc['confidence']
                })
        
        # 拉盘准备警报
        for pump in scan_results['pump_preparation']:
            if pump['confidence'] > 0.6:
                alerts.append({
                    'type': 'pump_preparation',
                    'symbol': pump['symbol'],
                    'level': 'high' if pump['confidence'] > 0.8 else 'medium',
                    'message': f"{pump['symbol']} 疑似拉盘准备",
                    'timeframe': pump['estimated_timeframe']
                })
        
        # 鲸鱼活动警报
        whale_data = scan_results['whale_monitoring']
        if whale_data and whale_data['high_alert_count'] > 0:
            alerts.append({
                'type': 'whale_activity',
                'level': 'high',
                'message': f"检测到{whale_data['high_alert_count']}个高风险鲸鱼活动",
                'details': whale_data['market_impact_summary']
            })
        
        return alerts

def main():
    """主程序"""
    print("🐋 大资金监控系统")
    print("💡 识别庄家动向和拉盘信号")
    print("=" * 50)
    
    tracker = BigMoneyTracker()
    
    # 监控币种列表
    symbols = [
        'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT',
        'DOT/USDT', 'LINK/USDT', 'MATIC/USDT', 'AVAX/USDT', 'ATOM/USDT'
    ]
    
    try:
        # 执行综合扫描
        results = tracker.run_comprehensive_scan(symbols)
        
        # 打印结果
        print(f"\n📋 扫描报告 - {results['scan_time']}")
        print("-" * 50)
        
        # 吸筹检测结果
        if results['accumulation_analysis']:
            print(f"\n📈 发现 {len(results['accumulation_analysis'])} 个吸筹信号:")
            for acc in results['accumulation_analysis']:
                print(f"   🎯 {acc['symbol']}: 吸筹信心度 {acc['confidence']:.1%}")
                print(f"      信号: {' | '.join(acc['signals'])}")
        
        # 拉盘准备结果
        if results['pump_preparation']:
            print(f"\n🚀 发现 {len(results['pump_preparation'])} 个拉盘准备信号:")
            for pump in results['pump_preparation']:
                print(f"   📊 {pump['symbol']}: 拉盘信心度 {pump['confidence']:.1%}")
                print(f"      预计时间: {pump['estimated_timeframe']}")
        
        # 鲸鱼活动结果
        whale_data = results['whale_monitoring']
        if whale_data and whale_data['active_whales'] > 0:
            print(f"\n🐋 活跃鲸鱼: {whale_data['active_whales']} 个")
            print(f"   高风险活动: {whale_data['high_alert_count']} 个")
            print(f"   市场情绪: {whale_data['market_impact_summary']['overall_sentiment']}")
        
        # 重要警报
        if results['alerts']:
            print(f"\n🚨 重要警报 ({len(results['alerts'])} 个):")
            for alert in results['alerts']:
                level_emoji = "🔴" if alert['level'] == 'high' else "🟡"
                print(f"   {level_emoji} {alert['message']}")
        
        # 保存结果
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f'big_money_scan_{timestamp}.json'
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(results, f, ensure_ascii=False, indent=2, default=str)
        
        print(f"\n💾 扫描结果已保存: {filename}")
        
    except Exception as e:
        print(f"❌ 扫描出错: {e}")

if __name__ == "__main__":
    main()