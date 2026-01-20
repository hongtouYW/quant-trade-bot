import pandas as pd
import numpy as np

class MAStrategy:
    """均线交叉策略"""
    
    def __init__(self, fast_period=5, slow_period=20):
        self.fast_period = fast_period
        self.slow_period = slow_period
        self.position = 0  # 0: 空仓, 1: 持有
        self.entry_price = 0
    
    def generate_signals(self, df):
        """
        生成交易信号
        :param df: 包含OHLCV数据的DataFrame
        :return: 添加了信号列的DataFrame
        """
        df = df.copy()
        
        # 计算均线
        df['ma_fast'] = df['close'].rolling(window=self.fast_period).mean()
        df['ma_slow'] = df['close'].rolling(window=self.slow_period).mean()
        
        # 生成信号
        df['signal'] = 0
        df.loc[df['ma_fast'] > df['ma_slow'], 'signal'] = 1   # 金叉：买入信号
        df.loc[df['ma_fast'] < df['ma_slow'], 'signal'] = -1  # 死叉：卖出信号
        
        # 交叉点检测
        df['cross'] = df['signal'].diff()
        df['buy_signal'] = df['cross'] == 2   # 从-1变到1
        df['sell_signal'] = df['cross'] == -2  # 从1变到-1
        
        return df
    
    def check_signal(self, df):
        """
        检查最新信号
        :param df: 包含信号的DataFrame
        :return: 信号类型 ('buy', 'sell', None)
        """
        if len(df) < 2:
            return None, ""
        
        latest = df.iloc[-1]
        prev = df.iloc[-2]
        
        if latest['buy_signal']:
            return 'buy', f"MA{self.fast_period}上穿MA{self.slow_period}"
        elif latest['sell_signal']:
            return 'sell', f"MA{self.fast_period}下穿MA{self.slow_period}"
        
        return None, ""


class RSIStrategy:
    """RSI超买超卖策略"""
    
    def __init__(self, period=14, oversold=30, overbought=70):
        self.period = period
        self.oversold = oversold
        self.overbought = overbought
    
    def calculate_rsi(self, df):
        """计算RSI"""
        delta = df['close'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=self.period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=self.period).mean()
        rs = gain / loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def generate_signals(self, df):
        """生成交易信号"""
        df = df.copy()
        df['rsi'] = self.calculate_rsi(df)
        
        df['signal'] = 0
        df.loc[df['rsi'] < self.oversold, 'signal'] = 1   # 超卖：买入
        df.loc[df['rsi'] > self.overbought, 'signal'] = -1  # 超买：卖出
        
        # 信号变化检测
        df['buy_signal'] = (df['rsi'] < self.oversold) & (df['rsi'].shift(1) >= self.oversold)
        df['sell_signal'] = (df['rsi'] > self.overbought) & (df['rsi'].shift(1) <= self.overbought)
        
        return df
    
    def check_signal(self, df):
        """检查最新信号"""
        if len(df) < 2:
            return None, ""
        
        latest = df.iloc[-1]
        
        if latest['buy_signal']:
            return 'buy', f"RSI跌破{self.oversold}超卖区"
        elif latest['sell_signal']:
            return 'sell', f"RSI突破{self.overbought}超买区"
        
        return None, ""


class MACDStrategy:
    """MACD策略"""
    
    def __init__(self, fast=12, slow=26, signal=9):
        self.fast = fast
        self.slow = slow
        self.signal_period = signal
    
    def generate_signals(self, df):
        """生成交易信号"""
        df = df.copy()
        
        # 计算MACD
        ema_fast = df['close'].ewm(span=self.fast, adjust=False).mean()
        ema_slow = df['close'].ewm(span=self.slow, adjust=False).mean()
        df['macd'] = ema_fast - ema_slow
        df['macd_signal'] = df['macd'].ewm(span=self.signal_period, adjust=False).mean()
        df['macd_hist'] = df['macd'] - df['macd_signal']
        
        # 金叉死叉
        df['signal'] = 0
        df.loc[df['macd'] > df['macd_signal'], 'signal'] = 1
        df.loc[df['macd'] < df['macd_signal'], 'signal'] = -1
        
        df['cross'] = df['signal'].diff()
        df['buy_signal'] = df['cross'] == 2
        df['sell_signal'] = df['cross'] == -2
        
        return df
    
    def check_signal(self, df):
        """检查最新信号"""
        if len(df) < 2:
            return None, ""
        
        latest = df.iloc[-1]
        
        if latest['buy_signal']:
            return 'buy', "MACD金叉"
        elif latest['sell_signal']:
            return 'sell', "MACD死叉"
        
        return None, ""


class CombinedStrategy:
    """组合策略（多指标确认）"""
    
    def __init__(self):
        self.ma_strategy = MAStrategy(fast_period=5, slow_period=20)
        self.rsi_strategy = RSIStrategy(period=14, oversold=30, overbought=70)
        self.macd_strategy = MACDStrategy()
    
    def generate_signals(self, df):
        """生成综合信号"""
        df = df.copy()
        
        # 应用各策略
        df = self.ma_strategy.generate_signals(df)
        df['rsi'] = self.rsi_strategy.calculate_rsi(df)
        
        ema_fast = df['close'].ewm(span=12, adjust=False).mean()
        ema_slow = df['close'].ewm(span=26, adjust=False).mean()
        df['macd'] = ema_fast - ema_slow
        df['macd_signal_line'] = df['macd'].ewm(span=9, adjust=False).mean()
        
        # 综合信号：至少两个指标确认
        df['combined_buy'] = (
            (df['buy_signal']) & 
            ((df['rsi'] < 40) | (df['macd'] > df['macd_signal_line']))
        )
        df['combined_sell'] = (
            (df['sell_signal']) & 
            ((df['rsi'] > 60) | (df['macd'] < df['macd_signal_line']))
        )
        
        return df
    
    def check_signal(self, df):
        """检查最新信号"""
        if len(df) < 2:
            return None, ""
        
        latest = df.iloc[-1]
        
        reasons = []
        if latest.get('combined_buy', False):
            reasons.append("MA金叉")
            if latest['rsi'] < 40:
                reasons.append(f"RSI={latest['rsi']:.1f}")
            if latest['macd'] > latest['macd_signal_line']:
                reasons.append("MACD多头")
            return 'buy', " + ".join(reasons)
        
        if latest.get('combined_sell', False):
            reasons.append("MA死叉")
            if latest['rsi'] > 60:
                reasons.append(f"RSI={latest['rsi']:.1f}")
            if latest['macd'] < latest['macd_signal_line']:
                reasons.append("MACD空头")
            return 'sell', " + ".join(reasons)
        
        return None, ""


# 测试代码
if __name__ == "__main__":
    import sys
    sys.path.append('..')
    from utils.data_loader import DataLoader
    import json
    
    with open('../config.json', 'r') as f:
        config = json.load(f)
    
    loader = DataLoader('binance', config['binance']['api_key'], config['binance']['api_secret'])
    df = loader.fetch_ohlcv('BTC/USDT', '1h', 100)
    
    # 测试均线策略
    ma_strategy = MAStrategy(5, 20)
    df = ma_strategy.generate_signals(df)
    signal, reason = ma_strategy.check_signal(df)
    print(f"MA策略信号: {signal}, 原因: {reason}")
    
    # 测试组合策略
    combined = CombinedStrategy()
    df = combined.generate_signals(df)
    signal, reason = combined.check_signal(df)
    print(f"组合策略信号: {signal}, 原因: {reason}")
