"""Bot Control API - Start/Stop/Status"""
from flask import Blueprint, jsonify, current_app

from ..middleware.auth_middleware import (
    agent_required, admin_required, get_current_user_id,
)
from ..models.bot_state import BotState
from ..engine.bot_manager import BotManager

bot_bp = Blueprint('bot', __name__)
bot_admin_bp = Blueprint('bot_admin', __name__)


def _get_manager() -> BotManager:
    return BotManager.get_instance(current_app._get_current_object())


# ─── Agent endpoints (/api/agent/bot/*) ──────────────────────

@bot_bp.route('/status', methods=['GET'])
@agent_required
def bot_status():
    agent_id = get_current_user_id()
    state = BotState.query.filter_by(agent_id=agent_id).first()
    if not state:
        return jsonify({'status': 'stopped'})
    result = state.to_dict()
    manager = _get_manager()
    live = manager.get_bot_status(agent_id)
    result['positions'] = live.get('positions', 0)
    result['scan_count_live'] = live.get('scan_count', 0)
    return jsonify(result)


@bot_bp.route('/logs', methods=['GET'])
@agent_required
def bot_logs():
    """Get recent bot activity log entries."""
    agent_id = get_current_user_id()
    manager = _get_manager()
    bot = manager._bots.get(agent_id)
    if not bot:
        return jsonify({'logs': [], 'message': 'Bot is not running'})
    logs = list(bot.activity_log)
    return jsonify({'logs': logs})


@bot_bp.route('/start', methods=['POST'])
@agent_required
def start_bot():
    agent_id = get_current_user_id()
    manager = _get_manager()
    success, message = manager.start_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'running'})


@bot_bp.route('/stop', methods=['POST'])
@agent_required
def stop_bot():
    agent_id = get_current_user_id()
    manager = _get_manager()
    success, message = manager.stop_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'stopped'})


@bot_bp.route('/pause', methods=['POST'])
@agent_required
def pause_bot():
    agent_id = get_current_user_id()
    manager = _get_manager()
    success, message = manager.pause_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'paused'})


@bot_bp.route('/resume', methods=['POST'])
@agent_required
def resume_bot():
    agent_id = get_current_user_id()
    manager = _get_manager()
    success, message = manager.resume_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'running'})


# ─── Admin endpoints (/api/admin/bots/*) ─────────────────────

@bot_admin_bp.route('/', methods=['GET'])
@admin_required
def admin_all_bots():
    """Admin: get all bots status with live info."""
    states = BotState.query.all()
    manager = _get_manager()
    result = []
    for state in states:
        d = state.to_dict()
        d['agent_id'] = state.agent_id
        live = manager.get_bot_status(state.agent_id)
        d['positions'] = live.get('positions', 0)
        d['thread_alive'] = live.get('thread_alive', False)
        result.append(d)
    return jsonify({'bots': result})


@bot_admin_bp.route('/<int:agent_id>/start', methods=['POST'])
@admin_required
def admin_start_bot(agent_id):
    """Admin: start a specific agent's bot."""
    manager = _get_manager()
    success, message = manager.start_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'running'})


@bot_admin_bp.route('/<int:agent_id>/stop', methods=['POST'])
@admin_required
def admin_stop_bot(agent_id):
    """Admin: stop a specific agent's bot."""
    manager = _get_manager()
    success, message = manager.stop_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'stopped'})


@bot_admin_bp.route('/<int:agent_id>/restart', methods=['POST'])
@admin_required
def admin_restart_bot(agent_id):
    """Admin: restart a specific agent's bot."""
    manager = _get_manager()
    success, message = manager.restart_bot(agent_id)
    if not success:
        return jsonify({'error': message}), 400
    return jsonify({'message': message, 'status': 'running'})
