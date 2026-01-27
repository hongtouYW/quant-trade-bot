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
        
        # 监控币种
        self.watch_symbols = ['XMR', 'MEMES', 'AXS', 'ROSE', 'XRP', 'SOL', 'DUSK']
        
        # 数据库路径
        self.db_path = '/Users/hongtou/newproject/quant-trade-bot/data/db/trading_assistant.db'
        
        # 当前持仓
        self.positions = {}  # {symbol: position_info}
        
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
        config_path = '/Users/hongtou/newproject/quant-trade-bot/config/config.json'
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
        """获取币种价格"""
        try:
            coin_id_map = {
                'XMR': 'monero', 'MEMES': 'meme', 'AXS': 'axie-infinity',
                'ROSE': 'oasis-network', 'XRP': 'ripple', 'SOL': 'solana', 'DUSK': 'dusk-network'
            }
            coin_id = coin_id_map.get(symbol, symbol.lower())
            
            url = f"https://api.coingecko.com/api/v3/simple/price?ids={coin_id}&vs_currencies=usd"
            response = requests.get(url, timeout=10)
            data = response.json()
            return data[coin_id]['usd']
        except Exception as e:
            print(f"获取{symbol}价格失败: {e}")
            return None
    
    def get_kline_data(self, symbol, interval='1h', limit=100):
        """获取K线数据"""
        try:
            symbol_map = {
                'XMR': 'XMRUSDT', 'MEMES': 'MEMESUSDT', 'AXS': 'AXSUSDT',
                'ROSE': 'ROSEUSDT', 'XRP': 'XRPUSDT', 'SOL': 'SOLUSDT', 'DUSK': 'DUSKUSDT'
            }
            binance_symbol = symbol_map.get(symbol, f"{symbol}USDT")
            
            url = f"https://api.binance.com/api/v3/klines?symbol={binance_symbol}&interval={interval}&limit={limit}"
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
            
            if amount < 100:
                print(f"{symbol} 资金不足，跳过开仓")
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
        """检查持仓是否需要平仓"""
        try:
            current_price = self.get_price(symbol)
            if not current_price:
                return
            
            direction = position['direction']
            entry_price = position['entry_price']
            stop_loss = position['stop_loss']
            take_profit = position['take_profit']
            leverage = position['leverage']
            
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
            else:
                # 计算当前盈亏
                if direction == 'LONG':
                    price_change_pct = (current_price - entry_price) / entry_price
                else:
                    price_change_pct = (entry_price - current_price) / entry_price
                
                roi = price_change_pct * leverage * 100
                pnl = position['amount'] * price_change_pct * leverage
                
                # 每30分钟发送一次持仓更新（简化版）
                # print(f"{symbol} 当前ROI: {roi:+.2f}%, PNL: {pnl:+.2f}U")
                
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
            
            # 最终盈亏 = 价格盈亏 - 手续费
            pnl = pnl_before_fee - total_fee
            
            # 更新资金
            self.current_capital += pnl
            
            # 更新数据库
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            
            exit_time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            cursor.execute('''
                UPDATE real_trades
                SET exit_price = ?, exit_time = ?, status = 'CLOSED',
                    pnl = ?, roi = ?, fee = ?, reason = reason || ' | ' || ?
                WHERE symbol = ? AND status = 'OPEN' AND mode = 'paper' AND assistant = '交易助手'
            ''', (exit_price, exit_time, pnl, roi, total_fee, reason, symbol))
            
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
        
        # 最多同时持有3个仓位
        if len(self.positions) < 3 and available > 200:
            # 开最强信号的仓
            if opportunities:
                symbol, score, analysis = opportunities[0]
                print(f"🎯 准备开仓: {symbol} (评分{score})")
                self.open_position(symbol, analysis)
        else:
            print(f"⏸️  暂不开仓 (持仓{len(self.positions)}/3, 可用{available:.0f}U)")
    
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
                
                # 扫描新机会
                self.scan_market()
                
                scan_count += 1
                
                # 每12次扫描（1小时）发送一次简报
                if scan_count % 12 == 0:
                    total_profit = self.current_capital - self.initial_capital
                    progress = (total_profit / self.target_profit) * 100
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
    trader.run(interval=300)  # 5分钟扫描一次
