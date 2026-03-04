"""WebSocket event handlers and emitters for real-time updates."""
from flask_jwt_extended import decode_token
from ..extensions import socketio

# Track connected agents: sid -> agent_id
_connected = {}


@socketio.on('connect')
def on_connect(auth):
    """Authenticate WebSocket connection using JWT token."""
    token = (auth or {}).get('token', '')
    if not token:
        return False  # reject
    try:
        data = decode_token(token)
        agent_id = data.get('sub')
        if not agent_id:
            return False
        from flask import request
        _connected[request.sid] = agent_id
        socketio.emit('connected', {'agent_id': agent_id}, to=request.sid)
    except Exception:
        return False  # reject invalid token


@socketio.on('disconnect')
def on_disconnect():
    from flask import request
    _connected.pop(request.sid, None)


def emit_bot_status(agent_id: int, status_data: dict):
    """Push bot status update to a specific agent."""
    for sid, aid in _connected.items():
        if aid == agent_id:
            socketio.emit('bot_status', status_data, to=sid)


def emit_trade_event(agent_id: int, event_type: str, data: dict):
    """Push trade open/close event to a specific agent."""
    for sid, aid in _connected.items():
        if aid == agent_id:
            socketio.emit('trade_event', {'type': event_type, **data}, to=sid)


def emit_notification(agent_id: int, notification: dict):
    """Push new notification to a specific agent."""
    for sid, aid in _connected.items():
        if aid == agent_id:
            socketio.emit('notification', notification, to=sid)


def emit_signal_update(agent_id: int, signals_data: dict):
    """Push signal scan result to a specific agent."""
    for sid, aid in _connected.items():
        if aid == agent_id:
            socketio.emit('signal_update', signals_data, to=sid)
