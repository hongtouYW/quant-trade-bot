#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Trading Assistant Dashboard - 交易助手仪表盘 v1.3
Port: 5111
独立于量化助手(5001)

v1.2 新功能:
- 按需加载图表（点击后才加载）
- 一次只显示一个持仓的图表
- 持仓选择下拉框
- 6种时间周期筛选
"""

from flask import Flask, jsonify, render_template_string, request
import sqlite3
from datetime import datetime, timedelta, timezone
import os
import requests
import time as _time

app = Flask(__name__)

# 监控币种列表
WATCH_SYMBOLS = [
    'XMR', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK',
    'VET', 'BNB', 'INJ', 'LINK', 'OP', 'FIL',
    'ETH', 'AVAX', 'DOT', 'ATOM', 'MATIC', 'ARB',
    'APT', 'SUI', 'SEI', 'TIA', 'WLD', 'NEAR',
    'BTC'
]

SYMBOL_MAP = {s: f'{s}USDT' for s in WATCH_SYMBOLS}

DB_PATH = '/opt/trading-bot/quant-trade-bot/data/db/paper_trader.db'  # Paper Trader 独立数据库

def get_db():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

@app.route('/')
def index():
    """主页面"""
    return render_template_string(HTML_TEMPLATE)

@app.route('/api/stats')
def get_stats():
    """获取统计数据"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        # 基本统计
        cursor.execute('''
            SELECT
                COUNT(*) as total_trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as win_trades,
                SUM(CASE WHEN pnl < 0 THEN 1 ELSE 0 END) as loss_trades,
                SUM(COALESCE(pnl, 0)) as total_pnl,
                SUM(COALESCE(fee, 0)) as total_fees,
                SUM(COALESCE(funding_fee, 0)) as total_funding_fees,
                AVG(CASE WHEN status = 'CLOSED' THEN roi END) as avg_roi,
                MAX(pnl) as best_trade,
                MIN(pnl) as worst_trade
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
        ''')
        
        stats = dict(cursor.fetchone())
        
        # 计算胜率
        total = stats['total_trades'] or 0
        wins = stats['win_trades'] or 0
        stats['win_rate'] = (wins / total * 100) if total > 0 else 0
        
        # 当前资金
        initial_capital = 2000
        current_capital = initial_capital + (stats['total_pnl'] or 0)
        target_profit = 3400

        # 计算持仓占用保证金
        cursor.execute('''
            SELECT
                COUNT(*) as open_positions,
                COALESCE(SUM(amount), 0) as margin_used
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'OPEN'
        ''')

        position_stats = dict(cursor.fetchone())
        margin_used = position_stats['margin_used']
        available_capital = current_capital - margin_used

        stats['initial_capital'] = initial_capital
        stats['current_capital'] = current_capital
        stats['available_capital'] = available_capital
        stats['margin_used'] = margin_used
        stats['target_profit'] = target_profit
        stats['progress'] = ((stats['total_pnl'] or 0) / target_profit * 100) if target_profit > 0 else 0
        stats['open_positions'] = position_stats['open_positions']

        # === 风险监控指标 ===

        # 1. 计算最大回撤和当前回撤
        cursor.execute('''
            SELECT exit_time, pnl
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
            ORDER BY exit_time
        ''')

        trades_data = cursor.fetchall()
        max_drawdown = 0
        peak_capital = initial_capital
        current_drawdown = 0
        cumulative_capital = initial_capital

        # 手动计算累积盈亏和回撤
        for trade in trades_data:
            cumulative_capital += trade['pnl']
            if cumulative_capital > peak_capital:
                peak_capital = cumulative_capital

            drawdown_pct = ((peak_capital - cumulative_capital) / peak_capital * 100) if peak_capital > 0 else 0
            max_drawdown = max(max_drawdown, drawdown_pct)

        # 当前回撤
        if current_capital < peak_capital:
            current_drawdown = (peak_capital - current_capital) / peak_capital * 100

        stats['max_drawdown'] = round(max_drawdown, 2)
        stats['current_drawdown'] = round(current_drawdown, 2)
        stats['peak_capital'] = round(peak_capital, 2)

        # 2. 连续亏损次数
        cursor.execute('''
            SELECT pnl
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
            ORDER BY exit_time DESC
            LIMIT 10
        ''')

        recent_trades = cursor.fetchall()
        consecutive_losses = 0
        for trade in recent_trades:
            if trade['pnl'] < 0:
                consecutive_losses += 1
            else:
                break

        stats['consecutive_losses'] = consecutive_losses

        # 3. 持仓风险分析
        cursor.execute('''
            SELECT
                symbol,
                direction,
                amount,
                leverage
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'OPEN'
        ''')

        open_positions = cursor.fetchall()

        # 持仓集中度
        max_position_pct = 0
        total_margin = sum(p['amount'] for p in open_positions)
        if total_margin > 0:
            for pos in open_positions:
                pos_pct = (pos['amount'] / total_margin * 100)
                max_position_pct = max(max_position_pct, pos_pct)

        stats['max_position_concentration'] = round(max_position_pct, 1)

        # 多空比例
        long_count = sum(1 for p in open_positions if p['direction'] == 'LONG')
        short_count = sum(1 for p in open_positions if p['direction'] == 'SHORT')
        total_positions = long_count + short_count

        long_ratio = (long_count / total_positions * 100) if total_positions > 0 else 0
        short_ratio = (short_count / total_positions * 100) if total_positions > 0 else 0

        stats['long_ratio'] = round(long_ratio, 1)
        stats['short_ratio'] = round(short_ratio, 1)

        # 杠杆风险暴露
        total_leverage_exposure = sum(p['amount'] * p['leverage'] for p in open_positions)
        leverage_ratio = (total_leverage_exposure / current_capital) if current_capital > 0 else 0

        stats['leverage_exposure'] = round(total_leverage_exposure, 0)
        stats['leverage_ratio'] = round(leverage_ratio, 2)

        # 风险评级 (0-10, 10为最高风险)
        risk_score = 0

        # 回撤风险 (0-3分)
        if current_drawdown > 15:
            risk_score += 3
        elif current_drawdown > 10:
            risk_score += 2
        elif current_drawdown > 5:
            risk_score += 1

        # 连续亏损风险 (0-2分)
        if consecutive_losses >= 3:
            risk_score += 2
        elif consecutive_losses >= 2:
            risk_score += 1

        # 持仓集中风险 (0-2分)
        if max_position_pct > 40:
            risk_score += 2
        elif max_position_pct > 30:
            risk_score += 1

        # 单边风险 (0-2分)
        if max(long_ratio, short_ratio) > 85:
            risk_score += 2
        elif max(long_ratio, short_ratio) > 70:
            risk_score += 1

        # 杠杆风险 (0-1分)
        if leverage_ratio > 3:
            risk_score += 1

        stats['risk_score'] = risk_score

        # 风险等级
        if risk_score >= 7:
            risk_level = '高风险'
            risk_color = '#ef4444'
        elif risk_score >= 4:
            risk_level = '中风险'
            risk_color = '#f59e0b'
        else:
            risk_level = '低风险'
            risk_color = '#10b981'

        stats['risk_level'] = risk_level
        stats['risk_color'] = risk_color

        conn.close()

        return jsonify(stats)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/positions')
def get_positions():
    """获取当前持仓"""
    try:
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT 
                symbol, direction, entry_price, amount, leverage,
                stop_loss, take_profit, entry_time, reason
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'OPEN'
            ORDER BY entry_time DESC
        ''')
        
        positions = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify(positions)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/trades')
def get_trades():
    """获取交易历史"""
    try:
        limit = 20
        conn = get_db()
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT
                symbol, direction, entry_price, exit_price,
                amount, leverage, pnl, roi, fee, funding_fee, entry_time, exit_time,
                status, reason, stop_loss, take_profit,
                initial_stop_loss, final_stop_loss, stop_move_count
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            ORDER BY entry_time DESC
            LIMIT ?
        ''', (limit,))
        
        trades = [dict(row) for row in cursor.fetchall()]
        conn.close()
        
        return jsonify(trades)
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_stats')
def get_daily_stats():
    """获取每日统计（最近7天）- 优先从daily_pnl表读取"""
    try:
        conn = get_db()
        cursor = conn.cursor()

        # 先尝试从daily_pnl表读取
        try:
            cursor.execute('''
                SELECT
                    date,
                    trades_count as trades,
                    win_count as wins,
                    total_pnl as daily_pnl,
                    win_rate,
                    cumulative_pnl
                FROM daily_pnl
                WHERE date >= date('now', '-7 days')
                ORDER BY date DESC
            ''')
            daily_stats = [dict(row) for row in cursor.fetchall()]

            # 如果有数据，直接返回
            if daily_stats:
                conn.close()
                return jsonify(daily_stats)
        except:
            # 如果表不存在，继续使用旧方法
            pass

        # 降级方案：实时计算（如果daily_pnl表不存在或无数据）
        cursor.execute('''
            SELECT
                DATE(exit_time) as date,
                COUNT(*) as trades,
                SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END) as wins,
                SUM(COALESCE(pnl, 0)) as daily_pnl
            FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手'
            AND status = 'CLOSED'
            AND exit_time >= date('now', '-7 days')
            GROUP BY DATE(exit_time)
            ORDER BY date DESC
        ''')

        daily_stats = [dict(row) for row in cursor.fetchall()]
        conn.close()

        return jsonify(daily_stats)

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/daily_history')
def get_daily_history():
    """获取每日收益历史记录（所有数据）"""
    try:
        days = request.args.get('days', 30, type=int)

        conn = get_db()
        cursor = conn.cursor()

        cursor.execute('''
            SELECT
                date,
                trades_count,
                win_count,
                loss_count,
                total_pnl,
                total_fee,
                total_funding_fee,
                win_rate,
                best_trade,
                worst_trade,
                cumulative_pnl
            FROM daily_pnl
            WHERE date >= date('now', ? || ' days')
            ORDER BY date DESC
        ''', (f'-{days}',))

        history = [dict(row) for row in cursor.fetchall()]
        conn.close()

        return jsonify(history)

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/price/<symbol>')
def get_current_price(symbol):
    """获取币种当前价格（使用Binance期货API）"""
    try:
        # 使用Binance期货API，与交易系统一致
        binance_symbol = f"{symbol}USDT"
        url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={binance_symbol}"
        response = requests.get(url, timeout=10)
        data = response.json()

        if 'price' in data:
            price = float(data['price'])
            return jsonify({'symbol': symbol, 'price': price})
        else:
            return jsonify({'error': f'Price not found for {symbol}'}), 404

    except Exception as e:
        return jsonify({'error': str(e), 'symbol': symbol}), 500

@app.route('/api/kline/<symbol>')
def get_kline(symbol):
    """获取K线数据（支持多时间周期）"""
    try:
        # 获取时间周期参数，默认5m
        interval = request.args.get('interval', '5m')
        
        # Binance不支持10m，改用15m
        if interval == '10m':
            interval = '15m'

        # 时间周期对应的数据量（保持图表信息量一致）
        interval_limits = {
            '5m': 288,   # 24小时 = 288个5分钟K线
            '15m': 96,   # 24小时 = 96个15分钟K线
            '30m': 48,   # 24小时 = 48个30分钟K线
            '1h': 168,   # 7天 = 168个1小时K线
            '4h': 168,   # 28天 = 168个4小时K线
            '1d': 90     # 90天 = 90个1日K线
        }
        
        limit = interval_limits.get(interval, 288)
        
        symbol_map = {
            # 原有币种
            'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
            'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
            # 新增币种
            'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
            'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT'
        }
        binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

        # 使用期货API获取K线数据
        url = f"https://fapi.binance.com/fapi/v1/klines?symbol={binance_symbol}&interval={interval}&limit={limit}"
        response = requests.get(url, timeout=10)
        klines = response.json()
        
        # 转换为简化格式
        data = []
        for k in klines:
            data.append({
                'time': int(k[0]),
                'open': float(k[1]),
                'high': float(k[2]),
                'low': float(k[3]),
                'close': float(k[4]),
                'volume': float(k[5])
            })
        
        return jsonify(data)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/watchlist')
def get_watchlist():
    """获取监控币种列表"""
    try:
        # 监控币种 (25个 - 激进策略：增加交易机会)
        watch_symbols = [
            # 原有监控 (7个)
            'XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK',
            # 高分币种 (6个)
            'VET',   # 得分100 - VeChain
            'BNB',   # 得分80 - Binance Coin
            'INJ',   # 得分80 - Injective
            'LINK',  # 得分70 - Chainlink
            'OP',    # 得分70 - Optimism
            'FIL',   # 得分70 - Filecoin
            # 高流动性币种 (6个)
            'ETH',   # 以太坊
            'AVAX',  # Avalanche
            'DOT',   # Polkadot
            'ATOM',  # Cosmos
            'MATIC', # Polygon
            'ARB',   # Arbitrum
            # 高波动性币种 (6个)
            'APT',   # Aptos
            'SUI',   # Sui
            'SEI',   # Sei
            'TIA',   # Celestia
            'WLD',   # Worldcoin
            'NEAR'   # Near Protocol
        ]

        conn = get_db()
        cursor = conn.cursor()

        # 获取当前持仓（包含方向、杠杆、止盈止损信息）
        cursor.execute('''
            SELECT symbol, direction, leverage, take_profit, stop_loss FROM real_trades
            WHERE mode = 'paper' AND assistant = '交易助手' AND status = 'OPEN'
        ''')
        positions_dict = {row['symbol']: {
            'direction': row['direction'],
            'leverage': row['leverage'],
            'take_profit': row['take_profit'],
            'stop_loss': row['stop_loss']
        } for row in cursor.fetchall()}
        conn.close()

        # 获取每个币种的当前价格和建议
        watchlist = []
        for symbol in watch_symbols:
            try:
                price_data = get_price_value(symbol)
                has_position = symbol in positions_dict

                # 获取信号建议（包括持仓和非持仓币种）
                suggestion_data = None
                confidence = 0
                suggested_direction = None
                stop_loss = None
                take_profit = None
                leverage = None
                profit_pct = None
                loss_pct = None

                # 获取信号数据（总是返回数据，包括信心度）
                suggestion_data = get_signal_suggestion(symbol)
                if suggestion_data:
                    confidence = suggestion_data['confidence']

                if has_position:
                    # 持仓币种：使用实际的杠杆、止盈、止损
                    pos_info = positions_dict[symbol]
                    leverage = pos_info['leverage']
                    take_profit = pos_info['take_profit']
                    stop_loss = pos_info['stop_loss']
                else:
                    # 非持仓币种：显示建议的止盈止损，计算预估盈亏%
                    leverage = 10  # 默认10倍杠杆
                    if suggestion_data and suggestion_data.get('tradeable', False):
                        # 只有可交易的信号才显示建议方向和止盈止损
                        stop_loss = suggestion_data['stop_loss']
                        take_profit = suggestion_data['take_profit']
                        suggested_direction = suggestion_data['direction']

                        # 计算预估盈利%和亏损%（考虑杠杆）
                        if suggested_direction == 'LONG' and take_profit and stop_loss:
                            profit_pct = ((take_profit - price_data) / price_data) * leverage * 100
                            loss_pct = ((price_data - stop_loss) / price_data) * leverage * 100
                        elif suggested_direction == 'SHORT' and take_profit and stop_loss:
                            profit_pct = ((price_data - take_profit) / price_data) * leverage * 100
                            loss_pct = ((stop_loss - price_data) / price_data) * leverage * 100

                watchlist.append({
                    'symbol': symbol,
                    'price': price_data,
                    'has_position': has_position,
                    'direction': positions_dict[symbol]['direction'] if has_position else None,  # 当前持仓方向
                    'suggested_direction': suggested_direction,  # 建议方向（仅非持仓）
                    'confidence': confidence,  # 信心度分数
                    'stop_loss': stop_loss,  # 止损价位
                    'take_profit': take_profit,  # 止盈价位
                    'leverage': leverage,  # 杠杆倍数
                    'profit_pct': profit_pct,  # 预估盈利%（仅非持仓）
                    'loss_pct': loss_pct  # 预估亏损%（仅非持仓）
                })
            except Exception as e:
                has_position = symbol in positions_dict
                watchlist.append({
                    'symbol': symbol,
                    'price': 0,
                    'has_position': has_position,
                    'direction': positions_dict[symbol]['direction'] if has_position else None,
                    'suggested_direction': None,
                    'confidence': 0,
                    'stop_loss': None,
                    'take_profit': None,
                    'leverage': positions_dict[symbol]['leverage'] if has_position else 10,
                    'profit_pct': None,
                    'loss_pct': None,
                    'error': str(e)
                })

        # 排序：1. 有持仓的在最前 2. 按信心度降序
        watchlist.sort(key=lambda x: (not x['has_position'], -x['confidence']))

        return jsonify(watchlist)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def get_price_value(symbol):
    """获取币种当前价格（期货价格）"""
    symbol_map = {
        # 原有币种
        'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
        'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
        'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
        'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
        # 新增币种
        'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
        'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
        'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
        'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
    }
    binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

    # 使用Binance期货API，与交易系统保持一致
    url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={binance_symbol}"
    response = requests.get(url, timeout=5)
    data = response.json()
    return float(data['price'])

