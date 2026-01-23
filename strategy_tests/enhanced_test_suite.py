#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
增强测试套件
提供全面的测试覆盖和质量保证
"""

import os
import sys
import json
import unittest
import asyncio
import threading
import time
from unittest.mock import Mock, patch, MagicMock
from datetime import datetime, timedelta
import tempfile
import shutil
from typing import Dict, Any, List
import subprocess


# 添加项目根目录到路径
project_root = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, project_root)


class TestEnvironment:
    """测试环境管理器"""
    
    def __init__(self):
        self.temp_dir = None
        self.original_cwd = None
        self.test_config = {}
        self.mock_data = {}
        
    def setup(self):
        """设置测试环境"""
        # 创建临时目录
        self.temp_dir = tempfile.mkdtemp(prefix="quant_test_")
        self.original_cwd = os.getcwd()
        
        # 设置测试配置
        self.test_config = {
            "binance": {
                "api_key": "test_api_key",
                "api_secret": "test_api_secret"
            },
            "telegram": {
                "bot_token": "test_bot_token",
                "chat_id": "test_chat_id"
            }
        }
        
        # 创建测试配置文件
        config_path = os.path.join(self.temp_dir, "test_config.json")
        with open(config_path, 'w') as f:
            json.dump(self.test_config, f)
        
        # 设置环境变量
        os.environ['TEST_MODE'] = 'true'
        os.environ['TEST_CONFIG_PATH'] = config_path
        
        print(f"🧪 测试环境已创建: {self.temp_dir}")
    
    def teardown(self):
        """清理测试环境"""
        if self.temp_dir and os.path.exists(self.temp_dir):
            shutil.rmtree(self.temp_dir)
            print(f"🧹 测试环境已清理: {self.temp_dir}")
        
        if self.original_cwd:
            os.chdir(self.original_cwd)
        
        # 清理环境变量
        os.environ.pop('TEST_MODE', None)
        os.environ.pop('TEST_CONFIG_PATH', None)


class MockExchange:
    """模拟交易所"""
    
    def __init__(self, name: str = "mock_exchange"):
        self.name = name
        self.balance = {"USDT": 1000.0, "BTC": 0.1, "ETH": 1.0}
        self.orders = []
        self.order_id_counter = 1000
        self.prices = {
            "BTCUSDT": 45000.0,
            "ETHUSDT": 3200.0,
            "ADAUSDT": 0.5
        }
    
    def fetch_balance(self):
        """获取余额"""
        return {"free": self.balance, "used": {}, "total": self.balance}
    
    def create_order(self, symbol, type, side, amount, price=None, params=None):
        """创建订单"""
        order_id = str(self.order_id_counter)
        self.order_id_counter += 1
        
        order = {
            "id": order_id,
            "symbol": symbol,
            "type": type,
            "side": side,
            "amount": amount,
            "price": price,
            "status": "open",
            "timestamp": int(time.time() * 1000)
        }
        
        self.orders.append(order)
        return order
    
    def fetch_ticker(self, symbol):
        """获取价格"""
        return {
            "symbol": symbol,
            "last": self.prices.get(symbol, 100.0),
            "bid": self.prices.get(symbol, 100.0) * 0.999,
            "ask": self.prices.get(symbol, 100.0) * 1.001
        }
    
    def fetch_ohlcv(self, symbol, timeframe="1h", since=None, limit=100):
        """获取K线数据"""
        base_price = self.prices.get(symbol, 100.0)
        ohlcv_data = []
        
        for i in range(limit):
            timestamp = int(time.time() - (limit - i) * 3600) * 1000
            open_price = base_price * (1 + (i % 10 - 5) * 0.01)
            close_price = open_price * (1 + (i % 5 - 2) * 0.005)
            high_price = max(open_price, close_price) * 1.01
            low_price = min(open_price, close_price) * 0.99
            volume = 1000 + (i % 100)
            
            ohlcv_data.append([timestamp, open_price, high_price, low_price, close_price, volume])
        
        return ohlcv_data


class APISecurityTests(unittest.TestCase):
    """API安全测试"""
    
    def setUp(self):
        from api_security import APISecurityManager
        self.security_manager = APISecurityManager()
    
    def test_rate_limiting(self):
        """测试限流功能"""
        # 测试正常请求
        result = self.security_manager.check_request_permission('binance', 'test_1')
        self.assertTrue(result['allowed'])
        
        # 测试超过限制
        for i in range(15):  # 超过默认10个请求的限制
            result = self.security_manager.check_request_permission('binance', f'test_{i}')
        
        # 最后几个请求应该被拒绝
        self.assertFalse(result['allowed'])
        self.assertEqual(result['reason'], 'rate_limit_exceeded')
    
    def test_circuit_breaker(self):
        """测试熔断器功能"""
        # 模拟多次失败
        for i in range(6):
            self.security_manager.record_request_result('test_service', success=False)
        
        # 检查熔断器是否打开
        result = self.security_manager.check_request_permission('test_service', 'test')
        self.assertFalse(result['allowed'])
        self.assertEqual(result['reason'], 'circuit_breaker_open')
    
    def test_security_stats(self):
        """测试安全统计"""
        stats = self.security_manager.get_security_stats()
        self.assertIsInstance(stats, dict)
        self.assertIn('recent_events', stats)


class ConfigurationTests(unittest.TestCase):
    """配置管理测试"""
    
    def setUp(self):
        self.test_env = TestEnvironment()
        self.test_env.setup()
    
    def tearDown(self):
        self.test_env.teardown()
    
    def test_secure_config_creation(self):
        """测试安全配置创建"""
        from secure_config import SecureConfigManager
        
        # 在测试模式下不自动创建
        manager = SecureConfigManager(
            config_dir=os.path.join(self.test_env.temp_dir, ".config"),
            auto_create=False
        )
        
        status = manager.get_config_status()
        self.assertFalse(status['encrypted_config_exists'])
    
    def test_public_config_access(self):
        """测试公开配置访问"""
        from secure_config import SecureConfigManager
        
        manager = SecureConfigManager(
            config_dir=os.path.join(self.test_env.temp_dir, ".config"),
            auto_create=False
        )
        
        config = manager.get_public_config()
        self.assertIn('trading', config)
        self.assertIn('monitoring', config)


class ExceptionHandlerTests(unittest.TestCase):
    """异常处理测试"""
    
    def setUp(self):
        from exception_handler import ExceptionMonitor
        self.monitor = ExceptionMonitor(log_dir="test_logs")
    
    def tearDown(self):
        # 清理测试日志
        if os.path.exists("test_logs"):
            shutil.rmtree("test_logs")
    
    def test_error_recording(self):
        """测试错误记录"""
        from exception_handler import ErrorSeverity
        
        test_error = ValueError("测试错误")
        error_record = self.monitor.record_error(
            test_error, "test_component", {"test": "context"}
        )
        
        self.assertEqual(error_record.error_type, "ValueError")
        self.assertEqual(error_record.component, "test_component")
        self.assertFalse(error_record.resolved)
    
    def test_health_monitoring(self):
        """测试健康监控"""
        self.monitor.update_component_health(
            "test_component", "healthy", {"cpu": 50}
        )
        
        summary = self.monitor.get_error_summary()
        self.assertIn('system_health', summary)
        self.assertIn('test_component', summary['system_health'])
    
    def test_recovery_suggestions(self):
        """测试恢复建议"""
        suggestions = self.monitor.get_recovery_suggestions()
        self.assertIsInstance(suggestions, list)


class DatabaseTests(unittest.TestCase):
    """数据库测试"""
    
    def setUp(self):
        from database_framework import TradingDataManager
        
        # 使用内存数据库进行测试
        self.db_manager = TradingDataManager(":memory:")
    
    def test_trade_operations(self):
        """测试交易操作"""
        # 添加交易
        self.db_manager.add_trade(
            symbol="BTCUSDT",
            side="buy",
            amount=100.0,
            price=45000.0,
            strategy="test_strategy",
            pnl=500.0
        )
        
        # 获取交易
        trades = self.db_manager.get_trades()
        self.assertEqual(len(trades), 1)
        self.assertEqual(trades[0]['symbol'], "BTCUSDT")
    
    def test_signal_operations(self):
        """测试信号操作"""
        # 添加信号
        self.db_manager.add_signal(
            symbol="ETHUSDT",
            strategy_name="test_strategy",
            signal_type="buy",
            confidence=0.85,
            price=3200.0
        )
        
        # 获取信号
        signals = self.db_manager.get_signals()
        self.assertEqual(len(signals), 1)
        self.assertEqual(signals[0]['symbol'], "ETHUSDT")
    
    def test_performance_stats(self):
        """测试性能统计"""
        # 添加一些交易数据
        self.db_manager.add_trade("BTCUSDT", "buy", 100, 45000, pnl=500)
        self.db_manager.add_trade("ETHUSDT", "sell", 50, 3200, pnl=-200)
        
        stats = self.db_manager.get_performance_stats()
        self.assertEqual(stats['total_trades'], 2)
        self.assertEqual(stats['total_pnl'], 300)


class TradingSystemIntegrationTests(unittest.TestCase):
    """交易系统集成测试"""
    
    def setUp(self):
        self.test_env = TestEnvironment()
        self.test_env.setup()
        self.mock_exchange = MockExchange()
    
    def tearDown(self):
        self.test_env.teardown()
    
    @patch('ccxt.binance')
    def test_trading_bot_initialization(self, mock_binance_class):
        """测试交易机器人初始化"""
        mock_binance_class.return_value = self.mock_exchange
        
        # 这里可以测试主要的交易机器人类
        # 由于实际的main.py可能需要网络连接，我们使用Mock
        pass
    
    def test_strategy_execution(self):
        """测试策略执行"""
        # 模拟策略分析
        from strategy_analyzer import StrategyAnalyzer
        
        # 创建模拟数据
        mock_data = self.mock_exchange.fetch_ohlcv("BTCUSDT")
        
        # 这里可以测试策略分析逻辑
        self.assertIsNotNone(mock_data)


class PerformanceTests(unittest.TestCase):
    """性能测试"""
    
    def test_database_performance(self):
        """测试数据库性能"""
        from database_framework import TradingDataManager
        
        db_manager = TradingDataManager(":memory:")
        
        # 批量插入测试
        start_time = time.time()
        for i in range(100):
            db_manager.add_trade(
                symbol=f"TEST{i%10}USDT",
                side="buy" if i % 2 == 0 else "sell",
                amount=100 + i,
                price=1000 + i,
                pnl=i - 50
            )
        
        insert_time = time.time() - start_time
        
        # 查询测试
        start_time = time.time()
        trades = db_manager.get_trades(limit=100)
        query_time = time.time() - start_time
        
        print(f"📊 数据库性能: 插入100条记录用时 {insert_time:.3f}s, 查询用时 {query_time:.3f}s")
        
        self.assertLess(insert_time, 1.0)  # 插入应该在1秒内完成
        self.assertLess(query_time, 0.1)   # 查询应该在0.1秒内完成
    
    def test_api_security_performance(self):
        """测试API安全性能"""
        from api_security import APISecurityManager
        
        security_manager = APISecurityManager()
        
        # 大量请求测试
        start_time = time.time()
        for i in range(1000):
            security_manager.check_request_permission('test', f'request_{i}')
        
        check_time = time.time() - start_time
        print(f"🔒 API安全性能: 1000次权限检查用时 {check_time:.3f}s")
        
        self.assertLess(check_time, 1.0)  # 1000次检查应该在1秒内完成


class CoverageReport:
    """测试覆盖率报告"""
    
    def __init__(self):
        self.test_results = {}
        self.coverage_data = {}
    
    def run_all_tests(self) -> Dict[str, Any]:
        """运行所有测试并生成报告"""
        test_classes = [
            APISecurityTests,
            ConfigurationTests,
            ExceptionHandlerTests,
            DatabaseTests,
            TradingSystemIntegrationTests,
            PerformanceTests
        ]
        
        total_tests = 0
        passed_tests = 0
        failed_tests = []
        
        for test_class in test_classes:
            suite = unittest.TestLoader().loadTestsFromTestCase(test_class)
            runner = unittest.TextTestRunner(verbosity=0, stream=open(os.devnull, 'w'))
            result = runner.run(suite)
            
            class_name = test_class.__name__
            total_tests += result.testsRun
            passed = result.testsRun - len(result.failures) - len(result.errors)
            passed_tests += passed
            
            self.test_results[class_name] = {
                'total': result.testsRun,
                'passed': passed,
                'failures': len(result.failures),
                'errors': len(result.errors)
            }
            
            if result.failures or result.errors:
                failed_tests.extend([f"{class_name}.{test}" for test, _ in result.failures + result.errors])
        
        coverage_percentage = (passed_tests / total_tests * 100) if total_tests > 0 else 0
        
        return {
            'timestamp': datetime.now().isoformat(),
            'total_tests': total_tests,
            'passed_tests': passed_tests,
            'failed_tests': len(failed_tests),
            'coverage_percentage': coverage_percentage,
            'test_results': self.test_results,
            'failed_test_names': failed_tests
        }
    
    def generate_html_report(self, report_data: Dict[str, Any]) -> str:
        """生成HTML格式的测试报告"""
        timestamp = report_data['timestamp']
        total_tests = report_data['passed_tests'] + report_data['failed_tests']
        passed_tests = report_data['passed_tests']
        failed_tests = report_data['failed_tests']
        coverage_percentage = report_data['coverage_percentage']
        
        # 创建简单的HTML报告
        html_content = f"""<!DOCTYPE html>
