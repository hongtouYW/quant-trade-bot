# -*- coding: utf-8 -*-
"""
量化交易策略引擎
支持多种技术指标策略
"""

import pandas as pd
import numpy as np
import talib
from typing import Dict, List, Tuple, Optional
import logging
from datetime import datetime, timedelta

logger = logging.getLogger(__name__)

class TradingStrategy:
    """交易策略基类"""
    
    def __init__(self, name: str):
        self.name = name
        self.signals = []
    
    def analyze(self, df: pd.DataFrame, symbol: str) -> Dict:
        """分析市场数据并生成信号"""
        raise NotImplementedError
    
    def calculate_confidence(self, indicators: Dict) -> float:
        """计算信号置信度"""
        return 0.5

class RSIMeanReversionStrategy(TradingStrategy):
    """RSI均值回归策略"""
    
    def __init__(self, rsi_period: int = 14, oversold: int = 30, overbought: int = 70):
        super().__init__("RSI均值回归策略")
        self.rsi_period = rsi_period
        self.oversold = oversold
        self.overbought = overbought
    
    def analyze(self, df: pd.DataFrame, symbol: str) -> Dict:
        """RSI策略分析"""
        try:
            if len(df) < self.rsi_period + 1:
                return {'signal': 'hold', 'confidence': 0.0, 'reason': '数据不足'}
            
            # 计算RSI
            rsi = talib.RSI(df['close'].values, timeperiod=self.rsi_period)
            current_rsi = rsi[-1]
            prev_rsi = rsi[-2] if len(rsi) > 1 else current_rsi
            
            # 计算价格变化
            price_change = (df['close'].iloc[-1] - df['close'].iloc[-2]) / df['close'].iloc[-2] * 100
            
            signal = 'hold'
            confidence = 0.0
            reason = ""
            
            # RSI超卖信号
            if current_rsi <= self.oversold and prev_rsi > current_rsi:
                signal = 'buy'
                confidence = min(0.9, (self.oversold - current_rsi) / self.oversold + 0.3)
                reason = f"RSI超卖反弹信号 (RSI: {current_rsi:.1f})"
            
            # RSI超买信号
            elif current_rsi >= self.overbought and prev_rsi < current_rsi:
                signal = 'sell'
                confidence = min(0.9, (current_rsi - self.overbought) / (100 - self.overbought) + 0.3)
                reason = f"RSI超买回调信号 (RSI: {current_rsi:.1f})"
            
            # RSI背离信号
            elif self._check_divergence(df, rsi):
                if current_rsi < 50:
                    signal = 'buy'
                    confidence = 0.6
                    reason = "RSI背离看涨信号"
                else:
                    signal = 'sell'
                    confidence = 0.6
                    reason = "RSI背离看跌信号"
            
            return {
                'signal': signal,
                'confidence': confidence,
                'reason': reason,
                'rsi': current_rsi,
                'price_change': price_change,
                'strategy': self.name
            }
            
        except Exception as e:
            logger.error(f"❌ RSI策略分析失败: {e}")
            return {'signal': 'hold', 'confidence': 0.0, 'reason': f'分析错误: {e}'}
    
    def _check_divergence(self, df: pd.DataFrame, rsi: np.ndarray) -> bool:
        """检查RSI背离"""
        try:
            if len(df) < 10:
                return False
            
            # 简单的背离检测：价格新高但RSI未创新高
            recent_price = df['close'].iloc[-5:].max()
            recent_rsi = rsi[-5:].max()
            
            prev_price = df['close'].iloc[-10:-5].max()
            prev_rsi = rsi[-10:-5].max()
            
            # 看跌背离：价格新高，RSI未新高
            if recent_price > prev_price and recent_rsi < prev_rsi:
                return True
            
            # 看涨背离：价格新低，RSI未新低  
            recent_low_price = df['close'].iloc[-5:].min()
            recent_low_rsi = rsi[-5:].min()
            prev_low_price = df['close'].iloc[-10:-5].min()
            prev_low_rsi = rsi[-10:-5].min()
            
            if recent_low_price < prev_low_price and recent_low_rsi > prev_low_rsi:
                return True
            
            return False
            
        except:
            return False

