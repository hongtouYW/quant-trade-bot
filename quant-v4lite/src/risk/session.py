import logging
from datetime import datetime
from typing import Tuple

from src.core.enums import SessionName

logger = logging.getLogger(__name__)


class SessionManager:
    """分时段参数管理"""

    def __init__(self, config: dict):
        self._zones = config.get('session', {}).get('zones', {})
        self._enabled = config.get('session', {}).get('enabled', False)

    def get_current_session(self) -> Tuple[SessionName, dict]:
        """返回当前时段名和参数"""
        if not self._enabled:
            return SessionName.AMERICA, {}

        hour = datetime.utcnow().hour

        for zone_name, zone_cfg in self._zones.items():
            if hour in zone_cfg.get('utc_hours', []):
                try:
                    session = SessionName(zone_name)
                except ValueError:
                    continue
                return session, zone_cfg

        return SessionName.AMERICA, self._zones.get('america', {})

    def apply_overrides(self, base_config: dict) -> dict:
        """将时段参数覆盖到基础配置"""
        if not self._enabled:
            return base_config

        session_name, zone_cfg = self.get_current_session()
        config = dict(base_config)

        # 覆盖风控参数
        risk = dict(config.get('risk', {}))
        if 'risk_per_trade_pct' in zone_cfg:
            risk['per_trade_risk_pct'] = zone_cfg['risk_per_trade_pct']
        if 'max_positions' in zone_cfg:
            risk['max_open_positions'] = zone_cfg['max_positions']
        config['risk'] = risk

        # 覆盖筛选参数
        trend = dict(config.get('trend_filter', {}))
        if 'min_adx' in zone_cfg:
            trend['adx_strong'] = zone_cfg['min_adx']
        config['trend_filter'] = trend

        ranking = dict(config.get('ranking', {}))
        if 'min_score' in zone_cfg:
            ranking['min_score'] = zone_cfg['min_score']
        config['ranking'] = ranking

        liq = dict(config.get('liquidity_filter', {}))
        if 'min_volume_ratio' in zone_cfg:
            liq['min_volume_ratio'] = zone_cfg['min_volume_ratio']
        config['liquidity_filter'] = liq

        return config
