import pandas as pd
import numpy as np

class MomentumBreakoutStrategy:
    """动量突破策略 - 捕捉强势趋势"""
    
    def __init__(self, lookback=20, volume_threshold=1.5, atr_multiplier=2.0):
        """
        :param lookback: 回看周期
        :param volume_threshold: 成交量突破倍数
        :param atr_multiplier: ATR止损倍数
        """
        self.lookback = lookback
        self.volume_threshold = volume_threshold
        self.atr_multiplier = atr_multiplier
        self.position = 0
        self.entry_price = 0
        self.stop_loss_price = 0
        self.trailing_stop = 0
        
    def calculate_indicators(self, df):
        """计算技术指标"""
        df = df.copy()
        
        # 计算价格通道
        df['high_channel'] = df['high'].rolling(self.lookback).max()
        df['low_channel'] = df['low'].rolling(self.lookback).min()
        df['mid_channel'] = (df['high_channel'] + df['low_channel']) / 2
        
        # 计算ATR (真实波幅)
        high_low = df['high'] - df['low']
        high_close = np.abs(df['high'] - df['close'].shift())
        low_close = np.abs(df['low'] - df['close'].shift())
        
        ranges = pd.concat([high_low, high_close, low_close], axis=1)
        true_range = ranges.max(axis=1)
        df['atr'] = true_range.rolling(14).mean()
        
        # 计算成交量指标
        df['volume_ma'] = df['volume'].rolling(20).mean()
        df['volume_ratio'] = df['volume'] / df['volume_ma']
        
        # 计算动量指标
        df['momentum'] = df['close'] / df['close'].shift(10) - 1
        df['roc'] = (df['close'] - df['close'].shift(12)) / df['close'].shift(12) * 100
        
        # 计算MACD
        exp1 = df['close'].ewm(span=12).mean()
        exp2 = df['close'].ewm(span=26).mean()
        df['macd'] = exp1 - exp2
        df['macd_signal'] = df['macd'].ewm(span=9).mean()
        df['macd_hist'] = df['macd'] - df['macd_signal']
        
        return df
    
    def generate_signals(self, df):
        """生成交易信号"""
        df = self.calculate_indicators(df)
        
        # 向上突破信号
        bullish_breakout = (
            (df['close'] > df['high_channel'].shift(1)) &  # 突破前期高点
            (df['volume_ratio'] > self.volume_threshold) &  # 成交量放大
            (df['momentum'] > 0.02) &  # 动量强劲
            (df['macd'] > df['macd_signal']) &  # MACD金叉
            (df['roc'] > 2)  # 价格变化率 > 2%
        )
        
        # 向下突破信号
        bearish_breakout = (
            (df['close'] < df['low_channel'].shift(1)) &  # 跌破前期低点
            (df['volume_ratio'] > self.volume_threshold) &  # 成交量放大
            (df['momentum'] < -0.02) &  # 动量疲弱
            (df['macd'] < df['macd_signal']) &  # MACD死叉
            (df['roc'] < -2)  # 价格变化率 < -2%
        )
        
        df['bull_breakout'] = bullish_breakout
        df['bear_breakout'] = bearish_breakout
        
        return df
    
    def check_signal(self, df):
        """检查当前信号"""
        if len(df) < self.lookback + 15:
            return None, ""
        
        latest = df.iloc[-1]
        current_price = latest['close']
        current_atr = latest['atr']
        
        # 已有仓位的管理
        if self.position != 0:
            # 动态止损（移动止损）
            if self.position > 0:  # 多头仓位
                # 更新移动止损
                new_trailing_stop = current_price - current_atr * self.atr_multiplier
                if new_trailing_stop > self.trailing_stop:
                    self.trailing_stop = new_trailing_stop
                
                # 检查止损
                if current_price <= self.trailing_stop:
                    return 'sell', f"移动止损触发 @{self.trailing_stop:.2f}"
                
                # 检查趋势反转
                if latest['bear_breakout']:
                    return 'sell', f"趋势反转信号"
                    
            elif self.position < 0:  # 空头仓位
                # 更新移动止损
                new_trailing_stop = current_price + current_atr * self.atr_multiplier
                if new_trailing_stop < self.trailing_stop:
                    self.trailing_stop = new_trailing_stop
                
                # 检查止损
                if current_price >= self.trailing_stop:
                    return 'buy', f"移动止损触发 @{self.trailing_stop:.2f}"
                
                # 检查趋势反转
                if latest['bull_breakout']:
                    return 'buy', f"趋势反转信号"
        
        # 新开仓信号
        if self.position == 0:
            if latest['bull_breakout']:
                return 'buy', f"向上突破 (价格: {current_price:.2f}, 成交量比: {latest['volume_ratio']:.1f}x)"
            elif latest['bear_breakout']:
                return 'sell', f"向下突破 (价格: {current_price:.2f}, 成交量比: {latest['volume_ratio']:.1f}x)"
        
        return None, ""
    
    def update_position(self, signal, price, atr):
        """更新仓位"""
        if signal == 'buy':
            if self.position <= 0:
                self.position = 1
                self.entry_price = price
                self.stop_loss_price = price - atr * self.atr_multiplier
                self.trailing_stop = self.stop_loss_price
        elif signal == 'sell':
            if self.position >= 0:
                self.position = -1
                self.entry_price = price
                self.stop_loss_price = price + atr * self.atr_multiplier
                self.trailing_stop = self.stop_loss_price
    
    def get_status(self):
        """获取策略状态"""
        pnl = 0
        if self.position != 0 and self.entry_price != 0:
            pnl = (self.trailing_stop - self.entry_price) / self.entry_price * self.position
            
        return {
            'strategy': 'Momentum Breakout',
            'position': self.position,
            'entry_price': self.entry_price,
            'trailing_stop': self.trailing_stop,
            'unrealized_pnl': f"{pnl:.2%}" if pnl else "0%",
            'lookback_period': self.lookback,
            'atr_multiplier': self.atr_multiplier
        }