class MACDTrendStrategy(TradingStrategy):
    """MACD趋势策略"""
    
    def __init__(self, fast_period: int = 12, slow_period: int = 26, signal_period: int = 9):
        super().__init__("MACD趋势策略")
        self.fast_period = fast_period
        self.slow_period = slow_period
        self.signal_period = signal_period
    
    def analyze(self, df: pd.DataFrame, symbol: str) -> Dict:
        """MACD策略分析"""
        try:
            if len(df) < self.slow_period + self.signal_period:
                return {'signal': 'hold', 'confidence': 0.0, 'reason': '数据不足'}
            
            # 计算MACD
            macd, macd_signal, macd_hist = talib.MACD(
                df['close'].values, 
                fastperiod=self.fast_period, 
                slowperiod=self.slow_period, 
                signalperiod=self.signal_period
            )
            
            current_macd = macd[-1]
            current_signal = macd_signal[-1]
            current_hist = macd_hist[-1]
            prev_hist = macd_hist[-2] if len(macd_hist) > 1 else current_hist
            
            signal = 'hold'
            confidence = 0.0
            reason = ""
            
            # MACD金叉信号
            if current_hist > 0 and prev_hist <= 0:
                signal = 'buy'
                confidence = min(0.8, abs(current_hist) * 1000 + 0.4)
                reason = f"MACD金叉买入信号 (MACD: {current_macd:.4f})"
            
            # MACD死叉信号
            elif current_hist < 0 and prev_hist >= 0:
                signal = 'sell'
                confidence = min(0.8, abs(current_hist) * 1000 + 0.4)
                reason = f"MACD死叉卖出信号 (MACD: {current_macd:.4f})"
            
            # MACD零轴突破
            elif self._check_zero_cross(macd):
                if current_macd > 0:
                    signal = 'buy'
                    confidence = 0.6
                    reason = "MACD突破零轴看涨"
                else:
                    signal = 'sell'
                    confidence = 0.6
                    reason = "MACD跌破零轴看跌"
            
            return {
                'signal': signal,
                'confidence': confidence,
                'reason': reason,
                'macd': current_macd,
                'macd_signal': current_signal,
                'macd_hist': current_hist,
                'strategy': self.name
            }
            
        except Exception as e:
            logger.error(f"❌ MACD策略分析失败: {e}")
            return {'signal': 'hold', 'confidence': 0.0, 'reason': f'分析错误: {e}'}
    
    def _check_zero_cross(self, macd: np.ndarray) -> bool:
        """检查零轴突破"""
        try:
            if len(macd) < 2:
                return False
            
            current = macd[-1]
            prev = macd[-2]
            
            # 从负到正或从正到负
            return (current > 0 and prev <= 0) or (current < 0 and prev >= 0)
            
        except:
            return False

class BollingerBandsStrategy(TradingStrategy):
    """布林带策略"""
    
    def __init__(self, period: int = 20, std_dev: int = 2):
        super().__init__("布林带均值回归策略")
        self.period = period
        self.std_dev = std_dev
    
    def analyze(self, df: pd.DataFrame, symbol: str) -> Dict:
        """布林带策略分析"""
        try:
            if len(df) < self.period:
                return {'signal': 'hold', 'confidence': 0.0, 'reason': '数据不足'}
            
            # 计算布林带
            upper, middle, lower = talib.BBANDS(
                df['close'].values, 
                timeperiod=self.period, 
                nbdevup=self.std_dev, 
                nbdevdn=self.std_dev
            )
            
            current_price = df['close'].iloc[-1]
            current_upper = upper[-1]
            current_lower = lower[-1]
            current_middle = middle[-1]
            
            # 计算布林带宽度
            bb_width = (current_upper - current_lower) / current_middle
            
            signal = 'hold'
            confidence = 0.0
            reason = ""
            
            # 价格触及下轨
            if current_price <= current_lower:
                signal = 'buy'
                distance = abs(current_price - current_lower) / current_lower
                confidence = min(0.8, 0.5 + distance * 10)
                reason = f"价格触及布林带下轨 (${current_price:.2f} vs ${current_lower:.2f})"
            
            # 价格触及上轨
            elif current_price >= current_upper:
                signal = 'sell'
                distance = abs(current_price - current_upper) / current_upper
                confidence = min(0.8, 0.5 + distance * 10)
                reason = f"价格触及布林带上轨 (${current_price:.2f} vs ${current_upper:.2f})"
            
            # 布林带收缩后扩张
            elif self._check_squeeze_breakout(df, upper, lower, middle):
                if current_price > current_middle:
                    signal = 'buy'
                    confidence = 0.6
                    reason = "布林带收缩后向上突破"
                else:
                    signal = 'sell' 
                    confidence = 0.6
                    reason = "布林带收缩后向下突破"
            
            return {
                'signal': signal,
                'confidence': confidence,
                'reason': reason,
                'bb_upper': current_upper,
                'bb_middle': current_middle,
                'bb_lower': current_lower,
                'bb_width': bb_width,
                'strategy': self.name
            }
            
        except Exception as e:
            logger.error(f"❌ 布林带策略分析失败: {e}")
            return {'signal': 'hold', 'confidence': 0.0, 'reason': f'分析错误: {e}'}
    
    def _check_squeeze_breakout(self, df: pd.DataFrame, upper: np.ndarray, 
                               lower: np.ndarray, middle: np.ndarray) -> bool:
        """检查布林带收缩突破"""
        try:
            if len(upper) < 10:
                return False
            
            # 计算布林带宽度历史
            width_current = (upper[-1] - lower[-1]) / middle[-1]
            width_avg = np.mean([(upper[i] - lower[i]) / middle[i] for i in range(-10, -1)])
            
            # 当前宽度显著小于历史平均，且价格突破中轨
            current_price = df['close'].iloc[-1]
            prev_price = df['close'].iloc[-2]
            current_middle = middle[-1]
            
            is_squeeze = width_current < width_avg * 0.7
            is_breakout = abs(current_price - current_middle) > abs(prev_price - current_middle)
            
            return is_squeeze and is_breakout
            
        except:
            return False

