import os
import yaml
from pathlib import Path
from typing import Any, Dict

from .exceptions import ConfigError


class Config:
    """配置加载器，支持环境变量替换"""

    def __init__(self, config_path: str = None):
        if config_path is None:
            config_path = str(Path(__file__).parent.parent.parent / "config" / "config.yaml")
        self._path = config_path
        self._data: Dict[str, Any] = {}
        self.load()

    def load(self):
        try:
            with open(self._path, 'r', encoding='utf-8') as f:
                raw = f.read()
            # 替换环境变量 ${VAR_NAME}
            import re
            def _replace_env(match):
                var_name = match.group(1)
                return os.environ.get(var_name, match.group(0))
            raw = re.sub(r'\$\{(\w+)\}', _replace_env, raw)
            self._data = yaml.safe_load(raw) or {}
        except FileNotFoundError:
            raise ConfigError(f"Config file not found: {self._path}")
        except yaml.YAMLError as e:
            raise ConfigError(f"Config parse error: {e}")

    def get(self, key: str, default: Any = None) -> Any:
        """支持点分隔的嵌套访问: config.get('risk.per_trade_risk_pct')"""
        keys = key.split('.')
        val = self._data
        for k in keys:
            if isinstance(val, dict):
                val = val.get(k)
            else:
                return default
            if val is None:
                return default
        return val

    def __getitem__(self, key: str) -> Any:
        val = self.get(key)
        if val is None:
            raise ConfigError(f"Config key not found: {key}")
        return val

    @property
    def data(self) -> Dict[str, Any]:
        return self._data

    # 快捷属性
    @property
    def leverage(self) -> int:
        return self.get('account.leverage', 10)

    @property
    def balance(self) -> float:
        return self.get('account.balance', 2000)

    @property
    def max_open_positions(self) -> int:
        return self.get('risk.max_open_positions', 4)

    @property
    def per_trade_risk_pct(self) -> float:
        return self.get('risk.per_trade_risk_pct', 0.4)

    @property
    def scan_interval(self) -> int:
        return self.get('symbols.scan_interval_sec', 300)
