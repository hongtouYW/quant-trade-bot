#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
市场系统性风险评估模块
提供全面的市场风险分析和预警机制
"""

import os
import sys
import time
import json
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
from typing import Dict, Any, List, Optional, Tuple
from dataclasses import dataclass, asdict
from enum import Enum
import threading
import logging
import ccxt


class RiskLevel(Enum):
    """风险等级"""
    LOW = "low"           # 低风险
    MEDIUM = "medium"     # 中等风险
    HIGH = "high"         # 高风险
    EXTREME = "extreme"   # 极端风险


@dataclass
class MarketRiskMetrics:
    """市场风险指标"""
    timestamp: datetime
    vix_level: float                    # 波动率指数
    market_correlation: float          # 市场相关性
    liquidity_stress: float            # 流动性压力
    margin_debt_ratio: float           # 保证金债务比率
    fear_greed_index: float            # 恐惧贪婪指数
    crypto_dominance: Dict[str, float] # 加密货币主导地位
    macro_indicators: Dict[str, float] # 宏观经济指标
    risk_level: RiskLevel              # 综合风险等级
    risk_score: float                  # 风险评分 (0-100)


@dataclass
class RiskAlert:
    """风险警报"""
    timestamp: datetime
    risk_type: str
    severity: RiskLevel
    message: str
    affected_assets: List[str]
    recommended_actions: List[str]
    data_source: str


class MarketDataCollector:
    """市场数据收集器"""
    
    def __init__(self):
        self.logger = logging.getLogger("market_data_collector")
        self.exchanges = {}
        self.data_cache = {}
        self.cache_timeout = 300  # 5分钟缓存
        
        self._initialize_exchanges()
    
    def _initialize_exchanges(self):
        """初始化交易所连接"""
        try:
            # 初始化主要交易所（只读模式）
            self.exchanges['binance'] = ccxt.binance({
                'sandbox': True,
                'enableRateLimit': True,
                'timeout': 10000
            })
            
            self.exchanges['coinbase'] = ccxt.coinbasepro({
                'sandbox': True,
                'enableRateLimit': True,
                'timeout': 10000
            })
            
        except Exception as e:
            self.logger.warning(f"交易所初始化失败: {e}")
            # 使用模拟数据作为后备
            self._use_mock_exchanges()
    
    def _use_mock_exchanges(self):
        """使用模拟交易所数据"""
        class MockExchange:
            def fetch_ticker(self, symbol):
                # 返回模拟价格数据
                base_prices = {
                    'BTC/USDT': 45000, 'ETH/USDT': 3200, 'BNB/USDT': 300,
                    'ADA/USDT': 0.5, 'DOT/USDT': 25, 'SOL/USDT': 100
                }
                base_price = base_prices.get(symbol, 100)
                return {
                    'symbol': symbol,
                    'last': base_price,
                    'percentage': np.random.uniform(-10, 10),
                    'baseVolume': np.random.uniform(1000, 100000)
                }
            
            def fetch_ohlcv(self, symbol, timeframe='1d', since=None, limit=30):
                # 返回模拟OHLCV数据
                data = []
                base_price = 100
                for i in range(limit):
                    timestamp = int(time.time() - (limit-i) * 86400) * 1000
                    close = base_price * (1 + np.random.uniform(-0.05, 0.05))
                    open_price = close * (1 + np.random.uniform(-0.02, 0.02))
                    high = max(open_price, close) * (1 + np.random.uniform(0, 0.03))
                    low = min(open_price, close) * (1 - np.random.uniform(0, 0.03))
                    volume = np.random.uniform(1000, 50000)
                    data.append([timestamp, open_price, high, low, close, volume])
                    base_price = close
                return data
        
        self.exchanges['binance'] = MockExchange()
        self.exchanges['coinbase'] = MockExchange()
        self.logger.info("使用模拟交易所数据")
    
    def get_market_data(self, symbols: List[str]) -> Dict[str, Any]:
        """获取市场数据"""
        cache_key = f"market_data_{hash(tuple(symbols))}"
        
        # 检查缓存
        if cache_key in self.data_cache:
            cached_time, cached_data = self.data_cache[cache_key]
            if time.time() - cached_time < self.cache_timeout:
                return cached_data
        
        market_data = {}
        
        for symbol in symbols:
            try:
                # 尝试从主交易所获取数据
                ticker = self.exchanges['binance'].fetch_ticker(symbol)
                market_data[symbol] = {
                    'price': ticker['last'],
                    'change_24h': ticker.get('percentage', 0),
                    'volume': ticker.get('baseVolume', 0)
                }
            except Exception as e:
                self.logger.warning(f"获取 {symbol} 数据失败: {e}")
                # 使用默认值
                market_data[symbol] = {
                    'price': 0,
                    'change_24h': 0,
                    'volume': 0
                }
        
        # 缓存数据
        self.data_cache[cache_key] = (time.time(), market_data)
        return market_data
    
    def get_historical_data(self, symbol: str, days: int = 30) -> pd.DataFrame:
        """获取历史数据"""
        try:
            ohlcv = self.exchanges['binance'].fetch_ohlcv(
                symbol, '1d', limit=days
            )
            
            df = pd.DataFrame(ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
            df.set_index('timestamp', inplace=True)
            
            return df
            
        except Exception as e:
            self.logger.error(f"获取历史数据失败 {symbol}: {e}")
            # 返回空DataFrame
            return pd.DataFrame()


class RiskCalculator:
    """风险计算器"""
    
    def __init__(self):
        self.logger = logging.getLogger("risk_calculator")
    
    def calculate_volatility_index(self, prices: pd.Series, window: int = 20) -> float:
        """计算波动率指数"""
        if len(prices) < window:
            return 0.0
        
        returns = prices.pct_change().dropna()
        volatility = returns.rolling(window=window).std().iloc[-1]
        
        # 年化波动率
        annual_volatility = volatility * np.sqrt(365) * 100
        
        # 转换为VIX-like指数 (0-100)
        vix_level = min(100, annual_volatility * 2)
        
        return float(vix_level)
    
    def calculate_market_correlation(self, price_data: Dict[str, pd.Series]) -> float:
        """计算市场相关性"""
        if len(price_data) < 2:
            return 0.0
        
        # 计算价格变化的相关性矩阵
        returns_df = pd.DataFrame()
        for symbol, prices in price_data.items():
            if len(prices) > 1:
                returns_df[symbol] = prices.pct_change()
        
        if returns_df.empty:
            return 0.0
        
        correlation_matrix = returns_df.corr()
        
        # 计算平均相关性（排除对角线）
        mask = np.triu(np.ones_like(correlation_matrix, dtype=bool))
        correlations = correlation_matrix.where(~mask).stack().dropna()
        
        if len(correlations) == 0:
            return 0.0
        
        return float(correlations.mean())
    
    def calculate_liquidity_stress(self, volume_data: Dict[str, float], 
                                  historical_volumes: Dict[str, pd.Series]) -> float:
        """计算流动性压力"""
        if not volume_data or not historical_volumes:
            return 0.0
        
        stress_scores = []
        
        for symbol, current_volume in volume_data.items():
            if symbol in historical_volumes and len(historical_volumes[symbol]) > 0:
                avg_volume = historical_volumes[symbol].mean()
                if avg_volume > 0:
                    volume_ratio = current_volume / avg_volume
                    # 成交量下降表示流动性压力增加
                    stress = max(0, 1 - volume_ratio)
                    stress_scores.append(stress)
        
        if not stress_scores:
            return 0.0
        
        return float(np.mean(stress_scores) * 100)
    
    def calculate_fear_greed_index(self, market_data: Dict[str, Any]) -> float:
        """计算恐惧贪婪指数"""
        factors = []
        
        # 价格变化因子
        price_changes = [data.get('change_24h', 0) for data in market_data.values()]
        if price_changes:
            avg_change = np.mean(price_changes)
            # 正向变化增加贪婪，负向变化增加恐惧
            price_factor = 50 + avg_change * 2  # 范围大约 20-80
            factors.append(np.clip(price_factor, 0, 100))
        
        # 波动性因子
        volatilities = []
        for symbol, data in market_data.items():
            if 'price_history' in data:
                vol = np.std(data['price_history']) if len(data['price_history']) > 1 else 0
                volatilities.append(vol)
        
        if volatilities:
            avg_volatility = np.mean(volatilities)
            # 高波动性增加恐惧
            volatility_factor = max(0, 100 - avg_volatility * 100)
            factors.append(volatility_factor)
        
        # 成交量因子
        volumes = [data.get('volume', 0) for data in market_data.values()]
        if volumes and max(volumes) > 0:
            # 高成交量通常表示市场活跃
            volume_factor = min(100, np.mean(volumes) / max(volumes) * 100)
            factors.append(volume_factor)
        
        if not factors:
            return 50.0  # 中性值
        
        return float(np.mean(factors))
    
    def calculate_crypto_dominance(self, market_data: Dict[str, Any]) -> Dict[str, float]:
        """计算加密货币主导地位"""
        total_market_cap = 0
        individual_caps = {}
        
        for symbol, data in market_data.items():
            # 估算市值 (价格 * 成交量作为代理)
            market_cap = data.get('price', 0) * data.get('volume', 0)
            individual_caps[symbol] = market_cap
            total_market_cap += market_cap
        
        dominance = {}
        if total_market_cap > 0:
            for symbol, cap in individual_caps.items():
                dominance[symbol] = (cap / total_market_cap) * 100
        
        return dominance


class SystemicRiskAssessment:
    """系统性风险评估"""
    
    def __init__(self):
        self.data_collector = MarketDataCollector()
        self.risk_calculator = RiskCalculator()
        self.risk_history = []
        self.alert_handlers = []
        self.logger = logging.getLogger("systemic_risk_assessment")
        
        # 风险阈值配置
        self.risk_thresholds = {
            'vix_high': 30,
            'vix_extreme': 50,
            'correlation_high': 0.8,
            'correlation_extreme': 0.9,
            'liquidity_stress_high': 60,
            'liquidity_stress_extreme': 80,
            'fear_greed_extreme_fear': 20,
            'fear_greed_extreme_greed': 80
        }
        
        # 主要监控的加密货币
        self.monitored_symbols = [
            'BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'ADA/USDT', 
            'DOT/USDT', 'SOL/USDT', 'AVAX/USDT', 'MATIC/USDT'
        ]
    
    def assess_current_risk(self) -> MarketRiskMetrics:
        """评估当前市场风险"""
        try:
            # 收集市场数据
            market_data = self.data_collector.get_market_data(self.monitored_symbols)
            
            # 收集历史数据用于计算指标
            historical_data = {}
            for symbol in self.monitored_symbols:
                df = self.data_collector.get_historical_data(symbol, days=30)
                if not df.empty:
                    historical_data[symbol] = df['close']
            
            # 计算各项风险指标
            vix_level = self._calculate_market_vix(historical_data)
            correlation = self._calculate_market_correlation(historical_data)
            liquidity_stress = self._calculate_liquidity_stress(market_data, historical_data)
            fear_greed = self.risk_calculator.calculate_fear_greed_index(market_data)
            dominance = self.risk_calculator.calculate_crypto_dominance(market_data)
            
            # 模拟宏观经济指标
            macro_indicators = self._get_macro_indicators()
            
            # 计算综合风险评分
            risk_score, risk_level = self._calculate_composite_risk(
                vix_level, correlation, liquidity_stress, fear_greed
            )
            
            risk_metrics = MarketRiskMetrics(
                timestamp=datetime.now(),
                vix_level=vix_level,
                market_correlation=correlation,
                liquidity_stress=liquidity_stress,
                margin_debt_ratio=0.0,  # 暂时设为0
                fear_greed_index=fear_greed,
                crypto_dominance=dominance,
                macro_indicators=macro_indicators,
                risk_level=risk_level,
                risk_score=risk_score
            )
            
            # 保存到历史记录
            self.risk_history.append(risk_metrics)
            
            # 检查是否需要发送警报
            self._check_risk_alerts(risk_metrics)
            
            return risk_metrics
            
        except Exception as e:
            self.logger.error(f"风险评估失败: {e}")
            # 返回默认风险指标
            return MarketRiskMetrics(
                timestamp=datetime.now(),
                vix_level=0.0,
                market_correlation=0.0,
                liquidity_stress=0.0,
                margin_debt_ratio=0.0,
                fear_greed_index=50.0,
                crypto_dominance={},
                macro_indicators={},
                risk_level=RiskLevel.MEDIUM,
                risk_score=50.0
            )
    
    def _calculate_market_vix(self, historical_data: Dict[str, pd.Series]) -> float:
        """计算市场波动率指数"""
        if not historical_data:
            return 0.0
        
        vix_values = []
        for symbol, prices in historical_data.items():
            vix = self.risk_calculator.calculate_volatility_index(prices)
            vix_values.append(vix)
        
        return float(np.mean(vix_values)) if vix_values else 0.0
    
    def _calculate_market_correlation(self, historical_data: Dict[str, pd.Series]) -> float:
        """计算市场相关性"""
        return self.risk_calculator.calculate_market_correlation(historical_data)
    
    def _calculate_liquidity_stress(self, market_data: Dict[str, Any], 
                                   historical_data: Dict[str, pd.Series]) -> float:
        """计算流动性压力"""
        volume_data = {k: v.get('volume', 0) for k, v in market_data.items()}
        
        # 获取历史成交量
        historical_volumes = {}
        for symbol in self.monitored_symbols:
            df = self.data_collector.get_historical_data(symbol, days=30)
            if not df.empty:
                historical_volumes[symbol] = df['volume']
        
        return self.risk_calculator.calculate_liquidity_stress(volume_data, historical_volumes)
    
    def _get_macro_indicators(self) -> Dict[str, float]:
        """获取宏观经济指标"""
        # 这里可以集成真实的宏观经济数据API
        # 目前使用模拟数据
        return {
            'us_treasury_10y': 4.5 + np.random.uniform(-0.5, 0.5),
            'dxy_index': 103.0 + np.random.uniform(-2, 2),
            'gold_price': 2000.0 + np.random.uniform(-50, 50),
            'sp500_vix': 20.0 + np.random.uniform(-5, 15)
        }
    
    def _calculate_composite_risk(self, vix: float, correlation: float, 
                                liquidity_stress: float, fear_greed: float) -> Tuple[float, RiskLevel]:
        """计算综合风险评分和等级"""
        # 各指标权重
        weights = {
            'vix': 0.3,
            'correlation': 0.25,
            'liquidity': 0.25,
            'sentiment': 0.2
        }
        
        # 标准化指标到0-100范围
        vix_score = min(100, vix)
        correlation_score = abs(correlation) * 100
        liquidity_score = liquidity_stress
        
        # 恐惧贪婪指数转换为风险评分（50为中性）
        sentiment_score = abs(fear_greed - 50) * 2
        
        # 计算加权平均
        composite_score = (
            vix_score * weights['vix'] +
            correlation_score * weights['correlation'] +
            liquidity_score * weights['liquidity'] +
            sentiment_score * weights['sentiment']
        )
        
        # 确定风险等级
        if composite_score >= 75:
            risk_level = RiskLevel.EXTREME
        elif composite_score >= 60:
            risk_level = RiskLevel.HIGH
        elif composite_score >= 40:
            risk_level = RiskLevel.MEDIUM
        else:
            risk_level = RiskLevel.LOW
        
        return float(composite_score), risk_level
    
    def _check_risk_alerts(self, risk_metrics: MarketRiskMetrics):
        """检查风险警报"""
        alerts = []
        
        # VIX警报
        if risk_metrics.vix_level >= self.risk_thresholds['vix_extreme']:
            alerts.append(RiskAlert(
                timestamp=risk_metrics.timestamp,
                risk_type="极端波动",
                severity=RiskLevel.EXTREME,
                message=f"市场波动率达到极端水平: {risk_metrics.vix_level:.1f}%",
                affected_assets=self.monitored_symbols,
                recommended_actions=["减少仓位", "增加现金配置", "避免高杠杆交易"],
                data_source="VIX计算"
            ))
        elif risk_metrics.vix_level >= self.risk_thresholds['vix_high']:
            alerts.append(RiskAlert(
                timestamp=risk_metrics.timestamp,
                risk_type="高波动",
                severity=RiskLevel.HIGH,
                message=f"市场波动率偏高: {risk_metrics.vix_level:.1f}%",
                affected_assets=self.monitored_symbols,
                recommended_actions=["谨慎交易", "控制风险敞口"],
                data_source="VIX计算"
            ))
        
        # 相关性警报
        if abs(risk_metrics.market_correlation) >= self.risk_thresholds['correlation_extreme']:
            alerts.append(RiskAlert(
                timestamp=risk_metrics.timestamp,
                risk_type="极端相关性",
                severity=RiskLevel.EXTREME,
                message=f"市场相关性异常: {risk_metrics.market_correlation:.2f}",
                affected_assets=self.monitored_symbols,
                recommended_actions=["分散投资失效", "考虑对冲策略"],
                data_source="相关性分析"
            ))
        
        # 流动性警报
        if risk_metrics.liquidity_stress >= self.risk_thresholds['liquidity_stress_extreme']:
            alerts.append(RiskAlert(
                timestamp=risk_metrics.timestamp,
                risk_type="流动性危机",
                severity=RiskLevel.EXTREME,
                message=f"流动性压力严重: {risk_metrics.liquidity_stress:.1f}%",
                affected_assets=self.monitored_symbols,
                recommended_actions=["避免大额交易", "准备充足现金"],
                data_source="流动性分析"
            ))
        
        # 情绪警报
        if (risk_metrics.fear_greed_index <= self.risk_thresholds['fear_greed_extreme_fear'] or 
            risk_metrics.fear_greed_index >= self.risk_thresholds['fear_greed_extreme_greed']):
            
            sentiment = "极端恐惧" if risk_metrics.fear_greed_index <= 20 else "极端贪婪"
            alerts.append(RiskAlert(
                timestamp=risk_metrics.timestamp,
                risk_type="极端情绪",
                severity=RiskLevel.HIGH,
                message=f"市场情绪{sentiment}: {risk_metrics.fear_greed_index:.1f}",
                affected_assets=self.monitored_symbols,
                recommended_actions=["反向思考", "逆向投资机会"],
                data_source="情绪分析"
            ))
        
        # 发送警报
        for alert in alerts:
            self._send_alert(alert)
    
    def _send_alert(self, alert: RiskAlert):
        """发送风险警报"""
        for handler in self.alert_handlers:
            try:
                handler(alert)
            except Exception as e:
                self.logger.error(f"发送警报失败: {e}")
        
        # 记录警报日志
        self.logger.warning(
            f"风险警报: {alert.risk_type} | 严重程度: {alert.severity.value} | "
            f"消息: {alert.message}"
        )
    
    def register_alert_handler(self, handler):
        """注册警报处理器"""
        self.alert_handlers.append(handler)
    
    def get_risk_summary(self) -> Dict[str, Any]:
        """获取风险摘要"""
        if not self.risk_history:
            return {"error": "暂无风险数据"}
        
        latest_risk = self.risk_history[-1]
        
        # 风险趋势分析
        risk_trend = "stable"
        if len(self.risk_history) >= 2:
            prev_score = self.risk_history[-2].risk_score
            current_score = latest_risk.risk_score
            
            if current_score > prev_score + 10:
                risk_trend = "increasing"
            elif current_score < prev_score - 10:
                risk_trend = "decreasing"
        
        return {
            "timestamp": latest_risk.timestamp.isoformat(),
            "overall_risk_level": latest_risk.risk_level.value,
            "risk_score": latest_risk.risk_score,
            "risk_trend": risk_trend,
            "key_metrics": {
                "volatility_index": latest_risk.vix_level,
                "market_correlation": latest_risk.market_correlation,
                "liquidity_stress": latest_risk.liquidity_stress,
                "fear_greed_index": latest_risk.fear_greed_index
            },
            "recommendations": self._get_risk_recommendations(latest_risk)
        }
    
    def _get_risk_recommendations(self, risk_metrics: MarketRiskMetrics) -> List[str]:
        """获取风险建议"""
        recommendations = []
        
        if risk_metrics.risk_level == RiskLevel.EXTREME:
            recommendations.extend([
                "立即降低仓位至安全水平",
                "停止所有高风险交易",
                "增加现金和稳定币配置",
                "密切监控市场动态"
            ])
        elif risk_metrics.risk_level == RiskLevel.HIGH:
            recommendations.extend([
                "减少风险敞口",
                "避免高杠杆操作",
                "考虑对冲策略",
                "增加止损设置"
            ])
        elif risk_metrics.risk_level == RiskLevel.MEDIUM:
            recommendations.extend([
                "保持谨慎态度",
                "适当控制仓位",
                "关注市场变化"
            ])
        else:
            recommendations.extend([
                "市场风险较低",
                "可考虑适度增加仓位",
                "把握投资机会"
            ])
        
        return recommendations


def main():
    """主函数 - 演示风险评估功能"""
    print("🔍 市场系统性风险评估系统")
    print("=" * 50)
    
    # 创建风险评估实例
    risk_assessment = SystemicRiskAssessment()
    
    # 注册警报处理器
    def print_alert(alert: RiskAlert):
        print(f"\n🚨 风险警报:")
        print(f"   类型: {alert.risk_type}")
        print(f"   严重程度: {alert.severity.value}")
        print(f"   消息: {alert.message}")
        print(f"   建议: {', '.join(alert.recommended_actions)}")
    
    risk_assessment.register_alert_handler(print_alert)
    
    # 执行风险评估
    print("📊 正在评估市场风险...")
    risk_metrics = risk_assessment.assess_current_risk()
    
    print(f"\n📈 风险评估结果:")
    print(f"   整体风险等级: {risk_metrics.risk_level.value.upper()}")
    print(f"   风险评分: {risk_metrics.risk_score:.1f}/100")
    print(f"   波动率指数: {risk_metrics.vix_level:.1f}%")
    print(f"   市场相关性: {risk_metrics.market_correlation:.2f}")
    print(f"   流动性压力: {risk_metrics.liquidity_stress:.1f}%")
    print(f"   恐惧贪婪指数: {risk_metrics.fear_greed_index:.1f}")
    
    # 显示主导地位
    if risk_metrics.crypto_dominance:
        print(f"\n🏆 市场主导地位:")
        sorted_dominance = sorted(
            risk_metrics.crypto_dominance.items(), 
            key=lambda x: x[1], 
            reverse=True
        )[:3]
        for symbol, dominance in sorted_dominance:
            print(f"   {symbol}: {dominance:.1f}%")
    
    # 获取风险摘要和建议
    summary = risk_assessment.get_risk_summary()
    if "recommendations" in summary:
        print(f"\n💡 风险建议:")
        for rec in summary["recommendations"]:
            print(f"   - {rec}")


if __name__ == '__main__':
    main()