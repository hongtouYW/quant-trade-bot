import ccxt
import pandas as pd
import numpy as np
from datetime import datetime, timedelta

class DataLoader:
    """行情数据加载器"""
    
    def __init__(self, exchange_name, api_key=None, api_secret=None):
        exchange_class = getattr(ccxt, exchange_name)
        self.exchange = exchange_class({
            'apiKey': api_key,
            'secret': api_secret,
            'enableRateLimit': True,
            'options': {'defaultType': 'spot'}
        })
    
    def fetch_ohlcv(self, symbol, timeframe='1h', limit=100):
        """获取K线数据"""
        ohlcv = self.exchange.fetch_ohlcv(symbol, timeframe=timeframe, limit=limit)
        df = pd.DataFrame(ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
        df['timestamp'] = pd.to_datetime(df['timestamp'], unit='ms')
        df.set_index('timestamp', inplace=True)
        return df
    
    def fetch_ticker(self, symbol):
        """获取当前价格"""
        ticker = self.exchange.fetch_ticker(symbol)
        return ticker
    
    def fetch_balance(self):
        """获取账户余额"""
        balance = self.exchange.fetch_balance()
        return balance
    
    def calculate_ma(self, df, period):
        """计算移动平均线"""
        return df['close'].rolling(window=period).mean()
    
    def calculate_ema(self, df, period):
        """计算指数移动平均线"""
        return df['close'].ewm(span=period, adjust=False).mean()
    
    def calculate_rsi(self, df, period=14):
        """计算RSI指标"""
        delta = df['close'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=period).mean()
        rs = gain / loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def calculate_macd(self, df, fast=12, slow=26, signal=9):
        """计算MACD指标"""
        ema_fast = df['close'].ewm(span=fast, adjust=False).mean()
        ema_slow = df['close'].ewm(span=slow, adjust=False).mean()
        macd_line = ema_fast - ema_slow
        signal_line = macd_line.ewm(span=signal, adjust=False).mean()
        histogram = macd_line - signal_line
        return macd_line, signal_line, histogram
    
    def calculate_bollinger_bands(self, df, period=20, std_dev=2):
        """计算布林带"""
        ma = df['close'].rolling(window=period).mean()
        std = df['close'].rolling(window=period).std()
        upper = ma + (std * std_dev)
        lower = ma - (std * std_dev)
        return upper, ma, lower
    
    def calculate_atr(self, df, period=14):
        """计算ATR（平均真实波幅）"""
        high = df['high']
        low = df['low']
        close = df['close'].shift(1)
        tr1 = high - low
        tr2 = abs(high - close)
        tr3 = abs(low - close)
        tr = pd.concat([tr1, tr2, tr3], axis=1).max(axis=1)
        atr = tr.rolling(window=period).mean()
        return atr
    
    def add_all_indicators(self, df):
        """添加所有常用指标"""
        df['ma5'] = self.calculate_ma(df, 5)
        df['ma10'] = self.calculate_ma(df, 10)
        df['ma20'] = self.calculate_ma(df, 20)
        df['ma60'] = self.calculate_ma(df, 60)
        df['ema12'] = self.calculate_ema(df, 12)
        df['ema26'] = self.calculate_ema(df, 26)
        df['rsi'] = self.calculate_rsi(df, 14)
        df['macd'], df['macd_signal'], df['macd_hist'] = self.calculate_macd(df)
        df['bb_upper'], df['bb_mid'], df['bb_lower'] = self.calculate_bollinger_bands(df)
        df['atr'] = self.calculate_atr(df)
        return df


# 测试代码
if __name__ == "__main__":
    import json
    with open('../config.json', 'r') as f:
        config = json.load(f)
    
    loader = DataLoader('binance', config['binance']['api_key'], config['binance']['api_secret'])
    df = loader.fetch_ohlcv('BTC/USDT', '1h', 100)
    df = loader.add_all_indicators(df)
    print(df.tail())
