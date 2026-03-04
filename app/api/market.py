"""Market Data API - Prices and K-lines"""
import requests as req
from flask import Blueprint, request, jsonify

from ..middleware.auth_middleware import any_auth_required

market_bp = Blueprint('market', __name__)

BINANCE_FUTURES = 'https://fapi.binance.com'

# Symbol mapping for 1000x tokens
SYMBOL_1000 = {'BONK', 'PEPE', 'SHIB', 'FLOKI'}


def _binance_symbol(symbol: str) -> str:
    s = symbol.upper().replace('USDT', '')
    if s in SYMBOL_1000:
        return f'1000{s}USDT'
    return f'{s}USDT'


@market_bp.route('/price/<symbol>', methods=['GET'])
@any_auth_required
def get_price(symbol):
    try:
        bs = _binance_symbol(symbol)
        resp = req.get(
            f'{BINANCE_FUTURES}/fapi/v1/ticker/price',
            params={'symbol': bs}, timeout=5
        )
        data = resp.json()
        if 'price' in data:
            return jsonify({
                'symbol': symbol,
                'binance_symbol': bs,
                'price': float(data['price']),
            })
        return jsonify({'error': 'Symbol not found'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500


@market_bp.route('/kline/<symbol>', methods=['GET'])
@any_auth_required
def get_kline(symbol):
    try:
        bs = _binance_symbol(symbol)
        interval = request.args.get('interval', '1h')
        limit = request.args.get('limit', 100, type=int)
        limit = min(limit, 1000)

        resp = req.get(
            f'{BINANCE_FUTURES}/fapi/v1/klines',
            params={'symbol': bs, 'interval': interval, 'limit': limit},
            timeout=10
        )
        raw = resp.json()
        candles = [{
            'time': c[0],
            'open': float(c[1]),
            'high': float(c[2]),
            'low': float(c[3]),
            'close': float(c[4]),
            'volume': float(c[5]),
        } for c in raw]

        return jsonify({
            'symbol': symbol,
            'interval': interval,
            'candles': candles,
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500
