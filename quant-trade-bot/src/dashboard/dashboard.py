import pymysql
import json
import time
from datetime import datetime
from flask import Flask, render_template_string, jsonify
import ccxt
import pandas as pd

app = Flask(__name__)

# HTML模板
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <title>量化交易监控面板</title>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="30">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { 
            text-align: center; 
            margin-bottom: 30px;
            font-size: 2rem;
            background: linear-gradient(90deg, #00d2ff, #3a7bd5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 { 
            font-size: 1rem; 
            color: #888; 
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .balance { font-size: 2rem; font-weight: bold; color: #00d2ff; }
        .price-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .symbol { font-weight: bold; }
        .price { color: #00d2ff; font-size: 1.2rem; }
        .change-up { color: #00ff88; }
        .change-down { color: #ff4757; }
        .indicator { 
            display: inline-block; 
            padding: 5px 10px; 
            margin: 5px; 
            border-radius: 5px; 
            background: rgba(255,255,255,0.1);
            font-size: 0.9rem;
        }
        .signal-buy { background: rgba(0, 255, 136, 0.2); color: #00ff88; }
        .signal-sell { background: rgba(255, 71, 87, 0.2); color: #ff4757; }
        .signal-none { background: rgba(255, 255, 255, 0.1); color: #888; }
        .status { 
            display: inline-block; 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            margin-right: 10px;
        }
        .status-online { background: #00ff88; box-shadow: 0 0 10px #00ff88; }
        .status-offline { background: #ff4757; }
        .time { color: #888; font-size: 0.9rem; text-align: center; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: #888; font-weight: normal; }
        .wide-card { grid-column: span 2; }
        @media (max-width: 768px) { .wide-card { grid-column: span 1; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 量化交易监控面板</h1>
        
        <div class="grid">
            <!-- 连接状态 -->
            <div class="card">
                <h2>📡 连接状态</h2>
                <p><span class="status {{ 'status-online' if status.binance else 'status-offline' }}"></span>Binance: {{ '已连接' if status.binance else '断开' }}</p>
                <p style="margin-top:10px"><span class="status {{ 'status-online' if status.bitget else 'status-offline' }}"></span>Bitget: {{ '已连接' if status.bitget else '断开' }}</p>
            </div>
            
            <!-- 账户余额 -->
            <div class="card">
                <h2>💰 账户余额 (Binance)</h2>
                <div class="balance">{{ "%.2f"|format(balance.usdt) }} USDT</div>
                {% if balance.btc > 0 %}
                <p style="margin-top:10px">BTC: {{ "%.6f"|format(balance.btc) }}</p>
                {% endif %}
                {% if balance.eth > 0 %}
                <p>ETH: {{ "%.6f"|format(balance.eth) }}</p>
                {% endif %}
            </div>
            
            <!-- 实时价格 -->
            <div class="card">
                <h2>📈 实时价格</h2>
                {% for item in prices %}
                <div class="price-row">
                    <span class="symbol">{{ item.symbol }}</span>
                    <span class="price">${{ "%.2f"|format(item.price) }}</span>
                    <span class="{{ 'change-up' if item.change > 0 else 'change-down' }}">
                        {{ "%.2f"|format(item.change) }}%
                    </span>
                </div>
                {% endfor %}
            </div>
            
            <!-- 交易信号 -->
            <div class="card">
                <h2>🎯 交易信号</h2>
                {% for sig in signals %}
                <div style="margin-bottom: 15px;">
                    <strong>{{ sig.symbol }}</strong>
                    <span class="indicator {{ 'signal-buy' if sig.signal == 'buy' else 'signal-sell' if sig.signal == 'sell' else 'signal-none' }}">
                        {{ sig.signal|upper if sig.signal else '无信号' }}
                    </span>
                    {% if sig.reason %}
                    <p style="color:#888; font-size:0.85rem; margin-top:5px;">{{ sig.reason }}</p>
                    {% endif %}
                </div>
                {% endfor %}
            </div>
            
            <!-- 技术指标 -->
            <div class="card wide-card">
                <h2>📊 技术指标</h2>
                <table>
                    <tr>
                        <th>交易对</th>
                        <th>MA5</th>
                        <th>MA20</th>
                        <th>RSI</th>
                        <th>MACD</th>
                        <th>布林上轨</th>
                        <th>布林下轨</th>
                    </tr>
                    {% for ind in indicators %}
                    <tr>
                        <td><strong>{{ ind.symbol }}</strong></td>
                        <td>{{ "%.2f"|format(ind.ma5) }}</td>
                        <td>{{ "%.2f"|format(ind.ma20) }}</td>
                        <td class="{{ 'change-up' if ind.rsi < 30 else 'change-down' if ind.rsi > 70 else '' }}">
                            {{ "%.1f"|format(ind.rsi) }}
                        </td>
                        <td class="{{ 'change-up' if ind.macd > 0 else 'change-down' }}">
                            {{ "%.4f"|format(ind.macd) }}
                        </td>
                        <td>{{ "%.2f"|format(ind.bb_upper) }}</td>
                        <td>{{ "%.2f"|format(ind.bb_lower) }}</td>
                    </tr>
                    {% endfor %}
                </table>
            </div>
        </div>
        
        <p class="time">最后更新: {{ update_time }} (每30秒自动刷新)</p>
    </div>
</body>
</html>
'''

def load_config():
    with open('config.json', 'r') as f:
        return json.load(f)

def get_exchange_status(config):
    status = {'binance': False, 'bitget': False}
    
    try:
        binance = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True
        })
        binance.fetch_time()
        status['binance'] = True
    except:
        pass
    
    try:
        bitget = ccxt.bitget({
            'apiKey': config['bitget']['api_key'],
            'secret': config['bitget']['api_secret'],
            'enableRateLimit': True
        })
        bitget.fetch_time()
        status['bitget'] = True
    except:
        pass
    
    return status

def get_balance(config):
    balance = {'usdt': 0, 'btc': 0, 'eth': 0}
    
    try:
        binance = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True
        })
        bal = binance.fetch_balance()
        balance['usdt'] = bal.get('USDT', {}).get('free', 0) or 0
        balance['btc'] = bal.get('BTC', {}).get('free', 0) or 0
        balance['eth'] = bal.get('ETH', {}).get('free', 0) or 0
    except Exception as e:
        print(f"获取余额失败: {e}")
    
    return balance

def get_prices(config):
    prices = []
    symbols = ['BTC/USDT', 'ETH/USDT']
    
    try:
        binance = ccxt.binance({'enableRateLimit': True})
        for symbol in symbols:
            ticker = binance.fetch_ticker(symbol)
            prices.append({
                'symbol': symbol,
                'price': ticker['last'],
                'change': ticker['percentage'] or 0
            })
    except Exception as e:
        print(f"获取价格失败: {e}")
        for symbol in symbols:
            prices.append({'symbol': symbol, 'price': 0, 'change': 0})
    
    return prices

def calculate_indicators(config):
    indicators = []
    symbols = ['BTC/USDT', 'ETH/USDT']
    
    try:
        binance = ccxt.binance({'enableRateLimit': True})
        
        for symbol in symbols:
            ohlcv = binance.fetch_ohlcv(symbol, '1h', limit=60)
            df = pd.DataFrame(ohlcv, columns=['timestamp', 'open', 'high', 'low', 'close', 'volume'])
            
            # 计算指标
            df['ma5'] = df['close'].rolling(5).mean()
            df['ma20'] = df['close'].rolling(20).mean()
            
            # RSI
            delta = df['close'].diff()
            gain = (delta.where(delta > 0, 0)).rolling(14).mean()
            loss = (-delta.where(delta < 0, 0)).rolling(14).mean()
            rs = gain / loss
            df['rsi'] = 100 - (100 / (1 + rs))
            
            # MACD
            ema12 = df['close'].ewm(span=12).mean()
            ema26 = df['close'].ewm(span=26).mean()
            df['macd'] = ema12 - ema26
            
            # 布林带
            df['bb_mid'] = df['close'].rolling(20).mean()
            df['bb_std'] = df['close'].rolling(20).std()
            df['bb_upper'] = df['bb_mid'] + 2 * df['bb_std']
            df['bb_lower'] = df['bb_mid'] - 2 * df['bb_std']
            
            latest = df.iloc[-1]
            indicators.append({
                'symbol': symbol,
                'ma5': latest['ma5'],
                'ma20': latest['ma20'],
                'rsi': latest['rsi'],
                'macd': latest['macd'],
                'bb_upper': latest['bb_upper'],
                'bb_lower': latest['bb_lower']
            })
    except Exception as e:
        print(f"计算指标失败: {e}")
    
    return indicators

def get_signals(indicators):
    signals = []
    
    for ind in indicators:
        signal = None
        reason = ""
        
        # MA交叉
        if ind['ma5'] > ind['ma20']:
            if ind['rsi'] < 40:
                signal = 'buy'
                reason = f"MA5上穿MA20, RSI={ind['rsi']:.1f}"
        elif ind['ma5'] < ind['ma20']:
            if ind['rsi'] > 60:
                signal = 'sell'
                reason = f"MA5下穿MA20, RSI={ind['rsi']:.1f}"
        
        signals.append({
            'symbol': ind['symbol'],
            'signal': signal,
            'reason': reason
        })
    
    return signals

@app.route('/')
def dashboard():
    config = load_config()
    
    status = get_exchange_status(config)
    balance = get_balance(config)
    prices = get_prices(config)
    indicators = calculate_indicators(config)
    signals = get_signals(indicators)
    
    return render_template_string(
        HTML_TEMPLATE,
        status=status,
        balance=balance,
        prices=prices,
        indicators=indicators,
        signals=signals,
        update_time=datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    )

@app.route('/api/status')
def api_status():
    config = load_config()
    return jsonify({
        'status': get_exchange_status(config),
        'balance': get_balance(config),
        'prices': get_prices(config),
        'time': datetime.now().isoformat()
    })

#!/usr/bin/env python3
"""Add /report route to the 5111 dashboard for V5 strategy status."""

# This code gets appended before `if __name__` in the dashboard.py

import pymysql

V5_REPORT_HTML = '''
<!DOCTYPE html>
<html>
<head>
    <title>V5 Strategy Report</title>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="60">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a3e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 10px; color: #00d4ff; font-size: 28px; }
        .subtitle { text-align: center; color: #888; margin-bottom: 30px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
        }
        .card h2 { color: #00d4ff; font-size: 16px; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13px; }
        th { color: #888; font-weight: 500; }
        .green { color: #00e676; }
        .red { color: #ff5252; }
        .yellow { color: #ffd740; }
        .blue { color: #448aff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-ok { background: rgba(0,230,118,0.2); color: #00e676; }
        .badge-warn { background: rgba(255,215,64,0.2); color: #ffd740; }
        .badge-new { background: rgba(0,212,255,0.2); color: #00d4ff; }
        .param-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .param { display: flex; justify-content: space-between; }
        .param-label { color: #888; }
        .param-value { color: #fff; font-weight: 500; }
        .indicator-box { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .indicator { background: rgba(0,212,255,0.1); border: 1px solid rgba(0,212,255,0.2); border-radius: 8px; padding: 10px 15px; text-align: center; }
        .indicator .name { font-size: 11px; color: #888; }
        .indicator .value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .comparison { margin-top: 20px; }
        .comparison table th { color: #00d4ff; }
        .footer { text-align: center; color: #555; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1>V5 Strategy Report</h1>
    <p class="subtitle">10x Leverage | Triple Confirmation (RSI + MACD + BB + ADX) | Updated: {{ update_time }}</p>

    <div class="grid">
        <div class="card">
            <h2>V5 Strategy Config</h2>
            {% if v5_preset %}
            <div class="param-grid">
                <div class="param"><span class="param-label">Leverage</span><span class="param-value blue">{{ v5_preset.get('max_leverage', 10) }}x</span></div>
                <div class="param"><span class="param-label">Max Positions</span><span class="param-value">{{ v5_preset.get('max_positions', 5) }}</span></div>
                <div class="param"><span class="param-label">Min Score</span><span class="param-value">{{ v5_preset.get('min_score', 75) }}</span></div>
                <div class="param"><span class="param-label">LONG Min Score</span><span class="param-value">{{ v5_preset.get('long_min_score', 80) }}</span></div>
                <div class="param"><span class="param-label">Max Position Size</span><span class="param-value">{{ v5_preset.get('max_position_size', 150) }}U</span></div>
                <div class="param"><span class="param-label">ROI Stop Loss</span><span class="param-value red">{{ v5_preset.get('roi_stop_loss', -8) }}%</span></div>
                <div class="param"><span class="param-label">TP1 ROI</span><span class="param-value green">+{{ v5_preset.get('tp1_roi', 10) }}% (close {{ (v5_preset.get('tp1_close_ratio', 0.5) * 100)|int }}%)</span></div>
                <div class="param"><span class="param-label">TP2 / Trailing Start</span><span class="param-value green">+{{ v5_preset.get('tp2_roi', 20) }}%</span></div>
                <div class="param"><span class="param-label">Trailing Distance</span><span class="param-value">{{ v5_preset.get('roi_trailing_distance', 5) }}%</span></div>
                <div class="param"><span class="param-label">ATR Stop</span><span class="param-value">{{ 'ON' if v5_preset.get('use_atr_stop') else 'OFF' }} ({{ v5_preset.get('atr_stop_multiplier', 1.5) }}x ATR)</span></div>
                <div class="param"><span class="param-label">ADX Threshold</span><span class="param-value">{{ v5_preset.get('adx_min_threshold', 25) }}</span></div>
                <div class="param"><span class="param-label">Daily Loss Limit</span><span class="param-value red">{{ v5_preset.get('daily_loss_limit', 100) }}U</span></div>
                <div class="param"><span class="param-label">Max Drawdown</span><span class="param-value red">{{ v5_preset.get('max_drawdown_pct', 12) }}%</span></div>
                <div class="param"><span class="param-label">Cooldown</span><span class="param-value">{{ v5_preset.get('cooldown_minutes', 60) }} min</span></div>
            </div>
            {% else %}
            <p class="red">V5 preset not found in DB!</p>
            {% endif %}
        </div>

        <div class="card">
            <h2>New Indicators (V5)</h2>
            <div class="indicator-box">
                <div class="indicator"><div class="name">MACD(12,26,9)</div><div class="value blue">25pts</div></div>
                <div class="indicator"><div class="name">RSI(14)</div><div class="value blue">20pts</div></div>
                <div class="indicator"><div class="name">Bollinger Bands(20,2)</div><div class="value blue">20pts</div></div>
                <div class="indicator"><div class="name">ADX(14)</div><div class="value blue">15pts</div></div>
                <div class="indicator"><div class="name">Volume</div><div class="value blue">10pts</div></div>
                <div class="indicator"><div class="name">BTC Filter</div><div class="value blue">10pts</div></div>
                <div class="indicator"><div class="name">Triple Confirm Bonus</div><div class="value green">+10pts</div></div>
            </div>
            <p style="margin-top:15px;color:#888;font-size:12px;">Total: 100pts max + 10 bonus. ADX &lt; 25 = skip (no ranging market trades at 10x).</p>
        </div>
    </div>

    <div class="card comparison">
        <h2>V4.2 vs V5.0 Comparison</h2>
        <table>
            <tr><th>Parameter</th><th>V4.2 (Current)</th><th>V5.0 (New)</th></tr>
            <tr><td>Leverage</td><td>3x</td><td class="blue">10x</td></tr>
            <tr><td>Max Positions</td><td>15</td><td>5</td></tr>
            <tr><td>Position Size</td><td>150-350U</td><td>50-150U</td></tr>
            <tr><td>Indicators</td><td>RSI + MA + Vol + Position</td><td class="green">RSI + MACD + BB + ADX + Vol</td></tr>
            <tr><td>Min Score</td><td>60</td><td>75</td></tr>
            <tr><td>Stop Loss</td><td>-10% ROI (fixed)</td><td class="yellow">-8% ROI (ATR dynamic)</td></tr>
            <tr><td>Take Profit</td><td>Trailing 6%/3%</td><td class="green">TP1: +10% (50%) → TP2: +20% trailing 5%</td></tr>
            <tr><td>Daily Loss Limit</td><td>200U</td><td class="yellow">100U</td></tr>
            <tr><td>Max Drawdown</td><td>20%</td><td class="yellow">12%</td></tr>
            <tr><td>Cooldown</td><td>30 min</td><td>60 min</td></tr>
            <tr><td>Risk: Drawdown Alert</td><td>15%/10%/5%</td><td class="yellow">10%/7%/4%</td></tr>
            <tr><td>Risk: Consec. Losses</td><td>3/2</td><td class="yellow">2/1</td></tr>
        </table>
    </div>

    <div class="card" style="margin-top:20px;">
        <h2>DB Migration Status</h2>
        <table>
            <tr><th>Table</th><th>New Columns</th><th>Status</th></tr>
            {% for m in migration_status %}
            <tr>
                <td>{{ m.table }}</td>
                <td>{{ m.columns }}</td>
                <td><span class="badge {{ 'badge-ok' if m.ok else 'badge-warn' }}">{{ 'OK' if m.ok else 'MISSING' }}</span></td>
            </tr>
            {% endfor %}
            <tr>
                <td>strategy_presets</td>
                <td>v5.0 preset</td>
                <td><span class="badge {{ 'badge-ok' if v5_preset else 'badge-warn' }}">{{ 'OK' if v5_preset else 'MISSING' }}</span></td>
            </tr>
        </table>
    </div>

    <div class="card" style="margin-top:20px;">
        <h2>Deployment Status</h2>
        <table>
            <tr><th>Component</th><th>Status</th><th>Note</th></tr>
            <tr><td>signal_analyzer.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+MACD, BB, ADX, analyze_signal_v5(), position_size_v5(), stop_take_v5()</td></tr>
            <tr><td>order_executor.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+reduce_position()</td></tr>
            <tr><td>agent_bot.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+v5 routing, _partial_close(), TP1 logic</td></tr>
            <tr><td>risk_manager.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+v5 stricter thresholds</td></tr>
            <tr><td>agent_config.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+6 v5 config columns</td></tr>
            <tr><td>trade.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+5 partial TP columns</td></tr>
            <tr><td>agent API</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+v5 field_map</td></tr>
            <tr><td>manage.py</td><td><span class="badge badge-ok">DEPLOYED</span></td><td>+v5.0 preset</td></tr>
            <tr><td>Production Bot</td><td><span class="badge badge-warn">NOT RESTARTED</span></td><td>Still running v4.2 — safe, no impact</td></tr>
        </table>
    </div>

    <p class="footer">V5 Strategy deployed to server. Production bot still running V4.2. Switch via Agent Settings when ready.</p>
</div>
</body>
</html>
'''

def get_v5_report_data():
    """Fetch v5 status from trading_saas MySQL."""
    conn = None
    try:
        conn = pymysql.connect(
            host='127.0.0.1', user='saas_user',
            password='SaasTrade2026xK9m', database='trading_saas',
            cursorclass=pymysql.cursors.DictCursor,
        )
        cur = conn.cursor()

        # Check v5 preset
        cur.execute("SELECT config FROM strategy_presets WHERE version='v5.0'")
        row = cur.fetchone()
        v5_preset = json.loads(row['config']) if row else None

        # Check migration status
        migration_status = []

        cur.execute("SELECT COUNT(*) as cnt FROM information_schema.columns "
                    "WHERE table_schema='trading_saas' AND table_name='agent_trading_config' AND column_name='tp1_roi'")
        r = cur.fetchone()
        migration_status.append({
            'table': 'agent_trading_config',
            'columns': 'tp1_roi, tp1_close_ratio, tp2_roi, use_atr_stop, atr_stop_multiplier, adx_min_threshold',
            'ok': r['cnt'] > 0,
        })

        cur.execute("SELECT COUNT(*) as cnt FROM information_schema.columns "
                    "WHERE table_schema='trading_saas' AND table_name='trades' AND column_name='tp1_hit'")
        r = cur.fetchone()
        migration_status.append({
            'table': 'trades',
            'columns': 'tp1_hit, tp1_price, tp1_time, partial_pnl, original_amount',
            'ok': r['cnt'] > 0,
        })

        return v5_preset, migration_status
    except Exception as e:
        print(f"V5 report data error: {e}")
        return None, []
    finally:
        if conn:
            conn.close()


@app.route('/report')
def v5_report():
    from datetime import datetime as dt
    v5_preset, migration_status = get_v5_report_data()
    return render_template_string(
        V5_REPORT_HTML,
        v5_preset=v5_preset,
        migration_status=migration_status,
        update_time=dt.now().strftime('%Y-%m-%d %H:%M:%S')
    )

if __name__ == '__main__':
    print("🚀 启动监控面板...")
    print("📊 访问 http://localhost:5000 查看实时数据")
    app.run(host='0.0.0.0', port=5000, debug=True)
