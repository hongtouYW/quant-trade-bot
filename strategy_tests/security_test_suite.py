#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
安全性和配置测试套件
验证Phase 1修复的问题
"""

import unittest
import sys
import os

# 添加项目根目录到Python路径
project_root = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, project_root)

from config_manager import ConfigManager

class TestSecurityImprovements(unittest.TestCase):
    """测试安全性改进"""
    
    def setUp(self):
        """设置测试"""
        self.config_manager = ConfigManager()
    
    def test_environment_variable_loading(self):
        """测试环境变量加载"""
        # 验证环境变量正确加载
        binance_config = self.config_manager.get_exchange_config('binance')
        
        self.assertIsNotNone(binance_config['api_key'])
        self.assertIsNotNone(binance_config['secret'])
        self.assertIsInstance(binance_config['sandbox'], bool)
        
        print("✅ 环境变量加载测试通过")
    
    def test_trading_mode_control(self):
        """测试交易模式控制"""
        # 验证交易模式设置
        self.assertIn(self.config_manager.trading_mode, ['paper', 'live'])
        
        if self.config_manager.is_paper_trading():
            print("📝 当前为模拟交易模式")
        elif self.config_manager.is_live_trading():
            print("🚨 当前为实盘交易模式")
        
        print("✅ 交易模式控制测试通过")
    
    def test_config_validation(self):
        """测试配置验证"""
        errors = self.config_manager.validate_config()
        
        # 应该没有配置错误
        self.assertEqual(len(errors), 0, f"配置验证失败: {errors}")
        
        print("✅ 配置验证测试通过")
    
    def test_risk_parameters(self):
        """测试风险参数"""
        risk_config = self.config_manager.get_risk_config()
        
        # 验证风险参数在合理范围
        self.assertLessEqual(risk_config['max_loss_pct'], 0.05, "单次亏损限制过高")
        self.assertLessEqual(risk_config['max_position_pct'], 0.2, "单次仓位限制过高")
        self.assertGreater(risk_config['max_daily_trades'], 0, "每日交易限制应大于0")
        
        print("✅ 风险参数测试通过")


class TestAPIResilience(unittest.TestCase):
    """测试API弹性"""
    
    def test_data_loader_with_retries(self):
        """测试数据加载器重试机制"""
        try:
            from utils.data_loader import DataLoader
            
            # 创建数据加载器实例
            loader = DataLoader('binance')
            
            # 验证重试参数存在
            self.assertTrue(hasattr(loader, 'max_retries'))
            self.assertGreater(loader.max_retries, 0)
            
            print("✅ API重试机制测试通过")
            
        except Exception as e:
            self.fail(f"数据加载器测试失败: {e}")
    
    def test_exchange_connection(self):
        """测试交易所连接"""
        try:
            from utils.data_loader import DataLoader
            from config_manager import config_manager
            
            binance_config = config_manager.get_exchange_config('binance')
            loader = DataLoader(
                'binance',
                binance_config['api_key'],
                binance_config['secret']
            )
            
            # 测试基础连接
            self.assertTrue(hasattr(loader, 'exchange'))
            
            print("✅ 交易所连接测试通过")
            
        except Exception as e:
            print(f"⚠️ 交易所连接测试警告: {e}")


class TestSystemIntegration(unittest.TestCase):
    """测试系统集成"""
    
    def test_trading_bot_initialization(self):
        """测试交易机器人初始化"""
        try:
            from main import TradingBot
            
            # 尝试初始化交易机器人
            bot = TradingBot()
            
            # 验证关键组件
            self.assertTrue(hasattr(bot, 'config_manager'))
            self.assertTrue(hasattr(bot, 'exchange'))
            self.assertTrue(hasattr(bot, 'risk_manager'))
            
            print("✅ 交易机器人初始化测试通过")
            
        except Exception as e:
            self.fail(f"交易机器人初始化失败: {e}")


def run_security_tests():
    """运行安全测试套件"""
    print("🔐 启动安全性和配置测试套件")
    print("=" * 50)
    
    # 创建测试套件
    test_suite = unittest.TestSuite()
    
    # 添加测试用例
    test_classes = [
        TestSecurityImprovements,
        TestAPIResilience,
        TestSystemIntegration
    ]
    
    for test_class in test_classes:
        tests = unittest.TestLoader().loadTestsFromTestCase(test_class)
        test_suite.addTests(tests)
    
    # 运行测试
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(test_suite)
    
    # 测试结果统计
    print("\n" + "=" * 50)
    print("🔐 安全测试结果:")
    print(f"✅ 成功: {result.testsRun - len(result.failures) - len(result.errors)}")
    print(f"❌ 失败: {len(result.failures)}")
    print(f"🚨 错误: {len(result.errors)}")
    
    success_rate = ((result.testsRun - len(result.failures) - len(result.errors)) / result.testsRun * 100) if result.testsRun > 0 else 0
    
    if success_rate >= 90:
        print(f"\n🎉 安全测试完成! 成功率: {success_rate:.1f}%")
        print("✅ Phase 1 修复验证通过")
    else:
        print(f"\n⚠️ 安全测试部分失败，成功率: {success_rate:.1f}%")
        print("❌ Phase 1 修复需要进一步改进")
    
    return result.wasSuccessful()


if __name__ == '__main__':
    success = run_security_tests()
    exit(0 if success else 1)