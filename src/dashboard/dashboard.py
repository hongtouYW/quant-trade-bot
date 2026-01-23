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

if __name__ == '__main__':
    print("🚀 启动监控面板...")
    print("📊 访问 http://localhost:5000 查看实时数据")
    app.run(host='0.0.0.0', port=5000, debug=True)