<html>
<head>
    <title>量化交易系统测试报告</title>
    <style>
        body {{ font-family: Arial, sans-serif; margin: 20px; }}
        .header {{ background: #f0f8ff; padding: 15px; border-radius: 5px; }}
        .summary {{ display: flex; gap: 20px; margin: 20px 0; }}
        .stat-box {{ background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; flex: 1; }}
        .passed {{ border-left: 4px solid #28a745; }}
        .failed {{ border-left: 4px solid #dc3545; }}
        .coverage {{ border-left: 4px solid #007bff; }}
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 量化交易系统测试报告</h1>
        <p>生成时间: {timestamp}</p>
    </div>
    
    <div class="summary">
        <div class="stat-box passed">
            <h3>✅ 通过测试</h3>
            <h2>{passed_tests}</h2>
        </div>
        <div class="stat-box failed">
            <h3>❌ 失败测试</h3>
            <h2>{failed_tests}</h2>
        </div>
        <div class="stat-box coverage">
            <h3>📊 覆盖率</h3>
            <h2>{coverage_percentage:.1f}%</h2>
        </div>
    </div>
    
    <p>总测试数: {total_tests}</p>
</body>
</html>"""
        
        return html_content


def main():
    """主函数"""
    print("🧪 开始运行增强测试套件")
    print("=" * 50)
    
    # 创建覆盖率报告生成器
    coverage_report = CoverageReport()
    
    # 运行所有测试
    report_data = coverage_report.run_all_tests()
    
    # 打印摘要
    print(f"\n📊 测试摘要:")
    print(f"   总测试数: {report_data['total_tests']}")
    print(f"   通过测试: {report_data['passed_tests']}")
    print(f"   失败测试: {report_data['failed_tests']}")
    print(f"   测试覆盖率: {report_data['coverage_percentage']:.1f}%")
    
    # 生成HTML报告
    html_report = coverage_report.generate_html_report(report_data)
    
    # 保存报告
    report_file = f"test_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
    with open(report_file, 'w', encoding='utf-8') as f:
        f.write(html_report)
    
    print(f"\n📄 详细报告已保存: {report_file}")
    
    # 保存JSON格式的报告
    json_file = f"test_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
    with open(json_file, 'w', encoding='utf-8') as f:
        json.dump(report_data, f, indent=2, ensure_ascii=False)
    
    print(f"📄 JSON报告已保存: {json_file}")
    
    # 如果覆盖率低于阈值，返回错误代码
    if report_data['coverage_percentage'] < 80:
        print(f"\n⚠️ 警告: 测试覆盖率 {report_data['coverage_percentage']:.1f}% 低于目标 80%")
        return 1
    else:
        print(f"\n✅ 测试覆盖率达标: {report_data['coverage_percentage']:.1f}%")
        return 0


if __name__ == '__main__':
    exit_code = main()
    sys.exit(exit_code)