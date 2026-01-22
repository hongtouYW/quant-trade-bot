#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易系统准备状态检查器
检查系统是否准备好进行实盘交易
"""

import os
import json
import sys
import subprocess
from datetime import datetime

class TradingSystemChecker:
    """系统准备状态检查器"""
    
    def __init__(self):
        self.checks = []
        self.warnings = []
        self.errors = []
        self.score = 0
        self.max_score = 0
    
    def add_check(self, name, check_func, weight=1, critical=False):
        """添加检查项"""
        self.checks.append({
            'name': name,
            'func': check_func,
            'weight': weight,
            'critical': critical,
            'status': 'pending'
        })
        self.max_score += weight
    
    def run_all_checks(self):
        """运行所有检查"""
        print("🔍 量化交易系统准备状态检查")
        print("=" * 50)
        
        critical_failures = 0
        
        for check in self.checks:
            print(f"\n📋 检查: {check['name']}")
            print("-" * 30)
            
            try:
                result = check['func']()
                if result:
                    check['status'] = 'passed'
                    self.score += check['weight']
                    print(f"✅ 通过 (+{check['weight']}分)")
                else:
                    check['status'] = 'failed'
                    if check['critical']:
                        critical_failures += 1
                        print(f"🚨 关键检查失败")
                    else:
                        print(f"⚠️  检查失败")
            
            except Exception as e:
                check['status'] = 'error'
                check['error'] = str(e)
                if check['critical']:
                    critical_failures += 1
                print(f"💥 检查异常: {e}")
        
        # 计算总分
        percentage = (self.score / self.max_score * 100) if self.max_score > 0 else 0
        
        # 生成报告
        self.generate_report(percentage, critical_failures)
        
        return percentage >= 80 and critical_failures == 0
    
    def generate_report(self, percentage, critical_failures):
        """生成检查报告"""
        print("\n" + "=" * 50)
        print("📊 系统准备状态报告")
        print("=" * 50)
        
        print(f"📈 总分: {self.score}/{self.max_score} ({percentage:.1f}%)")
        print(f"🚨 关键失败: {critical_failures}")
        
        # 状态判断
        if percentage >= 90 and critical_failures == 0:
            status = "🟢 优秀 - 系统完全就绪"
            recommendation = "可以开始实盘交易"
        elif percentage >= 80 and critical_failures == 0:
            status = "🟡 良好 - 基本就绪"
            recommendation = "建议小资金测试"
        elif percentage >= 60:
            status = "🟠 一般 - 需要改进"
            recommendation = "继续模拟交易测试"
        else:
            status = "🔴 不合格 - 存在严重问题"
            recommendation = "禁止实盘交易"
        
        print(f"📊 系统状态: {status}")
        print(f"💡 建议: {recommendation}")
        
        # 详细检查结果
        print(f"\n📋 详细检查结果:")
        for check in self.checks:
            status_icon = {
                'passed': '✅',
                'failed': '❌',
                'error': '💥',
                'pending': '⏸️'
            }[check['status']]
            
            critical_mark = " [关键]" if check['critical'] else ""
            print(f"   {status_icon} {check['name']}{critical_mark}")
        
        # 保存报告
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        report_data = {
            'timestamp': timestamp,
            'score': self.score,
            'max_score': self.max_score,
            'percentage': percentage,
            'critical_failures': critical_failures,
            'status': status,
            'recommendation': recommendation,
            'checks': self.checks,
            'warnings': self.warnings,
            'errors': self.errors
        }
        
        with open(f'system_readiness_report_{timestamp}.json', 'w') as f:
            json.dump(report_data, f, indent=2, default=str)
        
        print(f"\n📁 详细报告已保存: system_readiness_report_{timestamp}.json")


# 检查函数定义
def check_dependencies():
    """检查依赖包"""
    try:
        import pandas
        import numpy
        import ccxt
        import requests
        print("📦 核心依赖包已安装")
        return True
    except ImportError as e:
        print(f"❌ 缺少依赖包: {e}")
        return False

def check_config_files():
    """检查配置文件"""
    config_path = '/Users/hongtou/newproject/quant-trade-bot/config.json'
    
    if not os.path.exists(config_path):
        print("❌ config.json 文件不存在")
        return False
    
    try:
        with open(config_path, 'r') as f:
            config = json.load(f)
        
        required_keys = ['binance', 'telegram']
        for key in required_keys:
            if key not in config:
                print(f"❌ 配置文件缺少 {key} 配置")
                return False
        
        print("⚙️ 配置文件格式正确")
        return True
    except Exception as e:
        print(f"❌ 配置文件读取失败: {e}")
        return False

def check_strategy_files():
    """检查策略文件"""
    required_files = [
        'strategy/ma_strategy.py',
        'utils/risk_manager.py',
        'utils/data_loader.py',
    ]
    
    missing_files = []
    for file_path in required_files:
        full_path = f'/Users/hongtou/newproject/quant-trade-bot/{file_path}'
        if not os.path.exists(full_path):
            missing_files.append(file_path)
    
    if missing_files:
        print(f"❌ 缺少文件: {missing_files}")
        return False
    
    print("📄 策略文件完整")
    return True

def check_api_connectivity():
    """检查API连接"""
    try:
        import ccxt
        # 测试连接（只测试公开API）
        exchange = ccxt.binance()
        exchange.load_markets()
        print("🌐 API连接正常")
        return True
    except Exception as e:
        print(f"❌ API连接失败: {e}")
        return False

def check_risk_parameters():
    """检查风险参数"""
    try:
        from utils.risk_manager import RiskManager
        
        risk_manager = RiskManager()
        
        # 检查风险参数是否合理
        if risk_manager.max_loss_pct > 0.05:  # 单次亏损不应超过5%
            print("⚠️ 单次亏损限制过高")
            return False
        
        if risk_manager.max_position_pct > 0.2:  # 单次仓位不应超过20%
            print("⚠️ 单次仓位限制过高")
            return False
        
        print("🛡️ 风险参数设置合理")
        return True
    except Exception as e:
        print(f"❌ 风险管理器检查失败: {e}")
        return False

def check_backtesting_results():
    """检查回测结果"""
    backtest_files = [f for f in os.listdir('/Users/hongtou/newproject/quant-trade-bot/') 
                     if f.startswith('backtest') and f.endswith('.json')]
    
    if not backtest_files:
        print("⚠️ 没有找到回测结果文件")
        return False
    
    try:
        # 检查最新的回测文件
        latest_file = sorted(backtest_files)[-1]
        with open(f'/Users/hongtou/newproject/quant-trade-bot/{latest_file}', 'r') as f:
            backtest_data = json.load(f)
        
        # 检查关键指标
        if 'total_return' in backtest_data:
            if backtest_data['total_return'] < -20:  # 总收益率不应低于-20%
                print("⚠️ 回测收益率过低")
                return False
        
        print("📊 回测结果存在且合理")
        return True
    except Exception as e:
        print(f"❌ 回测结果检查失败: {e}")
        return False

def check_monitoring_systems():
    """检查监控系统"""
    dashboard_files = [
        'simple_dashboard.py',
        'market_monitor_dashboard.py',
        'trading_history_app.py'
    ]
    
    for file_name in dashboard_files:
        file_path = f'/Users/hongtou/newproject/quant-trade-bot/{file_name}'
        if not os.path.exists(file_path):
            print(f"⚠️ 监控文件缺失: {file_name}")
            return False
    
    print("📺 监控系统完整")
    return True

def check_paper_trading():
    """检查模拟交易功能"""
    try:
        paper_trading_path = '/Users/hongtou/newproject/quant-trade-bot/paper_trading_env.py'
        if not os.path.exists(paper_trading_path):
            print("⚠️ 模拟交易环境不存在")
            return False
        
        # 检查是否有模拟交易结果
        result_files = [f for f in os.listdir('/Users/hongtou/newproject/quant-trade-bot/') 
                       if f.startswith('paper_trading_results')]
        
        if not result_files:
            print("💡 建议先运行模拟交易测试")
            return False
        
        print("📝 模拟交易环境就绪")
        return True
    except Exception as e:
        print(f"❌ 模拟交易检查失败: {e}")
        return False

def run_comprehensive_check():
    """运行综合检查"""
    checker = TradingSystemChecker()
    
    # 添加检查项（按重要性排序）
    checker.add_check("依赖包完整性", check_dependencies, weight=2, critical=True)
    checker.add_check("配置文件完整性", check_config_files, weight=3, critical=True)
    checker.add_check("策略文件完整性", check_strategy_files, weight=2, critical=True)
    checker.add_check("API连接测试", check_api_connectivity, weight=2, critical=True)
    checker.add_check("风险参数检查", check_risk_parameters, weight=3, critical=True)
    checker.add_check("回测结果验证", check_backtesting_results, weight=2)
    checker.add_check("监控系统检查", check_monitoring_systems, weight=1)
    checker.add_check("模拟交易准备", check_paper_trading, weight=1)
    
    # 运行检查
    system_ready = checker.run_all_checks()
    
    return system_ready, checker

if __name__ == '__main__':
    print("🚀 启动量化交易系统准备状态检查")
    print("=" * 50)
    
    system_ready, checker = run_comprehensive_check()
    
    if system_ready:
        print("\n🎉 系统检查完成 - 可以考虑实盘交易!")
        print("\n💡 下一步建议:")
        print("   1. 先进行小资金实盘测试")
        print("   2. 监控系统运行1-2周")
        print("   3. 根据表现调整参数")
        exit(0)
    else:
        print("\n⚠️ 系统未完全准备就绪")
        print("\n💡 建议:")
        print("   1. 解决上述问题")
        print("   2. 继续模拟交易测试")
        print("   3. 重新运行检查")
        exit(1)