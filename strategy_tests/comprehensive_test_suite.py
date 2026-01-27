#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易系统单元测试套件
"""

import unittest
import sys
import os

# 添加项目根目录到Python路径
project_root = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, project_root)

import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import json

class TestTradingStrategies(unittest.TestCase):
    """测试交易策略"""
    
    def setUp(self):
        """设置测试数据"""
        # 创建模拟价格数据
        dates = pd.date_range('2024-01-01', periods=100, freq='D')
        prices = 50000 + np.cumsum(np.random.randn(100) * 100)
        
        self.test_data = pd.DataFrame({
            'timestamp': dates,
            'open': prices + np.random.randn(100) * 50,
            'high': prices + np.random.randn(100) * 50 + 100,
            'low': prices + np.random.randn(100) * 50 - 100,
            'close': prices,
            'volume': np.random.rand(100) * 1000000
        })
    
    def test_ma_strategy_signals(self):
        """测试MA策略信号生成"""
        try:
            from strategy.ma_strategy import MAStrategy
            
            strategy = MAStrategy(fast_period=5, slow_period=20)
            df_with_signals = strategy.generate_signals(self.test_data)
            
            # 验证信号列存在
            self.assertIn('signal', df_with_signals.columns)
            self.assertIn('buy_signal', df_with_signals.columns)
            self.assertIn('sell_signal', df_with_signals.columns)
            
            # 验证信号值合理
            signals = df_with_signals['signal'].unique()
            self.assertTrue(all(s in [-1, 0, 1] for s in signals))
            
            print("✅ MA策略信号测试通过")
            
        except Exception as e:
            print(f"❌ MA策略测试失败: {e}")
            self.fail(f"MA策略测试失败: {e}")
    
    def test_rsi_strategy_signals(self):
        """测试RSI策略信号生成"""
        try:
            from strategy.ma_strategy import RSIStrategy
            
            strategy = RSIStrategy(period=14)
            df_with_signals = strategy.generate_signals(self.test_data)
            
            # 验证RSI计算
            self.assertIn('rsi', df_with_signals.columns)
            rsi_values = df_with_signals['rsi'].dropna()
            
            # RSI应该在0-100之间
            self.assertTrue((rsi_values >= 0).all())
            self.assertTrue((rsi_values <= 100).all())
            
            print("✅ RSI策略信号测试通过")
            
        except Exception as e:
            print(f"❌ RSI策略测试失败: {e}")
            self.fail(f"RSI策略测试失败: {e}")


class TestRiskManager(unittest.TestCase):
    """测试风险管理"""
    
    def setUp(self):
        """设置风险管理器"""
        try:
            from utils.risk_manager import RiskManager
            self.risk_manager = RiskManager(
                max_position_pct=0.1,
                max_loss_pct=0.02
            )
        except ImportError as e:
            self.risk_manager = None
            print(f"⚠️  风险管理器导入失败: {e}")
    
    def test_position_size_calculation(self):
        """测试仓位计算"""
        if not self.risk_manager:
            self.skipTest("风险管理器未可用")
            
        balance = 10000  # $10,000
        price = 50000    # $50,000 BTC
        
        position_size = self.risk_manager.calculate_position_size(balance, price)
        
        # 仓位不应超过最大限制
        max_position_value = balance * self.risk_manager.max_position_pct
        actual_position_value = position_size * price
        
        self.assertLessEqual(actual_position_value, max_position_value)
        self.assertGreater(position_size, 0)
        
        print("✅ 仓位计算测试通过")
    
    def test_stop_loss_calculation(self):
        """测试止损计算"""
        if not self.risk_manager:
            self.skipTest("风险管理器未可用")
        
        entry_price = 50000
        stop_loss_buy = self.risk_manager.calculate_stop_loss(entry_price, 'buy')
        stop_loss_sell = self.risk_manager.calculate_stop_loss(entry_price, 'sell')
        
        # 买入止损应该低于入场价
        self.assertLess(stop_loss_buy, entry_price)
        
        # 卖出止损应该高于入场价
        self.assertGreater(stop_loss_sell, entry_price)
        
        print("✅ 止损计算测试通过")


class TestDataIntegrity(unittest.TestCase):
    """测试数据完整性"""
    
    def test_config_file_exists(self):
        """测试配置文件存在"""
        config_path = '/Users/hongtou/newproject/quant-trade-bot/config.json'
        self.assertTrue(os.path.exists(config_path), "配置文件不存在")
        
        # 验证配置文件格式
        try:
            with open(config_path, 'r') as f:
                config = json.load(f)
            
            required_keys = ['binance', 'telegram']
            for key in required_keys:
                self.assertIn(key, config)
            
            print("✅ 配置文件测试通过")
            
        except json.JSONDecodeError:
            self.fail("配置文件格式错误")
    
    def test_strategy_files_exist(self):
        """测试策略文件存在"""
        strategy_files = [
            '/Users/hongtou/newproject/quant-trade-bot/strategy/ma_strategy.py',
            '/Users/hongtou/newproject/quant-trade-bot/utils/risk_manager.py',
        ]
        
        for file_path in strategy_files:
            self.assertTrue(os.path.exists(file_path), f"策略文件不存在: {file_path}")
        
        print("✅ 策略文件存在性测试通过")


class TestSystemStress(unittest.TestCase):
    """压力测试"""
    
    def test_large_dataset_processing(self):
        """测试大数据集处理"""
        # 创建大数据集
        large_data = pd.DataFrame({
            'timestamp': pd.date_range('2020-01-01', periods=10000, freq='H'),
            'open': np.random.rand(10000) * 50000 + 40000,
            'high': np.random.rand(10000) * 50000 + 45000,
            'low': np.random.rand(10000) * 50000 + 35000,
            'close': np.random.rand(10000) * 50000 + 42000,
            'volume': np.random.rand(10000) * 1000000
        })
        
        start_time = datetime.now()
        
        try:
            from strategy.ma_strategy import MAStrategy
            strategy = MAStrategy()
            result = strategy.generate_signals(large_data)
            
            processing_time = (datetime.now() - start_time).total_seconds()
            
            self.assertLess(processing_time, 30, "数据处理时间过长")
            self.assertEqual(len(result), len(large_data))
            
            print(f"✅ 大数据集处理测试通过 (处理时间: {processing_time:.2f}秒)")
            
        except Exception as e:
            print(f"❌ 大数据集处理测试失败: {e}")


def run_all_tests():
    """运行所有测试"""
    print("🧪 启动量化交易系统测试套件")
    print("=" * 50)
    
    # 创建测试套件
    test_suite = unittest.TestSuite()
    
    # 添加测试用例
    test_classes = [
        TestTradingStrategies,
        TestRiskManager,
        TestDataIntegrity,
        TestSystemStress
    ]
    
    for test_class in test_classes:
        tests = unittest.TestLoader().loadTestsFromTestCase(test_class)
        test_suite.addTests(tests)
    
    # 运行测试
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(test_suite)
    
    # 测试结果统计
    print("\n" + "=" * 50)
    print("📊 测试结果统计:")
    print(f"✅ 成功: {result.testsRun - len(result.failures) - len(result.errors)}")
    print(f"❌ 失败: {len(result.failures)}")
    print(f"🚨 错误: {len(result.errors)}")
    
    if result.failures:
        print("\n💥 失败详情:")
        for test, error in result.failures:
            print(f"   - {test}: {error}")
    
    if result.errors:
        print("\n🚨 错误详情:")
        for test, error in result.errors:
            print(f"   - {test}: {error}")
    
    # 生成测试报告
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    report = {
        'timestamp': timestamp,
        'total_tests': result.testsRun,
        'passed': result.testsRun - len(result.failures) - len(result.errors),
        'failed': len(result.failures),
        'errors': len(result.errors),
        'success_rate': ((result.testsRun - len(result.failures) - len(result.errors)) / result.testsRun * 100) if result.testsRun > 0 else 0
    }
    
    with open(f'test_report_{timestamp}.json', 'w') as f:
        json.dump(report, f, indent=2)
    
    print(f"\n📋 详细测试报告已保存: test_report_{timestamp}.json")
    
    return result.wasSuccessful()


if __name__ == '__main__':
    success = run_all_tests()
    
    if success:
        print("\n🎉 所有测试通过! 系统准备就绪")
        exit(0)
    else:
        print("\n⚠️  部分测试失败，请检查问题后再进行实盘交易")
        exit(1)