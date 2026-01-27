import pandas as pd
import numpy as np
from scipy import stats

class MeanReversionStrategy:
    """均值回归策略 - 基于价格偏离均值的回归特性"""
    
    def __init__(self, period=20, zscore_threshold=2.0, min_volume_ratio=1.2):
        """
        :param period: 均值计算周期
        :param zscore_threshold: Z-Score阈值（标准差倍数）
        :param min_volume_ratio: 最小成交量比率
        """
        self.period = period
        self.zscore_threshold = zscore_threshold
        self.min_volume_ratio = min_volume_ratio
        self.position = 0
        self.entry_price = 0
        self.stop_loss = 0.05  # 5% 止损
        self.take_profit = 0.03  # 3% 止盈
        
    def calculate_indicators(self, df):
        """计算技术指标"""
        df = df.copy()
        
        # 计算移动平均和标准差
        df['mean'] = df['close'].rolling(self.period).mean()
        df['std'] = df['close'].rolling(self.period).std()
        
        # 计算Z-Score
        df['zscore'] = (df['close'] - df['mean']) / df['std']
        
        # 计算成交量比率
        df['volume_ratio'] = df['volume'] / df['volume'].rolling(self.period).mean()
        
        # 计算RSI辅助判断
        df['rsi'] = self.calculate_rsi(df['close'], 14)
        
        return df
    
    def calculate_rsi(self, prices, period=14):
        """计算RSI指标"""
        delta = prices.diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
        rs = gain / loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def generate_signals(self, df):
        """生成交易信号"""
        df = self.calculate_indicators(df)
        
        # 信号条件
        # 买入信号：价格严重低估 + 成交量放大 + RSI超卖
        buy_condition = (
            (df['zscore'] < -self.zscore_threshold) &
            (df['volume_ratio'] > self.min_volume_ratio) &
            (df['rsi'] < 30)
        )
        
        # 卖出信号：价格严重高估 + 成交量放大 + RSI超买
        sell_condition = (
            (df['zscore'] > self.zscore_threshold) &
            (df['volume_ratio'] > self.min_volume_ratio) &
            (df['rsi'] > 70)
        )
        
        # 回归信号：价格回归均值附近
        close_position_condition = (
            (abs(df['zscore']) < 0.5)
        )
        
        df['buy_signal'] = buy_condition
        df['sell_signal'] = sell_condition
        df['close_signal'] = close_position_condition
        
        return df
    
    def check_signal(self, df):
        """检查当前信号"""
        if len(df) < self.period + 1:
            return None, ""
        
        latest = df.iloc[-1]
        current_price = latest['close']
        
        # 风险管理 - 止损止盈检查
        if self.position != 0:
            pnl_ratio = (current_price - self.entry_price) / self.entry_price
            
            # 多头止损止盈
            if self.position > 0:
                if pnl_ratio <= -self.stop_loss:
                    return 'sell', f"多头止损 {pnl_ratio:.2%}"
                elif pnl_ratio >= self.take_profit:
                    return 'sell', f"多头止盈 {pnl_ratio:.2%}"
            
            # 空头止损止盈
            elif self.position < 0:
                if pnl_ratio >= self.stop_loss:
                    return 'buy', f"空头止损 {pnl_ratio:.2%}"
                elif pnl_ratio <= -self.take_profit:
                    return 'buy', f"空头止盈 {pnl_ratio:.2%}"
            
            # 均值回归平仓
            if latest['close_signal']:
                if self.position > 0:
                    return 'sell', f"价格回归均值，平多仓 (Z-Score: {latest['zscore']:.2f})"
                elif self.position < 0:
                    return 'buy', f"价格回归均值，平空仓 (Z-Score: {latest['zscore']:.2f})"
        
        # 新开仓信号
        if self.position == 0:
            if latest['buy_signal']:
                return 'buy', f"均值回归买入信号 (Z-Score: {latest['zscore']:.2f}, RSI: {latest['rsi']:.1f})"
            elif latest['sell_signal']:
                return 'sell', f"均值回归卖出信号 (Z-Score: {latest['zscore']:.2f}, RSI: {latest['rsi']:.1f})"
        
        return None, ""
    
    def update_position(self, signal, price):
        """更新仓位"""
        if signal == 'buy':
            if self.position <= 0:
                self.position = 1
                self.entry_price = price
        elif signal == 'sell':
            if self.position >= 0:
                self.position = -1
                self.entry_price = price
    
    def get_status(self):
        """获取策略状态"""
        return {
            'strategy': 'Mean Reversion',
            'position': self.position,
            'entry_price': self.entry_price,
            'period': self.period,
            'zscore_threshold': self.zscore_threshold,
            'stop_loss': f"{self.stop_loss:.1%}",
            'take_profit': f"{self.take_profit:.1%}"
        }