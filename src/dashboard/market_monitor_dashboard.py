# -*- coding: utf-8 -*-
"""
综合市场监控面板 - 潜力币种 + 大资金监控
整合所有分析功能的统一界面
"""

from flask import Flask, render_template_string, jsonify
import json
import os
from datetime import datetime
from potential_coin_scanner import PotentialCoinScanner
from big_money_tracker import BigMoneyTracker

app = Flask(__name__)

class MarketMonitorDashboard:
    def __init__(self):
        self.coin_scanner = PotentialCoinScanner()
        self.money_tracker = BigMoneyTracker()
        
    def get_latest_scan_results(self):
        """获取最新扫描结果"""
        # 查找最新的扫描文件
        scan_files = [f for f in os.listdir('.') if f.startswith('potential_coins_scan_')]
        money_files = [f for f in os.listdir('.') if f.startswith('big_money_scan_')]
        
        latest_scan = None
        latest_money = None
        
        if scan_files:
            latest_scan_file = sorted(scan_files)[-1]
            with open(latest_scan_file, 'r', encoding='utf-8') as f:
                latest_scan = json.load(f)
        
        if money_files:
            latest_money_file = sorted(money_files)[-1]
            with open(latest_money_file, 'r', encoding='utf-8') as f:
                latest_money = json.load(f)
        
        return latest_scan, latest_money

