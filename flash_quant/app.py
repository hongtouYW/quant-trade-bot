"""
Flash Quant - Flask Web 入口
所有数据从 MySQL 读取, 不再用 mock
"""
import json
from datetime import datetime, timezone, timedelta
from flask import Flask, render_template, jsonify, request, redirect, url_for, session, Response
from functools import wraps
from config.settings import settings
from core.logger import setup_logging
from core.constants import get_leverage_tier

setup_logging(level=settings.LOG_LEVEL, json_format=False)

MYT = timezone(timedelta(hours=8))


def create_app():
    app = Flask(__name__,
                template_folder='web/templates',
                static_folder='web/static')
    app.config['SECRET_KEY'] = settings.WEB_SECRET_KEY

    @app.context_processor
    def inject_config():
        return {'config': {
            'PHASE': settings.PHASE,
            'TRADING_MODE': settings.TRADING_MODE,
        }}

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
            if request.form.get('username') == settings.WEB_AUTH_USER and request.form.get('password'):
                session['logged_in'] = True
                return redirect(url_for('home'))
            return render_template('login.html', error='登录失败')
        return render_template('login.html')

    @app.route('/logout')
    def logout():
        session.pop('logged_in', None)
        return redirect(url_for('login'))

    @app.route('/')
    @login_required
    def home():
        from models.db_ops import (
            get_open_positions, query_signals, get_dashboard_stats,
            get_daily_stats, count_signals_today,
        )
        positions = get_open_positions()
        signals = query_signals(limit=20)
        stats = get_dashboard_stats()

        # 今日信号统计
        stats['signals_today'] = count_signals_today()
        # 按 tier 分
        today_signals = [s for s in query_signals(limit=200)
                         if s.get('timestamp') and hasattr(s['timestamp'], 'date')
                         and s['timestamp'].date() == datetime.now(MYT).date()]
        stats['tier1_today'] = len([s for s in today_signals if s.get('tier') == 'tier1'])
        stats['tier2_today'] = len([s for s in today_signals if s.get('tier') == 'tier2'])
        stats['tier3_today'] = len([s for s in today_signals if s.get('tier') == 'tier3'])

        # 资金曲线
        daily = get_daily_stats(days=30)
        equity_curve = []
        if daily:
            for d in reversed(daily):
                equity_curve.append({
                    'date': d['date'].isoformat() if hasattr(d['date'], 'isoformat') else str(d['date']),
                    'balance': d.get('ending_balance') or 10000,
                })
        if not equity_curve:
            equity_curve = [{'date': datetime.now(MYT).strftime('%Y-%m-%d'), 'balance': 10000 + stats['total_pnl']}]

        # 丰富 position 数据
        for p in positions:
            sym = p.get('symbol', '')
            tc = get_leverage_tier(sym)
            lev = p.get('leverage') or tc['max_leverage']
            margin = p.get('margin') or 300
            entry = p.get('entry_price') or 0
            current = p.get('current_price') or entry
            direction = p.get('direction', 'long')

            p['notional'] = margin * lev
            p['mmr'] = round(0.5 / lev * 100, 2)
            p['mark_price'] = current
            if direction == 'long':
                p['liq_price'] = round(entry * (1 - 1/lev * 0.95), 6) if entry else 0
            else:
                p['liq_price'] = round(entry * (1 + 1/lev * 0.95), 6) if entry else 0
            p['realized_pnl'] = round((p.get('unrealized_pnl') or 0) - (p.get('fee') or 0), 2)

            # 止盈价 (如果没有)
            if not p.get('take_profit'):
                tp_levels = p.get('take_profit_levels')
                if tp_levels:
                    if isinstance(tp_levels, str):
                        tp_levels = json.loads(tp_levels)
                    if tp_levels:
                        highest = max(t[0] for t in tp_levels)
                        if direction == 'long':
                            p['take_profit'] = round(entry * (1 + highest/lev), 6)
                        else:
                            p['take_profit'] = round(entry * (1 - highest/lev), 6)

            # 格式化时间
            ot = p.get('open_time')
            if ot and hasattr(ot, 'strftime'):
                p['open_time_str'] = ot.strftime('%m-%d %H:%M')
            else:
                p['open_time_str'] = str(ot)[:16] if ot else ''

        # 格式化信号时间
        for s in signals:
            ts = s.get('timestamp') or s.get('created_at')
            if ts and hasattr(ts, 'strftime'):
                s['timestamp_str'] = ts.strftime('%m-%d %H:%M')
            else:
                s['timestamp_str'] = str(ts)[:16] if ts else ''

        return render_template('home.html',
                             positions=positions, signals=signals,
                             stats=stats, equity_curve=equity_curve)

    @app.route('/signals')
    @login_required
    def signals_page():
        from models.db_ops import query_signals
        signals = query_signals(limit=100)
        for s in signals:
            ts = s.get('timestamp') or s.get('created_at')
            if ts and hasattr(ts, 'strftime'):
                s['timestamp_str'] = ts.strftime('%m-%d %H:%M')
            else:
                s['timestamp_str'] = str(ts)[:16] if ts else ''
        return render_template('signals.html', signals=signals)

    @app.route('/trades')
    @login_required
    def trades_page():
        from models.db_ops import get_closed_trades
        trades = get_closed_trades(days=30)
        for t in trades:
            ct = t.get('close_time') or t.get('open_time')
            if ct and hasattr(ct, 'strftime'):
                t['time_str'] = ct.strftime('%Y-%m-%d %H:%M')
                t['date'] = ct.strftime('%Y-%m-%d')
            else:
                t['time_str'] = str(ct)[:16] if ct else ''
                t['date'] = str(ct)[:10] if ct else ''

            # 计算缺失字段
            t['net_pnl'] = round((t.get('pnl') or 0), 2)
            t['total_fee'] = round((t.get('fee') or 0), 2)

            # 止盈止损
            tc = get_leverage_tier(t.get('symbol', ''))
            lev = t.get('leverage') or tc['max_leverage']
            entry = t.get('entry_price') or 0
            d = t.get('direction', 'long')
            if not t.get('stop_loss'):
                sl_dist = abs(tc['stop_loss_roi']) / lev
                t['stop_loss'] = round(entry * (1 - sl_dist) if d == 'long' else entry * (1 + sl_dist), 6)
            if not t.get('take_profit'):
                t['take_profit'] = round(entry * (1 + 0.30/lev) if d == 'long' else entry * (1 - 0.30/lev), 6)

        return render_template('trades.html', trades=trades)

    @app.route('/history')
    @login_required
    def history_page():
        from models.db_ops import get_closed_trades, get_dashboard_stats, get_calendar_data
        trades = get_closed_trades(days=30)
        stats = get_dashboard_stats()
        calendar = get_calendar_data(days=30)

        for t in trades:
            ct = t.get('close_time') or t.get('open_time')
            if ct and hasattr(ct, 'strftime'):
                t['time_str'] = ct.strftime('%Y-%m-%d %H:%M')
                t['date'] = ct.strftime('%Y-%m-%d')
            else:
                t['time_str'] = str(ct)[:16] if ct else ''
                t['date'] = str(ct)[:10] if ct else ''
            t['net_pnl'] = round((t.get('pnl') or 0), 2)
            t['total_fee'] = round((t.get('fee') or 0), 2)

        return render_template('history.html',
                             calendar=calendar, trades=trades, stats=stats)

    @app.route('/risk')
    @login_required
    def risk_page():
        from models.db_ops import get_open_positions, get_dashboard_stats, get_consecutive_losses
        from core.constants import (
            MAX_CONCURRENT_POSITIONS, CIRCUIT_DAILY_LOSS_PCT,
            CIRCUIT_WEEKLY_LOSS_PCT, CIRCUIT_MONTHLY_LOSS_PCT,
        )
        positions = get_open_positions()
        stats = get_dashboard_stats()
        consec = get_consecutive_losses()
        risk_data = {
            'open_positions': len(positions),
            'max_positions': MAX_CONCURRENT_POSITIONS,
            'consecutive_losses': consec,
            'daily_pnl': stats['total_pnl'],
            'daily_pct': stats['total_pnl'] / 10000 * 100 if stats['total_pnl'] else 0,
            'daily_limit': CIRCUIT_DAILY_LOSS_PCT * 100,
            'weekly_limit': CIRCUIT_WEEKLY_LOSS_PCT * 100,
            'monthly_limit': CIRCUIT_MONTHLY_LOSS_PCT * 100,
        }
        return render_template('risk.html', risk=risk_data)

    @app.route('/config')
    @login_required
    def config_page():
        from core.constants import LEVERAGE_TIERS
        return render_template('config.html', leverage_tiers=LEVERAGE_TIERS, config={
            'TRADING_MODE': settings.TRADING_MODE,
            'PHASE': settings.PHASE,
            'HAS_API_KEY': bool(settings.BINANCE_API_KEY),
            'SYMBOL_COUNT': 50,
        })

    @app.route('/backtest')
    @login_required
    def backtest_page():
        import os, glob
        base = os.path.dirname(__file__)

        # 加载所有回测版本
        versions = []
        bt_files = {
            'backtest_result.json': '当前',
            'backtest_result_v1_tier1_tier2.json': 'V1: Tier1+Tier2 (20币)',
            'backtest_result_v2_tier1_100coins.json': 'V2: Tier1 (100币)',
            'backtest_result_signal_trader.json': 'Signal Trader (TG跟单)',
        }
        for fname, label in bt_files.items():
            path = os.path.join(base, fname)
            if not os.path.exists(path):
                continue
            with open(path) as f:
                r = json.load(f)
            r['label'] = label
            r['file'] = fname
            # 补指标
            trades = r.get('trades', [])
            wins_pnl = [t['pnl'] for t in trades if t.get('pnl', 0) > 0]
            loss_pnl = [t['pnl'] for t in trades if t.get('pnl', 0) <= 0]
            avg_w = sum(wins_pnl) / len(wins_pnl) if wins_pnl else 0
            avg_l = abs(sum(loss_pnl) / len(loss_pnl)) if loss_pnl else 1
            if not r.get('profit_factor'):
                r['profit_factor'] = round(avg_w / avg_l, 2) if avg_l else 0
            if not r.get('breakeven_winrate'):
                r['breakeven_winrate'] = round(avg_l / (avg_w + avg_l) * 100, 1) if (avg_w + avg_l) else 50
            # 月度
            from collections import defaultdict
            monthly = defaultdict(lambda: {'trades': 0, 'pnl': 0, 'wins': 0})
            for t in trades:
                m = t.get('date', '')[:7]
                if not m or m == 'end': continue
                monthly[m]['trades'] += 1
                monthly[m]['pnl'] += t.get('pnl', 0)
                if t.get('pnl', 0) > 0: monthly[m]['wins'] += 1
            r['monthly'] = [
                {'month': m, 'trades': d['trades'],
                 'win_rate': d['wins']/d['trades']*100 if d['trades'] else 0,
                 'pnl': round(d['pnl'], 2)}
                for m, d in sorted(monthly.items())
            ]
            versions.append(r)

        # 默认显示第一个有数据的,或选中的
        selected = request.args.get('v', '0')
        try:
            idx = int(selected)
        except ValueError:
            idx = 0
        result = versions[idx] if idx < len(versions) else (versions[0] if versions else None)

        return render_template('backtest.html', result=result, versions=versions, selected=idx)

    @app.route('/api/trade/<int:trade_id>')
    @login_required
    def api_trade_detail(trade_id):
        from models.db_ops import get_trade_by_id
        trade = get_trade_by_id(trade_id)
        if not trade:
            return jsonify({'error': 'not found'}), 404
        # 转换 datetime 为 string
        for k, v in trade.items():
            if hasattr(v, 'isoformat'):
                trade[k] = v.isoformat()
        return jsonify(trade)

    @app.route('/api/stats')
    @login_required
    def api_stats():
        from models.db_ops import get_dashboard_stats
        return jsonify(get_dashboard_stats())

    @app.route('/api/stream')
    @login_required
    def api_stream():
        """SSE 实时推送 (FR-041)"""
        def generate():
            import time as _time
            from models.db_ops import get_open_positions, get_dashboard_stats, query_signals
            while True:
                try:
                    positions = get_open_positions()
                    stats = get_dashboard_stats()
                    recent = query_signals(limit=5)
                    data = json.dumps({
                        'positions': len(positions),
                        'total_trades': stats['total'],
                        'total_pnl': stats['total_pnl'],
                        'win_rate': stats['win_rate'],
                        'recent_signals': len(recent),
                    }, default=str)
                    yield f"data: {data}\n\n"
                except Exception:
                    pass
                _time.sleep(5)
        return Response(generate(), mimetype='text/event-stream')

    @app.route('/health')
    def health():
        return jsonify({'status': 'ok'})

    return app


app = create_app()

if __name__ == "__main__":
    print(f"\n🚀 Flash Quant Dashboard")
    print(f"   http://localhost:{settings.WEB_PORT}")
    print(f"   登录: {settings.WEB_AUTH_USER} / (任意密码)\n")
    app.run(host='0.0.0.0', port=settings.WEB_PORT, debug=True)
