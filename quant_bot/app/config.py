"""Configuration loader"""
import os
import yaml

_cfg = None
_symbols_cfg = None


def load_config(path=None):
    global _cfg
    if path is None:
        path = os.path.join(os.path.dirname(__file__), '..', 'config', 'config.yaml')
    with open(path) as f:
        _cfg = yaml.safe_load(f)
    return _cfg


def load_symbols_config(path=None):
    """加载 symbols.yaml 独立配置 (Spec §20)"""
    global _symbols_cfg
    if path is None:
        path = os.path.join(os.path.dirname(__file__), '..', 'config', 'symbols.yaml')
    try:
        with open(path) as f:
            _symbols_cfg = yaml.safe_load(f) or {}
    except FileNotFoundError:
        _symbols_cfg = {}
    return _symbols_cfg


def get_symbols_config():
    global _symbols_cfg
    if _symbols_cfg is None:
        load_symbols_config()
    return _symbols_cfg


def get_blacklist():
    """获取黑名单币种"""
    return get_symbols_config().get('blacklist', [])


def get_whitelist():
    """获取白名单币种"""
    return get_symbols_config().get('whitelist', [])


def get_symbol_override(symbol, key, default=None):
    """获取币种特定参数覆盖, 无覆盖则返回default"""
    overrides = get_symbols_config().get('overrides', {})
    sym_cfg = overrides.get(symbol, {})
    return sym_cfg.get(key, default)


def get_config():
    global _cfg
    if _cfg is None:
        load_config()
    return _cfg


def get(section, key=None, default=None):
    c = get_config()
    s = c.get(section, {})
    if key is None:
        return s
    return s.get(key, default)
