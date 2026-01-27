import pandas as pd
import numpy as np
from scipy import stats
import warnings
warnings.filterwarnings('ignore')

class PairsTradingStrategy:
    """配对交易策略 - 市场中性策略"""
    
    def __init__(self, symbol1='BTC/USDT', symbol2='ETH/USDT', 
                 lookback=60, entry_threshold=2.0, exit_threshold=0.5):
        """
        :param symbol1: 交易对1
        :param symbol2: 交易对2  
        :param lookback: 协整关系计算周期
        :param entry_threshold: 入场Z-Score阈值
        :param exit_threshold: 出场Z-Score阈值
        """
        self.symbol1 = symbol1
        self.symbol2 = symbol2
        self.lookback = lookback
        self.entry_threshold = entry_threshold
        self.exit_threshold = exit_threshold
        
        self.position1 = 0  # symbol1的仓位
        self.position2 = 0  # symbol2的仓位
        self.entry_ratio = 0  # 入场时的比价
        self.hedge_ratio = 1  # 对冲比例
        
        self.total_trades = 0
        self.winning_trades = 0
        
    def calculate_spread(self, price1, price2):
        """计算价差和Z-Score"""
        # 计算对数价格比
        log_ratio = np.log(price1 / price2)
        
        # 计算移动均值和标准差
        ratio_mean = log_ratio.rolling(self.lookback).mean()
        ratio_std = log_ratio.rolling(self.lookback).std()
        
        # 计算Z-Score
        zscore = (log_ratio - ratio_mean) / ratio_std
        
        return log_ratio, zscore, ratio_mean, ratio_std
    
    def calculate_cointegration(self, price1, price2):
        """计算协整关系"""
        if len(price1) < self.lookback:
            return None, None
        
        # 取最近的数据
        p1 = price1.iloc[-self.lookback:].values
        p2 = price2.iloc[-self.lookback:].values
        
        # 线性回归求对冲比例
        slope, intercept, r_value, p_value, std_err = stats.linregress(p2, p1)
        
        # 计算残差
        residuals = p1 - (slope * p2 + intercept)
        
        return slope, residuals
    
    def generate_signals(self, data1, data2):
        """
        生成配对交易信号
        :param data1: symbol1的价格数据
        :param data2: symbol2的价格数据
        :return: 信号字典
        """
        if len(data1) != len(data2) or len(data1) < self.lookback + 1:
            return None
        
        # 使用收盘价
        price1 = data1['close']
        price2 = data2['close']
        
        # 计算价差和Z-Score
        log_ratio, zscore, ratio_mean, ratio_std = self.calculate_spread(price1, price2)
        
        # 计算协整关系
        hedge_ratio, residuals = self.calculate_cointegration(price1, price2)
        
        if hedge_ratio is None:
            return None
        
        current_zscore = zscore.iloc[-1]
        current_price1 = price1.iloc[-1]
        current_price2 = price2.iloc[-1]
        
        signals = []
        
        # 当前无仓位，寻找入场机会
        if self.position1 == 0 and self.position2 == 0:
            
            # Z-Score > 入场阈值：symbol1相对贵，做空symbol1，做多symbol2
            if current_zscore > self.entry_threshold:
                signals.append({
                    'symbol': self.symbol1,
                    'action': 'sell',
                    'reason': f'价差过高 Z-Score: {current_zscore:.2f}',
                    'zscore': current_zscore
                })
                signals.append({
                    'symbol': self.symbol2, 
                    'action': 'buy',
                    'reason': f'配对买入 对冲比例: {hedge_ratio:.3f}',
                    'zscore': current_zscore
                })
                
                self.position1 = -1
                self.position2 = 1
                self.hedge_ratio = hedge_ratio
                self.entry_ratio = current_price1 / current_price2
                
            # Z-Score < -入场阈值：symbol1相对便宜，做多symbol1，做空symbol2
            elif current_zscore < -self.entry_threshold:
                signals.append({
                    'symbol': self.symbol1,
                    'action': 'buy', 
                    'reason': f'价差过低 Z-Score: {current_zscore:.2f}',
                    'zscore': current_zscore
                })
                signals.append({
                    'symbol': self.symbol2,
                    'action': 'sell',
                    'reason': f'配对卖出 对冲比例: {hedge_ratio:.3f}',
                    'zscore': current_zscore
                })
                
                self.position1 = 1
                self.position2 = -1  
                self.hedge_ratio = hedge_ratio
                self.entry_ratio = current_price1 / current_price2
        
        # 已有仓位，寻找出场机会
        else:
            # 价差回归，平仓
            if abs(current_zscore) < self.exit_threshold:
                # 平仓symbol1
                if self.position1 > 0:
                    signals.append({
                        'symbol': self.symbol1,
                        'action': 'sell',
                        'reason': f'价差回归平仓 Z-Score: {current_zscore:.2f}',
                        'zscore': current_zscore
                    })
                elif self.position1 < 0:
                    signals.append({
                        'symbol': self.symbol1,
                        'action': 'buy',
                        'reason': f'价差回归平仓 Z-Score: {current_zscore:.2f}',
                        'zscore': current_zscore
                    })
                
                # 平仓symbol2
                if self.position2 > 0:
                    signals.append({
                        'symbol': self.symbol2,
                        'action': 'sell',
                        'reason': f'配对平仓',
                        'zscore': current_zscore
                    })
                elif self.position2 < 0:
                    signals.append({
                        'symbol': self.symbol2,
                        'action': 'buy',
                        'reason': f'配对平仓',
                        'zscore': current_zscore
                    })
                
                # 计算交易结果
                current_ratio = current_price1 / current_price2
                if self.entry_ratio != 0:
                    pnl = (current_ratio - self.entry_ratio) / self.entry_ratio * self.position1
                    if pnl > 0:
                        self.winning_trades += 1
                    self.total_trades += 1
                
                # 重置仓位
                self.position1 = 0
                self.position2 = 0
                self.entry_ratio = 0
            
            # 止损：价差继续扩大
            elif (self.position1 > 0 and current_zscore < -3) or \
                 (self.position1 < 0 and current_zscore > 3):
                
                signals.append({
                    'symbol': self.symbol1,
                    'action': 'sell' if self.position1 > 0 else 'buy',
                    'reason': f'止损 Z-Score: {current_zscore:.2f}',
                    'zscore': current_zscore
                })
                signals.append({
                    'symbol': self.symbol2,
                    'action': 'sell' if self.position2 > 0 else 'buy', 
                    'reason': f'配对止损',
                    'zscore': current_zscore
                })
                
                # 记录亏损交易
                self.total_trades += 1
                
                # 重置仓位
                self.position1 = 0
                self.position2 = 0
                self.entry_ratio = 0
        
        return {
            'signals': signals,
            'zscore': current_zscore,
            'hedge_ratio': hedge_ratio,
            'spread_info': {
                'current_ratio': current_price1 / current_price2,
                'entry_ratio': self.entry_ratio,
                'mean_ratio': np.exp(ratio_mean.iloc[-1]) if not pd.isna(ratio_mean.iloc[-1]) else None
            }
        }
    
    def get_status(self):
        """获取策略状态"""
        win_rate = (self.winning_trades / self.total_trades * 100) if self.total_trades > 0 else 0
        
        return {
            'strategy': 'Pairs Trading',
            'pair': f"{self.symbol1} / {self.symbol2}",
            'position1': self.position1,
            'position2': self.position2,
            'hedge_ratio': self.hedge_ratio,
            'entry_ratio': self.entry_ratio,
            'total_trades': self.total_trades,
            'win_rate': f"{win_rate:.1f}%",
            'entry_threshold': self.entry_threshold,
            'exit_threshold': self.exit_threshold
        }