class StrategyEngine:
    """策略引擎管理器"""
    
    def __init__(self):
        self.strategies = [
            RSIMeanReversionStrategy(),
            MACDTrendStrategy(), 
            BollingerBandsStrategy()
        ]
        logger.info(f"📊 策略引擎初始化，加载 {len(self.strategies)} 个策略")
    
    def analyze_symbol(self, df: pd.DataFrame, symbol: str) -> Dict:
        """分析单个交易对"""
        try:
            all_signals = []
            
            for strategy in self.strategies:
                signal_data = strategy.analyze(df, symbol)
                signal_data['timestamp'] = datetime.now()
                all_signals.append(signal_data)
            
            # 合成最终信号
            final_signal = self._combine_signals(all_signals)
            
            return {
                'symbol': symbol,
                'timestamp': datetime.now(),
                'final_signal': final_signal,
                'individual_signals': all_signals,
                'price': df['close'].iloc[-1]
            }
            
        except Exception as e:
            logger.error(f"❌ 策略分析失败 {symbol}: {e}")
            return {
                'symbol': symbol,
                'final_signal': {'signal': 'hold', 'confidence': 0.0, 'reason': f'分析错误: {e}'},
                'individual_signals': []
            }
    
    def _combine_signals(self, signals: List[Dict]) -> Dict:
        """合成多个策略信号"""
        try:
            buy_votes = 0
            sell_votes = 0
            total_confidence = 0
            reasons = []
            
            for signal_data in signals:
                signal = signal_data.get('signal', 'hold')
                confidence = signal_data.get('confidence', 0.0)
                reason = signal_data.get('reason', '')
                
                if signal == 'buy':
                    buy_votes += confidence
                    reasons.append(f"✅ {reason} ({confidence:.1%})")
                elif signal == 'sell':
                    sell_votes += confidence
                    reasons.append(f"❌ {reason} ({confidence:.1%})")
                else:
                    reasons.append(f"⏸️ {reason}")
                
                total_confidence += confidence
            
            # 决定最终信号
            if buy_votes > sell_votes and buy_votes > 0.5:
                final_signal = 'buy'
                final_confidence = min(0.9, buy_votes / len(signals))
            elif sell_votes > buy_votes and sell_votes > 0.5:
                final_signal = 'sell'
                final_confidence = min(0.9, sell_votes / len(signals))
            else:
                final_signal = 'hold'
                final_confidence = 0.0
            
            return {
                'signal': final_signal,
                'confidence': final_confidence,
                'reason': ' | '.join(reasons[:3]),  # 只显示前3个原因
                'buy_votes': buy_votes,
                'sell_votes': sell_votes,
                'total_strategies': len(signals)
            }
            
        except Exception as e:
            logger.error(f"❌ 信号合成失败: {e}")
            return {'signal': 'hold', 'confidence': 0.0, 'reason': f'合成错误: {e}'}

if __name__ == "__main__":
    # 测试策略引擎
    engine = StrategyEngine()
    
    # 生成测试数据
    dates = pd.date_range(start='2024-01-01', periods=100, freq='1H')
    np.random.seed(42)
    prices = 45000 + np.cumsum(np.random.randn(100) * 100)
    
    df = pd.DataFrame({
        'timestamp': dates,
        'open': prices,
        'high': prices * (1 + np.random.uniform(0, 0.01, 100)),
        'low': prices * (1 - np.random.uniform(0, 0.01, 100)),
        'close': prices,
        'volume': np.random.uniform(100, 1000, 100)
    })
    
    # 测试策略分析
    result = engine.analyze_symbol(df, 'BTC/USDT')
    
    print("📊 策略引擎测试结果:")
    print(f"💱 交易对: {result['symbol']}")
    print(f"💰 价格: ${result['price']:.2f}")
    print(f"🎯 最终信号: {result['final_signal']['signal'].upper()}")
    print(f"📈 置信度: {result['final_signal']['confidence']:.1%}")
    print(f"📝 原因: {result['final_signal']['reason']}")