#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
安全配置管理系统
替代明文config.json，提供加密存储和安全访问
"""

import os
import json
import base64
import hashlib
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
from typing import Dict, Any, Optional
import getpass
from dataclasses import dataclass
from datetime import datetime
import logging


@dataclass
class SecretConfig:
    """敏感配置数据结构"""
    api_keys: Dict[str, Dict[str, str]]
    telegram: Dict[str, str]
    encryption_metadata: Dict[str, Any]


class SecureConfigManager:
    """安全配置管理器"""
    
    def __init__(self, config_dir: str = ".config", auto_create: bool = True):
        self.config_dir = config_dir
        self.encrypted_file = os.path.join(config_dir, "secure_config.enc")
        self.public_config_file = os.path.join(config_dir, "public_config.json")
        self.salt_file = os.path.join(config_dir, ".salt")
        
        # 创建配置目录
        os.makedirs(config_dir, exist_ok=True)
        
        # 设置日志
        self.logger = logging.getLogger("secure_config")
        
        # 非敏感的公开配置
        self.public_config = {
            "trading": {
                "risk_per_trade": 0.02,
                "max_positions": 5,
                "min_trade_amount": 10
            },
            "monitoring": {
                "check_interval": 30,
                "dashboard_port": 5010,
                "log_level": "INFO"
            },
            "strategy": {
                "timeframes": ["1h", "4h", "1d"],
                "indicators": {
                    "ma_periods": [20, 50, 200],
                    "rsi_period": 14,
                    "bb_period": 20
                }
            }
        }
        
        # 加载或创建配置
        self._initialize_config(auto_create)
    
    def _generate_key_from_password(self, password: str, salt: bytes = None) -> tuple:
        """从密码生成加密密钥"""
        if salt is None:
            salt = os.urandom(32)
        
        kdf = PBKDF2HMAC(
            algorithm=hashes.SHA256(),
            length=32,
            salt=salt,
            iterations=100000,
        )
        key = base64.urlsafe_b64encode(kdf.derive(password.encode()))
        return key, salt
    
    def _save_salt(self, salt: bytes):
        """保存盐值"""
        with open(self.salt_file, 'wb') as f:
            f.write(salt)
    
    def _load_salt(self) -> bytes:
        """加载盐值"""
        if not os.path.exists(self.salt_file):
            return None
        
        with open(self.salt_file, 'rb') as f:
            return f.read()
    
    def _initialize_config(self, auto_create: bool = True):
        """初始化配置"""
        # 保存公开配置
        self._save_public_config()
        
        # 检查是否存在加密配置
        if not os.path.exists(self.encrypted_file):
            self.logger.info("未找到加密配置文件")
            if auto_create:
                self._create_initial_config()
        else:
            self.logger.info("发现现有加密配置文件")
    
    def _save_public_config(self):
        """保存公开配置"""
        with open(self.public_config_file, 'w', encoding='utf-8') as f:
            json.dump(self.public_config, f, indent=2, ensure_ascii=False)
    
    def _create_initial_config(self):
        """创建初始加密配置"""
        print("🔐 创建安全配置文件")
        print("请设置主密码来保护您的API密钥和敏感信息...")
        
        if self.test_mode:
            password = "test_password_12345"  # 测试模式使用固定密码
            confirm_password = password
        else:
            password = getpass.getpass("输入主密码: ")
            confirm_password = getpass.getpass("确认主密码: ")
        
        if password != confirm_password:
            raise ValueError("密码确认失败")
        
        if len(password) < 8:
            raise ValueError("密码长度至少8位")
        
        # 生成密钥和盐值
        key, salt = self._generate_key_from_password(password)
        self._save_salt(salt)
        
        # 创建默认敏感配置
        secret_config = {
            "api_keys": {
                "binance": {
                    "api_key": "",
                    "api_secret": ""
                },
                "bitget": {
                    "api_key": "",
                    "api_secret": ""
                }
            },
            "telegram": {
                "bot_token": "",
                "chat_id": ""
            },
            "encryption_metadata": {
                "created_at": datetime.now().isoformat(),
                "version": "1.0"
            }
        }
        
        # 加密并保存
        self._encrypt_and_save_config(secret_config, key)
        print("✅ 安全配置文件创建成功")
    
    def _encrypt_and_save_config(self, config: Dict[str, Any], key: bytes):
        """加密并保存配置"""
        f = Fernet(key)
        
        # 序列化配置
        config_json = json.dumps(config, ensure_ascii=False, indent=2)
        
        # 加密
        encrypted_data = f.encrypt(config_json.encode('utf-8'))
        
        # 保存到文件
        with open(self.encrypted_file, 'wb') as file:
            file.write(encrypted_data)
    
    def load_secure_config(self, password: str = None) -> Dict[str, Any]:
        """加载安全配置"""
        if not os.path.exists(self.encrypted_file):
            raise FileNotFoundError("加密配置文件不存在")
        
        if password is None:
            password = getpass.getpass("输入主密码解锁配置: ")
        
        # 加载盐值
        salt = self._load_salt()
        if salt is None:
            raise FileNotFoundError("盐值文件不存在")
        
        # 生成密钥
        key, _ = self._generate_key_from_password(password, salt)
        
        try:
            # 解密配置
            f = Fernet(key)
            
            with open(self.encrypted_file, 'rb') as file:
                encrypted_data = file.read()
            
            decrypted_data = f.decrypt(encrypted_data)
            config = json.loads(decrypted_data.decode('utf-8'))
            
            self.logger.info("✅ 安全配置加载成功")
            return config
            
        except Exception as e:
            self.logger.error(f"解密配置失败: {e}")
            raise ValueError("密码错误或配置文件损坏")
    
    def update_secure_config(self, updates: Dict[str, Any], password: str = None):
        """更新安全配置"""
        # 加载现有配置
        current_config = self.load_secure_config(password)
        
        # 深度更新配置
        def deep_update(base_dict, update_dict):
            for key, value in update_dict.items():
                if key in base_dict and isinstance(base_dict[key], dict) and isinstance(value, dict):
                    deep_update(base_dict[key], value)
                else:
                    base_dict[key] = value
        
        deep_update(current_config, updates)
        
        # 更新时间戳
        if 'encryption_metadata' not in current_config:
            current_config['encryption_metadata'] = {}
        current_config['encryption_metadata']['updated_at'] = datetime.now().isoformat()
        
        # 重新加密保存
        if password is None:
            password = getpass.getpass("输入主密码保存更新: ")
        
        salt = self._load_salt()
        key, _ = self._generate_key_from_password(password, salt)
        
        self._encrypt_and_save_config(current_config, key)
        self.logger.info("✅ 安全配置更新成功")
    
    def get_public_config(self) -> Dict[str, Any]:
        """获取公开配置"""
        if os.path.exists(self.public_config_file):
            with open(self.public_config_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        return self.public_config
    
    def update_public_config(self, updates: Dict[str, Any]):
        """更新公开配置"""
        config = self.get_public_config()
        
        def deep_update(base_dict, update_dict):
            for key, value in update_dict.items():
                if key in base_dict and isinstance(base_dict[key], dict) and isinstance(value, dict):
                    deep_update(base_dict[key], value)
                else:
                    base_dict[key] = value
        
        deep_update(config, updates)
        
        with open(self.public_config_file, 'w', encoding='utf-8') as f:
            json.dump(config, f, indent=2, ensure_ascii=False)
        
        self.logger.info("✅ 公开配置更新成功")
    
    def migrate_from_plain_config(self, plain_config_file: str, password: str = None):
        """从明文配置迁移"""
        if not os.path.exists(plain_config_file):
            raise FileNotFoundError(f"配置文件不存在: {plain_config_file}")
        
        print(f"🔄 开始迁移明文配置: {plain_config_file}")
        
        # 读取明文配置
        with open(plain_config_file, 'r', encoding='utf-8') as f:
            plain_config = json.load(f)
        
        # 提取敏感信息
        sensitive_config = {
            "api_keys": {},
            "telegram": {},
            "encryption_metadata": {
                "migrated_from": plain_config_file,
                "migrated_at": datetime.now().isoformat(),
                "version": "1.0"
            }
        }
        
        # 迁移API密钥
        for exchange in ['binance', 'bitget']:
            if exchange in plain_config:
                sensitive_config["api_keys"][exchange] = plain_config[exchange]
        
        # 迁移Telegram配置
        if 'telegram' in plain_config:
            sensitive_config["telegram"] = plain_config['telegram']
        
        # 如果没有现有加密配置，创建新的
        if not os.path.exists(self.encrypted_file):
            if password is None:
                password = getpass.getpass("设置新的主密码: ")
            
            key, salt = self._generate_key_from_password(password)
            self._save_salt(salt)
        else:
            # 使用现有密码
            if password is None:
                password = getpass.getpass("输入现有主密码: ")
        
        # 更新安全配置
        self.update_secure_config(sensitive_config, password)
        
        # 备份原文件
        backup_file = f"{plain_config_file}.backup.{int(datetime.now().timestamp())}"
        os.rename(plain_config_file, backup_file)
        
        print(f"✅ 迁移完成")
        print(f"📁 原文件已备份为: {backup_file}")
        print(f"🔐 敏感信息已加密存储")
    
    def export_config_template(self) -> Dict[str, Any]:
        """导出配置模板"""
        template = {
            "api_keys": {
                "binance": {
                    "api_key": "your_binance_api_key_here",
                    "api_secret": "your_binance_api_secret_here"
                },
                "bitget": {
                    "api_key": "your_bitget_api_key_here",
                    "api_secret": "your_bitget_api_secret_here"
                }
            },
            "telegram": {
                "bot_token": "your_telegram_bot_token_here",
                "chat_id": "your_telegram_chat_id_here"
            }
        }
        return template
    
    def get_config_status(self) -> Dict[str, Any]:
        """获取配置状态"""
        status = {
            "config_dir": self.config_dir,
            "encrypted_config_exists": os.path.exists(self.encrypted_file),
            "public_config_exists": os.path.exists(self.public_config_file),
            "salt_exists": os.path.exists(self.salt_file)
        }
        
        if status["encrypted_config_exists"]:
            stat = os.stat(self.encrypted_file)
            status["encrypted_config_size"] = stat.st_size
            status["encrypted_config_modified"] = datetime.fromtimestamp(stat.st_mtime).isoformat()
        
        return status


class ConfigurationManager:
    """配置管理器门面类"""
    
    def __init__(self, config_dir: str = ".config"):
        self.secure_manager = SecureConfigManager(config_dir)
        self._cached_secure_config = None
        self._config_password = None
    
    def initialize_from_legacy(self, legacy_config_path: str = "config.json"):
        """从遗留配置初始化"""
        if os.path.exists(legacy_config_path):
            print("🔄 检测到遗留配置文件，开始迁移...")
            self.secure_manager.migrate_from_plain_config(legacy_config_path)
        else:
            print("📝 创建新的安全配置...")
            # 如果没有配置文件，引导用户创建
            if not os.path.exists(self.secure_manager.encrypted_file):
                self.secure_manager._create_initial_config()
    
    def get_api_config(self, exchange: str, password: str = None) -> Dict[str, str]:
        """获取交易所API配置"""
        if self._cached_secure_config is None or password != self._config_password:
            self._cached_secure_config = self.secure_manager.load_secure_config(password)
            self._config_password = password
        
        exchange = exchange.lower()
        if exchange in self._cached_secure_config.get('api_keys', {}):
            return self._cached_secure_config['api_keys'][exchange]
        
        raise KeyError(f"未找到交易所配置: {exchange}")
    
    def get_telegram_config(self, password: str = None) -> Dict[str, str]:
        """获取Telegram配置"""
        if self._cached_secure_config is None or password != self._config_password:
            self._cached_secure_config = self.secure_manager.load_secure_config(password)
            self._config_password = password
        
        return self._cached_secure_config.get('telegram', {})
    
    def get_trading_config(self) -> Dict[str, Any]:
        """获取交易配置"""
        return self.secure_manager.get_public_config().get('trading', {})


def main():
    """命令行工具"""
    import argparse
    
    parser = argparse.ArgumentParser(description="安全配置管理工具")
    parser.add_argument('--migrate', type=str, help="从明文配置迁移")
    parser.add_argument('--status', action='store_true', help="显示配置状态")
    parser.add_argument('--template', action='store_true', help="导出配置模板")
    
    args = parser.parse_args()
    
    # 在命令行模式下不自动创建配置
    manager = SecureConfigManager(auto_create=False)
    
    if args.migrate:
        manager.migrate_from_plain_config(args.migrate)
    
    elif args.status:
        status = manager.get_config_status()
        print("📊 配置状态:")
        for key, value in status.items():
            print(f"  {key}: {value}")
    
    elif args.template:
        template = manager.export_config_template()
        print("📝 配置模板:")
        print(json.dumps(template, indent=2, ensure_ascii=False))
    
    else:
        print("🔐 安全配置管理器")
        print("使用 --help 查看可用选项")


if __name__ == '__main__':
    main()