"""Notification API - In-app notification center."""
from datetime import datetime, timezone
from flask import Blueprint, jsonify, request

from ..extensions import db
from ..middleware.auth_middleware import agent_required, get_current_user_id
from ..models.notification import Notification

notification_bp = Blueprint('notifications', __name__)


@notification_bp.route('/', methods=['GET'])
@agent_required
def list_notifications():
    """Get notifications for the current agent.

    Query params:
        unread_only: bool (default false)
        limit: int (default 50, max 100)
    """
    agent_id = get_current_user_id()
    unread_only = request.args.get('unread_only', 'false').lower() == 'true'
    limit = min(int(request.args.get('limit', 50)), 100)

    query = Notification.query.filter_by(agent_id=agent_id)
    if unread_only:
        query = query.filter_by(is_read=False)

    notifications = query.order_by(Notification.created_at.desc()).limit(limit).all()

    unread_count = Notification.query.filter_by(
        agent_id=agent_id, is_read=False
    ).count()

    return jsonify({
        'notifications': [n.to_dict() for n in notifications],
        'unread_count': unread_count,
    })


@notification_bp.route('/unread-count', methods=['GET'])
@agent_required
def unread_count():
    """Get unread notification count (lightweight endpoint for polling)."""
    agent_id = get_current_user_id()
    count = Notification.query.filter_by(
        agent_id=agent_id, is_read=False
    ).count()
    return jsonify({'unread_count': count})


@notification_bp.route('/<int:notification_id>/read', methods=['POST'])
@agent_required
def mark_read(notification_id):
    """Mark a single notification as read."""
    agent_id = get_current_user_id()
    notif = Notification.query.filter_by(
        id=notification_id, agent_id=agent_id
    ).first()
    if not notif:
        return jsonify({'error': 'Notification not found'}), 404

    notif.is_read = True
    notif.read_at = datetime.now(timezone.utc)
    db.session.commit()
    return jsonify({'ok': True})


@notification_bp.route('/read-all', methods=['POST'])
@agent_required
def mark_all_read():
    """Mark all notifications as read."""
    agent_id = get_current_user_id()
    now = datetime.now(timezone.utc)
    Notification.query.filter_by(
        agent_id=agent_id, is_read=False
    ).update({'is_read': True, 'read_at': now})
    db.session.commit()
    return jsonify({'ok': True})


@notification_bp.route('/<int:notification_id>', methods=['DELETE'])
@agent_required
def delete_notification(notification_id):
    """Delete a notification."""
    agent_id = get_current_user_id()
    notif = Notification.query.filter_by(
        id=notification_id, agent_id=agent_id
    ).first()
    if not notif:
        return jsonify({'error': 'Notification not found'}), 404

    db.session.delete(notif)
    db.session.commit()
    return jsonify({'ok': True})
