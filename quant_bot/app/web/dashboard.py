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
from app.db import trade_store

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
        bot = create_bot(paper_mode=True, sandbox=False)
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
    def _trade_to_dict(t):
        return {
            'symbol': t.symbol,
            'direction': 'LONG' if t.direction == 1 else 'SHORT',
            'entry': t.entry_price,
            'exit': t.exit_price,
            'pnl': round(t.pnl, 2),
            'pnl_pct': round(t.pnl_pct * 100, 2),
            'fees': round(t.fees, 4),
            'funding_fees': round(t.funding_fees, 4),
            'net_pnl': round(t.net_pnl, 2),
            'reason': t.close_reason,
            'setup': t.setup_type,
            'opened': t.opened_at.strftime('%Y-%m-%d %H:%M'),
            'closed': t.closed_at.strftime('%Y-%m-%d %H:%M'),
        }
    return jsonify([_trade_to_dict(t) for t in bot.position_manager.trade_history])


@app.route('/api/db/trades')
def api_db_trades():
    """从数据库获取历史交易"""
    limit = request.args.get('limit', 200, type=int)
    trades = trade_store.load_trade_history(limit)
    return jsonify([
        {
            'symbol': t.symbol,
            'direction': 'LONG' if t.direction == 1 else 'SHORT',
            'entry': t.entry_price,
            'exit': t.exit_price,
            'pnl': round(t.pnl, 2),
            'pnl_pct': round(t.pnl_pct * 100, 2),
            'fees': round(t.fees, 4),
            'funding_fees': round(t.funding_fees, 4),
            'net_pnl': round(t.net_pnl, 2),
            'reason': t.close_reason,
            'setup': t.setup_type,
            'opened': t.opened_at.strftime('%Y-%m-%d %H:%M'),
            'closed': t.closed_at.strftime('%Y-%m-%d %H:%M'),
        }
        for t in trades
    ])


@app.route('/api/db/stats')
def api_db_stats():
    """数据库统计"""
    total = trade_store.get_trade_count()
    daily = trade_store.get_daily_stats(30)
    fee_summary = trade_store.get_fee_summary()
    return jsonify({'total_trades': total, 'daily_stats': daily, 'fee_summary': fee_summary})


@app.route('/api/logs')
def api_logs():
    bot = get_bot()
    if bot is None:
        return jsonify([])
    return jsonify(bot.logs[-200:])


@app.route('/api/optimize', methods=['POST'])
def api_optimize():
    """触发参数调优 (异步)"""
    import threading
    bot = get_bot()
    if bot is None:
        return jsonify({'error': 'Bot not initialized'}), 400

    metric = request.json.get('metric', 'sharpe') if request.json else 'sharpe'
    symbols = request.json.get('symbols', None) if request.json else None

    def _run_optimization():
        try:
            from app.backtest.optimizer import ParameterOptimizer
            # 用活跃池中的币种做回测数据
            symbol_data = {}
            test_symbols = symbols or [s['symbol'] for s in bot.active_pool[:6]]
            for sym in test_symbols:
                df = bot.cache.get(sym, '15m', force=True)
                if df is not None and len(df) > 60:
                    symbol_data[sym] = df

            if not symbol_data:
                bot._log('warning', '参数调优: 无可用数据')
                return

            optimizer = ParameterOptimizer(symbol_data, list(symbol_data.keys()))
            results = optimizer.optimize(metric=metric, top_n=5)
            bot._optimizer_results = results
            summary = optimizer.get_summary(5)
            bot._log('info', f'参数调优完成: {len(results)}个结果')
            bot._log('info', summary[:500])
        except Exception as e:
            bot._log('error', f'参数调优失败: {e}')

    threading.Thread(target=_run_optimization, daemon=True).start()
    return jsonify({'ok': True, 'msg': f'参数调优已启动, 目标={metric}'})


@app.route('/api/optimize/results')
def api_optimize_results():
    """获取调优结果"""
    bot = get_bot()
    if bot is None or not hasattr(bot, '_optimizer_results'):
        return jsonify({'results': [], 'msg': '无调优结果'})
    return jsonify({'results': bot._optimizer_results})


def create_app():
    config_path = os.environ.get('QUANT_CONFIG', None)
    if config_path:
        load_config(config_path)
    else:
        load_config()

    # 注册 Prometheus /metrics 端点 (Spec §20)
    from app.monitoring.metrics_exporter import register_metrics_endpoint
    register_metrics_endpoint(app)

    # Auto-create bot in paper mode and start immediately
    bot = create_bot(paper_mode=True, sandbox=False)
    bot.start()
    return app


if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(name)s: %(message)s')
    application = create_app()
    application.run(host='0.0.0.0', port=5001, debug=False)
