import json
import time
import os
from datetime import datetime
from flask import Flask, render_template_string, jsonify
import ccxt

app = Flask(__name__)

# 增强版HTML模板（包含历史记录功能）
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <title>量化交易监控面板</title>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
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
        .price-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .symbol { font-weight: bold; }
        .price { color: #00d2ff; }
        .change-up { color: #00ff88; }
        .change-down { color: #ff6b6b; }
        .status { 
            display: inline-block; 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
            margin-right: 8px;
        }
        .status-online { background: #00ff88; box-shadow: 0 0 10px #00ff88; }
        .status-offline { background: #ff4757; }
        .time { color: #888; font-size: 0.9rem; text-align: center; margin-top: 20px; }
        .info-row { padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        /* 历史记录样式 */
        .history-section { margin-top: 40px; }
        .strategy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .strategy-card { background: rgba(255,255,255,0.08); border-radius: 10px; padding: 15px; cursor: pointer; transition: all 0.3s; }
        .strategy-card:hover { background: rgba(255,255,255,0.12); transform: translateY(-2px); }
        .strategy-name { font-size: 1.1rem; font-weight: bold; margin-bottom: 10px; color: #00d2ff; }
        .strategy-stats { font-size: 0.9rem; }
        .profit { color: #00ff88; }
        .loss { color: #ff6b6b; }
        .neutral { color: #888; }
        .trades-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .trades-table th, .trades-table td { padding: 10px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .trades-table th { background: rgba(255,255,255,0.1); font-size: 0.9rem; }
        .trades-table td { font-size: 0.85rem; }
        .loading { text-align: center; color: #888; padding: 20px; }
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
                <h2>💰 账户余额</h2>
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
            
            <!-- 基础信息 -->
            <div class="card">
                <h2>ℹ️ 基础信息</h2>
                <div class="info-row">
                    <strong>当前账号:</strong> {{ account_info }}
                </div>
                <div class="info-row">
                    <strong>API权限:</strong> {{ permissions }}
                </div>
                <div class="info-row">
                    <strong>服务器时间:</strong> {{ server_time }}
                </div>
            </div>
        </div>
        
        <!-- 历史记录区域 -->
        <div class="history-section">
            <h1>📊 策略历史分析</h1>
            
            <div class="strategy-grid" id="strategiesGrid">
                <div class="loading">正在加载策略数据...</div>
            </div>
            
            <div class="card">
                <h2>📈 最近交易记录</h2>
                <table class="trades-table" id="tradesTable">
                    <thead>
                        <tr>
                            <th>策略</th>
                            <th>交易对</th>
                            <th>类型</th>
                            <th>价格</th>
                            <th>数量</th>
                            <th>盈亏</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody id="tradesBody">
                        <tr><td colspan="7" class="loading">正在加载交易记录...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <p class="time">最后更新: {{ update_time }} (每60秒自动刷新)</p>
    </div>

<script>
    // 加载策略数据
    async function loadStrategies() {
        try {
            const response = await fetch('/api/strategies');
            const data = await response.json();
            if (data.success) {
                displayStrategies(data.data);
            } else {
                document.getElementById('strategiesGrid').innerHTML = 
                    '<div class="loading">⚠️ ' + data.error + '</div>';
            }
        } catch (error) {
            console.error('加载策略数据失败:', error);
            document.getElementById('strategiesGrid').innerHTML = 
                '<div class="loading">❌ 策略数据加载失败</div>';
        }
    }
    
    // 显示策略卡片
    function displayStrategies(strategies) {
        const grid = document.getElementById('strategiesGrid');
        if (Object.keys(strategies).length === 0) {
            grid.innerHTML = '<div class="loading">📈 暂无策略数据，请运行回测</div>';
            return;
        }
        
        grid.innerHTML = '';
        
        Object.entries(strategies).forEach(([name, data]) => {
            const card = document.createElement('div');
            card.className = 'strategy-card';
            
            const profitClass = data.total_return_rate > 0 ? 'profit' : 
                              data.total_return_rate < 0 ? 'loss' : 'neutral';
            
            card.innerHTML = `
                <div class="strategy-name">${name}</div>
                <div class="strategy-stats">
                    <div>总收益率: <span class="${profitClass}">${(data.total_return_rate * 100).toFixed(2)}%</span></div>
                    <div>交易次数: ${data.total_trades}</div>
                    <div>胜率: ${(data.win_rate * 100).toFixed(1)}%</div>
                    <div>夏普比率: ${data.sharpe_ratio?.toFixed(3) || 'N/A'}</div>
                </div>
            `;
            
            grid.appendChild(card);
        });
    }
    
    // 加载交易记录
    async function loadTrades() {
        try {
            const response = await fetch('/api/trades');
            const data = await response.json();
            if (data.success) {
                displayTrades(data.data);
            } else {
                document.getElementById('tradesBody').innerHTML = 
                    '<tr><td colspan="7" class="loading">⚠️ ' + data.error + '</td></tr>';
            }
        } catch (error) {
            console.error('加载交易记录失败:', error);
            document.getElementById('tradesBody').innerHTML = 
                '<tr><td colspan="7" class="loading">❌ 交易记录加载失败</td></tr>';
        }
    }
    
    // 显示交易记录
    function displayTrades(allTrades) {
        const tbody = document.getElementById('tradesBody');
        
        if (Object.keys(allTrades).length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="loading">📋 暂无交易记录</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        
        // 合并所有策略的交易记录
        let allTradesList = [];
        Object.entries(allTrades).forEach(([strategy, trades]) => {
            trades.forEach(trade => {
                allTradesList.push({...trade, strategy});
            });
        });
        
        if (allTradesList.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="loading">📋 暂无交易记录</td></tr>';
            return;
        }
        
        // 按时间排序，显示最近20条
        allTradesList.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
        allTradesList.slice(0, 20).forEach(trade => {
            const row = document.createElement('tr');
            const profitClass = trade.profit > 0 ? 'profit' : 
                              trade.profit < 0 ? 'loss' : 'neutral';
            
            row.innerHTML = `
                <td>${trade.strategy}</td>
                <td>${trade.symbol}</td>
                <td>${trade.side}</td>
                <td>$${trade.price?.toFixed(4) || 'N/A'}</td>
                <td>${trade.amount?.toFixed(4) || 'N/A'}</td>
                <td><span class="${profitClass}">$${trade.profit?.toFixed(2) || 'N/A'}</span></td>
                <td>${new Date(trade.timestamp).toLocaleString()}</td>
            `;
            
            tbody.appendChild(row);
        });
    }
    
    // 页面加载时初始化数据
    document.addEventListener('DOMContentLoaded', () => {
        loadStrategies();
        loadTrades();
    });
    
    // 自动刷新
    setTimeout(() => location.reload(), 60000);
</script>
</body>
</html>
'''

def load_config():
    try:
        with open('/Users/hongtou/newproject/quant-trade-bot/config.json', 'r') as f:
            return json.load(f)
    except:
        return {'binance': {'api_key': '', 'api_secret': ''}, 'bitget': {'api_key': '', 'api_secret': ''}}

def get_exchange_status(config):
    status = {'binance': False, 'bitget': False}
    
    try:
        binance = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'timeout': 10000
        })
        binance.fetch_time()
        status['binance'] = True
    except:
        pass
    
    try:
        bitget = ccxt.bitget({
            'apiKey': config['bitget']['api_key'],
            'secret': config['bitget']['api_secret'],
            'enableRateLimit': True,
            'timeout': 10000
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
            'enableRateLimit': True,
            'timeout': 10000
        })
        balance_data = binance.fetch_balance()
        balance['usdt'] = balance_data['USDT']['total']
        balance['btc'] = balance_data['BTC']['total'] 
        balance['eth'] = balance_data['ETH']['total']
    except:
        # 模拟数据用于演示
        balance = {'usdt': 10000, 'btc': 0.5, 'eth': 2.5}
    
    return balance

def get_prices(config):
    symbols = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT']
    prices = []
    
    try:
        binance = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'timeout': 10000
        })
        
        for symbol in symbols:
            ticker = binance.fetch_ticker(symbol)
            prices.append({
                'symbol': symbol,
                'price': ticker['last'],
                'change': ticker['percentage']
            })
    except Exception as e:
        print(f"获取价格失败: {e}")
        # 模拟价格数据
        prices = [
            {'symbol': 'BTC/USDT', 'price': 42500, 'change': 2.5},
            {'symbol': 'ETH/USDT', 'price': 2650, 'change': -1.2},
            {'symbol': 'BNB/USDT', 'price': 315, 'change': 0.8}
        ]
    
    return prices

def get_account_info(config):
    try:
        binance = ccxt.binance({
            'apiKey': config['binance']['api_key'],
            'secret': config['binance']['api_secret'],
            'enableRateLimit': True,
            'timeout': 10000
        })
        account = binance.fetch_account()
        return f"Binance账户 (权限: {account.get('permissions', ['spot'])})"
    except:
        return "演示账户"

@app.route('/')
def dashboard():
    config = load_config()
    
    status = get_exchange_status(config)
    balance = get_balance(config)
    prices = get_prices(config)
    account_info = get_account_info(config)
    
    return render_template_string(
        HTML_TEMPLATE,
        status=status,
        balance=balance,
        prices=prices,
        account_info=account_info,
        permissions="读取+交易" if status['binance'] else "演示模式",
        server_time=datetime.now().strftime('%H:%M:%S'),
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

@app.route('/api/strategies')
def get_strategies():
    """获取所有策略的汇总数据"""
    try:
        with open('latest_analysis.json', 'r', encoding='utf-8') as f:
            strategies = json.load(f)
        return jsonify({'success': True, 'data': strategies})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无策略分析数据，请先运行回测'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/trades')
def get_all_trades():
    """获取所有策略的交易历史"""
    try:
        with open('latest_trades.json', 'r', encoding='utf-8') as f:
            trades = json.load(f)
        return jsonify({'success': True, 'data': trades})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无交易历史数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/trades/<strategy>')
def get_strategy_trades(strategy):
    """获取特定策略的交易历史"""
    try:
        with open('latest_trades.json', 'r', encoding='utf-8') as f:
            all_trades = json.load(f)
        
        strategy_trades = all_trades.get(strategy, [])
        return jsonify({'success': True, 'data': strategy_trades})
    except FileNotFoundError:
        return jsonify({'success': False, 'error': '暂无交易历史数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/performance/<strategy>')
def get_strategy_performance(strategy):
    """获取特定策略的性能数据"""
    try:
        with open('latest_analysis.json', 'r', encoding='utf-8') as f:
            all_strategies = json.load(f)
        
        strategy_data = all_strategies.get(strategy)
        if not strategy_data:
            return jsonify({'success': False, 'error': '策略未找到'})
        
        return jsonify({'success': True, 'data': strategy_data})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    import os
    port = int(os.environ.get('PORT', 5001))
    print("🚀 启动增强版监控面板...")
    print(f"📊 访问 http://localhost:{port} 查看实时数据和历史分析")
    app.run(host='0.0.0.0', port=port, debug=False)