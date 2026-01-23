#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
轻量级本地数据库框架
使用 SQLite + SQLAlchemy ORM
"""

import sqlite3
from sqlalchemy import create_engine, Column, Integer, String, Float, DateTime, Boolean, Text, func
from sqlalchemy.orm import declarative_base, sessionmaker, scoped_session
from datetime import datetime
import json
import os

# 数据库基类
Base = declarative_base()

class TradingDatabase:
    """量化交易数据库管理器"""
    
    def __init__(self, db_path="trading_data.db"):
        self.db_path = db_path
        self.engine = create_engine(f'sqlite:///{db_path}', echo=False)
        
        # 创建会话
        session_factory = sessionmaker(bind=self.engine)
        self.Session = scoped_session(session_factory)
        
        # 创建所有表
        Base.metadata.create_all(self.engine)
        
        print(f"📊 数据库连接成功: {db_path}")
    
    def get_session(self):
        """获取数据库会话"""
        return self.Session()
    
    def close_session(self):
        """关闭会话"""
        self.Session.remove()


# 数据模型定义
class TradeRecord(Base):
    """交易记录表"""
    __tablename__ = 'trade_records'
    
    id = Column(Integer, primary_key=True)
    timestamp = Column(DateTime, default=datetime.now)
    symbol = Column(String(20), nullable=False)
    side = Column(String(10), nullable=False)  # buy/sell
    amount = Column(Float, nullable=False)
    price = Column(Float, nullable=False)
    fee = Column(Float, default=0.0)
    pnl = Column(Float, default=0.0)
    strategy = Column(String(50))
    order_id = Column(String(100))
    status = Column(String(20), default='completed')
    
    def to_dict(self):
        return {
            'id': self.id,
            'timestamp': self.timestamp.isoformat() if self.timestamp else None,
            'symbol': self.symbol,
            'side': self.side,
            'amount': self.amount,
            'price': self.price,
            'fee': self.fee,
            'pnl': self.pnl,
            'strategy': self.strategy,
            'order_id': self.order_id,
            'status': self.status
        }


class MarketData(Base):
    """市场数据表"""
    __tablename__ = 'market_data'
    
    id = Column(Integer, primary_key=True)
    timestamp = Column(DateTime, default=datetime.now)
    symbol = Column(String(20), nullable=False)
    timeframe = Column(String(10), nullable=False)
    open_price = Column(Float)
    high_price = Column(Float)
    low_price = Column(Float)
    close_price = Column(Float)
    volume = Column(Float)
    
    def to_dict(self):
        return {
            'id': self.id,
            'timestamp': self.timestamp.isoformat() if self.timestamp else None,
            'symbol': self.symbol,
            'timeframe': self.timeframe,
            'open': self.open_price,
            'high': self.high_price,
            'low': self.low_price,
            'close': self.close_price,
            'volume': self.volume
        }


class StrategySignal(Base):
    """策略信号表"""
    __tablename__ = 'strategy_signals'
    
    id = Column(Integer, primary_key=True)
    timestamp = Column(DateTime, default=datetime.now)
    symbol = Column(String(20), nullable=False)
    strategy_name = Column(String(50), nullable=False)
    signal_type = Column(String(10), nullable=False)  # buy/sell
    confidence = Column(Float)
    price = Column(Float)
    reason = Column(Text)
    executed = Column(Boolean, default=False)
    
    def to_dict(self):
        return {
            'id': self.id,
            'timestamp': self.timestamp.isoformat() if self.timestamp else None,
            'symbol': self.symbol,
            'strategy_name': self.strategy_name,
            'signal_type': self.signal_type,
            'confidence': self.confidence,
            'price': self.price,
            'reason': self.reason,
            'executed': self.executed
        }


class SystemMetrics(Base):
    """系统指标表"""
    __tablename__ = 'system_metrics'
    
    id = Column(Integer, primary_key=True)
    timestamp = Column(DateTime, default=datetime.now)
    metric_name = Column(String(50), nullable=False)
    metric_value = Column(Float)
    metric_unit = Column(String(20))
    description = Column(Text)
    
    def to_dict(self):
        return {
            'id': self.id,
            'timestamp': self.timestamp.isoformat() if self.timestamp else None,
            'metric_name': self.metric_name,
            'metric_value': self.metric_value,
            'metric_unit': self.metric_unit,
            'description': self.description
        }


class TradingDataManager:
    """交易数据管理器"""
    
    def __init__(self, db_path="trading_data.db"):
        self.db = TradingDatabase(db_path)
    
    def add_trade(self, symbol, side, amount, price, strategy=None, fee=0.0, pnl=0.0):
        """添加交易记录"""
        session = self.db.get_session()
        try:
            trade = TradeRecord(
                symbol=symbol,
                side=side,
                amount=amount,
                price=price,
                strategy=strategy,
                fee=fee,
                pnl=pnl
            )
            session.add(trade)
            session.commit()
            return trade.id
        except Exception as e:
            session.rollback()
            print(f"❌ 添加交易记录失败: {e}")
            return None
        finally:
            session.close()
    
    def get_trades(self, symbol=None, limit=100):
        """获取交易记录"""
        session = self.db.get_session()
        try:
            query = session.query(TradeRecord)
            if symbol:
                query = query.filter(TradeRecord.symbol == symbol)
            
            trades = query.order_by(TradeRecord.timestamp.desc()).limit(limit).all()
            return [trade.to_dict() for trade in trades]
        finally:
            session.close()
    
    def add_signal(self, symbol, strategy_name, signal_type, confidence, price, reason=None):
        """添加策略信号"""
        session = self.db.get_session()
        try:
            signal = StrategySignal(
                symbol=symbol,
                strategy_name=strategy_name,
                signal_type=signal_type,
                confidence=confidence,
                price=price,
                reason=reason
            )
            session.add(signal)
            session.commit()
            return signal.id
        except Exception as e:
            session.rollback()
            print(f"❌ 添加信号记录失败: {e}")
            return None
        finally:
            session.close()
    
    def get_signals(self, symbol=None, executed=None, limit=100):
        """获取策略信号"""
        session = self.db.get_session()
        try:
            query = session.query(StrategySignal)
            if symbol:
                query = query.filter(StrategySignal.symbol == symbol)
            if executed is not None:
                query = query.filter(StrategySignal.executed == executed)
            
            signals = query.order_by(StrategySignal.timestamp.desc()).limit(limit).all()
            return [signal.to_dict() for signal in signals]
        finally:
            session.close()
    
    def add_market_data(self, symbol, timeframe, ohlcv_data):
        """批量添加市场数据"""
        session = self.db.get_session()
        try:
            for row in ohlcv_data.itertuples():
                market_data = MarketData(
                    symbol=symbol,
                    timeframe=timeframe,
                    timestamp=row.Index,
                    open_price=row.open,
                    high_price=row.high,
                    low_price=row.low,
                    close_price=row.close,
                    volume=row.volume
                )
                session.add(market_data)
            
            session.commit()
            print(f"✅ 已添加 {len(ohlcv_data)} 条 {symbol} {timeframe} 数据")
        except Exception as e:
            session.rollback()
            print(f"❌ 添加市场数据失败: {e}")
        finally:
            session.close()
    
    def get_performance_stats(self):
        """获取交易表现统计"""
        session = self.db.get_session()
        try:
            # 总交易次数
            total_trades = session.query(TradeRecord).count()
            
            # 盈利交易
            winning_trades = session.query(TradeRecord).filter(TradeRecord.pnl > 0).count()
            
            # 总盈亏
            total_pnl_result = session.query(func.sum(TradeRecord.pnl)).scalar()
            total_pnl = total_pnl_result if total_pnl_result else 0
            
            # 胜率
            win_rate = (winning_trades / total_trades * 100) if total_trades > 0 else 0
            
            # 平均盈亏
            avg_pnl = total_pnl / total_trades if total_trades > 0 else 0
            
            return {
                'total_trades': total_trades,
                'winning_trades': winning_trades,
                'losing_trades': total_trades - winning_trades,
                'win_rate': win_rate,
                'total_pnl': total_pnl,
                'avg_pnl': avg_pnl
            }
        finally:
            session.close()
    
    def migrate_from_json(self, json_files):
        """从JSON文件迁移数据"""
        for file_path in json_files:
            if not os.path.exists(file_path):
                continue
                
            try:
                with open(file_path, 'r') as f:
                    data = json.load(f)
                
                # 根据文件类型处理
                if 'trade_history' in file_path or 'trading_history' in file_path:
                    self._migrate_trade_history(data)
                elif 'backtest' in file_path:
                    self._migrate_backtest_data(data)
                
                print(f"✅ 已迁移文件: {file_path}")
                
            except Exception as e:
                print(f"❌ 迁移文件失败 {file_path}: {e}")
    
    def _migrate_trade_history(self, data):
        """迁移交易历史数据"""
        if isinstance(data, list):
            for trade in data:
                self.add_trade(
                    symbol=trade.get('symbol', ''),
                    side=trade.get('side', ''),
                    amount=trade.get('amount', 0),
                    price=trade.get('price', 0),
                    fee=trade.get('fee', 0),
                    pnl=trade.get('pnl', 0)
                )
    
    def _migrate_backtest_data(self, data):
        """迁移回测数据"""
        if 'trades' in data:
            for trade in data['trades']:
                self.add_trade(
                    symbol=trade.get('symbol', ''),
                    side=trade.get('action', ''),
                    amount=trade.get('amount', 0),
                    price=trade.get('price', 0),
                    strategy='backtest'
                )


# 全局数据库实例
db_manager = TradingDataManager()

if __name__ == '__main__':
    # 演示数据库功能
    print("🚀 测试本地数据库框架")
    print("=" * 30)
    
    # 添加测试数据
    trade_id = db_manager.add_trade('BTC/USDT', 'buy', 0.1, 95000, 'MA_Strategy', 9.5, 500)
    print(f"📝 添加交易记录 ID: {trade_id}")
    
    signal_id = db_manager.add_signal('BTC/USDT', 'MA_Strategy', 'buy', 0.85, 95000, 'MA金叉信号')
    print(f"📡 添加信号记录 ID: {signal_id}")
    
    # 查询数据
    trades = db_manager.get_trades(limit=5)
    print(f"📊 最近交易: {len(trades)} 条")
    
    signals = db_manager.get_signals(limit=5)
    print(f"📡 最近信号: {len(signals)} 条")
    
    # 统计数据
    stats = db_manager.get_performance_stats()
    print(f"📈 交易统计: {stats}")
    
    print("\n✅ 数据库框架测试完成")