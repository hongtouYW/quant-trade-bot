"""Flask web dashboard for QuantBot"""
import os
import sys
import json
import logging
from datetime import datetime
from flask import Flask, render_template, jsonify, request

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from app.main import create_bot, get_bot
from app.config import load_config, get

log = logging.getLogger(__name__)

app = Flask(__name__,
            template_folder=os.path.join(os.path.dirname(__file__), 'templates'),
            static_folder=os.path.join(os.path.dirname(__file__), 'static'))


@app.route('/')
def index():
    return render_template('index.html')


@app.route('/api/status')
def api_status():
    bot = get_bot()
    if bot is None:
        return jsonify({'running': False, 'error': 'Bot not initialized'})
    try:
        status = bot.get_status()
        return jsonify(status)
    except Exception as e:
        return jsonify({'running': False, 'error': str(e)})


@app.route('/api/start', methods=['POST'])
def api_start():
    bot = get_bot()
    if bot is None:
        bot = create_bot(paper_mode=True, sandbox=True)
    bot.start()
    return jsonify({'ok': True, 'msg': 'Bot started'})


@app.route('/api/stop', methods=['POST'])
def api_stop():
    bot = get_bot()
    if bot:
        bot.stop()
    return jsonify({'ok': True, 'msg': 'Bot stopped'})


@app.route('/api/config')
def api_config():
    cfg = {}
    try:
        from app.config import get_config
        cfg = get_config()
    except Exception:
        pass
    return jsonify(cfg)


@app.route('/api/pool')
def api_pool():
    bot = get_bot()
    if bot is None:
        return jsonify([])
    return jsonify(bot.active_pool)


@app.route('/api/trades')
def api_trades():
    bot = get_bot()
    if bot is None:
        return jsonify([])
    return jsonify([
        {
            'symbol': t.symbol,
            'direction': 'LONG' if t.direction == 1 else 'SHORT',
            'entry': t.entry_price,
            'exit': t.exit_price,
            'pnl': round(t.pnl, 2),
            'pnl_pct': round(t.pnl_pct * 100, 2),
            'reason': t.close_reason,
            'setup': t.setup_type,
            'opened': t.opened_at.strftime('%Y-%m-%d %H:%M'),
            'closed': t.closed_at.strftime('%Y-%m-%d %H:%M'),
        }
        for t in bot.position_manager.trade_history
    ])


@app.route('/api/logs')
def api_logs():
    bot = get_bot()
    if bot is None:
        return jsonify([])
    return jsonify(bot.logs[-200:])


def create_app():
    config_path = os.environ.get('QUANT_CONFIG', None)
    if config_path:
        load_config(config_path)
    else:
        load_config()

    # Auto-create bot in paper mode and start immediately
    bot = create_bot(paper_mode=True, sandbox=True)
    bot.start()
    return app


if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(name)s: %(message)s')
    application = create_app()
    application.run(host='0.0.0.0', port=5001, debug=False)