def get_signal_suggestion(symbol):
    """获取币种信号建议（做多/做空）+ 信心度 + 止盈止损"""
    try:
        symbol_map = {
            'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
            'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT',
            'VET': 'VETUSDT', 'BNB': 'BNBUSDT', 'INJ': 'INJUSDT',
            'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
            'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
            'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
            'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
            'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
        }
        binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

        # 获取K线数据（使用期货API）
        url = f"https://fapi.binance.com/fapi/v1/klines"
        params = {
            'symbol': binance_symbol,
            'interval': '5m',
            'limit': 50
        }
        response = requests.get(url, params=params, timeout=5)
        klines = response.json()

        if not klines or len(klines) < 30:
            return None

        # 计算简单RSI
        closes = [float(k[4]) for k in klines]
        changes = [closes[i] - closes[i-1] for i in range(1, len(closes))]
        gains = [c if c > 0 else 0 for c in changes]
        losses = [abs(c) if c < 0 else 0 for c in changes]

        avg_gain = sum(gains[-14:]) / 14 if len(gains) >= 14 else 0
        avg_loss = sum(losses[-14:]) / 14 if len(losses) >= 14 else 0

        if avg_loss == 0:
            rsi = 100
        else:
            rs = avg_gain / avg_loss
            rsi = 100 - (100 / (1 + rs))

        # 计算MA趋势
        ma7 = sum(closes[-7:]) / 7
        ma25 = sum(closes[-25:]) / 25
        current_price = closes[-1]

        # 计算信心度分数 (0-100)
        confidence = 0
        direction = None

        # RSI 分数 (40分)
        if rsi < 30:
            confidence += 40
            direction = 'LONG'
        elif rsi > 70:
            confidence += 40
            direction = 'SHORT'
        elif rsi < 40:
            confidence += 20
            direction = 'LONG'
        elif rsi > 60:
            confidence += 20
            direction = 'SHORT'

        # MA 趋势分数 (30分)
        if ma7 > ma25:
            confidence += 30
            if direction != 'SHORT':
                direction = 'LONG'
        elif ma7 < ma25:
            confidence += 30
            if direction != 'LONG':
                direction = 'SHORT'

        # 价格位置分数 (30分)
        if direction == 'LONG' and current_price > ma7:
            confidence += 30
        elif direction == 'SHORT' and current_price < ma7:
            confidence += 30
        elif direction == 'LONG' and current_price < ma7:
            confidence -= 10
        elif direction == 'SHORT' and current_price > ma7:
            confidence -= 10

        # 计算止盈止损 (基于当前价格或方向)
        if direction == 'LONG':
            stop_loss = current_price * 0.95  # -5%
            take_profit = current_price * 1.10  # +10%
        elif direction == 'SHORT':
            stop_loss = current_price * 1.05  # +5%
            take_profit = current_price * 0.90  # -10%
        else:
            # 无明确方向，返回基本信息
            stop_loss = None
            take_profit = None

        # 最低50分才标记为可交易（激进策略：增加交易频率）
        tradeable = confidence >= 50 and direction is not None

        return {
            'direction': direction,
            'confidence': max(0, min(confidence, 100)),  # 0-100分
            'stop_loss': stop_loss,
            'take_profit': take_profit,
            'current_price': current_price,
            'rsi': rsi,
            'tradeable': tradeable  # 是否可交易
        }

    except Exception as e:
        return None

