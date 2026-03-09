"""Market Data API - Prices and K-lines (Binance / Bitget)"""
import logging
from flask import Blueprint, request, jsonify

from ..middleware.auth_middleware import any_auth_required, get_current_user_id
from ..engine.signal_analyzer import fetch_price as sa_fetch_price, fetch_klines as sa_fetch_klines

logger = logging.getLogger(__name__)

market_bp = Blueprint('market', __name__)


def _get_agent_exchange() -> str:
    """Get exchange name from current agent's API key config."""
    try:
        agent_id = get_current_user_id()
        if agent_id:
            from ..models.agent_config import AgentApiKey
            record = AgentApiKey.query.filter_by(agent_id=agent_id).first()
            if record:
                return record.exchange or 'binance'
    except Exception:
        pass
    return 'binance'


@market_bp.route('/price/<symbol>', methods=['GET'])
@any_auth_required
def get_price(symbol):
    try:
        exchange = _get_agent_exchange()
        price = sa_fetch_price(symbol, exchange=exchange)
        if price is not None:
            return jsonify({
                'symbol': symbol,
                'price': price,
            })
        return jsonify({'error': 'Symbol not found'}), 404
    except Exception as e:
        logger.exception(f"Price fetch failed for {symbol}")
        return jsonify({'error': 'Failed to fetch price data'}), 500


@market_bp.route('/kline/<symbol>', methods=['GET'])
@any_auth_required
def get_kline(symbol):
    try:
        exchange = _get_agent_exchange()
        interval = request.args.get('interval', '1h')
        limit = request.args.get('limit', 100, type=int)
        limit = min(limit, 1000)

        candles = sa_fetch_klines(symbol, interval=interval, limit=limit,
                                  exchange=exchange)
        if candles is None:
            return jsonify({'error': 'Failed to fetch kline data'}), 500

        return jsonify({
            'symbol': symbol,
            'interval': interval,
            'candles': candles,
        })
    except Exception as e:
        logger.exception(f"Kline fetch failed for {symbol}")
        return jsonify({'error': 'Failed to fetch kline data'}), 500
