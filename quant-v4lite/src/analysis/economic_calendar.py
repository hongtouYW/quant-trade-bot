import logging
from datetime import datetime, timedelta
from typing import Optional

logger = logging.getLogger(__name__)

# 2026 主要经济事件 (UTC 时间)
# 格式: (月, 日, 小时, 事件名, 影响级别)
# 影响级别: high = 暂停交易, medium = 缩小仓位
MAJOR_EVENTS_2026 = [
    # CPI - 通常 US 每月第二/三周二/三
    (1, 14, 13, "CPI", "high"),
    (2, 12, 13, "CPI", "high"),
    (3, 11, 12, "CPI", "high"),
    (4, 14, 12, "CPI", "high"),
    (5, 13, 12, "CPI", "high"),
    (6, 10, 12, "CPI", "high"),
    (7, 14, 12, "CPI", "high"),
    (8, 12, 12, "CPI", "high"),
    (9, 15, 12, "CPI", "high"),
    (10, 13, 12, "CPI", "high"),
    (11, 12, 13, "CPI", "high"),
    (12, 10, 13, "CPI", "high"),
    # FOMC - 每 6 周
    (1, 29, 19, "FOMC", "high"),
    (3, 18, 18, "FOMC", "high"),
    (5, 6, 18, "FOMC", "high"),
    (6, 17, 18, "FOMC", "high"),
    (7, 29, 18, "FOMC", "high"),
    (9, 16, 18, "FOMC", "high"),
    (11, 4, 19, "FOMC", "high"),
    (12, 16, 19, "FOMC", "high"),
    # NFP - 每月第一个周五
    (1, 9, 13, "NFP", "high"),
    (2, 6, 13, "NFP", "high"),
    (3, 6, 13, "NFP", "high"),
    (4, 3, 12, "NFP", "high"),
    (5, 1, 12, "NFP", "high"),
    (6, 5, 12, "NFP", "high"),
    (7, 2, 12, "NFP", "high"),
    (8, 7, 12, "NFP", "high"),
    (9, 4, 12, "NFP", "high"),
    (10, 2, 12, "NFP", "high"),
    (11, 6, 13, "NFP", "high"),
    (12, 4, 13, "NFP", "high"),
    # PPI
    (1, 15, 13, "PPI", "medium"),
    (2, 13, 13, "PPI", "medium"),
    (3, 12, 12, "PPI", "medium"),
    (4, 15, 12, "PPI", "medium"),
    (5, 14, 12, "PPI", "medium"),
    (6, 11, 12, "PPI", "medium"),
]


class EconomicCalendar:
    """经济日历: 重大事件前降低风险"""

    def __init__(self, buffer_hours_before: float = 2.0,
                 buffer_hours_after: float = 1.0):
        self._buffer_before = buffer_hours_before
        self._buffer_after = buffer_hours_after
        self._events = self._build_events()

    def _build_events(self) -> list:
        """构建完整事件列表"""
        events = []
        year = 2026
        for month, day, hour, name, impact in MAJOR_EVENTS_2026:
            try:
                dt = datetime(year, month, day, hour, 0, 0)
                events.append({
                    'time': dt,
                    'name': name,
                    'impact': impact,
                })
            except ValueError:
                continue
        events.sort(key=lambda e: e['time'])
        return events

    def check_upcoming(self, now: Optional[datetime] = None) -> Optional[dict]:
        """
        检查当前是否在重大事件前后的缓冲区内。
        返回: {'name': str, 'impact': str, 'hours_until': float, 'action': str}
              或 None (无影响)
        action: 'pause' | 'reduce' | None
        """
        if now is None:
            now = datetime.utcnow()

        for event in self._events:
            dt = event['time']
            diff_hours = (dt - now).total_seconds() / 3600

            # 事件前 buffer_before 小时内
            if 0 < diff_hours <= self._buffer_before:
                action = 'pause' if event['impact'] == 'high' else 'reduce'
                return {
                    'name': event['name'],
                    'impact': event['impact'],
                    'hours_until': diff_hours,
                    'action': action,
                }

            # 事件后 buffer_after 小时内
            if -self._buffer_after <= diff_hours <= 0:
                return {
                    'name': event['name'],
                    'impact': event['impact'],
                    'hours_until': diff_hours,
                    'action': 'reduce',
                }

            # 已经过了这个事件很久，跳过
            if diff_hours < -self._buffer_after:
                continue

            # 未来事件太远，不用继续
            if diff_hours > 24:
                break

        return None

    def get_risk_scale(self, now: Optional[datetime] = None) -> float:
        """返回风险缩放因子: 1.0=正常, 0.5=减半, 0.0=暂停"""
        result = self.check_upcoming(now)
        if not result:
            return 1.0
        if result['action'] == 'pause':
            return 0.0
        if result['action'] == 'reduce':
            return 0.5
        return 1.0