@app.route('/')
def dashboard():
    """主监控面板"""
    html = '''
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💎 潜力币种监控中心</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .nav-tabs {
            display: flex;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .nav-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-tab.active {
            background: rgba(255,255,255,0.2);
            font-weight: bold;
        }
        
        .nav-tab:hover {
            background: rgba(255,255,255,0.15);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .card h3 {
            margin-bottom: 15px;
            color: #00d2ff;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .positive { color: #00ff88; }
        .negative { color: #ff4757; }
        .neutral { color: #ffa502; }
        
        .coin-item {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #00d2ff;
        }
        
        .coin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .coin-symbol {
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .score {
            background: linear-gradient(45deg, #00d2ff, #3742fa);
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .alert {
            background: rgba(255,71,87,0.2);
            border: 1px solid #ff4757;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .alert-high {
            border-color: #ff4757;
            background: rgba(255,71,87,0.2);
        }
        
        .alert-medium {
            border-color: #ffa502;
            background: rgba(255,165,2,0.2);
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 1.1rem;
        }
        
        .refresh-btn {
            background: linear-gradient(45deg, #00d2ff, #3742fa);
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            margin: 20px auto;
            display: block;
        }
        
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,210,255,0.3);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-active { background: #00ff88; color: #000; }
        .status-warning { background: #ffa502; color: #000; }
        .status-danger { background: #ff4757; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💎 潜力币种监控中心</h1>
            <p>实时追踪大资金动向 • 识别拉盘信号 • 发现投资机会</p>
            <div id="last-update"></div>
        </div>
        
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview')">📊 总览</button>
            <button class="nav-tab" onclick="showTab('potential')">💎 潜力币种</button>
            <button class="nav-tab" onclick="showTab('bigmoney')">🐋 大资金监控</button>
            <button class="nav-tab" onclick="showTab('alerts')">🚨 实时警报</button>
        </div>
        
        <div id="overview" class="tab-content active">
            <div class="grid">
                <div class="card">
                    <h3>🎯 市场概况</h3>
                    <div id="market-overview" class="loading">正在加载数据...</div>
                </div>
                <div class="card">
                    <h3>📈 今日亮点</h3>
                    <div id="today-highlights" class="loading">正在加载数据...</div>
                </div>
                <div class="card">
                    <h3>⚡ 快速操作</h3>
                    <button class="refresh-btn" onclick="refreshAllData()">🔄 刷新数据</button>
                    <button class="refresh-btn" onclick="runNewScan()">🔍 新扫描</button>
                </div>
            </div>
        </div>
        
        <div id="potential" class="tab-content">
            <div id="potential-coins" class="loading">正在加载潜力币种数据...</div>
        </div>
        
        <div id="bigmoney" class="tab-content">
            <div id="big-money-data" class="loading">正在加载大资金数据...</div>
        </div>
        
        <div id="alerts" class="tab-content">
            <div id="alerts-data" class="loading">正在加载警报数据...</div>
        </div>
    </div>
    
    <script>
        function showTab(tabId) {
            // 隐藏所有标签页
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 显示选中的标签页
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
        
        function loadData() {
            // 加载总览数据
            fetch('/api/overview')
                .then(response => response.json())
                .then(data => updateOverview(data));
            
            // 加载潜力币种数据
            fetch('/api/potential_coins')
                .then(response => response.json())
                .then(data => updatePotentialCoins(data));
            
            // 加载大资金数据
            fetch('/api/big_money')
                .then(response => response.json())
                .then(data => updateBigMoneyData(data));
            
            // 加载警报数据
            fetch('/api/alerts')
                .then(response => response.json())
                .then(data => updateAlerts(data));
        }
        
        function updateOverview(data) {
            const overview = document.getElementById('market-overview');
            const highlights = document.getElementById('today-highlights');
            
            if (data.success) {
                overview.innerHTML = `
                    <div class="metric">
                        <span>扫描币种:</span>
                        <span>${data.total_coins}个</span>
                    </div>
                    <div class="metric">
                        <span>高潜力币种:</span>
                        <span class="positive">${data.high_potential_count}个</span>
                    </div>
                    <div class="metric">
                        <span>异常放量:</span>
                        <span class="warning">${data.volume_surge_count}个</span>
                    </div>
                    <div class="metric">
                        <span>巨鲸活跃:</span>
                        <span class="neutral">${data.whale_activity_count}个</span>
                    </div>
                `;
                
                highlights.innerHTML = `
                    <div class="metric">
                        <span>市场情绪:</span>
                        <span class="positive">${data.market_sentiment}</span>
                    </div>
                    <div class="metric">
                        <span>顶级币种:</span>
                        <span>${data.top_coin || '无'}</span>
                    </div>
                    <div class="metric">
                        <span>平均评分:</span>
                        <span>${data.avg_score}/100</span>
                    </div>
                `;
            } else {
                overview.innerHTML = '<div class="alert">暂无数据，请先运行扫描</div>';
                highlights.innerHTML = '<div class="alert">暂无数据</div>';
            }
            
            // 更新时间
            document.getElementById('last-update').innerHTML = `
                <small>📅 最后更新: ${new Date().toLocaleString()}</small>
            `;
        }
        
        function updatePotentialCoins(data) {
            const container = document.getElementById('potential-coins');
            
            if (data.success && data.coins.length > 0) {
                let html = '<h2>🏆 潜力币种排行榜</h2>';
                
                data.coins.slice(0, 10).forEach((coin, index) => {
                    const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '🏅';
                    html += `
                        <div class="coin-item">
                            <div class="coin-header">
                                <span class="coin-symbol">${medal} ${coin.symbol}</span>
                                <span class="score">${coin.potential_score.toFixed(1)}</span>
                            </div>
                            <div class="metric">
                                <span>价格变化:</span>
                                <span class="${coin.price_change_24h >= 0 ? 'positive' : 'negative'}">
                                    ${coin.price_change_24h >= 0 ? '+' : ''}${coin.price_change_24h.toFixed(2)}%
                                </span>
                            </div>
                            <div class="metric">
                                <span>成交量放大:</span>
                                <span class="neutral">${coin.volume_ratio.toFixed(1)}x</span>
                            </div>
                            <div class="metric">
                                <span>资金流向:</span>
                                <span>${coin.fund_flow.direction}</span>
                            </div>
                            <div class="metric">
                                <span>巨鲸活动:</span>
                                <span>${coin.whale_activity.level}</span>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert">暂无潜力币种数据</div>';
            }
        }
        
        function updateBigMoneyData(data) {
            const container = document.getElementById('big-money-data');
            
            if (data.success) {
                let html = '<h2>🐋 大资金监控</h2>';
                
                if (data.accumulation && data.accumulation.length > 0) {
                    html += '<h3>📈 吸筹信号</h3>';
                    data.accumulation.slice(0, 5).forEach(acc => {
                        html += `
                            <div class="coin-item">
                                <div class="coin-header">
                                    <span class="coin-symbol">${acc.symbol}</span>
                                    <span class="score">${(acc.confidence * 100).toFixed(0)}%</span>
                                </div>
                                <div class="metric">
                                    <span>吸筹信号:</span>
                                    <span>${acc.signals.join(' | ')}</span>
                                </div>
                            </div>
                        `;
                    });
                }
                
                if (data.pump_preparation && data.pump_preparation.length > 0) {
                    html += '<h3>🚀 拉盘准备</h3>';
                    data.pump_preparation.slice(0, 5).forEach(pump => {
                        html += `
                            <div class="coin-item">
                                <div class="coin-header">
                                    <span class="coin-symbol">${pump.symbol}</span>
                                    <span class="score">${(pump.confidence * 100).toFixed(0)}%</span>
                                </div>
                                <div class="metric">
                                    <span>预计时间:</span>
                                    <span>${pump.estimated_timeframe}</span>
                                </div>
                                <div class="metric">
                                    <span>风险等级:</span>
                                    <span class="${pump.risk_level === '极高' ? 'negative' : 'neutral'}">${pump.risk_level}</span>
                                </div>
                            </div>
                        `;
                    });
                }
                
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert">暂无大资金数据</div>';
            }
        }
        
        function updateAlerts(data) {
            const container = document.getElementById('alerts-data');
            
            if (data.success && data.alerts.length > 0) {
                let html = '<h2>🚨 实时警报</h2>';
                
                data.alerts.forEach(alert => {
                    const alertClass = alert.level === 'high' ? 'alert-high' : 'alert-medium';
                    html += `
                        <div class="alert ${alertClass}">
                            <h4>${alert.message}</h4>
                            <p><strong>类型:</strong> ${alert.type}</p>
                            ${alert.timeframe ? `<p><strong>时间框架:</strong> ${alert.timeframe}</p>` : ''}
                            <small>⏰ ${new Date().toLocaleTimeString()}</small>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="alert">🎉 当前无紧急警报</div>';
            }
        }
        
        function refreshAllData() {
            loadData();
        }
        
        function runNewScan() {
            alert('🔄 开始新扫描...');
            fetch('/api/run_scan', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ 扫描完成');
                        loadData();
                    } else {
                        alert('❌ 扫描失败');
                    }
                });
        }
        
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            
            // 每5分钟自动刷新
            setInterval(loadData, 300000);
        });
    </script>
</body>
</html>
    '''
    return html

