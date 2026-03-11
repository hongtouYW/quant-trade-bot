#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易 v4.1 策略 - 基于交易助手 paper_trader v4.1 核心逻辑
独立数据库: paper_trading_v41.db
区别于 v4.3.1: 固定3x杠杆, ATR价格止损, 温和BTC惩罚
"""

import requests
import json
import time
import os
import sqlite3
from datetime import datetime

class AutoTraderV41:
    def __init__(self):
        # === 基本配置 ===
        self.initial_capital = 2000
        self.current_capital = 2000
        self.fee_rate = 0.0005  # 0.05% Binance合约
        self.min_score = 60
        self.max_positions = 15
        self.short_bias = 1.05   # 做空加成5%
        self.min_hold_minutes = 180   # 3h最短持仓
        self.max_hold_minutes = 2880  # 48h最长持仓
        self.fixed_leverage = 3       # v4.1: 固定3x (非动态)

        # === 监控币种 (~150个) ===
        self.watch_symbols = [
            'BTC', 'ETH', 'SOL', 'XRP', 'BNB', 'DOGE', 'ADA', 'AVAX', 'LINK', 'DOT',
            'NEAR', 'SUI', 'APT', 'ATOM', 'FTM', 'HBAR', 'XLM', 'ETC', 'LTC', 'BCH',
            'ALGO', 'ICP', 'FIL', 'XMR', 'TRX',
            'ARB', 'OP', 'MATIC', 'AAVE', 'UNI', 'CRV', 'DYDX', 'INJ', 'SEI',
            'STX', 'RUNE', 'SNX', 'COMP', 'MKR', 'LDO',
            'TAO', 'RENDER', 'FET', 'WLD', 'AGIX', 'OCEAN', 'ARKM', 'PENGU',
            'AIXBT', 'GRASS', 'CGPT',
            'TIA', 'JUP', 'PYTH', 'JTO', 'ENA', 'STRK', 'ZRO', 'WIF',
            'SHIB', 'FLOKI', 'TRUMP',
            'VET', 'AXS', 'ROSE', 'DUSK', 'CHZ', 'ENJ', 'SAND',
            'ONDO', 'PENDLE', 'EIGEN', 'ETHFI', 'TON',
            'MANA', 'GALA', 'IMX', 'ORDI', 'SXP', 'ZEC', 'DASH',
            'WAVES', 'GRT', 'THETA', 'IOTA', 'NEO', 'KAVA', 'ONE', 'CELO',
            'CAKE', 'SUSHI', 'GMX', 'ENS', 'BLUR', 'PEOPLE', 'MASK',
            '1INCH', 'ANKR', 'AR', 'FLOW', 'EGLD', 'KAS', 'JASMY', 'NOT',
            'NEIRO', 'PNUT', 'POPCAT', 'TURBO', 'MEME', 'BOME', 'DOGS',
            'FARTCOIN', 'USUAL', 'ME', 'MOODENG', 'SPX', 'ANIME', 'SONIC',
            'HYPE', 'LINA', 'LEVER', 'ALPHA', 'UNFI',
            'YGG', 'PIXEL', 'PORTAL', 'XAI', 'DYM', 'MANTA', 'ZK', 'W', 'SAGA', 'RSR',
        ]

        # === 币种分层 (基于回测) ===
        self.coin_tiers = {
            # T1: 高盈利币 - 1.3x仓位
            'ICP': 'T1', 'XMR': 'T1', 'IOTA': 'T1', 'DASH': 'T1',
            'COMP': 'T1', 'KAVA': 'T1', 'UNI': 'T1', 'SAND': 'T1',
            'AXS': 'T1', 'NEAR': 'T1', 'DOT': 'T1', 'CHZ': 'T1',
            'ENJ': 'T1', 'ADA': 'T1', 'VET': 'T1', 'BCH': 'T1',
            'ATOM': 'T1', 'ROSE': 'T1', 'DYDX': 'T1', 'IMX': 'T1',
            'AAVE': 'T1', 'XLM': 'T1', 'LINK': 'T1', 'SXP': 'T1',
            'ALGO': 'T1', 'CRV': 'T1',
            # T2: 中等盈利 - 1.0x仓位
            'ALPHA': 'T2', 'MKR': 'T2', 'ETC': 'T2', 'NEO': 'T2',
            'THETA': 'T2', 'ZEC': 'T2', 'RENDER': 'T2', 'GRT': 'T2',
            'SNX': 'T2', 'HBAR': 'T2', 'CELO': 'T2', 'ETH': 'T2',
            'FIL': 'T2', 'HYPE': 'T2', 'SHIB': 'T2', 'BNB': 'T2',
            'PYTH': 'T2', 'BTC': 'T2', 'LINA': 'T2', 'FLOKI': 'T2',
            'SEI': 'T2', 'XRP': 'T2', 'ORDI': 'T2',
            # T3: 低盈利 - 0.7x仓位
            'WIF': 'T3', 'FET': 'T3', 'LTC': 'T3', 'LEVER': 'T3',
            'MATIC': 'T3', 'ENA': 'T3', 'MANA': 'T3', 'PENGU': 'T3',
            'STRK': 'T3', 'INJ': 'T3', 'DOGE': 'T3', 'OP': 'T3',
            'TRUMP': 'T3', 'TRX': 'T3', 'ONE': 'T3', 'JUP': 'T3',
        }
        self.skip_coins = ['BERA', 'IP', 'LIT', 'TROY', 'VIRTUAL', 'BONK', 'PEPE']
        self.tier_multiplier = {'T1': 1.3, 'T2': 1.0, 'T3': 0.7}

        # === 状态 ===
        self.positions = {}
        self.last_close_time = None
        self._btc_trend_cache = None

        # === 数据库 (5001系统的DB) ===
        self.db_path = '/opt/trading-bot/data/db/paper_trading.db'
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self.init_database()
        self.load_positions()
        self._restore_capital()

        t1 = sum(1 for v in self.coin_tiers.values() if v == 'T1')
        t2 = sum(1 for v in self.coin_tiers.values() if v == 'T2')
        t3 = sum(1 for v in self.coin_tiers.values() if v == 'T3')
        print(f"[量化v4.1] 系统启动")
        print(f"  策略: 固定{self.fixed_leverage}x | ATR止损 | 最多{self.max_positions}仓 | SHORT+5%")
        print(f"  资金: {self.current_capital:.2f}U (初始{self.initial_capital}U)")
        print(f"  分层: T1={t1} T2={t2} T3={t3} 跳过={len(self.skip_coins)}")
        print(f"  币种: {len(self.watch_symbols)}个")

    # ==================== 数据库 (兼容5001 real_trades表) ====================
    ASSISTANT_NAME = '量化v4.1'
    MODE = 'paper'

    def init_database(self):
        """real_trades表已存在,不需要创建"""
        pass

    def _restore_capital(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            # 只计算v4.1自己的交易
            c.execute('SELECT COALESCE(SUM(pnl), 0) FROM real_trades WHERE status="CLOSED" AND assistant=?',
                      (self.ASSISTANT_NAME,))
            total_pnl = c.fetchone()[0]
            conn.close()
            self.current_capital = self.initial_capital + total_pnl
            if total_pnl != 0:
                print(f"  恢复: {self.initial_capital}U + {total_pnl:+.2f}U = {self.current_capital:.2f}U")
        except Exception as e:
            print(f"  恢复失败: {e}")

    def load_positions(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''SELECT symbol, direction, entry_price, amount, leverage, stop_loss, take_profit,
                         entry_time, score, final_stop_loss FROM real_trades
                         WHERE status="OPEN" AND assistant=?''', (self.ASSISTANT_NAME,))
            for row in c.fetchall():
                sym, dr, ep, amt, lev, sl, tp, et, sc, fsl = row
                self.positions[sym] = {
                    'direction': dr, 'entry_price': ep, 'amount': amt,
                    'leverage': lev, 'stop_loss': sl, 'take_profit': tp,
                    'entry_time': et, 'score': sc or 0,
                    'highest_price': ep if dr == 'LONG' else 0,
                    'lowest_price': ep if dr == 'SHORT' else float('inf')
                }
            conn.close()
            if self.positions:
                print(f"  持仓: {list(self.positions.keys())}")
        except Exception as e:
            print(f"  加载持仓失败: {e}")

    # ==================== 行情API ====================

    SYMBOL_1000 = {
        'BONK': '1000BONKUSDT', 'PEPE': '1000PEPEUSDT',
        'SHIB': '1000SHIBUSDT', 'FLOKI': '1000FLOKIUSDT',
    }

    def _bsym(self, symbol):
        return self.SYMBOL_1000.get(symbol, f"{symbol}USDT")

    def get_price(self, symbol):
        try:
            url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={self._bsym(symbol)}"
            r = requests.get(url, timeout=10)
            return float(r.json()['price'])
        except:
            return None

    def get_klines(self, symbol, interval='1h', limit=100):
        try:
            url = f"https://fapi.binance.com/fapi/v1/klines?symbol={self._bsym(symbol)}&interval={interval}&limit={limit}"
            r = requests.get(url, timeout=10)
            data = r.json()
            if isinstance(data, list):
                return data
        except:
            pass
        return None

    # ==================== 技术指标 ====================

    def calc_rsi(self, prices, period=14):
        if len(prices) < period + 1:
            return 50
        deltas = [prices[i] - prices[i-1] for i in range(1, len(prices))]
        gains = [d if d > 0 else 0 for d in deltas[-period:]]
        losses = [-d if d < 0 else 0 for d in deltas[-period:]]
        avg_g = sum(gains) / period
        avg_l = sum(losses) / period
        if avg_l == 0:
            return 100
        rs = avg_g / avg_l
        return 100 - (100 / (1 + rs))

    def calc_atr(self, klines, period=14):
        """计算ATR和ATR%"""
        if not klines or len(klines) < period + 1:
            return None, None
        highs = [float(k[2]) for k in klines]
        lows = [float(k[3]) for k in klines]
        closes = [float(k[4]) for k in klines]
        trs = []
        for i in range(1, len(klines)):
            tr = max(highs[i] - lows[i], abs(highs[i] - closes[i-1]), abs(lows[i] - closes[i-1]))
            trs.append(tr)
        atr = sum(trs[-period:]) / period
        price = closes[-1]
        atr_pct = (atr / price) * 100 if price > 0 else 0
        return atr, atr_pct

    def get_btc_trend(self):
        """BTC大盘趋势 (缓存5分钟)"""
        now = datetime.now()
        if self._btc_trend_cache:
            ct, cr = self._btc_trend_cache
            if (now - ct).total_seconds() < 300:
                return cr
        try:
            klines = self.get_klines('BTC', '1h', 100)
            if not klines:
                return {'direction': 'neutral', 'strength': 0}
            closes = [float(k[4]) for k in klines]
            ma7 = sum(closes[-7:]) / 7
            ma25 = sum(closes[-25:]) / 25
            ma50 = sum(closes[-50:]) / 50
            price = closes[-1]
            if price > ma7 > ma25 > ma50:
                d, s = 'up', 2
            elif price > ma7 > ma25:
                d, s = 'up', 1
            elif price < ma7 < ma25 < ma50:
                d, s = 'down', 2
            elif price < ma7 < ma25:
                d, s = 'down', 1
            else:
                d, s = 'neutral', 0
            result = {'direction': d, 'strength': s, 'price': price, 'ma7': ma7, 'ma25': ma25, 'ma50': ma50}
            self._btc_trend_cache = (now, result)
            return result
        except:
            return {'direction': 'neutral', 'strength': 0}

    # ==================== 信号分析 ====================

    def analyze_signal(self, symbol):
        """信号评分 (0-100) + 方向"""
        try:
            klines = self.get_klines(symbol, '1h', 100)
            if not klines or len(klines) < 50:
                return 0, None

            closes = [float(k[4]) for k in klines]
            volumes = [float(k[5]) for k in klines]
            highs = [float(k[2]) for k in klines]
            lows = [float(k[3]) for k in klines]
            price = closes[-1]

            votes = {'LONG': 0, 'SHORT': 0}

            # 1. RSI (30分)
            rsi = self.calc_rsi(closes)
            if rsi < 30:
                rsi_sc = 30; votes['LONG'] += 1
            elif rsi > 70:
                rsi_sc = 30; votes['SHORT'] += 1
            elif rsi < 45:
                rsi_sc = 15; votes['LONG'] += 1
            elif rsi > 55:
                rsi_sc = 15; votes['SHORT'] += 1
            else:
                rsi_sc = 5

            # 2. 趋势 (30分)
            ma7 = sum(closes[-7:]) / 7
            ma20 = sum(closes[-20:]) / 20
            ma50 = sum(closes[-50:]) / 50
            if price > ma7 > ma20 > ma50:
                trend_sc = 30; votes['LONG'] += 2
            elif price < ma7 < ma20 < ma50:
                trend_sc = 30; votes['SHORT'] += 2
            elif price > ma7 > ma20:
                trend_sc = 15; votes['LONG'] += 1
            elif price < ma7 < ma20:
                trend_sc = 15; votes['SHORT'] += 1
            else:
                trend_sc = 5

            # 3. 成交量 (20分)
            avg_vol = sum(volumes[-20:]) / 20
            vol_ratio = volumes[-1] / avg_vol if avg_vol > 0 else 1
            if vol_ratio > 1.5:
                vol_sc = 20
            elif vol_ratio > 1.2:
                vol_sc = 15
            elif vol_ratio > 1:
                vol_sc = 10
            else:
                vol_sc = 5

            # 4. 价格位置 (20分)
            h50 = max(highs[-50:])
            l50 = min(lows[-50:])
            pos = (price - l50) / (h50 - l50) if h50 > l50 else 0.5
            if pos < 0.2:
                pos_sc = 20; votes['LONG'] += 1
            elif pos > 0.8:
                pos_sc = 20; votes['SHORT'] += 1
            elif pos < 0.35:
                pos_sc = 10; votes['LONG'] += 1
            elif pos > 0.65:
                pos_sc = 10; votes['SHORT'] += 1
            else:
                pos_sc = 5

            # 方向
            if votes['LONG'] > votes['SHORT']:
                direction = 'LONG'
            elif votes['SHORT'] > votes['LONG']:
                direction = 'SHORT'
            else:
                direction = 'LONG' if rsi < 50 else 'SHORT'

            total = rsi_sc + trend_sc + vol_sc + pos_sc

            # === BTC趋势过滤 (v4.1: 温和版) ===
            btc = self.get_btc_trend()
            btc_dir = btc['direction']
            btc_str = btc['strength']

            # 个币自身趋势
            coin_own = False
            if direction == 'LONG' and price > ma7 > ma20:
                coin_own = True
            elif direction == 'SHORT' and price < ma7 < ma20:
                coin_own = True

            # v4.1温和惩罚 (vs v4.3: 更激进)
            if btc_dir == 'down' and direction == 'LONG':
                if coin_own:
                    total = int(total * 0.50)  # v4.1: 50% (v4.3是35%)
                elif btc_str >= 2:
                    total = int(total * 0.25)  # v4.1: 25% (v4.3是15%)
                else:
                    total = int(total * 0.40)  # v4.1: 40% (v4.3是25%)
            elif btc_dir == 'up' and direction == 'SHORT':
                if coin_own:
                    total = int(total * 0.75)
                elif btc_str >= 2:
                    total = int(total * 0.50)  # v4.1: 50% (v4.3是45%)
                else:
                    total = int(total * 0.65)  # v4.1: 65% (v4.3是60%)
            # v4.1: 没有 btc_below_ma50 额外检查

            # RSI与趋势冲突
            rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
            trend_dir = 'LONG' if price > ma20 else 'SHORT'
            if rsi_dir != trend_dir and not coin_own:
                total = int(total * 0.85)

            # SHORT加成
            if direction == 'SHORT':
                total = int(total * self.short_bias)

            atr, atr_pct = self.calc_atr(klines)

            analysis = {
                'price': price, 'rsi': rsi, 'ma7': ma7, 'ma20': ma20, 'ma50': ma50,
                'volume_ratio': vol_ratio, 'direction': direction, 'score': total,
                'btc_trend': btc_dir, 'coin_own_trend': coin_own,
                'atr': atr, 'atr_pct': atr_pct
            }
            return total, analysis

        except Exception as e:
            return 0, None

    # ==================== 仓位管理 ====================

    def calc_position_size(self, score, symbol=None):
        """v4.1仓位: 固定3x杠杆, 基于评分的仓位大小"""
        available = self.current_capital - sum(p['amount'] for p in self.positions.values())

        if score >= 85:
            size = min(200, available * 0.10)
        elif score >= 75:
            size = min(250, available * 0.15)
        elif score >= 70:
            size = min(200, available * 0.12)
        elif score >= 60:
            size = min(150, available * 0.08)
        else:
            return 0

        # Tier乘数
        if symbol:
            tier = self.coin_tiers.get(symbol, 'T3')
            mult = self.tier_multiplier.get(tier, 0.7)
            size = size * mult

        return max(50, int(size))

    def open_position(self, symbol, analysis):
        """开仓"""
        try:
            score = analysis['score']
            direction = analysis['direction']
            entry_price = analysis['price']
            leverage = self.fixed_leverage  # v4.1: 固定3x

            amount = self.calc_position_size(score, symbol)
            if amount < 50:
                return

            # v4.1: ATR止损 (3-8%, ATR*2倍)
            atr_pct = analysis.get('atr_pct')
            if atr_pct and atr_pct > 0:
                stop_pct = max(0.03, min(0.08, atr_pct * 2.0 / 100))
            else:
                stop_pct = 0.05  # 默认5%

            # 止盈 = 止损 * 2 (2:1 R:R)
            tp_pct = stop_pct * 2.0

            if direction == 'LONG':
                stop_loss = entry_price * (1 - stop_pct)
                take_profit = entry_price * (1 + tp_pct)
            else:
                stop_loss = entry_price * (1 + stop_pct)
                take_profit = entry_price * (1 - tp_pct)

            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

            self.positions[symbol] = {
                'direction': direction, 'entry_price': entry_price,
                'amount': amount, 'leverage': leverage,
                'stop_loss': stop_loss, 'take_profit': take_profit,
                'entry_time': now, 'score': score,
                'highest_price': entry_price if direction == 'LONG' else 0,
                'lowest_price': entry_price if direction == 'SHORT' else float('inf'),
            }

            # 写DB (real_trades表)
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            tier = self.coin_tiers.get(symbol, '?')
            reason_text = f"sc={score} RSI={analysis['rsi']:.1f} BTC={analysis['btc_trend']}"
            c.execute('''INSERT INTO real_trades (symbol, direction, entry_price, amount, leverage,
                         stop_loss, take_profit, entry_time, status, score, reason,
                         assistant, mode, entry_rsi, entry_trend,
                         original_stop_loss, original_take_profit)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, ?)''',
                      (symbol, direction, entry_price, amount, leverage,
                       stop_loss, take_profit, now, score, reason_text,
                       self.ASSISTANT_NAME, self.MODE,
                       analysis['rsi'], analysis['btc_trend'],
                       stop_loss, take_profit))
            conn.commit()
            conn.close()

            print(f"  >> OPEN {symbol} {direction} ${amount} @{entry_price:.6f} {leverage}x SL={stop_loss:.6f} TP={take_profit:.6f} sc={score}")

        except Exception as e:
            print(f"  开仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    def check_position(self, symbol, pos):
        """检查持仓"""
        try:
            price = self.get_price(symbol)
            if not price:
                return

            direction = pos['direction']
            ep = pos['entry_price']
            lev = pos['leverage']

            # ROI
            if direction == 'LONG':
                roi = ((price - ep) / ep) * lev * 100
            else:
                roi = ((ep - price) / ep) * lev * 100

            # 持仓时间
            hold_min = 0
            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hold_min = (datetime.now() - et).total_seconds() / 60
            except:
                pass

            # 3h最短保护 (除非亏>15% ROI)
            min_hold_protect = hold_min < self.min_hold_minutes and roi > -15

            should_close = False
            reason = ""

            # 1. 48h超时强制平仓
            if hold_min > self.max_hold_minutes:
                should_close = True
                reason = f"timeout {hold_min/60:.0f}h ROI={roi:+.1f}%"

            # 2. 止盈
            tp = pos.get('take_profit', 0)
            if not should_close and tp > 0:
                if direction == 'LONG' and price >= tp:
                    should_close = True
                    reason = f"TP hit @{price:.6f} ROI={roi:+.1f}%"
                elif direction == 'SHORT' and price <= tp:
                    should_close = True
                    reason = f"TP hit @{price:.6f} ROI={roi:+.1f}%"

            # 3. 止损 (v4.1: 固定SL也受3h保护)
            sl = pos.get('stop_loss', 0)
            if not should_close and sl > 0:
                hit = False
                if direction == 'LONG' and price <= sl:
                    hit = True
                elif direction == 'SHORT' and price >= sl:
                    hit = True
                if hit:
                    if min_hold_protect:
                        pass  # 3h保护
                    else:
                        should_close = True
                        reason = f"SL hit @{price:.6f} ROI={roi:+.1f}%"

            # 4. 移动追踪: 高/低点回撤止盈
            if not should_close:
                if direction == 'LONG':
                    if price > pos.get('highest_price', ep):
                        pos['highest_price'] = price
                    hp = pos['highest_price']
                    if hp > ep * 1.02:  # 至少涨2%才激活
                        drawdown = (hp - price) / hp
                        if drawdown > 0.015:  # 从高点回撤1.5%
                            should_close = True
                            trail_pnl = ((price - ep) / ep) * lev * 100
                            reason = f"trail TP @{price:.6f} peak={hp:.6f} ROI={trail_pnl:+.1f}%"
                else:
                    if price < pos.get('lowest_price', ep):
                        pos['lowest_price'] = price
                    lp = pos['lowest_price']
                    if lp < ep * 0.98:  # 至少跌2%才激活
                        drawup = (price - lp) / lp
                        if drawup > 0.015:
                            should_close = True
                            trail_pnl = ((ep - price) / ep) * lev * 100
                            reason = f"trail TP @{price:.6f} low={lp:.6f} ROI={trail_pnl:+.1f}%"

            if should_close:
                self.close_position(symbol, price, reason)

        except Exception as e:
            print(f"  检查{symbol}失败: {e}")

    def close_position(self, symbol, exit_price, reason):
        """平仓"""
        try:
            pos = self.positions.get(symbol)
            if not pos:
                return

            direction = pos['direction']
            ep = pos['entry_price']
            amount = pos['amount']
            lev = pos['leverage']

            if direction == 'LONG':
                pct = (exit_price - ep) / ep
            else:
                pct = (ep - exit_price) / ep

            roi = pct * lev * 100
            pnl_raw = amount * pct * lev

            # 手续费
            pos_val = amount * lev
            total_fee = pos_val * self.fee_rate * 2  # 开+平

            # 资金费
            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hours = (datetime.now() - et).total_seconds() / 3600
            except:
                hours = 0
            funding_fee = pos_val * 0.0001 * (hours / 8)

            pnl = pnl_raw - total_fee - funding_fee
            self.current_capital += pnl

            # 更新DB (real_trades表)
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''UPDATE real_trades SET exit_price=?, exit_time=?, status='CLOSED',
                         pnl=?, roi=?, fee=?, funding_fee=?,
                         close_reason=?, reason=reason||' | '||?,
                         final_stop_loss=?
                         WHERE symbol=? AND status='OPEN' AND assistant=?''',
                      (exit_price, now, pnl, roi, total_fee, funding_fee,
                       reason, reason,
                       pos.get('stop_loss', 0),
                       symbol, self.ASSISTANT_NAME))
            conn.commit()
            conn.close()

            del self.positions[symbol]
            self.last_close_time = datetime.now()

            mark = '+' if pnl > 0 else '-'
            print(f"  {mark} CLOSE {symbol} {direction} PnL={pnl:+.2f}U ROI={roi:+.1f}% | {reason}")

        except Exception as e:
            print(f"  平仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    # ==================== 主循环 ====================

    def scan_market(self):
        """扫描市场"""
        print(f"\n=== 扫描 {datetime.now().strftime('%H:%M:%S')} 持仓{len(self.positions)} 余额${self.current_capital:.2f} ===")

        opps = []
        for sym in self.watch_symbols:
            if sym in self.skip_coins or sym in self.positions:
                continue
            score, ana = self.analyze_signal(sym)
            if score >= self.min_score and ana:
                d = ana['direction']
                # v4.1: LONG需70+
                if d == 'LONG' and score < 70:
                    continue
                # v4: 85+ LONG跳过
                if score >= 85 and d == 'LONG':
                    continue
                # MA斜率过滤
                ma20 = ana.get('ma20', 0)
                ma50 = ana.get('ma50', 0)
                if ma20 > 0 and ma50 > 0:
                    slope = (ma20 - ma50) / ma50
                    if d == 'LONG' and slope < -0.01:
                        continue
                    if d == 'SHORT' and slope > 0.01:
                        continue
                tier = self.coin_tiers.get(sym, '?')
                opps.append((sym, score, ana))
                print(f"  * {sym}[{tier}] {score}分 {d}")

        opps.sort(key=lambda x: x[1], reverse=True)

        # 30分钟冷却
        if self.last_close_time:
            cd = (datetime.now() - self.last_close_time).total_seconds()
            if cd < 1800:
                print(f"  冷却中 (还剩{int((1800-cd)/60)}m)")
                return

        available = self.current_capital - sum(p['amount'] for p in self.positions.values())
        if len(self.positions) < self.max_positions and available > 100:
            opened = 0
            for sym, score, ana in opps:
                if opened >= 3 or len(self.positions) + opened >= self.max_positions:
                    break
                self.open_position(sym, ana)
                opened += 1

    def save_snapshot(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''INSERT INTO account_snapshots (timestamp, balance, equity, positions_count)
                         VALUES (?, ?, ?, ?)''',
                      (datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                       self.current_capital, self.current_capital, len(self.positions)))
            conn.commit()
            conn.close()
        except:
            pass

    def run(self, interval=60):
        """主循环"""
        print(f"\n>>> 开始运行 (每{interval}秒扫描)\n")
        scan_count = 0

        while True:
            try:
                # 检查持仓
                for sym in list(self.positions.keys()):
                    self.check_position(sym, self.positions[sym])

                # 扫描新机会
                self.scan_market()

                scan_count += 1

                # 每12次(~12分钟)存快照
                if scan_count % 12 == 0:
                    self.save_snapshot()
                    pnl = self.current_capital - self.initial_capital
                    print(f"\n  === 余额${self.current_capital:.2f} 盈亏${pnl:+.2f} 持仓{len(self.positions)} ===\n")

                time.sleep(interval)

            except KeyboardInterrupt:
                print(f"\n停止. 余额${self.current_capital:.2f} 盈亏${self.current_capital-self.initial_capital:+.2f}")
                break
            except Exception as e:
                print(f"错误: {e}")
                import traceback; traceback.print_exc()
                time.sleep(interval)


if __name__ == '__main__':
    trader = AutoTraderV41()
    trader.run(interval=60)
