#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Paper Trading Assistant - 交易助手模拟系统
目标：1周内模拟交易赚3400U（本金2000U）
独立数据库：trading_assistant.db
"""

import requests
import json
import time
import os
import sqlite3
from datetime import datetime

class PaperTradingAssistant:
    def __init__(self):
        self.config = self.load_config()
        self.telegram_token = self.config.get('telegram_bot_token')
        self.chat_id = self.config.get('telegram_chat_id')
        
        # Paper Trading 配置
        self.initial_capital = 2000  # 初始本金2000U
        self.current_capital = 2000
        self.target_profit = 3400  # 目标利润3400U
        self.max_position_size = 500  # 单笔最大500U
        self.min_score = 70  # 最低开仓分数70
        self.fee_rate = 0.0005  # 手续费率 0.05% (Binance合约)
        
        # 监控币种 (25个 - 激进策略：增加交易机会)
        self.watch_symbols = [
            # 原有监控 (6个)
            'XMR', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK',
            # 高分币种 (6个)
            'VET',   # 得分100 - VeChain
            'BNB',   # 得分80 - Binance Coin
            'INJ',   # 得分80 - Injective
            'LINK',  # 得分70 - Chainlink
            'OP',    # 得分70 - Optimism
            'FIL',   # 得分70 - Filecoin
            # 高流动性币种 (6个)
            'ETH',   # 以太坊 - 市值第2
            'AVAX',  # Avalanche - 高流动性
            'DOT',   # Polkadot - 老牌公链
            'ATOM',  # Cosmos - 跨链龙头
            'MATIC', # Polygon - Layer2龙头
            'ARB',   # Arbitrum - L2新秀
            # 高波动性币种 (6个)
            'APT',   # Aptos - 新公链
            'SUI',   # Sui - 高波动
            'SEI',   # Sei - DeFi链
            'TIA',   # Celestia - 模块化区块链
            'WLD',   # Worldcoin - AI概念
            'NEAR'   # Near Protocol - 分片公链
        ]
        
        # 数据库路径
        self.db_path = '/opt/trading-bot/quant-trade-bot/data/db/trading_assistant.db'
        
        # 当前持仓
        self.positions = {}  # {symbol: position_info}

        # 风险控制参数
        self.risk_pause = False  # 风险暂停标志
        self.last_risk_check = None  # 上次风险检查时间
        self.peak_capital = self.initial_capital  # 历史最高资金
        self.risk_position_multiplier = 1.0  # 风险调整后的仓位倍数 (1.0=正常, 0.5=减半)

        # 初始化数据库
        self.init_database()

        # 加载现有持仓
        self.load_positions()

        print(f"【交易助手-模拟】🧪 系统启动")
        print(f"初始本金: {self.initial_capital}U")
        print(f"目标利润: {self.target_profit}U")
        print(f"监控币种: {', '.join(self.watch_symbols)}")
        
    def load_config(self):
        """加载配置"""
        config_path = '/opt/trading-bot/quant-trade-bot/config/config.json'
        try:
            with open(config_path, 'r') as f:
                return json.load(f)
        except:
            return {}
    
    def init_database(self):
        """初始化数据库（如果不存在则创建）"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        # 确保real_trades表存在
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS real_trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT NOT NULL,
                direction TEXT NOT NULL,
                entry_price REAL NOT NULL,
                exit_price REAL,
                amount REAL NOT NULL,
                leverage INTEGER NOT NULL,
                stop_loss REAL,
                take_profit REAL,
                entry_time TIMESTAMP NOT NULL,
                exit_time TIMESTAMP,
                status TEXT NOT NULL,
                pnl REAL,
                roi REAL,
                    fee REAL DEFAULT 0,
                worst_trade REAL,
                mode TEXT NOT NULL
            )
        ''')
        
        conn.commit()
        conn.close()
        
    def load_positions(self):
        """从数据库加载未平仓位"""
        conn = sqlite3.connect(self.db_path)
        cursor = conn.cursor()
        
        cursor.execute('''
            SELECT symbol, direction, entry_price, amount, leverage, stop_loss, take_profit, entry_time
            FROM real_trades
            WHERE status = 'OPEN' AND mode = 'paper' AND assistant = '交易助手'
        ''')
        
        rows = cursor.fetchall()
        for row in rows:
            symbol = row[0]
            self.positions[symbol] = {
                'direction': row[1],
                'entry_price': row[2],
                'amount': row[3],
                'leverage': row[4],
                'stop_loss': row[5],
                'take_profit': row[6],
                'entry_time': row[7]
            }
        
        conn.close()
        
        if self.positions:
            print(f"加载现有持仓: {list(self.positions.keys())}")
    
    def get_price(self, symbol):
        """获取币种价格（使用Binance期货API）"""
        try:
            symbol_map = {
                # 原有币种
                'XMR': 'XMRUSDT', 'AXS': 'AXSUSDT',
                'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT',
                'DUSK': 'DUSKUSDT', 'VET': 'VETUSDT', 'BNB': 'BNBUSDT',
                'INJ': 'INJUSDT', 'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
                # 新增币种
                'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
                'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
                'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
                'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
            }
            binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

            # 使用Binance期货API
            url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={binance_symbol}"
            response = requests.get(url, timeout=10)
            data = response.json()
            return float(data['price'])
        except Exception as e:
            print(f"获取{symbol}价格失败: {e}")
            return None
    
    def get_kline_data(self, symbol, interval='1h', limit=100):
        """获取K线数据（使用Binance期货API）"""
        try:
            symbol_map = {
                # 原有币种
                'XMR': 'XMRUSDT', 'AXS': 'AXSUSDT',
                'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT',
                'DUSK': 'DUSKUSDT', 'VET': 'VETUSDT', 'BNB': 'BNBUSDT',
                'INJ': 'INJUSDT', 'LINK': 'LINKUSDT', 'OP': 'OPUSDT', 'FIL': 'FILUSDT',
                # 新增币种
                'ETH': 'ETHUSDT', 'AVAX': 'AVAXUSDT', 'DOT': 'DOTUSDT',
                'ATOM': 'ATOMUSDT', 'MATIC': 'MATICUSDT', 'ARB': 'ARBUSDT',
                'APT': 'APTUSDT', 'SUI': 'SUIUSDT', 'SEI': 'SEIUSDT',
                'TIA': 'TIAUSDT', 'WLD': 'WLDUSDT', 'NEAR': 'NEARUSDT'
            }
            binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")

            # 使用Binance期货API
            url = f"https://fapi.binance.com/fapi/v1/klines?symbol={binance_symbol}&interval={interval}&limit={limit}"
            response = requests.get(url, timeout=10)
            return response.json()
        except Exception as e:
            print(f"获取{symbol} K线失败: {e}")
            return None
    
    def calculate_rsi(self, prices, period=14):
        """计算RSI"""
        if len(prices) < period:
            return 50
        
        deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
        gains = [d if d > 0 else 0 for d in deltas]
        losses = [-d if d < 0 else 0 for d in deltas]
        
        avg_gain = sum(gains[-period:]) / period
        avg_loss = sum(losses[-period:]) / period
        
        if avg_loss == 0:
            return 100
        
        rs = avg_gain / avg_loss
        rsi = 100 - (100 / (1 + rs))
        return rsi
    
    def analyze_signal(self, symbol):
        """分析交易信号（0-100分）"""
        try:
            klines = self.get_kline_data(symbol, '1h', 100)
            if not klines:
                return 0, None
            
            closes = [float(k[4]) for k in klines]
            volumes = [float(k[5]) for k in klines]
            highs = [float(k[2]) for k in klines]
            lows = [float(k[3]) for k in klines]
            
            current_price = closes[-1]
            
            # 1. RSI分析 (40分)
            rsi = self.calculate_rsi(closes)
            if rsi < 30:
                rsi_score = 40  # 超卖，做多机会
                direction = 'LONG'
            elif rsi > 70:
                rsi_score = 40  # 超买，做空机会
                direction = 'SHORT'
            elif 40 <= rsi <= 60:
                rsi_score = 20
                direction = 'LONG' if rsi < 50 else 'SHORT'
            else:
                rsi_score = 10
                direction = 'LONG' if rsi < 50 else 'SHORT'
            
            # 2. 趋势分析 (25分)
            ma7 = sum(closes[-7:]) / 7
            ma20 = sum(closes[-20:]) / 20
            ma50 = sum(closes[-50:]) / 50
            
            if current_price > ma7 > ma20 > ma50:
                trend_score = 25
                direction = 'LONG'
            elif current_price < ma7 < ma20 < ma50:
                trend_score = 25
                direction = 'SHORT'
            elif current_price > ma7 > ma20:
                trend_score = 15
            else:
                trend_score = 5
            
            # 3. 成交量分析 (20分)
            avg_volume = sum(volumes[-20:]) / 20
            recent_volume = volumes[-1]
            volume_ratio = recent_volume / avg_volume if avg_volume > 0 else 1
            
            if volume_ratio > 1.5:
                volume_score = 20
            elif volume_ratio > 1.2:
                volume_score = 15
            elif volume_ratio > 1:
                volume_score = 10
            else:
                volume_score = 5
            
            # 4. 价格位置 (15分)
            high_50 = max(highs[-50:])
            low_50 = min(lows[-50:])
            price_position = (current_price - low_50) / (high_50 - low_50) if high_50 > low_50 else 0.5
            
            if price_position < 0.3:  # 接近底部
                position_score = 15
                direction = 'LONG'
            elif price_position > 0.7:  # 接近顶部
                position_score = 15
                direction = 'SHORT'
            else:
                position_score = 5
            
            total_score = rsi_score + trend_score + volume_score + position_score
            
            analysis = {
                'price': current_price,
                'rsi': rsi,
                'ma7': ma7,
                'ma20': ma20,
                'ma50': ma50,
                'volume_ratio': volume_ratio,
                'price_position': price_position,
                'direction': direction,
                'score': total_score
            }
            
            return total_score, analysis
            
        except Exception as e:
            print(f"{symbol}信号分析失败: {e}")
            return 0, None
    
    def calculate_position_size(self, score):
        """根据信号强度计算仓位大小"""
        # 可用资金
        available = self.current_capital - sum([p['amount'] for p in self.positions.values()])
        
        if score >= 85:
            size = min(500, available * 0.3)
            leverage = 10
        elif score >= 75:
            size = min(400, available * 0.25)
            leverage = 8
        elif score >= 70:
            size = min(300, available * 0.2)
            leverage = 5
        else:
            return 0, 5
        
        return size, leverage
    
    def open_position(self, symbol, analysis):
        """开仓"""
        try:
            score = analysis['score']
            direction = analysis['direction']
            entry_price = analysis['price']

            # 计算仓位大小和杠杆
            amount, leverage = self.calculate_position_size(score)

            # 根据风险等级调整仓位大小
            if self.risk_position_multiplier < 1.0:
                original_amount = amount
                amount = int(amount * self.risk_position_multiplier)
                print(f"⚠️ 风险调整: 仓位 {original_amount}U → {amount}U ({self.risk_position_multiplier*100:.0f}%)")

            if amount < 100:
                print(f"{symbol} 资金不足或风险过高，跳过开仓")
                return
            
            # 计算止损止盈
            if direction == 'LONG':
                stop_loss = entry_price * 0.95  # -5%
                take_profit = entry_price * 1.10  # +10%
            else:
                stop_loss = entry_price * 1.05
                take_profit = entry_price * 0.90
            
            # 记录持仓
            self.positions[symbol] = {
                'direction': direction,
                'entry_price': entry_price,
                'amount': amount,
                'leverage': leverage,
                'stop_loss': stop_loss,
                'take_profit': take_profit,
                'entry_time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            }
            
            # 写入数据库
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            cursor.execute('''
                INSERT INTO real_trades (
                    symbol, direction, entry_price, amount, leverage,
                    stop_loss, take_profit, entry_time, status,
                    assistant, mode, reason
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                symbol, direction, entry_price, amount, leverage,
                stop_loss, take_profit, self.positions[symbol]['entry_time'],
                'OPEN', '交易助手', 'paper',
                f"信号评分{score}分，RSI {analysis['rsi']:.1f}"
            ))
            
            conn.commit()
            conn.close()
            
            # 发送通知
            stars = '⭐' * (score // 20)
            msg = f"""【交易助手-模拟】🧪 开仓通知

💰 币种：{symbol}/USDT
📈 方向：{'做多' if direction == 'LONG' else '做空'}
💵 金额：{amount}U
🔢 杠杆：{leverage}x
📍 入场：${entry_price:.6f}

📊 信号评分：{score}分 {stars}
📉 RSI：{analysis['rsi']:.1f}
📈 趋势：{'多头' if analysis['price'] > analysis['ma20'] else '空头'}

🎯 止盈：${take_profit:.6f} (+10%)
🛑 止损：${stop_loss:.6f} (-5%)

💼 当前持仓数：{len(self.positions)}
💰 剩余资金：{self.current_capital - sum([p['amount'] for p in self.positions.values()]):.0f}U
"""
            self.send_telegram(msg)
            print(f"✅ {symbol} 开仓成功 - {direction} {amount}U @ ${entry_price:.6f}")
            
        except Exception as e:
            print(f"开仓失败: {e}")
            import traceback
            traceback.print_exc()
    
    def check_position(self, symbol, position):
        """检查持仓是否需要平仓（简单止盈止损）"""
        try:
            current_price = self.get_price(symbol)
            if not current_price:
                return

            direction = position['direction']
            stop_loss = position['stop_loss']
            take_profit = position['take_profit']

            # 检查止损止盈
            should_close = False
            reason = ""

            if direction == 'LONG':
                if current_price >= take_profit:
                    should_close = True
                    reason = "触发止盈"
                elif current_price <= stop_loss:
                    should_close = True
                    reason = "触发止损"
            else:  # SHORT
                if current_price <= take_profit:
                    should_close = True
                    reason = "触发止盈"
                elif current_price >= stop_loss:
                    should_close = True
                    reason = "触发止损"

            if should_close:
                self.close_position(symbol, current_price, reason)

        except Exception as e:
            print(f"检查{symbol}持仓失败: {e}")

    def close_position(self, symbol, exit_price, reason):
        """平仓"""
        try:
            position = self.positions.get(symbol)
            if not position:
                return
            
            direction = position['direction']
            entry_price = position['entry_price']
            amount = position['amount']
            leverage = position['leverage']
            
            # 计算盈亏
            if direction == 'LONG':
                price_change_pct = (exit_price - entry_price) / entry_price
            else:
                price_change_pct = (entry_price - exit_price) / entry_price
            
            roi = price_change_pct * leverage * 100
            pnl_before_fee = amount * price_change_pct * leverage
            
            # 计算手续费：开仓费 + 平仓费
            position_value = amount * leverage
            entry_fee = position_value * self.fee_rate
            exit_fee = position_value * self.fee_rate
            total_fee = entry_fee + exit_fee

            # 计算资金费率：每8小时收取0.01%
            entry_time_str = position.get('entry_time', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            entry_time = datetime.strptime(entry_time_str, '%Y-%m-%d %H:%M:%S')
            exit_time_obj = datetime.now()
            holding_hours = (exit_time_obj - entry_time).total_seconds() / 3600
            funding_rate = 0.0001  # 0.01%
            funding_fee = position_value * funding_rate * (holding_hours / 8)

            # 最终盈亏 = 价格盈亏 - 手续费 - 资金费率
            pnl = pnl_before_fee - total_fee - funding_fee

            # 更新资金
            self.current_capital += pnl

            # 更新数据库
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()

            exit_time = exit_time_obj.strftime('%Y-%m-%d %H:%M:%S')

            cursor.execute('''
                UPDATE real_trades
                SET exit_price = ?, exit_time = ?, status = 'CLOSED',
                    pnl = ?, roi = ?, fee = ?, funding_fee = ?, reason = reason || ' | ' || ?
                WHERE symbol = ? AND status = 'OPEN' AND mode = 'paper' AND assistant = '交易助手'
            ''', (exit_price, exit_time, pnl, roi, total_fee, funding_fee, reason, symbol))
            
            conn.commit()
            conn.close()
            
            # 删除持仓
            del self.positions[symbol]
            
            # 发送通知
            total_profit = self.current_capital - self.initial_capital
            progress = (total_profit / self.target_profit) * 100
            
            emoji = "🎉" if pnl > 0 else "😢"
            msg = f"""【交易助手-模拟】🧪 平仓通知 {emoji}

💰 币种：{symbol}/USDT
📈 方向：{'做多' if direction == 'LONG' else '做空'}
💵 金额：{amount}U × {leverage}x

📍 入场：${entry_price:.6f}
📍 出场：${exit_price:.6f}
📊 价格盈亏：{pnl_before_fee:+.2f}U ({roi:+.2f}%)
💸 手续费：-{total_fee:.2f}U
💰 实际盈亏：{pnl:+.2f}U
💡 原因：{reason}

━━━━━━━━━━━━━━━
💼 当前资金：{self.current_capital:.2f}U
📈 总盈亏：{total_profit:+.2f}U
🎯 目标进度：{progress:.1f}% ({total_profit:.0f}/{self.target_profit}U)
📦 剩余持仓：{len(self.positions)}个
"""
            self.send_telegram(msg)
            print(f"✅ {symbol} 平仓成功 - {reason} PNL: {pnl:+.2f}U")
            
        except Exception as e:
            print(f"平仓失败: {e}")
            import traceback
            traceback.print_exc()
    
    def scan_market(self):
        """扫描市场寻找机会"""
        print(f"\n━━━━ 市场扫描 {datetime.now().strftime('%H:%M:%S')} ━━━━")

        # 检查是否处于风险暂停状态
        if self.risk_pause:
            print(f"⏸️ 风险过高，暂停开新仓")
            return

        opportunities = []

        for symbol in self.watch_symbols:
            # 如果已经持仓，跳过
            if symbol in self.positions:
                continue

            score, analysis = self.analyze_signal(symbol)

            if score >= self.min_score:
                opportunities.append((symbol, score, analysis))
                print(f"✨ {symbol}: {score}分 - {analysis['direction']}")

        # 按分数排序
        opportunities.sort(key=lambda x: x[1], reverse=True)

        # 检查是否有足够资金
        available = self.current_capital - sum([p['amount'] for p in self.positions.values()])

        # 最多同时持有8个仓位（激进策略：增加资金利用率）
        if len(self.positions) < 8 and available > 200:
            # 开最强信号的仓
            if opportunities:
                symbol, score, analysis = opportunities[0]
                print(f"🎯 准备开仓: {symbol} (评分{score})")
                self.open_position(symbol, analysis)
        else:
            print(f"⏸️  暂不开仓 (持仓{len(self.positions)}/8, 可用{available:.0f}U)")
    
    def send_telegram(self, message):
        """发送Telegram通知"""
        try:
            if not self.telegram_token or not self.chat_id:
                return
            
            url = f"https://api.telegram.org/bot{self.telegram_token}/sendMessage"
            data = {
                'chat_id': self.chat_id,
                'text': message,
                'parse_mode': 'HTML'
            }
            requests.post(url, json=data, timeout=10)
        except Exception as e:
            print(f"Telegram发送失败: {e}")
    
    def send_daily_report(self):
        """发送每日报告"""
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            today = datetime.now().strftime('%Y-%m-%d')
            
            # 今日交易统计
            cursor.execute('''
                SELECT COUNT(*), SUM(pnl), AVG(roi)
                FROM real_trades
                WHERE DATE(entry_time) = ? AND mode = 'paper' AND assistant = '交易助手'
                AND status = 'CLOSED'
            ''', (today,))
            
            row = cursor.fetchone()
            trades_today = row[0] or 0
            pnl_today = row[1] or 0
            avg_roi = row[2] or 0
            
            # 总统计
            cursor.execute('''
                SELECT COUNT(*), SUM(pnl),
                       SUM(CASE WHEN pnl > 0 THEN 1 ELSE 0 END)
                FROM real_trades
                WHERE mode = 'paper' AND assistant = '交易助手'
                AND status = 'CLOSED'
            ''')
            
            row = cursor.fetchone()
            total_trades = row[0] or 0
            total_pnl = row[1] or 0
            win_trades = row[2] or 0
            
            win_rate = (win_trades / total_trades * 100) if total_trades > 0 else 0
            
            conn.close()
            
            total_profit = self.current_capital - self.initial_capital
            progress = (total_profit / self.target_profit) * 100
            
            msg = f"""【交易助手-模拟】📊 每日报告

📅 日期：{today}

━━━━ 今日战绩 ━━━━
📈 交易次数：{trades_today}笔
💰 今日盈亏：{pnl_today:+.2f}U
📊 平均回报：{avg_roi:+.2f}%

━━━━ 累计战绩 ━━━━
📈 总交易：{total_trades}笔
🎯 胜率：{win_rate:.1f}%
💰 总盈亏：{total_pnl:+.2f}U

━━━━ 资金状况 ━━━━
💼 当前资金：{self.current_capital:.2f}U
📈 盈亏：{total_profit:+.2f}U ({(total_profit/self.initial_capital*100):+.1f}%)
🎯 目标进度：{progress:.1f}%
📦 持仓数：{len(self.positions)}

━━━━ 目标追踪 ━━━━
🎯 目标：{self.target_profit}U (7天内)
📍 已赚：{total_profit:.0f}U
📍 还需：{self.target_profit - total_profit:.0f}U
"""
            self.send_telegram(msg)
            print(msg)
            
        except Exception as e:
            print(f"生成报告失败: {e}")

    def calculate_risk_metrics(self):
        """计算风险指标"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.row_factory = sqlite3.Row
            cursor = conn.cursor()

            # 1. 计算最大回撤和当前回撤 (兼容所有SQLite版本)
            cursor.execute('''
                SELECT exit_time, pnl
                FROM real_trades
                WHERE mode = 'paper' AND assistant = '交易助手'
                AND status = 'CLOSED'
                ORDER BY exit_time
            ''')

            trades_data = cursor.fetchall()
            max_drawdown = 0
            peak_capital = self.initial_capital
            current_drawdown = 0
            cumulative_capital = self.initial_capital

            # 手动计算累积盈亏和回撤
            for trade in trades_data:
                cumulative_capital += trade['pnl']
                if cumulative_capital > peak_capital:
                    peak_capital = cumulative_capital
                    self.peak_capital = peak_capital  # 更新峰值
                drawdown_pct = ((peak_capital - cumulative_capital) / peak_capital * 100) if peak_capital > 0 else 0
                max_drawdown = max(max_drawdown, drawdown_pct)

            if self.current_capital < self.peak_capital:
                current_drawdown = (self.peak_capital - self.current_capital) / self.peak_capital * 100

            # 2. 计算连续亏损次数
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

            # 3. 计算持仓集中度
            if len(self.positions) > 0:
                position_amounts = [p['amount'] for p in self.positions.values()]
                max_position_amount = max(position_amounts)
                total_position = sum(position_amounts)
                max_position_pct = (max_position_amount / total_position * 100) if total_position > 0 else 0
            else:
                max_position_pct = 0

            # 4. 计算多空比例
            long_count = sum(1 for p in self.positions.values() if p['direction'] == 'LONG')
            short_count = sum(1 for p in self.positions.values() if p['direction'] == 'SHORT')
            total_positions = len(self.positions)

            if total_positions > 0:
                long_ratio = (long_count / total_positions) * 100
                short_ratio = (short_count / total_positions) * 100
            else:
                long_ratio = 0
                short_ratio = 0

            # 5. 计算杠杆倍率
            if len(self.positions) > 0:
                leverages = [p['leverage'] for p in self.positions.values()]
                avg_leverage = sum(leverages) / len(leverages)
            else:
                avg_leverage = 0

            conn.close()

            # 6. 计算风险评分 (0-10)
            risk_score = 0

            # 回撤风险 (0-3分)
            if current_drawdown > 15:
                risk_score += 3
            elif current_drawdown > 10:
                risk_score += 2
            elif current_drawdown > 5:
                risk_score += 1

            # 连续亏损 (0-2分)
            if consecutive_losses >= 3:
                risk_score += 2
            elif consecutive_losses >= 2:
                risk_score += 1

            # 持仓集中度 (0-2分)
            if max_position_pct > 40:
                risk_score += 2
            elif max_position_pct > 30:
                risk_score += 1

            # 方向失衡 (0-2分)
            if max(long_ratio, short_ratio) > 85:
                risk_score += 2
            elif max(long_ratio, short_ratio) > 70:
                risk_score += 1

            # 杠杆风险 (0-1分)
            if avg_leverage > 3:
                risk_score += 1

            risk_metrics = {
                'max_drawdown': max_drawdown,
                'current_drawdown': current_drawdown,
                'consecutive_losses': consecutive_losses,
                'max_position_pct': max_position_pct,
                'long_ratio': long_ratio,
                'short_ratio': short_ratio,
                'avg_leverage': avg_leverage,
                'risk_score': risk_score
            }

            return risk_metrics

        except Exception as e:
            print(f"计算风险指标失败: {e}")
            return None

    def auto_reduce_positions(self):
        """高风险时自动减仓 - 关闭亏损最大的仓位"""
        try:
            if not self.positions:
                return 0

            # 计算每个持仓的当前盈亏
            losing_positions = []
            for symbol, pos in self.positions.items():
                try:
                    current_price = self.get_current_price(symbol)
                    if not current_price:
                        continue

                    if pos['direction'] == 'LONG':
                        pnl_pct = (current_price - pos['entry_price']) / pos['entry_price'] * 100
                    else:
                        pnl_pct = (pos['entry_price'] - current_price) / pos['entry_price'] * 100

                    pnl_pct *= pos['leverage']

                    if pnl_pct < 0:  # 只关注亏损的
                        losing_positions.append((symbol, pnl_pct, current_price))
                except:
                    continue

            # 按亏损排序，关闭亏损最大的
            losing_positions.sort(key=lambda x: x[1])

            closed_count = 0
            # 最多关闭一半的亏损仓位
            max_close = max(1, len(losing_positions) // 2)

            for symbol, pnl_pct, current_price in losing_positions[:max_close]:
                print(f"🔴 自动减仓: {symbol} (亏损 {pnl_pct:.2f}%)")
                self.close_position(symbol, current_price, "风险过高-自动减仓")
                closed_count += 1

            return closed_count

        except Exception as e:
            print(f"自动减仓失败: {e}")
            return 0

    def force_close_all(self):
        """极高风险时强制清仓"""
        try:
            if not self.positions:
                return 0

            closed_count = 0
            symbols_to_close = list(self.positions.keys())

            for symbol in symbols_to_close:
                try:
                    current_price = self.get_current_price(symbol)
                    if current_price:
                        print(f"🚨 强制清仓: {symbol}")
                        self.close_position(symbol, current_price, "极高风险-强制清仓")
                        closed_count += 1
                except:
                    continue

            return closed_count

        except Exception as e:
            print(f"强制清仓失败: {e}")
            return 0

    def tighten_stop_loss(self):
        """高风险时收紧止损"""
        try:
            tightened_count = 0
            for symbol, pos in self.positions.items():
                # 将止损收紧到原来的一半
                original_stop = pos.get('stop_loss_pct', 5)
                new_stop = max(2, original_stop * 0.6)  # 最小2%

                if new_stop < original_stop:
                    pos['stop_loss_pct'] = new_stop
                    print(f"⚡ 收紧止损: {symbol} {original_stop}% → {new_stop:.1f}%")
                    tightened_count += 1

            return tightened_count

        except Exception as e:
            print(f"收紧止损失败: {e}")
            return 0

    def check_risk_level(self):
        """检查风险等级并发出预警"""
        try:
            risk_metrics = self.calculate_risk_metrics()
            if not risk_metrics:
                return

            risk_score = risk_metrics['risk_score']
            current_drawdown = risk_metrics['current_drawdown']
            consecutive_losses = risk_metrics['consecutive_losses']

            # 判断风险等级并执行自动响应
            actions_taken = []

            # 极高风险 (>=9): 强制清仓
            if risk_score >= 9:
                risk_level = "🚨 极高风险"
                should_pause = True
                closed = self.force_close_all()
                if closed > 0:
                    actions_taken.append(f"强制清仓 {closed} 个")
                self.risk_position_multiplier = 0  # 完全停止开仓

            # 高风险 (7-8): 自动减仓 + 收紧止损
            elif risk_score >= 7:
                risk_level = "🔴 高风险"
                should_pause = True
                reduced = self.auto_reduce_positions()
                if reduced > 0:
                    actions_taken.append(f"自动减仓 {reduced} 个")
                tightened = self.tighten_stop_loss()
                if tightened > 0:
                    actions_taken.append(f"收紧止损 {tightened} 个")
                self.risk_position_multiplier = 0.3  # 新仓位减至30%

            # 中风险 (4-6): 减少新仓位大小
            elif risk_score >= 4:
                risk_level = "🟡 中风险"
                should_pause = False
                self.risk_position_multiplier = 0.5  # 新仓位减半

            # 低风险 (<4): 正常交易
            else:
                risk_level = "🟢 低风险"
                should_pause = False
                self.risk_position_multiplier = 1.0  # 正常仓位

            # 记录当前时间
            now = datetime.now()

            # 极高风险预警 (>=9)
            if risk_score >= 9:
                if not self.last_risk_check or (now - self.last_risk_check).seconds >= 1800:
                    actions_str = '\n'.join([f"• {a}" for a in actions_taken]) if actions_taken else "• 无"
                    msg = f"""🚨🚨🚨 【极高风险预警】🚨🚨🚨

【交易助手-模拟】检测到极高风险！已执行紧急措施！

━━━━ 风险指标 ━━━━
🚨 风险等级：{risk_level}
📊 风险评分：{risk_score}/10

━━━━ 详细指标 ━━━━
📉 最大回撤：{risk_metrics['max_drawdown']:.2f}%
⚠️ 当前回撤：{current_drawdown:.2f}%
🔴 连续亏损：{consecutive_losses}笔
⚖️ 持仓集中：{risk_metrics['max_position_pct']:.1f}%
📊 多/空比例：{risk_metrics['long_ratio']:.0f}%/{risk_metrics['short_ratio']:.0f}%
💪 杠杆倍率：{risk_metrics['avg_leverage']:.1f}x

━━━━ 已执行措施 ━━━━
{actions_str}

🛑 系统已强制清仓并停止交易！
⏰ 时间：{now.strftime('%Y-%m-%d %H:%M:%S')}
"""
                    self.send_telegram(msg)
                    print(f"\n{msg}\n")
                    self.last_risk_check = now

                # 设置风险暂停
                if should_pause and not self.risk_pause:
                    self.risk_pause = True
                    print("🛑 极高风险，强制清仓并停止交易")

            # 高风险预警 (7-8)
            elif risk_score >= 7:
                if not self.last_risk_check or (now - self.last_risk_check).seconds >= 1800:
                    actions_str = '\n'.join([f"• {a}" for a in actions_taken]) if actions_taken else "• 无"
                    msg = f"""⚠️⚠️⚠️ 【高风险预警】⚠️⚠️⚠️

【交易助手-模拟】检测到高风险状态！

━━━━ 风险指标 ━━━━
🔴 风险等级：{risk_level}
📊 风险评分：{risk_score}/10

━━━━ 详细指标 ━━━━
📉 最大回撤：{risk_metrics['max_drawdown']:.2f}%
⚠️ 当前回撤：{current_drawdown:.2f}%
🔴 连续亏损：{consecutive_losses}笔
⚖️ 持仓集中：{risk_metrics['max_position_pct']:.1f}%
📊 多/空比例：{risk_metrics['long_ratio']:.0f}%/{risk_metrics['short_ratio']:.0f}%
💪 杠杆倍率：{risk_metrics['avg_leverage']:.1f}x

━━━━ 已执行措施 ━━━━
{actions_str}
• 新仓位大小降至30%

⏸️ 系统已暂停开新仓！
⏰ 时间：{now.strftime('%Y-%m-%d %H:%M:%S')}
"""
                    self.send_telegram(msg)
                    print(f"\n{msg}\n")
                    self.last_risk_check = now

                # 设置风险暂停
                if should_pause and not self.risk_pause:
                    self.risk_pause = True
                    print("⏸️ 高风险，暂停开新仓")

            # 中风险预警（每小时最多发送一次）
            elif risk_score >= 4:
                if not self.last_risk_check or (now - self.last_risk_check).seconds >= 3600:
                    msg = f"""⚠️ 【风险提醒】

【交易助手-模拟】检测到中等风险

━━━━ 风险指标 ━━━━
🟡 风险等级：{risk_level}
📊 风险评分：{risk_score}/10

━━━━ 详细指标 ━━━━
📉 最大回撤：{risk_metrics['max_drawdown']:.2f}%
⚠️ 当前回撤：{current_drawdown:.2f}%
🔴 连续亏损：{consecutive_losses}笔
⚖️ 持仓集中：{risk_metrics['max_position_pct']:.1f}%
📊 多/空比例：{risk_metrics['long_ratio']:.0f}%/{risk_metrics['short_ratio']:.0f}%
💪 杠杆倍率：{risk_metrics['avg_leverage']:.1f}x

━━━━ 风险调整 ━━━━
• 新仓位大小降至50%

💡 建议：密切关注市场，谨慎开仓
⏰ 时间：{now.strftime('%Y-%m-%d %H:%M:%S')}
"""
                    self.send_telegram(msg)
                    print(f"\n{msg}\n")
                    self.last_risk_check = now

            # 低风险状态：如果之前处于暂停状态，可以恢复
            else:
                if self.risk_pause:
                    self.risk_pause = False
                    msg = f"""✅ 【风险恢复】

【交易助手-模拟】风险等级已降低

━━━━ 风险指标 ━━━━
🟢 风险等级：{risk_level}
📊 风险评分：{risk_score}/10

系统已恢复正常交易
⏰ 时间：{now.strftime('%Y-%m-%d %H:%M:%S')}
"""
                    self.send_telegram(msg)
                    print(f"\n{msg}\n")

            return risk_metrics

        except Exception as e:
            print(f"风险检查失败: {e}")
            import traceback
            traceback.print_exc()
            return None

    def run(self, interval=300):
        """运行主循环"""
        last_report_time = datetime.now().replace(hour=0, minute=0, second=0)
        scan_count = 0

        print(f"\n🚀 Paper Trading系统开始运行 (每{interval}秒扫描一次)\n")

        while True:
            try:
                # 检查现有持仓
                for symbol in list(self.positions.keys()):
                    self.check_position(symbol, self.positions[symbol])

                # 检查风险等级（每次扫描都检查）
                risk_metrics = self.check_risk_level()

                # 扫描新机会
                self.scan_market()

                scan_count += 1

                # 每12次扫描（1小时）发送一次简报
                if scan_count % 12 == 0:
                    total_profit = self.current_capital - self.initial_capital
                    progress = (total_profit / self.target_profit) * 100

                    # 包含风险信息的简报
                    if risk_metrics:
                        risk_level = "🔴高" if risk_metrics['risk_score'] >= 7 else "🟡中" if risk_metrics['risk_score'] >= 4 else "🟢低"
                        print(f"\n💼 资金: {self.current_capital:.2f}U | 盈亏: {total_profit:+.2f}U | 进度: {progress:.1f}% | 持仓: {len(self.positions)} | 风险: {risk_level} ({risk_metrics['risk_score']}/10)\n")
                    else:
                        print(f"\n💼 资金: {self.current_capital:.2f}U | 盈亏: {total_profit:+.2f}U | 进度: {progress:.1f}% | 持仓: {len(self.positions)}\n")
                
                # 每天发送一次报告
                now = datetime.now()
                if (now - last_report_time).days >= 1:
                    self.send_daily_report()
                    last_report_time = now
                
                # 检查是否达到目标
                if self.current_capital >= self.initial_capital + self.target_profit:
                    msg = f"""🎉🎉🎉 目标达成！🎉🎉🎉

【交易助手-模拟】已成功赚取{self.target_profit}U！

初始资金：{self.initial_capital}U
当前资金：{self.current_capital:.2f}U
总盈利：{self.current_capital - self.initial_capital:.2f}U

准备进入真实交易模式！💪
"""
                    self.send_telegram(msg)
                    print(msg)
                    break
                
                time.sleep(interval)
                
            except KeyboardInterrupt:
                print("\n\n⏸️  系统暂停")
                total_profit = self.current_capital - self.initial_capital
                print(f"当前资金: {self.current_capital:.2f}U")
                print(f"总盈亏: {total_profit:+.2f}U")
                print(f"持仓数: {len(self.positions)}")
                break
            except Exception as e:
                print(f"运行错误: {e}")
                import traceback
                traceback.print_exc()
                time.sleep(interval)

if __name__ == '__main__':
    trader = PaperTradingAssistant()
    trader.run(interval=60)  # 1分钟扫描一次（提高交易频率）
