"""Audit Logging Service"""
import json
from flask import request
from ..extensions import db
from ..models.audit import AuditLog


def log_action(user_type: str, user_id: int, action: str,
               resource: str = None, details: dict = None):
    """Record an action in the audit log."""
    entry = AuditLog(
        user_type=user_type,
        user_id=user_id,
        action=action,
        resource=resource,
        details=json.dumps(details) if details else None,
        ip_address=request.remote_addr if request else None,
    )
    db.session.add(entry)
    db.session.commit()
