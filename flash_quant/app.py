"""
Flash Quant - Flask Web 入口
本地开发: python3 app.py
生产: gunicorn -c gunicorn.conf.py app:app
"""
import random
import time
from datetime import datetime, timezone, timedelta
from flask import Flask, render_template, jsonify, request, redirect, url_for, session
from functools import wraps
from config.settings import settings
from core.logger import setup_logging
from core.constants import LEVERAGE_TIERS, get_leverage_tier

setup_logging(level=settings.LOG_LEVEL, json_format=False)


# === Mock 数据 (本地开发用, 服务器上会从 engine 读取) ===
_mock_signals = []
_mock_trades = []
_mock_positions = []


def _generate_mock_data():
    """生成模拟数据, 每次刷新更新"""
    global _mock_signals, _mock_trades, _mock_positions

    symbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
               'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'DOTUSDT',
               'NEARUSDT', 'APTUSDT', 'SUIUSDT', 'PEPEUSDT', 'ARBUSDT']
    tiers = ['tier1', 'tier2', 'tier3']
    decisions = ['executed', 'filtered', 'filtered', 'filtered', 'blocked']
    reasons = ['', 'wick', 'cvd_divergence', 'funding', 'circuit_breaker']
    directions = ['long', 'short']

    now = datetime.now(timezone.utc)

    # 生成信号
    if len(_mock_signals) < 50:
        for i in range(20):
            sym = random.choice(symbols)
            tier_config = get_leverage_tier(sym)
            dec_idx = random.randint(0, len(decisions) - 1)
            _mock_signals.append({
                'timestamp': (now - timedelta(minutes=random.randint(1, 1440))).strftime('%H:%M:%S'),
                'symbol': sym,
                'direction': random.choice(directions),
                'tier': random.choice(tiers),
                'volume_ratio': round(random.uniform(1.5, 12), 1),
                'price_change_pct': round(random.uniform(-5, 5), 2),
                'body_ratio': round(random.uniform(0.2, 0.9), 2),
                'cvd_aligned': random.choice([True, False]),
                'funding_passed': random.choice([True, True, True, False]),
                'final_decision': decisions[dec_idx],
                'filter_reason': reasons[dec_idx],
            })
        _mock_signals.sort(key=lambda x: x['timestamp'], reverse=True)

    # 生成交易
    if len(_mock_trades) < 30:
        for i in range(15):
            sym = random.choice(symbols)
            d = random.choice(directions)
            tier_config = get_leverage_tier(sym)
            entry = round(random.uniform(0.5, 70000), 2)
            pnl_pct = round(random.uniform(-0.1, 0.3), 4)
            exit_p = round(entry * (1 + pnl_pct / tier_config['max_leverage']), 2)
            pnl = round(300 * pnl_pct, 2)
            _mock_trades.append({
                'time': (now - timedelta(hours=random.randint(1, 72))).strftime('%m-%d %H:%M'),
                'symbol': sym,
                'direction': d,
                'tier': random.choice(tiers),
                'leverage': tier_config['max_leverage'],
                'entry_price': entry,
                'exit_price': exit_p,
                'pnl': pnl,
                'pnl_pct': pnl_pct,
                'close_reason': random.choice(['take_profit', 'stop_loss', 'timeout', 'trailing']),
            })

    # 生成持仓 (0-3个)
    _mock_positions = []
    n_pos = random.randint(0, 3)
    for i in range(n_pos):
        sym = random.choice(symbols[:5])
        d = random.choice(directions)
        tier_config = get_leverage_tier(sym)
        entry = round(random.uniform(50, 70000), 2)
        current = round(entry * (1 + random.uniform(-0.02, 0.03)), 2)
        if d == 'long':
            roi = (current - entry) / entry * tier_config['max_leverage']
        else:
            roi = (entry - current) / entry * tier_config['max_leverage']
        _mock_positions.append({
            'symbol': sym,
            'direction': d,
            'tier': random.choice(tiers),
            'leverage': tier_config['max_leverage'],
            'entry_price': entry,
            'current_price': current,
            'roi': round(roi, 4),
            'stop_loss': round(entry * (0.999 if d == 'long' else 1.001), 2),
            'open_time': (now - timedelta(minutes=random.randint(5, 120))).strftime('%H:%M:%S'),
        })


def _get_stats():
    """汇总统计"""
    trades = _mock_trades
    if not trades:
        return {'total': 0, 'wins': 0, 'win_rate': 0, 'total_pnl': 0}
    wins = [t for t in trades if t['pnl'] > 0]
    total_pnl = sum(t['pnl'] for t in trades)
    return {
        'total': len(trades),
        'wins': len(wins),
        'losses': len(trades) - len(wins),
        'win_rate': len(wins) / len(trades) if trades else 0,
        'total_pnl': round(total_pnl, 2),
    }


def create_app():
    app = Flask(__name__,
                template_folder='web/templates',
                static_folder='web/static')
    app.config['SECRET_KEY'] = settings.WEB_SECRET_KEY

    # 注入配置到模板
    @app.context_processor
    def inject_config():
        return {'config': {
            'PHASE': settings.PHASE,
            'TRADING_MODE': settings.TRADING_MODE,
        }}

    # === 认证 ===
    def login_required(f):
        @wraps(f)
        def decorated(*args, **kwargs):
            if not session.get('logged_in'):
                return redirect(url_for('login'))
            return f(*args, **kwargs)
        return decorated

    @app.route('/login', methods=['GET', 'POST'])
    def login():
        if request.method == 'POST':
            user = request.form.get('username', '')
            pwd = request.form.get('password', '')
            if user == settings.WEB_AUTH_USER and pwd:
                session['logged_in'] = True
                return redirect(url_for('home'))
            return render_template('login.html', error='Invalid credentials')
        return render_template('login.html')

    @app.route('/logout')
    def logout():
        session.pop('logged_in', None)
        return redirect(url_for('login'))

    # === Pages ===
    @app.route('/')
    @login_required
    def home():
        _generate_mock_data()
        stats = _get_stats()
        return render_template('home.html',
                             positions=_mock_positions,
                             signals=_mock_signals[:20],
                             stats=stats)

    @app.route('/signals')
    @login_required
    def signals_page():
        _generate_mock_data()
        return render_template('signals.html', signals=_mock_signals)

    @app.route('/trades')
    @login_required
    def trades_page():
        _generate_mock_data()
        return render_template('trades.html', trades=_mock_trades)

    @app.route('/risk')
    @login_required
    def risk_page():
        return render_template('risk.html')

    # === API ===
    @app.route('/api/status')
    @login_required
    def api_status():
        return jsonify({
            'mode': settings.TRADING_MODE,
            'phase': settings.PHASE,
            'status': 'running',
        })

    @app.route('/api/stats')
    @login_required
    def api_stats():
        return jsonify(_get_stats())

    @app.route('/health')
    def health():
        return jsonify({'status': 'ok'})

    return app


app = create_app()

if __name__ == "__main__":
    print(f"\n🚀 Flash Quant Dashboard starting...")
    print(f"   Mode: {settings.TRADING_MODE.upper()}")
    print(f"   Phase: {settings.PHASE}")
    print(f"   URL: http://localhost:{settings.WEB_PORT}")
    print(f"   Login: {settings.WEB_AUTH_USER} / (any password)\n")
    app.run(host='0.0.0.0', port=settings.WEB_PORT, debug=True)