# HTML模板
HTML_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交易助理 - Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header .subtitle {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card .label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card .value.positive {
            color: #10b981;
        }
        
        .stat-card .value.negative {
            color: #ef4444;
        }
        
        .stat-card .subtext {
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .badge.long {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge.short {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge.open {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge.closed {
            background: #e5e7eb;
            color: #374151;
        }

        .watchlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .watch-card {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .watch-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .watch-card.has-position {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .watch-symbol {
            font-size: 1.1em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .watch-card.has-position .watch-symbol {
            color: #10b981;
        }

        .watch-price {
            font-size: 0.95em;
            color: #999;
        }

        .watch-status {
            font-size: 0.75em;
            margin-top: 8px;
            padding: 3px 8px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            display: inline-block;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transition: width 0.5s;
        }
        
        .refresh-time {
            text-align: center;
            color: white;
            margin-top: 20px;
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* 持仓卡片样式 */
        .position-cards {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .position-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 8px;
            padding: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }

        /* 做多持仓 - 绿色边框 */
        .position-card.long {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(5, 150, 105, 0.05) 100%);
            border: 2px solid rgba(16, 185, 129, 0.4);
        }

        .position-card.long:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.6);
        }

        /* 做空持仓 - 红色边框 */
        .position-card.short {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(220, 38, 38, 0.05) 100%);
            border: 2px solid rgba(239, 68, 68, 0.4);
        }

        .position-card.short:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.6);
        }

        .position-card:hover {
            transform: translateY(-2px);
        }

        .position-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .position-card-title {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .position-card-symbol {
            font-size: 0.9em;
            font-weight: bold;
            color: #333;
        }

        .position-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 6px;
        }

        .position-card-main {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 6px;
        }

        .position-card-pnl {
            font-size: 1.1em;
            font-weight: bold;
        }

        .position-card-info {
            font-size: 0.75em;
            color: #666;
        }

        .position-card-label {
            color: #999;
            margin-right: 3px;
        }

        .position-card-value {
            font-weight: 600;
            color: #333;
        }

        .position-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7em;
            color: #666;
            padding-top: 6px;
            border-top: 1px dashed rgba(102, 126, 234, 0.2);
        }

        /* 交易历史卡片样式 */
        .trade-cards {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .trade-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(249, 250, 251, 0.9) 100%);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.2s;
        }

        .trade-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-color: #d1d5db;
        }

        .trade-card.closed {
            border-left: 3px solid #10b981;
        }

        .trade-card.closed.loss {
            border-left: 3px solid #ef4444;
        }

        .trade-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .trade-card-title {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .trade-card-symbol {
            font-size: 1em;
            font-weight: bold;
            color: #333;
        }

        .trade-card-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .trade-card-pnl {
            font-size: 1.2em;
            font-weight: bold;
        }

        .trade-card-roi {
            font-size: 0.95em;
            font-weight: 600;
        }

        .trade-card-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.75em;
            color: #666;
        }

        .trade-card-detail {
            display: flex;
            flex-direction: column;
        }

        .trade-card-detail-label {
            color: #999;
            margin-bottom: 2px;
        }

        .trade-card-detail-value {
            color: #333;
            font-weight: 500;
        }

        /* 小标签样式 */
        .badge-sm {
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 500;
        }

        .mini-btn {
            padding: 4px 10px;
            font-size: 0.75em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            background: #667eea;
            color: white;
        }

        .mini-btn:hover {
            background: #5568d3;
            transform: scale(1.05);
        }
        
        .loading::after {
            content: '...';
            animation: pulse 1.5s infinite;
        }
        
        /* 图表控制区域 */
        .chart-controls-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 10px;
        }

        .position-selector-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .position-selector-wrapper label {
            color: #666;
            font-size: 0.85em;
            font-weight: 500;
        }

        .position-selector-wrapper select {
            padding: 6px 12px;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 0.85em;
            cursor: pointer;
            background: white;
        }

        /* 时间周期按钮组 */
        .timeframe-selector {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }

        .timeframe-btn {
            padding: 5px 10px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75em;
            font-weight: 500;
            color: #666;
            transition: all 0.2s;
            min-width: 40px;
        }

        .timeframe-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .timeframe-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }
        
        /* 查看图表按钮 */
        .view-chart-btn {
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s;
        }
        
        .view-chart-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* 图表容器样式 */
        .chart-wrapper {
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .chart-title {
            font-size: 1em;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 10px;
            padding: 8px 10px;
            background: #f9fafb;
            border-radius: 6px;
        }

        .chart-info-grid.two-rows {
            grid-template-rows: auto auto;
        }

        .info-item {
            font-size: 0.8em;
        }

        .info-label {
            color: #666;
            font-weight: 500;
            margin-right: 3px;
        }

        .info-value {
            font-weight: bold;
            color: #333;
        }

        #charts-container {
            height: 100%;
            width: 100%;
        }

        .placeholder {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .placeholder-icon {
            font-size: 3em;
            margin-bottom: 20px;
        }

        /* 三栏布局 */
        .main-layout {
            display: grid;
            grid-template-columns: 200px 1fr 280px;
            gap: 20px;
            margin-bottom: 20px;
            height: 700px;
        }

        /* 底部区域 */
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .bottom-panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .bottom-panel h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .bottom-panel-content {
            max-height: 400px;
            overflow-y: auto;
        }

        /* 目标进度面板 */
        .progress-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .progress-stat-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .progress-stat-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
        }

        .progress-stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: #333;
        }

        .progress-stat-value.positive {
            color: #10b981;
        }

        .progress-stat-value.negative {
            color: #ef4444;
        }

        .progress-bar-large {
            width: 100%;
            height: 50px;
            background: #e5e7eb;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .progress-fill-large {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2em;
            transition: width 0.5s;
        }

        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.95em;
            color: #666;
            margin-top: 10px;
        }

        .daily-breakdown {
            margin-top: 15px;
        }

        .daily-breakdown-title {
            font-size: 1em;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 10px;
        }

        .daily-item {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .daily-item:last-child {
            border-bottom: none;
        }

        .daily-date {
            color: #666;
        }

        .daily-value {
            font-weight: 600;
        }

        .daily-value.positive {
            color: #10b981;
        }

        .daily-value.negative {
            color: #ef4444;
        }

        .left-panel {
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .left-panel h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
        }

        .left-panel-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .center-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .center-panel h2 {
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #333;
        }

        .center-panel-content {
            height: 600px;
            overflow-y: auto;
        }

        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
        }

        .right-panel-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .right-panel-section h2 {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #333;
        }

        /* 监控列表优化 - 垂直排列 */
        .watchlist-vertical {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .watch-card-vertical {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 12px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .watch-card-vertical:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            border-color: rgba(102, 126, 234, 0.4);
        }

        .watch-card-vertical.has-position {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .watch-card-vertical .watch-info {
            flex: 1;
        }

        .watch-card-vertical .watch-symbol {
            font-size: 1.1em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }

        .watch-card-vertical.has-position .watch-symbol {
            color: #10b981;
            font-size: 1.15em;
        }

        .watch-card-vertical .watch-price {
            font-size: 0.95em;
            color: #333;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .watch-card-vertical .watch-confidence {
            font-size: 0.8em;
            color: #667eea;
            font-weight: 700;
            margin-top: 3px;
        }

        .watch-card-vertical .watch-icon {
            font-size: 1.5em;
        }

        /* 顶部按钮栏 */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .panel-header h2 {
            margin: 0;
        }

        .header-btn {
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.3s;
        }

        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* 筛选按钮组 */
        .filter-buttons {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            padding: 5px 12px;
            background: #2d3748;
            color: #a0aec0;
            border: 1px solid #4a5568;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #374151;
            border-color: #667eea;
            color: white;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        /* 滚动条样式 */
        .left-panel-content::-webkit-scrollbar,
        .center-panel-content::-webkit-scrollbar,
        .right-panel::-webkit-scrollbar,
        .bottom-panel-content::-webkit-scrollbar {
            width: 6px;
        }

        .left-panel-content::-webkit-scrollbar-track,
        .center-panel-content::-webkit-scrollbar-track,
        .right-panel::-webkit-scrollbar-track,
        .bottom-panel-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb,
        .center-panel-content::-webkit-scrollbar-thumb,
        .right-panel::-webkit-scrollbar-thumb,
        .bottom-panel-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .left-panel-content::-webkit-scrollbar-thumb:hover,
        .center-panel-content::-webkit-scrollbar-thumb:hover,
        .right-panel::-webkit-scrollbar-thumb:hover,
        .bottom-panel-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* 响应式：小屏幕时恢复垂直布局 */
        @media (max-width: 1200px) {
            .main-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                height: auto;
            }

            .bottom-section {
                grid-template-columns: 1fr;
            }

            .watchlist-vertical {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .watch-card-vertical {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 交易助手仪表盘 v1.3</h1>
            <div class="subtitle">Paper Trading System - 按需加载 - Port 5111</div>
            <div style="margin-top: 10px;">
                <a href="/backtest" class="header-btn" style="text-decoration: none; padding: 8px 20px; font-size: 0.95em;">📊 回测模拟器</a>
                <a href="/report" class="header-btn" style="text-decoration: none; padding: 8px 20px; font-size: 0.95em;">📋 策略报告</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">资金统计</div>
                <div class="value" id="current-capital" style="font-size: 1.5em;">-</div>
                <div class="subtext" style="display: flex; flex-direction: column; gap: 4px; margin-top: 8px;">
                    <span>💰 初始: <span id="initial-capital">2000U</span></span>
                    <span>💵 可用: <span id="available-capital" style="color: #10b981; font-weight: bold;">-</span></span>
                    <span>🔒 占用: <span id="margin-used" style="color: #999;">-</span></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">总盈亏 (已扣费)</div>
                <div class="value" id="total-pnl">-</div>
                <div class="subtext" style="display: flex; flex-direction: column; gap: 4px; margin-top: 8px;">
                    <span>🎯 目标: <span id="target-profit">3400U</span></span>
                    <span>💳 交易费: <span id="total-fees" style="color: #ef4444;">-</span></span>
                    <span>⚡ 资金费: <span id="total-funding-fees" style="color: #ef4444;">-</span></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">胜率</div>
                <div class="value" id="win-rate">-</div>
                <div class="subtext"><span id="win-count">0</span> 胜 / <span id="total-count">0</span> 笔</div>
            </div>
            
            <div class="stat-card">
                <div class="label">持仓数</div>
                <div class="value" id="open-positions">-</div>
                <div class="subtext">最多同时8个</div>
            </div>
        </div>

        <!-- 风险监控面板 -->
        <div class="risk-panel" id="risk-panel" style="margin: 15px 0; padding: 15px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; border-left: 4px solid #10b981;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="margin: 0; color: white; font-size: 1.1em;">⚠️ 风险监控</h3>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 0.85em; color: #999;">风险等级:</span>
                    <span id="risk-level-badge" style="padding: 4px 12px; border-radius: 6px; font-size: 0.85em; font-weight: bold; background: #10b981; color: white;">低风险</span>
                    <span id="risk-score-display" style="font-size: 0.85em; color: #999;">评分: <span id="risk-score">0</span>/10</span>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">📉 最大回撤</div>
                    <div id="max-drawdown" style="font-size: 1.2em; font-weight: bold; color: #ef4444;">-</div>
                </div>
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">⚠️ 当前回撤</div>
                    <div id="current-drawdown" style="font-size: 1.2em; font-weight: bold; color: #f59e0b;">-</div>
                </div>
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">🔴 连续亏损</div>
                    <div id="consecutive-losses" style="font-size: 1.2em; font-weight: bold; color: #ef4444;">-</div>
                </div>
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">⚖️ 持仓集中</div>
                    <div id="position-concentration" style="font-size: 1.2em; font-weight: bold; color: #f59e0b;">-</div>
                </div>
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">📊 多/空比例</div>
                    <div id="long-short-ratio" style="font-size: 1.2em; font-weight: bold; color: #667eea;">-</div>
                </div>
                <div class="risk-item">
                    <div style="font-size: 0.8em; color: #999;">💪 杠杆倍率</div>
                    <div id="leverage-ratio" style="font-size: 1.2em; font-weight: bold; color: #10b981;">-</div>
                </div>
            </div>
        </div>

        <!-- 三栏布局 -->
        <div class="main-layout">
            <!-- 左侧：监控列表 -->
            <div class="left-panel">
                <div class="panel-header">
                    <h2 style="font-size: 1.2em;">👁️ 监控列表</h2>
                    <div style="font-size: 0.75em; color: #999; margin-top: 4px;">
                        <span id="watchlist-countdown">刷新: --</span>
                    </div>
                </div>
                <div class="left-panel-content">
                    <div id="watchlist-container">
                        <div class="loading">加载中</div>
                    </div>
                </div>
            </div>

            <!-- 中间：持仓实时图表 -->
            <div class="center-panel">
                <h2>📈 持仓实时图表</h2>
                <div id="chart-controls" style="display: none;">
                    <div class="chart-controls-wrapper">
                        <!-- 中间：时间周期 -->
                        <div class="timeframe-selector">
                            <button class="timeframe-btn active" data-interval="5m" onclick="changeTimeframe('5m', this)">5m</button>
                            <button class="timeframe-btn" data-interval="10m" onclick="changeTimeframe('10m', this)">10m</button>
                            <button class="timeframe-btn" data-interval="30m" onclick="changeTimeframe('30m', this)">30m</button>
                            <button class="timeframe-btn" data-interval="1h" onclick="changeTimeframe('1h', this)">1h</button>
                            <button class="timeframe-btn" data-interval="4h" onclick="changeTimeframe('4h', this)">4h</button>
                            <button class="timeframe-btn" data-interval="1d" onclick="changeTimeframe('1d', this)">1d</button>
                        </div>
                        <!-- 右上：持仓选择 -->
                        <div class="position-selector-wrapper">
                            <label>持仓:</label>
                            <select id="position-selector" onchange="loadSelectedChart()">
                                <option value="">-- 请选择 --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="center-panel-content">
                    <div id="charts-container">
                        <div class="placeholder">
                            <div class="placeholder-icon">📊</div>
                            <div style="font-size: 1.1em; margin-bottom: 8px;">请从右侧"当前持仓"点击查看</div>
                            <div style="font-size: 0.9em; color: #999;">或使用上方下拉框选择</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧：当前持仓 -->
            <div class="right-panel">
                <div class="right-panel-section">
                    <div class="panel-header">
                        <h2>📦 当前持仓</h2>
                    </div>
                    <div class="filter-buttons" style="margin-bottom: 12px;">
                        <button class="filter-btn active" onclick="filterPositions('all')">全部</button>
                        <button class="filter-btn" onclick="filterPositions('long')">📈 做多</button>
                        <button class="filter-btn" onclick="filterPositions('short')">📉 做空</button>
                    </div>
                    <div id="positions-table">
                        <div class="loading">加载中</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 底部区域：交易历史 + 目标进度 -->
        <div class="bottom-section">
            <!-- 交易历史 -->
            <div class="bottom-panel">
                <h2>📊 交易历史</h2>
                <div class="bottom-panel-content" id="trades-table">
                    <div class="loading">加载中</div>
                </div>
            </div>

            <!-- 目标进度 -->
            <div class="bottom-panel">
                <h2>🎯 目标进度追踪</h2>
                <div class="progress-panel">
                    <!-- 进度条 -->
                    <div>
                        <div class="progress-bar-large">
                            <div class="progress-fill-large" id="progress-bar-large" style="width: 0%">0%</div>
                        </div>
                        <div class="progress-details">
                            <span>已完成: <strong id="progress-earned">0U</strong></span>
                            <span>还需: <strong id="progress-remaining">3400U</strong></span>
                        </div>
                    </div>

                    <!-- 统计数据 -->
                    <div class="progress-stats">
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">目标金额</div>
                            <div class="progress-stat-value" id="progress-target">3400U</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">当前盈亏</div>
                            <div class="progress-stat-value" id="progress-current">-</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">平均日收益</div>
                            <div class="progress-stat-value" id="progress-daily-avg">-</div>
                        </div>
                        <div class="progress-stat-item">
                            <div class="progress-stat-label">预计完成天数</div>
                            <div class="progress-stat-value" id="progress-days">-</div>
                        </div>
                    </div>

                    <!-- 每日盈亏明细 -->
                    <div class="daily-breakdown">
                        <div class="daily-breakdown-title">📅 最近7天盈亏</div>
                        <div id="daily-pnl-list">
                            <div class="loading">加载中</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="refresh-time">
            最后更新: <span id="last-update">-</span> | 每60秒自动刷新
        </div>
    </div>
    
    <script>
        // 全局变量
        let currentInterval = '5m';
        let currentPositions = [];
        let allPositions = []; // 存储所有持仓（未筛选）
        let positionFilter = 'all'; // 持仓筛选状态: all, long, short
        let selectedPositionIndex = -1;
        let currentTrades = [];

        // 筛选持仓
        function filterPositions(filter) {
            positionFilter = filter;

            // 更新按钮样式
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // 重新渲染持仓
            renderPositions();
        }

        // 查看指定持仓的图表
        function viewChart(symbol, index) {
            selectedPositionIndex = index;

            // 显示图表控制区域
            document.getElementById('chart-controls').style.display = 'block';

            // 更新持仓选择器
            const selector = document.getElementById('position-selector');
            selector.value = index;

            // 滚动到图表区域
            document.getElementById('charts-container').scrollIntoView({ behavior: 'smooth', block: 'start' });

            // 加载该持仓的图表
            if (currentPositions.length > 0 && index >= 0 && index < currentPositions.length) {
                loadSingleChart(currentPositions[index]);
            }
        }

        // 查看交易复盘图表
        async function viewTradeChart(index) {
            if (!currentTrades || index < 0 || index >= currentTrades.length) {
                alert('无法加载交易数据');
                return;
            }

            const trade = currentTrades[index];

            // 隐藏常规图表控制
            document.getElementById('chart-controls').style.display = 'none';

            // 显示加载状态
            const container = document.getElementById('charts-container');
            container.innerHTML = '<div class="loading">加载复盘图表中...</div>';

            // 滚动到图表区域
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });

            try {
                // 获取K线数据（使用5分钟周期）
                const klineResp = await fetch(`/api/kline/${trade.symbol}?interval=5m`);
                const klineData = await klineResp.json();

                // 创建图表容器
                const chartDiv = document.createElement('div');
                chartDiv.className = 'chart-wrapper';

                const directionEmoji = trade.direction === 'LONG' ? '📈' : '📉';
                const directionText = trade.direction === 'LONG' ? '做多' : '做空';
                const directionColor = trade.direction === 'LONG' ? '#10b981' : '#ef4444';
                const pnlColor = trade.pnl >= 0 ? '#10b981' : '#ef4444';
                const roiColor = trade.roi >= 0 ? '#10b981' : '#ef4444';

                const title = document.createElement('div');
                title.className = 'chart-title';
                title.innerHTML = `
                    <span>📊</span>
                    <span>${trade.symbol}/USDT 复盘</span>
                    <span style="color: ${directionColor}; font-size: 0.9em;">${directionText}</span>
                    <span style="color: #667eea; font-size: 0.85em;">${trade.leverage}x杠杆</span>
                    <span style="color: #999; font-size: 0.75em; margin-left: auto;">已平仓</span>
                `;

                const info = document.createElement('div');
                info.className = 'chart-info-grid';
                info.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">📍 入场价:</span>
                        <span class="info-value" style="color: #3b82f6;">$${formatNumber(trade.entry_price, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🚪 出场价:</span>
                        <span class="info-value" style="color: #f59e0b;">$${formatNumber(trade.exit_price, 6)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💼 仓位:</span>
                        <span class="info-value">${formatNumber(trade.amount, 0)}U × ${trade.leverage}x</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💵 盈亏:</span>
                        <span class="info-value" style="color: ${pnlColor}; font-size: 1.15em;">${formatCurrency(trade.pnl)}U</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📊 ROI:</span>
                        <span class="info-value" style="color: ${roiColor}; font-size: 1.15em;">${formatCurrency(trade.roi)}%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💳 交易手续费:</span>
                        <span class="info-value" style="color: #999;">$${formatNumber(trade.fee, 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⚡ 资金费率:</span>
                        <span class="info-value" style="color: #999;">$${formatNumber(trade.funding_fee || 0, 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 总费用:</span>
                        <span class="info-value" style="color: #ef4444;">$${formatNumber((trade.fee || 0) + (trade.funding_fee || 0), 2)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 入场时间:</span>
                        <span class="info-value" style="font-size: 0.9em;">${formatTime(trade.entry_time)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 出场时间:</span>
                        <span class="info-value" style="font-size: 0.9em;">${formatTime(trade.exit_time)}</span>
                    </div>
                    ${trade.reason ? `
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">📝 平仓原因:</span>
                        <span class="info-value" style="color: #667eea;">${trade.reason}</span>
                    </div>
                    ` : ''}
                `;

                const canvasWrapper = document.createElement('div');
                canvasWrapper.style.flex = '1';
                canvasWrapper.style.minHeight = '0';
                canvasWrapper.style.position = 'relative';

                const canvas = document.createElement('canvas');
                canvas.id = `trade-chart-${index}`;

                canvasWrapper.appendChild(canvas);
                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvasWrapper);
                container.innerHTML = '';
                container.appendChild(chartDiv);

                // 准备图表数据
                const timeFormat = {hour: '2-digit', minute: '2-digit'};
                const labels = klineData.map(k => new Date(k.time).toLocaleString('zh-CN', timeFormat));
                const prices = klineData.map(k => k.close);

                // 找到入场和出场时间对应的索引
                const entryTime = new Date(trade.entry_time).getTime();
                const exitTime = new Date(trade.exit_time).getTime();

                let entryIndex = 0;
                let exitIndex = klineData.length - 1;

                for (let i = 0; i < klineData.length; i++) {
                    if (klineData[i].time >= entryTime && entryIndex === 0) {
                        entryIndex = i;
                    }
                    if (klineData[i].time >= exitTime) {
                        exitIndex = i;
                        break;
                    }
                }

                // 创建图表
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '价格走势',
                            data: prices,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.1,
                            pointRadius: 0,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            annotation: {
                                annotations: {
                                    // 入场价格线（蓝色虚线）
                                    entryLine: {
                                        type: 'line',
                                        yMin: trade.entry_price,
                                        yMax: trade.entry_price,
                                        borderColor: '#3b82f6',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `📍 入场 $${formatNumber(trade.entry_price, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#3b82f6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 入场点标记
                                    entryPoint: {
                                        type: 'point',
                                        xValue: entryIndex,
                                        yValue: trade.entry_price,
                                        backgroundColor: '#3b82f6',
                                        borderColor: '#ffffff',
                                        borderWidth: 3,
                                        radius: 8
                                    },
                                    // 出场价格线（橙色虚线）
                                    exitLine: {
                                        type: 'line',
                                        yMin: trade.exit_price,
                                        yMax: trade.exit_price,
                                        borderColor: '#f59e0b',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🚪 出场 $${formatNumber(trade.exit_price, 6)}`,
                                            enabled: true,
                                            position: 'end',
                                            backgroundColor: '#f59e0b',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 出场点标记
                                    exitPoint: {
                                        type: 'point',
                                        xValue: exitIndex,
                                        yValue: trade.exit_price,
                                        backgroundColor: '#f59e0b',
                                        borderColor: '#ffffff',
                                        borderWidth: 3,
                                        radius: 8
                                    },
                                    // 止盈线（绿色虚线）
                                    takeProfitLine: {
                                        type: 'line',
                                        yMin: trade.take_profit,
                                        yMax: trade.take_profit,
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🎯 止盈 $${formatNumber(trade.take_profit, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#10b981',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 止损线（红色虚线）
                                    stopLossLine: {
                                        type: 'line',
                                        yMin: trade.stop_loss,
                                        yMax: trade.stop_loss,
                                        borderColor: '#ef4444',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🛑 止损 $${formatNumber(trade.stop_loss, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#ef4444',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#999',
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                display: true,
                                position: 'right',
                                grid: {
                                    display: true,
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    color: '#999',
                                    callback: function(value) {
                                        return '$' + value.toFixed(6);
                                    }
                                }
                            }
                        }
                    }
                });

            } catch (error) {
                console.error('加载复盘图表失败:', error);
                container.innerHTML = '<p style="color: #ef4444;">加载图表失败</p>';
            }
        }
        
        // 从下拉框选择持仓
        function loadSelectedChart() {
            const selector = document.getElementById('position-selector');
            const index = parseInt(selector.value);
            
            if (!isNaN(index) && index >= 0 && index < currentPositions.length) {
                selectedPositionIndex = index;
                loadSingleChart(currentPositions[index]);
            } else {
                // 清空图表
                document.getElementById('charts-container').innerHTML = `
                    <div class="placeholder">
                        <div class="placeholder-icon">📊</div>
                        <div style="font-size: 1.2em;">请选择要查看的持仓</div>
                    </div>
                `;
            }
        }
        
        // 切换时间周期
        function changeTimeframe(interval, btn) {
            currentInterval = interval;
            
            // 更新按钮状态
            document.querySelectorAll('.timeframe-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // 重新加载当前选中的图表
            if (selectedPositionIndex >= 0 && currentPositions.length > 0) {
                loadSingleChart(currentPositions[selectedPositionIndex]);
            }
        }
        
        function formatNumber(num, decimals = 2) {
            if (num === null || num === undefined) return '-';
            return Number(num).toFixed(decimals);
        }
        
        function formatCurrency(num) {
            if (num === null || num === undefined) return '-';
            const formatted = formatNumber(num, 2);
            return num >= 0 ? '+' + formatted : formatted;
        }
        
        function formatTime(timeStr) {
            if (!timeStr) return '-';
            const date = new Date(timeStr);
            return date.toLocaleString('zh-CN', { 
                month: '2-digit', 
                day: '2-digit', 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        async function loadStats() {
            console.log('=== loadStats 开始 ===');
            try {
                const response = await fetch('/api/stats');
                console.log('API响应状态:', response.status);
                const stats = await response.json();
                console.log('Stats数据:', JSON.stringify(stats).substring(0, 200));

                document.getElementById('current-capital').textContent = formatNumber(stats.current_capital, 2) + 'U';
                document.getElementById('current-capital').className = 'value ' + (stats.current_capital >= stats.initial_capital ? 'positive' : 'negative');

                document.getElementById('initial-capital').textContent = formatNumber(stats.initial_capital, 2) + 'U';
                document.getElementById('available-capital').textContent = formatNumber(stats.available_capital, 2) + 'U';
                document.getElementById('margin-used').textContent = formatNumber(stats.margin_used, 2) + 'U';
                
                document.getElementById('total-pnl').textContent = formatCurrency(stats.total_pnl) + 'U';
                document.getElementById('total-pnl').className = 'value ' + (stats.total_pnl >= 0 ? 'positive' : 'negative');

                document.getElementById('target-profit').textContent = formatNumber(stats.target_profit, 2) + 'U';
                document.getElementById('total-fees').textContent = formatNumber(stats.total_fees || 0, 2) + 'U';
                document.getElementById('total-funding-fees').textContent = formatNumber(stats.total_funding_fees || 0, 2) + 'U';
                
                document.getElementById('win-rate').textContent = formatNumber(stats.win_rate, 1) + '%';
                document.getElementById('win-count').textContent = stats.win_trades || 0;
                document.getElementById('total-count').textContent = stats.total_trades || 0;
                
                document.getElementById('open-positions').textContent = stats.open_positions || 0;
                
                const progress = Math.min(100, Math.max(0, stats.progress || 0));
                const progressBar = document.getElementById('progress-bar-large');
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                    progressBar.textContent = formatNumber(progress, 1) + '%';
                }
                
                const earned = stats.total_pnl || 0;
                const remaining = Math.max(0, stats.target_profit - earned);
                const earnedEl = document.getElementById('progress-earned');
                const remainingEl = document.getElementById('progress-remaining');
                if (earnedEl) earnedEl.textContent = formatNumber(earned, 2) + 'U';
                if (remainingEl) remainingEl.textContent = formatNumber(remaining, 2) + 'U';

                // 更新风险监控指标
                console.log('风险数据:', stats.max_drawdown, stats.current_drawdown, stats.consecutive_losses);
                if (stats.max_drawdown !== undefined) {
                    console.log('正在更新风险指标...');
                    // 最大回撤
                    document.getElementById('max-drawdown').textContent = formatNumber(stats.max_drawdown, 2) + '%';
                    console.log('最大回撤已更新:', formatNumber(stats.max_drawdown, 2) + '%');

                    // 当前回撤
                    const currentDrawdown = stats.current_drawdown || 0;
                    document.getElementById('current-drawdown').textContent = formatNumber(currentDrawdown, 2) + '%';
                    document.getElementById('current-drawdown').style.color = currentDrawdown > 10 ? '#ef4444' : (currentDrawdown > 5 ? '#f59e0b' : '#10b981');

                    // 连续亏损
                    const consecutiveLosses = stats.consecutive_losses || 0;
                    document.getElementById('consecutive-losses').textContent = consecutiveLosses + '次';
                    document.getElementById('consecutive-losses').style.color = consecutiveLosses >= 3 ? '#ef4444' : (consecutiveLosses >= 2 ? '#f59e0b' : '#10b981');

                    // 持仓集中度
                    const concentration = stats.max_position_concentration || 0;
                    document.getElementById('position-concentration').textContent = formatNumber(concentration, 1) + '%';
                    document.getElementById('position-concentration').style.color = concentration > 40 ? '#ef4444' : (concentration > 30 ? '#f59e0b' : '#10b981');

                    // 多空比例
                    const longRatio = stats.long_ratio || 0;
                    const shortRatio = stats.short_ratio || 0;
                    document.getElementById('long-short-ratio').textContent = formatNumber(longRatio, 0) + '% / ' + formatNumber(shortRatio, 0) + '%';

                    // 杠杆倍率
                    const leverageRatio = stats.leverage_ratio || 0;
                    document.getElementById('leverage-ratio').textContent = formatNumber(leverageRatio, 2) + 'x';
                    document.getElementById('leverage-ratio').style.color = leverageRatio > 3 ? '#ef4444' : (leverageRatio > 2 ? '#f59e0b' : '#10b981');

                    // 风险评级
                    const riskScore = stats.risk_score || 0;
                    const riskLevel = stats.risk_level || '低风险';
                    const riskColor = stats.risk_color || '#10b981';

                    document.getElementById('risk-score').textContent = riskScore;
                    document.getElementById('risk-level-badge').textContent = riskLevel;
                    document.getElementById('risk-level-badge').style.background = riskColor;

                    // 更新风险面板边框颜色
                    const riskPanel = document.getElementById('risk-panel');
                    riskPanel.style.borderLeftColor = riskColor;
                }

            } catch (error) {
                console.error('加载统计失败:', error);
            }
        }
        
        async function loadPositions() {
            try {
                const response = await fetch('/api/positions');
                const positions = await response.json();

                const container = document.getElementById('positions-table');

                if (positions.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无持仓</p>';
                    allPositions = [];
                    currentPositions = [];
                    document.getElementById('position-selector').innerHTML = '<option value="">-- 暂无持仓 --</option>';
                    return;
                }

                // 获取所有持仓的当前价格
                const pricePromises = positions.map(pos =>
                    fetch(`/api/price/${pos.symbol}`).then(r => r.json())
                );
                const prices = await Promise.all(pricePromises);

                // 为每个持仓添加当前价格
                positions.forEach((pos, i) => {
                    pos.currentPrice = prices[i].price || 0;
                });

                // 保存到全局变量
                allPositions = positions;

                // 渲染持仓（应用筛选）
                renderPositions();

            } catch (error) {
                console.error('加载持仓失败:', error);
                document.getElementById('positions-table').innerHTML = '<p style="color: #ef4444;">加载失败</p>';
            }
        }

        function renderPositions() {
            const container = document.getElementById('positions-table');

            if (!allPositions || allPositions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无持仓</p>';
                return;
            }

            // 根据筛选器过滤持仓
            let filteredPositions = allPositions;
            if (positionFilter === 'long') {
                filteredPositions = allPositions.filter(pos => pos.direction === 'LONG');
            } else if (positionFilter === 'short') {
                filteredPositions = allPositions.filter(pos => pos.direction === 'SHORT');
            }

            if (filteredPositions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">无匹配持仓</p>';
                return;
            }

            let html = '<div class="position-cards">';

            filteredPositions.forEach((pos, i) => {
                const currentPrice = pos.currentPrice || 0;

                let pricePct = 0;
                if (pos.direction === 'LONG') {
                    pricePct = (currentPrice - pos.entry_price) / pos.entry_price;
                } else {
                    pricePct = (pos.entry_price - currentPrice) / pos.entry_price;
                }

                const roi = pricePct * pos.leverage * 100;
                const pnl = pos.amount * pricePct * pos.leverage;

                const directionText = pos.direction === 'LONG' ? '做多' : '做空';
                const directionClass = pos.direction.toLowerCase();
                const directionEmoji = pos.direction === 'LONG' ? '📈' : '📉';
                const pnlColor = pnl >= 0 ? '#10b981' : '#ef4444';

                // 找到在allPositions中的原始索引（用于viewChart）
                const originalIndex = allPositions.indexOf(pos);

                html += `
                    <div class="position-card ${directionClass}" onclick="viewChart('${pos.symbol}', ${originalIndex})">
                        <div class="position-card-header">
                            <div class="position-card-title">
                                <span class="position-card-symbol">${directionEmoji} ${pos.symbol}</span>
                                <span class="badge-sm ${directionClass}">${directionText}</span>
                                <span class="badge-sm" style="background: #667eea; color: white;">${pos.leverage}x</span>
                            </div>
                            <button class="mini-btn" onclick="event.stopPropagation(); viewChart('${pos.symbol}', ${originalIndex})">📊 图表</button>
                        </div>

                        <div class="position-card-body">
                            <div class="position-card-main">
                                <div>
                                    <div style="font-size: 0.75em; color: #999;">当前价</div>
                                    <div style="font-size: 1.1em; font-weight: bold; color: #667eea;">$${formatNumber(currentPrice, 4)}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.75em; color: #999;">盈亏</div>
                                    <div class="position-card-pnl" style="color: ${pnlColor};">
                                        ${formatCurrency(pnl)}U
                                    </div>
                                    <div style="font-size: 0.85em; color: ${pnlColor};">
                                        ${formatCurrency(roi)}%
                                    </div>
                                </div>
                            </div>

                            <div class="position-card-info">
                                <span class="position-card-label">入场:</span>
                                <span class="position-card-value">$${formatNumber(pos.entry_price, 4)}</span>
                            </div>

                            <div class="position-card-info">
                                <span class="position-card-label">金额:</span>
                                <span class="position-card-value">${formatNumber(pos.amount, 0)}U</span>
                            </div>
                        </div>

                        <div class="position-card-footer">
                            <span>🎯 ${formatNumber(pos.take_profit, 4)}</span>
                            <span>🛑 ${formatNumber(pos.stop_loss, 4)}</span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;

            // 更新currentPositions为所有持仓（用于其他功能）
            currentPositions = allPositions;

            // 填充持仓选择器
            const selector = document.getElementById('position-selector');
            selector.innerHTML = '<option value="">-- 请选择 --</option>';
            allPositions.forEach((pos, idx) => {
                const direction = pos.direction === 'LONG' ? '做多' : '做空';
                selector.innerHTML += `<option value="${idx}">${pos.symbol} ${direction} ${pos.leverage}x</option>`;
            });

            // 如果之前有选中的持仓，保持显示
            if (selectedPositionIndex >= 0 && selectedPositionIndex < allPositions.length) {
                selector.value = selectedPositionIndex;
                loadSingleChart(allPositions[selectedPositionIndex]);
            }
        }

        async function loadTrades() {
            try {
                const response = await fetch('/api/trades');
                const trades = await response.json();

                const container = document.getElementById('trades-table');

                if (trades.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">暂无交易记录</p>';
                    return;
                }

                let html = '<div class="trade-cards">';

                trades.forEach((trade, index) => {
                    const pnl = trade.pnl || 0;
                    const roi = trade.roi || 0;

                    const directionText = trade.direction === 'LONG' ? '做多' : '做空';
                    const directionClass = trade.direction.toLowerCase();
                    const statusText = trade.status === 'OPEN' ? '持仓中' : '已平仓';
                    const statusClass = trade.status.toLowerCase();
                    const pnlColor = pnl >= 0 ? '#10b981' : '#ef4444';
                    const lossClass = (trade.status === 'CLOSED' && pnl < 0) ? 'loss' : '';

                    html += `
                        <div class="trade-card ${statusClass} ${lossClass}">
                            <div class="trade-card-header">
                                <div class="trade-card-title">
                                    <span class="trade-card-symbol">${trade.symbol}</span>
                                    <span class="badge-sm ${directionClass}">${directionText}</span>
                                    <span class="badge-sm ${statusClass}">${statusText}</span>
                                </div>
                                ${trade.status === 'CLOSED' ?
                                    `<button class="mini-btn" onclick="viewTradeChart(${index})">📊</button>` :
                                    '<span style="color: #999; font-size: 0.75em;">-</span>'
                                }
                            </div>

                            <div class="trade-card-main">
                                <div>
                                    <div class="trade-card-pnl" style="color: ${pnlColor};">
                                        ${formatCurrency(pnl)}U
                                    </div>
                                </div>
                                <div class="trade-card-roi" style="color: ${pnlColor};">
                                    ${formatCurrency(roi)}%
                                </div>
                            </div>

                            <div class="trade-card-details">
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">入场</span>
                                    <span class="trade-card-detail-value">$${formatNumber(trade.entry_price, 4)}</span>
                                </div>
                                ${trade.exit_price ? `
                                    <div class="trade-card-detail">
                                        <span class="trade-card-detail-label">出场</span>
                                        <span class="trade-card-detail-value">$${formatNumber(trade.exit_price, 4)}</span>
                                    </div>
                                ` : '<div class="trade-card-detail"></div>'}
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">仓位</span>
                                    <span class="trade-card-detail-value">${formatNumber(trade.amount, 0)}U</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">杠杆</span>
                                    <span class="trade-card-detail-value">${trade.leverage}x</span>
                                </div>
                            </div>

                            <div class="trade-card-details" style="margin-top: 4px;">
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">初始止损</span>
                                    <span class="trade-card-detail-value">${trade.initial_stop_loss ? '$' + formatNumber(trade.initial_stop_loss, 4) : '-'}</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">最终止损</span>
                                    <span class="trade-card-detail-value">${trade.final_stop_loss ? '$' + formatNumber(trade.final_stop_loss, 4) : (trade.stop_loss ? '$' + formatNumber(trade.stop_loss, 4) : '-')}</span>
                                </div>
                                <div class="trade-card-detail">
                                    <span class="trade-card-detail-label">移动次数</span>
                                    <span class="trade-card-detail-value">${trade.stop_move_count !== null && trade.stop_move_count !== undefined ? trade.stop_move_count : '-'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;

                // 保存到全局变量
                currentTrades = trades;

            } catch (error) {
                console.error('加载交易历史失败:', error);
                document.getElementById('trades-table').innerHTML = '<p style="color: #ef4444; font-size: 0.9em;">加载失败</p>';
            }
        }

        async function loadWatchlist() {
            try {
                const response = await fetch('/api/watchlist');
                const watchlist = await response.json();

                const container = document.getElementById('watchlist-container');

                if (watchlist.length === 0) {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 15px; font-size: 0.9em;">无监控币种</p>';
                    return;
                }

                let html = '<div class="watchlist-vertical">';

                watchlist.forEach(coin => {
                    const hasPosition = coin.has_position ? 'has-position' : '';
                    // 使用真实信心度
                    const confidence = coin.confidence || 0;

                    // 方向/建议标签
                    let directionBadge = '';
                    if (coin.has_position && coin.direction) {
                        // 有持仓：显示当前方向
                        const isLong = coin.direction === 'LONG';
                        const directionText = isLong ? '做多' : '做空';
                        const directionEmoji = isLong ? '📈' : '📉';
                        const directionColor = isLong ? '#10b981' : '#ef4444';
                        directionBadge = `<span style="font-size: 0.75em; background: ${directionColor}; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">${directionEmoji} ${directionText}</span>`;
                    } else if (!coin.has_position && coin.suggested_direction) {
                        // 无持仓：显示建议
                        const isLong = coin.suggested_direction === 'LONG';
                        const suggestionText = isLong ? '建议做多' : '建议做空';
                        const suggestionEmoji = isLong ? '📈' : '📉';
                        const suggestionColor = isLong ? '#10b981' : '#f59e0b';
                        directionBadge = `<span style="font-size: 0.7em; background: ${suggestionColor}; color: white; padding: 2px 5px; border-radius: 4px; margin-left: 6px; opacity: 0.8;">${suggestionEmoji} ${suggestionText}</span>`;
                    }

                    // 止盈止损和杠杆信息
                    let detailsInfo = '';

                    if (coin.has_position) {
                        // 持仓币种：显示信心度、杠杆、止盈、止损
                        detailsInfo = `
                            <div style="font-size: 0.7em; color: #666; margin-top: 4px; line-height: 1.4;">
                                ${confidence > 0 ? `<div>💪 信心度: ${confidence}%</div>` : ''}
                                ${coin.leverage ? `<div>⚡ 杠杆: ${coin.leverage}x</div>` : ''}
                                ${coin.take_profit ? `<div>🎯 止盈: $${formatNumber(coin.take_profit, 4)}</div>` : ''}
                                ${coin.stop_loss ? `<div>🛑 止损: $${formatNumber(coin.stop_loss, 4)}</div>` : ''}
                            </div>
                        `;
                    } else {
                        // 非持仓币种：显示信心度、杠杆、止盈止损价位、预估盈利%、预估亏损%
                        detailsInfo = `
                            <div style="font-size: 0.7em; color: #666; margin-top: 4px; line-height: 1.4;">
                                ${confidence >= 0 ? `<div>💪 信心度: ${confidence}%</div>` : ''}
                                ${coin.leverage ? `<div>⚡ 杠杆: ${coin.leverage}x</div>` : ''}
                                ${coin.take_profit ? `<div>🎯 止盈: $${formatNumber(coin.take_profit, 4)}</div>` : ''}
                                ${coin.stop_loss ? `<div>🛑 止损: $${formatNumber(coin.stop_loss, 4)}</div>` : ''}
                                ${coin.profit_pct !== null ? `<div style="color: #10b981;">📈 预估盈利: ${formatNumber(coin.profit_pct, 2)}%</div>` : ''}
                                ${coin.loss_pct !== null ? `<div style="color: #ef4444;">📉 预估亏损: ${formatNumber(coin.loss_pct, 2)}%</div>` : ''}
                            </div>
                        `;
                    }

                    html += `
                        <div class="watch-card-vertical ${hasPosition}">
                            <div class="watch-info">
                                <div class="watch-symbol">${coin.symbol} ${directionBadge}</div>
                                <div class="watch-price">$${formatNumber(coin.price, 4)}</div>
                                ${detailsInfo}
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
                container.innerHTML = html;

            } catch (error) {
                console.error('加载监控列表失败:', error);
                document.getElementById('watchlist-container').innerHTML = '<p style="color: #ef4444; font-size: 0.9em;">加载失败</p>';
            }
        }

        async function loadSingleChart(pos) {
            const container = document.getElementById('charts-container');
            container.innerHTML = '<div class="loading">加载图表中</div>';
            
            // 显示图表控制区域
            document.getElementById('chart-controls').style.display = 'block';
            
            try {
                // 获取K线数据（使用当前选中的时间周期）
                const klineResp = await fetch(`/api/kline/${pos.symbol}?interval=${currentInterval}`);
                const klineData = await klineResp.json();
                
                // 获取当前价格
                const priceResp = await fetch(`/api/price/${pos.symbol}`);
                const priceData = await priceResp.json();
                const currentPrice = priceData.price || 0;
                
                // 计算盈亏
                let pricePct = 0;
                if (pos.direction === 'LONG') {
                    pricePct = (currentPrice - pos.entry_price) / pos.entry_price;
                } else {
                    pricePct = (pos.entry_price - currentPrice) / pos.entry_price;
                }
                const roi = pricePct * pos.leverage * 100;
                const pnl = pos.amount * pricePct * pos.leverage;
                
                // 创建图表容器
                const chartDiv = document.createElement('div');
                chartDiv.className = 'chart-wrapper';
                
                const directionEmoji = pos.direction === 'LONG' ? '📈' : '📉';
                const directionText = pos.direction === 'LONG' ? '做多' : '做空';
                const directionColor = pos.direction === 'LONG' ? '#10b981' : '#ef4444';
                
                const title = document.createElement('div');
                title.className = 'chart-title';
                title.innerHTML = `
                    <span>${directionEmoji}</span>
                    <span>${pos.symbol}/USDT</span>
                    <span style="color: ${directionColor}; font-size: 0.9em;">${directionText}</span>
                    <span style="color: #667eea; font-size: 0.85em;">${pos.leverage}x杠杆</span>
                    <span style="color: #999; font-size: 0.75em; margin-left: auto;">${currentInterval}</span>
                `;
                
                const info = document.createElement('div');
                info.className = 'chart-info-grid two-rows';
                info.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">📍 入场:</span>
                        <span class="info-value" style="color: #3b82f6;">$${formatNumber(pos.entry_price, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 当前:</span>
                        <span class="info-value" style="color: #8b5cf6; font-weight: bold;">$${formatNumber(currentPrice, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🎯 止盈:</span>
                        <span class="info-value" style="color: #10b981;">$${formatNumber(pos.take_profit, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🛑 止损:</span>
                        <span class="info-value" style="color: #ef4444;">$${formatNumber(pos.stop_loss, 5)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💼 仓位:</span>
                        <span class="info-value">${formatNumber(pos.amount, 0)}U × ${pos.leverage}x</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💵 盈亏:</span>
                        <span class="info-value" style="color: ${pnl >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">${formatCurrency(pnl)}U</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📊 ROI:</span>
                        <span class="info-value" style="color: ${roi >= 0 ? '#10b981' : '#ef4444'}; font-weight: bold;">${formatCurrency(roi)}%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱ 开仓:</span>
                        <span class="info-value" style="font-size: 0.85em;">${formatTime(pos.entry_time)}</span>
                    </div>
                `;

                const canvasWrapper = document.createElement('div');
                canvasWrapper.style.flex = '1';
                canvasWrapper.style.minHeight = '0';
                canvasWrapper.style.position = 'relative';

                const canvas = document.createElement('canvas');
                canvas.id = `chart-${pos.symbol}`;

                canvasWrapper.appendChild(canvas);
                chartDiv.appendChild(title);
                chartDiv.appendChild(info);
                chartDiv.appendChild(canvasWrapper);
                container.innerHTML = '';
                container.appendChild(chartDiv);
                
                // 准备图表数据（根据时间周期调整时间格式）
                let timeFormat = {};
                if (currentInterval === '1d') {
                    timeFormat = {month: 'short', day: 'numeric'};
                } else if (currentInterval === '4h' || currentInterval === '1h') {
                    timeFormat = {month: 'numeric', day: 'numeric', hour: '2-digit'};
                } else {
                    timeFormat = {hour: '2-digit', minute: '2-digit'};
                }
                
                const labels = klineData.map(k => new Date(k.time).toLocaleString('zh-CN', timeFormat));
                const prices = klineData.map(k => k.close);
                
                // 找到开仓时间对应的索引
                const entryTime = new Date(pos.entry_time).getTime();
                let entryIndex = 0;
                for (let i = 0; i < klineData.length; i++) {
                    if (klineData[i].time >= entryTime) {
                        entryIndex = i;
                        break;
                    }
                }
                
                // 创建图表
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '价格走势',
                            data: prices,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.1,
                            pointRadius: 0,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            annotation: {
                                annotations: {
                                    // 1. 购买价格线（蓝色虚线）
                                    entryLine: {
                                        type: 'line',
                                        yMin: pos.entry_price,
                                        yMax: pos.entry_price,
                                        borderColor: '#3b82f6',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `📍 入场 $${formatNumber(pos.entry_price, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#3b82f6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 购买点标记
                                    entryPoint: {
                                        type: 'point',
                                        xValue: entryIndex,
                                        yValue: pos.entry_price,
                                        backgroundColor: '#3b82f6',
                                        borderColor: '#ffffff',
                                        borderWidth: 2,
                                        radius: 6
                                    },
                                    // 2. 当前价格线（紫色粗线）
                                    currentLine: {
                                        type: 'line',
                                        yMin: currentPrice,
                                        yMax: currentPrice,
                                        borderColor: '#8b5cf6',
                                        borderWidth: 3,
                                        label: {
                                            content: `💰 当前 $${formatNumber(currentPrice, 6)}`,
                                            enabled: true,
                                            position: 'end',
                                            backgroundColor: '#8b5cf6',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 3. 止盈线（绿色虚线）
                                    takeProfitLine: {
                                        type: 'line',
                                        yMin: pos.take_profit,
                                        yMax: pos.take_profit,
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🎯 止盈 $${formatNumber(pos.take_profit, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#10b981',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    },
                                    // 4. 止损线（红色虚线）
                                    stopLossLine: {
                                        type: 'line',
                                        yMin: pos.stop_loss,
                                        yMax: pos.stop_loss,
                                        borderColor: '#ef4444',
                                        borderWidth: 2,
                                        borderDash: [8, 4],
                                        label: {
                                            content: `🛑 止损 $${formatNumber(pos.stop_loss, 6)}`,
                                            enabled: true,
                                            position: 'start',
                                            backgroundColor: '#ef4444',
                                            color: '#ffffff',
                                            font: {
                                                size: 11,
                                                weight: 'bold'
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.05)',
                                    drawBorder: true
                                },
                                ticks: {
                                    maxTicksLimit: 12,
                                    font: {
                                        size: 11,
                                        weight: '500'
                                    },
                                    color: '#666'
                                }
                            },
                            y: {
                                display: true,
                                position: 'right',
                                grid: {
                                    display: true,
                                    color: 'rgba(0, 0, 0, 0.08)',
                                    drawBorder: true
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toFixed(6);
                                    },
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    },
                                    color: '#333'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        elements: {
                            point: {
                                radius: 0,
                                hitRadius: 10,
                                hoverRadius: 5
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error(`加载${pos.symbol}图表失败:`, error);
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #ef4444;">加载图表失败: ${error.message}</div>`;
            }
        }
        
        async function loadProgressTracking() {
            try {
                // 获取统计数据
                const statsResp = await fetch('/api/stats');
                const stats = await statsResp.json();

                // 获取每日数据
                const dailyResp = await fetch('/api/daily_stats');
                const dailyStats = await dailyResp.json();

                // 更新大进度条
                const progress = Math.min(100, Math.max(0, stats.progress || 0));
                const progressBar = document.getElementById('progress-bar-large');
                progressBar.style.width = progress + '%';
                progressBar.textContent = formatNumber(progress, 1) + '%';

                // 更新进度详情
                const earned = stats.total_pnl || 0;
                const remaining = Math.max(0, stats.target_profit - earned);
                document.getElementById('progress-earned').textContent = formatNumber(earned, 2) + 'U';
                document.getElementById('progress-remaining').textContent = formatNumber(remaining, 2) + 'U';

                // 更新统计卡片
                document.getElementById('progress-target').textContent = formatNumber(stats.target_profit, 2) + 'U';

                const currentPnl = document.getElementById('progress-current');
                currentPnl.textContent = formatCurrency(earned) + 'U';
                currentPnl.className = 'progress-stat-value ' + (earned >= 0 ? 'positive' : 'negative');

                // 计算平均日收益（基于最近7天）
                let totalDailyPnl = 0;
                let daysWithTrades = 0;
                dailyStats.forEach(day => {
                    if (day.trades > 0) {
                        totalDailyPnl += day.daily_pnl || 0;
                        daysWithTrades++;
                    }
                });

                const avgDailyPnl = daysWithTrades > 0 ? totalDailyPnl / daysWithTrades : 0;
                const avgDaily = document.getElementById('progress-daily-avg');
                avgDaily.textContent = formatCurrency(avgDailyPnl) + 'U/天';
                avgDaily.className = 'progress-stat-value ' + (avgDailyPnl >= 0 ? 'positive' : 'negative');

                // 计算预计完成天数
                const daysElement = document.getElementById('progress-days');
                if (avgDailyPnl > 0 && remaining > 0) {
                    const estimatedDays = Math.ceil(remaining / avgDailyPnl);
                    daysElement.textContent = estimatedDays + '天';
                    daysElement.className = 'progress-stat-value';
                } else if (remaining <= 0) {
                    daysElement.textContent = '已完成!';
                    daysElement.className = 'progress-stat-value positive';
                } else {
                    daysElement.textContent = '-';
                    daysElement.className = 'progress-stat-value';
                }

                // 渲染每日盈亏列表
                const dailyListContainer = document.getElementById('daily-pnl-list');
                if (dailyStats.length === 0) {
                    dailyListContainer.innerHTML = '<p style="text-align: center; color: #999; padding: 10px;">暂无数据</p>';
                } else {
                    let html = '';
                    dailyStats.forEach(day => {
                        const pnlColor = day.daily_pnl >= 0 ? 'positive' : 'negative';
                        const winRate = day.trades > 0 ? (day.wins / day.trades * 100) : 0;
                        html += `
                            <div class="daily-item">
                                <span class="daily-date">${day.date} (${day.trades}笔, 胜率${formatNumber(winRate, 0)}%)</span>
                                <span class="daily-value ${pnlColor}">${formatCurrency(day.daily_pnl || 0)}U</span>
                            </div>
                        `;
                    });
                    dailyListContainer.innerHTML = html;
                }

            } catch (error) {
                console.error('加载目标进度失败:', error);
            }
        }

        function updateAll() {
            loadStats();
            loadWatchlist();
            loadPositions();
            loadTrades();
            loadProgressTracking();
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('zh-CN');
        }

        // 初始加载
        updateAll();

        // 每60秒刷新统计、持仓、交易历史
        setInterval(() => {
            loadStats();
            loadPositions();
            loadTrades();
            loadProgressTracking();
            document.getElementById('last-update').textContent = new Date().toLocaleTimeString('zh-CN');
        }, 60000);

        // 每10分钟刷新监控列表（包含信号分析，比较耗时）
        let watchlistCountdown = 600;  // 10分钟 = 600秒

        // 更新倒计时显示
        function updateWatchlistCountdown() {
            const minutes = Math.floor(watchlistCountdown / 60);
            const seconds = watchlistCountdown % 60;
            const countdownText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            document.getElementById('watchlist-countdown').textContent = `刷新: ${countdownText}`;

            watchlistCountdown--;

            if (watchlistCountdown < 0) {
                watchlistCountdown = 600;
                console.log('🔄 刷新监控列表（10分钟定时）');
                loadWatchlist();
            }
        }

        // 每秒更新倒计时
        setInterval(updateWatchlistCountdown, 1000);
        updateWatchlistCountdown();  // 立即显示初始倒计时
    </script>
</body>
</html>
'''

# ==============================
# 回测模拟器
# ==============================

_kline_cache = {}

def fetch_historical_klines(symbol, year):
    """从Binance拉取一整年的1h K线数据"""
    cache_key = f"{symbol}_{year}"
    if cache_key in _kline_cache:
        return _kline_cache[cache_key]

    binance_symbol = SYMBOL_MAP.get(symbol, f"{symbol}USDT")
    start_dt = datetime(year, 1, 1, tzinfo=timezone.utc)
    end_dt = datetime(year, 12, 31, 23, 59, 59, tzinfo=timezone.utc)
    start_ms = int(start_dt.timestamp() * 1000)
    end_ms = int(end_dt.timestamp() * 1000)

    all_candles = []
    current_start = start_ms

    while current_start < end_ms:
        try:
            url = "https://fapi.binance.com/fapi/v1/klines"
            params = {
                'symbol': binance_symbol,
                'interval': '1h',
                'startTime': current_start,
                'endTime': end_ms,
                'limit': 1500
            }
            response = requests.get(url, params=params, timeout=30)
            klines = response.json()

            if not klines or isinstance(klines, dict):
                break

            for k in klines:
                all_candles.append({
                    'time': int(k[0]),
                    'open': float(k[1]),
                    'high': float(k[2]),
                    'low': float(k[3]),
                    'close': float(k[4]),
                    'volume': float(k[5])
                })

            current_start = int(klines[-1][0]) + 3600001
            if len(klines) < 1500:
                break

            _time.sleep(0.2)  # 避免API限流
        except Exception as e:
            print(f"拉取K线失败: {e}")
            break

    if all_candles:
        _kline_cache[cache_key] = all_candles

    return all_candles


BACKTEST_DB = '/opt/trading-bot/quant-trade-bot/data/db/backtest_history.db'

def init_backtest_db():
    """初始化回测历史数据库"""
    conn = sqlite3.connect(BACKTEST_DB)
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS backtest_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        symbol TEXT, year INTEGER, initial_capital REAL,
        final_capital REAL, total_pnl REAL, win_rate REAL,
        total_trades INTEGER, win_trades INTEGER, loss_trades INTEGER,
        max_drawdown REAL, profit_factor REAL,
        avg_win REAL, avg_loss REAL, best_trade REAL, worst_trade REAL,
        bankrupt INTEGER, strategy_version TEXT,
        run_time TEXT, note TEXT
    )''')
    c.execute('''CREATE TABLE IF NOT EXISTS backtest_trades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        run_id INTEGER, trade_id INTEGER,
        direction TEXT, entry_price REAL, exit_price REAL,
        amount REAL, leverage INTEGER,
        pnl REAL, roi REAL, fee REAL, funding_fee REAL,
        reason TEXT, entry_time TEXT, exit_time TEXT,
        score INTEGER, stop_moves INTEGER,
        FOREIGN KEY (run_id) REFERENCES backtest_runs(id)
    )''')
    conn.commit()
    conn.close()

init_backtest_db()

# 策略预设
STRATEGY_PRESETS = {
    'v1': {
        'label': 'v1 原始 (Original)',
        'description': '窄止损/高杠杆/1h冷却',
        'config': {
            'min_score': 55,
            'cooldown': 1,
            'stop_multiplier': 1.5,
            'stop_range_min': 0.015,
            'stop_range_max': 0.04,
            'take_profit_ratio': 1.5,
            'max_leverage': 10,
            'enable_trend_filter': False,
        }
    },
    'v2': {
        'label': 'v2 稳健 (Conservative)',
        'description': '宽止损/低杠杆/12h冷却/趋势过滤',
        'config': {
            'min_score': 70,
            'cooldown': 12,
            'stop_multiplier': 2.0,
            'stop_range_min': 0.03,
            'stop_range_max': 0.08,
            'take_profit_ratio': 1.5,
            'max_leverage': 5,
            'enable_trend_filter': True,
        }
    },
    'v3': {
        'label': 'v3 自定义 (Custom)',
        'description': '自由调整所有参数',
        'config': {}
    }
}


@app.route('/backtest')
def backtest_page():
    """回测模拟器页面"""
    return render_template_string(BACKTEST_TEMPLATE)


@app.route('/api/backtest/symbols')
def get_backtest_symbols():
    """获取可回测的币种列表"""
    return jsonify(WATCH_SYMBOLS)


@app.route('/api/backtest/strategies')
def get_strategies():
    """返回可用策略预设"""
    return jsonify({k: {'label': v['label'], 'description': v['description'], 'config': v['config']}
                    for k, v in STRATEGY_PRESETS.items()})


@app.route('/api/backtest/run', methods=['POST'])
def run_backtest_api():
    """执行回测并保存结果"""
    try:
        from backtest_engine import run_backtest

        params = request.get_json()
        symbol = params.get('symbol', 'BTC')
        year = int(params.get('year', 2024))
        initial_capital = float(params.get('initial_capital', 2000))
        strategy = params.get('strategy', 'v2')
        custom_params = params.get('custom_params', {})
        note = params.get('note', '')

        candles = fetch_historical_klines(symbol, year)
        if not candles:
            return jsonify({'error': f'{symbol} 在 {year} 年没有数据'}), 400

        # 构建配置：基础 + 策略预设 + 自定义覆盖
        config = {
            'initial_capital': initial_capital,
            'fee_rate': 0.0005,
            'max_positions': 3,
            'max_same_direction': 2
        }
        preset = STRATEGY_PRESETS.get(strategy, STRATEGY_PRESETS['v2'])
        config.update(preset['config'])
        if strategy == 'v3' and custom_params:
            for k, v in custom_params.items():
                config[k] = float(v) if isinstance(v, str) else v

        result = run_backtest(candles, config)

        # 保存到数据库
        s = result['summary']
        conn = sqlite3.connect(BACKTEST_DB)
        c = conn.cursor()
        c.execute('''INSERT INTO backtest_runs
            (symbol, year, initial_capital, final_capital, total_pnl,
             win_rate, total_trades, win_trades, loss_trades,
             max_drawdown, profit_factor, avg_win, avg_loss,
             best_trade, worst_trade, bankrupt, strategy_version, run_time, note)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
            (symbol, year, s['initial_capital'], s['final_capital'], s['total_pnl'],
             s['win_rate'], s['total_trades'], s['win_trades'], s['loss_trades'],
             s['max_drawdown'], s['profit_factor'], s['avg_win'], s['avg_loss'],
             s['best_trade'], s['worst_trade'], 1 if s['bankrupt'] else 0,
             strategy, datetime.now().strftime('%Y-%m-%d %H:%M:%S'), note))
        run_id = c.lastrowid

        for t in result['trades']:
            c.execute('''INSERT INTO backtest_trades
                (run_id, trade_id, direction, entry_price, exit_price,
                 amount, leverage, pnl, roi, fee, funding_fee,
                 reason, entry_time, exit_time, score, stop_moves)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)''',
                (run_id, t['trade_id'], t['direction'], t['entry_price'], t['exit_price'],
                 t['amount'], t['leverage'], t['pnl'], t['roi'], t['fee'], t['funding_fee'],
                 t['reason'], t['entry_time'], t['exit_time'], t['score'], t['stop_moves']))

        conn.commit()
        conn.close()

        result['run_id'] = run_id
        return jsonify(result)

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500


@app.route('/api/backtest/history')
def get_backtest_history():
    """获取回测历史列表"""
    conn = sqlite3.connect(BACKTEST_DB)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute('''SELECT * FROM backtest_runs ORDER BY id DESC LIMIT 50''')
    rows = [dict(r) for r in c.fetchall()]
    conn.close()
    return jsonify(rows)


@app.route('/api/backtest/trades/<int:run_id>')
def get_backtest_trades(run_id):
    """获取某次回测的交易详情"""
    conn = sqlite3.connect(BACKTEST_DB)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()
    c.execute('SELECT * FROM backtest_trades WHERE run_id=? ORDER BY trade_id', (run_id,))
    trades = [dict(r) for r in c.fetchall()]
    conn.close()
    return jsonify(trades)


@app.route('/api/backtest/scan', methods=['POST'])
def run_backtest_scan():
    """参数扫描 - 遍历不同参数组合"""
    try:
        from backtest_engine import run_backtest
        import itertools

        params = request.get_json()
        symbol = params.get('symbol', 'BTC')
        year = int(params.get('year', 2024))
        initial_capital = float(params.get('initial_capital', 2000))
        strategy = params.get('strategy', 'v2')
        scan_params = params.get('scan_params', {})

        if not scan_params:
            return jsonify({'error': '请至少选择一个扫描参数'}), 400

        candles = fetch_historical_klines(symbol, year)
        if not candles:
            return jsonify({'error': f'{symbol} 在 {year} 年没有数据'}), 400

        # 安全限制
        param_names = list(scan_params.keys())
        param_values = list(scan_params.values())
        total_combos = 1
        for v in param_values:
            total_combos *= len(v)
        if total_combos > 50:
            return jsonify({'error': f'组合数过多 ({total_combos})，最多50种'}), 400

        # 基础配置
        base_config = {
            'initial_capital': initial_capital,
            'fee_rate': 0.0005,
            'max_positions': 3,
            'max_same_direction': 2
        }
        preset = STRATEGY_PRESETS.get(strategy, STRATEGY_PRESETS['v2'])
        base_config.update(preset['config'])

        results = []
        for combo in itertools.product(*param_values):
            cfg = dict(base_config)
            label_parts = []
            for name, val in zip(param_names, combo):
                cfg[name] = val
                label_parts.append(f"{name}={val}")

            result = run_backtest(candles, cfg)
            sm = result['summary']
            results.append({
                'params': dict(zip(param_names, combo)),
                'params_label': ', '.join(label_parts),
                'final_capital': sm['final_capital'],
                'total_pnl': sm['total_pnl'],
                'win_rate': sm['win_rate'],
                'total_trades': sm['total_trades'],
                'max_drawdown': sm['max_drawdown'],
                'profit_factor': sm['profit_factor'],
                'bankrupt': sm['bankrupt']
            })

        results.sort(key=lambda r: r['total_pnl'], reverse=True)

        return jsonify({
            'symbol': symbol,
            'year': year,
            'total_combos': len(results),
            'results': results
        })

    except Exception as e:
        import traceback
        traceback.print_exc()
        return jsonify({'error': str(e)}), 500


@app.route('/api/backtest/kline/<symbol>')
def get_backtest_kline(symbol):
    """获取回测用的K线数据片段（用于交易图表）"""
    start = request.args.get('start', type=int)
    end = request.args.get('end', type=int)
    year = request.args.get('year', 2024, type=int)

    candles = fetch_historical_klines(symbol, year)
    if not candles:
        return jsonify([])

    # 按时间范围过滤，前后扩展50根K线
    filtered = []
    for c in candles:
        if start and c['time'] < start - 50 * 3600000:
            continue
        if end and c['time'] > end + 50 * 3600000:
            break
        filtered.append(c)

    return jsonify(filtered[-200:] if len(filtered) > 200 else filtered)


@app.route('/api/backtest/report')
def get_backtest_report():
    """生成策略对比报告 — 支持按年份查询，year=all 返回所有年份汇总"""
    year_param = request.args.get('year', 'all')
    conn = sqlite3.connect(BACKTEST_DB)
    conn.row_factory = sqlite3.Row
    c = conn.cursor()

    # 获取可用年份
    c.execute('SELECT DISTINCT year FROM backtest_runs WHERE strategy_version IN (\'v1\',\'v2\') ORDER BY year')
    available_years = [r['year'] for r in c.fetchall()]

    # 查询数据
    if year_param == 'all':
        c.execute('''SELECT * FROM backtest_runs
            WHERE strategy_version IN ('v1','v2') ORDER BY id DESC''')
    else:
        c.execute('''SELECT * FROM backtest_runs
            WHERE year = ? AND strategy_version IN ('v1','v2') ORDER BY id DESC''',
            (int(year_param),))
    rows = [dict(r) for r in c.fetchall()]
    conn.close()

    # 每个 symbol+strategy(+year for all) 只保留最新一条
    seen = {}
    for r in rows:
        if year_param == 'all':
            key = f"{r['symbol']}_{r['strategy_version']}_{r['year']}"
        else:
            key = f"{r['symbol']}_{r['strategy_version']}"
        if key not in seen:
            seen[key] = r

    # 按 symbol 整理对比
    symbols = sorted(set(r['symbol'] for r in seen.values()))
    comparison = []
    v1_total = {'pnl': 0, 'trades': 0, 'wins': 0, 'count': 0}
    v2_total = {'pnl': 0, 'trades': 0, 'wins': 0, 'count': 0}

    if year_param == 'all':
        # 汇总模式：每个 symbol 合并所有年份的盈亏
        for sym in symbols:
            row = {'symbol': sym}
            for v in ['v1', 'v2']:
                total_pnl = 0
                total_trades = 0
                total_wr_sum = 0
                yr_count = 0
                for yr in available_years:
                    key = f"{sym}_{v}_{yr}"
                    d = seen.get(key)
                    if d:
                        total_pnl += d['total_pnl']
                        total_trades += d['total_trades']
                        total_wr_sum += d['win_rate']
                        yr_count += 1
                if yr_count > 0:
                    row[v] = {
                        'pnl': round(total_pnl, 2),
                        'trades': total_trades,
                        'win_rate': round(total_wr_sum / yr_count, 1),
                        'years': yr_count
                    }
                    totals = v1_total if v == 'v1' else v2_total
                    totals['pnl'] += total_pnl
                    totals['trades'] += total_trades
                    totals['count'] += 1
                    if total_pnl > 0:
                        totals['wins'] += 1
                else:
                    row[v] = None
            p1 = row.get('v1') or {}
            p2 = row.get('v2') or {}
            if p1 and p2:
                row['winner'] = 'v1' if (p1.get('pnl', 0) or 0) > (p2.get('pnl', 0) or 0) else 'v2'
            comparison.append(row)
    else:
        # 单年模式
        for sym in symbols:
            row = {'symbol': sym}
            for v in ['v1', 'v2']:
                key = f"{sym}_{v}"
                d = seen.get(key)
                if d:
                    row[v] = {
                        'pnl': d['total_pnl'], 'trades': d['total_trades'],
                        'win_rate': d['win_rate'], 'best': d['best_trade'],
                        'worst': d['worst_trade'], 'final_capital': d['final_capital'],
                        'bankrupt': d['bankrupt']
                    }
                    totals = v1_total if v == 'v1' else v2_total
                    totals['pnl'] += d['total_pnl']
                    totals['trades'] += d['total_trades']
                    totals['count'] += 1
                    if d['total_pnl'] > 0:
                        totals['wins'] += 1
                else:
                    row[v] = None
            p1 = row.get('v1') or {}
            p2 = row.get('v2') or {}
            if p1 and p2:
                row['winner'] = 'v1' if (p1.get('pnl', 0) or 0) > (p2.get('pnl', 0) or 0) else 'v2'
            comparison.append(row)

    comparison.sort(key=lambda x: (x.get('v2') or {}).get('pnl', 0), reverse=True)

    return jsonify({
        'comparison': comparison,
        'v1_total': v1_total,
        'v2_total': v2_total,
        'available_years': available_years,
        'selected_year': year_param
    })


@app.route('/report')
def report_page():
    """策略对比报告页面"""
    return render_template_string(REPORT_TEMPLATE)


REPORT_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>策略对比报告 - 交易助手</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            text-align: center; padding: 25px;
            background: rgba(255,255,255,0.05);
            border-radius: 16px; margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .header h1 { font-size: 1.8em; color: #fff; }
        .header .subtitle { color: #999; margin-top: 5px; font-size: 0.9em; }
        .nav-links { margin-top: 10px; display: flex; gap: 15px; justify-content: center; }
        .nav-links a { color: #667eea; text-decoration: none; font-size: 0.9em; }
        .nav-links a:hover { text-decoration: underline; }

        /* 汇总卡片 */
        .summary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .summary-card {
            background: rgba(255,255,255,0.08); border-radius: 16px;
            padding: 25px; backdrop-filter: blur(10px);
        }
        .summary-card h3 { font-size: 1.1em; margin-bottom: 15px; }
        .summary-card.v1 { border-left: 4px solid #f0b90b; }
        .summary-card.v2 { border-left: 4px solid #2ecc71; }
        .stat-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.6em; font-weight: 700; }
        .stat-label { font-size: 0.75em; color: #999; margin-top: 2px; }
        .pnl-pos { color: #2ecc71; }
        .pnl-neg { color: #e74c3c; }

        /* 对比表 */
        .report-panel {
            background: rgba(255,255,255,0.08); border-radius: 16px;
            padding: 25px; backdrop-filter: blur(10px);
        }
        .report-panel h3 { margin-bottom: 15px; font-size: 1.1em; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        thead th {
            padding: 10px 8px; text-align: center; color: #aaa;
            font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.1);
            position: sticky; top: 0; background: rgba(30,25,60,0.95);
        }
        thead th.left { text-align: left; }
        tbody td { padding: 8px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
        tbody td.left { text-align: left; font-weight: 600; }
        tbody tr:hover { background: rgba(255,255,255,0.04); }
        .winner-v1 { background: rgba(240,185,11,0.08); }
        .winner-v2 { background: rgba(46,204,113,0.08); }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 0.75em; font-weight: 600;
        }
        .badge-v1 { background: rgba(240,185,11,0.2); color: #f0b90b; }
        .badge-v2 { background: rgba(46,204,113,0.2); color: #2ecc71; }
        .verdict {
            margin-top: 20px; padding: 20px; border-radius: 12px;
            background: rgba(46,204,113,0.1); border: 1px solid rgba(46,204,113,0.3);
            text-align: center; font-size: 1.1em;
        }
        .loading { text-align: center; padding: 60px; color: #999; font-size: 1.2em; }
        .year-tab {
            padding: 6px 18px; border-radius: 20px; cursor: pointer;
            font-size: 0.85em; border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.05); color: #aaa; transition: all 0.2s;
        }
        .year-tab:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .year-tab.active { background: #667eea; color: #fff; border-color: #667eea; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>策略对比报告</h1>
        <div class="subtitle" id="report-subtitle">全币种 v1 vs v2 回测对比 | 本金 2000 USDT | 止盈 x1.5</div>
        <div class="nav-links">
            <a href="/">← 仪表盘</a>
            <a href="/backtest">回测模拟器</a>
        </div>
        <div id="year-tabs" style="margin-top:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;"></div>
    </div>

    <div id="loading" class="loading">加载报告数据中...</div>

    <div id="content" style="display:none;">
        <!-- 汇总 -->
        <div class="summary-row">
            <div class="summary-card v1">
                <h3 style="color:#f0b90b;">v1 原始 (Original)</h3>
                <div style="color:#888;font-size:0.8em;margin-bottom:12px;">窄止损 1.5-4% | 高杠杆 10x | 冷却 1h | 无趋势过滤</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="v1-pnl">-</div>
                        <div class="stat-label">总盈亏 (U)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="v1-wincount">-</div>
                        <div class="stat-label">盈利币种</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="v1-trades">-</div>
                        <div class="stat-label">总交易笔数</div>
                    </div>
                </div>
            </div>
            <div class="summary-card v2">
                <h3 style="color:#2ecc71;">v2 稳健 (Conservative)</h3>
                <div style="color:#888;font-size:0.8em;margin-bottom:12px;">宽止损 3-8% | 低杠杆 5x | 冷却 12h | 趋势过滤</div>
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-value" id="v2-pnl">-</div>
                        <div class="stat-label">总盈亏 (U)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="v2-wincount">-</div>
                        <div class="stat-label">盈利币种</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="v2-trades">-</div>
                        <div class="stat-label">总交易笔数</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 逐币对比表 -->
        <div class="report-panel">
            <h3>逐币种对比明细</h3>
            <table>
                <thead>
                    <tr>
                        <th class="left">币种</th>
                        <th>v1 盈亏</th>
                        <th>v1 笔数</th>
                        <th>v1 胜率</th>
                        <th>v2 盈亏</th>
                        <th>v2 笔数</th>
                        <th>v2 胜率</th>
                        <th>赢家</th>
                    </tr>
                </thead>
                <tbody id="report-body"></tbody>
                <tfoot id="report-foot"></tfoot>
            </table>
        </div>

        <!-- 结论 -->
        <div class="verdict" id="verdict"></div>
    </div>
</div>
<script>
    let currentYear = 'all';

    function loadReport(year) {
        currentYear = year;
        document.getElementById('loading').style.display = 'block';
        document.getElementById('content').style.display = 'none';

        fetch('/api/backtest/report?year=' + year)
            .then(r => r.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('content').style.display = 'block';

                // 年份标签
                const tabsDiv = document.getElementById('year-tabs');
                tabsDiv.innerHTML = '';
                const allTab = document.createElement('span');
                allTab.className = 'year-tab' + (year === 'all' ? ' active' : '');
                allTab.textContent = '全部汇总';
                allTab.onclick = () => loadReport('all');
                tabsDiv.appendChild(allTab);
                data.available_years.forEach(y => {
                    const tab = document.createElement('span');
                    tab.className = 'year-tab' + (String(year) === String(y) ? ' active' : '');
                    tab.textContent = y + '年';
                    tab.onclick = () => loadReport(y);
                    tabsDiv.appendChild(tab);
                });

                // 标题
                const subtitle = year === 'all'
                    ? `${data.available_years.join('+')}年 全币种汇总 | 本金 2000 USDT | 止盈 x1.5`
                    : `${year}年全币种 v1 vs v2 | 本金 2000 USDT | 止盈 x1.5`;
                document.getElementById('report-subtitle').textContent = subtitle;

                const t1 = data.v1_total, t2 = data.v2_total;
                const el = id => document.getElementById(id);

                // 汇总卡片
                el('v1-pnl').textContent = (t1.pnl >= 0 ? '+' : '') + t1.pnl.toFixed(0);
                el('v1-pnl').className = 'stat-value ' + (t1.pnl >= 0 ? 'pnl-pos' : 'pnl-neg');
                el('v1-wincount').textContent = t1.wins + '/' + t1.count;
                el('v1-trades').textContent = t1.trades.toLocaleString();
                el('v2-pnl').textContent = (t2.pnl >= 0 ? '+' : '') + t2.pnl.toFixed(0);
                el('v2-pnl').className = 'stat-value ' + (t2.pnl >= 0 ? 'pnl-pos' : 'pnl-neg');
                el('v2-wincount').textContent = t2.wins + '/' + t2.count;
                el('v2-trades').textContent = t2.trades.toLocaleString();

                // 表格
                const tbody = document.getElementById('report-body');
                tbody.innerHTML = '';
                data.comparison.forEach(row => {
                    const v1 = row.v1 || {};
                    const v2 = row.v2 || {};
                    const w = row.winner;
                    const tr = document.createElement('tr');
                    tr.className = w === 'v1' ? 'winner-v1' : 'winner-v2';

                    const pnlHtml = (val) => {
                        if (val == null) return '<td>-</td>';
                        const cls = val >= 0 ? 'pnl-pos' : 'pnl-neg';
                        return `<td class="${cls}">${val >= 0 ? '+' : ''}${val.toFixed(1)}</td>`;
                    };

                    tr.innerHTML = `
                        <td class="left">${row.symbol}</td>
                        ${pnlHtml(v1.pnl)}
                        <td>${v1.trades || '-'}</td>
                        <td>${v1.win_rate != null ? v1.win_rate + '%' : '-'}</td>
                        ${pnlHtml(v2.pnl)}
                        <td>${v2.trades || '-'}</td>
                        <td>${v2.win_rate != null ? v2.win_rate + '%' : '-'}</td>
                        <td><span class="badge badge-${w}">${w}</span></td>
                    `;
                    tbody.appendChild(tr);
                });

                // 汇总行
                const tfoot = document.getElementById('report-foot');
                tfoot.innerHTML = '';
                const tfr = document.createElement('tr');
                tfr.style.fontWeight = '700';
                tfr.style.borderTop = '2px solid rgba(255,255,255,0.2)';
                const p1cls = t1.pnl >= 0 ? 'pnl-pos' : 'pnl-neg';
                const p2cls = t2.pnl >= 0 ? 'pnl-pos' : 'pnl-neg';
                const totalWinner = t2.pnl > t1.pnl ? 'v2' : 'v1';
                tfr.innerHTML = `
                    <td class="left">合计 (${t1.count}币)</td>
                    <td class="${p1cls}">${t1.pnl >= 0 ? '+' : ''}${t1.pnl.toFixed(0)}</td>
                    <td>${t1.trades.toLocaleString()}</td>
                    <td>${t1.count > 0 ? (t1.wins/t1.count*100).toFixed(0) : 0}% 盈利</td>
                    <td class="${p2cls}">${t2.pnl >= 0 ? '+' : ''}${t2.pnl.toFixed(0)}</td>
                    <td>${t2.trades.toLocaleString()}</td>
                    <td>${t2.count > 0 ? (t2.wins/t2.count*100).toFixed(0) : 0}% 盈利</td>
                    <td><span class="badge badge-${totalWinner}">${totalWinner}</span></td>
                `;
                tfoot.appendChild(tfr);

                // 结论
                const winner = t2.pnl > t1.pnl ? 'v2' : 'v1';
                const winnerPnl = Math.max(t1.pnl, t2.pnl);
                const loserPnl = Math.min(t1.pnl, t2.pnl);
                const wPct = winner === 'v2'
                    ? (t2.count > 0 ? (t2.wins/t2.count*100).toFixed(0) : 0)
                    : (t1.count > 0 ? (t1.wins/t1.count*100).toFixed(0) : 0);
                const lPct = winner === 'v2'
                    ? (t1.count > 0 ? (t1.wins/t1.count*100).toFixed(0) : 0)
                    : (t2.count > 0 ? (t2.wins/t2.count*100).toFixed(0) : 0);
                const loser = winner === 'v2' ? 'v1' : 'v2';
                const yearLabel = year === 'all' ? '跨年汇总' : year + '年';
                const pnlCls = winnerPnl >= 0 ? 'pnl-pos' : 'pnl-neg';
                el('verdict').innerHTML = `
                    <strong>${yearLabel}</strong>：
                    <strong>${winner} 策略</strong> 总盈亏
                    <strong class="${pnlCls}">${winnerPnl >= 0 ? '+' : ''}${winnerPnl.toFixed(0)}U</strong>，
                    ${wPct}% 币种盈利（${loser} 仅 ${lPct}%）。
                `;
                el('verdict').style.borderColor = winner === 'v2' ? 'rgba(46,204,113,0.3)' : 'rgba(240,185,11,0.3)';
                el('verdict').style.background = winner === 'v2' ? 'rgba(46,204,113,0.1)' : 'rgba(240,185,11,0.1)';
            });
    }

    // 初始加载
    loadReport('all');
</script>
</body>
</html>
'''


BACKTEST_TEMPLATE = '''
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回测模拟器 - 交易助手</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        .page-wrapper { display: flex; gap: 20px; max-width: 1520px; margin: 0 auto; }
        .sidebar { width: 280px; flex-shrink: 0; position: sticky; top: 20px; align-self: flex-start; max-height: calc(100vh - 40px); overflow-y: auto; }
        .main-content { flex: 1; min-width: 0; max-width: 1200px; }
        .header {
            text-align: center;
            padding: 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .header h1 { font-size: 1.8em; color: #fff; }
        .header .subtitle { color: #999; margin-top: 5px; font-size: 0.9em; }
        .back-link {
            display: inline-block;
            margin-top: 10px;
            color: #667eea;
            text-decoration: none;
            font-size: 0.9em;
        }
        .back-link:hover { text-decoration: underline; }

        /* 配置面板 */
        .config-panel {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        .config-title { font-size: 1.1em; font-weight: 600; margin-bottom: 20px; color: #fff; }
        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        .config-field label {
            display: block;
            font-size: 0.85em;
            color: #aaa;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .config-field select, .config-field input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: 8px;
            font-size: 1em;
            background: rgba(255,255,255,0.08);
            color: #fff;
            transition: border-color 0.3s;
        }
        .config-field select:focus, .config-field input:focus {
            border-color: #667eea;
            outline: none;
        }
        .config-field select option { background: #302b63; color: #fff; }

        /* 币种选择 */

        .run-btn {
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.05em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .run-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
        .run-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* 加载中 */
        .spinner {
            display: none;
            text-align: center;
            padding: 40px;
            color: #667eea;
            font-size: 1.1em;
        }
        .spinner::before {
            content: '';
            display: inline-block;
            width: 30px; height: 30px;
            border: 3px solid rgba(102,126,234,0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* 结果区域 */
        #results-section { display: none; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        .stat-card .label { font-size: 0.85em; color: #999; margin-bottom: 6px; }
        .stat-card .value { font-size: 1.6em; font-weight: 700; }
        .stat-card .sub { font-size: 0.8em; color: #888; margin-top: 4px; }
        .positive { color: #10b981; }
        .negative { color: #ef4444; }

        .bankrupt-warning {
            display: none;
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid #ef4444;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #ef4444;
            font-weight: 600;
        }

        /* 资金曲线 */
        .chart-panel {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        .chart-panel h3 { margin-bottom: 15px; color: #fff; }
        .chart-container { height: 350px; position: relative; }

        /* 交易历史 */
        .trade-panel {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        .trade-panel h3 { margin-bottom: 15px; color: #fff; }
        .trade-scroll { max-height: 500px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th { background: rgba(255,255,255,0.08); padding: 10px 8px; text-align: left; color: #aaa; font-weight: 500; position: sticky; top: 0; }
        td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        tr:hover { background: rgba(255,255,255,0.03); }
        .badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge.long { background: rgba(16,185,129,0.2); color: #10b981; }
        .badge.short { background: rgba(239,68,68,0.2); color: #ef4444; }

        /* 左侧历史面板 */
        .history-panel {
            background: rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 15px;
            backdrop-filter: blur(10px);
        }
        .history-panel h3 { margin-bottom: 12px; color: #fff; font-size: 1em; }
        .history-scroll { max-height: calc(100vh - 120px); overflow-y: auto; }
        .history-item {
            display: flex; flex-direction: column; gap: 3px;
            padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.82em; cursor: pointer; border-radius: 8px;
            transition: background 0.2s; border-left: 3px solid transparent;
        }
        .history-item:hover { background: rgba(255,255,255,0.08); }
        .history-item.active { border-left-color: #667eea; background: rgba(102,126,234,0.1); }
        .history-row-top { display: flex; justify-content: space-between; align-items: center; }
        .history-row-bottom { display: flex; gap: 8px; color: #888; font-size: 0.9em; }

        /* 图表弹窗 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: #1a1a2e;
            border-radius: 16px;
            padding: 25px;
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 12px; right: 16px;
            font-size: 1.5em;
            cursor: pointer;
            color: #888;
            background: none;
            border: none;
        }
        .modal-close:hover { color: #fff; }
        .trade-chart-container { height: 300px; position: relative; margin: 15px 0; }
        .trade-info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .trade-info-item {
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        .trade-info-item .label { font-size: 0.75em; color: #888; }
        .trade-info-item .val { font-size: 1.1em; font-weight: 600; margin-top: 3px; }

        @media (max-width: 900px) {
            .page-wrapper { flex-direction: column; }
            .sidebar { width: 100%; position: static; max-height: 300px; order: 2; }
            .main-content { order: 1; }
            .history-scroll { max-height: 250px; }
        }
        @media (max-width: 768px) {
            .config-grid { grid-template-columns: 1fr 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .trade-info-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- 左侧：历史记录 -->
        <div class="sidebar">
            <div class="history-panel">
                <h3>历史记录</h3>
                <div class="history-scroll" id="history-list"></div>
            </div>
        </div>

        <!-- 右侧：主内容 -->
        <div class="main-content">
        <div class="header">
            <h1>回测模拟器</h1>
            <div class="subtitle">用历史数据验证交易策略</div>
            <div style="margin-top:10px;display:flex;gap:15px;justify-content:center;">
                <a href="/" class="back-link">← 返回仪表盘</a>
                <a href="/report" class="back-link">📋 策略报告</a>
            </div>
        </div>

        <div class="config-panel">
            <div class="config-title">回测配置</div>
            <div class="config-grid" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
                <div class="config-field">
                    <label>选择币种</label>
                    <select id="symbol-select">
                        <option value="" disabled selected>-- 选择 --</option>
                    </select>
                </div>
                <div class="config-field">
                    <label>年份</label>
                    <select id="year-select">
                        <option value="2024" selected>2024</option>
                        <option value="2023">2023</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
                <div class="config-field">
                    <label>金额 (USDT)</label>
                    <input type="number" id="capital-input" value="2000" min="100" step="100">
                </div>
                <div class="config-field">
                    <label>策略</label>
                    <select id="strategy-select" onchange="onStrategyChange()">
                        <option value="v1">v1 原始</option>
                        <option value="v2" selected>v2 稳健</option>
                        <option value="v3">v3 自定义</option>
                    </select>
                </div>
                <div class="config-field">
                    <label>&nbsp;</label>
                    <button class="run-btn" id="run-btn" onclick="runBacktest()">开始回测</button>
                </div>
            </div>
            <!-- 策略参数对比 -->
            <div id="strategy-desc" style="margin-top:15px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
                <table style="width:100%;border-collapse:collapse;font-size:0.82em;">
                    <thead>
                        <tr style="color:#aaa;text-align:left;">
                            <th style="padding:4px 8px;font-weight:500;"></th>
                            <th style="padding:4px 8px;font-weight:500;">最低评分</th>
                            <th style="padding:4px 8px;font-weight:500;">冷却时间</th>
                            <th style="padding:4px 8px;font-weight:500;">止损范围</th>
                            <th style="padding:4px 8px;font-weight:500;">止盈倍数</th>
                            <th style="padding:4px 8px;font-weight:500;">最大杠杆</th>
                            <th style="padding:4px 8px;font-weight:500;">趋势过滤</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="desc-v1" style="color:#e0e0e0;">
                            <td style="padding:4px 8px;font-weight:600;color:#f0b90b;">v1 原始</td>
                            <td style="padding:4px 8px;">≥55</td>
                            <td style="padding:4px 8px;">1h</td>
                            <td style="padding:4px 8px;">1.5-4%</td>
                            <td style="padding:4px 8px;">×1.5</td>
                            <td style="padding:4px 8px;">10x</td>
                            <td style="padding:4px 8px;color:#e74c3c;">关</td>
                        </tr>
                        <tr id="desc-v2" style="color:#e0e0e0;">
                            <td style="padding:4px 8px;font-weight:600;color:#2ecc71;">v2 稳健</td>
                            <td style="padding:4px 8px;">≥70</td>
                            <td style="padding:4px 8px;">12h</td>
                            <td style="padding:4px 8px;">3-8%</td>
                            <td style="padding:4px 8px;">×1.5</td>
                            <td style="padding:4px 8px;">5x</td>
                            <td style="padding:4px 8px;color:#2ecc71;">开</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- v3 自定义参数 -->
            <div id="custom-params" style="display:none;margin-top:15px;border-top:1px solid rgba(255,255,255,0.1);padding-top:15px;">
                <div style="font-size:0.85em;color:#aaa;margin-bottom:10px;">v3 自定义参数:</div>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;">
                    <div class="config-field"><label>最低评分</label><input type="number" id="p-min-score" value="70" min="30" max="95" step="5"></div>
                    <div class="config-field"><label>冷却(bars)</label><input type="number" id="p-cooldown" value="12" min="1" max="48"></div>
                    <div class="config-field"><label>止盈倍数</label><input type="number" id="p-tp-ratio" value="1.5" min="1" max="10" step="0.5"></div>
                    <div class="config-field"><label>最大杠杆</label><input type="number" id="p-max-leverage" value="5" min="1" max="20"></div>
                    <div class="config-field"><label>止损ATR倍数</label><input type="number" id="p-stop-mult" value="2.0" min="0.5" max="5" step="0.5"></div>
                </div>
            </div>
        </div>

        <!-- 参数扫描 -->
        <div class="config-panel" id="scan-panel">
            <div class="config-title">参数扫描 <span style="font-size:0.7em;color:#888;">自动对比不同参数组合</span></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:12px;">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85em;">
                    <input type="checkbox" class="scan-cb" data-key="min_score" data-vals="[60,65,70,75,80]" data-n="5" checked> 最低评分
                    <span style="color:#666;font-size:0.8em;">5种</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85em;">
                    <input type="checkbox" class="scan-cb" data-key="cooldown" data-vals="[4,8,12,24]" data-n="4"> 冷却时间
                    <span style="color:#666;font-size:0.8em;">4种</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85em;">
                    <input type="checkbox" class="scan-cb" data-key="take_profit_ratio" data-vals="[1,1.5,2,3]" data-n="4"> 止盈倍数
                    <span style="color:#666;font-size:0.8em;">4种</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85em;">
                    <input type="checkbox" class="scan-cb" data-key="max_leverage" data-vals="[3,5,10]" data-n="3"> 最大杠杆
                    <span style="color:#666;font-size:0.8em;">3种</span>
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.85em;">
                    <input type="checkbox" class="scan-cb" data-key="stop_multiplier" data-vals="[1.5,2.0,2.5,3.0]" data-n="4"> 止损ATR倍
                    <span style="color:#666;font-size:0.8em;">4种</span>
                </label>
            </div>
            <div style="margin-top:12px;display:flex;gap:15px;align-items:center;">
                <button class="run-btn" style="width:auto;padding:8px 20px;" id="scan-btn" onclick="runScan()">开始扫描</button>
                <span id="scan-combo-count" style="color:#888;font-size:0.85em;">5 种组合</span>
            </div>
        </div>

        <div class="spinner" id="spinner">正在回测中，请稍候...</div>

        <div id="results-section">
            <div class="bankrupt-warning" id="bankrupt-warning">
                ⚠️ 资金归零！策略在该时段内破产
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">最终资金</div>
                    <div class="value" id="bt-final-capital">-</div>
                    <div class="sub" id="bt-pnl-sub">-</div>
                </div>
                <div class="stat-card">
                    <div class="label">总盈亏</div>
                    <div class="value" id="bt-total-pnl">-</div>
                    <div class="sub" id="bt-pnl-pct">-</div>
                </div>
                <div class="stat-card">
                    <div class="label">胜率</div>
                    <div class="value" id="bt-win-rate">-</div>
                    <div class="sub" id="bt-win-count">-</div>
                </div>
                <div class="stat-card">
                    <div class="label">最大回撤</div>
                    <div class="value negative" id="bt-max-drawdown">-</div>
                    <div class="sub">peak-to-trough</div>
                </div>
                <div class="stat-card">
                    <div class="label">盈亏比</div>
                    <div class="value" id="bt-profit-factor">-</div>
                    <div class="sub" id="bt-avg-detail">-</div>
                </div>
                <div class="stat-card">
                    <div class="label">总交易次数</div>
                    <div class="value" id="bt-total-trades">-</div>
                    <div class="sub" id="bt-best-worst">-</div>
                </div>
            </div>

            <div class="chart-panel">
                <h3>资金曲线</h3>
                <div class="chart-container">
                    <canvas id="equity-chart"></canvas>
                </div>
            </div>

            <div class="trade-panel">
                <h3>交易历史 <span style="font-size:0.7em;color:#888;">(点击查看图表)</span></h3>
                <div class="trade-scroll" id="trade-list"></div>
            </div>
        </div>

        <!-- 扫描结果 -->
        <div id="scan-results" style="display:none;">
            <div class="trade-panel">
                <h3>扫描结果 <span style="font-size:0.7em;color:#888;" id="scan-summary"></span></h3>
                <div class="trade-scroll" id="scan-table"></div>
            </div>
        </div>

        </div><!-- /main-content -->
    </div><!-- /page-wrapper -->

    <!-- 交易图表弹窗 -->
    <div class="modal-overlay" id="trade-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h3 id="modal-title">交易详情</h3>
            <div class="trade-info-grid" id="modal-info"></div>
            <div class="trade-chart-container">
                <canvas id="trade-detail-chart"></canvas>
            </div>
        </div>
    </div>

    <script>
        let selectedSymbol = '';
        let equityChart = null;

        // 初始化币种列表
        fetch('/api/backtest/symbols')
            .then(r => r.json())
            .then(symbols => {
                const sel = document.getElementById('symbol-select');
                symbols.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s + '/USDT';
                    sel.appendChild(opt);
                });
            });

        document.getElementById('symbol-select').addEventListener('change', function() {
            selectedSymbol = this.value;
        });

        function onStrategyChange() {
            const v = document.getElementById('strategy-select').value;
            document.getElementById('custom-params').style.display = v === 'v3' ? 'block' : 'none';
            // 高亮当前策略行
            const v1row = document.getElementById('desc-v1');
            const v2row = document.getElementById('desc-v2');
            const descBox = document.getElementById('strategy-desc');
            v1row.style.background = v === 'v1' ? 'rgba(240,185,11,0.12)' : 'none';
            v2row.style.background = v === 'v2' ? 'rgba(46,204,113,0.12)' : 'none';
            // v3 时对比表仍显示（方便对比参照）
        }
        onStrategyChange(); // 初始化高亮

        async function runBacktest() {
            if (!selectedSymbol) {
                alert('请选择一个币种');
                return;
            }

            const btn = document.getElementById('run-btn');
            const spinner = document.getElementById('spinner');
            const results = document.getElementById('results-section');

            btn.disabled = true;
            btn.textContent = '回测中...';
            spinner.style.display = 'block';
            results.style.display = 'none';

            const strategy = document.getElementById('strategy-select').value;
            let customParams = {};
            if (strategy === 'v3') {
                customParams = {
                    min_score: parseInt(document.getElementById('p-min-score').value),
                    cooldown: parseInt(document.getElementById('p-cooldown').value),
                    take_profit_ratio: parseFloat(document.getElementById('p-tp-ratio').value),
                    max_leverage: parseInt(document.getElementById('p-max-leverage').value),
                    stop_multiplier: parseFloat(document.getElementById('p-stop-mult').value)
                };
            }

            try {
                const response = await fetch('/api/backtest/run', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        symbol: selectedSymbol,
                        year: document.getElementById('year-select').value,
                        initial_capital: parseFloat(document.getElementById('capital-input').value) || 2000,
                        strategy: strategy,
                        custom_params: customParams
                    })
                });

                const result = await response.json();
                if (result.error) {
                    alert('回测失败: ' + result.error);
                    return;
                }

                currentSymbol = selectedSymbol;
                currentYear = parseInt(document.getElementById('year-select').value);
                renderResults(result);
                results.style.display = 'block';
                results.scrollIntoView({behavior: 'smooth'});
                loadHistory();  // 刷新历史列表

            } catch (e) {
                alert('回测请求失败: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '开始回测';
                spinner.style.display = 'none';
            }
        }

        function renderResults(result) {
            const s = result.summary;

            // 确保资金曲线可见（历史记录会隐藏它）
            document.getElementById('equity-chart').parentElement.style.display = '';

            // 统计卡片
            const fcEl = document.getElementById('bt-final-capital');
            fcEl.textContent = fmtN(s.final_capital, 2) + 'U';
            fcEl.className = 'value ' + (s.final_capital >= s.initial_capital ? 'positive' : 'negative');
            document.getElementById('bt-pnl-sub').textContent = '初始: ' + fmtN(s.initial_capital, 0) + 'U';

            const pnlEl = document.getElementById('bt-total-pnl');
            pnlEl.textContent = fmtC(s.total_pnl) + 'U';
            pnlEl.className = 'value ' + (s.total_pnl >= 0 ? 'positive' : 'negative');
            const pnlPct = (s.total_pnl / s.initial_capital * 100).toFixed(1);
            document.getElementById('bt-pnl-pct').textContent = '收益率: ' + fmtC(parseFloat(pnlPct)) + '%';

            const wrEl = document.getElementById('bt-win-rate');
            wrEl.textContent = fmtN(s.win_rate, 1) + '%';
            wrEl.className = 'value ' + (s.win_rate >= 50 ? 'positive' : 'negative');
            document.getElementById('bt-win-count').textContent = s.win_trades + '胜 / ' + s.loss_trades + '负';

            document.getElementById('bt-max-drawdown').textContent = fmtN(s.max_drawdown, 2) + '%';

            const pfEl = document.getElementById('bt-profit-factor');
            pfEl.textContent = s.profit_factor >= 999 ? '∞' : fmtN(s.profit_factor, 2);
            pfEl.className = 'value ' + (s.profit_factor >= 1 ? 'positive' : 'negative');
            document.getElementById('bt-avg-detail').textContent =
                '均盈: ' + fmtN(s.avg_win, 1) + 'U / 均亏: ' + fmtN(s.avg_loss, 1) + 'U';

            document.getElementById('bt-total-trades').textContent = s.total_trades;
            document.getElementById('bt-best-worst').textContent =
                '最佳: ' + fmtC(s.best_trade) + 'U / 最差: ' + fmtC(s.worst_trade) + 'U';

            // 破产警告
            document.getElementById('bankrupt-warning').style.display = s.bankrupt ? 'block' : 'none';

            // 资金曲线
            renderEquityCurve(result.equity_curve, s.initial_capital);

            // 交易历史
            renderTradeHistory(result.trades);
        }

        function renderEquityCurve(curve, initialCapital) {
            const ctx = document.getElementById('equity-chart').getContext('2d');
            if (equityChart) equityChart.destroy();

            const labels = curve.map(p => {
                const d = new Date(p.time);
                return d.getMonth()+1 + '/' + d.getDate();
            });

            equityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '资金 (U)',
                        data: curve.map(p => p.capital),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        tension: 0.1,
                        pointRadius: 0,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#ccc' } },
                        annotation: {
                            annotations: {
                                initLine: {
                                    type: 'line',
                                    yMin: initialCapital,
                                    yMax: initialCapital,
                                    borderColor: '#f59e0b',
                                    borderWidth: 2,
                                    borderDash: [8, 4],
                                    label: {
                                        content: '初始资金',
                                        display: true,
                                        position: 'start',
                                        backgroundColor: '#f59e0b',
                                        color: '#000',
                                        font: { size: 11 }
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: { ticks: { color: '#888', maxTicksLimit: 15 }, grid: { color: 'rgba(255,255,255,0.05)' } },
                        y: {
                            ticks: { color: '#888', callback: v => v.toFixed(0) + 'U' },
                            grid: { color: 'rgba(255,255,255,0.05)' }
                        }
                    }
                }
            });
        }

        function renderTradeHistory(trades) {
            const container = document.getElementById('trade-list');
            if (!trades.length) {
                container.innerHTML = '<p style="text-align:center;color:#888;padding:20px;">没有执行任何交易</p>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>#</th><th>方向</th><th>入场</th><th>出场</th>';
            html += '<th>金额</th><th>杠杆</th><th>盈亏</th><th>ROI</th><th>原因</th><th>图表</th>';
            html += '</tr></thead><tbody>';

            trades.forEach((t, i) => {
                const cls = t.pnl >= 0 ? 'positive' : 'negative';
                const badge = t.direction === 'LONG' ? 'long' : 'short';
                html += '<tr>' +
                    '<td>' + (i+1) + '</td>' +
                    '<td><span class="badge ' + badge + '">' + t.direction + '</span></td>' +
                    '<td>$' + fmtPrice(t.entry_price) + '</td>' +
                    '<td>$' + fmtPrice(t.exit_price) + '</td>' +
                    '<td>' + fmtN(t.amount, 0) + 'U</td>' +
                    '<td>' + t.leverage + 'x</td>' +
                    '<td class="' + cls + '">' + fmtC(t.pnl) + 'U</td>' +
                    '<td class="' + cls + '">' + fmtC(t.roi) + '%</td>' +
                    '<td>' + t.reason + '</td>' +
                    '<td><button class="header-btn" style="padding:3px 8px;font-size:0.8em;" ' +
                        'onclick="viewTradeDetail(' + JSON.stringify(t).replace(/"/g, '&quot;') + ')">查看</button></td>' +
                '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        function fmtN(num, d) { return (num || 0).toFixed(d); }
        function fmtC(num) { return (num >= 0 ? '+' : '') + (num || 0).toFixed(2); }
        function fmtPrice(p) {
            if (p >= 1000) return p.toFixed(2);
            if (p >= 1) return p.toFixed(4);
            return p.toFixed(6);
        }

        // === 历史记录 ===
        let currentRunId = null;
        let currentSymbol = '';
        let currentYear = 2024;
        let tradeDetailChart = null;

        function loadHistory() {
            fetch('/api/backtest/history')
                .then(r => r.json())
                .then(rows => {
                    const el = document.getElementById('history-list');
                    if (!rows.length) {
                        el.innerHTML = '<p style="text-align:center;color:#888;padding:15px;">暂无记录</p>';
                        return;
                    }
                    window._historyRows = {};
                    rows.forEach(r => { window._historyRows[r.id] = r; });

                    el.innerHTML = rows.map(r => {
                        const cls = r.total_pnl >= 0 ? 'positive' : 'negative';
                        return '<div class="history-item" data-id="' + r.id + '" onclick="loadHistoryRun(' + r.id + ')">' +
                            '<div class="history-row-top">' +
                                '<strong>' + r.symbol + '</strong>' +
                                '<span class="' + cls + '" style="font-weight:600;">' + fmtC(r.total_pnl) + 'U</span>' +
                            '</div>' +
                            '<div class="history-row-bottom">' +
                                '<span>' + r.year + '</span>' +
                                '<span style="color:#667eea;">' + (r.strategy_version || '-') + '</span>' +
                                '<span>' + fmtN(r.win_rate,0) + '%</span>' +
                                '<span>' + r.total_trades + '笔</span>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                });
        }

        function loadHistoryRun(runId) {
            const run = window._historyRows[runId];
            if (!run) return;

            currentRunId = runId;
            currentSymbol = run.symbol;
            currentYear = run.year;

            // 更新统计卡片
            const fcEl = document.getElementById('bt-final-capital');
            fcEl.textContent = fmtN(run.final_capital, 2) + 'U';
            fcEl.className = 'value ' + (run.final_capital >= run.initial_capital ? 'positive' : 'negative');
            document.getElementById('bt-pnl-sub').textContent = '初始: ' + fmtN(run.initial_capital, 0) + 'U';

            const pnlEl = document.getElementById('bt-total-pnl');
            pnlEl.textContent = fmtC(run.total_pnl) + 'U';
            pnlEl.className = 'value ' + (run.total_pnl >= 0 ? 'positive' : 'negative');
            const pnlPct = (run.total_pnl / run.initial_capital * 100).toFixed(1);
            document.getElementById('bt-pnl-pct').textContent = '收益率: ' + fmtC(parseFloat(pnlPct)) + '%';

            const wrEl = document.getElementById('bt-win-rate');
            wrEl.textContent = fmtN(run.win_rate, 1) + '%';
            wrEl.className = 'value ' + (run.win_rate >= 50 ? 'positive' : 'negative');
            document.getElementById('bt-win-count').textContent = run.win_trades + '胜 / ' + run.loss_trades + '负';

            document.getElementById('bt-max-drawdown').textContent = fmtN(run.max_drawdown, 2) + '%';

            const pfEl = document.getElementById('bt-profit-factor');
            pfEl.textContent = run.profit_factor >= 999 ? '∞' : fmtN(run.profit_factor, 2);
            pfEl.className = 'value ' + (run.profit_factor >= 1 ? 'positive' : 'negative');
            document.getElementById('bt-avg-detail').textContent =
                '均盈: ' + fmtN(run.avg_win, 1) + 'U / 均亏: ' + fmtN(run.avg_loss, 1) + 'U';

            document.getElementById('bt-total-trades').textContent = run.total_trades;
            document.getElementById('bt-best-worst').textContent =
                '最佳: ' + fmtC(run.best_trade) + 'U / 最差: ' + fmtC(run.worst_trade) + 'U';

            // 标注当前选中
            document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
            document.querySelector('.history-item[data-id="' + runId + '"]').classList.add('active');

            // 隐藏资金曲线（历史记录不含曲线数据）
            const chartContainer = document.getElementById('equity-chart').parentElement;
            chartContainer.style.display = 'none';

            // 加载交易列表
            fetch('/api/backtest/trades/' + runId)
                .then(r => r.json())
                .then(trades => {
                    const container = document.getElementById('trade-list');
                    if (!trades.length) {
                        container.innerHTML = '<p style="text-align:center;color:#888;">该回测无交易</p>';
                        return;
                    }
                    let html = '<table><thead><tr>';
                    html += '<th>#</th><th>方向</th><th>入场</th><th>出场</th>';
                    html += '<th>金额</th><th>杠杆</th><th>盈亏</th><th>ROI</th><th>原因</th><th>图表</th>';
                    html += '</tr></thead><tbody>';
                    trades.forEach((t, i) => {
                        const cls = t.pnl >= 0 ? 'positive' : 'negative';
                        const badge = t.direction === 'LONG' ? 'long' : 'short';
                        html += '<tr>' +
                            '<td>' + (i+1) + '</td>' +
                            '<td><span class="badge ' + badge + '">' + t.direction + '</span></td>' +
                            '<td>$' + fmtPrice(t.entry_price) + '</td>' +
                            '<td>$' + fmtPrice(t.exit_price) + '</td>' +
                            '<td>' + fmtN(t.amount,0) + 'U</td>' +
                            '<td>' + t.leverage + 'x</td>' +
                            '<td class="' + cls + '">' + fmtC(t.pnl) + 'U</td>' +
                            '<td class="' + cls + '">' + fmtC(t.roi) + '%</td>' +
                            '<td>' + t.reason + '</td>' +
                            '<td><button class="header-btn" style="padding:3px 8px;font-size:0.8em;" ' +
                                'onclick="viewTradeDetail(' + JSON.stringify(t).replace(/"/g, '&quot;') + ')">查看</button></td>' +
                        '</tr>';
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;

                    document.getElementById('results-section').style.display = 'block';
                    container.scrollIntoView({behavior: 'smooth'});
                });
        }

        // === 交易详情图表 ===
        function viewTradeDetail(trade) {
            const modal = document.getElementById('trade-modal');
            modal.classList.add('active');

            document.getElementById('modal-title').textContent =
                trade.direction + ' | ' + (currentSymbol || selectedSymbol) + ' | 评分' + trade.score;

            const cls = trade.pnl >= 0 ? 'positive' : 'negative';
            document.getElementById('modal-info').innerHTML =
                '<div class="trade-info-item"><div class="label">入场价</div><div class="val">$' + fmtPrice(trade.entry_price) + '</div></div>' +
                '<div class="trade-info-item"><div class="label">出场价</div><div class="val">$' + fmtPrice(trade.exit_price) + '</div></div>' +
                '<div class="trade-info-item"><div class="label">盈亏</div><div class="val ' + cls + '">' + fmtC(trade.pnl) + 'U</div></div>' +
                '<div class="trade-info-item"><div class="label">ROI</div><div class="val ' + cls + '">' + fmtC(trade.roi) + '%</div></div>' +
                '<div class="trade-info-item"><div class="label">金额/杠杆</div><div class="val">' + fmtN(trade.amount,0) + 'U / ' + trade.leverage + 'x</div></div>' +
                '<div class="trade-info-item"><div class="label">手续费</div><div class="val">' + fmtN(trade.fee,2) + 'U</div></div>' +
                '<div class="trade-info-item"><div class="label">止损移动</div><div class="val">' + trade.stop_moves + '次</div></div>' +
                '<div class="trade-info-item"><div class="label">原因</div><div class="val" style="font-size:0.85em;">' + trade.reason + '</div></div>';

            // 拉取K线画图
            const sym = currentSymbol || selectedSymbol;
            const yr = currentYear || document.getElementById('year-select').value;
            const entryTs = new Date(trade.entry_time + 'Z').getTime();
            const exitTs = new Date(trade.exit_time + 'Z').getTime();

            fetch('/api/backtest/kline/' + sym + '?start=' + entryTs + '&end=' + exitTs + '&year=' + yr)
                .then(r => r.json())
                .then(candles => {
                    renderTradeChart(candles, trade);
                });
        }

        function renderTradeChart(candles, trade) {
            const ctx = document.getElementById('trade-detail-chart').getContext('2d');
            if (tradeDetailChart) tradeDetailChart.destroy();

            const labels = candles.map(c => {
                const d = new Date(c.time);
                return (d.getMonth()+1) + '/' + d.getDate() + ' ' + d.getHours() + ':00';
            });
            const prices = candles.map(c => c.close);

            // 止损止盈线
            const entryPrice = trade.entry_price;
            const exitPrice = trade.exit_price;

            const annotations = {
                entryLine: {
                    type: 'line', yMin: entryPrice, yMax: entryPrice,
                    borderColor: '#667eea', borderWidth: 2, borderDash: [6,3],
                    label: { content: '入场 $' + fmtPrice(entryPrice), display: true, position: 'start', backgroundColor: '#667eea', font: {size: 10} }
                },
                exitLine: {
                    type: 'line', yMin: exitPrice, yMax: exitPrice,
                    borderColor: trade.pnl >= 0 ? '#10b981' : '#ef4444', borderWidth: 2, borderDash: [6,3],
                    label: { content: '出场 $' + fmtPrice(exitPrice), display: true, position: 'end',
                             backgroundColor: trade.pnl >= 0 ? '#10b981' : '#ef4444', font: {size: 10} }
                }
            };

            tradeDetailChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '价格',
                        data: prices,
                        borderColor: '#8b9cf7',
                        borderWidth: 1.5,
                        tension: 0.1,
                        pointRadius: 0,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        annotation: { annotations: annotations }
                    },
                    scales: {
                        x: { ticks: { color: '#888', maxTicksLimit: 10 }, grid: { color: 'rgba(255,255,255,0.05)' } },
                        y: { ticks: { color: '#888', callback: v => '$' + v.toFixed(2) }, grid: { color: 'rgba(255,255,255,0.05)' } }
                    }
                }
            });
        }

        function closeModal() {
            document.getElementById('trade-modal').classList.remove('active');
        }

        // ESC关闭弹窗
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        // === 参数扫描 ===
        function updateComboCount() {
            let total = 1;
            document.querySelectorAll('.scan-cb:checked').forEach(cb => {
                total *= parseInt(cb.dataset.n);
            });
            const el = document.getElementById('scan-combo-count');
            if (total > 50) {
                el.textContent = total + ' 种组合（超过50上限）';
                el.style.color = '#ef4444';
            } else {
                el.textContent = total + ' 种组合';
                el.style.color = '#888';
            }
        }
        document.querySelectorAll('.scan-cb').forEach(cb => cb.addEventListener('change', updateComboCount));

        async function runScan() {
            if (!selectedSymbol) { alert('请先选择币种'); return; }

            const scanParams = {};
            document.querySelectorAll('.scan-cb:checked').forEach(cb => {
                scanParams[cb.dataset.key] = JSON.parse(cb.dataset.vals);
            });
            if (Object.keys(scanParams).length === 0) { alert('请至少勾选一个参数'); return; }

            let total = 1;
            Object.values(scanParams).forEach(v => total *= v.length);
            if (total > 50) { alert('组合数过多 (' + total + ')，请减少勾选参数'); return; }

            const btn = document.getElementById('scan-btn');
            btn.disabled = true;
            btn.textContent = '扫描中... (' + total + '组合)';

            try {
                const response = await fetch('/api/backtest/scan', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        symbol: selectedSymbol,
                        year: document.getElementById('year-select').value,
                        initial_capital: parseFloat(document.getElementById('capital-input').value) || 2000,
                        strategy: document.getElementById('strategy-select').value,
                        scan_params: scanParams
                    })
                });
                const data = await response.json();
                if (data.error) { alert('扫描失败: ' + data.error); return; }
                renderScanResults(data);
            } catch(e) {
                alert('扫描请求失败: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '开始扫描';
            }
        }

        function renderScanResults(data) {
            document.getElementById('scan-results').style.display = 'block';
            document.getElementById('scan-summary').textContent =
                data.total_combos + '种组合 | ' + data.symbol + ' ' + data.year;

            let html = '<table><thead><tr>';
            html += '<th>#</th><th>参数</th><th>最终资金</th><th>盈亏</th>';
            html += '<th>胜率</th><th>回撤</th><th>盈亏比</th><th>交易数</th>';
            html += '</tr></thead><tbody>';

            data.results.forEach((r, i) => {
                const cls = r.total_pnl >= 0 ? 'positive' : 'negative';
                const best = i === 0 ? ' style="background:rgba(16,185,129,0.1);"' : '';
                html += '<tr' + best + '>' +
                    '<td>' + (i+1) + '</td>' +
                    '<td style="font-size:0.8em;">' + r.params_label + '</td>' +
                    '<td>' + fmtN(r.final_capital, 0) + 'U</td>' +
                    '<td class="' + cls + '">' + fmtC(r.total_pnl) + 'U</td>' +
                    '<td>' + fmtN(r.win_rate, 1) + '%</td>' +
                    '<td class="negative">' + fmtN(r.max_drawdown, 1) + '%</td>' +
                    '<td>' + fmtN(r.profit_factor, 2) + '</td>' +
                    '<td>' + r.total_trades + '</td>' +
                '</tr>';
            });

            html += '</tbody></table>';
            document.getElementById('scan-table').innerHTML = html;
            document.getElementById('scan-results').scrollIntoView({behavior:'smooth'});
        }

        // 页面加载时读取历史
        loadHistory();
    </script>
</body>
</html>
'''

if __name__ == '__main__':
    print("=" * 60)
    print("🧪 交易助手仪表盘 v1.3 启动")
    print("=" * 60)
    print(f"📊 端口: 5111")
    print(f"💾 数据库: {DB_PATH}")
    print(f"🌐 访问地址: http://localhost:5111")
    print("=" * 60)
    print("✨ v1.2 新功能:")
    print("  - 按需加载图表（点击后才加载）")
    print("  - 一次只显示一个持仓图表")
    print("  - 持仓选择下拉框")
    print("  - 6种时间周期筛选")
    print("=" * 60)
    print()
    
    app.run(host='0.0.0.0', port=5111, debug=False)
