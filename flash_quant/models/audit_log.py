"""
AuditLog 模型 - 审计日志表
"""
import json
from sqlalchemy import (
    Table, Column, BigInteger, String, DateTime, Enum, JSON, Index, func
)
from models.base import metadata

audit_logs = Table(
    'audit_logs', metadata,
    Column('id', BigInteger, primary_key=True, autoincrement=True),
    Column('timestamp', DateTime, server_default=func.now()),
    Column('actor', String(50), nullable=False),
    Column('action', String(100), nullable=False),
    Column('target', String(100)),
    Column('details', JSON, nullable=False),  # 永远 json.dumps()
    Column('severity', Enum('info', 'warning', 'error', 'critical'), default='info'),

    Index('idx_audit_timestamp', 'timestamp'),
    Index('idx_audit_action', 'action'),
    Index('idx_audit_severity', 'severity'),
)


def make_audit_details(**kwargs) -> str:
    """确保 audit details 永远是 JSON 字符串 (已知陷阱防护)"""
    return json.dumps(kwargs, ensure_ascii=False, default=str)
