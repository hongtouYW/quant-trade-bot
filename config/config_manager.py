#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
环境配置管理器
安全地加载环境变量和配置
"""

import os
from dotenv import load_dotenv
import json

class ConfigManager:
    """配置管理器"""
    
    def __init__(self, config_path=None):
        # 加载环境变量
        load_dotenv()
        
        self.trading_mode = os.getenv('TRADING_MODE', 'paper')
        self.debug_mode = os.getenv('DEBUG_MODE', 'true').lower() == 'true'
        
        # 加载传统配置文件作为后备
        if config_path and os.path.exists(config_path):
            with open(config_path, 'r') as f:
                self._file_config = json.load(f)
        else:
            self._file_config = {}
    
    def get_exchange_config(self, exchange_name):
        """获取交易所配置"""
        if exchange_name.lower() == 'binance':
            return {
                'api_key': os.getenv('BINANCE_API_KEY') or self._file_config.get('binance', {}).get('api_key'),
                'secret': os.getenv('BINANCE_API_SECRET') or self._file_config.get('binance', {}).get('api_secret'),
                'sandbox': self.trading_mode == 'paper'
            }
        elif exchange_name.lower() == 'bitget':
            return {
                'api_key': os.getenv('BITGET_API_KEY') or self._file_config.get('bitget', {}).get('api_key'),
                'secret': os.getenv('BITGET_API_SECRET') or self._file_config.get('bitget', {}).get('api_secret'),
                'sandbox': self.trading_mode == 'paper'
            }
        else:
            raise ValueError(f"不支持的交易所: {exchange_name}")
    
    def get_telegram_config(self):
        """获取Telegram配置"""
        return {
            'bot_token': os.getenv('TELEGRAM_BOT_TOKEN') or self._file_config.get('telegram', {}).get('bot_token'),
            'chat_id': os.getenv('TELEGRAM_CHAT_ID') or self._file_config.get('telegram', {}).get('chat_id')
        }
    
    def get_risk_config(self):
        """获取风险管理配置"""
        return {
            'max_position_pct': float(os.getenv('MAX_POSITION_PCT', '0.1')),
            'max_loss_pct': float(os.getenv('MAX_LOSS_PCT', '0.02')),
            'max_daily_trades': int(os.getenv('MAX_DAILY_TRADES', '10'))
        }
    
    def is_live_trading(self):
        """是否为实盘交易模式"""
        return self.trading_mode == 'live'
    
    def is_paper_trading(self):
        """是否为模拟交易模式"""
        return self.trading_mode == 'paper'
    
    def validate_config(self):
        """验证配置完整性"""
        errors = []
        
        # 检查必要的API密钥
        binance_config = self.get_exchange_config('binance')
        if not binance_config['api_key'] or not binance_config['secret']:
            errors.append("Binance API配置缺失")
        
        telegram_config = self.get_telegram_config()
        if not telegram_config['bot_token'] or not telegram_config['chat_id']:
            errors.append("Telegram配置缺失")
        
        # 检查交易模式
        if self.trading_mode not in ['paper', 'live']:
            errors.append("交易模式必须为 'paper' 或 'live'")
        
        return errors
    
    def print_config_status(self):
        """打印配置状态"""
        print("⚙️  系统配置状态")
        print("=" * 30)
        print(f"🎯 交易模式: {self.trading_mode.upper()}")
        print(f"🔧 调试模式: {self.debug_mode}")
        
        # 验证配置
        errors = self.validate_config()
        if errors:
            print("❌ 配置错误:")
            for error in errors:
                print(f"   - {error}")
        else:
            print("✅ 配置验证通过")


# 全局配置实例
config_manager = ConfigManager('/Users/hongtou/newproject/quant-trade-bot/config.json')

# 测试配置管理器
if __name__ == '__main__':
    config_manager.print_config_status()