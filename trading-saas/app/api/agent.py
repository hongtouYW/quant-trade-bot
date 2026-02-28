"""Agent API - Profile, API Keys, Telegram, Strategy Config"""
import json as _json
from datetime import datetime, timezone
from flask import Blueprint, request, jsonify
from ..middleware.auth_middleware import agent_required, get_current_user_id
from ..services.encryption_service import EncryptionService
from ..services.audit_service import log_action
from ..models.agent import Agent
from ..models.agent_config import AgentApiKey, AgentTelegramConfig, AgentTradingConfig
from ..models.strategy_preset import StrategyPreset
from ..extensions import db

agent_bp = Blueprint('agent', __name__)


@agent_bp.route('/dashboard', methods=['GET'])
@agent_required
def dashboard():
    agent_id = get_current_user_id()
    agent = Agent.query.get(agent_id)
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404

    from sqlalchemy import func
    from ..models.trade import Trade

    # Trade stats
    stats = (
        db.session.query(
            func.count(Trade.id),
            func.sum(Trade.pnl),
            func.sum(db.case((Trade.pnl > 0, 1), else_=0)),
        )
        .filter(Trade.agent_id == agent_id, Trade.status == 'CLOSED')
        .first()
    )

    open_positions = Trade.query.filter_by(
        agent_id=agent_id, status='OPEN'
    ).count()

    total_trades = stats[0] or 0
    total_pnl = float(stats[1]) if stats[1] else 0
    win_trades = int(stats[2]) if stats[2] else 0
    initial_capital = float(agent.trading_config.initial_capital) if agent.trading_config else 2000

    return jsonify({
        'agent': agent.to_dict(),
        'stats': {
            'initial_capital': initial_capital,
            'current_capital': initial_capital + total_pnl,
            'total_pnl': total_pnl,
            'total_trades': total_trades,
            'win_trades': win_trades,
            'win_rate': (win_trades / total_trades * 100) if total_trades > 0 else 0,
            'open_positions': open_positions,
        }
    })


@agent_bp.route('/profile', methods=['GET'])
@agent_required
def get_profile():
    agent_id = get_current_user_id()
    agent = Agent.query.get(agent_id)
    if not agent:
        return jsonify({'error': 'Agent not found'}), 404
    return jsonify(agent.to_dict(include_config=True))


@agent_bp.route('/profile', methods=['PUT'])
@agent_required
def update_profile():
    agent_id = get_current_user_id()
    agent = Agent.query.get(agent_id)
    data = request.get_json()

    updatable = ['display_name', 'phone']
    for field in updatable:
        if field in data:
            setattr(agent, field, data[field])
    db.session.commit()
    return jsonify(agent.to_dict())


# === Binance Balance ===

