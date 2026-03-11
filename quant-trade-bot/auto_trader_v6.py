#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
量化交易 v6 策略 — 智能整合版
v4.2评分基础 (RSI+MA+Volume+Position=100) + MACD/ADX/BB加分(+25)
3x杠杆 | 15仓位 | min_score 70 | 6年回测PnL最高 (+617K)

独立数据库: paper_trading.db (real_trades表, assistant='量化v6')
"""

import requests
import json
import time
import os
import sqlite3
from datetime import datetime, date

class AutoTraderV6:
    def __init__(self):
        # === v6 核心参数 ===
        self.initial_capital = 2000
        self.current_capital = 2000
        self.fee_rate = 0.0005
        self.min_score = 70
        self.long_min_score = 85
        self.max_positions = 15
        self.max_leverage = 3
        self.cooldown_seconds = 3600  # 60min
        self.min_hold_minutes = 60
        self.max_hold_minutes = 2880
        self.short_bias = 1.05

        # === 风控参数 ===
        self.max_drawdown_pct = 999     # 风控暂时关闭
        self.daily_loss_limit = 99999   # 风控暂时关闭
        self.risk_pause = False         # 风控暂停标志
        self.risk_position_multiplier = 1.0  # 仓位缩放

        # === v6 ROI止盈止损参数 ===
        self.roi_stop_loss = -10
        self.roi_trailing_start = 6
        self.roi_trailing_distance = 3

        # === 监控币种 ===
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
        self.skip_coins = ['BERA', 'IP', 'LIT', 'TROY', 'VIRTUAL', 'BONK', 'PEPE', 'DUSK', 'FARTCOIN', 'ANIME', 'LINA', 'LEVER', 'ALPHA', 'UNFI', 'PIXEL', 'XAI', 'DYM', 'BOME', 'DOGS', 'TURBO', 'SPX', 'MOODENG']

        # === 状态 ===
        self.positions = {}
        self.cooldowns = {}  # per-symbol cooldown
        self._last_mode_log = None
        self.ASSISTANT_NAME = '交易助手'
        self.MODE = 'paper' 

        # === 数据库 ===
        self.db_path = '/opt/trading-bot/quant-trade-bot/data/db/paper_trader.db'
        os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
        self.init_database()
        self.load_positions()
        self._restore_capital()

        print(f"[量化v6] 系统启动")
        print(f"  策略=v6智能整合 | {self.max_leverage}x | {self.max_positions}仓 | min_score={self.min_score}")

        print(f"  ROI: SL={self.roi_stop_loss}% | 移动止盈+{self.roi_trailing_start}%启动/{self.roi_trailing_distance}%回撤")
        print(f"  资金: {self.current_capital:.2f}U (初始{self.initial_capital}U)")
        print(f"  币种: {len(self.watch_symbols)}个 | 最多{self.max_positions}仓")

    # ==================== A/B 测试控制 ====================



    # ==================== 数据库 ====================

    def init_database(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''CREATE TABLE IF NOT EXISTS real_trades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                symbol TEXT NOT NULL, direction TEXT NOT NULL,
                entry_price REAL NOT NULL, exit_price REAL,
                amount REAL NOT NULL, leverage REAL DEFAULT 1.0,
                stop_loss REAL, take_profit REAL,
                entry_time DATETIME DEFAULT CURRENT_TIMESTAMP, exit_time DATETIME,
                status TEXT DEFAULT 'open', pnl REAL DEFAULT 0, roi REAL DEFAULT 0,
                reason TEXT, assistant TEXT DEFAULT '交易助手', fee REAL DEFAULT 0,
                mode TEXT DEFAULT 'paper', funding_fee REAL DEFAULT 0,
                score INTEGER DEFAULT 0, entry_rsi REAL, entry_trend TEXT,
                atr_pct REAL, max_profit REAL DEFAULT 0, max_loss REAL DEFAULT 0,
                duration_minutes INTEGER, initial_stop_loss REAL, initial_take_profit REAL,
                final_stop_loss REAL, stop_move_count INTEGER DEFAULT 0,
                original_stop_loss REAL, original_take_profit REAL, close_reason TEXT
            )''')
            c.execute('''CREATE TABLE IF NOT EXISTS account_snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME, balance REAL, equity REAL, positions_count INTEGER
            )''')
            conn.commit()
            conn.close()
        except Exception as e:
            print(f"  数据库初始化失败: {e}")

    def _restore_capital(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('SELECT COALESCE(SUM(pnl), 0) FROM real_trades WHERE status="CLOSED" AND assistant=?',
                      (self.ASSISTANT_NAME,))
            total_pnl = c.fetchone()[0]
            conn.close()
            self.current_capital = self.initial_capital + total_pnl
            if total_pnl != 0:
                print(f"  恢复: {self.initial_capital}U + {total_pnl:+.2f}U = {self.current_capital:.2f}U")
        except Exception as e:
            print(f"  恢复失败: {e}")

        # Restore per-symbol cooldowns from recent closed trades
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute("SELECT symbol, exit_time FROM real_trades WHERE status='CLOSED' AND assistant=? AND exit_time IS NOT NULL ORDER BY exit_time DESC LIMIT 30", (self.ASSISTANT_NAME,))
            for row in c.fetchall():
                sym, et = row
                if sym not in self.cooldowns and et:
                    close_time = datetime.strptime(et, '%Y-%m-%d %H:%M:%S')
                    elapsed = (datetime.now() - close_time).total_seconds()
                    if elapsed < self.cooldown_seconds:
                        self.cooldowns[sym] = close_time
                        print(f"  冷却恢复: {sym} 平仓{int(elapsed/60)}m前，还需等{int((self.cooldown_seconds-elapsed)/60)}m")
            conn.close()
        except Exception as e:
            print(f"  冷却恢复失败: {e}")

    def load_positions(self):
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''SELECT id, symbol, direction, entry_price, amount, leverage, stop_loss, take_profit,
                         entry_time, score, final_stop_loss, max_profit FROM real_trades
                         WHERE status="OPEN" AND assistant=?''', (self.ASSISTANT_NAME,))
            for row in c.fetchall():
                tid, sym, dr, ep, amt, lev, sl, tp, et, sc, fsl, mp = row
                # Restore peak_roi: use max_profit from DB, or calculate from current price
                peak_roi = 0
                if mp and mp > 0:
                    peak_roi = mp
                else:
                    # Calculate current ROI as baseline
                    try:
                        import requests as _req
                        SYMBOL_1000 = {'BONK': '1000BONKUSDT', 'PEPE': '1000PEPEUSDT',
                                       'SHIB': '1000SHIBUSDT', 'FLOKI': '1000FLOKIUSDT', 'NEIRO': '1000NEIROUSDT'}
                        bsym = SYMBOL_1000.get(sym, f"{sym}USDT")
                        r = _req.get(f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={bsym}", timeout=10)
                        price = float(r.json()['price'])
                        if dr == 'LONG':
                            peak_roi = ((price - ep) / ep) * lev * 100
                        else:
                            peak_roi = ((ep - price) / ep) * lev * 100
                        peak_roi = max(0, peak_roi)
                    except:
                        pass
                self.positions[sym] = {
                    'direction': dr, 'entry_price': ep, 'amount': amt,
                    'leverage': lev, 'stop_loss': sl, 'take_profit': tp,
                    'entry_time': et, 'score': sc or 0,
                    'peak_roi': peak_roi, 'trade_id': tid,
                }
                if peak_roi > 0:
                    print(f"    {sym}: peak_roi={peak_roi:.1f}% (恢复)")
            conn.close()
            if self.positions:
                print(f"  持仓: {list(self.positions.keys())}")
        except Exception as e:
            print(f"  加载持仓失败: {e}")

    # ==================== 行情API ====================

    SYMBOL_1000 = {
        'BONK': '1000BONKUSDT', 'PEPE': '1000PEPEUSDT',
        'SHIB': '1000SHIBUSDT', 'FLOKI': '1000FLOKIUSDT', 'NEIRO': '1000NEIROUSDT',
    }

    def _bsym(self, symbol):
        return self.SYMBOL_1000.get(symbol, f"{symbol}USDT")

    def get_price(self, symbol):
        try:
            url = f"https://fapi.binance.com/fapi/v1/ticker/price?symbol={self._bsym(symbol)}"
            r = requests.get(url, timeout=10)
            return float(r.json()['price'])
        except Exception as e:
            print(f"  价格获取失败 {symbol}: {e}")
            return None

    def get_klines(self, symbol, interval='1h', limit=100):
        try:
            url = f"https://fapi.binance.com/fapi/v1/klines?symbol={self._bsym(symbol)}&interval={interval}&limit={limit}"
            r = requests.get(url, timeout=10)
            data = r.json()
            if isinstance(data, list):
                return data
        except Exception as e:
            print(f"  K线获取失败 {symbol}: {e}")
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

    def calc_macd(self, closes, fast=12, slow=26, signal=9):
        """Calculate MACD indicator."""
        if len(closes) < slow + signal:
            return None
        def ema(data, period):
            k = 2 / (period + 1)
            result = [data[0]]
            for i in range(1, len(data)):
                result.append(data[i] * k + result[-1] * (1 - k))
            return result
        ema_fast = ema(closes, fast)
        ema_slow = ema(closes, slow)
        macd_line = [f - s for f, s in zip(ema_fast, ema_slow)]
        signal_line = ema(macd_line[slow-1:], signal)  # Start after slow EMA stabilizes
        hist = macd_line[-1] - signal_line[-1]
        prev_hist = macd_line[-2] - signal_line[-2]
        return {'macd': macd_line[-1], 'signal': signal_line[-1],
                'histogram': hist, 'prev_histogram': prev_hist,
                'crossover_up': prev_hist <= 0 and hist > 0,
                'crossover_down': prev_hist >= 0 and hist < 0}

    def calc_bollinger(self, closes, period=20, std_dev=2):
        """Calculate Bollinger Bands."""
        if len(closes) < period:
            return None
        sma = sum(closes[-period:]) / period
        variance = sum((c - sma) ** 2 for c in closes[-period:]) / period
        std = variance ** 0.5
        upper = sma + std_dev * std
        lower = sma - std_dev * std
        pct_b = (closes[-1] - lower) / (upper - lower) if upper > lower else 0.5
        bw = (upper - lower) / sma if sma > 0 else 0
        return {'upper': upper, 'middle': sma, 'lower': lower,
                'percent_b': pct_b, 'bandwidth': bw}

    def calc_adx(self, klines, period=14):
        """Calculate ADX (Average Directional Index)."""
        if len(klines) < period * 2 + 1:
            return None
        highs = [float(k[2]) for k in klines]
        lows = [float(k[3]) for k in klines]
        closes = [float(k[4]) for k in klines]
        tr_list, plus_dm, minus_dm = [], [], []
        for i in range(1, len(klines)):
            h, l, pc = highs[i], lows[i], closes[i-1]
            tr_list.append(max(h - l, abs(h - pc), abs(l - pc)))
            up = highs[i] - highs[i-1]
            dn = lows[i-1] - lows[i]
            plus_dm.append(up if up > dn and up > 0 else 0)
            minus_dm.append(dn if dn > up and dn > 0 else 0)
        def smooth(data, p):
            s = sum(data[:p])
            result = [s]
            for i in range(p, len(data)):
                s = s - s / p + data[i]
                result.append(s)
            return result
        atr = smooth(tr_list, period)
        s_plus = smooth(plus_dm, period)
        s_minus = smooth(minus_dm, period)
        dx_list = []
        for i in range(len(atr)):
            if atr[i] == 0:
                dx_list.append(0)
                continue
            di_p = 100 * s_plus[i] / atr[i]
            di_m = 100 * s_minus[i] / atr[i]
            di_sum = di_p + di_m
            dx_list.append(100 * abs(di_p - di_m) / di_sum if di_sum > 0 else 0)
        if len(dx_list) < period:
            return None
        adx = sum(dx_list[-period:]) / period
        return adx

    # ==================== v6 信号分析 ====================

    def analyze_signal(self, symbol):
        """v6信号: v4.2基础评分(0-100) + MACD/ADX/BB加分(0-25) = 最高125
        RSI(30) + MA趋势(30) + Volume(20) + PricePos(20) + Bonus(25)
        """
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

            # ═══ v4.2 BASE (0-100) ═══

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

            # 2. 趋势 (30分, 双权重)
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

            # 方向决定
            if votes['LONG'] > votes['SHORT']:
                direction = 'LONG'
            elif votes['SHORT'] > votes['LONG']:
                direction = 'SHORT'
            else:
                direction = 'LONG' if rsi < 50 else 'SHORT'

            total = rsi_sc + trend_sc + vol_sc + pos_sc

            # RSI/趋势冲突惩罚 (BEFORE bonus, matches Report v6b)
            rsi_dir = 'LONG' if rsi < 50 else 'SHORT'
            trend_dir = 'LONG' if price > ma20 else 'SHORT'
            if rsi_dir != trend_dir:
                total = int(total * 0.85)

            # ═══ V6 BONUS (0-25) ═══
            bonus = 0
            macd = self.calc_macd(closes)
            bb = self.calc_bollinger(closes)
            adx = self.calc_adx(klines)

            # MACD confirmation (+3/+6/+10) — crossover-based (matches Report v6b)
            if macd:
                hist = macd['histogram']
                prev_hist = macd['prev_histogram']
                macd_dir = 'LONG' if hist > 0 else 'SHORT'
                if macd_dir == direction:
                    if macd.get('crossover_up') or macd.get('crossover_down'):
                        bonus += 10  # crossover + direction match
                    elif (hist > 0 and hist > prev_hist) or \
                         (hist < 0 and hist < prev_hist):
                        bonus += 6   # momentum increasing + direction match
                    else:
                        bonus += 3   # direction match only

            # ADX trend strength (+2/+5/+8)
            if adx is not None:
                if adx >= 35:
                    bonus += 8
                elif adx >= 25:
                    bonus += 5
                elif adx >= 20:
                    bonus += 2

            # BB extreme position (+4/+7)
            if bb:
                pct_b = bb['percent_b']
                if direction == 'LONG':
                    if pct_b < 0.1:
                        bonus += 7
                    elif pct_b < 0.25:
                        bonus += 4
                else:
                    if pct_b > 0.9:
                        bonus += 7
                    elif pct_b > 0.75:
                        bonus += 4

            total += bonus

            # ═══ FILTERS ═══

            # SHORT bias
            if direction == 'SHORT':
                total = int(total * self.short_bias)

            # LONG 更高门槛
            if direction == 'LONG' and total < self.long_min_score:
                return 0, None

            analysis = {
                'price': price, 'rsi': rsi, 'ma7': ma7, 'ma20': ma20, 'ma50': ma50,
                'volume_ratio': vol_ratio, 'direction': direction, 'score': total,
                'bonus': bonus, 'adx': adx,
                'macd_hist': macd['histogram'] if macd else None,
                'bb_pctb': bb['percent_b'] if bb else None,
            }
            return total, analysis

        except Exception as e:
            return 0, None

    # ==================== 仓位管理 ====================

    def calc_position_size(self, score):
        available = self.current_capital - sum(p['amount'] for p in self.positions.values())
        leverage = min(3, self.max_leverage)

        if score >= 90:
            size = min(350, available * 0.15)
        elif score >= 80:
            size = min(280, available * 0.12)
        elif score >= 70:
            size = min(200, available * 0.10)
        elif score >= 60:
            size = min(150, available * 0.08)
        else:
            return 0, 0

        size = size * self.risk_position_multiplier
        if size < 50:
            return 0, 0
        return max(50, int(size)), leverage

    def open_position(self, symbol, analysis):
        try:
            score = analysis['score']
            direction = analysis['direction']
            entry_price = analysis['price']


            amount, leverage = self.calc_position_size(score)
            if amount < 50:
                return

            stop_pct = self.roi_stop_loss / (leverage * 100)
            tp_pct = self.roi_trailing_start / (leverage * 100)
            if direction == 'LONG':
                stop_loss = entry_price * (1 + stop_pct)
                take_profit = entry_price * (1 + tp_pct)
            else:
                stop_loss = entry_price * (1 - stop_pct)
                take_profit = entry_price * (1 - tp_pct)

            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

            bonus = analysis.get('bonus', 0)
            adx_val = analysis.get('adx')
            adx_str = f" ADX={adx_val:.0f}" if adx_val else ""
            reason_text = f"[v6] sc={score}(+{bonus}) RSI={analysis['rsi']:.1f} {direction} {leverage}x{adx_str}"

            # DB check and INSERT first, before writing to memory
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute("SELECT COUNT(*) FROM real_trades WHERE symbol=? AND status='OPEN' AND assistant=?",
                      (symbol, self.ASSISTANT_NAME))
            if c.fetchone()[0] > 0:
                conn.close()
                print(f"  跳过 {symbol}: DB中已有OPEN记录")
                return
            c.execute('''INSERT INTO real_trades (symbol, direction, entry_price, amount, leverage,
                         stop_loss, take_profit, entry_time, status, score, reason,
                         assistant, mode, entry_rsi, entry_trend,
                         original_stop_loss, original_take_profit)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'OPEN', ?, ?, ?, ?, ?, ?, ?, ?)''',
                      (symbol, direction, entry_price, amount, leverage,
                       stop_loss, take_profit, now, score, reason_text,
                       self.ASSISTANT_NAME, self.MODE,
                       analysis['rsi'], 'v6-paper',
                       stop_loss, take_profit))
            trade_id = c.lastrowid
            conn.commit()
            conn.close()

            # Only write to memory AFTER successful DB insert
            self.positions[symbol] = {
                'direction': direction, 'entry_price': entry_price,
                'amount': amount, 'leverage': leverage,
                'stop_loss': stop_loss, 'take_profit': take_profit,
                'entry_time': now, 'score': score,
                'peak_roi': 0, 'trade_id': trade_id,
            }

            print(f"  >> [v6] OPEN {symbol} {direction} ${amount} @{entry_price:.6f} {leverage}x SL={stop_loss:.6f} sc={score}(+{bonus})")

        except Exception as e:
            print(f"  开仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    def check_position(self, symbol, pos):
        try:
            price = self.get_price(symbol)
            if not price:
                return

            direction = pos['direction']
            ep = pos['entry_price']
            lev = pos['leverage']

            if direction == 'LONG':
                roi = ((price - ep) / ep) * lev * 100
            else:
                roi = ((ep - price) / ep) * lev * 100

            if roi > pos.get('peak_roi', 0):
                pos['peak_roi'] = roi
                # Persist peak_roi to DB
                try:
                    conn = sqlite3.connect(self.db_path)
                    conn.execute("UPDATE real_trades SET max_profit=? WHERE id=? AND status='OPEN'",
                                 (roi, pos.get('trade_id', 0)))
                    conn.commit()
                    conn.close()
                except:
                    pass

            peak_roi = pos['peak_roi']

            hold_min = 0
            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hold_min = (datetime.now() - et).total_seconds() / 60
            except Exception as e:
                print(f"  时间解析失败 {symbol}: {e}")

            # min_hold_protect: 持仓<60min时，-10%到-15%之间不触发止损
            # 但 -15% 是硬底线，任何时候都强制止损
            min_hold_protect = hold_min < self.min_hold_minutes and roi > self.roi_stop_loss

            should_close = False
            reason = ""

            if hold_min > self.max_hold_minutes:
                should_close = True
                reason = f"timeout {hold_min/60:.0f}h ROI={roi:+.1f}%"

            # 硬底线: ROI <= -15% 无条件强制止损
            if not should_close and roi <= -15:
                should_close = True
                reason = f"HARD SL hit ROI={roi:+.1f}% (floor=-15%)"

            if not should_close and roi <= self.roi_stop_loss:
                if min_hold_protect:
                    pass
                else:
                    should_close = True
                    reason = f"SL hit ROI={roi:+.1f}% (limit={self.roi_stop_loss}%)"

            if not should_close and peak_roi >= self.roi_trailing_start:
                trail_exit_roi = peak_roi - self.roi_trailing_distance
                if roi <= trail_exit_roi:
                    should_close = True
                    if trail_exit_roi > 0:
                        reason = f"trail TP @{price:.6f} peakROI={peak_roi:+.1f}% exitROI={trail_exit_roi:+.1f}%"
                    else:
                        reason = f"trail SL @{price:.6f} peakROI={peak_roi:+.1f}% exitROI={trail_exit_roi:+.1f}%"

            if should_close:
                self.close_position(symbol, price, reason)

        except Exception as e:
            print(f"  检查{symbol}失败: {e}")

    def close_position(self, symbol, exit_price, reason):
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

            roi_pct = pct * lev * 100  # For display: +21.8%
            roi_db = pct * lev        # For DB storage: 0.218
            pnl_raw = amount * pct * lev

            pos_val = amount * lev
            total_fee = pos_val * self.fee_rate * 2

            try:
                et = datetime.strptime(pos['entry_time'], '%Y-%m-%d %H:%M:%S')
                hours = (datetime.now() - et).total_seconds() / 3600
            except Exception as e:
                hours = 0
            funding_fee = pos_val * 0.0001 * (hours / 8)

            pnl = pnl_raw - total_fee - funding_fee

            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()
            c.execute('''UPDATE real_trades SET exit_price=?, exit_time=?, status='CLOSED',
                         pnl=?, roi=?, fee=?, funding_fee=?,
                         close_reason=?, reason=reason||' | '||?,
                         final_stop_loss=?
                         WHERE id=? AND status='OPEN' ''',
                      (exit_price, now, pnl, roi_db, total_fee, funding_fee,
                       reason, reason,
                       pos.get('stop_loss', 0),
                       pos.get('trade_id', 0)))
            conn.commit()
            conn.close()

            # Only update memory after DB success
            self.current_capital += pnl
            self.cooldowns[symbol] = datetime.now()
            del self.positions[symbol]

            mark = '+' if pnl > 0 else '-'
            print(f"  {mark} CLOSE {symbol} {direction} PnL={pnl:+.2f}U ROI={roi_pct:+.1f}% | {reason}")

        except Exception as e:
            print(f"  平仓失败 {symbol}: {e}")
            import traceback; traceback.print_exc()

    # ==================== 主循环 ====================

    def check_risk(self):
        """风控检查 - 每次扫描前执行"""
        try:
            conn = sqlite3.connect(self.db_path)
            c = conn.cursor()

            # 1. 连续亏损
            c.execute("SELECT pnl FROM real_trades WHERE status='CLOSED' AND assistant=? ORDER BY exit_time DESC LIMIT 10", (self.ASSISTANT_NAME,))
            recent = [r[0] for r in c.fetchall()]
            consecutive_losses = 0
            for pnl in recent:
                if pnl and pnl < 0:
                    consecutive_losses += 1
                else:
                    break

            # 2. 回撤
            c.execute("SELECT COALESCE(SUM(pnl), 0) FROM real_trades WHERE status='CLOSED' AND assistant=?", (self.ASSISTANT_NAME,))
            total_pnl = c.fetchone()[0]
            current_capital = self.initial_capital + total_pnl

            c.execute("SELECT pnl FROM real_trades WHERE status='CLOSED' AND assistant=? ORDER BY exit_time", (self.ASSISTANT_NAME,))
            all_pnl = [r[0] for r in c.fetchall()]
            peak = self.initial_capital
            cum = self.initial_capital
            for p in all_pnl:
                cum += (p or 0)
                peak = max(peak, cum)
            drawdown = ((peak - current_capital) / peak * 100) if peak > 0 else 0

            # 3. 今日亏损
            today = date.today().isoformat()
            c.execute("SELECT COALESCE(SUM(pnl), 0) FROM real_trades WHERE status='CLOSED' AND assistant=? AND DATE(exit_time)=?", (self.ASSISTANT_NAME, today))
            daily_pnl = c.fetchone()[0]
            conn.close()

            # === 风控决策 ===
            old_pause = self.risk_pause
            old_mult = self.risk_position_multiplier

            # 回撤超限 -> 暂停（含自动恢复）
            if drawdown > self.max_drawdown_pct:
                recent_wins = sum(1 for p in recent[:3] if p and p > 0)
                if recent_wins >= 2 and len(recent) >= 3:
                    self.risk_pause = False
                    self.risk_position_multiplier = 0.5
                    print(f"  [风控] 回撤{drawdown:.1f}%超限但近3笔{recent_wins}盈 -> 自动恢复(仓位50%)")
                else:
                    self.risk_pause = True
                    self.risk_position_multiplier = 0
                    print(f"  [风控] 回撤{drawdown:.1f}% > {self.max_drawdown_pct}% -> 暂停开仓")

            # 每日亏损超限 -> 暂停
            elif daily_pnl < 0 and abs(daily_pnl) > self.daily_loss_limit:
                self.risk_pause = True
                self.risk_position_multiplier = 0
                print(f"  [风控] 今日亏损{daily_pnl:.2f}U > {self.daily_loss_limit}U -> 暂停开仓")

            # 连续亏损>=5 -> 暂停
            elif consecutive_losses >= 5:
                self.risk_pause = True
                self.risk_position_multiplier = 0.3
                print(f"  [风控] 连续亏损{consecutive_losses}笔 -> 暂停开仓(仓位30%)")

            # 连续亏损>=3 -> 减仓
            elif consecutive_losses >= 3:
                self.risk_pause = False
                self.risk_position_multiplier = 0.5
                print(f"  [风控] 连续亏损{consecutive_losses}笔 -> 仓位减半")

            # 正常
            else:
                self.risk_pause = False
                self.risk_position_multiplier = 1.0

            # 状态变化时打印
            if old_pause != self.risk_pause or abs(old_mult - self.risk_position_multiplier) > 0.01:
                status = "暂停" if self.risk_pause else "正常"
                print(f"  [风控] 状态={status} 仓位={self.risk_position_multiplier:.0%} 连亏={consecutive_losses} 回撤={drawdown:.1f}% 日亏={daily_pnl:.2f}U")

        except Exception as e:
            print(f"  [风控] 检查失败: {e}")

    def scan_market(self):
        print(f"\n=== [v6] 扫描 {datetime.now().strftime('%H:%M:%S')} 持仓{len(self.positions)} 余额${self.current_capital:.2f} ===")

        opps = []
        for sym in self.watch_symbols:
            if sym in self.skip_coins or sym in self.positions:
                continue
            score, ana = self.analyze_signal(sym)
            if score >= self.min_score and ana:
                d = ana['direction']
                bonus = ana.get('bonus', 0)
                opps.append((sym, score, ana))
                print(f"  * {sym} {score}分(+{bonus}) {d}")

        opps.sort(key=lambda x: x[1], reverse=True)

        available = self.current_capital - sum(p['amount'] for p in self.positions.values())

        if len(self.positions) < self.max_positions and available > 100:
            opened = 0
            for sym, score, ana in opps:
                if opened >= 3 or len(self.positions) + opened >= self.max_positions:
                    break
                # Per-symbol cooldown check
                if sym in self.cooldowns:
                    elapsed = (datetime.now() - self.cooldowns[sym]).total_seconds()
                    if elapsed < self.cooldown_seconds:
                        continue
                    else:
                        del self.cooldowns[sym]
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
        except Exception as e:
            print(f"  快照保存失败: {e}")

    def run(self, interval=60):
        print(f"\n>>> 开始运行 (每{interval}秒扫描)\n")
        scan_count = 0

        while True:
            try:
                for sym in list(self.positions.keys()):
                    self.check_position(sym, self.positions[sym])

                self.check_risk()
                self.scan_market()

                scan_count += 1

                if scan_count % 12 == 0:
                    self.save_snapshot()
                    pnl = self.current_capital - self.initial_capital
                    print(f"\n  === 余额${self.current_capital:.2f} 盈亏${pnl:+.2f} 持仓{len(self.positions)} ===\n")

                # 每小时打印PnL
                if scan_count % 60 == 0:
                    pnl = self.current_capital - self.initial_capital
                    print(f"\n  === [v6] 1h汇总: 余额${self.current_capital:.2f} 盈亏${pnl:+.2f} 持仓{len(self.positions)} ===")

                time.sleep(interval)

            except KeyboardInterrupt:
                print(f"\n停止. 余额${self.current_capital:.2f} 盈亏${self.current_capital-self.initial_capital:+.2f}")
                break
            except Exception as e:
                print(f"错误: {e}")
                import traceback; traceback.print_exc()
                time.sleep(interval)


if __name__ == '__main__':
    trader = AutoTraderV6()
    trader.run(interval=20)
