"""Configuration loader"""
import os
import yaml

_cfg = None

def load_config(path=None):
    global _cfg
    if path is None:
        path = os.path.join(os.path.dirname(__file__), '..', 'config', 'config.yaml')
    with open(path) as f:
        _cfg = yaml.safe_load(f)
    return _cfg

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