@agent_bp.route('/balance', methods=['GET'])
@agent_required
def get_balance():
    """Fetch real-time USDT balance from Binance."""
    agent_id = get_current_user_id()
    record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if not record or not record.permissions_verified:
        return jsonify({'error': 'API keys not configured or not verified'}), 400

    try:
        import ccxt
        combined_json = EncryptionService.decrypt(record.binance_api_key_enc, record.encryption_iv)
        keys = _json.loads(combined_json)

        exchange = ccxt.binance({
            'apiKey': keys['k'],
            'secret': keys['s'],
            'enableRateLimit': True,
            'options': {'defaultType': 'future'},
        })
        if record.is_testnet:
            exchange.set_sandbox_mode(True)

        balance = exchange.fetch_balance()
        usdt = balance.get('USDT', {})

        return jsonify({
            'total': float(usdt.get('total', 0)),
            'free': float(usdt.get('free', 0)),
            'used': float(usdt.get('used', 0)),
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 400


# === API Keys ===

@agent_bp.route('/api-keys', methods=['PUT'])
@agent_required
def set_api_keys():
    agent_id = get_current_user_id()
    data = request.get_json()

    if not data.get('api_key') or not data.get('api_secret'):
        return jsonify({'error': 'api_key and api_secret required'}), 400

    # Encrypt both key+secret as a single JSON blob with one nonce
    combined = _json.dumps({'k': data['api_key'], 's': data['api_secret']})
    blob_enc, nonce = EncryptionService.encrypt(combined)

    existing = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if existing:
        existing.binance_api_key_enc = blob_enc
        existing.binance_api_secret_enc = b''
        existing.encryption_iv = nonce
        existing.is_testnet = data.get('is_testnet', False)
        existing.permissions_verified = False
    else:
        api_key_record = AgentApiKey(
            agent_id=agent_id,
            binance_api_key_enc=blob_enc,
            binance_api_secret_enc=b'',
            encryption_iv=nonce,
            is_testnet=data.get('is_testnet', False),
        )
        db.session.add(api_key_record)

    db.session.commit()

    log_action('agent', agent_id, 'set_api_keys',
               details={'is_testnet': data.get('is_testnet', False)})

    return jsonify({'message': 'API keys saved', 'has_api_key': True})


@agent_bp.route('/api-keys', methods=['DELETE'])
@agent_required
def delete_api_keys():
    agent_id = get_current_user_id()
    existing = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if existing:
        db.session.delete(existing)
        db.session.commit()
    log_action('agent', agent_id, 'delete_api_keys')
    return jsonify({'message': 'API keys removed'})


@agent_bp.route('/api-keys/status', methods=['GET'])
@agent_required
def api_keys_status():
    agent_id = get_current_user_id()
    record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if not record:
        return jsonify({'has_api_key': False})
    return jsonify(record.to_dict())


@agent_bp.route('/api-keys/verify', methods=['POST'])
@agent_required
def verify_api_keys():
    """Test Binance API connectivity with stored keys."""
    agent_id = get_current_user_id()
    record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
    if not record:
        return jsonify({'error': 'No API keys configured'}), 400

    try:
        import ccxt
        combined_json = EncryptionService.decrypt(record.binance_api_key_enc, record.encryption_iv)
        keys = _json.loads(combined_json)
        api_key = keys['k']
        api_secret = keys['s']

        exchange = ccxt.binance({
            'apiKey': api_key,
            'secret': api_secret,
            'enableRateLimit': True,
            'options': {'defaultType': 'future'},
        })
        if record.is_testnet:
            exchange.set_sandbox_mode(True)

        balance = exchange.fetch_balance()
        usdt_balance = balance.get('USDT', {}).get('total', 0)

        record.permissions_verified = True
        record.last_verified_at = datetime.now(timezone.utc)
        db.session.commit()

        return jsonify({
            'verified': True,
            'usdt_balance': usdt_balance,
            'message': 'API keys verified successfully',
        })
    except Exception as e:
        record.permissions_verified = False
        db.session.commit()
        return jsonify({'verified': False, 'error': str(e)}), 400


# === Telegram ===

@agent_bp.route('/telegram', methods=['GET'])
@agent_required
def get_telegram():
    agent_id = get_current_user_id()
    tg = AgentTelegramConfig.query.filter_by(agent_id=agent_id).first()
    if not tg:
        return jsonify({'configured': False})
    return jsonify({
        'configured': True,
        'chat_id': tg.chat_id,
        'is_enabled': tg.is_enabled,
    })


@agent_bp.route('/telegram', methods=['PUT'])
@agent_required
def set_telegram():
    agent_id = get_current_user_id()
    data = request.get_json()

    if not data.get('bot_token') or not data.get('chat_id'):
        return jsonify({'error': 'bot_token and chat_id required'}), 400

    token_enc, nonce = EncryptionService.encrypt(data['bot_token'])

    existing = AgentTelegramConfig.query.filter_by(agent_id=agent_id).first()
    if existing:
        existing.bot_token_enc = token_enc
        existing.chat_id = data['chat_id']
        existing.encryption_iv = nonce
        existing.is_enabled = data.get('is_enabled', True)
    else:
        tg = AgentTelegramConfig(
            agent_id=agent_id,
            bot_token_enc=token_enc,
            chat_id=data['chat_id'],
            encryption_iv=nonce,
            is_enabled=data.get('is_enabled', True),
        )
        db.session.add(tg)

    db.session.commit()
    log_action('agent', agent_id, 'set_telegram')
    return jsonify({'message': 'Telegram config saved'})


@agent_bp.route('/telegram/test', methods=['POST'])
@agent_required
def test_telegram():
    """Send a test message via agent's Telegram bot."""
    agent_id = get_current_user_id()
    tg = AgentTelegramConfig.query.filter_by(agent_id=agent_id).first()
    if not tg:
        return jsonify({'error': 'No Telegram config'}), 400

    try:
        import requests as req
        bot_token = EncryptionService.decrypt(tg.bot_token_enc, tg.encryption_iv)
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        resp = req.post(url, json={
            'chat_id': tg.chat_id,
            'text': '🤖 Trading SaaS test message - connection successful!',
        }, timeout=10)
        if resp.status_code == 200:
            return jsonify({'success': True, 'message': 'Test message sent'})
        return jsonify({'success': False, 'error': resp.text}), 400
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 400


# === Trading Config ===

@agent_bp.route('/trading/config', methods=['GET'])
@agent_required
def get_trading_config():
    agent_id = get_current_user_id()
    config = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    if not config:
        return jsonify({'error': 'No trading config'}), 404
    return jsonify({'config': config.to_dict()})


@agent_bp.route('/trading/config', methods=['PUT'])
@agent_required
def update_trading_config():
    agent_id = get_current_user_id()
    config = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    if not config:
        config = AgentTradingConfig(agent_id=agent_id)
        db.session.add(config)

    data = request.get_json()
    updatable = [
        'initial_capital', 'max_positions', 'max_position_size',
        'max_leverage', 'min_score', 'long_min_score', 'fee_rate',
        'cooldown_minutes', 'roi_stop_loss', 'roi_trailing_start',
        'roi_trailing_distance', 'daily_loss_limit', 'max_drawdown_pct',
        'enable_trend_filter', 'enable_btc_filter', 'short_bias',
        'custom_params',
    ]
    for field in updatable:
        if field in data:
            value = data[field]
            if field == 'custom_params' and isinstance(value, dict):
                import json
                value = json.dumps(value)
            setattr(config, field, value)

    db.session.commit()
    log_action('agent', agent_id, 'update_trading_config',
               details={k: data[k] for k in updatable if k in data})
    return jsonify(config.to_dict())


@agent_bp.route('/trading/strategies', methods=['GET'])
@agent_required
def list_strategies():
    presets = StrategyPreset.query.filter_by(is_active=True).all()
    return jsonify({'strategies': [p.to_dict() for p in presets]})


@agent_bp.route('/trading/strategy/<version>', methods=['POST'])
@agent_required
def switch_strategy(version):
    """Apply a strategy preset to agent's trading config."""
    agent_id = get_current_user_id()
    preset = StrategyPreset.query.filter_by(version=version, is_active=True).first()
    if not preset:
        return jsonify({'error': f'Strategy {version} not found'}), 404

    config = AgentTradingConfig.query.filter_by(agent_id=agent_id).first()
    if not config:
        config = AgentTradingConfig(agent_id=agent_id)
        db.session.add(config)

    config.strategy_version = version

    # Apply preset config values (config is JSON text in MariaDB 5.5)
    import json
    preset_config = json.loads(preset.config) if preset.config else {}
    field_map = {
        'min_score': 'min_score',
        'long_min_score': 'long_min_score',
        'max_leverage': 'max_leverage',
        'max_positions': 'max_positions',
        'roi_stop_loss': 'roi_stop_loss',
        'roi_trailing_start': 'roi_trailing_start',
        'roi_trailing_distance': 'roi_trailing_distance',
        'enable_trend_filter': 'enable_trend_filter',
        'enable_btc_filter': 'enable_btc_filter',
        'short_bias': 'short_bias',
    }
    for preset_key, config_field in field_map.items():
        if preset_key in preset_config:
            setattr(config, config_field, preset_config[preset_key])

    db.session.commit()
    log_action('agent', agent_id, 'switch_strategy',
               details={'version': version})
    return jsonify({
        'message': f'Switched to strategy {version}',
        'config': config.to_dict(),
    })
