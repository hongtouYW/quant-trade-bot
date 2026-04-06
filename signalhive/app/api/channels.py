"""Channel CRUD API endpoints."""
from flask import Blueprint, request, jsonify
from flask_login import login_required, current_user
from ..models.channel import Channel
from ..extensions import db

channels_bp = Blueprint('channels_api', __name__)


@channels_bp.route('', methods=['POST'])
@login_required
def create_channel():
    data = request.json or {}
    platform = data.get('platform', 'telegram')
    source_url = data.get('source_url', '').strip()
    source_name = data.get('source_name', '').strip()

    if not source_url:
        return jsonify({'error': 'source_url required'}), 400

    if platform not in ('telegram', 'twitter', 'weibo', 'youtube', 'facebook'):
        return jsonify({'error': 'Invalid platform'}), 400

    ch = Channel(
        user_id=current_user.id,
        platform=platform,
        source_url=source_url,
        source_name=source_name or source_url,
        status='active',
        module=data.get('module', 'realtime'),
        content_type=data.get('content_type', 'text'),
    )
    db.session.add(ch)
    db.session.commit()

    return jsonify(ch.to_dict()), 201


@channels_bp.route('', methods=['GET'])
@login_required
def list_channels():
    channels = Channel.query.filter_by(user_id=current_user.id).order_by(Channel.created_at.desc()).all()
    return jsonify([ch.to_dict() for ch in channels])


@channels_bp.route('/<int:channel_id>', methods=['DELETE'])
@login_required
def delete_channel(channel_id):
    ch = Channel.query.filter_by(id=channel_id, user_id=current_user.id).first()
    if not ch:
        return jsonify({'error': 'Channel not found'}), 404
    db.session.delete(ch)
    db.session.commit()
    return jsonify({'ok': True})


@channels_bp.route('/<int:channel_id>', methods=['PUT'])
@login_required
def update_channel(channel_id):
    ch = Channel.query.filter_by(id=channel_id, user_id=current_user.id).first()
    if not ch:
        return jsonify({'error': 'Channel not found'}), 404

    data = request.json or {}
    if 'status' in data:
        ch.status = data['status']
    if 'source_name' in data:
        ch.source_name = data['source_name']

    db.session.commit()
    return jsonify(ch.to_dict())


@channels_bp.route('/<int:channel_id>/health', methods=['GET'])
@login_required
def channel_health(channel_id):
    ch = Channel.query.filter_by(id=channel_id, user_id=current_user.id).first()
    if not ch:
        return jsonify({'error': 'Channel not found'}), 404

    return jsonify({
        'channel_id': ch.id,
        'status': ch.status,
        'health_status': ch.health_status,
        'last_message_at': ch.last_message_at.isoformat() if ch.last_message_at else None,
    })