@app.route('/api/overview')
def api_overview():
    """总览API"""
    try:
        dashboard = MarketMonitorDashboard()
        scan_data, money_data = dashboard.get_latest_scan_results()
        
        if scan_data:
            return jsonify({
                'success': True,
                'total_coins': scan_data['total_scanned'],
                'high_potential_count': scan_data['high_potential_count'],
                'volume_surge_count': scan_data['volume_surge_count'],
                'whale_activity_count': scan_data['whale_activity_count'],
                'market_sentiment': scan_data['summary']['market_sentiment'],
                'top_coin': scan_data['summary']['top_coin'],
                'avg_score': scan_data['summary']['average_score']
            })
        else:
            return jsonify({'success': False, 'message': '无数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/potential_coins')
def api_potential_coins():
    """潜力币种API"""
    try:
        dashboard = MarketMonitorDashboard()
        scan_data, _ = dashboard.get_latest_scan_results()
        
        if scan_data and 'high_potential_coins' in scan_data:
            return jsonify({
                'success': True,
                'coins': scan_data['high_potential_coins']
            })
        else:
            return jsonify({'success': False, 'message': '无潜力币种数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/big_money')
def api_big_money():
    """大资金API"""
    try:
        dashboard = MarketMonitorDashboard()
        _, money_data = dashboard.get_latest_scan_results()
        
        if money_data:
            return jsonify({
                'success': True,
                'accumulation': money_data.get('accumulation_analysis', []),
                'pump_preparation': money_data.get('pump_preparation', []),
                'whale_monitoring': money_data.get('whale_monitoring', {})
            })
        else:
            return jsonify({'success': False, 'message': '无大资金数据'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/alerts')
def api_alerts():
    """警报API"""
    try:
        dashboard = MarketMonitorDashboard()
        _, money_data = dashboard.get_latest_scan_results()
        
        if money_data and 'alerts' in money_data:
            return jsonify({
                'success': True,
                'alerts': money_data['alerts']
            })
        else:
            return jsonify({'success': False, 'alerts': []})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/run_scan', methods=['POST'])
def api_run_scan():
    """运行新扫描"""
    try:
        dashboard = MarketMonitorDashboard()
        
        # 运行潜力币种扫描
        analyses = dashboard.coin_scanner.scan_all_coins()
        coin_report = dashboard.coin_scanner.generate_report(analyses)
        
        # 运行大资金扫描
        symbols = ['BTC/USDT', 'ETH/USDT', 'BNB/USDT', 'SOL/USDT', 'ADA/USDT']
        money_report = dashboard.money_tracker.run_comprehensive_scan(symbols)
        
        return jsonify({'success': True, 'message': '扫描完成'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == '__main__':
    print("💎 启动潜力币种监控中心")
    print("🌐 访问 http://localhost:5020")
    print("📊 功能: 潜力币种筛选 | 大资金监控 | 拉盘信号识别")
    app.run(host='0.0.0.0', port=5020, debug=True)